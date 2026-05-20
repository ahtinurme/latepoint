<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListOrders extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-orders';
		$this->label       = __( 'List orders', 'latepoint' );
		$this->description = __( 'Returns a paginated, filtered list of orders.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'status'         => [
						'type' => 'string',
						'enum' => [ 'open', 'cancelled', 'completed' ],
					],
					'customer_id'    => [ 'type' => 'integer' ],
					'payment_status' => [ 'type' => 'string' ],
					'date_from'      => [
						'type'   => 'string',
						'format' => 'date',
					],
					'date_to'        => [
						'type'   => 'string',
						'format' => 'date',
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
				'orders'   => [
					'type'  => 'array',
					'items' => $this->order_output_schema(),
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsOrderModel();

		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$query->where( [ 'customer_id' => (int) $args['customer_id'] ] );
		}
		if ( ! empty( $args['payment_status'] ) ) {
			$query->where( [ 'payment_status' => sanitize_text_field( $args['payment_status'] ) ] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$query->where( [ 'created_at >=' => sanitize_text_field( $args['date_from'] ) ] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$query->where( [ 'created_at <=' => sanitize_text_field( $args['date_to'] ) ] );
		}

		$total  = ( clone $query )->count();
		$orders = $query
			->order_by( 'created_at DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();

		return [
			'orders'   => array_map( [ $this, 'serialize_order' ], $orders ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
