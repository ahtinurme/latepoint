<?php
/**
 * Plugin Name: LatePoint Yumefit Rules
 * Description: Site-specific LatePoint customizations for Yumefit. (1) Shared redemption
 *              pool for "shared pool" bundles (N sessions usable across all included
 *              services combined, vs LatePoint's default N-per-service). (2) Package
 *              validity window — bundle sessions can only be booked within N months of
 *              purchase (default 2). (3) Auto-applies a percentage discount coupon
 *              for "püsiklient" (loyal) customers, driven by the "Püsiklient?" customer
 *              custom field as the single source of truth. (4) Gift cards — buy a
 *              package as a gift in the native booking flow; a one-time voucher code is
 *              shown to the buyer and emailed to them to hand over, and the buyer's own
 *              copy is locked. (5) Admin-only payment method Sularaha for
 *              recording cash payments.
 * Version:     1.9.0
 * Author:      Yumefit
 * Text Domain: latepoint-yumefit-rules
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enforce package (bundle) rules when a bundle session is being booked/scheduled.
 *
 * Runs on `latepoint_check_steps_for_errors`, where OsStepsHelper::$booking_object
 * holds the booking being placed (with its bundle order_item_id). Adds errors to
 * block the booking when a rule is violated. Fail-open: only ever blocks when it
 * positively identifies a bundle redemption that breaks a rule.
 *
 * Rule 1 — Validity window: a bundle's sessions can only be booked within
 *   `valid_for_months` (bundle meta) or the global `yumefit_bundle_validity_months`
 *   option (default 2) months of the purchase (order created_at).
 *
 * Rule 2 — Shared pool: for bundles flagged in `yumefit_shared_pool_bundles`
 *   ([bundle_id => total]), the total is shared across ALL the bundle's services
 *   (a non-cancelled booking counts as used).
 */
/* ===== CUSTOM CODE START (yumefit: package expiry helpers + display) =====
 * Shared by the validity-enforcement rule AND the admin/customer "valid until"
 * display, so what's shown always matches what's enforced. Validity precedence:
 * per-order meta `valid_for_months` (a specific card) -> bundle meta
 * `valid_for_months` (card type, e.g. 10x = 4) -> global option (default 2). */
function yumefit_bundle_validity_months($order, $bundle = null): int {
    if ($order && !empty($order->id) && class_exists('OsMetaHelper')) {
        $m = OsMetaHelper::get_order_meta_by_key('valid_for_months', $order->id, '');
        if (is_numeric($m) && (int) $m > 0) {
            return (int) $m;
        }
    }
    if ($bundle && method_exists($bundle, 'get_meta_by_key')) {
        $m = $bundle->get_meta_by_key('valid_for_months');
        if (is_numeric($m) && (int) $m > 0) {
            return (int) $m;
        }
    }
    return (int) get_option('yumefit_bundle_validity_months', 2);
}

function yumefit_bundle_expiry_date($order, $bundle = null): ?DateTime {
    $months = yumefit_bundle_validity_months($order, $bundle);
    $purchased = ($order && !empty($order->created_at)) ? (string) $order->created_at : '';
    if ($months <= 0 || $purchased === '') {
        return null;
    }
    try {
        return (new DateTime(substr($purchased, 0, 10)))->modify('+' . $months . ' months');
    } catch (\Throwable $e) {
        return null;
    }
}

/** Resolve the bundle object for an order (its first bundle order-item), or null. */
function yumefit_order_bundle($order) {
    if (!$order || empty($order->id) || !class_exists('OsOrderItemModel')) {
        return null;
    }
    global $wpdb;
    $oiId = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}latepoint_order_items WHERE order_id = %d AND variant = 'bundle' LIMIT 1",
        (int) $order->id
    ));
    if (!$oiId) {
        return null;
    }
    $order_item = new OsOrderItemModel($oiId);
    return method_exists($order_item, 'build_original_object_from_item_data') ? $order_item->build_original_object_from_item_data() : null;
}

