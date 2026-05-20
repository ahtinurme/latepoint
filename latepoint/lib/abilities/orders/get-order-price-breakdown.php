<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetOrderPriceBreakdown extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-order-price-breakdown';
		$this->label       = __( 'Get order price breakdown', 'latepoint' );
		$this->description = __( 'Returns the price breakdown (subtotal, tax, total) for an order.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'order_id' => [
					'type'        => 'integer',
					'description' => __( 'Order ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'order_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'order_id'           => [ 'type' => 'integer' ],
				'subtotal'           => [ 'type' => 'number' ],
				'total'              => [ 'type' => 'number' ],
				'payment_status'     => [ 'type' => 'string' ],
				'fulfillment_status' => [ 'type' => 'string' ],
			],
		];
	}

	public function execute( array $args ) {
		$order = new OsOrderModel( (int) $args['order_id'] );
		if ( $order->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		return [
			'order_id'           => (int) $order->id,
			'subtotal'           => (float) ( $order->subtotal ?? 0 ),
			'total'              => (float) ( $order->total ?? 0 ),
			'payment_status'     => $order->payment_status ?? '',
			'fulfillment_status' => $order->fulfillment_status ?? '',
		];
	}
}
