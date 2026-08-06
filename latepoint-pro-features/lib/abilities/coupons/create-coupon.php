<?php
/**
 * LatePoint Pro Ability — Create coupon (MCP / WordPress Abilities API).
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateCoupon extends LatePointAbstractCouponAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-coupon';
		$this->label       = __( 'Create coupon', 'latepoint-pro-features' );
		$this->description = __( 'Creates a new coupon. Useful for offering store credit or a goodwill discount instead of issuing a refund. Supports fixed or percentage discounts, usage limits, an expiration date, and service/agent/customer restrictions.', 'latepoint-pro-features' );
		$this->permission  = 'coupon__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'code'           => [
						'type'        => 'string',
						'description' => __( 'Coupon code customers enter at checkout. Leave empty to auto-generate a unique code. Stored uppercase.', 'latepoint-pro-features' ),
					],
					'name'           => [
						'type'        => 'string',
						'description' => __( 'Internal name/label for the coupon.', 'latepoint-pro-features' ),
					],
					'description'    => [
						'type'        => 'string',
						'description' => __( 'Internal description of the coupon.', 'latepoint-pro-features' ),
					],
					'discount_type'  => [
						'type'        => 'string',
						'enum'        => [ 'fixed', 'percent' ],
						'description' => __( 'Discount type: "fixed" for a fixed amount, "percent" for a percentage.', 'latepoint-pro-features' ),
					],
					'discount_value' => [
						'type'             => 'number',
						'exclusiveMinimum' => 0,
						'description'      => __( 'Discount amount: a fixed currency amount, or a percentage (1-100) when discount_type is "percent".', 'latepoint-pro-features' ),
					],
					'status'         => [
						'type'        => 'string',
						'enum'        => [ 'active', 'disabled' ],
						'default'     => 'active',
						'description' => __( 'Coupon status. "disabled" means inactive.', 'latepoint-pro-features' ),
					],
					'active_from'    => [
						'type'        => 'string',
						'description' => __( 'Date the coupon becomes valid, in YYYY-MM-DD format. Optional.', 'latepoint-pro-features' ),
					],
					'active_to'      => [
						'type'        => 'string',
						'description' => __( 'Expiration date, in YYYY-MM-DD format. Optional.', 'latepoint-pro-features' ),
					],
				],
				$this->rule_input_properties()
			),
			'required'   => [ 'discount_type', 'discount_value' ],
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
		// Discount type + value.
		$discount_type  = isset( $args['discount_type'] ) ? sanitize_text_field( $args['discount_type'] ) : '';
		$discount_value = isset( $args['discount_value'] ) ? (float) $args['discount_value'] : 0;
		$discount_error = $this->validate_discount( $discount_type, $discount_value );
		if ( $discount_error instanceof WP_Error ) {
			return $discount_error;
		}

		// Status — defaults to active; only active/disabled are valid.
		$status = ( isset( $args['status'] ) && '' !== $args['status'] ) ? sanitize_text_field( $args['status'] ) : LATEPOINT_COUPON_STATUS_ACTIVE;
		if ( ! in_array( $status, [ LATEPOINT_COUPON_STATUS_ACTIVE, LATEPOINT_COUPON_STATUS_DISABLED ], true ) ) {
			return new WP_Error( 'invalid_status', __( 'The status must be either "active" or "disabled".', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}

		// Dates — optional, must be YYYY-MM-DD when provided.
		$active_from = $this->normalize_date( isset( $args['active_from'] ) ? $args['active_from'] : '' );
		if ( false === $active_from ) {
			return new WP_Error( 'invalid_active_from', __( 'The "active_from" date must be in YYYY-MM-DD format.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}
		$active_to = $this->normalize_date( isset( $args['active_to'] ) ? $args['active_to'] : '' );
		if ( false === $active_to ) {
			return new WP_Error( 'invalid_active_to', __( 'The "active_to" date must be in YYYY-MM-DD format.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}

		// Coupon code — auto-generate a unique one when omitted (stored uppercase by the model).
		$code = isset( $args['code'] ) ? trim( sanitize_text_field( $args['code'] ) ) : '';
		if ( '' === $code ) {
			$code = $this->generate_unique_code();
		}

		$coupon                 = new OsCouponModel();
		$coupon->code           = $code;
		$coupon->name           = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		$coupon->description    = isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '';
		$coupon->discount_type  = $discount_type;
		$coupon->discount_value = $discount_value;
		$coupon->status         = $status;
		$coupon->active_from    = $active_from;
		$coupon->active_to      = $active_to;
		$coupon->rules          = wp_json_encode( $this->apply_rules( [], $args ) );

		if ( ! $coupon->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create coupon.', 'latepoint-pro-features' ),
				WP_DEBUG ? [ 'errors' => $coupon->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		do_action( 'latepoint_coupon_created', $coupon );

		return $this->serialize_coupon( new OsCouponModel( $coupon->id ) );
	}

	/**
	 * Generate a unique uppercase coupon code (avoids the `code` unique-index collision).
	 *
	 * @return string
	 */
	protected function generate_unique_code(): string {
		for ( $i = 0; $i < 5; $i++ ) {
			$code = strtoupper( wp_generate_password( 8, false ) );
			if ( ! ( new OsCouponModel() )->where( [ 'code' => $code ] )->count() ) {
				return $code;
			}
		}
		return strtoupper( wp_generate_password( 12, false ) );
	}
}
