<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsCouponsHelper {

	public static function calculate_coupon_discount( OsOrderModel $order ) {
		$order->coupon_discount = OsMoneyHelper::pad_to_db_format( $order->recalculate_total() ) - OsMoneyHelper::pad_to_db_format( $order->recalculate_subtotal() );
	}

	public static function get_payment_total_info_with_coupon_html( $html, $booking ) {
		$coupon_applied = ( $booking->coupon_code ) ? 'coupon-is-applied' : '';
		$regular_price  = $booking->get_subtotal();
		$deposit_price  = $booking->deposit_amount_to_charge( [ 'apply_coupons' => false ] );

		$discounted_price_formatted = $booking->formatted_full_price( true, true );
		$regular_price_formatted    = $booking->formatted_full_price( false, true );
		$price_html                 = ( $discounted_price_formatted != $regular_price_formatted ) ? '<span>' . $regular_price_formatted . '</span>' . $discounted_price_formatted : $regular_price_formatted;
		$deposit_price_formatted    = $booking->formatted_deposit_price();
		$price_portion_html         = '';

		if ( $regular_price > 0 ) {
			$price_portion_html .= '<div class="payment-total-price-w"><span>' . __( 'Total booking price: ', 'latepoint-pro-features' ) . '</span><span class="lp-price-value">' . $price_html . '</span></div>';
		}
		if ( $deposit_price > 0 ) {
			$price_portion_html .= '<div class="payment-deposit-price-w"><span>' . __( 'Deposit Amount: ', 'latepoint-pro-features' ) . '</span><span class="lp-price-value">' . $deposit_price_formatted . '</span></div>';
		}

		$payment_portion = ( OsCartsHelper::get_default_payment_portion_type( $booking ) == LATEPOINT_PAYMENT_PORTION_DEPOSIT ) ? ' paying-deposit ' : '';
		$html            = '<div class="payment-total-info ' . $coupon_applied . $payment_portion . '">
                  ' . $price_portion_html . '
                  <div class="coupon-code-trigger-w"><a href="#">' . __( 'Have a coupon code?', 'latepoint-pro-features' ) . '</a></div>
                  <div class="coupon-code-input-w">
                    <input type="text" name="coupon_code" class="coupon-code-input" data-route="' . OsRouterHelper::build_route_name( 'coupons', 'apply' ) . '" placeholder="' . __( 'Enter Code...', 'latepoint-pro-features' ) . '">
                    <div class="coupon-code-input-submit">' . __( 'OK', 'latepoint-pro-features' ) . '</div>
                    <input type="hidden" name="booking[coupon_code]" value="' . $booking->coupon_code . '"/>
                  </div>
                  <div class="applied-coupon-code">
                    <span class="coupon-code-self">' . $booking->coupon_code . '</span>
                    <span class="coupon-code-clear"><i class="latepoint-icon latepoint-icon-common-01"></i></span>
                  </div>
                </div>';

		return $html;
	}

	public static function get_coupon_by_code( $coupon_code ) {
		$coupon = new OsCouponModel();
		$coupon = $coupon->where(
			[
				'code'   => $coupon_code,
				'status' => LATEPOINT_COUPON_STATUS_ACTIVE,
			]
		)->set_limit( 1 )->get_results_as_models();

		return $coupon;
	}

	public static function is_coupon_code_valid( string $coupon_code, OsCartModel $cart ) {
		if ( empty( $coupon_code ) ) {
			return new WP_Error( 'coupon_invalid', __( 'Invalid coupon code.', 'latepoint-pro-features' ) );
		}
		if ( $cart->order_id ) {
			$order       = new OsOrderModel( $cart->order_id );
			$customer_id = $order->customer_id;
		} elseif ( ! empty( $cart->order_forced_customer_id ) ) {
			// cart is created from an order model, customer is either set or new, no need to get it from session
			$customer_id = $cart->order_forced_customer_id;
		} else {
			$customer_id = OsAuthHelper::get_logged_in_customer_id();
		}
		$valid  = true;
		$reason = '';
		$coupon = new OsCouponModel();
		$coupon = $coupon->where(
			[
				'code'   => $coupon_code,
				'status' => LATEPOINT_COUPON_STATUS_ACTIVE,
			]
		)->set_limit( 1 )->get_results_as_models();

		if ( $coupon ) {
			// Active dates, check only for new orders
			if ( empty( $cart->order_id ) ) {
				if ( $coupon->active_from ) {
					try {
						$active_from_utc = OsWpDateTime::os_createFromFormat( 'Y-m-d', $coupon->active_from, new DateTimeZone( 'UTC' ) );
						$now_time_utc    = new OsWpDateTime( 'now', new DateTimeZone( 'UTC' ) );

						if ( $active_from_utc > $now_time_utc ) {
							$valid  = false;
							$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
						}
					} catch ( Exception $e ) {

					}
				}
				if ( $coupon->active_to ) {
					try {
						$active_to_utc = OsWpDateTime::os_createFromFormat( 'Y-m-d', $coupon->active_to, new DateTimeZone( 'UTC' ) );
						$now_time_utc  = new OsWpDateTime( 'now', new DateTimeZone( 'UTC' ) );

						if ( $active_to_utc < $now_time_utc ) {
							$valid  = false;
							$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
						}
					} catch ( Exception $e ) {
					}
				}
			}
			// Customer id
			if ( $coupon->get_rule( 'customer_ids' ) ) {
				$allowed_customer_ids = explode( ',', str_replace( ' ', '', $coupon->get_rule( 'customer_ids' ) ) );
				if ( empty( $customer_id ) || ( ! empty( $allowed_customer_ids ) && ! in_array( $customer_id, $allowed_customer_ids ) ) ) {
					$valid  = false;
					$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
				}
			}

			$cart_items = $cart->get_items();
			foreach ( $cart_items as $cart_item ) {

				$original_object = $cart_item->build_original_object_from_item_data();
				switch ( $cart_item->variant ) {
					case LATEPOINT_ITEM_VARIANT_BOOKING:
						// Agent id
						if ( $coupon->get_rule( 'agent_ids' ) && empty( $agent_valid ) ) {
							$allowed_agent_ids = explode( ',', str_replace( ' ', '', $coupon->get_rule( 'agent_ids' ) ) );
							if ( in_array( $original_object->agent_id, $allowed_agent_ids ) ) {
								$agent_valid = true;
							}
						}
						// Service id
						if ( $coupon->get_rule( 'service_ids' ) && empty( $service_valid ) ) {
							$allowed_service_ids = explode( ',', str_replace( ' ', '', $coupon->get_rule( 'service_ids' ) ) );
							if ( in_array( $original_object->service_id, $allowed_service_ids ) ) {
								$service_valid = true;
							}
						}
						break;
					case LATEPOINT_ITEM_VARIANT_BUNDLE:
						if ( $coupon->get_rule( 'bundle_ids' ) && empty( $bundle_valid ) ) {
							$allowed_bundle_ids = explode( ',', str_replace( ' ', '', $coupon->get_rule( 'bundle_ids' ) ) );
							if ( in_array( $original_object->id, $allowed_bundle_ids ) ) {
								$bundle_valid = true;
							}
						}
						break;
				}
			}
			if ( $coupon->get_rule( 'agent_ids' ) && empty( $agent_valid ) ) {
				$valid  = false;
				$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
			}
			if ( $coupon->get_rule( 'service_ids' ) && empty( $service_valid ) ) {
				$valid  = false;
				$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
			}
			if ( $coupon->get_rule( 'bundle_ids' ) && empty( $bundle_valid ) ) {
				$valid  = false;
				$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
			}

			// Minimum order subtotal
			if ( $coupon->get_rule( 'minimum_order_subtotal' ) ) {
				$cart_subtotal          = $cart->get_subtotal();
				$minimum_order_subtotal = (float) $coupon->get_rule( 'minimum_order_subtotal' );
				if ( $cart_subtotal < $minimum_order_subtotal ) {
					$valid  = false;
					$reason = sprintf(
						/* translators: %s: formatted minimum order subtotal */
						__( 'This coupon requires a minimum order total of %s', 'latepoint-pro-features' ),
						OsMoneyHelper::format_price( $minimum_order_subtotal )
					);
				}
			}

			// limit
			if ( $coupon->get_rule( 'limit_total' ) ) {
				$order = new OsOrderModel();
				if ( $cart->order_id ) {
					$order->where( [ 'id !=' => $cart->order_id ] );
				}
				$total_orders = $order->where( [ 'coupon_code' => $coupon->code ] )->should_not_be_cancelled()->count();
				if ( $total_orders >= $coupon->get_rule( 'limit_total' ) ) {
					$valid  = false;
					$reason = sprintf( __( 'This coupon can only be used %d times', 'latepoint-pro-features' ), $coupon->get_rule( 'limit_total' ) );
				}
			}
			// per customer limit
			if ( $coupon->get_rule( 'limit_per_customer' ) ) {
				if ( empty( $customer_id ) ) {
					$valid  = false;
					$reason = __( 'Customer is missing', 'latepoint-pro-features' );
				} else {
					$order = new OsOrderModel();
					if ( $cart->order_id ) {
						$order->where( [ 'id !=' => $cart->order_id ] );
					}
					$total_orders = $order->where(
						[
							'coupon_code' => $coupon->code,
							'customer_id' => $customer_id,
						]
					)->should_not_be_cancelled()->count();
					if ( $total_orders >= $coupon->get_rule( 'limit_per_customer' ) ) {
						$valid  = false;
						$reason = sprintf( _n( 'This coupon can only be used once per customer.', 'This coupon is limited to %d uses per customer', $coupon->get_rule( 'limit_per_customer' ), 'latepoint-pro-features' ), $coupon->get_rule( 'limit_per_customer' ) );
					}
				}
			}
			// min orders
			if ( $coupon->get_rule( 'orders_more' ) ) {
				if ( empty( $customer_id ) ) {
					$valid  = false;
					$reason = __( 'Customer is missing', 'latepoint-pro-features' );
				} else {
					$order = new OsOrderModel();
					if ( $cart->order_id ) {
						$order->where( [ 'id !=' => $cart->order_id ] );
					}
					$total_orders = $order->where( [ 'customer_id' => $customer_id ] )->should_not_be_cancelled()->count();
					if ( $total_orders <= $coupon->get_rule( 'orders_more' ) ) {
						$valid  = false;
						$reason = sprintf( __( 'You need to have at least %d orders to use this coupon', 'latepoint-pro-features' ), $coupon->get_rule( 'orders_more' ) );
					}
				}
			}
			// max orders
			if ( $coupon->get_rule( 'orders_less' ) ) {
				if ( empty( $customer_id ) ) {
					$valid  = false;
					$reason = __( 'Customer is missing', 'latepoint-pro-features' );
				} else {
					$order = new OsOrderModel();
					if ( $cart->order_id ) {
						$order->where( [ 'id !=' => $cart->order_id ] );
					}
					$total_orders = $order->where( [ 'customer_id' => $customer_id ] )->should_not_be_cancelled()->count();
					if ( $total_orders >= $coupon->get_rule( 'orders_less' ) ) {
						$valid = false;
						if ( $coupon->get_rule( 'orders_less' ) == 1 ) {
							$reason = __( 'This coupon can only be applied on your first order', 'latepoint-pro-features' );
						} else {
							$reason = sprintf( __( 'This coupon can only be applied on your first %d orders', 'latepoint-pro-features' ), $coupon->get_rule( 'orders_less' ) );
						}
					}
				}
			}
		} else {
			$valid  = false;
			$reason = __( 'This coupon code is invalid or has expired', 'latepoint-pro-features' );
		}
		if ( $valid ) {
			return true;
		} else {
			return new WP_Error( 'coupon_invalid', $reason );
		}
	}


	public static function get_statuses_list() {
		$statuses = [
			LATEPOINT_COUPON_STATUS_ACTIVE   => __( 'Active', 'latepoint-pro-features' ),
			LATEPOINT_COUPON_STATUS_DISABLED => __( 'Disabled', 'latepoint-pro-features' ),
		];

		/**
		 * List of statuses for a coupon
		 *
		 * @since 1.0.11
		 * @hook latepoint_coupon_statuses
		 *
		 * @param {array} $statuses list of statuses in array
		 * @returns {array} The filtered list of statuses
		 */
		return apply_filters( 'latepoint_coupon_statuses', $statuses );
	}

	public static function quick_coupon_btn_html( $coupon_id = false, $params = array() ) {
		$html = '';
		if ( $coupon_id ) {
			$params['coupon_id'] = $coupon_id;
		}
		$route = OsRouterHelper::build_route_name( 'coupons', 'quick_edit' );

		$params_str = http_build_query( $params );
		$html       = 'data-os-params="' . $params_str . '"
    data-os-action="' . $route . '"
    data-os-output-target="side-panel"
    data-os-after-call="latepointCouponsAddon.init_quick_coupon_form"';

		return $html;
	}

	public static function get_nice_status_name( $status ) {
		$statuses_list = OsCouponsHelper::get_statuses_list();
		if ( $status && isset( $statuses_list[ $status ] ) ) {
			return $statuses_list[ $status ];
		} else {
			return __( 'Undefined Status', 'latepoint-pro-features' );
		}
	}

	public static function get_usage_stats( string $coupon_code ): array {
		$result = [
			'usage_count'     => 0,
			'last_usage_date' => esc_html__( 'n/a', 'latepoint-pro-features' ),
			'total_discount'  => 0,
		];
		$orders = new OsOrderModel();
		#ABS because in admin side coupon discount is negative
		$stats = $orders->select( 'COUNT(*) as usage_count, MAX(created_at) as last_usage_date, SUM(ABS(coupon_discount)) as total_discount' )
					->where( [ 'coupon_code' => $coupon_code ] )
					->set_limit( 1 )
					->get_results_as_models();
		if ( $stats ) {
			$result = [
				'usage_count'     => $stats->usage_count,
				'last_usage_date' => OsCouponsHelper::get_readable_date( $stats->last_usage_date ),
				'total_discount'  => OsMoneyHelper::format_price( $stats->total_discount ),
			];
		}
		return $result;
	}

	public static function get_readable_date( $date_string = '' ) {
		if ( empty( $date_string ) ) {
			return esc_html__( 'n/a', 'latepoint-pro-features' );
		}
		try {
			$utcTimezone = new DateTimeZone( 'UTC' );
			$date        = new OsWpDateTime( $date_string, $utcTimezone );
			return OsTimeHelper::get_readable_date( $date );
		} catch ( Exception $e ) {
			return esc_html__( 'n/a', 'latepoint-pro-features' );
		}
	}
}
