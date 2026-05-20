<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetPendingBookingsCount extends LatePointAbstractAnalyticsAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-pending-bookings-count';
		$this->label       = __( 'Get pending bookings count', 'latepoint' );
		$this->description = __( 'Returns the total number of bookings currently in pending status.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [ 'count' => [ 'type' => 'integer' ] ],
		];
	}

	public function execute( array $args ) {
		$count = (int) ( new OsBookingModel() )->where( [ 'status' => LATEPOINT_BOOKING_STATUS_PENDING ] )->count();
		return [ 'count' => $count ];
	}
}
