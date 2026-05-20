<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityApproveBooking extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/approve-booking';
		$this->label       = __( 'Approve booking', 'latepoint' );
		$this->description = __( 'Approves a pending booking by setting its status to approved. May trigger confirmation notifications to the customer.', 'latepoint' );
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
				'status' => LATEPOINT_BOOKING_STATUS_APPROVED,
			] 
		);
	}
}
