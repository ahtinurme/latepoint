#!/usr/bin/env php
<?php
/**
 * Finalize package (bundle) orders so a customer's package sessions are linked to
 * the package and the package shows as fully paid with €0 due.
 *
 * Phase A (linking): for each in-scope bundle order, link the customer's eligible
 *   sessions to the bundle's order-item, up to the package quantity. Eligible =
 *   non-cancelled, matching the bundle's service, currently on a standalone €0
 *   booking order with no transaction, not already linked. The emptied standalone
 *   order is deleted (mirrors link_package_usage). This is revenue-neutral.
 *
 * Phase B (payment): record the prepaid package payment. paid = sum of the bundle
 *   order's transactions; if total > paid, insert a transaction for the shortfall
 *   (processor 'timma_import') + a paid invoice, then mark the order fully_paid so
 *   the balance reads €0. Active packages are prepaid in full, so recording the
 *   shortfall recovers the package-sale revenue that was never booking-attached.
 *
 * Scope: by default only ACTIVE bundle orders (created >= --since, default
 *   today-2months). Pass --all to include every bundle order (history) — note this
 *   records nominal package prices for €0-paid bundles. --customer=ID to scope to one.
 *
 * Usage: php -d memory_limit=1024M finalize_package_orders.php [--dry-run] [--all]
 *          [--since=YYYY-MM-DD] [--customer=ID]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['dry-run', 'all', 'since:', 'customer:', 'no-link', 'partial-keep']);
$dryRun = isset($opts['dry-run']);
$all    = isset($opts['all']);
$since  = $opts['since'] ?? (new DateTime('now'))->modify('-2 months')->format('Y-m-d');
$onlyCustomer = isset($opts['customer']) ? (int) $opts['customer'] : 0;
$noLink = isset($opts['no-link']);          // skip Phase A (history already linked by marker pass)
$partialKeep = isset($opts['partial-keep']); // partial bundles: set total=paid (no new revenue) instead of topping up

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;

$FULLY = defined('LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID') ? LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID : 'fully_paid';
$CANCELLED = defined('LATEPOINT_BOOKING_STATUS_CANCELLED') ? LATEPOINT_BOOKING_STATUS_CANCELLED : 'cancelled';
$now = (new DateTime('now', new DateTimeZone('Europe/Tallinn')))->format('Y-m-d H:i:s');

echo "=========== Finalize package orders ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | scope: " . ($all ? 'ALL bundles' : "active since {$since}") . ($onlyCustomer ? " | customer {$onlyCustomer}" : '') . "\n";
echo "===============================================\n";

$where = "oi.variant='bundle'";
if (!$all) { $where .= $wpdb->prepare(" AND o.created_at >= %s", $since . ' 00:00:00'); }
if ($onlyCustomer) { $where .= $wpdb->prepare(" AND o.customer_id = %d", $onlyCustomer); }

$bundles = $wpdb->get_results(
    "SELECT o.id order_id, o.customer_id, o.total, oi.id oi_id, oi.item_data
     FROM {$P}latepoint_orders o
     JOIN {$P}latepoint_order_items oi ON oi.order_id = o.id AND oi.variant = 'bundle'
     WHERE {$where} ORDER BY o.id"
);

$st = ['bundles' => 0, 'linked' => 0, 'orders_deleted' => 0, 'paid_orders' => 0, 'revenue_added' => 0.0];

foreach ($bundles as $b) {
    $st['bundles']++;
    $item = json_decode($b->item_data, true);
    $bundleId = (int) ($item['bundle_id'] ?? 0);
    $services = $bundleId ? $wpdb->get_results($wpdb->prepare(
        "SELECT service_id, quantity FROM {$P}latepoint_bundles_services WHERE bundle_id = %d", $bundleId
    )) : [];

    // ---- Phase A: link eligible sessions up to quantity ----
    foreach (($noLink ? [] : $services) as $svc) {
        $serviceId = (int) $svc->service_id;
        $qty = (int) $svc->quantity;
        $used = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$P}latepoint_bookings WHERE order_item_id = %d AND service_id = %d AND status <> %s",
            $b->oi_id, $serviceId, $CANCELLED
        ));
        $remaining = $qty - $used;
        if ($remaining <= 0) { continue; }

        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT bk.id bk_id, bk.order_item_id cur_oi, coi.order_id cur_order
             FROM {$P}latepoint_bookings bk
             JOIN {$P}latepoint_order_items coi ON coi.id = bk.order_item_id AND coi.variant = 'booking'
             JOIN {$P}latepoint_orders co ON co.id = coi.order_id
             WHERE bk.customer_id = %d AND bk.service_id = %d AND bk.status <> %s
               AND bk.order_item_id <> %d
               AND co.total = 0
               AND NOT EXISTS (SELECT 1 FROM {$P}latepoint_transactions t WHERE t.order_id = co.id)
             ORDER BY bk.start_date ASC, bk.start_time ASC
             LIMIT %d",
            $b->customer_id, $serviceId, $CANCELLED, $b->oi_id, $remaining
        ));

        foreach ($candidates as $cand) {
            echo sprintf("  link bk#%d -> bundle oi#%d (cust %d, svc %d)\n", $cand->bk_id, $b->oi_id, $b->customer_id, $serviceId);
            $st['linked']++;
            if ($dryRun) { continue; }
            $wpdb->update("{$P}latepoint_bookings", ['order_item_id' => $b->oi_id], ['id' => $cand->bk_id]);
            // remove the emptied standalone order
            $wpdb->delete("{$P}latepoint_order_items", ['id' => $cand->cur_oi]);
            $remainingItems = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$P}latepoint_order_items WHERE order_id = %d", $cand->cur_order
            ));
            if ($remainingItems === 0) {
                $wpdb->delete("{$P}latepoint_orders", ['id' => $cand->cur_order]);
                $st['orders_deleted']++;
            }
        }
    }

    // ---- Phase B: settle the package order so balance = €0 ----
    $paid = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$P}latepoint_transactions WHERE order_id = %d", $b->order_id
    ));
    $shortfall = round((float) $b->total - $paid, 2);

    // --partial-keep: a bundle that already has SOME payment (per-session) keeps its
    // received revenue — shrink total to what was paid (balance 0) instead of topping
    // up the nominal price, which would double-count. €0-paid bundles still record the
    // full (genuinely-missing lump-sum) price below.
    if ($partialKeep && $paid > 0.005 && $shortfall > 0.005) {
        echo sprintf("  keep bundle order #%d: total %.2f -> %.2f (paid, no new revenue)\n", $b->order_id, $b->total, $paid);
        if (!$dryRun) { $wpdb->update("{$P}latepoint_orders", ['total' => $paid, 'subtotal' => $paid], ['id' => $b->order_id]); }
        $shortfall = 0;
    }

    if ($shortfall > 0.005) {
        echo sprintf("  pay bundle order #%d: +%.2f (total %.2f, paid %.2f)\n", $b->order_id, $shortfall, $b->total, $paid);
        $st['paid_orders']++;
        $st['revenue_added'] += $shortfall;
        if (!$dryRun) {
            $accKey = substr(md5('inv' . $b->order_id . $now), 0, 32);
            $wpdb->insert("{$P}latepoint_order_invoices", [
                'order_id' => $b->order_id, 'invoice_number' => 'TIMMA-PKG-' . $b->order_id,
                'status' => 'paid', 'charge_amount' => $shortfall, 'payment_portion' => 'full',
                'access_key' => $accKey, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $invId = (int) $wpdb->insert_id;
            $wpdb->insert("{$P}latepoint_transactions", [
                'invoice_id' => $invId, 'order_id' => $b->order_id, 'customer_id' => $b->customer_id,
                'processor' => 'timma_import', 'payment_method' => 'external', 'payment_portion' => 'full',
                'kind' => 'capture', 'status' => 'succeeded', 'amount' => $shortfall,
                'notes' => 'Reconstructed package purchase payment', 'token' => substr(md5('tx' . $b->order_id . $now), 0, 32),
                'access_key' => substr(md5('txa' . $b->order_id . $now), 0, 36), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
    if (!$dryRun) {
        $wpdb->update("{$P}latepoint_orders", ['payment_status' => $FULLY], ['id' => $b->order_id]);
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Bundle orders processed: %d\n", $st['bundles']);
echo sprintf("Sessions linked to packages: %d (emptied orders deleted: %d)\n", $st['linked'], $st['orders_deleted']);
echo sprintf("Bundle orders paid up: %d | revenue recorded: %.2f EUR\n", $st['paid_orders'], $st['revenue_added']);
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
