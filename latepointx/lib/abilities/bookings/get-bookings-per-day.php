<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityGetBookingsPerDay extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-bookings-per-day';
		$this->label       = __( 'Get bookings per day', 'latepoint' );
		$this->description = __( 'Returns daily booking counts for a date range (histogram data).', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'date_from'   => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Period start (Y-m-d).', 'latepoint' ),
				],
				'date_to'     => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Period end (Y-m-d).', 'latepoint' ),
				],
				'agent_id'    => [ 'type' => 'integer' ],
				'service_id'  => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
			],
			'required'   => [ 'date_from', 'date_to' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'days' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'date'  => [
								'type'   => 'string',
								'format' => 'date',
							],
							'count' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	public function execute( array $args ) {
		$filter              = new \LatePoint\Misc\Filter();
		$filter->agent_id    = ! empty( $args['agent_id'] ) ? (int) $args['agent_id'] : 0;
		$filter->service_id  = ! empty( $args['service_id'] ) ? (int) $args['service_id'] : 0;
		$filter->location_id = ! empty( $args['location_id'] ) ? (int) $args['location_id'] : 0;

		$rows = OsBookingHelper::get_total_bookings_per_day_for_period(
			sanitize_text_field( $args['date_from'] ),
			sanitize_text_field( $args['date_to'] ),
			$filter
		);

		$days = array_map(
			fn( $row ) => [
				'date'  => is_array( $row ) ? $row['start_date'] : $row->start_date,
				'count' => (int) ( is_array( $row ) ? $row['bookings_per_day'] : $row->bookings_per_day ),
			],
			$rows
		);

		return [ 'days' => $days ];
	}
}
