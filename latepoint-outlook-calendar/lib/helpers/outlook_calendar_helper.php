<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Outlook calendar helper.
 */
class OsOutlookCalendarHelper {
	/**
	 * Check if Outlook Calendar integration is enabled
	 */
	public static function is_enabled() {
		return OsSettingsHelper::is_on( 'enable_outlook_calendar' );
	}

	/**
	 * Check if Outlook Teams is enabled for service
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function is_outlook_teams_enabled_for_service( $service_id ) {
		return OsMetaHelper::get_service_meta_by_key( 'enable_outlook_teams', $service_id ) === 'on';
	}

	/**
	 * Check if customer should be included as attendee
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function should_customer_be_included_as_attendee( $service_id ): bool {
		return OsMetaHelper::get_service_meta_by_key( 'add_customer_to_outlook_event', $service_id ) === 'on';
	}

	/**
	 * Check if agent should be included as attendee
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function should_agent_be_included_as_attendee( $service_id ): bool {
		return OsMetaHelper::get_service_meta_by_key( 'add_agent_to_outlook_event', $service_id ) === 'on';
	}

	/**
	 * Get Outlook Teams conference URL for booking ID
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public static function get_outlook_teams_conference_url_for_booking_id( $booking_id ): string {
		$booking = new OsBookingModel( $booking_id );
		return $booking->get_meta_by_key( 'outlook_calendar_event_meeting_url' );
	}

	/**
	 * Check if integration is done through relay (always true for Outlook)
	 */
	public static function is_integrated_through_relay() {
		return true; // Outlook always uses relay.
	}

	/**
	 * Check if the given service is in shared meeting mode.
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	private static function is_shared_meeting_mode( int $service_id ): bool {
		return method_exists( 'OsFeatureGroupBookingsHelper', 'is_shared_meeting_mode' )
			&& OsFeatureGroupBookingsHelper::is_shared_meeting_mode( $service_id );
	}

	/**
	 * Check if an agent is connected to Outlook Calendar
	 *
	 * @param int $agent_id Agent ID.
	 * @return bool
	 */
	public static function is_agent_connected_to_outlook( $agent_id ) {
		return self::get_access_token_for_agent( $agent_id ) !== false;
	}

	/**
	 * Get access token for an agent
	 *
	 * @param int $agent_id Agent ID.
	 * @return array|false
	 */
	public static function get_access_token_for_agent( $agent_id ) {
		$agent        = new OsAgentModel( $agent_id );
		$access_token = $agent->get_meta_by_key( 'outlook_cal_access_token_relay', false );
		return $access_token ? json_decode( $access_token, true ) : false;
	}

	/**
	 * Get selected calendar ID for pushing bookings
	 *
	 * @param int $agent_id Agent ID.
	 * @return string
	 */
	public static function get_selected_calendar_id_for_push( int $agent_id ): string {
		return OsMetaHelper::get_agent_meta_by_key( 'outlook_cal_selected_calendar_id_for_push', $agent_id );
	}

	/**
	 * Get selected calendar IDs for pulling events
	 *
	 * @param int $agent_id Agent ID.
	 * @return string
	 */
	public static function get_selected_calendar_ids_for_pull( int $agent_id ): string {
		return OsMetaHelper::get_agent_meta_by_key( 'outlook_cal_selected_calendar_ids_for_pull', $agent_id );
	}

	/**
	 * Set selected calendar ID for pushing bookings
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool
	 */
	public static function set_selected_calendar_id_for_push( $calendar_id, $agent_id ) {
		return OsMetaHelper::save_agent_meta_by_key( 'outlook_cal_selected_calendar_id_for_push', $calendar_id, $agent_id );
	}

	/**
	 * Set selected calendar IDs for pulling events
	 *
	 * @param string $calendar_ids Calendar IDs.
	 * @param int    $agent_id Agent ID.
	 * @return bool
	 */
	public static function set_selected_calendar_ids_for_pull( $calendar_ids, $agent_id ) {
		return OsMetaHelper::save_agent_meta_by_key( 'outlook_cal_selected_calendar_ids_for_pull', $calendar_ids, $agent_id );
	}

