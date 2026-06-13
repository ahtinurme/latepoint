#!/usr/bin/env php
<?php
/**
 * Import Timma clients + their bookings (and, next, orders) into LatePoint.
 * Pulls FRESH data from the Timma API (token rotates, pass it in). Idempotent.
 *
 * Customers: matched by timma_client_id meta -> email -> phone; created if new.
 *   "Püsiklient" (meta is_pusiklient=yes + admin_notes marker) when ANY of:
 *     >=5 successful (non-cancelled) bookings, >=5 purchases, or a 5x+ package.
 *   Customer notes: Timma `info` (Broneerimisteave) -> admin_notes.
 * Bookings: matched by booking_meta timma_slot_id; created with correct status
 *   (no_show / cancelled / approved / completed), agent, service, local time.
 *   Booking notes: Timma bookingNote -> booking_meta `note`.
 *
 * Usage: php -d memory_limit=1024M import_timma_customers_bookings.php --token=... [opts]
 *   --token= (req) --email= --biz= --what=customers|bookings|all (default all)
 *   --limit=N  --dry-run  --cache
 */

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }

$opts = getopt('', ['token:', 'email:', 'biz:', 'what:', 'limit:', 'pusiklient-threshold:', 'dry-run', 'cache']);
$token = $opts['token'] ?? getenv('TIMMA_TOKEN') ?: '';
$email = $opts['email'] ?? 'carmen.kaljula@gmail.com';
$biz   = $opts['biz']   ?? '673e4475236e6416d9c1fa32';
$what  = $opts['what']  ?? 'all';
$limit = isset($opts['limit']) ? (int) $opts['limit'] : 0;
$pusiThreshold = isset($opts['pusiklient-threshold']) ? (int) $opts['pusiklient-threshold'] : 5;
$dryRun = isset($opts['dry-run']);
$cache  = isset($opts['cache']);
$doCustomers = in_array($what, ['customers', 'all'], true);
$doBookings  = in_array($what, ['bookings', 'all'], true);

if ($token === '') { fwrite(STDERR, "ERROR: --token is required\n"); exit(1); }

$wpLoad = realpath(__DIR__ . '/../../../wp-load.php');
if (!$wpLoad) { fwrite(STDERR, "wp-load.php not found\n"); exit(1); }
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? 'yumefit.ee';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define('WP_USE_THEMES', false);
require $wpLoad;
require __DIR__ . '/timma_maps.php';

if (!class_exists('OsCustomerModel') || !class_exists('OsMetaHelper') || !class_exists('OsBookingModel')) {
    fwrite(STDERR, "ERROR: LatePoint not loaded\n"); exit(1);
}

global $wpdb;
$P = $wpdb->prefix;
$nowUtc = gmdate('Y-m-d\TH:i:s.000\Z');
$cacheDir = (realpath(__DIR__ . '/../.context/timma') ?: (__DIR__ . '/../.context/timma'));
if ($cache && !is_dir($cacheDir . '/slots')) { @mkdir($cacheDir . '/slots', 0777, true); }

echo "============ Timma customers/bookings import ============\n";
echo "DB    : " . DB_NAME . " @ " . DB_HOST . "\n";
echo "Mode  : " . ($dryRun ? 'DRY-RUN' : 'LIVE') . " | what={$what}" . ($limit ? " | limit={$limit}" : "") . "\n";
echo "=========================================================\n";

