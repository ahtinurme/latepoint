#!/usr/bin/env php
<?php
/**
 * Reconcile Timma package PURCHASES against LatePoint bundle orders, additive-only.
 *
 * Rule: if a package exists in LatePoint but not Timma, LatePoint is correct (ignore).
 * We only ever flag / add packages that exist in Timma but are MISSING from LatePoint.
 *
 * Source of truth for "a package was bought" = product-type revenue rows in the FRESH
 * export (.context/timma/export/serviceslots/<clientId>.json), i.e. "<service> Nx kaart"
 * with a positive sum + receiptNumber. Refund rows ("Tagasimakse ...", negative sum) net
 * out the matching purchase. "Kuukaart" memberships are NOT quantity packages — reported
 * separately, never auto-created.
 *
 * A purchase is considered ALREADY PRESENT in LP if the customer has a bundle order that
 *  (a) carries order_meta timma_receipt = this receipt, OR
 *  (b) maps to the same (family,size) and was created within ±DATE_WINDOW days
 *      (a marker-run import of the same card).
 * Otherwise it is MISSING.
 *
 * Default = DRY-RUN report. Pass --apply to create the missing ones (order completed,
 * fully_paid, bundle item, created_at = purchase date, order_meta timma_receipt for
 * idempotency, valid_for_months by size). --with-transaction also records a paid
 * transaction (changes monthly revenue totals — off by default; see revenue notes).
 *
 * Usage: php -d memory_limit=1024M reconcile_timma_packages.php [--apply] [--with-transaction]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$opts            = getopt('', ['apply', 'with-transaction']);
$apply           = isset($opts['apply']);
$withTransaction = isset($opts['with-transaction']);
const DATE_WINDOW = 21; // days

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;

$exportDir = realpath(__DIR__ . '/../.context/timma/export');
if (!$exportDir || !is_dir($exportDir . '/serviceslots')) { fwrite(STDERR, "fresh export not found\n"); exit(1); }

function K($n, $f) { return defined($n) ? constant($n) : $f; }

function famOf(string $n): string {
    $n = mb_strtolower($n, 'UTF-8');
    if (strpos($n, 'stuudio') !== false || strpos($n, 'kehahoolitsus') !== false) return 'SHARED';
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
function pkgSizeOf(string $n): int {
    return preg_match('/(\d+)\s*x/iu', $n, $m) ? (int) $m[1] : 0;
}

/* ---- LP lookups ---------------------------------------------------------- */
$clientToCust = [];
foreach ($wpdb->get_results("SELECT object_id,meta_value FROM {$P}latepoint_customer_meta WHERE meta_key='timma_client_id'") as $r) {
    $clientToCust[(string) $r->meta_value] = (int) $r->object_id;
}

// bundle_id -> [fam,size]
$bundleFamSize = [];
foreach ($wpdb->get_results(
    "SELECT bs.bundle_id, COUNT(*) n, MAX(bs.quantity) q, GROUP_CONCAT(s.name SEPARATOR '||') names
     FROM {$P}latepoint_bundles_services bs JOIN {$P}latepoint_services s ON s.id=bs.service_id
     GROUP BY bs.bundle_id") as $r) {
    $fam = ((int) $r->n > 1) ? 'SHARED' : famOf((string) $r->names);
    $bundleFamSize[(int) $r->bundle_id] = [$fam, (int) $r->q];
}

// customer_id -> list of their bundle orders [order_id, bundle_id, fam, size, created_at, receipt]
function lpBundleOrders(int $custId): array {
    global $wpdb, $P, $bundleFamSize;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT o.id order_id, o.created_at, JSON_UNQUOTE(JSON_EXTRACT(oi.item_data,'$.bundle_id')) bundle_id
         FROM {$P}latepoint_orders o
         JOIN {$P}latepoint_order_items oi ON oi.order_id=o.id AND oi.variant='bundle'
         WHERE o.customer_id=%d", $custId));
    $out = [];
    foreach ($rows as $r) {
        $bid = (int) $r->bundle_id;
        [$fam, $size] = $bundleFamSize[$bid] ?? ['?', 0];
        $receipt = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$P}latepoint_order_meta WHERE object_id=%d AND meta_key='timma_receipt' LIMIT 1", (int) $r->order_id));
        $out[] = ['order_id' => (int) $r->order_id, 'bundle_id' => $bid, 'fam' => $fam, 'size' => $size, 'created_at' => substr((string) $r->created_at, 0, 10), 'receipt' => $receipt];
    }
    return $out;
}

