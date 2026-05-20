<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityDeleteBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/delete-booking';
		$this->label       = __( 'Delete booking', 'latepoint' );
		$this->description = __( 'Permanently deletes a booking record from the database. This cannot be undone. Use cancel-booking to preserve the record instead.', 'latepoint' );
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
					'description' => __( 'Booking ID to delete.', 'latepoint' ),
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
		$booking = new OsBookingModel( (int) $args['id'] );
		if ( $booking->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$id = (int) $booking->id;
		if ( ! $booking->delete() ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete booking.', 'latepoint' ), [ 'status' => 500 ] );
		}

		return [
			'deleted' => true,
			'id'      => $id,
		];
	}
}
