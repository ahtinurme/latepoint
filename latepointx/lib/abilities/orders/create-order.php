<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateOrder extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-order';
		$this->label       = __( 'Create order', 'latepoint' );
		$this->description = __( 'Creates a new order record. Orders group one or more bookings together for billing purposes.', 'latepoint' );
		$this->permission  = 'booking__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'customer_id'    => [
					'type'        => 'integer',
					'description' => __( 'Customer ID.', 'latepoint' ),
				],
				'status'         => [
					'type'    => 'string',
					'enum'    => [ 'open', 'cancelled', 'completed' ],
					'default' => 'open',
				],
				'payment_status' => [ 'type' => 'string' ],
				'notes'          => [ 'type' => 'string' ],
			],
			'required'   => [ 'customer_id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->order_output_schema();
	}

	public function execute( array $args ) {
		$order              = new OsOrderModel();
		$order->customer_id = (int) $args['customer_id'];
		$order->status      = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : LATEPOINT_ORDER_STATUS_OPEN;

		if ( ! empty( $args['payment_status'] ) ) {
			$order->payment_status = sanitize_text_field( $args['payment_status'] );
		}
		if ( ! empty( $args['notes'] ) ) {
			$order->customer_comment = sanitize_textarea_field( $args['notes'] );
		}

		if ( ! $order->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create order.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $order->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_order( new OsOrderModel( $order->id ) );
	}
}
