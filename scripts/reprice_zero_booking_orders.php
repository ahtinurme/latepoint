#!/usr/bin/env php
<?php
/**
 * Repair imported standalone booking orders whose stored subtotal/total is 0 even
 * though the service has a price. LatePoint computes the price breakdown live from
 * the service charge, so these show e.g. line 32.00 but subtotal/total 0.00 (and a
 * contradictory "fully paid" with a non-zero amount due once the püsiklient coupon
 * applies live).
 *
 * Sets the order + its booking order-item subtotal/total to the service charge_amount,
 * and payment_status from actual transactions (these have none -> not_paid / owed).
 * The püsiklient coupon still applies live at view time (e.g. 32 -> 27.20 due).
 *
 * Scope: standalone (variant=booking) orders with total=0, service charge>0, and a
 * booking whose status is in --statuses (default approved,no_show). Bundle-linked
 * sessions and €0 services are untouched. Idempotent.
 *
 * Usage: php -d memory_limit=1024M reprice_zero_booking_orders.php [--dry-run]
 *          [--statuses=approved,no_show]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['dry-run', 'statuses:']);
$dryRun = isset($opts['dry-run']);
$statuses = array_filter(array_map('trim', explode(',', $opts['statuses'] ?? 'approved,no_show')));
if (!$statuses) { fwrite(STDERR, "no statuses\n"); exit(1); }

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;
$NOT = defined('LATEPOINT_ORDER_PAYMENT_STATUS_NOT_PAID') ? LATEPOINT_ORDER_PAYMENT_STATUS_NOT_PAID : 'not_paid';

$in = implode(',', array_fill(0, count($statuses), '%s'));
echo "=========== Reprice zero-total booking orders ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | statuses: " . implode(',', $statuses) . "\n";
echo "========================================================\n";

$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT o.id order_id, oi.id oi_id, s.charge_amount price, bk.id bk_id, bk.status
     FROM {$P}latepoint_orders o
     JOIN {$P}latepoint_order_items oi ON oi.order_id = o.id AND oi.variant = 'booking'
     JOIN {$P}latepoint_bookings bk ON bk.order_item_id = oi.id
     JOIN {$P}latepoint_services s ON s.id = bk.service_id
     WHERE o.total = 0 AND s.charge_amount > 0 AND bk.status IN ($in)
       AND NOT EXISTS (SELECT 1 FROM {$P}latepoint_transactions t WHERE t.order_id = o.id)",
    ...$statuses
));

$n = 0; $sum = 0.0;
foreach ($rows as $r) {
    $price = round((float) $r->price, 2);
    echo sprintf("  order #%d (bk#%d %s): 0.00 -> %.2f\n", $r->order_id, $r->bk_id, $r->status, $price);
    $n++; $sum += $price;
    if (!$dryRun) {
        $wpdb->update("{$P}latepoint_order_items", ['subtotal' => $price, 'total' => $price], ['id' => $r->oi_id]);
        $wpdb->update("{$P}latepoint_orders", ['subtotal' => $price, 'total' => $price, 'payment_status' => $NOT], ['id' => $r->order_id]);
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Orders repriced: %d | base price total: %.2f EUR (now not_paid; püsiklient coupon applies live)\n", $n, $sum);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