function timma_get(string $path, string $token, string $email) {
    $res = wp_remote_get('https://pro.timma.ee' . $path, [
        'headers' => ['x-auth-token' => $token, 'x-auth-email' => $email, 'accept' => 'application/json'],
        'timeout' => 45,
    ]);
    if (is_wp_error($res)) { fwrite(STDERR, "Timma error: " . $res->get_error_message() . "\n"); return null; }
    if (wp_remote_retrieve_response_code($res) !== 200) {
        fwrite(STDERR, "Timma {$path} HTTP " . wp_remote_retrieve_response_code($res) . " (token expired?)\n"); return null;
    }
    return json_decode(wp_remote_retrieve_body($res), true);
}
function normPhone(?string $p): string { return preg_replace('/^372/', '', preg_replace('/\D/', '', (string) $p)); }
function packageSize(?string $note): int {
    if ($note && preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})/', $note, $m) && (int)$m[1] >= (int)$m[2] && (int)$m[1] <= 50) return (int) $m[1];
    return 0;
}
function findCustomerIdByMeta(string $t): ?int { global $wpdb,$P; $id=$wpdb->get_var($wpdb->prepare("SELECT object_id FROM {$P}latepoint_customer_meta WHERE meta_key='timma_client_id' AND meta_value=%s LIMIT 1",$t)); return $id?(int)$id:null; }
function findCustomerIdByEmail(string $e): ?int { global $wpdb,$P; if($e==='')return null; $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$P}latepoint_customers WHERE LOWER(email)=%s LIMIT 1",strtolower($e))); return $id?(int)$id:null; }
function findCustomerIdByPhone(string $p): ?int { global $wpdb,$P; $np=normPhone($p); if(strlen($np)<7)return null; $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$P}latepoint_customers WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') LIKE %s LIMIT 1",'%'.$wpdb->esc_like(substr($np,-7)))); return $id?(int)$id:null; }
function K(string $name, string $fallback): string { return defined($name) ? constant($name) : $fallback; }

