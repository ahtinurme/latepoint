<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetDashboardStats extends LatePointAbstractAnalyticsAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-dashboard-stats';
		$this->label       = __( 'Get dashboard stats', 'latepoint' );
		$this->description = __( 'Returns aggregate booking statistics for a date range (defaults to current month).', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'date_from' => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Period start (Y-m-d). Defaults to first day of current month.', 'latepoint' ),
				],
				'date_to'   => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Period end (Y-m-d). Defaults to last day of current month.', 'latepoint' ),
				],
			],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'bookings_count'  => [ 'type' => 'integer' ],
				'revenue'         => [ 'type' => 'number' ],
				'customers_count' => [ 'type' => 'integer' ],
				'pending_count'   => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$date_from = ! empty( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : wp_date( 'Y-m-01' );
		$date_to   = ! empty( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : wp_date( 'Y-m-t' );

		$filter = new \LatePoint\Misc\Filter();

		$bookings_count  = (int) OsBookingHelper::get_stat_for_period( 'bookings', $date_from, $date_to, $filter );
		$revenue         = (float) OsBookingHelper::get_stat_for_period( 'price', $date_from, $date_to, $filter );
		$customers_count = (int) ( new OsCustomerModel() )->count();
		$pending_count   = (int) ( new OsBookingModel() )->where( [ 'status' => LATEPOINT_BOOKING_STATUS_PENDING ] )->count();

		return [
			'bookings_count'  => $bookings_count,
			'revenue'         => $revenue,
			'customers_count' => $customers_count,
			'pending_count'   => $pending_count,
		];
	}
}
