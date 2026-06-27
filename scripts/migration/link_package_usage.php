#!/usr/bin/env php
<?php
/**
 * Link each imported package's session bookings to its bundle order-item, so
 * LatePoint shows actual usage (e.g. "4 of 5 used"). Reconstructs the same
 * package-marker runs as import_timma_packages.php, finds the bundle order via
 * order_meta `timma_package_key`, and repoints the run's session bookings'
 * order_item_id to the bundle's order-item. Any payment on a repointed session
 * order is MOVED to the bundle order (revenue-neutral), then the now-empty
 * per-session order is deleted. Idempotent.
 *
 * Usage: php -d memory_limit=1024M link_package_usage.php [--dry-run] [--limit=N]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['dry-run', 'limit:']);
$dryRun = isset($opts['dry-run']);
$limit  = isset($opts['limit']) ? (int) $opts['limit'] : 0;

$wpLoad = realpath(__DIR__ . '/../../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;
$slotsDir = realpath(__DIR__ . '/../../.context/timma/slots');
if (!$slotsDir) { fwrite(STDERR, "no cached slots\n"); exit(1); }

echo "=========== Link package usage (bookings -> bundle) ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . ($limit ? " | limit {$limit} packages" : "") . "\n";
echo "===============================================================\n";

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
        $s = (int) $m[1]; $i = (int) $m[2]; if ($s >= $i && $s <= 50) return ['size' => $s, 'index' => $i];
    }
    return null;
}
function bookingIdForSlot(string $slotId): int {
    global $wpdb, $P;
    return (int) ($wpdb->get_var($wpdb->prepare("SELECT object_id FROM {$P}latepoint_booking_meta WHERE meta_key='timma_slot_id' AND meta_value=%s LIMIT 1", $slotId)) ?: 0);
}
function bundleOrderItemForKey(string $pkgKey): array {
    global $wpdb, $P;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT o.id order_id, oi.id oi_id FROM {$P}latepoint_orders o
         JOIN {$P}latepoint_order_meta om ON om.object_id=o.id AND om.meta_key='timma_package_key' AND om.meta_value=%s
         JOIN {$P}latepoint_order_items oi ON oi.order_id=o.id AND oi.variant='bundle' LIMIT 1", $pkgKey));
    return $row ? ['order_id' => (int) $row->order_id, 'oi_id' => (int) $row->oi_id] : ['order_id' => 0, 'oi_id' => 0];
}

$st = ['packages'=>0,'linked'=>0,'already'=>0,'no_booking'=>0,'orders_deleted'=>0,'txns_moved'=>0,'no_bundle_order'=>0];
$pkgCount = 0;

foreach (scandir($slotsDir) as $file) {
    if (substr($file, -5) !== '.json') continue;
    $clientId = substr($file, 0, -5);
    $slots = json_decode(file_get_contents($slotsDir . '/' . $file), true) ?: [];
    $marked = [];
    foreach ($slots as $s) {
        if (!empty($s['deletedOn']) || !empty($s['cancellationReason'])) continue;
        $pm = pkgMarker($s['reservation']['bookingNote'] ?? ''); if (!$pm) continue;
        $marked[] = ['id' => (string) ($s['id'] ?? ''), 'd' => substr((string) ($s['start'] ?? ''), 0, 10),
                     'fam' => famOf($s['reservation']['serviceName'] ?? $s['title'] ?? ''), 'size' => $pm['size'], 'idx' => $pm['index']];
    }
    if (!$marked) continue;
    usort($marked, fn($a, $b) => strcmp($a['d'], $b['d']));

    // group into runs per family, collecting member slot ids
    $byFam = [];
    foreach ($marked as $m) $byFam[$m['fam']][] = $m;
    foreach ($byFam as $fam => $arr) {
        $runs = []; $prev = null; $ri = -1;
        foreach ($arr as $m) {
            if ($prev === null || $m['size'] !== $prev['size'] || $m['idx'] <= $prev['idx']) {
                $runs[] = ['size' => $m['size'], 'start' => $m['d'], 'slots' => []];
                $ri = count($runs) - 1;
            }
            $runs[$ri]['slots'][] = $m['id'];
            $prev = $m;
        }
        foreach ($runs as $run) {
            if (!in_array($run['size'], [5, 10], true)) continue;
            if ($limit && $pkgCount >= $limit) break 3;
            $pkgCount++; $st['packages']++;
            $pkgKey = "{$clientId}|{$fam}|{$run['size']}|{$run['start']}";
            $bo = bundleOrderItemForKey($pkgKey);
            if (!$bo['oi_id']) { $st['no_bundle_order']++; continue; }

            foreach ($run['slots'] as $slotId) {
                if ($slotId === '') continue;
                $bkId = bookingIdForSlot($slotId);
                if (!$bkId) { $st['no_booking']++; continue; }
                $curOi = (int) $wpdb->get_var($wpdb->prepare("SELECT order_item_id FROM {$P}latepoint_bookings WHERE id=%d", $bkId));
                if ($curOi === $bo['oi_id']) { $st['already']++; continue; }
                if ($dryRun) { $st['linked']++; continue; }

                $oldOrderId = (int) $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$P}latepoint_order_items WHERE id=%d", $curOi));
                // repoint booking to the bundle order-item
                $wpdb->update("{$P}latepoint_bookings", ['order_item_id' => $bo['oi_id']], ['id' => $bkId]);
                $st['linked']++;
                if ($oldOrderId && $oldOrderId !== $bo['order_id']) {
                    // move any payment to the bundle order (revenue-neutral)
                    $moved = $wpdb->query($wpdb->prepare("UPDATE {$P}latepoint_transactions SET order_id=%d WHERE order_id=%d", $bo['order_id'], $oldOrderId));
                    $wpdb->query($wpdb->prepare("UPDATE {$P}latepoint_order_invoices SET order_id=%d WHERE order_id=%d", $bo['order_id'], $oldOrderId));
                    if ($moved) $st['txns_moved'] += (int) $moved;
                    // delete the now-empty per-session order (only its own booking order-item remains)
                    $wpdb->delete("{$P}latepoint_order_items", ['id' => $curOi]);
                    $remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}latepoint_order_items WHERE order_id=%d", $oldOrderId));
                    if ($remaining === 0) { $wpdb->delete("{$P}latepoint_orders", ['id' => $oldOrderId]); $st['orders_deleted']++; }
                }
            }
        }
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Packages processed: %d (no bundle order: %d)\n", $st['packages'], $st['no_bundle_order']);
echo sprintf("Session bookings linked: %d (already linked: %d, no booking row: %d)\n", $st['linked'], $st['already'], $st['no_booking']);
echo sprintf("Old per-session orders deleted: %d | payments moved to bundle order: %d\n", $st['orders_deleted'], $st['txns_moved']);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
