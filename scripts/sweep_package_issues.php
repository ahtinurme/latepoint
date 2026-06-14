#!/usr/bin/env php
<?php
/**
 * Read-only sweep of cached Timma slots to find two package-import gaps:
 *   (A) FREE packages — bookingNotes containing "tasuta" (free promo) whose package
 *       was imported as a PAID bundle (phantom revenue).
 *   (B) LARGE / multi-card packs — markers with a number > 10 (e.g. "1/20"), which the
 *       5/10-only marker parser skipped, so the cards were never imported.
 *
 * Maps each Timma client to its LatePoint customer (customer_meta timma_client_id)
 * and reports the customer's current imported bundles for context. No writes.
 *
 * Usage: php -d memory_limit=1024M sweep_package_issues.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;
$slotsDir = realpath(__DIR__ . '/../.context/timma/slots');
if (!$slotsDir) { fwrite(STDERR, "no cached slots\n"); exit(1); }

// timma_client_id -> [lp_id, name]
$cust = [];
foreach ($wpdb->get_results("SELECT cm.meta_value tid, c.id, CONCAT(c.first_name,' ',c.last_name) nm
    FROM {$P}latepoint_customer_meta cm JOIN {$P}latepoint_customers c ON c.id=cm.object_id
    WHERE cm.meta_key='timma_client_id'") as $r) {
    $cust[(string) $r->tid] = ['id' => (int) $r->id, 'nm' => $r->nm];
}
function bundlesFor($lpId) {
    global $wpdb, $P;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.item_data, o.total FROM {$P}latepoint_orders o
         JOIN {$P}latepoint_order_items oi ON oi.order_id=o.id AND oi.variant='bundle'
         WHERE o.customer_id=%d", $lpId));
    $out = [];
    foreach ($rows as $r) { $d = json_decode($r->item_data, true); $out[] = 'b' . ($d['bundle_id'] ?? '?') . '=' . $r->total; }
    return implode(',', $out) ?: 'none';
}

$free = []; $large = [];
foreach (scandir($slotsDir) as $file) {
    if (substr($file, -5) !== '.json') { continue; }
    $tid = substr($file, 0, -5);
    $slots = json_decode(file_get_contents($slotsDir . '/' . $file), true) ?: [];
    $hasFree = false; $largeMax = 0; $largeCount = 0; $svcNames = [];
    foreach ($slots as $s) {
        if (!empty($s['deletedOn']) || !empty($s['cancellationReason'])) { continue; }
        $note = (string) ($s['reservation']['bookingNote'] ?? '');
        if ($note === '') { continue; }
        if (stripos($note, 'tasuta') !== false) { $hasFree = true; }
        if (preg_match_all('/(\d{1,3})\s*\/\s*(\d{1,3})/', $note, $m, PREG_SET_ORDER)) {
            foreach ($m as $mm) {
                $hi = max((int) $mm[1], (int) $mm[2]);
                if ($hi > 10) { $largeMax = max($largeMax, $hi); $largeCount++; $svcNames[$s['reservation']['serviceName'] ?? '?'] = true; }
            }
        }
    }
    $c = $cust[$tid] ?? null;
    $label = $c ? "#{$c['id']} {$c['nm']}" : "(unmapped {$tid})";
    if ($hasFree) { $free[] = ['label' => $label, 'lp' => $c['id'] ?? 0]; }
    if ($largeMax > 0) { $large[] = ['label' => $label, 'lp' => $c['id'] ?? 0, 'max' => $largeMax, 'cnt' => $largeCount, 'svc' => implode('|', array_keys($svcNames))]; }
}

echo "================= (A) FREE 'tasuta' packages =================\n";
echo count($free) . " customers with a 'tasuta' (free) note:\n";
foreach ($free as $f) { echo sprintf("  %-32s bundles: %s\n", $f['label'], $f['lp'] ? bundlesFor($f['lp']) : '-'); }

echo "\n================= (B) LARGE / multi-card packs (marker > 10) =================\n";
echo count($large) . " customers with a marker number > 10:\n";
foreach ($large as $l) { echo sprintf("  %-32s maxMarker=%-3d sessions=%-3d bundles: %s | svc: %s\n", $l['label'], $l['max'], $l['cnt'], $l['lp'] ? bundlesFor($l['lp']) : '-', $l['svc']); }