/* ---- Collect Timma product-revenue purchases (net of refunds) ------------ */
$clients = [];
foreach (json_decode(file_get_contents($exportDir . '/clients.json'), true) as $c) { $clients[$c['id']] = $c['name'] ?? '?'; }

$purchases = []; // each: cid,name,svc,fam,size,sum,date,receipt,method
$refunds   = [];
foreach (glob($exportDir . '/serviceslots/*.json') as $f) {
    $cid = basename($f, '.json');
    foreach (json_decode(file_get_contents($f), true) ?: [] as $s) {
        foreach (($s['revenues'] ?? []) as $rv) {
            if (($rv['type'] ?? '') !== 'product') continue;
            $nm = (string) ($rv['serviceName'] ?? '');
            $low = mb_strtolower($nm, 'UTF-8');
            if (strpos($low, 'kaart') === false && strpos($low, 'pakett') === false) continue;
            $row = [
                'cid' => $cid, 'name' => $clients[$cid] ?? '?', 'svc' => $nm,
                'sum' => ($rv['sum'] ?? 0) / 100.0, 'date' => substr((string) ($s['start'] ?? ''), 0, 10),
                'receipt' => (string) ($rv['receiptNumber'] ?? ''),
                'method' => (($rv['sumCash'] ?? 0) != 0 ? 'cash' : 'card'),
            ];
            if (strpos($low, 'tagasimakse') !== false || $row['sum'] < 0) { $refunds[] = $row; continue; }
            $purchases[] = $row;
        }
    }
}
// net out refunds: drop a purchase whose (cid, |sum|) matches a refund
foreach ($refunds as $rf) {
    foreach ($purchases as $k => $p) {
        if ($p['cid'] === $rf['cid'] && abs($p['sum'] - abs($rf['sum'])) < 0.01) { unset($purchases[$k]); break; }
    }
}
$purchases = array_values($purchases);

/* ---- Diff each purchase against LP ---------------------------------------- */
$missing = []; $matched = []; $membership = []; $nocust = [];
foreach ($purchases as $p) {
    $fam = famOf($p['svc']); $size = pkgSizeOf($p['svc']);
    if (strpos(mb_strtolower($p['svc'], 'UTF-8'), 'kuukaart') !== false || $size === 0) { $membership[] = $p; continue; }
    $cust = $clientToCust[$p['cid']] ?? 0;
    if (!$cust) { $nocust[] = $p; continue; }

    $p['fam'] = $fam; $p['size'] = $size; $p['cust'] = $cust;
    $orders = lpBundleOrders($cust);
    $hit = null;
    foreach ($orders as $o) {
        if ($o['receipt'] === $p['receipt'] && $p['receipt'] !== '') { $hit = $o; break; }
        if ($o['fam'] === $fam && $o['size'] === $size) {
            $days = abs((strtotime($o['created_at']) - strtotime($p['date'])) / 86400);
            if ($days <= DATE_WINDOW) { $hit = $o; break; }
        }
    }
    if ($hit) { $p['lp_order'] = $hit['order_id']; $matched[] = $p; }
    else { $missing[] = $p; }
}

/* ---- Report --------------------------------------------------------------- */
echo "================ Timma → LatePoint package reconciliation ================\n";
echo 'DB: ' . DB_NAME . ' | ' . ($apply ? 'APPLY' . ($withTransaction ? '+TXN' : '') : 'DRY-RUN') . " | export: fresh\n";
echo sprintf("Product-revenue purchases: %d (after netting %d refunds)\n", count($purchases), count($refunds));
echo "==========================================================================\n\n";

