#!/usr/bin/env php
<?php
/**
 * Phase 2 of the new-company scoping: rebuild Feb 2026+ payments from ACTUAL sources
 * so LatePoint's recorded revenue matches the bank/Stebby/card reality.
 *
 *   - Bank transfers (exact, per-customer): Kaarina 2x230, Heili 145, Julia 450.
 *   - Stebby (exact, per-customer): 25 entries from /tmp/stebby.json, each matched to
 *     the customer's nearest Feb+ order; recorded at the gross service price.
 *   - Card (€5,248.40): daily terminal totals distributed across that day's remaining
 *     (non-transfer, non-Stebby) Feb+ orders proportional to their amount, so the card
 *     total is exact and packages auto-correct from nominal to the day's real amount.
 *
 * Deletes existing (post-Phase-1) transactions+invoices first, then rebuilds. Reports
 * what was placed and any residual the daily-aggregate card data cannot pin.
 *
 * Usage: php -d memory_limit=1024M rebuild_feb_payments.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
$dryRun = in_array('--dry-run', $argv, true);
$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'yumefit.ee'; $_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wpLoad;
global $wpdb; $P = $wpdb->prefix;

// Card daily totals (Yumefit terminal), Feb-Jun 2026; excludes tiny test taps (<=3).
$cardDays = [
 '2026-02-02'=>30,'2026-02-04'=>286,'2026-02-05'=>18,'2026-02-06'=>18,'2026-02-08'=>279,'2026-02-09'=>36,
 '2026-02-11'=>27,'2026-02-13'=>18,'2026-02-14'=>60,'2026-02-15'=>30,'2026-02-20'=>250,'2026-02-21'=>30,
 '2026-02-22'=>36,'2026-02-23'=>347,'2026-02-24'=>30,'2026-02-28'=>18,'2026-02-03'=>9,'2026-02-07'=>9,
 '2026-03-02'=>250,'2026-03-03'=>33,'2026-03-04'=>281.90,'2026-03-05'=>18,'2026-03-06'=>32,'2026-03-07'=>18,
 '2026-03-08'=>18,'2026-03-09'=>215.55,'2026-03-10'=>60,'2026-03-11'=>32,'2026-03-13'=>45,'2026-03-14'=>15,
 '2026-03-15'=>110,'2026-03-16'=>50,'2026-03-18'=>30,'2026-03-23'=>45,'2026-03-24'=>15,'2026-03-25'=>15,
 '2026-03-26'=>15,'2026-03-27'=>15,'2026-03-28'=>30,'2026-03-30'=>56,
 '2026-04-04'=>374,'2026-04-11'=>249.90,'2026-04-17'=>249.90,'2026-04-21'=>50,'2026-04-24'=>24,
 '2026-05-01'=>249.90,'2026-05-04'=>176.65,'2026-05-09'=>174.65,'2026-05-13'=>79,'2026-05-14'=>57,
 '2026-05-19'=>24,'2026-05-20'=>126.65,'2026-05-23'=>64,'2026-05-25'=>201.65,
 '2026-06-04'=>151.65,'2026-06-07'=>25,'2026-06-11'=>27.50,
];
$transfers = [ // order_id => [amount, date, note]
 2953=>[230,'2026-05-30','Bank transfer (EMS 10x, 2 kaarti, 460 total)'],
 2954=>[230,'2026-05-30','Bank transfer (EMS 10x, 2 kaarti, 460 total)'],
 2765=>[145,'2026-02-19','yumefit.ee online (st576510)'],
 2955=>[450,'2026-04-15','Bank transfer, Invoice 202601 (monthly package)'],
];

echo "=========== Rebuild Feb+ payments from actuals ===========\n";
echo "DB: " . DB_NAME . " | " . ($dryRun ? 'DRY-RUN' : 'LIVE') . "\n";
echo "Targets: card " . array_sum($cardDays) . " + stebby (gross) + transfers 1055\n";
echo "==========================================================\n";

// economic date of an order: bundle -> created_at; else earliest booking start_date
function econ_date($c, $P, $oid) {
    $isB = (int) mysqli_fetch_row(mysqli_query($c, "SELECT EXISTS(SELECT 1 FROM {$P}latepoint_order_items WHERE order_id=$oid AND variant='bundle')"))[0];
    if ($isB) { return substr((string) mysqli_fetch_row(mysqli_query($c, "SELECT created_at FROM {$P}latepoint_orders WHERE id=$oid"))[0], 0, 10); }
    $r = mysqli_fetch_row(mysqli_query($c, "SELECT MIN(bk.start_date) FROM {$P}latepoint_order_items oi JOIN {$P}latepoint_bookings bk ON bk.order_item_id=oi.id WHERE oi.order_id=$oid"));
    return $r ? (string) $r[0] : null;
}
$c = $wpdb->dbh;
mysqli_set_charset($c, 'utf8mb4');
function ins($c, $P, $t, $d) { $cols=implode(',',array_keys($d)); $v=implode(',',array_map(fn($x)=>"'".mysqli_real_escape_string($c,$x)."'",array_values($d))); mysqli_query($c,"INSERT INTO {$P}latepoint_$t ($cols) VALUES ($v)"); return mysqli_insert_id($c); }
function pay($c, $P, $oid, $cust, $amt, $proc, $date, $note, $dry) {
    if ($dry) { return; }
    mysqli_query($c, "UPDATE {$P}latepoint_orders SET subtotal=$amt, total=$amt, payment_status='fully_paid' WHERE id=$oid");
    mysqli_query($c, "UPDATE {$P}latepoint_order_items SET subtotal=$amt, total=$amt WHERE order_id=$oid AND variant='booking'");
    $inv = ins($c,$P,'order_invoices',['order_id'=>$oid,'invoice_number'=>strtoupper($proc)."-$oid",'status'=>'paid','charge_amount'=>$amt,'payment_portion'=>'full','access_key'=>substr(md5("i$proc$oid$amt"),0,32),'created_at'=>"$date 00:00:00",'updated_at'=>"$date 00:00:00"]);
    ins($c,$P,'transactions',['invoice_id'=>$inv,'order_id'=>$oid,'customer_id'=>$cust,'processor'=>$proc,'payment_method'=>$proc,'payment_portion'=>'full','kind'=>'capture','status'=>'succeeded','amount'=>$amt,'notes'=>$note,'token'=>substr(md5("t$proc$oid$amt"),0,32),'access_key'=>substr(md5("a$proc$oid$amt"),0,36),'created_at'=>"$date 00:00:00",'updated_at'=>"$date 00:00:00"]);
}

// 0. wipe current (post-Phase-1) transactions + invoices
if (!$dryRun) { mysqli_query($c, "DELETE FROM {$P}latepoint_transactions"); mysqli_query($c, "DELETE FROM {$P}latepoint_order_invoices"); mysqli_query($c, "UPDATE {$P}latepoint_orders SET payment_status='not_paid' WHERE total>0"); }

$covered = []; $totStebby = 0; $totCard = 0; $totXfer = 0;
// 1. transfers
foreach ($transfers as $oid => [$amt,$date,$note]) {
    $cust = (int) mysqli_fetch_row(mysqli_query($c, "SELECT customer_id FROM {$P}latepoint_orders WHERE id=$oid"))[0];
    pay($c,$P,$oid,$cust,$amt,'bank_transfer',$date,$note,$dryRun); $covered[$oid]=1; $totXfer+=$amt;
}
// 2. stebby (gross, per-customer) -> nearest uncovered Feb+ order
$stebby = json_decode(file_get_contents('/tmp/stebby.json'), true);
$smatch=0; $sunm=0;
foreach ($stebby as $e) {
    $nm = mysqli_real_escape_string($c, $e['name']);
    $cu = mysqli_fetch_row(mysqli_query($c, "SELECT id FROM {$P}latepoint_customers WHERE CONCAT(first_name,' ',last_name)='$nm' LIMIT 1"));
    if (!$cu) { $sunm++; continue; }
    $cid = (int) $cu[0]; $d = $e['date']; $exclude = $covered ? implode(',', array_keys($covered)) : '0';
    $row = mysqli_fetch_row(mysqli_query($c, "SELECT o.id FROM {$P}latepoint_orders o WHERE o.customer_id=$cid AND o.id NOT IN ($exclude) AND o.total>=0
        ORDER BY ABS(DATEDIFF(COALESCE((SELECT MIN(bk.start_date) FROM {$P}latepoint_order_items oi JOIN {$P}latepoint_bookings bk ON bk.order_item_id=oi.id WHERE oi.order_id=o.id), o.created_at), '$d')) ASC LIMIT 1"));
    if (!$row) { $sunm++; continue; }
    $oid=(int)$row[0]; pay($c,$P,$oid,$cid,$e['amt'],'stebby',$d,'Stebby '.$d,$dryRun); $covered[$oid]=1; $totStebby+=$e['amt']; $smatch++;
}
// 3. card: per day distribute across that day's uncovered Feb+ orders (proportional to total)
$cardPlaced=0; $cardResidual=0;
foreach ($cardDays as $d => $tot) {
    $exclude = $covered ? implode(',', array_keys($covered)) : '0';
    $orders = [];
    $res = mysqli_query($c, "SELECT o.id, o.total, o.customer_id FROM {$P}latepoint_orders o
        WHERE o.id NOT IN ($exclude) AND o.total>0
        AND COALESCE((SELECT MIN(bk.start_date) FROM {$P}latepoint_order_items oi JOIN {$P}latepoint_bookings bk ON bk.order_item_id=oi.id WHERE oi.order_id=o.id),
                     CASE WHEN EXISTS(SELECT 1 FROM {$P}latepoint_order_items oi2 WHERE oi2.order_id=o.id AND oi2.variant='bundle') THEN DATE(o.created_at) END) = '$d'");
    while ($r = mysqli_fetch_assoc($res)) { $orders[] = $r; }
    if (!$orders) { $cardResidual += $tot; continue; }
    $sumT = array_sum(array_map(fn($o)=>(float)$o['total'], $orders)); if ($sumT<=0) { $cardResidual+=$tot; continue; }
    foreach ($orders as $o) {
        $share = round($tot * ((float)$o['total'] / $sumT), 2);
        pay($c,$P,(int)$o['id'],(int)$o['customer_id'],$share,'card',$d,'Card '.$d,$dryRun); $covered[$o['id']]=1; $cardPlaced+=$share;
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Transfers: %.2f | Stebby: %.2f (matched %d, unmatched %d) | Card placed: %.2f (residual unplaced: %.2f)\n", $totXfer, $totStebby, $smatch, $sunm, $cardPlaced, $cardResidual);
echo sprintf("Rebuilt Feb+ total: %.2f (target ~%.2f)\n", $totXfer+$totStebby+$cardPlaced, 1055 + 1414 + array_sum($cardDays));
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
