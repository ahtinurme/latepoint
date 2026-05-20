<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListCustomers extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-customers';
		$this->label       = __( 'List customers', 'latepoint' );
		$this->description = __( 'Returns a paginated list of customers with optional search/filter.', 'latepoint' );
		$this->permission  = 'customer__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				self::pagination(),
				[
					'search' => [
						'type'        => 'string',
						'description' => __( 'Search by name, email, or phone.', 'latepoint' ),
					],
					'status' => [
						'type'        => 'string',
						'description' => __( 'Filter by customer status.', 'latepoint' ),
					],
				]
			),
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'customers' => [
					'type'  => 'array',
					'items' => $this->customer_output_schema(),
				],
				'total'     => [ 'type' => 'integer' ],
				'page'      => [ 'type' => 'integer' ],
				'per_page'  => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsCustomerModel();

		if ( ! empty( $args['search'] ) ) {
			$s = '%' . sanitize_text_field( $args['search'] ) . '%';
			$query->where(
				[
					'OR' => [
						'first_name LIKE' => $s,
						'last_name LIKE'  => $s,
						'email LIKE'      => $s,
						'phone LIKE'      => $s,
					],
				]
			);
		}
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}

		$customers = ( clone $query )
			->order_by( 'last_name ASC, first_name ASC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		$total     = $query->count();

		return [
			'customers' => array_map( [ $this, 'serialize_customer' ], $customers ),
			'total'     => (int) $total,
			'page'      => $page,
			'per_page'  => $per_page,
		];
	}
}