echo "--- MISSING in LatePoint (in Timma, no matching LP bundle) ---\n";
if (!$missing) echo "  (none)\n";
foreach ($missing as $p) {
    echo sprintf("  cust#%d %-22s | %s %dx | €%.2f %s | %s | receipt %s\n",
        $p['cust'], mb_substr($p['name'], 0, 22), $p['fam'], $p['size'], $p['sum'], $p['method'], $p['date'], $p['receipt']);
}
echo "\n--- Already in LatePoint (matched, no action) ---\n";
if (!$matched) echo "  (none)\n";
foreach ($matched as $p) {
    echo sprintf("  cust#%d %-22s | %s %dx | €%.2f | %s -> LP order #%d\n",
        $p['cust'], mb_substr($p['name'], 0, 22), $p['fam'], $p['size'], $p['sum'], $p['date'], $p['lp_order']);
}
echo "\n--- Memberships / non-quantity (Kuukaart) — NOT packages, skipped ---\n";
if (!$membership) echo "  (none)\n";
foreach ($membership as $p) echo sprintf("  %-22s | %s | €%.2f | %s\n", mb_substr($p['name'], 0, 22), $p['svc'], $p['sum'], $p['date']);
if ($nocust) {
    echo "\n--- No LP customer matched (timma_client_id missing) ---\n";
    foreach ($nocust as $p) echo sprintf("  %s | %s | €%.2f | %s\n", $p['name'], $p['svc'], $p['sum'], $p['date']);
}

echo "\nSUMMARY: " . count($missing) . " missing, " . count($matched) . " already present, "
   . count($membership) . " memberships, " . count($nocust) . " no-customer.\n";

if (!$apply) {
    echo "\nDRY-RUN — nothing written. Re-run with --apply to create the MISSING packages.\n";
    exit(0);
}

/* ---- Apply: create missing bundle orders ---------------------------------- */
require_once __DIR__ . '/timma_maps.php'; // not strictly needed; bundle resolution below
$DEFAULT_PRICE = [5 => 149, 10 => 294];
$FAM_SID = ['EMS' => 5004, 'Jooga personal' => 9001, 'Jooga rühm' => 9003, 'Kavitatsioon' => 1154,
            'Infrapunamatt' => 1151, 'RF-lifting' => 1153, 'Lipolaser' => 1159, 'Massaaž' => 2002];

function lpServiceForTimma(int $sid): int {
    global $wpdb, $P; static $m = null;
    if ($m === null) { $m = []; foreach ($wpdb->get_results("SELECT object_id,meta_value FROM {$P}latepoint_service_meta WHERE meta_key='timma_service_id'") as $r) $m[(string) $r->meta_value] = (int) $r->object_id; }
    return $m[(string) $sid] ?? 0;
}
function bundleForFamSize(string $fam, int $size, array $FAM_SID, array $DEFAULT_PRICE): int {
    global $wpdb, $P, $bundleFamSize;
    // SHARED → existing multi-service shared-pool bundle of this size
    if ($fam === 'SHARED') {
        foreach ($bundleFamSize as $bid => $fs) { if ($fs[0] === 'SHARED' && $fs[1] === $size) return $bid; }
        return 0; // do not invent a shared-pool bundle
    }
    foreach ($bundleFamSize as $bid => $fs) { if ($fs[0] === $fam && $fs[1] === $size) return $bid; }
    // create a single-service bundle for this family+size
    $sid = $FAM_SID[$fam] ?? 0; $svcId = $sid ? lpServiceForTimma($sid) : 0;
    if (!$svcId) return 0;
    $b = new OsBundleModel();
    $b->name = "{$fam} {$size}x kaart"; $b->charge_amount = $DEFAULT_PRICE[$size] ?? 0;
    $b->status = K('LATEPOINT_BUNDLE_STATUS_ACTIVE', 'active');
    $b->visibility = K('LATEPOINT_BUNDLE_VISIBILITY_VISIBLE', 'visible');
    $b->save();
    $dur = (int) ($wpdb->get_var($wpdb->prepare("SELECT duration FROM {$P}latepoint_services WHERE id=%d", $svcId)) ?: 60);
    $b->save_services(['service_' . $svcId => ['quantity' => $size, 'total_attendees' => 1, 'duration' => $dur, 'connected' => 'yes']]);
    $bundleFamSize[(int) $b->id] = [$fam, $size];
    echo "  + created bundle #{$b->id} '{$b->name}'\n";
    return (int) $b->id;
}