/** "Pakett kehtib kuni DD.MM.YYYY" line for a package order (empty if not a package). */
function yumefit_package_expiry_html($order, $bundle = null): string {
    if (!$order) {
        return '';
    }
    if (!$bundle) {
        $bundle = yumefit_order_bundle($order);
    }
    if (!$bundle) {
        return '';
    }
    $expiry = yumefit_bundle_expiry_date($order, $bundle);
    if (!$expiry) {
        return '';
    }
    return '<div class="yumefit-package-expiry" style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid #eee;font-weight:600;">'
        . '<span>' . esc_html__('Package valid until', 'latepoint-yumefit-rules') . '</span>'
        . '<span>' . esc_html($expiry->format('d.m.Y')) . '</span>'
        . '</div>';
}

// Admin order edit panel (under the price breakdown) + the order summary shown to
// both admin and customer (order summary lightbox includes _full_summary.php).
add_action('latepoint_order_quick_form_price_after_total', 'yumefit_show_package_expiry');
add_action('latepoint_order_full_summary_head_info_after', 'yumefit_show_package_expiry');
function yumefit_show_package_expiry($order): void {
    echo yumefit_package_expiry_html($order);
}

// Show the customer's packages on the admin customer edit form (LatePoint core has
// no per-customer package view). Lists each bundle order: name, used/total, valid
// until, paid status. The "Add package" button opens LatePoint's native new-order
// side panel pre-filled with this customer, where any admin/agent picks the bundle
// and marks it paid — so we reuse core order creation instead of replicating it.
add_action('latepoint_customer_edit_form_after', 'yumefit_show_customer_packages');
function yumefit_show_customer_packages($customer): void {
    if (empty($customer->id) || !class_exists('OsOrderModel')) {
        return;
    }
    global $wpdb; $P = $wpdb->prefix;
    $cancelled = defined('LATEPOINT_BOOKING_STATUS_CANCELLED') ? LATEPOINT_BOOKING_STATUS_CANCELLED : 'cancelled';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT o.id order_id, oi.id oi_id, oi.item_data, o.payment_status,
                (SELECT COUNT(*) FROM {$P}latepoint_bookings b WHERE b.order_item_id = oi.id AND b.status <> %s) used
         FROM {$P}latepoint_orders o
         JOIN {$P}latepoint_order_items oi ON oi.order_id = o.id AND oi.variant = 'bundle'
         WHERE o.customer_id = %d ORDER BY o.created_at DESC",
        $cancelled, (int) $customer->id
    ));

    $add_btn = class_exists('OsOrdersHelper')
        ? '<a href="#" ' . OsOrdersHelper::quick_order_btn_html(false, ['customer_id' => (int) $customer->id]) . ' class="latepoint-btn latepoint-btn-primary"><i class="latepoint-icon latepoint-icon-plus"></i><span>' . esc_html__('Add package', 'latepoint-yumefit-rules') . '</span></a>'
        : '';

    echo '<div class="white-box"><div class="white-box-header"><div class="os-form-sub-header"><h3>' . esc_html__('Packages', 'latepoint-yumefit-rules') . '</h3></div>' . $add_btn . '</div>';

    if (!$rows) {
        echo '<div class="white-box-section"><p style="margin:0;color:#6b6b6b;">' . esc_html__('No packages yet.', 'latepoint-yumefit-rules') . '</p></div></div>';
        return;
    }

    $shared = get_option('yumefit_shared_pool_bundles', []);
    echo '<div class="white-box-section"><table style="width:100%;border-collapse:collapse;">';
    foreach ($rows as $r) {
        $item = json_decode($r->item_data, true);
        $bundleId = (int) ($item['bundle_id'] ?? 0);
        $bundle = $bundleId ? new OsBundleModel($bundleId) : null;
        $name = ($bundle && !empty($bundle->name)) ? $bundle->name : ('Bundle #' . $bundleId);
        $qty = (is_array($shared) && !empty($shared[$bundleId]))
            ? (int) $shared[$bundleId]
            : (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(quantity) FROM {$P}latepoint_bundles_services WHERE bundle_id = %d", $bundleId));
        $order = new OsOrderModel((int) $r->order_id);
        $expiry = function_exists('yumefit_bundle_expiry_date') ? yumefit_bundle_expiry_date($order, $bundle) : null;
        $paid = ($r->payment_status === (defined('LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID') ? LATEPOINT_ORDER_PAYMENT_STATUS_FULLY_PAID : 'fully_paid'));
        echo '<tr style="border-bottom:1px solid #eee;">'
            . '<td style="padding:6px 8px 6px 0;">' . esc_html($name) . '</td>'
            . '<td style="padding:6px 8px;white-space:nowrap;">' . (int) $r->used . ' / ' . $qty . '</td>'
            . '<td style="padding:6px 8px;white-space:nowrap;color:#6b6b6b;">' . ($expiry ? esc_html__('until', 'latepoint-yumefit-rules') . ' ' . esc_html($expiry->format('d.m.Y')) : '') . '</td>'
            . '<td style="padding:6px 0 6px 8px;text-align:right;">' . ($paid ? '✓ ' . esc_html__('paid', 'latepoint-yumefit-rules') : esc_html(OsOrdersHelper::get_nice_order_payment_status_name($r->payment_status))) . '</td>'
            . '</tr>';
    }
    echo '</table></div></div>';
}
/* ===== CUSTOM CODE END (yumefit: package expiry helpers + display) ===== */

