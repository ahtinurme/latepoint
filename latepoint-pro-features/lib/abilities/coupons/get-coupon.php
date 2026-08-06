<?php
/**
 * LatePoint Pro Ability — Get coupon (by id or code).
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetCoupon extends LatePointAbstractCouponAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-coupon';
		$this->label       = __( 'Get coupon', 'latepoint-pro-features' );
		$this->description = __( 'Returns a single coupon by ID or by code.', 'latepoint-pro-features' );
		$this->permission  = 'coupon__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'   => [
					'type'        => 'integer',
					'description' => __( 'Coupon ID. Provide either id or code.', 'latepoint-pro-features' ),
				],
				'code' => [
					'type'        => 'string',
					'description' => __( 'Coupon code (case-insensitive). Provide either id or code.', 'latepoint-pro-features' ),
				],
			],
		];
	}

	public function get_output_schema(): array {
		return $this->coupon_output_schema();
	}

	/**
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public function execute( array $args ) {
		if ( ! empty( $args['id'] ) ) {
			$coupon = new OsCouponModel( (int) $args['id'] );
			if ( $coupon->is_new_record() ) {
				return new WP_Error( 'not_found', __( 'Coupon not found.', 'latepoint-pro-features' ), [ 'status' => 404 ] );
			}
		} elseif ( ! empty( $args['code'] ) ) {
			$found = ( new OsCouponModel() )
				->where( [ 'code' => strtoupper( trim( sanitize_text_field( $args['code'] ) ) ) ] )
				->set_limit( 1 )
				->get_results_as_models();
			if ( empty( $found ) ) {
				return new WP_Error( 'not_found', __( 'Coupon not found.', 'latepoint-pro-features' ), [ 'status' => 404 ] );
			}
			// get_results_as_models() returns a single model when a limit of 1 is set, otherwise an array.
			$coupon = is_array( $found ) ? reset( $found ) : $found;
		} else {
			return new WP_Error( 'missing_identifier', __( 'Provide either a coupon id or code.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}

		return $this->serialize_coupon( $coupon );
	}
}
