<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilitySearchCustomers extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/search-customers';
		$this->label       = __( 'Search customers', 'latepoint' );
		$this->description = __( 'Searches customers by name, email, or phone — optimised for autocomplete.', 'latepoint' );
		$this->permission  = 'customer__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'query' => [
					'type'        => 'string',
					'description' => __( 'Search term.', 'latepoint' ),
				],
				'limit' => [
					'type'    => 'integer',
					'default' => 10,
					'minimum' => 1,
					'maximum' => 50,
				],
			],
			'required'   => [ 'query' ],
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
			],
		];
	}

	public function execute( array $args ) {
		$search = sanitize_text_field( $args['query'] );
		$limit  = min( 50, max( 1, (int) ( $args['limit'] ?? 10 ) ) );

		$s         = '%' . $search . '%';
		$customers = ( new OsCustomerModel() )
			->where(
				[
					'OR' => [
						'first_name LIKE' => $s,
						'last_name LIKE'  => $s,
						'email LIKE'      => $s,
						'phone LIKE'      => $s,
					],
				]
			)
			->order_by( 'last_name ASC' )
			->set_limit( $limit )
			->get_results_as_models();

		return [
			'customers' => array_map( [ $this, 'serialize_customer' ], $customers ),
		];
	}
}
