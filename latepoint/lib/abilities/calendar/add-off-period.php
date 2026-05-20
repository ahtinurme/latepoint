<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityAddOffPeriod extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/add-off-period';
		$this->label       = __( 'Add off period', 'latepoint' );
		$this->description = __( 'Creates a new off/blocked period during which an agent or the entire business will not accept bookings.', 'latepoint' );
		$this->permission  = 'agent__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'       => [
					'type'        => 'string',
					'description' => __( 'Label for the off period.', 'latepoint' ),
				],
				'start_date' => [
					'type'   => 'string',
					'format' => 'date',
				],
				'end_date'   => [
					'type'   => 'string',
					'format' => 'date',
				],
				'agent_id'   => [ 'type' => 'integer' ],
			],
			'required'   => [ 'name', 'start_date', 'end_date' ],
		];
	}

	public function get_output_schema(): array {
		return $this->off_period_output_schema();
	}

	public function execute( array $args ) {
		$period             = new OsOffPeriodModel();
		$period->summary    = sanitize_text_field( $args['name'] );
		$period->start_date = sanitize_text_field( $args['start_date'] );
		$period->end_date   = sanitize_text_field( $args['end_date'] );

		if ( ! empty( $args['agent_id'] ) ) {
			$period->agent_id = (int) $args['agent_id'];
		}

		if ( ! $period->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create off period.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $period->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_off_period( new OsOffPeriodModel( $period->id ) );
	}
}
