<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityRescheduleBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/reschedule-booking';
		$this->label       = __( 'Reschedule booking', 'latepoint' );
		$this->description = __( 'Moves an existing booking to a new date and/or time. May trigger rescheduling notifications to the customer.', 'latepoint' );
		$this->permission  = 'booking__edit';
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
					'description' => __( 'Booking ID.', 'latepoint' ),
				],
				'start_date' => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'New date (Y-m-d).', 'latepoint' ),
				],
				'start_time' => [
					'type'        => 'integer',
					'description' => __( 'New start time (minutes from midnight).', 'latepoint' ),
				],
				'end_time'   => [
					'type'        => 'integer',
					'description' => __( 'New end time (minutes from midnight).', 'latepoint' ),
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

		if ( isset( $args['start_date'] ) ) {
			$booking->start_date = sanitize_text_field( $args['start_date'] );
		}
		if ( isset( $args['start_time'] ) ) {
			$booking->start_time = (int) $args['start_time'];
		}
		if ( isset( $args['end_time'] ) ) {
			$booking->end_time = (int) $args['end_time'];
		}

		if ( ! $booking->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to reschedule booking.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $booking->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_booking( new OsBookingModel( $booking->id ) );
	}
}