/** Create order -> order_item -> booking (+ invoice/transaction when paid) for one Timma slot. */
function tm_import_slot(array $slot, int $customerId, array $staff, string $nowUtc, array &$st): void {
    global $wpdb, $P;
    $slotId = (string) ($slot['id'] ?? ''); if ($slotId === '') return;
    if ($wpdb->get_var($wpdb->prepare("SELECT object_id FROM {$P}latepoint_booking_meta WHERE meta_key='timma_slot_id' AND meta_value=%s LIMIT 1", $slotId))) { $st['bk_skip']++; return; }
    $sid = $slot['reservation']['serviceId'] ?? null;
    if ($sid === null) { $st['bk_skip_noservice']++; return; }

    $status    = tm_booking_status($slot, $nowUtc);
    $sname     = (string) ($slot['reservation']['serviceName'] ?? $slot['title'] ?? '');
    $serviceId = tm_resolve_service_id($sid, $sname, false);
    $agentId   = tm_resolve_agent_id((string) ($slot['userId'] ?? ''), $staff, false);
    $locId     = tm_location_id();
    if (!$serviceId || !$agentId || !$locId) { echo "    !! unresolved svc/agent/loc for slot {$slotId}\n"; $st['bk_fail']++; return; }

    [$sd, $smin, $sutc] = tm_local($slot['start']);
    [$ed, $emin, $eutc] = tm_local($slot['end']);
    $dur = max(0, (int) round((strtotime($slot['end']) - strtotime($slot['start'])) / 60));

    $revs = is_array($slot['revenues'] ?? null) ? $slot['revenues'] : [];
    $paidC = 0; $card = 0; $cash = 0; $gift = 0; $other = 0; $receipt = '';
    foreach ($revs as $r) {
        $paidC += (int) ($r['sum'] ?? 0); $card += (int) ($r['sumCard'] ?? 0); $cash += (int) ($r['sumCash'] ?? 0);
        $gift += (int) ($r['sumGiftCard'] ?? 0); $other += (int) ($r['sumOther'] ?? 0);
        if ($receipt === '' && !empty($r['receiptNumber'])) $receipt = (string) $r['receiptNumber'];
    }
    $svcPriceC  = (int) ($slot['reservation']['servicePrice'] ?? 0);
    $totalC     = $paidC > 0 ? $paidC : $svcPriceC;
    $total      = round($totalC / 100, 2);
    $isCancelled = ($status === K('LATEPOINT_BOOKING_STATUS_CANCELLED', 'cancelled'));
    $note       = trim((string) ($slot['reservation']['bookingNote'] ?? ''));

    // order
    $order = new OsOrderModel();
    $order->customer_id = $customerId;
    $order->status = $isCancelled ? K('LATEPOINT_ORDER_STATUS_CANCELLED', 'cancelled') : K('LATEPOINT_ORDER_STATUS_COMPLETED', 'completed');
    $order->fulfillment_status = $isCancelled ? K('LATEPOINT_ORDER_FULFILLMENT_STATUS_NOT', 'not_fulfilled') : K('LATEPOINT_ORDER_FULFILLMENT_STATUS_FULFILLED', 'fulfilled');
    $order->payment_status = ($paidC > 0 || $totalC == 0) ? K('LATEPOINT_ORDER_PAYMENT_STATUS_FULLY', 'fully') : K('LATEPOINT_ORDER_PAYMENT_STATUS_NOT', 'not_paid');
    $order->subtotal = $total; $order->total = $total;
    if ($note !== '') $order->customer_comment = $note;
    if (!$order->save()) { echo "    !! order save failed slot {$slotId}\n"; $st['bk_fail']++; return; }

    // booking (built, then linked to its order_item)
    $bk = new OsBookingModel();
    $bk->customer_id = $customerId; $bk->service_id = $serviceId; $bk->agent_id = $agentId; $bk->location_id = $locId;
    $bk->start_date = $sd; $bk->end_date = $sd; $bk->start_time = $smin; $bk->end_time = $emin; $bk->duration = $dur ?: null;
    $bk->start_datetime_utc = $sutc; $bk->end_datetime_utc = $eutc; $bk->status = $status;
    $bk->buffer_before = 0; $bk->buffer_after = 0; $bk->total_attendees = 1;
    $bk->customer_timezone = 'Europe/Tallinn'; $bk->server_timezone = 'Europe/Tallinn';

    $oi = new OsOrderItemModel();
    $oi->order_id = $order->id; $oi->variant = K('LATEPOINT_ITEM_VARIANT_BOOKING', 'booking');
    $oi->subtotal = $total; $oi->total = $total;
    $oi->item_data = $bk->generate_item_data();
    if (!$oi->save()) { $order->delete($order->id); echo "    !! order_item save failed slot {$slotId}\n"; $st['bk_fail']++; return; }

    $bk->order_item_id = $oi->id;
    if (!$bk->save()) { echo "    !! booking save failed slot {$slotId}: " . implode(';', $bk->get_error_messages()) . "\n"; $oi->delete($oi->id); $order->delete($order->id); $st['bk_fail']++; return; }
    $oi->item_data = $bk->generate_item_data(); $oi->save(); // refresh with booking id

    OsMetaHelper::save_booking_meta_by_key('timma_slot_id', $slotId, $bk->id);
    if ($note !== '') OsMetaHelper::save_booking_meta_by_key('note', $note, $bk->id);

    if ($paidC > 0) {
        $inv = new OsInvoiceModel();
        $inv->order_id = $order->id; $inv->charge_amount = round($paidC / 100, 2);
        $inv->status = K('LATEPOINT_INVOICE_STATUS_PAID', 'paid');
        $inv->payment_portion = K('LATEPOINT_PAYMENT_PORTION_FULL', 'full');
        $inv->invoice_number = $receipt !== '' ? $receipt : ('TIMMA-' . $slotId);
        $inv->save();
        $pm = $card > 0 ? 'card' : ($cash > 0 ? 'cash' : ($gift > 0 ? 'gift_card' : ($other > 0 ? 'voucher' : 'other')));
        $tx = new OsTransactionModel();
        $tx->order_id = $order->id; $tx->invoice_id = $inv->id; $tx->customer_id = $customerId;
        $tx->amount = round($paidC / 100, 2); $tx->status = K('LATEPOINT_TRANSACTION_STATUS_SUCCEEDED', 'succeeded');
        $tx->kind = K('LATEPOINT_TRANSACTION_KIND_CAPTURE', 'capture'); $tx->processor = 'timma_import';
        $tx->payment_method = $pm; $tx->payment_portion = K('LATEPOINT_PAYMENT_PORTION_FULL', 'full');
        $tx->receipt_number = $receipt;
        $tx->save();
        $st['orders_paid']++;
    }

    if (!empty($slot['createdAt'])) {
        $ca = (new DateTime($slot['createdAt']))->format('Y-m-d H:i:s');
        $wpdb->update("{$P}latepoint_bookings", ['created_at' => $ca], ['id' => $bk->id]);
        $wpdb->update("{$P}latepoint_orders", ['created_at' => $ca], ['id' => $order->id]);
    }
    $st['bk_create']++;
    $st['bk_status'][$status] = ($st['bk_status'][$status] ?? 0) + 1;
}

