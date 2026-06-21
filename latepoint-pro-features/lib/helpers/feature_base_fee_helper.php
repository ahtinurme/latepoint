<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureBaseFeeHelper {

	const META_KEY = 'base_fee';

	public static function get_base_fee( OsServiceModel $service ): float {
		return (float) $service->get_meta_by_key( self::META_KEY, 0 );
	}

	public static function adjust_full_amount_for_service( float $amount, OsBookingModel $booking ): float {
		$fee = self::get_base_fee( $booking->service );
		if ( $fee > 0 ) {
			$amount += $fee;
		}
		return $amount;
	}

	public static function add_base_fee_to_service_row_item( array $service_row_item, OsBookingModel $booking ): array {
		$fee = self::get_base_fee( $booking->service );
		if ( $fee <= 0 || empty( $service_row_item['items'] ) ) {
			return $service_row_item;
		}
		$formatted = OsMoneyHelper::format_price( $fee, true, false );
		/* translators: %s: formatted base fee amount */
		$note = sprintf( __( '+ %s base fee', 'latepoint-pro-features' ), $formatted );
		foreach ( $service_row_item['items'] as &$service_item ) {
			$existing             = isset( $service_item['note'] ) ? trim( $service_item['note'] ) : '';
			$service_item['note'] = $existing ? $existing . ' ' . $note : $note;
		}
		return $service_row_item;
	}

	public static function save_service_info( OsServiceModel $service, bool $is_new_record, array $service_params ): void {
		$raw   = isset( $service_params['base_fee'] ) ? $service_params['base_fee'] : 0;
		$value = OsParamsHelper::sanitize_param( $raw, 'money' );
		$service->save_meta_by_key( self::META_KEY, $value );
	}

	public static function render_base_fee_field( OsServiceModel $service ): void {
		echo OsFormHelper::money_field( 'service[base_fee]', __( 'Base Fee', 'latepoint-pro-features' ), self::get_base_fee( $service ), [ 'theme' => 'bordered' ] );
	}
}
