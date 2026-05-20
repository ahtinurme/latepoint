<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCheckSlotAvailability extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/check-slot-availability';
		$this->label       = __( 'Check slot availability', 'latepoint' );
		$this->description = __( 'Checks whether a specific time slot has no conflicting bookings.', 'latepoint' );
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
				'service_id'  => [ 'type' => 'integer' ],
				'date'        => [
					'type'   => 'string',
					'format' => 'date',
				],
				'start_time'  => [
					'type'        => 'integer',
					'description' => __( 'Start time (minutes from midnight).', 'latepoint' ),
				],
				'agent_id'    => [ 'type' => 'integer' ],
				'location_id' => [ 'type' => 'integer' ],
			],
			'required'   => [ 'service_id', 'date', 'start_time' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'available' => [ 'type' => 'boolean' ],
				'conflicts' => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
			],
		];
	}

	public function execute( array $args ) {
		$service_id = (int) $args['service_id'];
		$date       = sanitize_text_field( $args['date'] );
		$start_time = (int) $args['start_time'];
		$agent_id   = ! empty( $args['agent_id'] ) ? (int) $args['agent_id'] : 0;

		if ( $service_id ) {
			$service  = new OsServiceModel( $service_id );
			$duration = ( ! $service->is_new_record() && $service->duration ) ? (int) $service->duration : 60;
		} else {
			$duration = 60;
		}
		$end_time = $start_time + $duration;

		$query = new OsBookingModel();
		$query->where(
			[
				'start_date' => $date,
				'status'     => LATEPOINT_BOOKING_STATUS_APPROVED,
			] 
		);
		if ( $agent_id ) {
			$query->where( [ 'agent_id' => $agent_id ] );
		}
		$query->where( [ 'start_time <' => $end_time ] );
		$query->where( [ 'end_time >' => $start_time ] );

		$count     = $query->count();
		$available = ( $count === 0 );

		return [
			'available' => $available,
			'conflicts' => [],
		];
	}
}
