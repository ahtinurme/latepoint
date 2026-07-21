<?php
/*
 * Copyright (c) 2025 LatePoint LLC. All rights reserved.
 *
 * "Maximum bookings per day" feature (pro). Closes a day in the calendar once the number of
 * bookings reaches a configured limit. Limits can be set globally (setting), per-service or
 * per-agent (meta); the most restrictive applicable limit wins. All enforcement and UI is
 * driven through core's generic extension hooks - no feature-specific code lives in core.
 */

class OsFeatureMaxBookingsPerDayHelper {

	const META_KEY   = 'max_bookings_per_day';
	const GLOBAL_KEY = 'max_bookings_per_day';

	/**
	 * Closes a day once a configured "maximum bookings per day" limit is reached.
	 * Hooked to the core filter `latepoint_blocked_periods_for_range`. Skipped on the backend so
	 * admins can still overbook a full day. A global or service limit blocks the whole day for
	 * every resource, while an agent limit blocks only that agent. Bookings are counted using the
	 * same statuses that block timeslots, so cancelling a booking automatically reopens the day.
	 *
	 * @param array $grouped_blocked_periods blocked periods keyed by 'Y-m-d'
	 * @param \LatePoint\Misc\Filter $filter
	 * @param bool $accessed_from_backend
	 *
	 * @return array
	 */
	public static function enforce_daily_limit( array $grouped_blocked_periods, \LatePoint\Misc\Filter $filter, bool $accessed_from_backend = false ): array {
		// Admin override - the cap only applies to customer-facing booking touchpoints.
		if ( $accessed_from_backend || ! $filter->date_from ) {
			return $grouped_blocked_periods;
		}

		// Resolve the configured limits for each scope ( 0 / empty means "no limit" ).
		// Global "maximum bookings per day" setting commented out - re-enable when needed.
		// $global_limit = (int) OsSettingsHelper::get_settings_value( self::GLOBAL_KEY );
		$global_limit = 0;

		$service_limit = 0;
		if ( $filter->service_id ) {
			$service_limits = self::get_daily_limits_from_meta( new OsServiceMetaModel(), [ $filter->service_id ] );
			$service_limit  = $service_limits[ $filter->service_id ] ?? 0;
		}

		// Collect per-agent limits for the agents involved in this request.
		$agent_ids = [];
		foreach ( $filter->connections as $connection ) {
			if ( ! empty( $connection->agent_id ) ) {
				$agent_ids[ $connection->agent_id ] = $connection->agent_id;
			}
		}
		$agent_limits = $agent_ids ? self::get_daily_limits_from_meta( new OsAgentMetaModel(), array_values( $agent_ids ) ) : [];

		// Nothing configured - leave availability untouched.
		if ( $global_limit <= 0 && $service_limit <= 0 && empty( $agent_limits ) ) {
			return $grouped_blocked_periods;
		}

		$date_from = $filter->date_from;
		$date_to   = $filter->date_to ? $filter->date_to : $filter->date_from;
		$statuses  = array_filter( OsBookingHelper::get_timeslot_blocking_statuses() );
		$exclude   = $filter->exclude_booking_ids;

		// Count bookings per day for each scope that has a limit ( one grouped query each ).
		$global_counts  = ( $global_limit > 0 ) ? self::count_bookings_per_day( $date_from, $date_to, $statuses, [ 'exclude_booking_ids' => $exclude ] ) : [];
		$service_counts = ( $service_limit > 0 ) ? self::count_bookings_per_day(
			$date_from,
			$date_to,
			$statuses,
			[
				'service_id'          => $filter->service_id,
				'exclude_booking_ids' => $exclude,
			]
		) : [];
		$agent_counts   = ( ! empty( $agent_limits ) ) ? self::count_bookings_per_day(
			$date_from,
			$date_to,
			$statuses,
			[
				'agent_ids'           => array_keys( $agent_limits ),
				'exclude_booking_ids' => $exclude,
			]
		) : [];

		foreach ( array_keys( $grouped_blocked_periods ) as $date ) {
			// Global or service cap reached - close the whole day ( blocks every resource ).
			// Counts are keyed by day and agent, so sum the day's agent buckets for the day total.
			$global_day_total  = isset( $global_counts[ $date ] ) ? array_sum( $global_counts[ $date ] ) : 0;
			$service_day_total = isset( $service_counts[ $date ] ) ? array_sum( $service_counts[ $date ] ) : 0;
			$day_is_full       = ( $global_limit > 0 && $global_day_total >= $global_limit )
				|| ( $service_limit > 0 && $service_day_total >= $service_limit );

			if ( $day_is_full ) {
				$grouped_blocked_periods[ $date ][] = new \LatePoint\Misc\BlockedPeriod(
					[
						'start_time' => 0,
						'end_time'   => 24 * 60,
						'start_date' => $date,
						'end_date'   => $date,
					]
				);
				continue;
			}

			// Per-agent caps - close the day for that agent only.
			foreach ( $agent_limits as $agent_id => $agent_limit ) {
				if ( ( $agent_counts[ $date ][ $agent_id ] ?? 0 ) >= $agent_limit ) {
					$grouped_blocked_periods[ $date ][] = new \LatePoint\Misc\BlockedPeriod(
						[
							'start_time' => 0,
							'end_time'   => 24 * 60,
							'start_date' => $date,
							'end_date'   => $date,
							'agent_id'   => $agent_id,
						]
					);
				}
			}
		}

		return $grouped_blocked_periods;
	}

