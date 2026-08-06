<?php
/**
 * LatePoint Pro Ability — List coupons (paginated, filtered).
 *
 * @package LatePoint\Pro\Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListCoupons extends LatePointAbstractCouponAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-coupons';
		$this->label       = __( 'List coupons', 'latepoint-pro-features' );
		$this->description = __( 'Returns a paginated, filtered list of coupons.', 'latepoint-pro-features' );
		$this->permission  = 'coupon__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'code'          => [
						'type'        => 'string',
						'description' => __( 'Filter by exact coupon code (case-insensitive).', 'latepoint-pro-features' ),
					],
					'status'        => [
						'type'        => 'string',
						'enum'        => [ 'active', 'disabled' ],
						'description' => __( 'Filter by status.', 'latepoint-pro-features' ),
					],
					'discount_type' => [
						'type'        => 'string',
						'enum'        => [ 'fixed', 'percent' ],
						'description' => __( 'Filter by discount type.', 'latepoint-pro-features' ),
					],
				],
				self::pagination()
			),
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'coupons'  => [
					'type'  => 'array',
					'items' => $this->coupon_output_schema(),
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	/**
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsCouponModel();

		if ( ! empty( $args['code'] ) ) {
			$query->where( [ 'code' => strtoupper( trim( sanitize_text_field( $args['code'] ) ) ) ] );
		}
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		if ( ! empty( $args['discount_type'] ) ) {
			$query->where( [ 'discount_type' => sanitize_text_field( $args['discount_type'] ) ] );
		}

		$coupons = ( clone $query )
			->order_by( 'id DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		// get_results_as_models() returns a single model (not an array) when the limit is 1.
		if ( ! is_array( $coupons ) ) {
			$coupons = $coupons ? [ $coupons ] : [];
		}
		$total = $query->count();

		return [
			'coupons'  => array_map( [ $this, 'serialize_coupon' ], $coupons ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
