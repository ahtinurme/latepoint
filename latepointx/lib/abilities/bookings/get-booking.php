<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityGetBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-booking';
		$this->label       = __( 'Get booking', 'latepoint' );
		$this->description = __( 'Returns a single booking by ID with all related data.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Booking ID.', 'latepoint' ),
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
		return $this->serialize_booking( $booking );
	}
}
