<?php
/**
 * Plugin Name: LatePoint Addon - Prepayment (Pay with Package)
 * Plugin URI:  https://yumefit.ee/
 * Description: Adds a "Pay with package" payment method. A logged-in customer who owns a
 *              package (bundle) covering the booked service can pick it at the payment step
 *              and redeem one session - re-driving the booking through LatePoint's native
 *              bundle-scheduling flow (no charge). Registered as a real payment processor, so
 *              it is enabled/disabled under Settings -> Payments and only appears when the
 *              customer actually has a usable package for the service.
 * Version:     2.0.1
 * Author:      Yumefit
 * Text Domain: latepoint-prepayment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'LATEPOINT_PREPAYMENT_PROCESSOR', 'prepayment' );

// Register as an installed LatePoint addon so it shows in the addons list.
add_filter( 'latepoint_installed_addons', function ( $addons ) {
	$addons[] = [ 'name' => 'latepoint-addon-prepayment', 'db_version' => '2.0.0', 'version' => '2.0.0' ];
	return $addons;
} );

/*
 * --------------------------------------------------------------------
 * Register "Pay with package" as a payment processor
 * --------------------------------------------------------------------
 */

// Appears under Settings -> Payments with its own enable/disable toggle.
add_filter( 'latepoint_payment_processors', function ( $processors ) {
	$processors[ LATEPOINT_PREPAYMENT_PROCESSOR ] = [
		'code'       => LATEPOINT_PREPAYMENT_PROCESSOR,
		'name'       => __( 'Pay with package', 'latepoint-prepayment' ),
		'front_name' => __( 'Pay with package', 'latepoint-prepayment' ),
		'image_url'  => plugins_url( 'public/images/prepayment.svg', __FILE__ ),
	];
	return $processors;
} );

function latepoint_prepayment_method_info(): array {
	return [
		'name'      => __( 'Pay with package', 'latepoint-prepayment' ),
		'label'     => __( 'Pay with package', 'latepoint-prepayment' ),
		'image_url' => plugins_url( 'public/images/prepayment.svg', __FILE__ ),
	];
}

// Make it a "pay now" method+processor. Listed unconditionally for settings/config...
add_filter( 'latepoint_get_all_payment_times', function ( $payment_times ) {
	$payment_times[ LATEPOINT_PAYMENT_TIME_NOW ][ LATEPOINT_PREPAYMENT_PROCESSOR ][ LATEPOINT_PREPAYMENT_PROCESSOR ] = latepoint_prepayment_method_info();
	return $payment_times;
} );

// ...but only offered in the booking flow when enabled AND the customer actually has a
// usable package for the booked service (so the tile is hidden otherwise).
add_filter( 'latepoint_get_enabled_payment_times', function ( $payment_times ) {
	if ( ! class_exists( 'OsPaymentsHelper' ) || ! OsPaymentsHelper::is_payment_processor_enabled( LATEPOINT_PREPAYMENT_PROCESSOR ) ) {
		return $payment_times;
	}
	if ( ! latepoint_prepayment_cart_has_available_package() ) {
		return $payment_times;
	}
	$payment_times[ LATEPOINT_PAYMENT_TIME_NOW ][ LATEPOINT_PREPAYMENT_PROCESSOR ][ LATEPOINT_PREPAYMENT_PROCESSOR ] = latepoint_prepayment_method_info();
	return $payment_times;
} );

// Render the package choices on the pay step when "Pay with package" is the selected method.
add_action( 'latepoint_step_payment__pay_content', 'latepoint_prepayment_render_pay_content', 10 );
function latepoint_prepayment_render_pay_content( $cart ): void {
	if ( ! ( $cart instanceof OsCartModel ) || ! class_exists( 'OsPaymentsHelper' ) ) {
		return;
	}
	if ( ! OsPaymentsHelper::should_processor_handle_payment_for_cart( LATEPOINT_PREPAYMENT_PROCESSOR, $cart ) ) {
		return;
	}
	echo '<div class="lp-payment-method-content lp-prepayment-content" data-payment-method="' . esc_attr( LATEPOINT_PREPAYMENT_PROCESSOR ) . '" style="display:none;">';
	echo '<div class="lp-payment-method-content-i">';
	latepoint_prepayment_render_tiles( $cart );
	echo '</div></div>';
}

