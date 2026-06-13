#!/usr/bin/env php
<?php
/**
 * Create/update a LatePoint "shared pool" bundle: N redemptions usable across
 * ALL active services except group trainings ("rühmatreening"), with a single
 * shared total (not per-service). The shared cap is enforced at booking time by
 * the companion plugin `latepoint-yumefit-rules`, which reads the bundle ids +
 * caps from the `yumefit_shared_pool_bundles` option this script writes.
 *
 * Idempotent. Usage:
 *   php setup_shared_pool_bundle.php [--name="..."] [--total=5] [--price=149]
 *                                    [--exclude-like=hmatreening] [--dry-run]
 *
 * Run with: php -d memory_limit=1024M setup_shared_pool_bundle.php ...
 */

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }

$opts  = getopt('', ['name:', 'total:', 'price:', 'exclude-like:', 'dry-run']);
$name  = $opts['name']  ?? 'Stuudio pakett (kõik teenused v.a jooga rühmatreening)';
$total = (int) ($opts['total'] ?? 5);
$price = (float) ($opts['price'] ?? 149);
$excludeLike = $opts['exclude-like'] ?? 'hmatreening'; // matches "rühmatreening" (ASCII-safe substring)
$dryRun = isset($opts['dry-run']);

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? 'yumefit.ee';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define('WP_USE_THEMES', false);
require $wpLoad;

if (!class_exists('OsBundleModel')) { fwrite(STDERR, "LatePoint not loaded\n"); exit(1); }

global $wpdb;
$P = $wpdb->prefix;

echo "=========== Shared-pool bundle setup ===========\n";
echo "DB     : " . DB_NAME . " @ " . DB_HOST . "\n";
echo "Mode   : " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "Bundle : \"{$name}\"  total={$total}  price=€{$price}\n";
echo "Exclude: services whose name contains \"{$excludeLike}\"\n";
echo "================================================\n";

// included services = active, name does NOT contain the exclude term
$services = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, duration FROM {$P}latepoint_services
     WHERE status=%s AND name NOT LIKE %s ORDER BY id",
    LATEPOINT_SERVICE_STATUS_ACTIVE, '%' . $wpdb->esc_like($excludeLike) . '%'
));
echo "Included services (" . count($services) . "):\n";
foreach ($services as $s) echo "  #{$s->id} {$s->name}\n";

// idempotent: find existing bundle by exact name
$existing = (new OsBundleModel())->where(['name' => $name])->set_limit(1)->get_results_as_models();
$bundle = ($existing && $existing->id) ? $existing : new OsBundleModel();
$isNew = empty($bundle->id);

echo ($isNew ? "\nCREATE" : "\nUPDATE #{$bundle->id}") . " bundle\n";

if ($dryRun) {
    echo "[dry-run] would set price €{$price}, attach " . count($services) . " services @ qty {$total}, mark shared_pool_total={$total}\n";
    exit(0);
}

$bundle->name          = $name;
$bundle->charge_amount = $price;
$bundle->status        = LATEPOINT_BUNDLE_STATUS_ACTIVE;
$bundle->visibility    = LATEPOINT_BUNDLE_VISIBILITY_VISIBLE;
if (!$bundle->save()) {
    fwrite(STDERR, "Bundle save failed: " . implode('; ', $bundle->get_error_messages()) . "\n");
    exit(1);
}

// attach all included services at quantity = total (each capped individually at
// `total`; the shared cap across them is enforced by the companion plugin)
$serviceParams = [];
foreach ($services as $s) {
    $serviceParams['service_' . $s->id] = [
        'quantity'        => $total,
        'total_attendees' => 1,
        'duration'        => (int) $s->duration,
        'connected'       => 'yes',
    ];
}
$bundle->save_services($serviceParams);

// mark this bundle as shared-pool in an option the enforcement plugin reads
$map = get_option('yumefit_shared_pool_bundles', []);
if (!is_array($map)) $map = [];
$map[(int) $bundle->id] = $total;
update_option('yumefit_shared_pool_bundles', $map, false);

echo "Saved bundle #{$bundle->id} with " . count($services) . " services, shared cap {$total}.\n";
echo "Shared-pool option now: " . json_encode($map) . "\n";
echo "Done.\n";
