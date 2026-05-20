<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityGetBookingStats extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-booking-stats';
		$this->label       = __( 'Get booking stats', 'latepoint' );
		$this->description = __( 'Returns aggregate booking statistics (count, revenue, or duration) for a date range.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'stat'        => [
					'type'        => 'string',
					'enum'        => [ 'bookings', 'price', 'duration' ],
					'description' => __( 'Metric to aggregate.', 'latepoint' ),
				],
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
				'group_by'    => [
					'type'        => 'string',
					'enum'        => [ 'agent_id', 'service_id', 'location_id' ],
					'description' => __( 'Optional grouping dimension.', 'latepoint' ),
				],
			],
			'required'   => [ 'stat', 'date_from', 'date_to' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'stat'   => [ 'type' => 'string' ],
				'result' => [
					'type'        => 'number',
					'description' => __( 'Numeric total when no group_by; array of {value, group_by_value} objects when group_by is set.', 'latepoint' ),
				],
			],
		];
	}

	public function execute( array $args ) {
		$filter              = new \LatePoint\Misc\Filter();
		$filter->agent_id    = ! empty( $args['agent_id'] ) ? (int) $args['agent_id'] : 0;
		$filter->service_id  = ! empty( $args['service_id'] ) ? (int) $args['service_id'] : 0;
		$filter->location_id = ! empty( $args['location_id'] ) ? (int) $args['location_id'] : 0;

		$group_by = ! empty( $args['group_by'] ) ? sanitize_text_field( $args['group_by'] ) : false;
		$result   = OsBookingHelper::get_stat_for_period(
			sanitize_text_field( $args['stat'] ),
			sanitize_text_field( $args['date_from'] ),
			sanitize_text_field( $args['date_to'] ),
			$filter,
			$group_by
		);

		if ( $result === false ) {
			return new WP_Error( 'invalid_stat', __( 'Invalid stat or group_by parameter.', 'latepoint' ), [ 'status' => 400 ] );
		}

		return [
			'stat'   => $args['stat'],
			'result' => $result,
		];
	}
}
