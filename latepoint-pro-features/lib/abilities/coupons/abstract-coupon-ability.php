<?php
/**
 * Shared base for the Pro coupon abilities: serializer, output schema, and input helpers.
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractCouponAbility extends LatePointAbstractAbility {

	protected string $role = LATEPOINT_USER_TYPE_ADMIN;

	/** Integer rule inputs mapped to their stored `rules` key (see apply_rules()). */
	protected array $int_rule_map = [
		'usage_limit'        => 'limit_total',
		'limit_per_customer' => 'limit_per_customer',
		'limit_per_order'    => 'limit_per_order',
		'orders_more'        => 'orders_more',
		'orders_less'        => 'orders_less',
	];

	public function serialize_coupon( OsCouponModel $coupon ): array {
		$rules = [];
		if ( ! empty( $coupon->rules ) ) {
			$decoded = json_decode( $coupon->rules, true );
			if ( is_array( $decoded ) ) {
				$rules = $decoded;
			}
		}

		return [
			'id'             => (int) $coupon->id,
			'code'           => $coupon->code ?? '',
			'name'           => $coupon->name ?? '',
			'description'    => $coupon->description ?? '',
			'discount_type'  => $coupon->discount_type ?? '',
			'discount_value' => (float) ( $coupon->discount_value ?? 0 ),
			'status'         => $coupon->status ?? '',
			'active_from'    => $coupon->active_from ?? '',
			'active_to'      => $coupon->active_to ?? '',
			'rules'          => empty( $rules ) ? new \stdClass() : $rules,
			'created_at'     => ! empty( $coupon->created_at ) ? date( 'c', strtotime( $coupon->created_at ) ) : '',
			'updated_at'     => ! empty( $coupon->updated_at ) ? date( 'c', strtotime( $coupon->updated_at ) ) : '',
		];
	}

	protected function coupon_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'             => [ 'type' => 'integer' ],
				'code'           => [ 'type' => 'string' ],
				'name'           => [ 'type' => 'string' ],
				'description'    => [ 'type' => 'string' ],
				'discount_type'  => [ 'type' => 'string' ],
				'discount_value' => [ 'type' => 'number' ],
				'status'         => [ 'type' => 'string' ],
				'active_from'    => [ 'type' => 'string' ],
				'active_to'      => [ 'type' => 'string' ],
				'rules'          => [ 'type' => 'object' ],
				'created_at'     => [ 'type' => 'string' ],
				'updated_at'     => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * Shared coupon rule/restriction input properties for create and update.
	 *
	 * @return array
	 */
	protected function rule_input_properties(): array {
		return [
			'usage_limit'            => [
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'Maximum total number of times this coupon can be used across all customers.', 'latepoint-pro-features' ),
			],
			'limit_per_customer'     => [
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'Maximum number of times a single customer can use this coupon.', 'latepoint-pro-features' ),
			],
			'limit_per_order'        => [
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'Maximum number of times this coupon can be applied within a single order.', 'latepoint-pro-features' ),
			],
			'minimum_order_subtotal' => [
				'type'        => 'number',
				'minimum'     => 0,
				'description' => __( 'Minimum order subtotal required for the coupon to apply.', 'latepoint-pro-features' ),
			],
			'service_ids'            => [
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
				'description' => __( 'Restrict the coupon to these service IDs.', 'latepoint-pro-features' ),
			],
			'agent_ids'              => [
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
				'description' => __( 'Restrict the coupon to these agent IDs.', 'latepoint-pro-features' ),
			],
			'bundle_ids'             => [
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
				'description' => __( 'Restrict the coupon to these bundle IDs.', 'latepoint-pro-features' ),
			],
			'customer_ids'           => [
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
				'description' => __( 'Restrict the coupon to these customer IDs.', 'latepoint-pro-features' ),
			],
			'orders_more'            => [
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'Only valid for customers who have more than this many prior orders.', 'latepoint-pro-features' ),
			],
			'orders_less'            => [
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => __( 'Only valid for customers who have fewer than this many prior orders (e.g. 1 = first order only).', 'latepoint-pro-features' ),
			],
		];
	}

	/**
	 * Whether any coupon `rules` input was supplied in the given args.
	 *
	 * @param array $args
	 * @return bool
	 */
	protected function has_rule_args( array $args ): bool {
		$rule_keys = array_merge(
			array_keys( $this->int_rule_map ),
			[ 'minimum_order_subtotal', 'service_ids', 'agent_ids', 'bundle_ids', 'customer_ids' ]
		);
		foreach ( $rule_keys as $key ) {
			if ( array_key_exists( $key, $args ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Merge provided rule inputs onto a base set. Empty value clears a rule; omitted leaves it.
	 * ID lists are stored comma-joined (UI format). Create passes []; update the existing rules.
	 *
	 * @param array $base Existing rules to merge onto.
	 * @param array $args Provided input arguments.
	 * @return array
	 */
	protected function apply_rules( array $base, array $args ): array {
		$rules = $base;

		foreach ( $this->int_rule_map as $arg_key => $rule_key ) {
			if ( array_key_exists( $arg_key, $args ) ) {
				if ( '' === $args[ $arg_key ] || null === $args[ $arg_key ] ) {
					unset( $rules[ $rule_key ] );
				} else {
					$rules[ $rule_key ] = absint( $args[ $arg_key ] );
				}
			}
		}

		if ( array_key_exists( 'minimum_order_subtotal', $args ) ) {
			if ( '' === $args['minimum_order_subtotal'] || null === $args['minimum_order_subtotal'] ) {
				unset( $rules['minimum_order_subtotal'] );
			} else {
				$rules['minimum_order_subtotal'] = (float) $args['minimum_order_subtotal'];
			}
		}

		foreach ( [ 'service_ids', 'agent_ids', 'bundle_ids', 'customer_ids' ] as $id_key ) {
			if ( array_key_exists( $id_key, $args ) ) {
				$ids = is_array( $args[ $id_key ] ) ? array_filter( array_map( 'absint', $args[ $id_key ] ) ) : [];
				if ( $ids ) {
					$rules[ $id_key ] = implode( ',', $ids );
				} else {
					unset( $rules[ $id_key ] );
				}
			}
		}

		return $rules;
	}

	/**
	 * Validate a coupon discount type and value.
	 *
	 * @param string $type  'fixed' or 'percent'.
	 * @param float  $value Discount amount.
	 * @return \WP_Error|null WP_Error (422) when invalid, null when valid.
	 */
	protected function validate_discount( string $type, float $value ): ?\WP_Error {
		if ( ! in_array( $type, [ 'fixed', 'percent' ], true ) ) {
			return new WP_Error( 'invalid_discount_type', __( 'The discount type must be either "fixed" or "percent".', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}
		if ( $value <= 0 ) {
			return new WP_Error( 'invalid_discount_value', __( 'The discount value must be greater than zero.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}
		if ( 'percent' === $type && $value > 100 ) {
			return new WP_Error( 'invalid_discount_value', __( 'A percentage discount cannot exceed 100.', 'latepoint-pro-features' ), [ 'status' => 422 ] );
		}
		return null;
	}

	/**
	 * Validate an optional YYYY-MM-DD date: returns the string, null when empty, or false when invalid.
	 *
	 * @param mixed $value
	 * @return string|null|false
	 */
	protected function normalize_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return false;
		}
		return $value;
	}
}
