<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsTaxesHelper {

	public function __construct() {
	}

	public static function add_taxes_to_order_price_breakdown_rows( array $rows, OsOrderModel $order, $rows_to_hide ) {
		if ( ! empty( $order->taxes ) ) {
			$rows['after_subtotal']['taxes'] = [ 'items' => [] ];
			foreach ( $order->taxes as $tax_id => $tax ) {
				$rows['after_subtotal']['taxes']['items'][] = [
					'label'     => $tax['label'],
					'raw_value' => OsMoneyHelper::pad_to_db_format( $tax['amount'] ),
					'value'     => OsMoneyHelper::format_price( $tax['amount'], true, false ),
				];
			}
		}

		return $rows;
	}

	public static function add_taxes_to_cart_price_breakdown_rows( array $rows, OsCartModel $cart, $rows_to_hide ) {
		if ( ! empty( $cart->taxes ) ) {
			$rows['after_subtotal']['taxes'] = [ 'items' => [] ];
			foreach ( $cart->taxes as $tax_id => $tax ) {
				$rows['after_subtotal']['taxes']['items'][] = [
					'label'     => $tax['label'],
					'raw_value' => OsMoneyHelper::pad_to_db_format( $tax['amount'] ),
					'value'     => OsMoneyHelper::format_price( $tax['amount'], true, false ),
				];
			}
		}

		return $rows;
	}

	/**
	 * @param \LatePoint\Addons\Taxes\Tax[] $taxes
	 *
	 * @return mixed
	 */
	public static function prepare_to_save( $taxes ) {
		$taxes_arr = [];
		foreach ( $taxes as $tax ) {
			$taxes_arr[] = $tax->to_save_format();
		}

		// !!TODO
		return $taxes;
	}


	public static function has_validation_errors( $params ) {
		$errors = [];
		if ( empty( $params['name'] ) ) {
			$errors[] = __( 'Tax Name cannot be empty', 'latepoint-pro-features' );
		}
		if ( empty( $params['type'] ) ) {
			$errors[] = __( 'Tax Type cannot be empty', 'latepoint-pro-features' );
		}
		if ( empty( $params['value'] ) ) {
			$errors[] = __( 'Tax Value cannot be empty', 'latepoint-pro-features' );
		}
		if ( empty( $errors ) ) {
			return false;
		} else {
			return $errors;
		}
	}

	/**
	 * @param $params
	 *
	 * @return \LatePoint\Addons\Taxes\Tax
	 */
	public static function load_from_params( $params ): \LatePoint\Addons\Taxes\Tax {
		return new \LatePoint\Addons\Taxes\Tax( $params );
	}

	public static function save_from_params( $params ) {
		$taxes  = self::get_taxes_arr();
		$errors = [];
		foreach ( $params as $tax_params ) {
			$tax_params['value'] = OsParamsHelper::sanitize_param( $tax_params['value'], 'money' );
			$validation_errors   = OsTaxesHelper::has_validation_errors( $tax_params );
			if ( is_array( $validation_errors ) ) {
				$errors[] = implode( ', ', $validation_errors );
			} else {
				$tax               = self::load_from_params( $tax_params );
				$taxes[ $tax->id ] = $tax->to_save_format();
			}
		}
		if ( $errors ) {
			return new WP_Error( 'invalid_taxes_params', implode( $errors ) );
		} else {
			return OsSettingsHelper::save_setting_by_name( 'taxes', json_encode( $taxes ) );
		}
	}

	public static function delete( $tax_id ) {
		if ( isset( $tax_id ) && ! empty( $tax_id ) ) {
			$taxes = self::get_taxes_arr();
			unset( $taxes[ $tax_id ] );

			return self::save_taxes_arr( $taxes );
		} else {
			return false;
		}
	}

	public static function calculate_tax_for_amount( \LatePoint\Addons\Taxes\Tax $tax, $amount ) {
		$tax_amount = 0;
		if ( $tax->value > 0 ) {
			switch ( $tax->type ) {
				case 'fixed':
					$tax_amount = $tax->value;
					break;
				case 'percentage':
					$tax_amount = $amount * $tax->value / 100;
					break;
			}
		}

		return $tax_amount;
	}

	public static function generate_tax_id() {
		return 'tax_' . OsUtilHelper::random_text( 'alnum', 8 );
	}

	// to ignore conditions - pass false for $booking_object

	/**
	 * @return \LatePoint\Addons\Taxes\Tax[]
	 */
	public static function get_taxes_arr(): array {
		$taxes_json = OsSettingsHelper::get_settings_value( 'taxes', '' );
		$taxes      = [];
		if ( $taxes_json ) {
			$taxes_arr = json_decode( $taxes_json, true );
			foreach ( $taxes_arr as $tax_arr ) {
				$tax               = new \LatePoint\Addons\Taxes\Tax( $tax_arr );
				$taxes[ $tax->id ] = $tax;
			}
		}

		return $taxes;
	}

	public static function save_taxes_arr( $taxes_arr ) {
		$taxes_arr = self::prepare_to_save( $taxes_arr );

		return OsSettingsHelper::save_setting_by_name( 'taxes', json_encode( $taxes_arr ) );
	}

	public static function calculate_taxes_for_cart( OsCartModel $cart ): array {
		$cart_taxes       = [];
		$taxes            = OsTaxesHelper::get_taxes_arr();
		$cart_total       = $cart->get_total();
		$total_tax_amount = 0;
		foreach ( $taxes as $tax ) {
			$decimal_separator      = OsSettingsHelper::get_settings_value( 'decimal_separator', '.' );
			$thousand_separator     = OsSettingsHelper::get_settings_value( 'thousand_separator', ',' );
			$label                  = ( $tax->type == 'fixed' ) ? $tax->name : $tax->name . ' (' . ( number_format( $tax->value, 3, $decimal_separator, $thousand_separator ) + 0 ) . '%)';
			$tax_amount             = OsTaxesHelper::calculate_tax_for_amount( $tax, $cart_total );
			$total_tax_amount       = $total_tax_amount + $tax_amount;
			$cart_taxes[ $tax->id ] = [
				'label'  => $label,
				'amount' => $tax_amount,
			];
		}

		$cart->taxes     = $cart_taxes;
		$cart->tax_total = $total_tax_amount;
		$cart->total     = $cart_total + $total_tax_amount;

		return $cart_taxes;
	}
}
