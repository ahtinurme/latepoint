<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetDailyChartData extends LatePointAbstractAnalyticsAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-daily-chart-data';
		$this->label       = __( 'Get daily chart data', 'latepoint' );
		$this->description = __( 'Returns daily booking counts or revenue for charting over a date range.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'date_from' => [
					'type'   => 'string',
					'format' => 'date',
				],
				'date_to'   => [
					'type'   => 'string',
					'format' => 'date',
				],
				'metric'    => [
					'type' => 'string',
					'enum' => [ 'bookings', 'revenue' ],
				],
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
							'value' => [ 'type' => 'number' ],
						],
					],
				],
			],
		];
	}

	public function execute( array $args ) {
		$date_from = sanitize_text_field( $args['date_from'] );
		$date_to   = sanitize_text_field( $args['date_to'] );
		$metric    = ! empty( $args['metric'] ) ? sanitize_text_field( $args['metric'] ) : 'bookings';
		$filter    = new \LatePoint\Misc\Filter();

		if ( $metric === 'revenue' ) {
			$rows = OsBookingHelper::get_stat_for_period( 'price', $date_from, $date_to, $filter, 'start_date' );
			$days = [];
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$days[] = [
						'date'  => $row['start_date'] ?? $row[0] ?? '',
						'value' => (float) ( $row['price'] ?? $row['value'] ?? $row[1] ?? 0 ),
					];
				}
			}
		} else {
			$rows = OsBookingHelper::get_total_bookings_per_day_for_period( $date_from, $date_to, $filter );
			$days = array_map(
				fn( $row ) => [
					'date'  => ( (array) $row )['start_date'] ?? '',
					'value' => (float) ( ( (array) $row )['bookings_per_day'] ?? 0 ),
				],
				$rows
			);
		}

		return [ 'days' => $days ];
	}
}