add_filter('latepoint_check_steps_for_errors', 'yumefit_enforce_bundle_rules', 20, 3);

function yumefit_enforce_bundle_rules(array $errors, array $steps, array $steps_rules): array {
    if (!class_exists('OsStepsHelper') || !class_exists('OsOrderItemModel')) {
        return $errors;
    }

    $booking = OsStepsHelper::$booking_object ?? null;
    if (empty($booking) || empty($booking->order_item_id) || !is_numeric($booking->order_item_id)) {
        return $errors; // no real (saved) bundle order item in context
    }

    $order_item = new OsOrderItemModel((int) $booking->order_item_id);
    if (!method_exists($order_item, 'is_bundle') || !$order_item->is_bundle()) {
        return $errors;
    }

    $bundle    = $order_item->build_original_object_from_item_data();
    $bundle_id = (int) ($bundle->id ?? 0);

    // --- Rule 1: validity window (applies to ALL bundles) ---
    // CUSTOM: validity now comes from the shared helper (order meta -> bundle meta
    // -> global), so enforcement matches the displayed "valid until" date.
    if (!empty($booking->start_date) && !empty($order_item->order_id) && class_exists('OsOrderModel')) {
        $order  = new OsOrderModel((int) $order_item->order_id);
        $expiry = yumefit_bundle_expiry_date($order, $bundle);
        if ($expiry) {
            try {
                $start = new DateTime(substr((string) $booking->start_date, 0, 10));
                if ($start > $expiry) {
                    $errors['yumefit_bundle_expired'] = sprintf(
                        /* translators: %s is a date */
                        __('This package can only be used until %s.', 'latepoint-yumefit-rules'),
                        $expiry->format('d.m.Y')
                    );
                }
            } catch (\Throwable $e) {
                // fail-open
            }
        }
    }

    // --- Rule 2: shared redemption pool (only for flagged bundles) ---
    $shared = get_option('yumefit_shared_pool_bundles', []);
    if ($bundle_id && is_array($shared) && !empty($shared[$bundle_id])) {
        $cap = (int) $shared[$bundle_id];

        global $wpdb;
        $used = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}latepoint_bookings WHERE order_item_id = %d AND status <> %s",
            (int) $booking->order_item_id,
            LATEPOINT_BOOKING_STATUS_CANCELLED
        ));

        if ($used >= $cap) {
            $errors['yumefit_shared_pool'] = sprintf(
                /* translators: 1: used count, 2: cap */
                __('This package is fully used — %1$d of %2$d sessions are already booked.', 'latepoint-yumefit-rules'),
                $used,
                $cap
            );
        }
    }

    return $errors;
}


