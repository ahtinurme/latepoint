<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityCancelBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/cancel-booking';
		$this->label       = __( 'Cancel booking', 'latepoint' );
		$this->description = __( 'Cancels a booking by setting its status to cancelled. May trigger cancellation notifications. The booking record is preserved, not deleted.', 'latepoint' );
		$this->permission  = 'booking__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
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
		return ( new LatePointAbilityChangeBookingStatus() )->execute(
			[
				'id'     => $args['id'],
				'status' => LATEPOINT_BOOKING_STATUS_CANCELLED,
			] 
		);
	}
}
