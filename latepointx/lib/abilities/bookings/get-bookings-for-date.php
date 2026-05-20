<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityGetBookingsForDate extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-bookings-for-date';
		$this->label       = __( 'Get bookings for date', 'latepoint' );
		$this->description = __( 'Returns all bookings on a specific calendar date.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		$filters = $this->booking_filters_schema();
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'date' => [
						'type'        => 'string',
						'format'      => 'date',
						'description' => __( 'Calendar date (Y-m-d).', 'latepoint' ),
					],
				],
				array_intersect_key( $filters, array_flip( [ 'agent_id', 'service_id', 'location_id', 'status' ] ) )
			),
			'required'   => [ 'date' ],
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
			],
		];
	}

	public function execute( array $args ) {
		$date  = sanitize_text_field( $args['date'] );
		$query = new OsBookingModel();
		$query->where( [ 'start_date' => $date ] );
		$query = $this->apply_filters( $query, array_diff_key( $args, [ 'date' => '' ] ) );
		$query->order_by( 'start_time ASC' );

		$bookings = $query->get_results_as_models();
		$bookings = is_array( $bookings ) ? $bookings : ( $bookings ? [ $bookings ] : [] );

		return [
			'bookings' => array_map( [ $this, 'serialize_booking' ], $bookings ),
			'total'    => count( $bookings ),
		];
	}
}
