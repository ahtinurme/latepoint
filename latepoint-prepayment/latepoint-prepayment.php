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

/** Whether the "pay with package" option is enabled (admin toggle, default on). */
function latepoint_prepayment_is_enabled(): bool {
	return ! class_exists( 'OsSettingsHelper' )
		|| OsSettingsHelper::get_settings_value( 'enable_prepayment_packages', 'on' ) === 'on';
}

// Admin toggle under LatePoint -> Settings -> General.
add_action( 'latepoint_settings_general_other_after', function () {
	if ( ! class_exists( 'OsFormHelper' ) ) {
		return;
	}
	echo OsFormHelper::toggler_field(
		'settings[enable_prepayment_packages]',
		__( 'Enable "pay with package"', 'latepoint-prepayment' ),
		latepoint_prepayment_is_enabled(),
		false,
		false,
		[ 'sub_label' => __( 'Let customers pay for a booking with one of their purchased packages.', 'latepoint-prepayment' ) ]
	);
} );

// JS/CSS that move the package option into the payment-method grid (next to the processors).
add_action( 'wp_enqueue_scripts', 'latepoint_prepayment_enqueue_assets' );
add_action( 'admin_enqueue_scripts', 'latepoint_prepayment_enqueue_assets' );
function latepoint_prepayment_enqueue_assets(): void {
	if ( ! latepoint_prepayment_is_enabled() ) {
		return;
	}
	wp_enqueue_script( 'latepoint-prepayment-front', plugins_url( 'public/javascripts/prepayment-front.js', __FILE__ ), [ 'jquery' ], '1.0.0', true );
	wp_enqueue_style( 'latepoint-prepayment-front', plugins_url( 'public/stylesheets/prepayment-front.css', __FILE__ ), [], '1.0.0' );
}

// Render the "Pay with your package" option only on the payment-method/processor selection
// steps (where you choose how to pay) - not on a processor's own pay screen, so it doesn't
// linger after Stebby/EveryPay is already selected. The JS then moves it into the grid.
add_action( 'latepoint_before_step_content', 'latepoint_prepayment_render_on_selection_step' );

// $current_step_code is passed by latepoint_before_step_content; pull the cart from steps.
function latepoint_prepayment_render_on_selection_step( $current_step_code ): void {
	if ( ! latepoint_prepayment_is_enabled() ) {
		return;
	}
	if ( ! in_array( $current_step_code, [ 'payment__methods', 'payment__processors' ], true ) || ! class_exists( 'OsStepsHelper' ) ) {
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

	// Render each package as a LatePoint option tile. The container is hidden; the
	// front-end script moves the tiles into the payment-method grid so they sit next
	// to EveryPay/Stebby. data-stebby-context-free os_trigger_booking starts the
	// native bundle-scheduling flow on click (no charge).
	echo '<div class="lp-prepayment-tiles" style="display:none;">';

	foreach ( $packages as $package ) {
		$attrs = $base_attrs + [ 'data-order-item-id' => $package['order_item_id'] ];
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
