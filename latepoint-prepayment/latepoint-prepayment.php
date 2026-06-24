<?php
/**
 * Plugin Name: LatePoint Addon - Prepayment (Pay with Package)
 * Plugin URI:  https://yumefit.ee/
 * Description: Lets a logged-in customer pay for an in-progress booking with one of
 *              their already-purchased packages (bundles). At the payment step it lists
 *              the customer's available packages that cover the selected service; picking
 *              one re-drives the booking through LatePoint's native bundle-scheduling flow
 *              (no charge), exactly like scheduling from the customer cabinet - but started
 *              from the booking form instead of the cabinet.
 * Version:     1.0.0
 * Author:      Yumefit
 * Text Domain: latepoint-prepayment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Register as an installed LatePoint addon so it shows in the addons list.
add_filter( 'latepoint_installed_addons', function ( $addons ) {
	$addons[] = [ 'name' => 'latepoint-addon-prepayment', 'db_version' => '1.0.0', 'version' => '1.0.0' ];
	return $addons;
} );

// Render the "Pay with your package" panel on the booking payment step. We render it on
// the payment-method selection step (where the customer/admin picks how to pay) and also
// on the final pay step as a fallback for when the method step is auto-skipped (single
// processor). Priority 5 so it appears above the online payment processors.
add_action( 'latepoint_step_payment__pay_content', 'latepoint_prepayment_render_panel', 5 );
add_action( 'latepoint_before_step_content', 'latepoint_prepayment_render_on_selection_step' );

// $current_step_code is passed by latepoint_before_step_content; pull the cart from steps.
function latepoint_prepayment_render_on_selection_step( $current_step_code ): void {
	if ( $current_step_code !== 'payment__methods' || ! class_exists( 'OsStepsHelper' ) ) {
		return;
	}
	latepoint_prepayment_render_panel( OsStepsHelper::$cart_object ?? null );
}

function latepoint_prepayment_render_panel( $cart ): void {
	if ( ! class_exists( 'OsAuthHelper' ) || ! ( $cart instanceof OsCartModel ) ) {
		return;
	}

	$booking = latepoint_prepayment_active_booking( $cart );
	if ( ! $booking || empty( $booking->service_id ) ) {
		return;
	}

	// Resolve the customer this order is for. This is the logged-in customer on the public
	// form, or the customer selected on the order in the admin/backend flow.
	$customer_id = class_exists( 'OsStepsHelper' ) ? (int) OsStepsHelper::get_customer_object_id() : 0;
	if ( ! $customer_id ) {
		// No identified customer yet. On the public form a guest can log in to use a package;
		// in the backend there's no one to prompt, so render nothing.
		if ( ! OsAuthHelper::get_current_user()->has_backend_access() && ! OsAuthHelper::is_customer_logged_in() ) {
			$login_url = class_exists( 'OsSettingsHelper' ) ? OsSettingsHelper::get_customer_login_url() : '';
			if ( empty( $login_url ) ) {
				return;
			}
			echo '<div class="lp-prepayment-panel" style="border:1px solid #e3e6ec;border-radius:10px;padding:16px;margin-bottom:16px;">';
			echo '<div style="font-weight:600;margin-bottom:6px;">' . esc_html__( 'Have a package?', 'latepoint-prepayment' ) . '</div>';
			echo '<p style="margin:0 0 12px;color:#5a6068;">' . esc_html__( 'Log in to pay with one of your purchased packages.', 'latepoint-prepayment' ) . '</p>';
			echo '<a href="' . esc_url( $login_url ) . '" class="latepoint-btn latepoint-btn-primary">' . esc_html__( 'Log in', 'latepoint-prepayment' ) . '</a>';
			echo '</div>';
		}
		return;
	}

	$packages = latepoint_prepayment_available_packages( $customer_id, (int) $booking->service_id );
	if ( ! $packages ) {
		return;
	}

	$base_attrs = [
		'data-selected-service' => (int) $booking->service_id,
	];
	if ( ! empty( $booking->agent_id ) && is_numeric( $booking->agent_id ) ) {
		$base_attrs['data-selected-agent'] = (int) $booking->agent_id;
	}
	if ( ! empty( $booking->location_id ) && is_numeric( $booking->location_id ) ) {
		$base_attrs['data-selected-location'] = (int) $booking->location_id;
	}
	if ( ! empty( $booking->start_date ) ) {
		$base_attrs['data-selected-start-date'] = substr( (string) $booking->start_date, 0, 10 );
	}
	if ( isset( $booking->start_time ) && $booking->start_time !== '' && is_numeric( $booking->start_time ) ) {
		$base_attrs['data-selected-start-time'] = (int) $booking->start_time;
	}

	echo '<div class="lp-prepayment-panel" style="border:1px solid #e3e6ec;border-radius:10px;padding:16px;margin-bottom:16px;">';
	echo '<div style="font-weight:600;margin-bottom:10px;">' . esc_html__( 'Pay with your package', 'latepoint-prepayment' ) . '</div>';

	foreach ( $packages as $package ) {
		$attrs = $base_attrs + [ 'data-order-item-id' => $package['order_item_id'] ];
		$attr_html = '';
		foreach ( $attrs as $key => $value ) {
			$attr_html .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		// translators: %1$d remaining sessions, %2$d total sessions
		$remaining_label = sprintf( esc_html__( '%1$d of %2$d sessions left', 'latepoint-prepayment' ), $package['remaining'], $package['total'] );
		echo '<div class="os_trigger_booking lp-prepayment-option" role="button" tabindex="0"' . $attr_html
			. ' style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;border:1px solid #e3e6ec;border-radius:8px;margin-bottom:8px;cursor:pointer;">';
		echo '<span style="font-weight:600;">' . esc_html( $package['name'] ) . '</span>';
		echo '<span style="color:#5a6068;white-space:nowrap;">' . $remaining_label . '</span>';
		echo '</div>';
	}

	echo '</div>';
}

/** First booking in the cart (the appointment being paid for), or false. */
function latepoint_prepayment_active_booking( OsCartModel $cart ) {
	foreach ( $cart->get_items() as $item ) {
		if ( $item->is_booking() ) {
			$booking = $item->build_original_object_from_item_data();
			if ( $booking instanceof OsBookingModel ) {
				return $booking;
			}
		}
	}
	return false;
}

