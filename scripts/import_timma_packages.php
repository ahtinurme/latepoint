#!/usr/bin/env php
<?php
/**
 * Import each customer's BOUGHT PACKAGES (incl. expired) into LatePoint as bundle
 * order-items, reconstructed from booking package-markers (size/index runs).
 *
 * - Source: cached Timma serviceslots in .context/timma/slots/<clientId>.json
 *   (a run from .../1 up to .../N = one purchased N-pack; new run on reset/size change).
 * - Only standard pack sizes are imported (5, 10); odd sizes are marker noise.
 * - Creates an order (status completed) + a bundle order-item per package, with
 *   order created_at = purchase (run start) date so the 2-month validity plugin
 *   governs whether remaining sessions are still bookable. NO transaction is created
 *   (the payment is already recorded on the session order — avoids double-counting).
 * - Idempotent via order_meta `timma_package_key` = clientId|family|size|startDate.
 *
 * Usage: php -d memory_limit=1024M import_timma_packages.php [--dry-run] [--sizes=5,10]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['dry-run', 'sizes:']);
$dryRun = isset($opts['dry-run']);
$sizes  = array_map('intval', explode(',', $opts['sizes'] ?? '5,10'));

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsBundleModel') || !class_exists('OsOrderModel')) { fwrite(STDERR, "LatePoint not loaded\n"); exit(1); }

global $wpdb; $P = $wpdb->prefix;
$slotsDir = realpath(__DIR__ . '/../.context/timma/slots');
if (!$slotsDir) { fwrite(STDERR, "No cached slots dir\n"); exit(1); }
$today = new DateTime('2026-06-13');

echo "=========== Import bought packages (bundles) ===========\n";
echo "DB: " . DB_NAME . " @ " . DB_HOST . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | sizes " . implode(',', $sizes) . "\n";
echo "========================================================\n";

function K($n, $f) { return defined($n) ? constant($n) : $f; }
function famOf(string $n): string {
    $n = mb_strtolower($n, 'UTF-8');
    if (strpos($n, 'jooga') !== false && strpos($n, 'rühm') !== false) return 'Jooga rühm';
    if (strpos($n, 'jooga') !== false) return 'Jooga personal';
    if (strpos($n, 'ems') !== false) return 'EMS';
    if (strpos($n, 'kavitats') !== false) return 'Kavitatsioon';
    if (strpos($n, 'infrapuna') !== false) return 'Infrapunamatt';
    if (strpos($n, 'rf') !== false) return 'RF-lifting';
    if (strpos($n, 'lipolaser') !== false) return 'Lipolaser';
    if (strpos($n, 'massaaž') !== false || strpos($n, 'kobido') !== false) return 'Massaaž';
    return 'Muu';
}
function pkgMarker(?string $note): ?array {
    if ($note && preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})/', $note, $m)) {
        $s = (int) $m[1]; $i = (int) $m[2];
        if ($s >= $i && $s <= 50) return ['size' => $s, 'index' => $i];
    }
    return null;
}

// family -> canonical Timma serviceId (resolve LP service via service_meta) + label + default price
$FAM = [
    'EMS'            => ['sid' => 5004, 'label' => 'EMS personaaltreening'],
    'Jooga personal' => ['sid' => 9001, 'label' => 'Jooga personaaltreening'],
    'Jooga rühm'     => ['sid' => 9003, 'label' => 'Jooga rühmatreening', 'price' => [5 => 89, 10 => 174]],
    'Kavitatsioon'   => ['sid' => 1154, 'label' => 'Kavitatsioon'],
    'Infrapunamatt'  => ['sid' => 1151, 'label' => 'Infrapunamatt'],
    'RF-lifting'     => ['sid' => 1153, 'label' => 'RF- lifting'],
    'Lipolaser'      => ['sid' => 1159, 'label' => 'Lipolaser'],
    'Massaaž'        => ['sid' => 2002, 'label' => 'Massaaž'],
];
$DEFAULT_PRICE = [5 => 149, 10 => 294];

function lpServiceForTimma(int $sid): int {
    global $wpdb, $P; static $m = null;
    if ($m === null) { $m = []; foreach ($wpdb->get_results("SELECT object_id,meta_value FROM {$P}latepoint_service_meta WHERE meta_key='timma_service_id'") as $r) $m[(string)$r->meta_value] = (int)$r->object_id; }
    return $m[(string) $sid] ?? 0;
}
// find a bundle with this single service+quantity, else create one
function bundleFor(string $fam, int $size, array $FAM, array $DEFAULT_PRICE, bool $dryRun): int {
    global $wpdb, $P; static $cache = [];
    $key = "$fam|$size"; if (isset($cache[$key])) return $cache[$key];
    $cfg = $FAM[$fam]; $svcId = lpServiceForTimma($cfg['sid']);
    if (!$svcId) return $cache[$key] = 0;
    // match only a SINGLE-service bundle (exclude multi-service ones like the shared-pool bundle)
    $dupId = $wpdb->get_var($wpdb->prepare(
        "SELECT bs.bundle_id FROM {$P}latepoint_bundles_services bs
         JOIN {$P}latepoint_bundles b ON b.id=bs.bundle_id
         WHERE bs.service_id=%d AND bs.quantity=%d
           AND (SELECT COUNT(*) FROM {$P}latepoint_bundles_services x WHERE x.bundle_id=bs.bundle_id)=1
         LIMIT 1", $svcId, $size));
    if ($dupId) return $cache[$key] = (int) $dupId;
    $price = $cfg['price'][$size] ?? ($DEFAULT_PRICE[$size] ?? 0);
    if ($dryRun) { echo "  [dry] would CREATE bundle '{$cfg['label']} {$size}x kaart' (svc#{$svcId} x{$size}, €{$price})\n"; return $cache[$key] = -1; }
    $b = new OsBundleModel();
    $b->name = "{$cfg['label']} {$size}x kaart"; $b->charge_amount = $price;
    $b->status = K('LATEPOINT_BUNDLE_STATUS_ACTIVE', 'active');
    $b->visibility = K('LATEPOINT_BUNDLE_VISIBILITY_VISIBLE', 'visible');
    $b->save();
    $dur = (int) ($wpdb->get_var($wpdb->prepare("SELECT duration FROM {$P}latepoint_services WHERE id=%d", $svcId)) ?: 60);
    $b->save_services(['service_' . $svcId => ['quantity' => $size, 'total_attendees' => 1, 'duration' => $dur, 'connected' => 'yes']]);
    echo "  + created bundle #{$b->id} '{$b->name}'\n";
    return $cache[$key] = (int) $b->id;
}

// clientId -> LP customer id
$clientToCust = [];
foreach ($wpdb->get_results("SELECT object_id,meta_value FROM {$P}latepoint_customer_meta WHERE meta_key='timma_client_id'") as $r) $clientToCust[(string)$r->meta_value] = (int)$r->object_id;

$st = ['runs'=>0,'created'=>0,'skip_exists'=>0,'skip_size'=>0,'skip_nocust'=>0,'skip_nobundle'=>0,'active'=>0];
$activeList = [];

foreach (scandir($slotsDir) as $file) {
    if (substr($file, -5) !== '.json') continue;
    $clientId = substr($file, 0, -5);
    $custId = $clientToCust[$clientId] ?? 0;
    $slots = json_decode(file_get_contents($slotsDir . '/' . $file), true) ?: [];

    // marked, non-cancelled bookings, sorted by date
    $marked = [];
    foreach ($slots as $s) {
        if (!empty($s['deletedOn']) || !empty($s['cancellationReason'])) continue;
        $pm = pkgMarker($s['reservation']['bookingNote'] ?? ''); if (!$pm) continue;
        $marked[] = ['d' => substr((string)($s['start'] ?? ''), 0, 10), 'fam' => famOf($s['reservation']['serviceName'] ?? $s['title'] ?? ''), 'size' => $pm['size'], 'idx' => $pm['index']];
    }
    if (!$marked) continue;
    usort($marked, fn($a, $b) => strcmp($a['d'], $b['d']));

    // detect runs per family
    $byFam = [];
    foreach ($marked as $m) $byFam[$m['fam']][] = $m;
    foreach ($byFam as $fam => $arr) {
        $prev = null;
        foreach ($arr as $m) {
            $isNewRun = ($prev === null || $m['size'] !== $prev['size'] || $m['idx'] <= $prev['idx']);
            if ($isNewRun) {
                $st['runs']++;
                $size = $m['size']; $start = $m['d'];
                if (!in_array($size, [5, 10], true) && !in_array($size, $GLOBALS['sizes'], true)) { $st['skip_size']++; $prev = $m; continue; }
                if (!isset($GLOBALS['FAM'][$fam])) { $st['skip_nobundle']++; $prev = $m; continue; }
                if (!$custId) { $st['skip_nocust']++; $prev = $m; continue; }

                $pkgKey = "{$clientId}|{$fam}|{$size}|{$start}";
                $exists = $wpdb->get_var($wpdb->prepare("SELECT object_id FROM {$P}latepoint_order_meta WHERE meta_key='timma_package_key' AND meta_value=%s LIMIT 1", $pkgKey));
                if ($exists) { $st['skip_exists']++; $prev = $m; continue; }

                $expiry = (new DateTime($start))->modify('+2 months');
                $isActive = $expiry >= $GLOBALS['today'];
                if ($isActive) { $st['active']++; $activeList[] = "{$clientId} cust#{$custId} {$fam} {$size}x bought {$start} (exp {$expiry->format('Y-m-d')})"; }

                if ($dryRun) { $st['created']++; $prev = $m; continue; }

                $bundleId = bundleFor($fam, $size, $GLOBALS['FAM'], $GLOBALS['DEFAULT_PRICE'], false);
                if ($bundleId <= 0) { $st['skip_nobundle']++; $prev = $m; continue; }
                $price = $GLOBALS['FAM'][$fam]['price'][$size] ?? ($GLOBALS['DEFAULT_PRICE'][$size] ?? 0);

                $order = new OsOrderModel();
                $order->customer_id = $custId;
                $order->status = K('LATEPOINT_ORDER_STATUS_COMPLETED', 'completed');
                $order->fulfillment_status = K('LATEPOINT_ORDER_FULFILLMENT_STATUS_FULFILLED', 'fulfilled');
                $order->payment_status = K('LATEPOINT_ORDER_PAYMENT_STATUS_FULLY', 'fully');
                $order->subtotal = $price; $order->total = $price;
                if (!$order->save()) { $prev = $m; continue; }
                $oi = new OsOrderItemModel();
                $oi->order_id = $order->id; $oi->variant = K('LATEPOINT_ITEM_VARIANT_BUNDLE', 'bundle');
                $oi->subtotal = $price; $oi->total = $price;
                $oi->item_data = wp_json_encode(['bundle_id' => $bundleId]);
                if (!$oi->save()) { $order->delete($order->id); $prev = $m; continue; }
                OsMetaHelper::save_order_meta_by_key('timma_package_key', $pkgKey, $order->id);
                $wpdb->update("{$P}latepoint_orders", ['created_at' => $start . ' 00:00:00'], ['id' => $order->id]);
                $st['created']++;
            }
            $prev = $m;
        }
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Package runs detected: %d\n", $st['runs']);
echo sprintf("Bundles/orders created: %d | skipped: exists %d, odd-size %d, no-customer %d, no-bundle-family %d\n",
    $st['created'], $st['skip_exists'], $st['skip_size'], $st['skip_nocust'], $st['skip_nobundle']);
echo sprintf("Currently ACTIVE (purchased within 2 months): %d\n", $st['active']);
foreach (array_slice($activeList, 0, 30) as $a) echo "   * {$a}\n";
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
