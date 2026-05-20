<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityChangeBookingStatus extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/change-booking-status';
		$this->label       = __( 'Change booking status', 'latepoint' );
		$this->description = __( 'Changes a booking to any valid status value. Use get-booking-statuses to see available statuses. May trigger status-change notifications.', 'latepoint' );
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
					'description' => __( 'Booking ID.', 'latepoint' ),
				],
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'approved', 'pending', 'cancelled', 'no_show', 'completed' ],
					'description' => __( 'New status.', 'latepoint' ),
				],
			],
			'required'   => [ 'id', 'status' ],
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

		$booking->status = sanitize_text_field( $args['status'] );
		if ( ! $booking->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update booking status.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $booking->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_booking( new OsBookingModel( $booking->id ) );
	}
}
