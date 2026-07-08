#!/usr/bin/env php
<?php
/**
 * One-time Klaviyo import (and ongoing-sync kickoff).
 *
 * Runs the same code path the daily cron uses (yumefit_klaviyo_sync_run in
 * latepoint-yumefit-rules): bulk-upserts every LatePoint customer to Klaviyo with
 * latepoint_bookings + pusiklient profile properties, and subscribes customers
 * never sent before to email/SMS/WhatsApp marketing (historical import — no
 * double-opt-in mails, consented_at = customer created_at). Segments are then
 * defined once in the Klaviyo UI on those properties.
 *
 * --key=pk_... stores the private API key (Klaviyo → Settings → API keys; scopes:
 * profiles full, subscriptions full, lists read) in option yumefit_klaviyo_private_key,
 * which the daily cron uses from then on. Only needed once.
 *
 * Usage: YUMEFIT_CLI_SHIM=1 php klaviyo_import.php [--key=pk_...] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['key:', 'dry-run']);
$dryRun = isset($opts['dry-run']);

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!function_exists('yumefit_klaviyo_sync_run')) {
    // prod loads latepoint-yumefit-rules outside active_plugins; locally pull it in by hand
    require_once __DIR__ . '/../latepoint-yumefit-rules/latepoint-yumefit-rules.php';
}
if (!class_exists('OsMetaHelper')) {
    fwrite(STDERR, "LatePoint not loaded — run with YUMEFIT_CLI_SHIM=1\n");
    exit(1);
}

echo "=============== Klaviyo import ===============\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "==============================================\n";

if (!empty($opts['key'])) {
    $key = trim((string) $opts['key']);
    if (!preg_match('/^pk_\w{30,}$/', $key)) { fwrite(STDERR, "--key does not look like a Klaviyo private key (pk_...)\n"); exit(1); }
    $dryRun || update_option('yumefit_klaviyo_private_key', $key, false);
    echo "Private API key " . ($dryRun ? 'validated (not stored, dry-run)' : 'stored in option yumefit_klaviyo_private_key') . "\n";
}

$customers = yumefit_klaviyo_customers();
$withPhone = array_filter($customers, fn($c) => $c->phone !== '');
$pusiklient = array_filter($customers, fn($c) => $c->pusiklient);
$booked = array_filter($customers, fn($c) => $c->bookings >= 1);
$alreadySubscribed = (array) get_option('yumefit_klaviyo_subscribed_ids', []);

echo sprintf("Customers: %d  (phone ok: %d, püsiklient: %d, ≥1 booking: %d, already subscribed: %d)\n",
    count($customers), count($withPhone), count($pusiklient), count($booked), count($alreadySubscribed));

if ($dryRun) {
    foreach (array_slice($customers, 0, 3) as $c) {
        echo sprintf("  sample: %s | phone=%s | bookings=%d | pusiklient=%s\n",
            $c->email, $c->phone ?: '-', $c->bookings, $c->pusiklient ? 'yes' : 'no');
    }
    echo "DRY-RUN — nothing sent to Klaviyo.\n";
    exit(0);
}

$summary = yumefit_klaviyo_sync_run();

echo "\n=================== SUMMARY ===================\n";
echo sprintf("Profiles upserted:  %d\nNewly subscribed:   %d\n", $summary['profiles'], $summary['newly_subscribed']);
foreach ($summary['errors'] as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}
if (!$summary['errors']) {
    echo "Done. Daily cron keeps this fresh from now on.\n";
}
exit($summary['errors'] ? 1 : 0);
