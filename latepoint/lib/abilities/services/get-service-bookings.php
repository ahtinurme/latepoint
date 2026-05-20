<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetServiceBookings extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-service-bookings';
		$this->label       = __( 'Get service bookings', 'latepoint' );
		$this->description = __( 'Returns bookings for a specific service.', 'latepoint' );
		$this->permission  = 'service__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'service_id' => [
					'type'        => 'integer',
					'description' => __( 'Service ID.', 'latepoint' ),
				],
				'date_from'  => [
					'type'   => 'string',
					'format' => 'date',
				],
				'date_to'    => [
					'type'   => 'string',
					'format' => 'date',
				],
				'page'       => [
					'type'    => 'integer',
					'default' => 1,
				],
				'per_page'   => [
					'type'    => 'integer',
					'default' => 20,
				],
			],
			'required'   => [ 'service_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'bookings' => [ 'type' => 'array' ],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$service = new OsServiceModel( (int) $args['service_id'] );
		if ( $service->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = ( new OsBookingModel() )->where( [ 'service_id' => $service->id ] );
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

		return [
			'bookings' => array_map( [ $this, 'serialize_booking' ], $bookings ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	private function serialize_booking( OsBookingModel $b ): array {
		return [
			'id'            => (int) $b->id,
			'status'        => (string) $b->status,
			'customer_id'   => (int) $b->customer_id,
			'agent_id'      => (int) $b->agent_id,
			'service_id'    => (int) $b->service_id,
			'location_id'   => (int) $b->location_id,
			'start_date'    => (string) $b->start_date,
			'start_time'    => (int) $b->start_time,
			'end_time'      => (int) $b->end_time,
			'duration'      => (int) $b->duration,
			'customer_name' => $b->customer ? trim( $b->customer->first_name . ' ' . $b->customer->last_name ) : '',
			'service_name'  => $b->service ? (string) $b->service->name : '',
			'agent_name'    => $b->agent ? trim( $b->agent->first_name . ' ' . $b->agent->last_name ) : '',
			'notes'         => $b->order ? (string) ( $b->order->customer_comment ?? '' ) : '',
		];
	}
}
