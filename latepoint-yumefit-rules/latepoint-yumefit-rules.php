<?php
/**
 * Plugin Name: LatePoint Yumefit Rules
 * Description: Site-specific LatePoint customizations for Yumefit. (1) Shared redemption
 *              pool for "shared pool" bundles (N sessions usable across all included
 *              services combined, vs LatePoint's default N-per-service). (2) Package
 *              validity window — bundle sessions can only be booked within N months of
 *              purchase (default 2). (3) Auto-applies a percentage discount coupon
 *              for "püsiklient" (loyal) customers, driven by the "Püsiklient?" customer
 *              custom field as the single source of truth.
 * Version:     1.5.0
 * Author:      Yumefit
 * Text Domain: latepoint
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
        . '<span>' . esc_html__('Package valid until', 'latepoint') . '</span>'
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
// until, paid status.
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
    if (!$rows) {
        return;
    }
    $shared = get_option('yumefit_shared_pool_bundles', []);
    echo '<div class="white-box"><div class="white-box-header"><div class="os-form-sub-header"><h3>' . esc_html__('Packages', 'latepoint') . '</h3></div></div><div class="white-box-section"><table style="width:100%;border-collapse:collapse;">';
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
            . '<td style="padding:6px 8px;white-space:nowrap;color:#6b6b6b;">' . ($expiry ? esc_html__('until', 'latepoint') . ' ' . esc_html($expiry->format('d.m.Y')) : '') . '</td>'
            . '<td style="padding:6px 0 6px 8px;text-align:right;">' . ($paid ? '✓ ' . esc_html__('paid', 'latepoint') : esc_html(OsOrdersHelper::get_nice_order_payment_status_name($r->payment_status))) . '</td>'
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
                        __('This package can only be used until %s.', 'latepoint'),
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
                __('This package is fully used — %1$d of %2$d sessions are already booked.', 'latepoint'),
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
