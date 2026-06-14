#!/usr/bin/env php
<?php
/**
 * Price imported standalone booking orders correctly so the admin breakdown is
 * self-consistent.
 *
 * Imported booking orders were stored with subtotal/total=0 even though the service
 * has a price; LatePoint computes the breakdown's coupon line live but shows the
 * STORED subtotal/total, so a püsiklient order rendered as "32.00 / -4.80 / 32.00"
 * (discount shown but not subtracted).
 *
 * For each in-scope order this sets:
 *   - order-item + order subtotal = service charge_amount (the line price)
 *   - if the customer is püsiklient: coupon_code + coupon_discount = the loyal-customer
 *     % of the subtotal, and total = subtotal - discount (e.g. 32 -> 27.20)
 *   - otherwise total = subtotal
 *   - payment_status = not_paid (these have no transaction), price_breakdown cleared
 *     so it recalculates from the stored coupon.
 *
 * Scope: standalone (variant=booking) orders with no transaction whose booking status
 * is in --statuses (default approved,no_show). Idempotent.
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

// püsiklient coupon: code + percentage
$coupCode = strtoupper(trim((string) get_option('yumefit_pusiklient_coupon_code', '')));
$coupId   = (int) get_option('yumefit_pusiklient_coupon_id', 0);
$pct = 15.0;
if ($coupId) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT discount_type, discount_value FROM {$P}latepoint_coupons WHERE id=%d", $coupId));
    if ($row && $row->discount_type === 'percent') { $pct = (float) $row->discount_value; }
}
// loyal customers (is_pusiklient meta, kept in sync with the custom field)
$pusiklient = [];
foreach ($wpdb->get_col("SELECT object_id FROM {$P}latepoint_customer_meta WHERE meta_key='is_pusiklient' AND meta_value='yes'") as $cid) {
    $pusiklient[(int) $cid] = true;
}

$in = implode(',', array_fill(0, count($statuses), '%s'));
echo "=========== Reprice booking orders ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | statuses: " . implode(',', $statuses) . " | püsiklient {$pct}% (" . ($coupCode ?: 'no code') . ")\n";
echo "=============================================\n";

$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT o.id order_id, o.customer_id, oi.id oi_id, s.charge_amount price, bk.id bk_id, bk.status
     FROM {$P}latepoint_orders o
     JOIN {$P}latepoint_order_items oi ON oi.order_id = o.id AND oi.variant = 'booking'
     JOIN {$P}latepoint_bookings bk ON bk.order_item_id = oi.id
     JOIN {$P}latepoint_services s ON s.id = bk.service_id
     WHERE s.charge_amount > 0 AND bk.status IN ($in)
       AND NOT EXISTS (SELECT 1 FROM {$P}latepoint_transactions t WHERE t.order_id = o.id)",
    ...$statuses
));

$n = 0; $loyal = 0;
foreach ($rows as $r) {
    $base = round((float) $r->price, 2);
    $isLoyal = !empty($pusiklient[(int) $r->customer_id]) && $coupCode !== '';
    $discount = $isLoyal ? round($base * $pct / 100, 2) : 0.0;
    $total = round($base - $discount, 2);
    echo sprintf("  order #%d (bk#%d %s): subtotal %.2f%s -> total %.2f\n",
        $r->order_id, $r->bk_id, $r->status, $base, $isLoyal ? sprintf(" - %.2f", $discount) : '', $total);
    $n++; if ($isLoyal) { $loyal++; }
    if ($dryRun) { continue; }
    $wpdb->update("{$P}latepoint_order_items", ['subtotal' => $base, 'total' => $base], ['id' => $r->oi_id]);
    $wpdb->update("{$P}latepoint_orders", [
        'subtotal' => $base, 'total' => $total,
        'coupon_code' => $isLoyal ? $coupCode : '', 'coupon_discount' => $discount,
        'price_breakdown' => '', 'payment_status' => $NOT,
    ], ['id' => $r->order_id]);
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Orders repriced: %d (of which püsiklient with discount baked in: %d)\n", $n, $loyal);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
