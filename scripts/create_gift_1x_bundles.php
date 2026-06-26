#!/usr/bin/env php
<?php
/**
 * Create 1× single-service bundles so single sessions can be sold/gifted through the
 * native LatePoint flow (a bundle purchase skips the datepicker, so the gift voucher
 * works the same as for multi-session packages — see latepoint-yumefit-rules gift layer).
 *
 * Idempotent: skips a service that already has a quantity-1 single-service bundle.
 * Mirrors the existing EMS bundle config (total_attendees + duration per service).
 *
 * Usage: php -d memory_limit=1024M create_gift_1x_bundles.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$dryRun = in_array('--dry-run', $argv, true);

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsBundleModel')) { fwrite(STDERR, "LatePoint not loaded\n"); exit(1); }

global $wpdb; $P = $wpdb->prefix;
function K($n, $f) { return defined($n) ? constant($n) : $f; }

// service_id => display suffix; price/duration/attendees are read from the service.
$SERVICES = [3, 4]; // EMS personaaltreening, EMS treening kahekesi

echo '=========== Create 1× gift bundles ===========' . "\n";
echo 'DB: ' . DB_NAME . ' | ' . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";

foreach ($SERVICES as $svcId) {
    $svc = $wpdb->get_row($wpdb->prepare("SELECT id,name,duration,charge_amount FROM {$P}latepoint_services WHERE id=%d", $svcId));
    if (!$svc) { echo "  ! service #{$svcId} not found — skipped\n"; continue; }

    // idempotency: existing single-service quantity-1 bundle for this service?
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT bs.bundle_id FROM {$P}latepoint_bundles_services bs
         WHERE bs.service_id=%d AND bs.quantity=1
           AND (SELECT COUNT(*) FROM {$P}latepoint_bundles_services x WHERE x.bundle_id=bs.bundle_id)=1
         LIMIT 1", $svcId));
    if ($existing) { echo "  = service '{$svc->name}' already has 1× bundle #{$existing} — skipped\n"; continue; }

    $name  = $svc->name . ' (1 kord)';
    $price = (float) $svc->charge_amount;
    $dur   = (int) ($svc->duration ?: 45);
    echo "  + '{$name}' (svc #{$svcId} ×1, €{$price}, {$dur}min)\n";
    if ($dryRun) { continue; }

    $b = new OsBundleModel();
    $b->name          = $name;
    $b->charge_amount = $price;
    $b->status        = K('LATEPOINT_BUNDLE_STATUS_ACTIVE', 'active');
    $b->visibility    = K('LATEPOINT_BUNDLE_VISIBILITY_VISIBLE', 'visible');
    if (!$b->save()) { echo "    ! save failed\n"; continue; }
    $b->save_services(['service_' . $svcId => [
        'quantity' => 1, 'total_attendees' => 1, 'duration' => $dur, 'connected' => 'yes',
    ]]);
    echo "    -> bundle #{$b->id}\n";
}

echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