echo "\n--- APPLYING ---\n";
$created = 0;
foreach ($missing as $p) {
    $bundleId = bundleForFamSize($p['fam'], $p['size'], $FAM_SID, $DEFAULT_PRICE);
    if ($bundleId <= 0) { echo "  ! no bundle for {$p['fam']} {$p['size']}x — skipped ({$p['name']})\n"; continue; }
    $price = $p['sum']; $purchase = $p['date'] . ' 00:00:00';
    $months = ($p['size'] >= 10) ? 4 : 2;

    $order = new OsOrderModel();
    $order->customer_id = $p['cust'];
    $order->status = K('LATEPOINT_ORDER_STATUS_COMPLETED', 'completed');
    $order->fulfillment_status = K('LATEPOINT_ORDER_FULFILLMENT_STATUS_FULFILLED', 'fulfilled');
    $order->payment_status = K('LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID', 'fully_paid');
    $order->subtotal = $price; $order->total = $price;
    if (!$order->save()) { echo "  ! order save failed for {$p['name']}\n"; continue; }
    $oi = new OsOrderItemModel();
    $oi->order_id = $order->id; $oi->variant = K('LATEPOINT_ITEM_VARIANT_BUNDLE', 'bundle');
    $oi->subtotal = $price; $oi->total = $price;
    $oi->item_data = wp_json_encode(['bundle_id' => $bundleId]);
    if (!$oi->save()) { $order->delete($order->id); echo "  ! item save failed for {$p['name']}\n"; continue; }
    OsMetaHelper::save_order_meta_by_key('timma_receipt', $p['receipt'], $order->id);
    OsMetaHelper::save_order_meta_by_key('valid_for_months', $months, $order->id);
    $wpdb->update("{$P}latepoint_orders", ['created_at' => $purchase, 'updated_at' => $purchase], ['id' => $order->id]);

    if ($withTransaction && $price > 0) {
        $wpdb->insert("{$P}latepoint_order_invoices", ['order_id' => $order->id, 'invoice_number' => 'TIMMA-CARD-' . $order->id, 'status' => 'paid', 'charge_amount' => $price, 'payment_portion' => 'full', 'access_key' => substr(md5('inv' . $order->id), 0, 32), 'created_at' => $purchase, 'updated_at' => $purchase]);
        $invId = (int) $wpdb->insert_id;
        $wpdb->insert("{$P}latepoint_transactions", ['invoice_id' => $invId, 'order_id' => $order->id, 'customer_id' => $p['cust'], 'processor' => 'timma_import', 'payment_method' => 'external', 'payment_portion' => 'full', 'kind' => 'capture', 'status' => 'succeeded', 'amount' => $price, 'notes' => 'Timma card purchase ' . $p['receipt'], 'token' => substr(md5('tx' . $order->id), 0, 32), 'access_key' => substr(md5('txa' . $order->id), 0, 36), 'created_at' => $purchase, 'updated_at' => $purchase]);
    }
    echo "  + {$p['name']}: {$p['fam']} {$p['size']}x -> order #{$order->id} (bundle #{$bundleId}, €{$price}, valid {$months}mo)\n";
    $created++;
}
echo "\nDone. Created {$created} package order(s).\n";
