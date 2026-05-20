<?php
/**
 * Abstract base for booking abilities — shared serialize & filter helpers.
 *
 * @package LatePoint\Abilities
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractBookingAbility extends LatePointAbstractAbility {

	public function serialize_booking( OsBookingModel $b ): array {
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

	protected function apply_filters( OsBookingModel $query, array $input ): OsBookingModel {
		if ( ! empty( $input['status'] ) ) {
			$query->where( [ 'status' => $input['status'] ] );
		}
		if ( ! empty( $input['agent_id'] ) ) {
			$query->where( [ 'agent_id' => (int) $input['agent_id'] ] );
		}
		if ( ! empty( $input['service_id'] ) ) {
			$query->where( [ 'service_id' => (int) $input['service_id'] ] );
		}
		if ( ! empty( $input['location_id'] ) ) {
			$query->where( [ 'location_id' => (int) $input['location_id'] ] );
		}
		if ( ! empty( $input['customer_id'] ) ) {
			$query->where( [ 'customer_id' => (int) $input['customer_id'] ] );
		}
		if ( ! empty( $input['date_from'] ) ) {
			$query->where( [ 'start_date >=' => sanitize_text_field( $input['date_from'] ) ] );
		}
		if ( ! empty( $input['date_to'] ) ) {
			$query->where( [ 'start_date <=' => sanitize_text_field( $input['date_to'] ) ] );
		}
		return $query;
	}

	protected function booking_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'            => [ 'type' => 'integer' ],
				'status'        => [ 'type' => 'string' ],
				'customer_id'   => [ 'type' => 'integer' ],
				'agent_id'      => [ 'type' => 'integer' ],
				'service_id'    => [ 'type' => 'integer' ],
				'location_id'   => [ 'type' => 'integer' ],
				'start_date'    => [
					'type'   => 'string',
					'format' => 'date',
				],
				'start_time'    => [
					'type'        => 'integer',
					'description' => __( 'Minutes from midnight.', 'latepoint' ),
				],
				'end_time'      => [
					'type'        => 'integer',
					'description' => __( 'Minutes from midnight.', 'latepoint' ),
				],
				'duration'      => [
					'type'        => 'integer',
					'description' => __( 'Duration in minutes.', 'latepoint' ),
				],
				'customer_name' => [ 'type' => 'string' ],
				'service_name'  => [ 'type' => 'string' ],
				'agent_name'    => [ 'type' => 'string' ],
				'notes'         => [ 'type' => 'string' ],
				'updated_at'    => [
					'type'   => 'string',
					'format' => 'date-time',
				],
				'created_at'    => [
					'type'   => 'string',
					'format' => 'date-time',
				],
			],
		];
	}

	protected function booking_filters_schema(): array {
		return [
			'status'      => [
				'type'        => 'string',
				'enum'        => [ 'approved', 'pending', 'cancelled', 'no_show', 'completed' ],
				'description' => __( 'Filter by booking status.', 'latepoint' ),
			],
			'agent_id'    => [
				'type'        => 'integer',
				'description' => __( 'Filter by agent ID.', 'latepoint' ),
			],
			'service_id'  => [
				'type'        => 'integer',
				'description' => __( 'Filter by service ID.', 'latepoint' ),
			],
			'location_id' => [
				'type'        => 'integer',
				'description' => __( 'Filter by location ID.', 'latepoint' ),
			],
			'customer_id' => [
				'type'        => 'integer',
				'description' => __( 'Filter by customer ID.', 'latepoint' ),
			],
			'date_from'   => [
				'type'        => 'string',
				'format'      => 'date',
				'description' => __( 'Start date filter (Y-m-d).', 'latepoint' ),
			],
			'date_to'     => [
				'type'        => 'string',
				'format'      => 'date',
				'description' => __( 'End date filter (Y-m-d).', 'latepoint' ),
			],
		];
	}
}
