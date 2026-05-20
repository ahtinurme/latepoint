<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityUpdateBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-booking';
		$this->label       = __( 'Update booking', 'latepoint' );
		$this->description = __( 'Updates one or more fields on an existing booking. Only provided fields are changed. Use reschedule-booking to change date/time specifically.', 'latepoint' );
		$this->permission  = 'booking__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'type'        => 'integer',
					'description' => __( 'Booking ID to update.', 'latepoint' ),
				],
				'start_date'  => [
					'type'   => 'string',
					'format' => 'date',
				],
				'start_time'  => [ 'type' => 'integer' ],
				'end_time'    => [ 'type' => 'integer' ],
				'agent_id'    => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
				'notes'       => [ 'type' => 'string' ],
				'status'      => [
					'type' => 'string',
					'enum' => [ 'approved', 'pending', 'cancelled', 'no_show', 'completed' ],
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->booking_output_schema();
	}

	public function execute( array $args ) {
		$booking = new OsBookingModel( (int) $args['id'] );
		if ( $booking->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$allowed = [ 'start_date', 'start_time', 'end_time', 'agent_id', 'location_id', 'status' ];
		foreach ( $allowed as $field ) {
			if ( isset( $args[ $field ] ) ) {
				$booking->$field = in_array( $field, [ 'agent_id', 'location_id', 'start_time', 'end_time' ], true )
					? (int) $args[ $field ]
					: sanitize_text_field( $args[ $field ] );
			}
		}

		if ( ! $booking->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update booking.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $booking->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		if ( isset( $args['notes'] ) ) {
			$order = $booking->get_order();
			if ( $order && ! $order->is_new_record() ) {
				$order->customer_comment = sanitize_textarea_field( $args['notes'] );
				$order->save();
			}
		}

		return $this->serialize_booking( new OsBookingModel( $booking->id ) );
	}
}
