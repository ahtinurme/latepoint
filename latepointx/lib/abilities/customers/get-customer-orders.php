<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetCustomerOrders extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-customer-orders';
		$this->label       = __( 'Get customer orders', 'latepoint' );
		$this->description = __( 'Returns all orders for a specific customer.', 'latepoint' );
		$this->permission  = 'customer__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				self::pagination(),
				[
					'customer_id' => [
						'type'        => 'integer',
						'description' => __( 'Customer ID.', 'latepoint' ),
					],
				]
			),
			'required'   => [ 'customer_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'orders'   => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$customer = new OsCustomerModel( (int) $args['customer_id'] );
		if ( $customer->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query  = ( new OsOrderModel() )->where( [ 'customer_id' => $customer->id ] );
		$orders = ( clone $query )
			->order_by( 'created_at DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		$total  = $query->count();

		$serialized = array_map(
			fn( OsOrderModel $o ) => [
				'id'          => (int) $o->id,
				'status'      => $o->status ?? '',
				'total'       => $o->total ?? 0,
				'customer_id' => (int) $o->customer_id,
				'created_at'  => $o->created_at ?? '',
			],
			$orders
		);

		return [
			'orders'   => $serialized,
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