/* -------------------------------------------------------------------------
 * Püsiklient (loyal customer) automatic discount.
 *
 * Auto-applies a percentage coupon to the cart for püsiklient customers, so the
 * discount shows as a normal coupon line without the customer entering a code.
 * The percentage is configured on the coupon (Coupons admin); the coupon CODE is
 * stored in option yumefit_pusiklient_coupon_code. Also strips the code if a
 * non-püsiklient customer somehow enters it.
 *
 * SINGLE SOURCE OF TRUTH: eligibility is read from the "Püsiklient?" customer
 * custom field (a checkbox; LatePoint stores its value as 'on' in customer_meta
 * under the field's internal id). The field id is stored in option
 * yumefit_pusiklient_field_id (set by scripts/setup_pusiklient_field.php). Toggling
 * the checkbox in the customer admin therefore directly controls the discount.
 * Falls back to the legacy is_pusiklient meta only while the field id is unset.
 * ---------------------------------------------------------------------- */
function yumefit_is_pusiklient($customer_id): bool {
    if (!$customer_id || !class_exists('OsMetaHelper')) {
        return false;
    }

    $field_id = trim((string) get_option('yumefit_pusiklient_field_id', ''));
    if ($field_id !== '') {
        return OsMetaHelper::get_customer_meta_by_key($field_id, $customer_id, '') === 'on';
    }

    // Fallback until the custom field is wired up by the setup script.
    return OsMetaHelper::get_customer_meta_by_key('is_pusiklient', $customer_id, '') === 'yes';
}

add_filter('latepoint_cart_get_coupon_code', 'yumefit_pusiklient_auto_coupon', 10, 2);
function yumefit_pusiklient_auto_coupon($code, $cart) {
    $ourCode = strtoupper(trim((string) get_option('yumefit_pusiklient_coupon_code', '')));
    if ($ourCode === '') {
        return $code;
    }

    $customer_id = 0;
    if (!empty($cart->order_forced_customer_id)) {
        $customer_id = (int) $cart->order_forced_customer_id;
    } elseif (class_exists('OsAuthHelper')) {
        $customer_id = (int) OsAuthHelper::get_logged_in_customer_id();
    }

    $is_pusiklient = yumefit_is_pusiklient($customer_id);

    if ($is_pusiklient && empty($code)) {
        return $ourCode; // auto-apply for loyal customers (only if no other coupon already entered)
    }
    if (!$is_pusiklient && strtoupper(trim((string) $code)) === $ourCode) {
        return ''; // a non-püsiklient must not benefit from the loyal-customer code
    }
    return $code;
}


/* =========================================================================
 * GIFT CARDS — buy a package as a gift inside the native LatePoint flow.
 *
 * A package (bundle) purchase already skips agent/location/datepicker in core
 * (OsStepsHelper::should_step_be_skipped → is_bundle), so the booking widget
 * sells a package with no time slot. We bolt a gift layer onto that:
 *  - a "See on kingitus" toggle on the customer step (shown only when the cart
 *    is a package purchase);
 *  - the flag rides along in the order intent's payment_data JSON so it
 *    survives the async EveryPay callback into the order's initial_payment_data;
 *  - on a paid gift order we mint a single-use 100%-off coupon scoped to that
 *    bundle (the voucher), show it on the confirmation screen + email it to the
 *    BUYER, flag the order as a gift, and the validity rule then BLOCKS the buyer
 *    from redeeming their own copy — they hand the code to whoever they gift it
 *    to, who redeems it (→ their own €0 bundle order).
 *
 * Voucher = the native coupon primitive; payment = native EveryPay; no new
 * page, table, or dependency. See [[giftcard-plugin]] (the retired standalone).
 * ====================================================================== */

const YUMEFIT_GIFT_VALID_MONTHS = 12;

// True when the current cart is BUYING a package (a bundle line item), as opposed
// to a plain service booking or scheduling an already-owned bundle.
function yumefit_cart_has_bundle_purchase(): bool {
    if (!class_exists('OsCartsHelper')) {
        return false;
    }
    $cart = OsCartsHelper::get_or_create_cart();
    foreach ($cart->get_items() as $item) {
        if (method_exists($item, 'is_bundle') && $item->is_bundle()) {
            return true;
        }
    }
    return false;
}