// Safety net: redeeming happens client-side (clicking a package re-drives the booking). If a
// "pay with package" order is ever submitted without a package being chosen, block it rather
// than create an unpaid order.
add_filter( 'latepoint_process_payment_for_order_intent', function ( $result, $order_intent ) {
	if ( ! class_exists( 'OsPaymentsHelper' ) || ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( LATEPOINT_PREPAYMENT_PROCESSOR, $order_intent ) ) {
		return $result;
	}
	$message = __( 'Please choose a package to redeem.', 'latepoint-prepayment' );
	$order_intent->add_error( 'send_to_step', $message, 'payment' );
	$result['status']  = LATEPOINT_STATUS_ERROR;
	$result['message'] = $message;
	return $result;
}, 10, 2 );

// Wallet icon for the package option tiles.
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'latepoint-prepayment-front', plugins_url( 'public/stylesheets/prepayment-front.css', __FILE__ ), [], '2.0.0' );
} );

/*
 * --------------------------------------------------------------------
 * Package lookup + tiles
 * --------------------------------------------------------------------
 */

/** Whether the current booking's customer has a usable package for the booked service. */
function latepoint_prepayment_cart_has_available_package(): bool {
	if ( ! class_exists( 'OsStepsHelper' ) || ! isset( OsStepsHelper::$cart_object ) ) {
		return false;
	}
	$cart = OsStepsHelper::$cart_object;
	if ( ! ( $cart instanceof OsCartModel ) ) {
		return false;
	}
	// You can't pay for a package (or gift card) purchase WITH a package — if the cart
	// is buying a bundle, never offer "Pay with package".
	foreach ( $cart->get_items() as $item ) {
		if ( method_exists( $item, 'is_bundle' ) && $item->is_bundle() ) {
			return false;
		}
	}
	$booking = latepoint_prepayment_active_booking( $cart );
	if ( ! $booking || empty( $booking->service_id ) ) {
		return false;
	}
	$customer_id = (int) OsStepsHelper::get_customer_object_id();
	if ( ! $customer_id ) {
		return false;
	}
	return ! empty( latepoint_prepayment_available_packages( $customer_id, (int) $booking->service_id ) );
}

/** Renders the customer's package tiles; clicking one re-drives the booking as a bundle redemption. */
function latepoint_prepayment_render_tiles( OsCartModel $cart ): void {
	$booking = latepoint_prepayment_active_booking( $cart );
	if ( ! $booking || empty( $booking->service_id ) ) {
		return;
	}
	$customer_id = class_exists( 'OsStepsHelper' ) ? (int) OsStepsHelper::get_customer_object_id() : 0;
	$packages    = $customer_id ? latepoint_prepayment_available_packages( $customer_id, (int) $booking->service_id ) : [];
	if ( ! $packages ) {
		echo '<p style="color:#5a6068;">' . esc_html__( 'You have no usable package for this service.', 'latepoint-prepayment' ) . '</p>';
		return;
	}

	$base_attrs = [ 'data-selected-service' => (int) $booking->service_id ];
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

	echo '<div class="lp-options lp-options-grid lp-options-grid-three lp-prepayment-tiles">';
	foreach ( $packages as $package ) {
		$attrs     = $base_attrs + [ 'data-order-item-id' => $package['order_item_id'] ];
		$attr_html = '';
		foreach ( $attrs as $key => $value ) {
			$attr_html .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		$label = $package['name'] . ' · ' . sprintf( '%d/%d', $package['remaining'], $package['total'] );
		echo '<div tabindex="0" class="lp-option os_trigger_booking lp-prepayment-tile"' . $attr_html . '>';
		echo '<div class="lp-option-image-w"><div class="lp-option-image"></div></div>';
		echo '<div class="lp-option-label">' . esc_html( $label ) . '</div>';
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
