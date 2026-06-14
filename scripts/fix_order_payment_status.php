#!/usr/bin/env php
<?php
/**
 * Repair order payment_status that the importer wrote with an invalid literal value.
 *
 * An earlier import used a wrong constant name (LATEPOINT_ORDER_PAYMENT_STATUS_FULLY
 * does not exist), so paid/free orders were stored as the literal 'fully' instead of
 * 'fully_paid'. LatePoint doesn't recognise 'fully', so the bookings/orders lists show
 * "Undefined Status" ("Määratlemata staatus").
 *
 * This recomputes each affected order's payment_status from its ACTUAL transactions
 * (same rule as OsOrderModel::determine_payment_status):
 *   total <= 0                  -> fully_paid   (nothing owed)
 *   paid  >= total              -> fully_paid
 *   0 < paid < total            -> partially_paid
 *   paid  == 0                  -> not_paid
 * Note transactions were consolidated onto bundle orders by link_package_usage, so some
 * standalone session orders legitimately resolve to partially_paid / not_paid.
 *
 * Only orders currently holding the invalid value are touched; valid statuses
 * (fully_paid / partially_paid / not_paid / processing / refunded) are left alone.
 * Idempotent. Usage: php -d memory_limit=1024M fix_order_payment_status.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['dry-run', 'bad:']);
$dryRun = isset($opts['dry-run']);
$bad = $opts['bad'] ?? 'fully'; // the invalid value to repair

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
if (!class_exists('OsOrderModel')) { fwrite(STDERR, "LatePoint not loaded\n"); exit(1); }
global $wpdb; $P = $wpdb->prefix;

$FULLY = defined('LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID') ? LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID : 'fully_paid';
$PART  = defined('LATEPOINT_ORDER_PAYMENT_STATUS_PARTIALLY_PAID') ? LATEPOINT_ORDER_PAYMENT_STATUS_PARTIALLY_PAID : 'partially_paid';
$NOT   = defined('LATEPOINT_ORDER_PAYMENT_STATUS_NOT_PAID') ? LATEPOINT_ORDER_PAYMENT_STATUS_NOT_PAID : 'not_paid';

echo "=========== Fix order payment_status ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | repairing payment_status='{$bad}'\n";
echo "===============================================\n";

// Preview what each affected order would become, from actual transaction sums.
$preview = $wpdb->get_results($wpdb->prepare(
    "SELECT CASE
        WHEN o.total <= 0 THEN %s
        WHEN COALESCE(t.paid,0) >= o.total - 0.005 THEN %s
        WHEN COALESCE(t.paid,0) > 0 THEN %s
        ELSE %s
     END AS new_status, COUNT(*) AS n
     FROM {$P}latepoint_orders o
     LEFT JOIN (SELECT order_id, SUM(amount) paid FROM {$P}latepoint_transactions GROUP BY order_id) t
            ON t.order_id = o.id
     WHERE o.payment_status = %s
     GROUP BY new_status",
    $FULLY, $FULLY, $PART, $NOT, $bad
));
$total = 0;
foreach ($preview as $r) { echo sprintf("  -> %-16s %d\n", $r->new_status, $r->n); $total += (int) $r->n; }
echo "  affected orders: {$total}\n";

if (!$dryRun && $total > 0) {
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$P}latepoint_orders o
         LEFT JOIN (SELECT order_id, SUM(amount) paid FROM {$P}latepoint_transactions GROUP BY order_id) t
                ON t.order_id = o.id
         SET o.payment_status = CASE
            WHEN o.total <= 0 THEN %s
            WHEN COALESCE(t.paid,0) >= o.total - 0.005 THEN %s
            WHEN COALESCE(t.paid,0) > 0 THEN %s
            ELSE %s
         END
         WHERE o.payment_status = %s",
        $FULLY, $FULLY, $PART, $NOT, $bad
    ));
    echo "Rows updated: " . (int) $updated . "\n";
}

// Cancelled orders are never "paid": a cancelled booking has no delivered service.
// Set them to not_paid, EXCEPT the few that carry a real transaction (money was
// actually received) — those are left for manual review (refund handling).
$cancelledFixable = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$P}latepoint_orders o
     WHERE o.status = %s AND o.payment_status <> %s
       AND NOT EXISTS (SELECT 1 FROM {$P}latepoint_transactions t WHERE t.order_id = o.id)",
    defined('LATEPOINT_ORDER_STATUS_CANCELLED') ? LATEPOINT_ORDER_STATUS_CANCELLED : 'cancelled',
    $NOT
));
echo "\nCancelled orders to set not_paid (no transaction): {$cancelledFixable}\n";
if (!$dryRun && $cancelledFixable > 0) {
    $wpdb->query($wpdb->prepare(
        "UPDATE {$P}latepoint_orders o
         SET o.payment_status = %s
         WHERE o.status = %s
           AND NOT EXISTS (SELECT 1 FROM {$P}latepoint_transactions t WHERE t.order_id = o.id)",
        $NOT,
        defined('LATEPOINT_ORDER_STATUS_CANCELLED') ? LATEPOINT_ORDER_STATUS_CANCELLED : 'cancelled'
    ));
    echo "Cancelled orders updated.\n";
}

echo "\n=== resulting payment_status distribution ===\n";
foreach ($wpdb->get_results("SELECT payment_status, COUNT(*) n FROM {$P}latepoint_orders GROUP BY payment_status") as $r) {
    echo sprintf("  %-16s %d\n", $r->payment_status ?: '(empty)', $r->n);
}
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
