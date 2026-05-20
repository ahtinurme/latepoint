<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityGetUpcomingBookings extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-upcoming-bookings';
		$this->label       = __( 'Get upcoming bookings', 'latepoint' );
		$this->description = __( 'Returns upcoming bookings from today, with optional filters.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		$filters = $this->booking_filters_schema();
		return [
			'type'       => 'object',
			'properties' => array_merge(
				array_intersect_key( $filters, array_flip( [ 'agent_id', 'service_id', 'location_id', 'customer_id', 'status' ] ) ),
				self::pagination()
			),
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'bookings' => [
					'type'  => 'array',
					'items' => $this->booking_output_schema(),
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsBookingModel();
		$query->where( [ 'start_date >=' => wp_date( 'Y-m-d' ) ] );
		$query = $this->apply_filters( $query, $args );
		$total = ( clone $query )->count();

		$bookings = $query
			->order_by( 'start_date ASC, start_time ASC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		$bookings = is_array( $bookings ) ? $bookings : ( $bookings ? [ $bookings ] : [] );

		return [
			'bookings' => array_map( [ $this, 'serialize_booking' ], $bookings ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
