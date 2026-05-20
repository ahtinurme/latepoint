<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetWorkSchedule extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-work-schedule';
		$this->label       = __( 'Get work schedule', 'latepoint' );
		$this->description = __( 'Returns work periods for an agent or all agents.', 'latepoint' );
		$this->permission  = 'agent__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'agent_id'    => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
			],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'work_periods' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'         => [ 'type' => 'integer' ],
							'weekday'    => [ 'type' => 'integer' ],
							'start_time' => [ 'type' => 'integer' ],
							'end_time'   => [ 'type' => 'integer' ],
							'agent_id'   => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	public function execute( array $args ) {
		$query = new OsWorkPeriodModel();

		if ( ! empty( $args['agent_id'] ) ) {
			$query->where( [ 'agent_id' => (int) $args['agent_id'] ] );
		}
		if ( ! empty( $args['location_id'] ) ) {
			$query->where( [ 'location_id' => (int) $args['location_id'] ] );
		}

		$periods = $query->order_by( 'week_day ASC, start_time ASC' )->get_results_as_models();

		return [ 'work_periods' => array_map( [ $this, 'serialize_work_period' ], $periods ) ];
	}
}