// staff map for agent resolution
$staff = [];
foreach ((timma_get("/api/users/customer/{$biz}?includeRemoved=true", $token, $email) ?: []) as $u) {
    $staff[(string) ($u['id'] ?? '')] = ['name' => $u['name'] ?? '', 'email' => $u['email'] ?? ''];
}

$clientsResp = timma_get("/api/clients/customer/{$biz}/v3?direction=asc&limit=2000&search=&sort=name", $token, $email);
if ($clientsResp === null) { exit(1); }
$clients = $clientsResp['results'] ?? [];
echo "Timma clients: " . count($clients) . " | staff: " . count($staff) . "\n";
if ($limit > 0) { $clients = array_slice($clients, 0, $limit); echo "  (limited to {$limit})\n"; }

$st = ['cust_created'=>0,'cust_updated'=>0,'pusiklient'=>0,'bk_create'=>0,'bk_skip'=>0,'bk_skip_noservice'=>0,'bk_fail'=>0,'bk_nocustomer'=>0,'orders_paid'=>0,'bk_status'=>[]];
$i = 0;

foreach ($clients as $c) {
    $i++;
    $cid   = (string) ($c['id'] ?? '');
    $name  = trim((string) ($c['name'] ?? ''));
    $parts = preg_split('/\s+/', $name, 2);
    $first = $parts[0] ?? ''; $last = $parts[1] ?? '';
    $em    = strtolower(trim((string) ($c['email'] ?? '')));
    $phone = trim((string) ($c['phone'] ?? ''));
    $info  = trim((string) ($c['info'] ?? ''));
    $addr  = trim((string) ($c['address'] ?? '')); $city = trim((string) ($c['city'] ?? '')); $zip = trim((string) ($c['postalCode'] ?? ''));
    $pastVisits = (int) ($c['numberOfPastVisits'] ?? 0);

    $slots = timma_get("/api/serviceslots/client/{$cid}?includeCancelled=true", $token, $email);
    $slots = is_array($slots) ? $slots : [];
    if ($cache && !$dryRun) @file_put_contents($cacheDir . '/slots/' . $cid . '.json', json_encode($slots));

    // püsiklient
    $successful = 0; $receipts = []; $maxPkg = 0;
    foreach ($slots as $s) {
        if (empty($s['deletedOn']) && empty($s['cancellationReason'])) $successful++;
        foreach (($s['revenues'] ?? []) as $r) if (!empty($r['receiptNumber'])) $receipts[$r['receiptNumber']] = true;
        $maxPkg = max($maxPkg, packageSize($s['reservation']['bookingNote'] ?? ''));
    }
    $successful = max($successful, $pastVisits);
    $isPusiklient = ($successful >= $pusiThreshold) || (count($receipts) >= $pusiThreshold) || ($maxPkg >= 5);

    $emailToUse = $em !== '' ? $em : ('noemail+' . (preg_replace('/\D/', '', $phone) ?: 'c' . substr($cid, -8)) . '@yumefit.invalid');

    // ---- upsert customer ----
    $customerId = findCustomerIdByMeta($cid) ?? ($em !== '' ? findCustomerIdByEmail($em) : null) ?? findCustomerIdByPhone($phone);
    if ($i % 50 === 0) echo "  ...{$i}/" . count($clients) . "\n";

    if ($doCustomers && !$dryRun) {
        if ($customerId) {
            $cust = new OsCustomerModel($customerId);
            if ($first && !$cust->first_name) $cust->first_name = $first;
            if ($last && !$cust->last_name) $cust->last_name = $last;
            if ($phone && !$cust->phone) $cust->phone = $phone;
            if ($info && !$cust->admin_notes) $cust->admin_notes = $info; // import notes if missing
            $cust->save(); $st['cust_updated']++;
        } else {
            $cust = new OsCustomerModel();
            $cust->first_name = $first ?: '-'; $cust->last_name = $last ?: '-';
            $cust->email = $emailToUse; $cust->phone = $phone !== '' ? $phone : '00000000'; // phone is required
            $an = [];
            if ($isPusiklient) $an[] = 'Püsiklient';
            if ($addr || $city || $zip) $an[] = trim("$addr, $zip $city", ', ');
            if ($info) $an[] = $info;
            $cust->admin_notes = implode("\n", $an);
            $cust->status = defined('LATEPOINT_CUSTOMER_STATUS_ACTIVE') ? LATEPOINT_CUSTOMER_STATUS_ACTIVE : 'active';
            $cust->is_guest = 0;
            if (!$cust->save()) { echo "  !! cust save failed {$name}: " . implode(';', $cust->get_error_messages()) . "\n"; continue; }
            $customerId = (int) $cust->id; $st['cust_created']++;
        }
        OsMetaHelper::save_customer_meta_by_key('timma_client_id', $cid, $customerId);
        OsMetaHelper::save_customer_meta_by_key('is_pusiklient', $isPusiklient ? 'yes' : 'no', $customerId);
        OsMetaHelper::save_customer_meta_by_key('accept_email_marketing', !empty($c['acceptEmailMarketing']) ? 'yes' : 'no', $customerId);
        OsMetaHelper::save_customer_meta_by_key('accept_sms_marketing', !empty($c['acceptSmsMarketing']) ? 'yes' : 'no', $customerId);
        if ($isPusiklient) {
            $st['pusiklient']++;
            $cc = new OsCustomerModel($customerId);
            if (stripos((string) $cc->admin_notes, 'püsiklient') === false) { $cc->admin_notes = trim("Püsiklient\n" . (string) $cc->admin_notes); $cc->save(); }
        }
    } elseif ($dryRun) {
        $customerId ? $st['cust_updated']++ : $st['cust_created']++;
        if ($isPusiklient) $st['pusiklient']++;
    }

    // ---- bookings + orders ----
    if ($doBookings) {
        if (!$customerId) { $st['bk_nocustomer'] += count($slots); continue; }
        foreach ($slots as $slot) {
            if ($dryRun) {
                $slotId = (string) ($slot['id'] ?? ''); if ($slotId === '') continue;
                if (($slot['reservation']['serviceId'] ?? null) === null) { $st['bk_skip_noservice']++; continue; }
                $status = tm_booking_status($slot, $nowUtc);
                $st['bk_status'][$status] = ($st['bk_status'][$status] ?? 0) + 1;
                $st['bk_create']++;
                continue;
            }
            tm_import_slot($slot, (int) $customerId, $staff, $nowUtc, $st);
        }
    }
}

echo "\n==================== SUMMARY ====================\n";
echo sprintf("Customers: created %d, updated %d | püsiklient %d\n", $st['cust_created'], $st['cust_updated'], $st['pusiklient']);
echo sprintf("Bookings : created %d, skipped(existing) %d, skipped(no service) %d, no-customer %d, failed %d\n",
    $st['bk_create'], $st['bk_skip'], $st['bk_skip_noservice'], $st['bk_nocustomer'], $st['bk_fail']);
echo sprintf("Orders w/ payment (invoice+transaction): %d\n", $st['orders_paid']);
echo "Booking status breakdown: " . json_encode($st['bk_status']) . "\n";
echo ($dryRun ? "DRY-RUN — no changes written.\n" : "Done.\n");
