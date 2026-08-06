<?php
/**
 * LatePoint Pro Ability — Update coupon (partial; only provided fields change).
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityUpdateCoupon extends LatePointAbstractCouponAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-coupon';
		$this->label       = __( 'Update coupon', 'latepoint-pro-features' );
		$this->description = __( 'Updates one or more fields on an existing coupon (looked up by ID). Only the fields provided are changed; provided coupon rules are merged onto the existing ones.', 'latepoint-pro-features' );
		$this->permission  = 'coupon__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'id'             => [
						'type'        => 'integer',
						'description' => __( 'ID of the coupon to update.', 'latepoint-pro-features' ),
					],
					'code'           => [
						'type'        => 'string',
						'description' => __( 'New coupon code. Stored uppercase; must remain unique.', 'latepoint-pro-features' ),
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
						'description'      => __( 'Discount amount: a fixed currency amount, or a percentage (1-100) when the discount type is "percent".', 'latepoint-pro-features' ),
					],
					'status'         => [
						'type'        => 'string',
						'enum'        => [ 'active', 'disabled' ],
						'description' => __( 'Coupon status. "disabled" means inactive.', 'latepoint-pro-features' ),
					],
					'active_from'    => [
						'type'        => 'string',
						'description' => __( 'Date the coupon becomes valid, in YYYY-MM-DD format. Send an empty string to clear it.', 'latepoint-pro-features' ),
					],
					'active_to'      => [
						'type'        => 'string',
						'description' => __( 'Expiration date, in YYYY-MM-DD format. Send an empty string to clear it.', 'latepoint-pro-features' ),
					],
				],
				$this->rule_input_properties()
			),
			'required'   => [ 'id' ],
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
		$coupon = new OsCouponModel( (int) ( $args['id'] ?? 0 ) );
		if ( $coupon->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Coupon not found.', 'latepoint-pro-features' ), [ 'status' => 404 ] );
		}

		// Snapshot the pre-update state for the `latepoint_coupon_updated` hook (matches the controller's contract).
		$old_coupon = clone $coupon;

		// Discount type/value — when either is supplied, validate the effective pair
		// (the supplied value falls back to the coupon's current one) and apply it.
		if ( array_key_exists( 'discount_type', $args ) || array_key_exists( 'discount_value', $args ) ) {
			$discount_type  = array_key_exists( 'discount_type', $args ) ? sanitize_text_field( (string) $args['discount_type'] ) : (string) $coupon->discount_type;
			$discount_value = array_key_exists( 'discount_value', $args ) ? (float) $args['discount_value'] : (float) $coupon->discount_value;
			$discount_error = $this->validate_discount( $discount_type, $discount_value );
			if ( $discount_error instanceof WP_Error ) {
				return $discount_error;
			}
			$coupon->discount_type  = $discount_type;
			$coupon->discount_value = $discount_value;
		}

		// Status — validate when provided.
		if ( array_key_exists( 'status', $args ) ) {
			$status = sanitize_text_field( (string) $args['status'] );
			if ( ! in_array( $status, [ LATEPOINT_COUPON_STATUS_ACTIVE, LATEPOINT_COUPON_STATUS_DISABLED ], true ) ) {
				return new WP_Error( 'invalid_status', __( 'The status must be either "active" or "disabled".', 'latepoint-pro-features' ), [ 'status' => 422 ] );
			}
			$coupon->status = $status;
		}

		// Code — a code is required to stay non-empty, so only apply a non-empty value.
		if ( array_key_exists( 'code', $args ) ) {
			$code = trim( sanitize_text_field( (string) $args['code'] ) );
			if ( '' === $code ) {
				return new WP_Error( 'invalid_code', __( 'The coupon code cannot be empty.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
			}
			$coupon->code = $code;
		}

		if ( array_key_exists( 'name', $args ) ) {
			$coupon->name = sanitize_text_field( (string) $args['name'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$coupon->description = sanitize_textarea_field( (string) $args['description'] );
		}

		// Dates — optional; an empty string clears the date. Reject malformed values.
		if ( array_key_exists( 'active_from', $args ) ) {
			$active_from = $this->normalize_date( $args['active_from'] );
			if ( false === $active_from ) {
				return new WP_Error( 'invalid_active_from', __( 'The "active_from" date must be in YYYY-MM-DD format.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
			}
			$coupon->active_from = $active_from;
		}
		if ( array_key_exists( 'active_to', $args ) ) {
			$active_to = $this->normalize_date( $args['active_to'] );
			if ( false === $active_to ) {
				return new WP_Error( 'invalid_active_to', __( 'The "active_to" date must be in YYYY-MM-DD format.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
			}
			$coupon->active_to = $active_to;
		}

		// Rules — only touch when a rule input was supplied; merge onto existing rules.
		if ( $this->has_rule_args( $args ) ) {
			$existing = [];
			if ( ! empty( $coupon->rules ) ) {
				$decoded = json_decode( $coupon->rules, true );
				if ( is_array( $decoded ) ) {
					$existing = $decoded;
				}
			}
			$coupon->rules = wp_json_encode( $this->apply_rules( $existing, $args ) );
		}

		if ( ! $coupon->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update coupon.', 'latepoint-pro-features' ),
				WP_DEBUG ? [ 'errors' => $coupon->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		do_action( 'latepoint_coupon_updated', $coupon, $old_coupon );

		return $this->serialize_coupon( new OsCouponModel( $coupon->id ) );
	}
}