// 1) Render the gift fields on the customer step (package purchases only).
add_action('latepoint_after_step_content', 'yumefit_gift_fields', 10, 1);
function yumefit_gift_fields($step_code): void {
    if ($step_code !== 'customer' || !yumefit_cart_has_bundle_purchase()) {
        return;
    }
    ?>
    <div class="yumefit-gift-w" style="margin-top:18px;border-top:1px solid #eee;padding-top:14px">
        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:600;cursor:pointer">
            <input type="checkbox" name="gift_enabled" value="1">
            <span><?php esc_html_e('See on kingitus — soovin koodi, mille saan kinkida', 'latepoint-yumefit-rules'); ?></span>
        </label>
        <p style="color:#6b6b6b;font-size:13px;margin:8px 0 0">
            <?php esc_html_e('Ühekordne kood kuvatakse pärast makset ja saadetakse sulle e-postiga.', 'latepoint-yumefit-rules'); ?>
        </p>
    </div>
    <?php
}

// 2) Stash submitted gift data onto the order intent (rides in payment_data JSON →
//    copied to order.initial_payment_data on convert, surviving the EveryPay callback).
add_filter('latepoint_before_order_intent_save_from_cart', 'yumefit_gift_stash_on_intent', 10, 1);
function yumefit_gift_stash_on_intent($order_intent) {
    if (empty($_POST['gift_enabled'])) {
        return $order_intent;
    }
    $data = json_decode((string) ($order_intent->payment_data ?? ''), true) ?: [];
    $data['gift_enabled']       = '1';
    $order_intent->payment_data = wp_json_encode($data);
    return $order_intent;
}

// 3) On a paid gift order: mint the voucher, email it, flag the order as a gift.
add_action('latepoint_order_created', 'yumefit_gift_on_order_created', 20, 1);
function yumefit_gift_on_order_created($order): void {
    if (empty($order->id) || !class_exists('OsMetaHelper') || !class_exists('OsCouponModel')) {
        return;
    }
    $pd = json_decode((string) ($order->initial_payment_data ?? ''), true) ?: [];
    if (empty($pd['gift_enabled'])) {
        return;
    }
    if (OsMetaHelper::get_order_meta_by_key('gift_voucher_code', $order->id, '')) {
        return; // already issued (idempotent)
    }

    $bundles = $order->get_bundles_from_order_items();
    if (!$bundles) {
        return;
    }
    $bundle = reset($bundles);
    if (empty($bundle->id)) {
        return;
    }

    $code = yumefit_gift_make_coupon((int) $bundle->id, (string) $bundle->name);
    if (!$code) {
        return;
    }

    OsMetaHelper::save_order_meta_by_key('gift_is_gift', '1', $order->id);
    OsMetaHelper::save_order_meta_by_key('gift_voucher_code', $code, $order->id);

    yumefit_gift_send_email($order, (string) $bundle->name, $code);
}

// Show the voucher code on the confirmation screen (gift orders only).
add_action('latepoint_step_confirmation_head_info_after', 'yumefit_gift_show_on_confirmation', 10, 1);
function yumefit_gift_show_on_confirmation($order): void {
    if (empty($order->id) || !class_exists('OsMetaHelper')) {
        return;
    }
    $code = OsMetaHelper::get_order_meta_by_key('gift_voucher_code', (int) $order->id, '');
    if (!$code) {
        return;
    }
    $valid_until = date_i18n(get_option('date_format'), strtotime('+' . YUMEFIT_GIFT_VALID_MONTHS . ' months'));
    echo '<div class="yumefit-gift-code" style="margin:16px 0;padding:16px;border:2px dashed #1c1f23;border-radius:10px;text-align:center">'
        . '<div style="font-weight:600;margin-bottom:6px">' . esc_html__('Sinu kinkekaardi kood', 'latepoint-yumefit-rules') . '</div>'
        . '<div style="font-size:22px;font-weight:700;letter-spacing:1px">' . esc_html($code) . '</div>'
        . '<div style="color:#6b6b6b;font-size:13px;margin-top:6px">'
        . esc_html(sprintf(__('Kehtib kuni %s. Saatsime koodi ka sinu e-postile.', 'latepoint-yumefit-rules'), $valid_until))
        . '</div></div>';
}

