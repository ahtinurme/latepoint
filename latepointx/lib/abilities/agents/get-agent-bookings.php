<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetAgentBookings extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-agent-bookings';
		$this->label       = __( 'Get agent bookings', 'latepoint' );
		$this->description = __( 'Returns bookings assigned to a specific agent.', 'latepoint' );
		$this->permission  = 'agent__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				self::pagination(),
				[
					'agent_id'  => [
						'type'        => 'integer',
						'description' => __( 'Agent ID.', 'latepoint' ),
					],
					'date_from' => [
						'type'   => 'string',
						'format' => 'date',
					],
					'date_to'   => [
						'type'   => 'string',
						'format' => 'date',
					],
					'status'    => [ 'type' => 'string' ],
				]
			),
			'required'   => [ 'agent_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'bookings' => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$agent = new OsAgentModel( (int) $args['agent_id'] );
		if ( $agent->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Agent not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = ( new OsBookingModel() )->where( [ 'agent_id' => $agent->id ] );
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$query->where( [ 'start_date >=' => sanitize_text_field( $args['date_from'] ) ] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$query->where( [ 'start_date <=' => sanitize_text_field( $args['date_to'] ) ] );
		}

		$bookings = ( clone $query )
			->order_by( 'start_date ASC, start_time ASC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		$total    = $query->count();

		$booking_serializer = new LatePointAbilityListBookings();
		return [
			'bookings' => array_map( [ $booking_serializer, 'serialize_booking' ], $bookings ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
