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
 * Outlook calendar relay helper.
 */
class OsOutlookCalendarRelayHelper {
	/**
	 * Check if Outlook Calendar integration is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return OsSettingsHelper::is_on( 'enable_outlook_calendar' );
	}

	/**
	 * Make a request to the relay server
	 *
	 * @param string $path Request path.
	 * @param string $connection_data Connection data.
	 * @param string $method HTTP method.
	 * @param array  $vars Request variables.
	 * @param array  $headers Request headers.
	 *
	 * @throws Exception When the request fails.
	 */
	public static function do_request( string $path, string $connection_data = '', string $method = 'GET', array $vars = array(), array $headers = array() ) {

		$default_vars    = array();
		$default_headers = array(
			'latepoint-version'     => LATEPOINT_VERSION,
			'latepoint-domain'      => OsUtilHelper::get_site_url(),
			'latepoint-license-key' => OsLicenseHelper::get_license_key(),
		);

		if ( ! empty( $connection_data ) ) {
			$default_headers['connection-data'] = $connection_data;
		}

		$args = array(
			'timeout'   => 15,
			'headers'   => array_merge( $default_headers, $headers ),
			'body'      => array_merge( $default_vars, $vars ),
			'sslverify' => false,
			'method'    => $method,
		);

		$url = OUTLOOK_CALENDAR_RELAY_URL . "/api/wp/v1/outlook-calendar/{$path}";

		$response = wp_remote_request( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ), true );
		}
			$error_message = $response->get_error_message();
			throw new Exception( esc_html( $error_message ) );
	}

	/**
	 * Get connection URL for agent to authorize
	 *
	 * @param int $agent_id Agent ID.
	 *
	 * @return string
	 */
	public static function get_connect_url_for_agent( $agent_id ) {
		$agent       = new OsAgentModel( $agent_id );
		$agent_token = $agent->get_meta_by_key( 'agent_token_for_outlook_auth' );
		if ( empty( $agent_token ) ) {
			$agent_token = OsUtilHelper::generate_uuid();
			$agent->save_meta_by_key( 'agent_token_for_outlook_auth', $agent_token );
		}
		$url  = OUTLOOK_CALENDAR_RELAY_URL . '/wp/outlook-calendar-connection/';
		$url .= $agent_token . '/' . strtr( base64_encode( implode( '|||', array( $agent->full_name, $agent->avatar_url, OsUtilHelper::get_site_url(), 'connect' ) ) ), '+/=', '-_.' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return $url;
	}

	/**
	 * Remove connection from relay server
	 *
	 * @param string $connection_id Connection ID.
	 *
	 * @return array|void
	 */
	public static function remove_connection( string $connection_id ) {
		try {
			$response = self::do_request( "connections/{$connection_id}", '', 'DELETE' );
			return $response['data'] ?? false;
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error removing connection to Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Get list of calendars from Outlook
	 *
	 * @param string $connection_data Connection data.
	 *
	 * @return array
	 */
	public static function get_list_of_calendars( string $connection_data ) {
		$calendars = array();
		try {
			$response = self::do_request( 'calendars', $connection_data );
			if ( ! empty( $response['data'] ) ) {
				$calendars = $response['data'];
			}
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error getting list of calendars from Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
		return $calendars;
	}

	/**
	 * Stop subscription (webhook)
	 *
	 * @param string $subscription_id Subscription ID.
	 * @param string $connection_data Connection data.
	 *
	 * @return array|void
	 */
	public static function stop_subscription( string $subscription_id, string $connection_data ) {
		try {
			$response = self::do_request( "subscriptions/{$subscription_id}", $connection_data, 'DELETE' );
			return $response['data'] ?? false;
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error stopping subscription for Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Start subscription (webhook)
	 *
	 * @param string $agent_id Agent ID.
	 * @param string $calendar_id Calendar ID.
	 * @param string $connection_data Connection data.
	 *
	 * @return array|void
	 */
	public static function start_subscription( string $agent_id, string $calendar_id, string $connection_data ) {
		try {
			$response = self::do_request( "calendars/{$calendar_id}/subscriptions/{$agent_id}", $connection_data, 'POST' );
			return $response['data'] ?? false;
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error starting subscription for Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Create booking in Outlook Calendar
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $connection_data Connection data.
	 * @param array  $event_data Event data.
	 *
	 * @return array|void
	 */
	public static function create_booking_in_outlook( string $calendar_id, string $connection_data, array $event_data ) {
		try {
			$response = self::do_request( "calendars/{$calendar_id}/events", $connection_data, 'POST', array( 'event' => $event_data ) );
			return $response['data'] ?? false;
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error creating booking in Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Update booking in Outlook Calendar
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $connection_data Connection data.
	 * @param string $outlook_event_id Outlook event ID.
	 * @param array  $event_data Event data.
	 *
	 * @return void
	 */
	public static function update_booking_in_outlook( string $calendar_id, string $connection_data, string $outlook_event_id, array $event_data ) {
		try {
			$response = self::do_request( "calendars/{$calendar_id}/events/{$outlook_event_id}", $connection_data, 'PATCH', array( 'event' => $event_data ) );
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error updating booking in Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Delete booking from Outlook Calendar
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $connection_data Connection data.
	 * @param string $outlook_event_id Outlook event ID.
	 *
	 * @return void
	 */
	public static function delete_booking_from_outlook( string $calendar_id, string $connection_data, string $outlook_event_id ) {
		try {
			$response = self::do_request( "calendars/{$calendar_id}/events/{$outlook_event_id}", $connection_data, 'DELETE' );
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error deleting booking from Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Refresh access token
	 *
	 * @param string $connection_data Connection data.
	 *
	 * @return string|false
	 */
	public static function refresh_access_token( string $connection_data ) {
		try {
			$response = self::do_request( 'refresh-token', $connection_data );
			if ( ! empty( $response['data'] ) && ! empty( $response['data']['new_access_token'] ) ) {
				return $response['data']['new_access_token'];
			}
				return false;

		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error refreshing access token for Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * Get event from Outlook Calendar
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $connection_data Connection data.
	 * @param string $outlook_event_id Outlook event ID.
	 *
	 * @return array|false
	 */
	public static function get_event_from_outlook( string $calendar_id, string $connection_data, string $outlook_event_id ) {
		try {
			$response = self::do_request( "calendars/{$calendar_id}/events/{$outlook_event_id}", $connection_data );
			if ( ! empty( $response['data'] ) ) {
				return $response['data'];
			}
				return false;

		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error getting event info from Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
	}

	/**
	 * List events for calendar
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $connection_data Connection data.
	 * @param array  $opt_params Optional parameters.
	 *
	 * @return array
	 */
	public static function list_events_for_calendar( string $calendar_id, string $connection_data, array $opt_params = array() ) {
		$events = array();
		try {
			$response = self::do_request( "calendars/{$calendar_id}/events", $connection_data, 'GET', array(), array( 'opt-params' => wp_json_encode( $opt_params ) ) );
			if ( ! empty( $response['data'] ) ) {
				$events = $response['data'];
			}
		} catch ( Exception $e ) {
			OsDebugHelper::log( 'Error listing events from Outlook Calendar', 'outlook_calendar', array( 'error_message' => $e->getMessage() ) );
		}
		return $events;
	}

	/**
	 * Get connection data for agent
	 *
	 * @param OsAgentModel|int $agent Agent model or ID.
	 *
	 * @return string
	 */
	public static function get_connection_data_for_agent( $agent ) {
		if ( is_a( $agent, 'OsAgentModel' ) ) {
			return wp_json_encode(
				array(
					'access_token_data' => $agent->get_meta_by_key( 'outlook_cal_access_token_relay' ),
					'connection_id'     => $agent->get_meta_by_key( 'outlook_cal_connection_id_relay' ),
				)
			);
		}

		return wp_json_encode(
			array(
				'access_token_data' => OsMetaHelper::get_agent_meta_by_key( 'outlook_cal_access_token_relay', $agent ),
				'connection_id'     => OsMetaHelper::get_agent_meta_by_key( 'outlook_cal_connection_id_relay', $agent ),
			)
		);
	}

	/**
	 * Get a specific event from Outlook calendar
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param string $outlook_event_id Outlook event ID.
	 * @param string $connection_data Connection data.
	 *
	 * @return array|null
	 */
	public static function get_event_from_calendar( string $calendar_id, string $outlook_event_id, string $connection_data ) {
		$path     = 'calendars/' . $calendar_id . '/events/' . $outlook_event_id;
		$response = self::do_request( $path, $connection_data, 'GET' );
		return ! empty( $response['data'] ) ? $response['data'] : null;
	}
}