// 4) Lock the BUYER's own copy: a gift-flagged bundle order can't be scheduled by
//    the purchaser — only whoever receives the gift code can, via their own order.
add_filter('latepoint_check_steps_for_errors', 'yumefit_gift_block_buyer_redemption', 30, 3);
function yumefit_gift_block_buyer_redemption(array $errors, array $steps, array $steps_rules): array {
    if (!class_exists('OsStepsHelper') || !class_exists('OsOrderItemModel') || !class_exists('OsMetaHelper')) {
        return $errors;
    }
    $booking = OsStepsHelper::$booking_object ?? null;
    if (empty($booking) || empty($booking->order_item_id) || !is_numeric($booking->order_item_id)) {
        return $errors;
    }
    $order_item = new OsOrderItemModel((int) $booking->order_item_id);
    if (empty($order_item->order_id)) {
        return $errors;
    }
    if (OsMetaHelper::get_order_meta_by_key('gift_is_gift', (int) $order_item->order_id, '') === '1') {
        $errors['yumefit_gift_locked'] = __('This package was purchased as a gift — the voucher code must be used to book it.', 'latepoint-yumefit-rules');
    }
    return $errors;
}

// Mint a single-use, 100%-off coupon scoped to one bundle = the gift voucher.
function yumefit_gift_make_coupon(int $bundle_id, string $bundle_name): string {
    $coupon                 = new OsCouponModel();
    $coupon->code           = yumefit_gift_unique_code();
    $coupon->name           = trim(sprintf(__('Kinkekaart: %s', 'latepoint-yumefit-rules'), $bundle_name));
    $coupon->discount_type  = 'percent';
    $coupon->discount_value = '100';
    $coupon->rules          = wp_json_encode(['limit_total' => 1, 'bundle_ids' => (string) $bundle_id]);
    $coupon->status         = defined('LATEPOINT_COUPON_STATUS_ACTIVE') ? LATEPOINT_COUPON_STATUS_ACTIVE : 'active';
    $coupon->active_from    = date('Y-m-d');
    $coupon->active_to      = date('Y-m-d', strtotime('+' . YUMEFIT_GIFT_VALID_MONTHS . ' months'));

    return $coupon->save() ? $coupon->code : '';
}

function yumefit_gift_unique_code(): string {
    global $wpdb;
    $table = defined('LATEPOINT_TABLE_COUPONS') ? LATEPOINT_TABLE_COUPONS : $wpdb->prefix . 'latepoint_coupons';
    do {
        $code   = 'GIFT-' . strtoupper(wp_generate_password(8, false, false));
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE code = %s", $code));
    } while ($exists);
    return $code;
}

// Email the voucher code to the BUYER (the purchaser hands it over themselves).
// Estonian, names the gifted package + validity.
function yumefit_gift_send_email($order, string $bundle_name, string $code): void {
    if (empty($order->customer_id) || !class_exists('OsCustomerModel')) {
        return;
    }
    $customer    = new OsCustomerModel((int) $order->customer_id);
    $buyer_email = (string) ($customer->email ?? '');
    if (!is_email($buyer_email)) {
        return;
    }

    $valid_until = date_i18n(get_option('date_format'), strtotime('+' . YUMEFIT_GIFT_VALID_MONTHS . ' months'));
    $site        = get_bloginfo('name');
    $body = __('Aitäh ostu eest! Kinkekaardi kood on valmis.', 'latepoint-yumefit-rules') . "\n\n"
        . sprintf(__('Kinkekaart: %s', 'latepoint-yumefit-rules'), $bundle_name) . "\n"
        . sprintf(__('Kood: %s', 'latepoint-yumefit-rules'), $code) . "\n"
        . sprintf(__('Kehtib kuni: %s', 'latepoint-yumefit-rules'), $valid_until) . "\n\n"
        . __('Anna kood kingisaajale. Broneerimisel valitakse sama pakett ja sisestatakse kood — paketi hind kaetakse täies ulatuses.', 'latepoint-yumefit-rules');

    wp_mail($buyer_email, sprintf(__('Kinkekaardi kood — %s', 'latepoint-yumefit-rules'), $site), $body);
}

