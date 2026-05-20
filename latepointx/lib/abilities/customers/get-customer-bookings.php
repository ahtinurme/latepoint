<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetCustomerBookings extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-customer-bookings';
		$this->label       = __( 'Get customer bookings', 'latepoint' );
		$this->description = __( 'Returns bookings for a specific customer, with optional future/past/status filter.', 'latepoint' );
		$this->permission  = 'customer__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				self::pagination(),
				[
					'customer_id' => [
						'type'        => 'integer',
						'description' => __( 'Customer ID.', 'latepoint' ),
					],
					'status'      => [
						'type'        => 'string',
						'description' => __( 'Filter by booking status.', 'latepoint' ),
					],
					'time_scope'  => [
						'type'        => 'string',
						'enum'        => [ 'upcoming', 'past', 'all' ],
						'default'     => 'all',
						'description' => __( 'Time scope filter.', 'latepoint' ),
					],
				]
			),
			'required'   => [ 'customer_id' ],
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
		$customer = new OsCustomerModel( (int) $args['customer_id'] );
		if ( $customer->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Customer not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$scope    = $args['time_scope'] ?? 'all';

		$query = ( new OsBookingModel() )->where( [ 'customer_id' => $customer->id ] );

		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		if ( $scope === 'upcoming' ) {
			$query->where( [ 'start_date >=' => wp_date( 'Y-m-d' ) ] );
		} elseif ( $scope === 'past' ) {
			$query->where( [ 'start_date <' => wp_date( 'Y-m-d' ) ] );
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
