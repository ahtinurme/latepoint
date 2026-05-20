<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetCustomerByEmail extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-customer-by-email';
		$this->label       = __( 'Get customer by email', 'latepoint' );
		$this->description = __( 'Looks up a customer by their email address.', 'latepoint' );
		$this->permission  = 'customer__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'email' => [
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'Customer email address.', 'latepoint' ),
				],
			],
			'required'   => [ 'email' ],
		];
	}

	public function get_output_schema(): array {
		return $this->customer_output_schema();
	}

	public function execute( array $args ) {
		$email    = sanitize_email( $args['email'] );
		$customer = ( new OsCustomerModel() )->where( [ 'email' => $email ] )->set_limit( 1 )->get_results_as_models();
		if ( ! $customer ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		return $this->serialize_customer( $customer );
	}
}