/* ===== Jooga rühmatreening: owner-managed class timetable =====
 * The group class (service 7, taught by Marleen, agent 3) runs only at the
 * date+times listed in option `yumefit_jooga_slots` — edited by the owner in
 * WP admin: Settings → "Jooga graafik", one class per line ("07.07.2026 19:00").
 *
 * Saving the page rebuilds the (agent, service) work-period chain: weekly
 * all-off rows + one custom-date row per class (that chain is the most specific,
 * so classes work even on days Marleen has no general schedule). The slot filter
 * below is the guard for LatePoint's precedence quirk — an agent-level custom
 * day OUTRANKS the weekly service off-rows and would re-open the class on any
 * date a custom day is added — and covers both slot display and submit
 * validation (both go through get_resources_grouped_by_day).
 *
 * Class length comes from the service settings. Other services are NOT blocked
 * around a class automatically — a booked class blocks the hour natively, and a
 * hard reservation is done by capping Marleen's day in LatePoint (as done for
 * the July 2026 Tuesdays). */

const YUMEFIT_JOOGA_SERVICE = 7;
const YUMEFIT_JOOGA_AGENT = 3;

/**
 * Parses the class list, one "DD.MM.YYYY HH:MM" (or "YYYY-MM-DD HH:MM") per
 * line; empty and #-comment lines ignored.
 *
 * @return array{
 *     0: array<string, array<int, int>>,
 *     1: array<int, string>
 * } [date => start minutes list, invalid lines]
 */
function yumefit_jooga_parse_slots(string $raw): array {
    $slots = [];
    $bad = [];
    foreach (preg_split('/\R+/', trim($raw)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2})[:.](\d{2})$/', $line, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2})[:.](\d{2})$/', $line, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            $bad[] = $line;
            continue;
        }
        if (!checkdate($month, $day, $year) || (int) $m[4] > 23 || (int) $m[5] > 59) {
            $bad[] = $line;
            continue;
        }
        $slots[sprintf('%04d-%02d-%02d', $year, $month, $day)][] = ((int) $m[4]) * 60 + (int) $m[5];
    }
    return [$slots, $bad];
}

/** Rebuilds the class's work-period chain from a parsed slot list. Idempotent. */
function yumefit_jooga_rebuild_work_periods(array $slots): void {
    global $wpdb;
    $table = $wpdb->prefix . 'latepoint_work_periods';
    $duration = (int) ($wpdb->get_var($wpdb->prepare("SELECT duration FROM {$wpdb->prefix}latepoint_services WHERE id = %d", YUMEFIT_JOOGA_SERVICE)) ?: 60);
    $now = current_time('mysql');

    $insert = function (array $row) use ($wpdb, $table, $now): void {
        $wpdb->insert($table, $row + ['agent_id' => YUMEFIT_JOOGA_AGENT, 'service_id' => YUMEFIT_JOOGA_SERVICE, 'location_id' => 0, 'chain_id' => 'jooga_slots', 'created_at' => $now, 'updated_at' => $now]);
    };

    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE agent_id = %d AND service_id = %d", YUMEFIT_JOOGA_AGENT, YUMEFIT_JOOGA_SERVICE));

    for ($day = 1; $day <= 7; $day++) {
        $insert(['week_day' => $day, 'start_time' => 0, 'end_time' => 0]);
    }
    foreach ($slots as $date => $starts) {
        foreach ($starts as $start) {
            $insert(['week_day' => (int) date('N', strtotime($date)), 'start_time' => $start, 'end_time' => $start + $duration, 'custom_date' => $date]);
        }
    }

    // Belt for servers without the slot filter loaded: pin the class OFF on
    // Marleen's future custom days that have no class (custom day outranks the
    // weekly off rows). Custom days added later are handled by the filter.
    $dates = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT custom_date FROM {$table} WHERE agent_id = %d AND service_id = 0 AND custom_date >= %s",
        YUMEFIT_JOOGA_AGENT, current_time('Y-m-d')
    ));
    foreach (array_diff($dates, array_keys($slots)) as $date) {
        $insert(['week_day' => (int) date('N', strtotime($date)), 'start_time' => 0, 'end_time' => 0, 'custom_date' => $date]);
    }
}