	/**
	 * Get list of calendars for an agent
	 *
	 * @param int $agent_id Agent ID.
	 * @return array
	 */
	public static function get_list_of_calendars( $agent_id ) {
		$calendars = array();
		try {
			$connection_data = OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $agent_id );
			$calendars       = OsOutlookCalendarRelayHelper::get_list_of_calendars( $connection_data );
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error getting list of Outlook calendars: ' . $e->getMessage(), 'outlook_calendar_error' );
		}
		usort(
			$calendars,
			static function ( $item1, $item2 ) {
				return $item1['name'] <=> $item2['name'];
			}
		);
		return $calendars;
	}

	/**
	 * Get calendar name by ID
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param int    $agent_id Agent ID.
	 * @return string|false
	 */
	public static function get_calendar_name_by_id( $calendar_id, $agent_id ) {
		$calendars = self::get_list_of_calendars( $agent_id );
		if ( ! empty( $calendars ) ) {
			foreach ( $calendars as $calendar ) {
				if ( $calendar['id'] === $calendar_id ) {
					return $calendar['name'];
				}
			}
		}
		return false;
	}

	/**
	 * Get event title template
	 */
	public static function get_event_title_template() {
		return OsSettingsHelper::get_settings_value( 'outlook_calendar_event_summary_template', '{{service_name}}' );
	}

	/**
	 * Get event description template
	 */
	public static function get_event_description_template() {
		return OsSettingsHelper::get_settings_value( 'outlook_calendar_event_description_template', 'Customer Name: <strong>{{customer_full_name}}</strong><br/>Phone: <strong>{{customer_phone}}</strong>' );
	}

	/**
	 * Remove calendar for push
	 *
	 * @param int $agent_id Agent ID.
	 */
	public static function remove_calendar_for_push( $agent_id ) {
		OsMetaHelper::delete_agent_meta_by_key( 'outlook_cal_selected_calendar_id_for_push', $agent_id );
	}

	/**
	 * Remove calendar for pull
	 *
	 * @param string $calendar_id_to_remove Calendar ID to remove.
	 * @param int    $agent_id Agent ID.
	 * @return bool
	 */
	public static function remove_calendar_for_pull( $calendar_id_to_remove, $agent_id ) {
		$current_calendar_ids     = self::get_selected_calendar_ids_for_pull( $agent_id );
		$current_calendar_ids_arr = $current_calendar_ids ? explode( ',', $current_calendar_ids ) : array();
		$remaining_calendar_ids   = array();
		foreach ( $current_calendar_ids_arr as $current_calendar_id ) {
			if ( $current_calendar_id !== $calendar_id_to_remove ) {
				$remaining_calendar_ids[] = $current_calendar_id;
			}
		}
		self::stop_subscription( $agent_id, $calendar_id_to_remove );
		if ( empty( $remaining_calendar_ids ) ) {
			return OsMetaHelper::delete_agent_meta_by_key( 'outlook_cal_selected_calendar_ids_for_pull', $agent_id );
		}
			return self::set_selected_calendar_ids_for_pull( implode( ',', $remaining_calendar_ids ), $agent_id );
	}

	/**
	 * Add calendar for pull
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool
	 */
	public static function add_calendar_for_pull( $calendar_id, $agent_id ) {
		$current_calendar_ids     = self::get_selected_calendar_ids_for_pull( $agent_id );
		$current_calendar_ids_arr = $current_calendar_ids ? explode( ',', $current_calendar_ids ) : array();
		if ( ! in_array( $calendar_id, $current_calendar_ids_arr, true ) ) {
			$current_calendar_ids_arr[] = $calendar_id;
			return self::set_selected_calendar_ids_for_pull( implode( ',', $current_calendar_ids_arr ), $agent_id );
		}
		return true;
	}

	/**
	 * Clear calendar connection info from agent
	 *
	 * @param int   $agent_id Agent ID.
	 * @param array $what_to_keep Keys to keep.
	 */
	public static function clear_calendar_connection_info_from_agent( int $agent_id, array $what_to_keep = array() ) {
		$agent = new OsAgentModel( $agent_id );
		if ( $agent ) {
			if ( ! in_array( 'token', $what_to_keep, true ) ) {
				$agent->delete_meta_by_key( 'outlook_cal_access_token_relay' );
				if ( self::is_integrated_through_relay() ) {
					$connection_id = $agent->get_meta_by_key( 'outlook_cal_connection_id_relay' );
					if ( $connection_id ) {
						OsOutlookCalendarRelayHelper::remove_connection( $connection_id );
					}
				}
			}
			if ( ! in_array( 'calendar_id_push', $what_to_keep, true ) ) {
				$agent->delete_meta_by_key( 'outlook_cal_selected_calendar_id_for_push' );
			}
			if ( ! in_array( 'calendar_ids_pull', $what_to_keep, true ) ) {
				$agent->delete_meta_by_key( 'outlook_cal_selected_calendar_ids_for_pull' );
			}
		}
	}

	/**
	 * Check if subscription is being watched
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param int    $agent_id Agent ID.
	 * @param array  $agent_subscriptions_arr Agent subscriptions.
	 * @return array|false
	 */
	public static function is_calendar_being_watched( $calendar_id, $agent_id, $agent_subscriptions_arr = array() ) {
		if ( empty( $agent_subscriptions_arr ) ) {
			$agent_subscriptions     = OsMetaHelper::get_agent_meta_by_key( 'outlook_cal_agent_subscriptions', $agent_id, '' );
			$agent_subscriptions_arr = json_decode( $agent_subscriptions, true );
		}
		if ( $agent_subscriptions_arr ) {
			foreach ( $agent_subscriptions_arr as $subscription ) {
				if ( $subscription['calendar_id'] === $calendar_id ) {
					return $subscription;
				}
			}
		}
		return false;
	}

	/**
	 * Start subscription (webhook) for calendar
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $calendar_id Calendar ID.
	 */
	public static function start_subscription( $agent_id, $calendar_id ) {
		$agent = new OsAgentModel( $agent_id );
		if ( ! $agent->id ) {
			return false;
		}

		$agent_subscriptions     = $agent->get_meta_by_key( 'outlook_cal_agent_subscriptions' );
		$agent_subscriptions_arr = json_decode( $agent_subscriptions, true );
		$agent_subscription      = false;

		if ( $agent_subscriptions_arr ) {
			foreach ( $agent_subscriptions_arr as $subscription ) {
				if ( $subscription['calendar_id'] === $calendar_id ) {
					$agent_subscription = true;
					break;
				}
			}
		}

		if ( ! $agent_subscription ) {
			$new_subscription = OsOutlookCalendarRelayHelper::start_subscription( $agent_id, $calendar_id, OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $agent ) );
			if ( $new_subscription ) {
				$agent_subscriptions_arr[] = $new_subscription;
				$agent->save_meta_by_key( 'outlook_cal_agent_subscriptions', wp_json_encode( $agent_subscriptions_arr ) );
			}
		}
	}

	/**
	 * Stop subscription (webhook) for calendar
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $calendar_id Calendar ID.
	 * @return bool|false
	 */
	public static function stop_subscription( $agent_id, $calendar_id ) {
		$agent = new OsAgentModel( $agent_id );
		if ( ! $agent->id ) {
			return false;
		}

		$agent_subscriptions     = $agent->get_meta_by_key( 'outlook_cal_agent_subscriptions' );
		$agent_subscriptions_arr = json_decode( $agent_subscriptions, true );
		$agent_subscription      = false;

		if ( $agent_subscriptions_arr ) {
			foreach ( $agent_subscriptions_arr as $subscription ) {
				if ( $subscription['calendar_id'] === $calendar_id ) {
					$agent_subscription = $subscription;
					break;
				}
			}
		}

		if ( ! $agent_subscription ) {
			return false;
		}

		OsOutlookCalendarRelayHelper::stop_subscription( $agent_subscription['id'], OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $agent ) );

		$updated_agent_subscriptions_arr = array();
		foreach ( $agent_subscriptions_arr as $subscription ) {
			if ( $subscription['calendar_id'] !== $calendar_id ) {
				$updated_agent_subscriptions_arr[] = $subscription;
			}
		}

		if ( $updated_agent_subscriptions_arr ) {
			$agent->save_meta_by_key( 'outlook_cal_agent_subscriptions', wp_json_encode( $updated_agent_subscriptions_arr ) );
		} else {
			$agent->delete_meta_by_key( 'outlook_cal_agent_subscriptions' );
		}

		OsActivitiesHelper::create_activity(
			array(
				'agent_id'        => $agent->id,
				'code'            => 'outlook_calendar_subscription_stopped',
				'description'     => array( 'calendar_id' => $calendar_id ),
				'subscription_id' => $agent_subscription['id'],
			)
		);

		return true;
	}

	/**
	 * Refresh subscription (webhook) for calendar
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $calendar_id Calendar ID.
	 */
	public static function refresh_subscription( $agent_id, $calendar_id ) {
		self::stop_subscription( $agent_id, $calendar_id );
		self::start_subscription( $agent_id, $calendar_id );
	}

	/**
	 * Get events for a specific date
	 *
	 * @param string          $target_date Target date.
	 * @param int|array|false $agent_id Agent ID or IDs.
	 * @return array
	 */
	public static function get_events_for_date( $target_date, $agent_id = false ) {
		$events_model = new OsOutlookCalendarEventModel();

		if ( ! OsTimeHelper::is_valid_date( $target_date ) ) {
			return array();
		}

		$target_date = OsWpDateTime::CreateFromFormat( 'Y-m-d', $target_date );
		if ( ! $target_date ) {
			return array();
		}

		$weekday = OsTimeHelper::get_db_weekday_by_number( $target_date->format( 'N' ) );
		$events_model->escape_by_ref( $weekday );
		$weekday_relative = ceil( $target_date->format( 'j' ) / 7 ) . $weekday;

		if ( $target_date->format( 't' ) - $target_date->format( 'j' ) < 7 ) {
			$last_weekday_query = " OR (`weekday` = '-1{$weekday}') ";
		} else {
			$last_weekday_query = '';
		}

		// Clean the date.
		$formatted_date = $target_date->format( 'Y-m-d' );
		$events_model->escape_by_ref( $formatted_date );

		$query = 'SELECT events.start_date, events.end_date, events.start_time, events.end_time, events.id, events.summary, agent_id FROM ' . LATEPOINT_TABLE_OUTLOOK_EVENTS . ' as events
              LEFT JOIN ' . LATEPOINT_TABLE_OUTLOOK_RECURRENCES . " as recs ON events.id = recs.lp_event_id
              WHERE
                (`start_date` = '{$formatted_date}'
                  OR (`start_date` <= '{$formatted_date}' && `end_date` >= '{$formatted_date}')
                  OR (`frequency` = 'daily'
                    AND (DATEDIFF('{$formatted_date}', `start_date`) % `interval`) = 0)
                    AND (`count` IS NULL OR FLOOR(DATEDIFF('{$formatted_date}', `start_date`) / `interval`) < `count`)
                  OR (`frequency` = 'weekly'
                    AND `weekday` = '{$weekday}'
                    AND ((FLOOR(DATEDIFF('{$formatted_date}', `start_date`)/7) % `interval`) = 0)
                    AND (`count` IS NULL OR FLOOR(FLOOR(DATEDIFF('{$formatted_date}', `start_date`)/7) / `interval`) < `count`))
                  OR (`frequency` = 'monthly'
                    AND (((DAYOFMONTH(`start_date`) = DAYOFMONTH('{$formatted_date}') AND (`weekday` = '{$weekday_relative}' OR `weekday` IS NULL OR `weekday` = '')) OR (`weekday` = '{$weekday_relative}') {$last_weekday_query})
                    AND (PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM '{$formatted_date}'), EXTRACT(YEAR_MONTH FROM `start_date`)) % `interval`) = 0)
                    AND (`count` IS NULL OR FLOOR(PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM '{$formatted_date}'), EXTRACT(YEAR_MONTH FROM `start_date`)) / `interval`) < `count`))
                  OR (`frequency` = 'yearly'
                    AND date_format(`start_date`, '%m%d') = date_format('{$formatted_date}', '%m%d')
                    AND ((EXTRACT(YEAR FROM '{$formatted_date}') - EXTRACT(YEAR FROM `start_date`)) % `interval` = 0)
                    AND (`count` IS NULL OR FLOOR(EXTRACT(YEAR FROM '{$formatted_date}') - EXTRACT(YEAR FROM `start_date`) / `interval`) < `count`))
                )AND (`start_date` <= '{$formatted_date}' AND (`until` > '{$formatted_date}' OR `until` IS NULL))";

		if ( $agent_id ) {
			if ( is_array( $agent_id ) ) {
				$agent_ids = implode( ',', $agent_id );
				$query    .= " AND agent_id IN ({$agent_ids})";
			} else {
				$query .= " AND agent_id = {$agent_id}";
			}
		}

		$events = $events_model->get_query_results( $query );

		$clean_events = array();

		// Clean up multi-day events.
		// For example, if event spans multiple days we need to create separate events for each of those days.
		foreach ( $events as $event ) {
			if ( $event->start_date !== $event->end_date && $event->start_date < $event->end_date ) {
				$split_event = clone $event;
				if ( $event->start_date !== $target_date->format( 'Y-m-d' ) ) {
					$split_event->start_time = '0';
				}
				if ( $event->end_date !== $target_date->format( 'Y-m-d' ) ) {
					$split_event->end_time = '1439';
				}
				$split_event->start_date = $target_date;
				$split_event->end_date   = $target_date;
				$clean_events[]          = $split_event;
			} else {
				$clean_events[] = $event;
			}
		}

		return $clean_events;
	}

	/**
	 * Check if event is full day
	 *
	 * @param int $start_time Start time in minutes.
	 * @param int $end_time End time in minutes.
	 * @return bool
	 */
	public static function is_full_day_event( $start_time, $end_time ) {
		$start_time = (int) $start_time;
		$end_time   = (int) $end_time;
		return $start_time === 0 && $end_time === 1439;
	}

	/**
	 * Delete booking from Outlook Calendar
	 *
	 * @param int       $booking_id Booking ID.
	 * @param int|false $custom_agent_id Custom agent ID.
	 * @return bool
	 */
	public static function delete_booking_from_outlook( $booking_id, $custom_agent_id = false ) {
		$booking = new OsBookingModel();
		if ( ! $booking->load_by_id( $booking_id ) ) {
			return true;
		}

		$agent_id         = $custom_agent_id !== false ? $custom_agent_id : $booking->agent_id;
		$calendar_id      = self::get_selected_calendar_id_for_push( $agent_id );
		$outlook_event_id = $booking->get_meta_by_key( 'outlook_calendar_event_id', false );

		if ( ! $outlook_event_id ) {
			return true;
		}

		// In shared meeting mode, if other group bookings share this Outlook event,
		// only clear this booking's meta — preserve the event for the remaining bookings.
		if ( self::is_shared_meeting_mode( $booking->service_id ) ) {
			$meta            = new OsBookingMetaModel();
			$shared_bookings = $meta->where(
				array(
					'meta_key'     => 'outlook_calendar_event_id',
					'meta_value'   => $outlook_event_id,
					'object_id !=' => $booking_id,
				)
			)->get_results_as_models();
			if ( ! empty( $shared_bookings ) ) {
				$booking->delete_meta_by_key( 'outlook_calendar_event_id' );
				$booking->delete_meta_by_key( 'outlook_calendar_event_meeting_url' );
				return true;
			}
		}

		OsOutlookCalendarRelayHelper::delete_booking_from_outlook( $calendar_id, OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $agent_id ), $outlook_event_id );
		$booking->delete_meta_by_key( 'outlook_calendar_event_id' );
		$booking->delete_meta_by_key( 'outlook_calendar_event_meeting_url' );

		return true;
	}

	/**
	 * Create or update booking in Outlook Calendar
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public static function create_or_update_booking_in_outlook( $booking_id ) {
		$booking = new OsBookingModel();
		if ( ! $booking->load_by_id( $booking_id ) ) {
			return false;
		}

		$outlook_event_id = $booking->get_meta_by_key( 'outlook_calendar_event_id', false );
		$calendar_id      = self::get_selected_calendar_id_for_push( $booking->agent_id );

		if ( ! $calendar_id ) {
			return false;
		}

		// Check if booking status should be synced.
		if ( ! self::is_booking_status_syncable( $booking->status ) ) {
			self::delete_booking_from_outlook( $booking->id );
			return true;
		}

		$attendees = array();
		if ( OsOutlookCalendarHelper::should_customer_be_included_as_attendee( $booking->service_id ) ) {

			$attendees[] = array(
				'emailAddress' => array(
					'address' => $booking->customer->email,
					'name'    => $booking->customer->full_name,
				),
				'type'         => 'required',
			);
		}
		if ( OsOutlookCalendarHelper::should_agent_be_included_as_attendee( $booking->service_id ) ) {
			$attendees[] = array(
				'emailAddress' => array(
					'address' => $booking->agent->email,
					'name'    => $booking->agent->full_name,
				),
				'type'         => 'required',
			);
		}

		// Build event data.
		$description = self::get_event_description_template();
		$order       = $booking->get_order();
		$description = OsReplacerHelper::replace_all_vars(
			$description,
			array(
				'customer'    => $booking->customer,
				'agent'       => $booking->agent,
				'booking'     => $booking,
				'order'       => $order,
				'transaction' => $order->get_transactions()[0] ?? null,
			)
		);

		$summary = self::get_event_title_template();
		$summary = OsReplacerHelper::replace_all_vars(
			$summary,
			array(
				'customer'    => $booking->customer,
				'agent'       => $booking->agent,
				'booking'     => $booking,
				'order'       => $order,
				'transaction' => $order->get_transactions()[0] ?? null,
			)
		);

		$event_data = array(
			'subject'   => $summary,
			'attendees' => $attendees,
			'body'      => array(
				'contentType' => 'HTML',
				'content'     => $description,
			),
			'start'     => array(
				'dateTime' => $booking->format_start_date_and_time_rfc3339(),
				'timeZone' => OsTimeHelper::get_wp_timezone_name(),
			),
			'end'       => array(
				'dateTime' => $booking->format_end_date_and_time_rfc3339(),
				'timeZone' => OsTimeHelper::get_wp_timezone_name(),
			),
			'location'  => array(
				'displayName' => $booking->location->full_address,
			),
		);

		// Add support for Microsoft Teams.
		// @todo Polish the Teams integration.
		if ( OsMeetingSystemsHelper::is_external_meeting_system_enabled( 'outlook_teams' ) && OsOutlookCalendarHelper::is_outlook_teams_enabled_for_service( $booking->service_id ) ) {
			$add_outlook_teams             = true;
			$event_data['isOnlineMeeting'] = true;
			// $event_data['onlineMeetingProvider'] = 'teamsForBusiness'; // Will need to look into this more
		} else {
			$add_outlook_teams = false;
		}

		// In shared meeting mode, reuse existing Outlook event + Teams link from another group booking in the same slot.
		if ( $add_outlook_teams && ! $outlook_event_id && self::is_shared_meeting_mode( $booking->service_id ) ) {
			$group_bookings = OsFeatureGroupBookingsHelper::get_group_bookings_for_slot( $booking );
			foreach ( $group_bookings as $group_booking ) {
				if ( (int) $group_booking->id === (int) $booking->id ) {
					continue;
				}
				$existing_event_id    = OsMetaHelper::get_booking_meta_by_key( 'outlook_calendar_event_id', $group_booking->id );
				$existing_meeting_url = OsMetaHelper::get_booking_meta_by_key( 'outlook_calendar_event_meeting_url', $group_booking->id );
				if ( ! empty( $existing_event_id ) && ! empty( $existing_meeting_url ) ) {
					// Shared mode: reuse existing Outlook event + Teams link, skip event creation.
					$booking->save_meta_by_key( 'outlook_calendar_event_id', $existing_event_id );
					$booking->save_meta_by_key( 'outlook_calendar_event_meeting_url', $existing_meeting_url );
					return true;
				}
			}
		}

		if ( $outlook_event_id ) {
			OsOutlookCalendarRelayHelper::update_booking_in_outlook( $calendar_id, OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $booking->agent_id ), $outlook_event_id, $event_data );
		} else {
			$data = OsOutlookCalendarRelayHelper::create_booking_in_outlook( $calendar_id, OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $booking->agent_id ), $event_data );
			if ( ! empty( $data ) ) {
				$booking->save_meta_by_key( 'outlook_calendar_event_id', $data['event_id'] );
				if ( $add_outlook_teams && ! empty( $data['meeting_url'] ) ) {
					$booking->save_meta_by_key( 'outlook_calendar_event_meeting_url', $data['meeting_url'] );
				}
			}
		}

		return true;
	}

	/**
	 * Check if booking status should be synced
	 *
	 * @param string $booking_status Booking status.
	 * @return bool
	 */
	public static function is_booking_status_syncable( string $booking_status ): bool {
		return in_array( $booking_status, self::get_enabled_booking_statuses(), true );
	}

	/**
	 * Get enabled booking statuses for sync
	 */
	public static function get_enabled_booking_statuses() {
		$timeslot_blocking_statuses = OsBookingHelper::get_timeslot_blocking_statuses();
		return apply_filters( 'latepoint_outlook_calendar_enabled_booking_statuses', $timeslot_blocking_statuses );
	}

	/**
	 * Get list of calendars formatted for select dropdown
	 *
	 * @param int $agent_id Agent ID.
	 * @return array
	 */
	public static function get_list_of_calendars_for_select( $agent_id ) {
		$calendars            = self::get_list_of_calendars( $agent_id );
		$calendars_for_select = array();

		if ( ! empty( $calendars ) ) {
			foreach ( $calendars as $calendar ) {
				$calendars_for_select[] = array(
					'value' => $calendar['id'],
					'label' => $calendar['name'],
				);
			}
		}

		return $calendars_for_select;
	}

	/**
	 * Get saved Outlook event record by Outlook event ID
	 *
	 * @param string $outlook_event_id Outlook event ID.
	 * @return OsOutlookCalendarEventModel|false
	 */
	public static function get_record_by_outlook_event_id( $outlook_event_id ) {
		$event = new OsOutlookCalendarEventModel();
		return $event->where( array( 'outlook_event_id' => $outlook_event_id ) )->set_limit( 1 )->get_results_as_models();
	}

	/**
	 * Get start datetime of Outlook event
	 * Param isAllDay to check for all day event can be used if needed in future
	 *
	 * @param array $ocal_event Outlook calendar event data.
	 * @return OsWpDateTime|false
	 */
	public static function os_get_start_of_outlook_event( $ocal_event ) {
		try {
			if ( isset( $ocal_event['start']['dateTime'] ) ) {
				$date_string = $ocal_event['start']['dateTime'];
				$timezone    = new DateTimeZone( $ocal_event['start']['timeZone'] ?? 'UTC' );

				$prepare_date = new OsWpDateTime( $date_string, $timezone );

				return $prepare_date->setTimezone( OsTimeHelper::get_wp_timezone() );
			}
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error parsing Outlook event start time: ' . $e->getMessage(), 'outlook_calendar_error' );
		}
		return false;
	}

	/**
	 * Get end datetime of Outlook event
	 *
	 * @param array $ocal_event Outlook calendar event data.
	 * @return OsWpDateTime|false
	 */
	public static function os_get_end_of_outlook_event( $ocal_event ) {
		try {
			if ( isset( $ocal_event['end']['dateTime'] ) ) {
				$date_string = $ocal_event['end']['dateTime'];
				$timezone    = new DateTimeZone( $ocal_event['end']['timeZone'] ?? 'UTC' );

				$prepare_date = new OsWpDateTime( $date_string, $timezone );

				return $prepare_date->setTimezone( OsTimeHelper::get_wp_timezone() );
			}
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error parsing Outlook event end time: ' . $e->getMessage(), 'outlook_calendar_error' );
		}

		return false;
	}

	/**
	 * Get end datetime of Outlook event
	 *
	 * @param array $ocal_event Outlook calendar event data.
	 * @return OsWpDateTime|false
	 */
	public static function os_get_end_of_recurrence_event( $ocal_event ) {
		try {
			if ( isset( $ocal_event['recurrence']['range']['endDate'] ) ) {
				$date_string = $ocal_event['recurrence']['range']['endDate'];
				$timezone    = new DateTimeZone( 'UTC' );

				$prepare_date = new OsWpDateTime( $date_string, $timezone );

				$prepare_date->setTimezone( OsTimeHelper::get_wp_timezone() );

				return $prepare_date->setTime( 23, 59, 59 );
			}
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error parsing Outlook event end time: ' . $e->getMessage(), 'outlook_calendar_error' );
		}

		return false;
	}

	/**
	 * Parse Outlook event recurrence rules
	 *
	 * @param array $ocal_event Outlook calendar event data.
	 * @param bool  $save_to_db Whether to save to database.
	 * @return array
	 */
	public static function get_ocal_event_recurrences( $ocal_event, $save_to_db = true ) {
		$recurrences = array();
		if ( empty( $ocal_event['recurrence'] ) ) {
			return $recurrences;
		}

		$recurrence_pattern = $ocal_event['recurrence']['pattern'] ?? array();
		$recurrence_range   = $ocal_event['recurrence']['range'] ?? array();

		$recurrence = new OsOutlookEventRecurrenceModel();

		// Frequency mapping.
		$frequency_map = array(
			'daily'           => 'DAILY',
			'weekly'          => 'WEEKLY',
			'absoluteMonthly' => 'MONTHLY',
			'relativeMonthly' => 'MONTHLY',
			'absoluteYearly'  => 'YEARLY',
			'relativeYearly'  => 'YEARLY',
		);

		$recurrence->frequency = $frequency_map[ $recurrence_pattern['type'] ] ?? 'DAILY';
		$recurrence->interval  = $recurrence_pattern['interval'] ?? 1;

		// Parse weekday for weekly recurrence.
		if ( ! empty( $recurrence_pattern['daysOfWeek'] ) ) {
			$weekdays = array();
			foreach ( $recurrence_pattern['daysOfWeek'] as $day ) {
				$weekdays[] = strtoupper( substr( $day, 0, 2 ) );
			}
			$recurrence->weekday = implode( ',', $weekdays );
		}

		// Parse end date.
		if ( isset( $recurrence_range['endDate'] ) ) {
			$recurrence->until = $recurrence_range['endDate'];
		}

		/**
		 * Outlook always sends 0 for numberOfOccurrences, so we skip parsing count for now.
		 * Parse count.
		 * if (isset($recurrence_range['numberOfOccurrences'])) {
		 * $recurrence->count = $recurrence_range['numberOfOccurrences'];
		 * }
		 */

		if ( $save_to_db && isset( $ocal_event['id'] ) ) {
			$event = self::get_record_by_outlook_event_id( $ocal_event['id'] );
			if ( $event && $event->id ) {
				$recurrence->lp_event_id = $event->id;
				$recurrence->save();
			}
		}

		$recurrences[] = $recurrence;
		return $recurrences;
	}

	/**
	 * Translate weekday codes to human-readable names
	 *
	 * @param string $weekday Weekday code.
	 * @return string
	 */
	public static function translate_weekdays( $weekday ) {
		if ( empty( $weekday ) ) {
			return '';
		}

		$weekday_map = array(
			'MO' => __( 'Monday', 'latepoint-outlook-calendar' ),
			'TU' => __( 'Tuesday', 'latepoint-outlook-calendar' ),
			'WE' => __( 'Wednesday', 'latepoint-outlook-calendar' ),
			'TH' => __( 'Thursday', 'latepoint-outlook-calendar' ),
			'FR' => __( 'Friday', 'latepoint-outlook-calendar' ),
			'SA' => __( 'Saturday', 'latepoint-outlook-calendar' ),
			'SU' => __( 'Sunday', 'latepoint-outlook-calendar' ),
		);

		// Handle multiple weekdays.
		$weekdays   = explode( ',', $weekday );
		$translated = array();
		foreach ( $weekdays as $day ) {
			// Handle relative weekdays like "1MO", "2TU", "-1FR".
			$day    = trim( $day );
			$prefix = '';
			if ( preg_match( '/^(-?\d+)(.+)$/', $day, $matches ) ) {
				$prefix = $matches[1];
				$day    = $matches[2];
			}
			if ( isset( $weekday_map[ $day ] ) ) {
				$translated[] = $prefix . $weekday_map[ $day ];
			}
		}

		return implode( ', ', $translated );
	}

	/**
	 * Load events from Outlook calendar via relay
	 *
	 * @param string           $calendar_id Calendar ID.
	 * @param OsAgentModel|int $agent Agent model or ID.
	 * @param array            $opt_params Optional parameters.
	 * @return array
	 */
	public static function load_events_from_outlook_calendar( $calendar_id, $agent, $opt_params = array() ) {
		$connection_data = OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $agent );
		return OsOutlookCalendarRelayHelper::list_events_for_calendar( $calendar_id, $connection_data, $opt_params );
	}
}
