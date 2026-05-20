<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityUpdateCustomer extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-customer';
		$this->label       = __( 'Update customer', 'latepoint' );
		$this->description = __( 'Updates one or more fields on an existing customer profile. Only provided fields are changed.', 'latepoint' );
		$this->permission  = 'customer__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [
					'type'        => 'integer',
					'description' => __( 'Customer ID.', 'latepoint' ),
				],
				'first_name' => [ 'type' => 'string' ],
				'last_name'  => [ 'type' => 'string' ],
				'email'      => [
					'type'   => 'string',
					'format' => 'email',
				],
				'phone'      => [ 'type' => 'string' ],
				'notes'      => [ 'type' => 'string' ],
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

		if ( isset( $args['first_name'] ) ) {
			$customer->first_name = sanitize_text_field( $args['first_name'] );
		}
		if ( isset( $args['last_name'] ) ) {
			$customer->last_name = sanitize_text_field( $args['last_name'] );
		}
		if ( isset( $args['email'] ) ) {
			$customer->email = sanitize_email( $args['email'] );
		}
		if ( isset( $args['phone'] ) ) {
			$customer->phone = sanitize_text_field( $args['phone'] );
		}
		if ( isset( $args['notes'] ) ) {
			$customer->notes = sanitize_textarea_field( $args['notes'] );
		}

		if ( ! $customer->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update customer.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $customer->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_customer( new OsCustomerModel( $customer->id ) );
	}
}