add_filter('latepoint_get_resources_grouped_by_day', 'yumefit_jooga_restrict_slots', 10, 1);
function yumefit_jooga_restrict_slots(array $daily_resources): array {
    static $slots = null;
    if ($slots === null) {
        [$slots] = yumefit_jooga_parse_slots((string) get_option('yumefit_jooga_slots', ''));
    }
    foreach ($daily_resources as $date => $resources) {
        foreach ($resources as $resource) {
            if ((int) $resource->service_id !== YUMEFIT_JOOGA_SERVICE) {
                continue;
            }
            $allowed = $slots[$date] ?? [];
            $resource->slots = array_values(array_filter(
                $resource->slots,
                fn($slot) => in_array((int) $slot->start_time, $allowed, true)
            ));
        }
    }
    return $daily_resources;
}

add_action('admin_menu', function (): void {
    add_options_page('Jooga graafik', 'Jooga graafik', 'manage_options', 'yumefit-jooga', 'yumefit_jooga_settings_page');
});

add_action('admin_init', function (): void {
    register_setting('yumefit_jooga', 'yumefit_jooga_slots', ['sanitize_callback' => 'yumefit_jooga_save_slots']);
});

/** Sanitize callback: validates the list and rebuilds the work periods on save. */
function yumefit_jooga_save_slots($raw): string {
    $raw = (string) $raw;
    [$slots, $bad] = yumefit_jooga_parse_slots($raw);
    if ($bad) {
        add_settings_error('yumefit_jooga_slots', 'yumefit-jooga-bad-lines', 'Vigased read (õige kuju on nt "07.07.2026 19:00"): ' . esc_html(implode(' | ', $bad)));
    }
    yumefit_jooga_rebuild_work_periods($slots);
    return $raw;
}

function yumefit_jooga_settings_page(): void {
    ?>
    <div class="wrap">
        <h1>Jooga rühmatreeningu ajad</h1>
        <p>
            Üks treening rea kohta, kujul <code>07.07.2026 19:00</code> (kuupäev + algusaeg).
            Treeningu pikkus tuleb teenuse seadetest. Jooga rühmatreening on broneeritav
            <strong>ainult</strong> siin loetletud aegadel.
        </p>
        <p>
            Teised teenused sel kellaajal automaatselt ei sulgu — tund on kaitstud alles siis,
            kui keegi on treeningusse broneerinud, või kui lõpetad Marleeni tööpäeva LatePointis
            enne treeningu algust.
        </p>
        <form method="post" action="options.php">
            <?php settings_fields('yumefit_jooga'); ?>
            <textarea name="yumefit_jooga_slots" rows="12" cols="28" class="code"><?php echo esc_textarea((string) get_option('yumefit_jooga_slots', '')); ?></textarea>
            <?php submit_button('Salvesta'); ?>
        </form>
    </div>
    <?php
}

/* ===== Admin-only payment method: Sularaha =====
 * latepoint_all_payment_methods_for_select feeds only the admin selects (the
 * transaction edit form and the transactions list filter) plus nice-name
 * display of saved transactions. The customer checkout builds its choices
 * from latepoint_get_enabled_payment_times, which is untouched — so this is
 * selectable only when an admin/agent records a payment. */
add_filter('latepoint_all_payment_methods_for_select', function (array $methods): array {
    return $methods + ['sularaha' => 'Sularaha'];
});

/* ===== Customer cabinet: one tile per row =====
 * LatePoint lays the cabinet booking/order/bundle tiles in a 3-column grid
 * (.customer-bookings-tiles / .customer-orders-tiles). Force a single column so
 * the cards stack one per row. !important to beat LatePoint's same-specificity rule. */
add_action('wp_head', function (): void {
    echo '<style id="yumefit-cabinet-one-per-row">.customer-bookings-tiles,.customer-orders-tiles{grid-template-columns:1fr !important}</style>' . "\n";
});
