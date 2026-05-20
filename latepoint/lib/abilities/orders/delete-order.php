<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDeleteOrder extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/delete-order';
		$this->label       = __( 'Delete order', 'latepoint' );
		$this->description = __( 'Permanently deletes an order and its associated invoices. This cannot be undone.', 'latepoint' );
		$this->permission  = 'booking__delete';
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
					'description' => __( 'Order ID to delete.', 'latepoint' ),
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
		$order = new OsOrderModel( (int) $args['id'] );
		if ( $order->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$id = (int) $order->id;
		if ( ! $order->delete() ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete order.', 'latepoint' ), [ 'status' => 422 ] );
		}

		return [
			'deleted' => true,
			'id'      => $id,
		];
	}
}