/**
 * Bundle order-items owned by the customer that cover $service_id and still have
 * sessions left (and are not expired). Mirrors the counting used by
 * latepoint-yumefit-rules so what's offered matches what's enforced.
 *
 * @return array<int, array{order_item_id:int, name:string, remaining:int, total:int}>
 */
function latepoint_prepayment_available_packages( int $customer_id, int $service_id ): array {
	if ( ! $customer_id || ! $service_id || ! class_exists( 'OsBundleModel' ) ) {
		return [];
	}

	global $wpdb;
	$prefix    = $wpdb->prefix;
	$cancelled = defined( 'LATEPOINT_BOOKING_STATUS_CANCELLED' ) ? LATEPOINT_BOOKING_STATUS_CANCELLED : 'cancelled';

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT oi.id oi_id, oi.item_data, o.id order_id,
				(SELECT COUNT(*) FROM {$prefix}latepoint_bookings b WHERE b.order_item_id = oi.id AND b.status <> %s) used
		 FROM {$prefix}latepoint_orders o
		 JOIN {$prefix}latepoint_order_items oi ON oi.order_id = o.id AND oi.variant = 'bundle'
		 WHERE o.customer_id = %d
		 ORDER BY o.created_at DESC",
		$cancelled, $customer_id
	) );
	if ( ! $rows ) {
		return [];
	}

	$shared    = get_option( 'yumefit_shared_pool_bundles', [] );
	$today     = new DateTime( 'today' );
	$available = [];

	foreach ( $rows as $row ) {
		$item      = json_decode( $row->item_data, true );
		$bundle_id = (int) ( $item['bundle_id'] ?? 0 );
		if ( ! $bundle_id ) {
			continue;
		}

		$bundle = new OsBundleModel( $bundle_id );
		if ( ! $bundle->has_service( $service_id ) ) {
			continue;
		}

		if ( is_array( $shared ) && ! empty( $shared[ $bundle_id ] ) ) {
			// Shared pool: total is shared across all the bundle's services.
			$total = (int) $shared[ $bundle_id ];
			$used  = (int) $row->used;
		} else {
			$total = $bundle->quantity_for_service( $service_id );
			$used  = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}latepoint_bookings WHERE order_item_id = %d AND service_id = %d AND status <> %s",
				(int) $row->oi_id, $service_id, $cancelled
			) );
		}

		$remaining = $total - $used;
		if ( $remaining <= 0 ) {
			continue;
		}

		// Don't offer expired packages (yumefit-rules blocks them at confirmation anyway).
		if ( function_exists( 'yumefit_bundle_expiry_date' ) && class_exists( 'OsOrderModel' ) ) {
			$expiry = yumefit_bundle_expiry_date( new OsOrderModel( (int) $row->order_id ), $bundle );
			if ( $expiry && $expiry < $today ) {
				continue;
			}
		}

		$available[] = [
			'order_item_id' => (int) $row->oi_id,
			'name'          => ! empty( $bundle->name ) ? $bundle->name : 'Bundle #' . $bundle_id,
			'remaining'     => $remaining,
			'total'         => $total,
		];
	}

	return $available;
}
