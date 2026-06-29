#!/usr/bin/env php
<?php
/**
 * Split the "New Booking Notification" automation (process #1, booking_created,
 * 2 emails) into three service-scoped copies so first-time EMS bookings can get
 * their own onboarding emails:
 *
 *   - original  -> all OTHER services (service_id NOT in 1,2)
 *   - copy "Esmakordne EMS"          -> service_id = 1
 *   - copy "Esmakordne EMS kahekesi" -> service_id = 2
 *
 * The copies are exact duplicates of the original's two emails (fresh action
 * ids). Edit their content afterwards in LatePoint -> Automations.
 *
 * Idempotent: re-running rebuilds the same three processes (matched by name),
 * never double-wrapping or duplicating.
 *
 * Boots full LatePoint against the live DB, so it needs the CLI shim mu-plugin
 * (see [[local-cli-against-prod-db]]):
 *   YUMEFIT_IMPORT_CLI=1 php -d memory_limit=1024M setup_ems_booking_emails.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }

$opts   = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);

$ORIGINAL_ID   = 1;            // "New Booking Notification"
$ESMANE_ID     = '1';         // Esmakordne EMS konsultatsioon + personaaltreening
$ESMANE_2X_ID  = '2';         // Esmakordne EMS konsultatsioon + treening kahekesi
$COPY_ESMANE_NAME    = 'New Booking Notification — Esmakordne EMS';
$COPY_ESMANE_2X_NAME = 'New Booking Notification — Esmakordne EMS kahekesi';

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsProcessModel')) { fwrite(STDERR, "LatePoint not loaded (run with YUMEFIT_IMPORT_CLI=1)\n"); exit(1); }

echo "=========== EMS booking-email split ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "===============================================\n";

/** Recursively collect the type=action items from a decoded actions_json tree. */
function collect_action_items(array $groups): array {
    $actions = [];
    foreach ($groups as $node) {
        if (($node['type'] ?? '') === 'action') { $actions[] = $node; }
        if (!empty($node['items'])) { $actions = array_merge($actions, collect_action_items($node['items'])); }
    }
    return $actions;
}

/** Give every action item a fresh id (so copies don't share ids with the source). */
function reid_action_items(array $items): array {
    foreach ($items as &$item) {
        $item['id'] = 'pa_' . OsUtilHelper::random_text('alnum', 6);
    }
    return $items;
}

/** Wrap action items in a single service_id trigger-condition group. */
function build_actions_json(string $operator, string $value, array $actionItems): string {
    $groups = [[
        'type'              => 'group',
        'trigger_condition' => ['property' => 'booking__service_id'],
        'items'             => [[
            'type'     => 'trigger_condition_branch',
            'settings' => ['operator' => $operator, 'value' => $value],
            'items'    => [[
                'type'              => 'group',
                'trigger_condition' => false,
                'items'             => $actionItems,
                'time_offset'       => [],
            ]],
        ]],
        'time_offset'       => [],
    ]];
    return wp_json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$original = new OsProcessModel($ORIGINAL_ID);
if (!$original->id) { fwrite(STDERR, "Process #{$ORIGINAL_ID} not found\n"); exit(1); }

$sourceItems = collect_action_items(json_decode($original->actions_json, true) ?: []);
if (count($sourceItems) < 1) { fwrite(STDERR, "No actions found on process #{$ORIGINAL_ID}\n"); exit(1); }
echo "Source: #{$original->id} \"{$original->name}\" ({$original->event_type}), " . count($sourceItems) . " action(s)\n\n";

$excludeList = "{$ESMANE_ID},{$ESMANE_2X_ID}";

// 1) Restrict the original to all OTHER services.
echo "ORIGINAL #{$original->id}: trigger only if service_id NOT IN [{$excludeList}]\n";
if (!$dryRun) {
    $original->actions_json = build_actions_json('not_equal', $excludeList, $sourceItems);
    if (!$original->save()) { fwrite(STDERR, "Save failed: " . implode('; ', $original->get_error_messages()) . "\n"); exit(1); }
}

/** Create or update a service-scoped copy (matched by name) of the original's emails. */
function upsert_copy(string $name, string $serviceId, array $sourceItems, bool $dryRun): void {
    $existing = (new OsProcessModel())->where(['name' => $name, 'event_type' => 'booking_created'])->set_limit(1)->get_results_as_models();
    $copy = ($existing && $existing->id) ? $existing : new OsProcessModel();
    $verb = $copy->id ? "UPDATE #{$copy->id}" : "CREATE";
    echo "{$verb} \"{$name}\": trigger only if service_id = {$serviceId}\n";
    if ($dryRun) { return; }

    $copy->name         = $name;
    $copy->event_type   = 'booking_created';
    $copy->status       = LATEPOINT_STATUS_ACTIVE;
    $copy->actions_json = build_actions_json('equal', $serviceId, reid_action_items($sourceItems));
    if (!$copy->save()) { fwrite(STDERR, "Save failed for \"{$name}\": " . implode('; ', $copy->get_error_messages()) . "\n"); exit(1); }
}

// 2) + 3) The two first-time EMS copies.
upsert_copy($COPY_ESMANE_NAME, $ESMANE_ID, $sourceItems, $dryRun);
upsert_copy($COPY_ESMANE_2X_NAME, $ESMANE_2X_ID, $sourceItems, $dryRun);

echo "\n" . ($dryRun ? "DRY-RUN — no changes written.\n" : "Done. Edit copy email content in LatePoint -> Automations.\n");
