<?php
/**
 * Plugin Name: LatePoint Yumefit Rules
 * Description: Site-specific LatePoint customizations for Yumefit. Enforces a shared
 *              redemption pool for "shared pool" bundles (N sessions usable across all
 *              included services combined, instead of LatePoint's default N-per-service).
 * Version:     1.0.0
 * Author:      Yumefit
 * Text Domain: latepoint
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hard-cap total redemptions for shared-pool bundles.
 *
 * LatePoint counts bundle usage per (order_item, service); this makes the cap
 * shared across ALL services of the bundle. Bundles are flagged in the
 * `yumefit_shared_pool_bundles` option ([bundle_id => total]) by the setup script.
 *
 * A non-cancelled booking counts as used. Cancellation timing is governed by the
 * global LatePoint cancellation setting, so late cancels and no-shows keep counting.
 *
 * Fail-open: only ever ADDS an error when it positively identifies a full shared
 * pool — it never blocks ordinary bookings.
 */
add_filter('latepoint_check_steps_for_errors', 'yumefit_enforce_shared_pool_cap', 20, 3);

function yumefit_enforce_shared_pool_cap(array $errors, array $steps, array $steps_rules): array {
    if (!class_exists('OsStepsHelper') || !class_exists('OsOrderItemModel')) {
        return $errors;
    }

    $booking = OsStepsHelper::$booking_object ?? null;
    if (empty($booking) || empty($booking->order_item_id) || !is_numeric($booking->order_item_id)) {
        return $errors; // no real (saved) bundle order item in context
    }

    $shared = get_option('yumefit_shared_pool_bundles', []);
    if (empty($shared) || !is_array($shared)) {
        return $errors;
    }

    $order_item = new OsOrderItemModel((int) $booking->order_item_id);
    if (!method_exists($order_item, 'is_bundle') || !$order_item->is_bundle()) {
        return $errors;
    }

    $bundle = $order_item->build_original_object_from_item_data();
    $bundle_id = (int) ($bundle->id ?? 0);
    if (!$bundle_id || empty($shared[$bundle_id])) {
        return $errors; // not a shared-pool bundle
    }

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

    return $errors;
}
