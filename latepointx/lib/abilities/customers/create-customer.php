<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateCustomer extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-customer';
		$this->label       = __( 'Create customer', 'latepoint' );
		$this->description = __( 'Creates a new customer record. Email address must be unique across all customers.', 'latepoint' );
		$this->permission  = 'customer__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'first_name' => [
					'type'        => 'string',
					'description' => __( 'First name.', 'latepoint' ),
				],
				'last_name'  => [
					'type'        => 'string',
					'description' => __( 'Last name.', 'latepoint' ),
				],
				'email'      => [
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'Email address.', 'latepoint' ),
				],
				'phone'      => [
					'type'        => 'string',
					'description' => __( 'Phone number.', 'latepoint' ),
				],
				'notes'      => [
					'type'        => 'string',
					'description' => __( 'Internal notes.', 'latepoint' ),
				],
			],
			'required'   => [ 'first_name', 'email' ],
		];
	}

	public function get_output_schema(): array {
		return $this->customer_output_schema();
	}

	public function execute( array $args ) {
		$customer             = new OsCustomerModel();
		$customer->first_name = sanitize_text_field( $args['first_name'] );
		$customer->email      = sanitize_email( $args['email'] );

		if ( ! empty( $args['last_name'] ) ) {
			$customer->last_name = sanitize_text_field( $args['last_name'] );
		}
		if ( ! empty( $args['phone'] ) ) {
			$customer->phone = sanitize_text_field( $args['phone'] );
		}
		if ( ! empty( $args['notes'] ) ) {
			$customer->notes = sanitize_textarea_field( $args['notes'] );
		}

		if ( ! $customer->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create customer.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $customer->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_customer( new OsCustomerModel( $customer->id ) );
	}
}
