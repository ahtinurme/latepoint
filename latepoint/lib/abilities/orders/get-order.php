<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetOrder extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-order';
		$this->label       = __( 'Get order', 'latepoint' );
		$this->description = __( 'Returns a single order by ID.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Order ID.', 'latepoint' ),
				],
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
		return $this->serialize_order( $order );
	}
}
