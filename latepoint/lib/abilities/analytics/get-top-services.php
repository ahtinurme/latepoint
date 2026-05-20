<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetTopServices extends LatePointAbstractAnalyticsAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-top-services';
		$this->label       = __( 'Get top services', 'latepoint' );
		$this->description = __( 'Returns the most-booked services with booking counts and revenue for a period.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'date_from' => [
					'type'   => 'string',
					'format' => 'date',
				],
				'date_to'   => [
					'type'   => 'string',
					'format' => 'date',
				],
				'limit'     => [
					'type'    => 'integer',
					'default' => 5,
				],
			],
			'required'   => [ 'date_from', 'date_to' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'services' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'service_id'   => [ 'type' => 'integer' ],
							'service_name' => [ 'type' => 'string' ],
							'count'        => [ 'type' => 'integer' ],
							'revenue'      => [ 'type' => 'number' ],
						],
					],
				],
			],
		];
	}

	public function execute( array $args ) {
		global $wpdb;

		$date_from      = sanitize_text_field( $args['date_from'] );
		$date_to        = sanitize_text_field( $args['date_to'] );
		$limit          = ! empty( $args['limit'] ) ? max( 1, min( 50, (int) $args['limit'] ) ) : 5;
		$bookings_table = LATEPOINT_TABLE_BOOKINGS;
		$services_table = LATEPOINT_TABLE_SERVICES;

		$order_items_table = LATEPOINT_TABLE_ORDER_ITEMS;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.service_id, s.name AS service_name,
				        COUNT(*) AS booking_count,
				        SUM(oi.subtotal) AS total_revenue
				 FROM {$bookings_table} b
				 LEFT JOIN {$services_table} s ON s.id = b.service_id
				 LEFT JOIN {$order_items_table} oi ON oi.id = b.order_item_id
				 WHERE b.start_date >= %s AND b.start_date <= %s
				   AND b.status != %s
				 GROUP BY b.service_id
				 ORDER BY booking_count DESC
				 LIMIT %d",
				$date_from,
				$date_to,
				LATEPOINT_BOOKING_STATUS_CANCELLED,
				$limit
			),
			ARRAY_A
		);

		$services = array_map(
			fn( $row ) => [
				'service_id'   => (int) $row['service_id'],
				'service_name' => $row['service_name'] ?? '',
				'count'        => (int) $row['booking_count'],
				'revenue'      => (float) ( $row['total_revenue'] ?? 0 ),
			],
			$rows ?? []
		);

		return [ 'services' => $services ];
	}
}
