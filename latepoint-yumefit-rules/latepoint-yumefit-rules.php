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
 * Version:     1.3.2
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
    $months = 0;
    if ($bundle_id && method_exists($bundle, 'get_meta_by_key')) {
        $meta_months = $bundle->get_meta_by_key('valid_for_months');
        if (is_numeric($meta_months)) {
            $months = (int) $meta_months;
        }
    }
    if ($months <= 0) {
        $months = (int) get_option('yumefit_bundle_validity_months', 2);
    }

    if ($months > 0 && !empty($booking->start_date)) {
        $purchased_at = '';
        if (!empty($order_item->order_id) && class_exists('OsOrderModel')) {
            $order = new OsOrderModel((int) $order_item->order_id);
            $purchased_at = $order->created_at ?? '';
        }
        if (empty($purchased_at)) {
            $purchased_at = $order_item->created_at ?? '';
        }
        if (!empty($purchased_at)) {
            try {
                $expiry = (new DateTime(substr($purchased_at, 0, 10)))->modify('+' . $months . ' months');
                $start  = new DateTime(substr((string) $booking->start_date, 0, 10));
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
