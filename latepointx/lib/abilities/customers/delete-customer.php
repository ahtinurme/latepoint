<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDeleteCustomer extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/delete-customer';
		$this->label       = __( 'Delete customer', 'latepoint' );
		$this->description = __( 'Permanently deletes a customer and all their associated bookings and orders. This cannot be undone.', 'latepoint' );
		$this->permission  = 'customer__delete';
		$this->read_only   = false;
		$this->destructive = true;
		$this->idempotent  = false;
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
		return [
			'type'       => 'object',
			'properties' => [
				'deleted' => [ 'type' => 'boolean' ],
				'id'      => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$customer = new OsCustomerModel( (int) $args['id'] );
		if ( $customer->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$id = (int) $customer->id;
		if ( ! $customer->delete() ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete customer.', 'latepoint' ), [ 'status' => 500 ] );
		}

		return [
			'deleted' => true,
			'id'      => $id,
		];
	}
}
