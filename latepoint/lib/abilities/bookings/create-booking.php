<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityCreateBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-booking';
		$this->label       = __( 'Create booking', 'latepoint' );
		$this->description = __( 'Creates a new booking for a customer with a specific service, agent, date and time. May trigger booking confirmation notifications.', 'latepoint' );
		$this->permission  = 'booking__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'customer_id' => [
					'type'        => 'integer',
					'description' => __( 'Customer ID.', 'latepoint' ),
				],
				'service_id'  => [
					'type'        => 'integer',
					'description' => __( 'Service ID.', 'latepoint' ),
				],
				'agent_id'    => [
					'type'        => 'integer',
					'description' => __( 'Agent ID.', 'latepoint' ),
				],
				'location_id' => [
					'type'        => 'integer',
					'description' => __( 'Location ID.', 'latepoint' ),
				],
				'start_date'  => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Booking date (Y-m-d).', 'latepoint' ),
				],
				'start_time'  => [
					'type'        => 'integer',
					'description' => __( 'Start time (minutes from midnight).', 'latepoint' ),
				],
				'end_time'    => [
					'type'        => 'integer',
					'description' => __( 'End time (minutes from midnight).', 'latepoint' ),
				],
				'status'      => [
					'type'        => 'string',
					'enum'        => [ 'approved', 'pending' ],
					'default'     => 'approved',
					'description' => __( 'Initial booking status.', 'latepoint' ),
				],
				'notes'       => [
					'type'        => 'string',
					'description' => __( 'Internal booking notes.', 'latepoint' ),
				],
			],
			'required'   => [ 'customer_id', 'service_id', 'start_date', 'start_time' ],
		];
	}

	public function get_output_schema(): array {
		return $this->booking_output_schema();
	}

	public function execute( array $args ) {
		$order              = new OsOrderModel();
		$order->customer_id = (int) $args['customer_id'];
		$order->status      = LATEPOINT_ORDER_STATUS_OPEN;
		if ( ! $order->save() ) {
			return new WP_Error( 'save_failed', __( 'Failed to create order.', 'latepoint' ), [ 'status' => 422 ] );
		}

		$order_item           = new OsOrderItemModel();
		$order_item->order_id = $order->id;
		if ( ! $order_item->save() ) {
			return new WP_Error( 'save_failed', __( 'Failed to create order item.', 'latepoint' ), [ 'status' => 422 ] );
		}

		$booking                = new OsBookingModel();
		$booking->customer_id   = (int) $args['customer_id'];
		$booking->service_id    = (int) $args['service_id'];
		$booking->order_item_id = $order_item->id;
		$booking->start_date    = sanitize_text_field( $args['start_date'] );
		$booking->start_time    = (int) $args['start_time'];

		if ( ! empty( $args['agent_id'] ) ) {
			$booking->agent_id = (int) $args['agent_id'];
		}
		if ( ! empty( $args['location_id'] ) ) {
			$booking->location_id = (int) $args['location_id'];
		}
		if ( ! empty( $args['end_time'] ) ) {
			$booking->end_time = (int) $args['end_time'];
		}

		$booking->status = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : LATEPOINT_BOOKING_STATUS_APPROVED;

		if ( ! $booking->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create booking.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $booking->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_booking( new OsBookingModel( $booking->id ) );
	}
}
