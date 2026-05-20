<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityChangeOrderStatus extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/change-order-status';
		$this->label       = __( 'Change order status', 'latepoint' );
		$this->description = __( 'Changes the status of an order (e.g. open, completed, cancelled).', 'latepoint' );
		$this->permission  = 'booking__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'     => [
					'type'        => 'integer',
					'description' => __( 'Order ID.', 'latepoint' ),
				],
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'open', 'cancelled', 'completed' ],
					'description' => __( 'New status.', 'latepoint' ),
				],
			],
			'required'   => [ 'id', 'status' ],
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

		$order->status = sanitize_text_field( $args['status'] );
		if ( ! $order->save() ) {
			return new WP_Error( 'save_failed', __( 'Failed to change order status.', 'latepoint' ), [ 'status' => 422 ] );
		}

		return $this->serialize_order( new OsOrderModel( $order->id ) );
	}
}
