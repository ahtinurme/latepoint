<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityUpdateOrder extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-order';
		$this->label       = __( 'Update order', 'latepoint' );
		$this->description = __( 'Updates one or more fields on an existing order. Only provided fields are changed.', 'latepoint' );
		$this->permission  = 'booking__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'             => [
					'type'        => 'integer',
					'description' => __( 'Order ID to update.', 'latepoint' ),
				],
				'status'         => [
					'type' => 'string',
					'enum' => [ 'open', 'cancelled', 'completed' ],
				],
				'payment_status' => [ 'type' => 'string' ],
				'notes'          => [ 'type' => 'string' ],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->order_output_schema();
	}

	public function execute( array $args ) {
		$order = new OsOrderModel( (int) $args['id'] );
		if ( $order->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		if ( isset( $args['status'] ) ) {
			$order->status = sanitize_text_field( $args['status'] );
		}
		if ( isset( $args['payment_status'] ) ) {
			$order->payment_status = sanitize_text_field( $args['payment_status'] );
		}
		if ( isset( $args['notes'] ) ) {
			$order->customer_comment = sanitize_textarea_field( $args['notes'] );
		}

		if ( ! $order->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update order.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $order->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_order( new OsOrderModel( $order->id ) );
	}
}
