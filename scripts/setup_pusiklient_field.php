#!/usr/bin/env php
<?php
/**
 * Make the "Püsiklient?" customer custom field the single source of truth for the
 * loyal-customer discount.
 *
 *  1. Resolves the customer custom field whose label matches "püsiklient" (or use
 *     --field-id=) and stores its internal id in option yumefit_pusiklient_field_id,
 *     which the latepoint-yumefit-rules plugin reads to decide eligibility.
 *  2. Backfills the field for existing loyal customers: every customer with the
 *     legacy meta is_pusiklient=yes gets the checkbox value 'on' written under the
 *     field's id (this is exactly what the admin form stores when the box is ticked,
 *     so it shows checked and toggling it later works normally).
 *
 * After this runs, ticking/unticking the "Püsiklient?" box in the customer admin
 * directly turns the discount on/off — there is no separate hidden flag to keep in
 * sync. Idempotent: re-running only writes missing 'on' values.
 *
 * Usage: php -d memory_limit=1024M setup_pusiklient_field.php [--field-id=...] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['field-id:', 'dry-run']);
$fieldIdArg = isset($opts['field-id']) ? trim((string) $opts['field-id']) : '';
$dryRun = isset($opts['dry-run']);

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsSettingsHelper') || !class_exists('OsMetaHelper')) {
    fwrite(STDERR, "LatePoint (Pro custom fields) not loaded\n");
    exit(1);
}
global $wpdb; $P = $wpdb->prefix;

echo "=========== Püsiklient custom field setup ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "=====================================================\n";

// --- 1. resolve the custom field id ---
$json = OsSettingsHelper::get_settings_value('custom_fields_for_customer', false);
$fields = $json ? json_decode($json, true) : [];
if (!is_array($fields) || !$fields) {
    fwrite(STDERR, "No customer custom fields defined.\n");
    exit(1);
}

$fieldId = '';
if ($fieldIdArg !== '') {
    if (!isset($fields[$fieldIdArg])) {
        fwrite(STDERR, "--field-id={$fieldIdArg} not found among customer custom fields.\n");
        exit(1);
    }
    $fieldId = $fieldIdArg;
} else {
    foreach ($fields as $id => $f) {
        $label = (string) ($f['label'] ?? '');
        if (mb_stripos($label, 'püsiklient') !== false || mb_stripos($label, 'pusiklient') !== false) {
            $fieldId = (string) $id;
            break;
        }
    }
}

if ($fieldId === '') {
    fwrite(STDERR, "Could not find a 'Püsiklient?' customer custom field. Available fields:\n");
    foreach ($fields as $id => $f) {
        fwrite(STDERR, sprintf("  id=%s  type=%s  label=%s\n", $id, $f['type'] ?? '?', $f['label'] ?? ''));
    }
    fwrite(STDERR, "Pass --field-id=<id> to pick one explicitly.\n");
    exit(1);
}

$field = $fields[$fieldId];
$type  = (string) ($field['type'] ?? '');
echo "Resolved field: id={$fieldId}  type={$type}  label=" . ($field['label'] ?? '') . "\n";
if ($type !== 'checkbox') {
    fwrite(STDERR, "WARNING: field is '{$type}', not 'checkbox'. The plugin treats 'on' as eligible; verify this is correct.\n");
}

if (!$dryRun) {
    update_option('yumefit_pusiklient_field_id', $fieldId, false);
    echo "Stored option yumefit_pusiklient_field_id={$fieldId}\n";
}

// --- 2. backfill 'on' for existing loyal customers ---
$loyalIds = $wpdb->get_col(
    "SELECT object_id FROM {$P}latepoint_customer_meta WHERE meta_key='is_pusiklient' AND meta_value='yes'"
);
echo "Loyal customers (is_pusiklient=yes): " . count($loyalIds) . "\n";

$already = 0; $written = 0;
foreach ($loyalIds as $cid) {
    $cid = (int) $cid;
    $current = OsMetaHelper::get_customer_meta_by_key($fieldId, $cid, '');
    if ($current === 'on') {
        $already++;
        continue;
    }
    $written++;
    if (!$dryRun) {
        OsMetaHelper::save_customer_meta_by_key($fieldId, 'on', $cid);
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Field checked-value 'on' written: %d (already set: %d)\n", $written, $already);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done. The 'Püsiklient?' box now reflects loyalty and drives the discount.\n");