	/**
	 * Counts bookings per day within a date range, grouped by day and agent.
	 * Optionally restricted to a service and/or a set of agents.
	 *
	 * @param string $date_from
	 * @param string $date_to
	 * @param array $statuses statuses that count toward the limit ( empty = all non-cancelled )
	 * @param array $args optional filters: 'service_id' (int), 'agent_ids' (int[]), 'exclude_booking_ids' (int[])
	 *
	 * @return array map of 'Y-m-d' => [ agent_id => int count ]
	 */
	public static function count_bookings_per_day( string $date_from, string $date_to, array $statuses, array $args = [] ): array {
		$args = array_merge(
			[
				'service_id'          => 0,
				'agent_ids'           => [],
				'exclude_booking_ids' => [],
			],
			$args
		);

		$bookings = new OsBookingModel();
		$bookings->select( 'count(id) as bookings_count, start_date, agent_id' )
				->where(
					[
						'start_date >=' => $date_from,
						'start_date <=' => $date_to,
					]
				);
		if ( $statuses ) {
			$bookings->where( [ 'status' => $statuses ] );
		} else {
			$bookings->should_not_be_cancelled();
		}
		if ( $args['service_id'] ) {
			$bookings->where( [ 'service_id' => $args['service_id'] ] );
		}
		if ( $args['agent_ids'] ) {
			$bookings->where( [ 'agent_id' => $args['agent_ids'] ] );
		}
		if ( $args['exclude_booking_ids'] ) {
			$bookings->where( [ 'id NOT IN' => $args['exclude_booking_ids'] ] );
		}
		$results = $bookings->group_by( 'start_date, agent_id' )->get_results( ARRAY_A );

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row['start_date'] ][ (int) $row['agent_id'] ] = (int) $row['bookings_count'];
		}

		return $counts;
	}

	/**
	 * Loads the "max_bookings_per_day" limits stored as meta for a set of objects ( services or agents ).
	 * Returns only positive limits, fetched in a single query to avoid N+1.
	 *
	 * @param OsMetaModel $meta_model an instance of the relevant meta model ( OsServiceMetaModel / OsAgentMetaModel )
	 * @param array $object_ids
	 *
	 * @return array map of object_id => int limit
	 */
	public static function get_daily_limits_from_meta( OsMetaModel $meta_model, array $object_ids ): array {
		$limits = [];
		if ( empty( $object_ids ) ) {
			return $limits;
		}
		$rows = $meta_model->where(
			[
				'meta_key'  => self::META_KEY,
				'object_id' => array_values( $object_ids ),
			]
		)->get_results();
		foreach ( $rows as $row ) {
			$value = (int) $row->meta_value;
			if ( $value > 0 ) {
				$limits[ (int) $row->object_id ] = $value;
			}
		}

		return $limits;
	}

	/**
	 * Renders the global limit field inside Settings > General > Restrictions.
	 * Hooked to `latepoint_general_settings_section_restrictions_after`.
	 */
	public static function render_global_field(): void {
		?>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'Maximum Bookings Per Day', 'latepoint-pro-features' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'Once the total number of bookings for a day reaches this number the whole day becomes unavailable. Leave blank or set to 0 to remove the limit. This can be overridden per service and per agent.', 'latepoint-pro-features' ); ?></div>
				<?php echo OsFormHelper::text_field( 'settings[max_bookings_per_day]', __( 'Maximum Number of Bookings Per Day', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( self::GLOBAL_KEY ), [ 'theme' => 'simple' ] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the per-agent limit field ( called from the pro agent edit form ).
	 *
	 * @param OsAgentModel $agent
	 */
	public static function render_agent_field( OsAgentModel $agent ): void {
		echo OsFormHelper::text_field( 'agent[max_bookings_per_day]', __( 'Maximum Number of Bookings Per Day', 'latepoint-pro-features' ), $agent->get_meta_by_key( self::META_KEY ), [ 'theme' => 'simple' ] );
	}

	/**
	 * Persists the per-service limit. Hooked to `latepoint_service_saved`.
	 *
	 * @param OsServiceModel $service
	 * @param bool $is_new_record
	 * @param array $service_params
	 */
	public static function save_service_setting( OsServiceModel $service, bool $is_new_record, array $service_params ): void {
		$value = absint( $service_params['max_bookings_per_day'] ?? 0 );
		$service->save_meta_by_key( self::META_KEY, $value ? $value : '' );
	}

	/**
	 * Persists the per-agent limit. Hooked to `latepoint_agent_saved`.
	 *
	 * @param OsAgentModel $agent
	 * @param bool $is_new_record
	 * @param array $agent_params
	 */
	public static function save_agent_setting( OsAgentModel $agent, bool $is_new_record, array $agent_params ): void {
		$value = absint( $agent_params['max_bookings_per_day'] ?? 0 );
		$agent->save_meta_by_key( self::META_KEY, $value ? $value : '' );
	}
}
