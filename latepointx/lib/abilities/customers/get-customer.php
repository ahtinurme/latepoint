<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetCustomer extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-customer';
		$this->label       = __( 'Get customer', 'latepoint' );
		$this->description = __( 'Returns a single customer by ID with full profile.', 'latepoint' );
		$this->permission  = 'customer__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Customer ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->customer_output_schema();
	}

	public function execute( array $args ) {
		$customer = new OsCustomerModel( (int) $args['id'] );
		if ( $customer->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		return $this->serialize_customer( $customer );
	}
}
