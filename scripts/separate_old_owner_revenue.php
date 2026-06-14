#!/usr/bin/env php
<?php
/**
 * Scope LatePoint revenue to the NEW company (handover ~25 Jan 2026; cutoff 2026-02-01).
 *
 * Phase 1 (--phase=1): set aside pre-handover (old-owner) reconstructed payments.
 *   For every order whose ECONOMIC date is before --cutoff — economic date = the
 *   bundle purchase date (order.created_at) for package orders, else the order's
 *   earliest booking start_date — delete its transactions + invoices and zero the
 *   order/order-item totals + set payment_status=not_paid. Bookings, packages and
 *   usage links are KEPT for records; only the (old-owner) money is removed.
 *
 * Idempotent. Usage:
 *   php -d memory_limit=1024M separate_old_owner_revenue.php --phase=1 [--cutoff=2026-02-01] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['phase:', 'cutoff:', 'dry-run']);
$phase = (int) ($opts['phase'] ?? 0);
$cutoff = $opts['cutoff'] ?? '2026-02-01';
$dryRun = isset($opts['dry-run']);
if ($phase !== 1) { fwrite(STDERR, "--phase=1 required\n"); exit(1); }

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;

echo "=========== Separate old-owner revenue (Phase 1) ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | cutoff {$cutoff}\n";
echo "============================================================\n";

// Orders whose economic date is before the cutoff.
$preOrders = $wpdb->get_col($wpdb->prepare(
    "SELECT o.id FROM {$P}latepoint_orders o
     WHERE (
        CASE WHEN EXISTS(SELECT 1 FROM {$P}latepoint_order_items oi WHERE oi.order_id=o.id AND oi.variant='bundle')
             THEN DATE(o.created_at)
             ELSE (SELECT MIN(bk.start_date) FROM {$P}latepoint_order_items oi2
                   JOIN {$P}latepoint_bookings bk ON bk.order_item_id=oi2.id WHERE oi2.order_id=o.id)
        END
     ) < %s", $cutoff
));
$preOrders = array_map('intval', array_filter($preOrders));
echo "Pre-handover orders: " . count($preOrders) . "\n";
if (!$preOrders) { echo "Nothing to do.\n"; exit(0); }

$idList = implode(',', $preOrders);
$txCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$P}latepoint_transactions WHERE order_id IN ($idList)");
$txSum   = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$P}latepoint_transactions WHERE order_id IN ($idList)");
echo sprintf("Transactions to remove: %d (EUR %.2f)\n", $txCount, $txSum);

if (!$dryRun) {
    // Chunk to keep IN() lists sane.
    foreach (array_chunk($preOrders, 500) as $chunk) {
        $in = implode(',', $chunk);
        $wpdb->query("DELETE FROM {$P}latepoint_transactions WHERE order_id IN ($in)");
        $wpdb->query("DELETE FROM {$P}latepoint_order_invoices WHERE order_id IN ($in)");
        $wpdb->query("UPDATE {$P}latepoint_order_items SET subtotal=0, total=0 WHERE order_id IN ($in)");
        $wpdb->query("UPDATE {$P}latepoint_orders SET subtotal=0, total=0, payment_status='not_paid' WHERE id IN ($in)");
    }
    echo "Done — pre-handover money removed; bookings/packages/usage kept.\n";
} else {
    echo "DRY-RUN — no changes written.\n";
}

echo "\nResulting recorded revenue (all transactions): EUR " .
    number_format((float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$P}latepoint_transactions"), 2) . "\n";
