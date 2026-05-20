<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetAvailableSlots extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-available-slots';
		$this->label       = __( 'Get available slots', 'latepoint' );
		$this->description = __( 'Returns available booking time slots for a service/agent/location on a given date.', 'latepoint' );
		$this->permission  = '';
		$this->read_only   = true;
	}

	public function check_permission(): bool {
		return true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'service_id'  => [
					'type'        => 'integer',
					'description' => __( 'Service ID.', 'latepoint' ),
				],
				'date'        => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Date to check (Y-m-d).', 'latepoint' ),
				],
				'agent_id'    => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
				'duration'    => [ 'type' => 'integer' ],
			],
			'required'   => [ 'service_id', 'date' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'slots' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
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
		$slots = apply_filters( 'latepoint_get_available_slots', [], $args );
		return [ 'slots' => $slots ];
	}
}
