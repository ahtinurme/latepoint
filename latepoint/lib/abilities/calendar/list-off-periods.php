<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListOffPeriods extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-off-periods';
		$this->label       = __( 'List off periods', 'latepoint' );
		$this->description = __( 'Returns off/blocked periods for agents, optionally filtered by date range.', 'latepoint' );
		$this->permission  = 'agent__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'agent_id'  => [ 'type' => 'integer' ],
				'date_from' => [
					'type'   => 'string',
					'format' => 'date',
				],
				'date_to'   => [
					'type'   => 'string',
					'format' => 'date',
				],
			],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'off_periods' => [
					'type'  => 'array',
					'items' => $this->off_period_output_schema(),
				],
			],
		];
	}

	public function execute( array $args ) {
		$query = new OsOffPeriodModel();

		if ( ! empty( $args['agent_id'] ) ) {
			$query->where( [ 'agent_id' => (int) $args['agent_id'] ] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$query->where( [ 'end_date >=' => sanitize_text_field( $args['date_from'] ) ] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$query->where( [ 'start_date <=' => sanitize_text_field( $args['date_to'] ) ] );
		}

		$periods = $query->order_by( 'start_date ASC' )->get_results_as_models();

		return [ 'off_periods' => array_map( [ $this, 'serialize_off_period' ], $periods ) ];
	}
}
