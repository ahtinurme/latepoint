#!/usr/bin/env php
<?php
/**
 * Create one or more prepaid package (bundle) "cards" for a customer that were
 * missed by the marker-based import, each recorded as a paid bundle order with a
 * configurable validity window.
 *
 * For --count cards it creates, per card: an order (status completed, fully_paid)
 * with a single bundle order-item, a paid invoice + transaction for the card price,
 * and order-meta valid_for_months. It also sets the bundle's valid_for_months meta
 * so the validity rule applies to this card type.
 *
 * Optionally --link-booking moves an existing booking onto the first card (e.g. the
 * session that already started consuming the card).
 *
 * Usage: php -d memory_limit=1024M create_package_cards.php --customer=ID --bundle=ID
 *          --count=2 --valid-months=4 [--price=294] [--purchase=YYYY-MM-DD]
 *          [--link-booking=BK_ID] [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts = getopt('', ['customer:', 'bundle:', 'count:', 'valid-months:', 'price:', 'purchase:', 'link-booking:', 'dry-run']);
$dryRun = isset($opts['dry-run']);
$customer = (int) ($opts['customer'] ?? 0);
$bundleId = (int) ($opts['bundle'] ?? 0);
$count = (int) ($opts['count'] ?? 1);
$months = (int) ($opts['valid-months'] ?? 0);
$linkBooking = (int) ($opts['link-booking'] ?? 0);
if (!$customer || !$bundleId || $count < 1) { fwrite(STDERR, "--customer, --bundle, --count required\n"); exit(1); }

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;

$bundle = $wpdb->get_row($wpdb->prepare("SELECT id,name,charge_amount FROM {$P}latepoint_bundles WHERE id=%d", $bundleId));
if (!$bundle) { fwrite(STDERR, "bundle {$bundleId} not found\n"); exit(1); }
$price = isset($opts['price']) ? (float) $opts['price'] : (float) $bundle->charge_amount;
$purchase = ($opts['purchase'] ?? (new DateTime('now'))->format('Y-m-d')) . ' 00:00:00';

echo "=========== Create package cards ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "customer={$customer} bundle=#{$bundleId} '{$bundle->name}' x{$count} @ {$price} | valid {$months}mo | purchased {$purchase}\n";
echo "===========================================\n";

if ($months > 0 && !$dryRun) {
    $exists = $wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$P}latepoint_bundle_meta WHERE object_id=%d AND meta_key='valid_for_months'", $bundleId));
    if ($exists) {
        $wpdb->update("{$P}latepoint_bundle_meta", ['meta_value' => $months], ['meta_id' => $exists]);
    } else {
        $wpdb->insert("{$P}latepoint_bundle_meta", ['object_id' => $bundleId, 'meta_key' => 'valid_for_months', 'meta_value' => $months]);
    }
    echo "Set bundle #{$bundleId} valid_for_months={$months} (applies to this card type)\n";
}

$firstOrderItemId = 0;
for ($i = 1; $i <= $count; $i++) {
    echo "  card {$i}/{$count}: order + bundle item + payment {$price}\n";
    if ($dryRun) { continue; }
    $wpdb->insert("{$P}latepoint_orders", [
        'customer_id' => $customer, 'status' => 'completed', 'fulfillment_status' => 'fulfilled',
        'payment_status' => 'fully_paid', 'subtotal' => $price, 'total' => $price,
        'confirmation_code' => strtoupper(substr(md5('ord' . $customer . $i . $purchase), 0, 7)),
        'created_at' => $purchase, 'updated_at' => $purchase,
    ]);
    $orderId = (int) $wpdb->insert_id;
    $wpdb->insert("{$P}latepoint_order_items", [
        'order_id' => $orderId, 'variant' => 'bundle', 'item_data' => wp_json_encode(['bundle_id' => $bundleId]),
        'subtotal' => $price, 'total' => $price, 'created_at' => $purchase, 'updated_at' => $purchase,
    ]);
    $oiId = (int) $wpdb->insert_id;
    if (!$firstOrderItemId) { $firstOrderItemId = $oiId; }
    if ($months > 0) { $wpdb->insert("{$P}latepoint_order_meta", ['object_id' => $orderId, 'meta_key' => 'valid_for_months', 'meta_value' => $months]); }
    if ($price > 0) {
        $wpdb->insert("{$P}latepoint_order_invoices", [
            'order_id' => $orderId, 'invoice_number' => 'TIMMA-CARD-' . $orderId, 'status' => 'paid',
            'charge_amount' => $price, 'payment_portion' => 'full', 'access_key' => substr(md5('inv' . $orderId), 0, 32),
            'created_at' => $purchase, 'updated_at' => $purchase,
        ]);
        $invId = (int) $wpdb->insert_id;
        $wpdb->insert("{$P}latepoint_transactions", [
            'invoice_id' => $invId, 'order_id' => $orderId, 'customer_id' => $customer,
            'processor' => 'timma_import', 'payment_method' => 'external', 'payment_portion' => 'full',
            'kind' => 'capture', 'status' => 'succeeded', 'amount' => $price,
            'notes' => 'Reconstructed package card purchase', 'token' => substr(md5('tx' . $orderId), 0, 32),
            'access_key' => substr(md5('txa' . $orderId), 0, 36), 'created_at' => $purchase, 'updated_at' => $purchase,
        ]);
    }
    echo "    -> order #{$orderId}, bundle item #{$oiId}\n";
}

if ($linkBooking && $firstOrderItemId) {
    echo "  link booking #{$linkBooking} -> first card item #{$firstOrderItemId}\n";
    if (!$dryRun) { $wpdb->update("{$P}latepoint_bookings", ['order_item_id' => $firstOrderItemId], ['id' => $linkBooking]); }
}

echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
