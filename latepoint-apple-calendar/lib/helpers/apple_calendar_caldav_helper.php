<?php
/**
 * Apple Calendar CalDAV Helper
 *
 * Handles CalDAV protocol communication with iCloud Calendar (caldav.icloud.com)
 * Uses WordPress HTTP API (wp_remote_request) for all CalDAV operations.
 *
 * CalDAV Protocol Reference: RFC 4791
 * CardDAV/CalDAV Discovery: RFC 6764
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Prevent redeclaration.
if ( class_exists( 'OsAppleCalendarCalDAVHelper' ) ) {
	return;
}

/**
 * Apple Calendar CalDAV Helper Class
 *
 * Provides methods for CalDAV protocol communication with iCloud Calendar.
 *
 * @since 1.0.0
 */
class OsAppleCalendarCalDAVHelper {
	/**
	 * CalDAV server configuration
	 */
	public const CALDAV_SERVER   = 'caldav.icloud.com';
	public const CALDAV_PROTOCOL = 'https';

	/**
	 * Build CalDAV URL with optional path
	 *
	 * @param string $path Optional path to append (e.g., '/.well-known/caldav').
	 * @return string CalDAV URL (e.g., https://caldav.icloud.com or https://caldav.icloud.com/.well-known/caldav).
	 *
	 * @since 1.0.0
	 */
	private static function get_caldav_url( $path = '' ) {
		$base_url = self::CALDAV_PROTOCOL . '://' . self::CALDAV_SERVER;
		return $path ? $base_url . $path : $base_url;
	}

	/**
	 * Build authorization header for Basic Auth
	 *
	 * @param string $apple_id Apple ID (email).
	 * @param string $app_password 16-character app-specific password.
	 * @return string Base64 encoded credentials.
	 *
	 * @since 1.0.0
	 */
	private static function build_auth_header( $apple_id, $app_password ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- This is needed for Basic Auth.
		return base64_encode( $apple_id . ':' . $app_password );
	}

	/**
	 * Test CalDAV connection with credentials
	 *
	 * @param string $apple_id Apple ID (email).
	 * @param string $app_password App-specific password.
	 * @return array ['success' => bool, 'message' => string, 'data' => array].
	 *
	 * @since 1.0.0
	 */
	public static function test_connection( $apple_id, $app_password ) {
		// Use well-known URL for initial connection test.
		$test_url = self::get_caldav_url( '/.well-known/caldav' );

		$response = wp_remote_request(
			$test_url,
			array(
				'method'      => 'PROPFIND',
				'headers'     => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $apple_id, $app_password ),
					'Content-Type'  => 'application/xml; charset=utf-8',
					'Depth'         => '0',
				),
				'body'        => '<?xml version="1.0" encoding="UTF-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal /></d:prop></d:propfind>',
				'timeout'     => 15,
				'sslverify'   => true,
				'redirection' => 5, // Follow redirects.
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Connection failed: ' . $response->get_error_message(),
				'data'    => null,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// iCloud may return 207 (Multi-Status) or redirect (301/302).
		if ( $status_code === 207 || $status_code === 200 || $status_code === 301 || $status_code === 302 ) {
			// Try to extract the principal URL from response.
			$body = wp_remote_retrieve_body( $response );

			return array(
				'success' => true,
				'message' => 'Connection successful',
				'data'    => array(
					'status_code'          => $status_code,
					'principal_discovered' => ! empty( $body ),
				),
			);
		}
		if ( $status_code === 401 || $status_code === 403 ) {
			return array(
				'success' => false,
				'message' => 'Authentication failed. Please check your Apple ID and app-specific password.',
				'data'    => array( 'status_code' => $status_code ),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		return array(
			'success' => false,
			'message' => 'Connection failed with status code: ' . $status_code,
			'data'    => array(
				'status_code'   => $status_code,
				'response_body' => substr( $body, 0, 500 ),
				'headers'       => wp_remote_retrieve_headers( $response ),
			),
		);
	}

	/**
	 * List all calendars for an agent
	 *
	 * @param int $agent_id Agent ID.
	 * @return array|WP_Error Array of calendars or WP_Error on failure
	 *
	 * @since 1.0.0
	 */
	public static function list_calendars( $agent_id ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found for this agent.' );
		}

		// Step 1: Discover the principal URL.
		$discovery_url = self::get_caldav_url( '/.well-known/caldav' );

		$discovery_response = wp_remote_request(
			$discovery_url,
			array(
				'method'      => 'PROPFIND',
				'headers'     => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
					'Content-Type'  => 'application/xml; charset=utf-8',
					'Depth'         => '0',
				),
				'body'        => '<?xml version="1.0" encoding="UTF-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:current-user-principal /></d:prop></d:propfind>',
				'timeout'     => 30,
				'sslverify'   => true,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $discovery_response ) ) {
			return $discovery_response;
		}

		// Parse the response to find current-user-principal.
		$body                   = wp_remote_retrieve_body( $discovery_response );
		$current_user_principal = self::parse_current_user_principal( $body );

		if ( ! $current_user_principal ) {
			return new WP_Error( 'caldav_error', 'Failed to discover principal URL' );
		}

		// Step 2: Query the principal to find calendar-home-set.
		$principal_response = wp_remote_request(
			$current_user_principal,
			array(
				'method'    => 'PROPFIND',
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
					'Content-Type'  => 'application/xml; charset=utf-8',
					'Depth'         => '0',
				),
				'body'      => '<?xml version="1.0" encoding="UTF-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-home-set /></d:prop></d:propfind>',
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $principal_response ) ) {
			return $principal_response;
		}

		$principal_body    = wp_remote_retrieve_body( $principal_response );
		$calendar_home_url = self::parse_calendar_home_set( $principal_body );

		if ( ! $calendar_home_url ) {
			return new WP_Error( 'caldav_error', 'Failed to discover calendar home URL' );
		}

		// Step 3: Query for calendars at the calendar home URL.
		$response = wp_remote_request(
			$calendar_home_url,
			array(
				'method'    => 'PROPFIND',
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
					'Content-Type'  => 'application/xml; charset=utf-8',
					'Depth'         => '1',
				),
				'body'      => '<?xml version="1.0" encoding="UTF-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="http://calendarserver.org/ns/"><d:prop><d:displayname /><d:resourcetype /><c:calendar-description /><cs:getctag /><c:supported-calendar-component-set /></d:prop></d:propfind>',
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 207 ) {
			$error_body = wp_remote_retrieve_body( $response );
			return new WP_Error( 'caldav_error', 'Failed to list calendars. Status: ' . $status_code, array( 'response' => substr( $error_body, 0, 500 ) ) );
		}

		$body = wp_remote_retrieve_body( $response );
		return self::parse_calendar_list( $body );
	}

	/**
	 * Parse current-user-principal from CalDAV response
	 *
	 * @param string $xml_body XML response body.
	 * @return string|false Principal URL or false
	 *
	 * @since 1.0.0
	 */
	private static function parse_current_user_principal( $xml_body ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_body );

		if ( $xml === false ) {
			return false;
		}

		$xml->registerXPathNamespace( 'd', 'DAV:' );

		$principal = $xml->xpath( '//d:current-user-principal/d:href' );
		if ( ! empty( $principal ) ) {
			$href = (string) $principal[0];
			// Make sure it's a full URL.
			if ( strpos( $href, 'http' ) !== 0 ) {
				$href = self::get_caldav_url( $href );
			}
			return $href;
		}

		return false;
	}

	/**
	 * Parse calendar-home-set from CalDAV response
	 *
	 * @param string $xml_body XML response body.
	 * @return string|false Calendar home URL or false
	 *
	 * @since 1.0.0
	 */
	private static function parse_calendar_home_set( $xml_body ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_body );

		if ( $xml === false ) {
			return false;
		}

		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

		$calendar_home = $xml->xpath( '//c:calendar-home-set/d:href' );
		if ( ! empty( $calendar_home ) ) {
			$href = (string) $calendar_home[0];
			// Make sure it's a full URL.
			if ( strpos( $href, 'http' ) !== 0 ) {
				$href = self::get_caldav_url( $href );
			}
			return $href;
		}

		return false;
	}

	/**
	 * Parse calendar list from PROPFIND response
	 *
	 * @param string $xml_body XML response body.
	 * @return array Array of calendars
	 *
	 * @since 1.0.0
	 */
	private static function parse_calendar_list( $xml_body ) {
		$calendars = array();

		// Suppress XML errors.
		libxml_use_internal_errors( true );

		$xml = simplexml_load_string( $xml_body );
		if ( $xml === false ) {
			return $calendars;
		}

		// Register namespaces.
		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );
		$xml->registerXPathNamespace( 'cs', 'http://calendarserver.org/ns/' );

		$responses = $xml->xpath( '//d:response' );

		foreach ( $responses as $response ) {
			$response->registerXPathNamespace( 'd', 'DAV:' );
			$response->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

			// Check if this is a calendar resource.
			$resourcetype = $response->xpath( './/d:resourcetype/c:calendar' );
			if ( empty( $resourcetype ) ) {
				continue; // Not a calendar, skip.
			}

			$href        = (string) $response->xpath( './/d:href' )[0];
			$displayname = $response->xpath( './/d:displayname' );
			$description = $response->xpath( './/c:calendar-description' );
			$ctag        = $response->xpath( './/cs:getctag' );

			// Make sure href is a full URL.
			if ( strpos( $href, 'http' ) !== 0 ) {
				$full_url = self::get_caldav_url( $href );
			} else {
				$full_url = $href;
			}

			$calendar = array(
				'id'          => basename( $href ), // Use just the UUID as ID.
				'url'         => $full_url, // Full URL for CalDAV operations.
				'name'        => ! empty( $displayname ) ? (string) $displayname[0] : basename( $href ),
				'description' => ! empty( $description ) ? (string) $description[0] : '',
				'ctag'        => ! empty( $ctag ) ? (string) $ctag[0] : '',
			);

			$calendars[] = $calendar;
		}

		return $calendars;
	}

	/**
	 * Get events from a calendar within a date range
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $calendar_url Calendar URL.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date End date (Y-m-d).
	 * @return array|WP_Error Array of events or WP_Error
	 *
	 * @since 1.0.0
	 */
	public static function get_events_in_range( $agent_id, $calendar_url, $start_date, $end_date ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		// Convert dates to iCalendar format (UTC).
		$start_datetime = new DateTime( $start_date . ' 00:00:00', new DateTimeZone( 'UTC' ) );
		$end_datetime   = new DateTime( $end_date . ' 23:59:59', new DateTimeZone( 'UTC' ) );
		$start_ical     = $start_datetime->format( 'Ymd\THis\Z' );
		$end_ical       = $end_datetime->format( 'Ymd\THis\Z' );

		// Build REPORT request body.
		$request_body = '<?xml version="1.0" encoding="UTF-8"?><c:calendar-query xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:"><d:prop><d:getetag /><c:calendar-data /></d:prop><c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT"><c:time-range start="' . $start_ical . '" end="' . $end_ical . '"/></c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';

		$response = wp_remote_request(
			$calendar_url,
			array(
				'method'    => 'REPORT',
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
					'Content-Type'  => 'application/xml; charset=utf-8',
					'Depth'         => '1',
				),
				'body'      => $request_body,
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 207 ) {
			return new WP_Error( 'caldav_error', 'Failed to fetch events. Status: ' . $status_code );
		}

		$body = wp_remote_retrieve_body( $response );
		return self::parse_events_from_report( $body, $calendar_url );
	}

	/**
	 * Get events incrementally using sync-token (CalDAV sync-collection REPORT)
	 * This method fetches only changed events since the last sync, making it much more efficient
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $calendar_url Calendar URL.
	 * @param string $sync_token Previous sync token (null for initial sync).
	 * @param string $start_date Optional start date filter (Y-m-d).
	 * @param string $end_date Optional end date filter (Y-m-d).
	 * @return array|WP_Error Array with 'events' and 'sync_token' keys, or WP_Error
	 *
	 * @since 1.0.0
	 */
	public static function get_events_incremental( $agent_id, $calendar_url, $sync_token = null, $start_date = null, $end_date = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Keep parameters for future use.
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		// Build sync-collection REPORT request body.
		// According to RFC 6578, an empty sync-token means "initial sync - get everything".
		if ( empty( $sync_token ) ) {
			$sync_token_xml = '<d:sync-token/>';
		} else {
			$sync_token_xml = '<d:sync-token>' . htmlspecialchars( $sync_token, ENT_XML1, 'UTF-8' ) . '</d:sync-token>';
		}

		$request_body = '<?xml version="1.0" encoding="UTF-8"?>
			<d:sync-collection xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">' .
			$sync_token_xml .
			'<d:sync-level>1</d:sync-level>
			<d:prop>
			<d:getetag />
			<c:calendar-data />
			</d:prop>
			</d:sync-collection>';

		$response = wp_remote_request(
			$calendar_url,
			array(
				'method'    => 'REPORT',
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
					'Content-Type'  => 'application/xml; charset=utf-8',
					'Depth'         => '1',
				),
				'body'      => $request_body,
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Status 207 = Multi-Status (success).
		// Status 403 or 409 = Invalid sync-token, need to do full resync.
		if ( $status_code === 403 || $status_code === 409 ) {
			return new WP_Error( 'invalid_sync_token', 'Sync token is invalid or expired. Full resync required.', array( 'status' => $status_code ) );
		}

		if ( $status_code !== 207 ) {
			return new WP_Error( 'caldav_error', 'Failed to fetch incremental events. Status: ' . $status_code );
		}

		$body = wp_remote_retrieve_body( $response );
		return self::parse_sync_collection_response( $body, $calendar_url, $agent_id );
	}

	/**
	 * Fetch a single event by its URL
	 * Used when sync-collection returns only href+etag without calendar-data
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $event_url Full event URL.
	 * @return string|WP_Error ICS data or error
	 *
	 * @since 1.0.0
	 */
	private static function fetch_event_by_url( $agent_id, $event_url ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		$response = wp_remote_get(
			$event_url,
			array(
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
				),
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new WP_Error( 'caldav_error', 'Failed to fetch event. Status: ' . $status_code );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse sync-collection REPORT response
	 *
	 * @param string $xml_body XML response body.
	 * @param string $calendar_url Calendar URL.
	 * @param int    $agent_id Agent ID (for fetching events without calendar-data).
	 * @return array Array with 'events', 'deleted_hrefs', and 'sync_token' keys
	 *
	 * @since 1.0.0
	 */
	private static function parse_sync_collection_response( $xml_body, $calendar_url, $agent_id = null ) {
		$result = array(
			'events'        => array(),
			'deleted_hrefs' => array(),
			'sync_token'    => null,
		);

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_body );
		if ( $xml === false ) {
			return $result;
		}

		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

		// Extract new sync token.
		$sync_token_nodes = $xml->xpath( '//d:sync-token' );
		if ( ! empty( $sync_token_nodes ) ) {
			$result['sync_token'] = (string) $sync_token_nodes[0];
		}

		// Extract responses.
		$responses = $xml->xpath( '//d:response' );

		foreach ( $responses as $response ) {
			$response->registerXPathNamespace( 'd', 'DAV:' );
			$response->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

			$href_nodes = $response->xpath( './/d:href' );
			if ( empty( $href_nodes ) ) {
				continue;
			}
			$href = (string) $href_nodes[0];

			// Check if this is a deletion (status 404).
			$status_nodes = $response->xpath( './/d:status' );
			if ( ! empty( $status_nodes ) ) {
				$status = (string) $status_nodes[0];
				if ( strpos( $status, '404' ) !== false ) {
					// Event was deleted.
					$result['deleted_hrefs'][] = $href;
					continue;
				}
			}

			// Skip the calendar collection itself (ends with /).
			if ( substr( $href, -1 ) === '/' ) {
				continue;
			}

			// Parse event data.
			$etag_nodes          = $response->xpath( './/d:getetag' );
			$calendar_data_nodes = $response->xpath( './/c:calendar-data' );

			$etag = ! empty( $etag_nodes ) ? (string) $etag_nodes[0] : '';

			// Check if we have calendar data.
			if ( empty( $calendar_data_nodes ) || empty( (string) $calendar_data_nodes[0] ) ) {
				// No calendar data in sync response - need to fetch it separately.
				// This is common for sync-collection - it only returns href+etag for changed items.
				$full_event_url = self::get_caldav_url( $href );
				$ics_data       = self::fetch_event_by_url( $agent_id, $full_event_url );

				if ( is_wp_error( $ics_data ) || empty( $ics_data ) ) {
					// Failed to fetch event data, skip this item.
					continue;
				}
			} else {
				$ics_data = (string) $calendar_data_nodes[0];
			}

			// Parse ICS data.
			$event_data = OsAppleCalendarICSHelper::parse_ics_to_event_data( $ics_data );
			if ( $event_data ) {
				$event_data['caldav_url']        = $href;
				$event_data['caldav_etag']       = $etag;
				$event_data['apple_calendar_id'] = $calendar_url;
				$result['events'][]              = $event_data;
			}
		}

		return $result;
	}

	/**
	 * Parse events from calendar-query REPORT response
	 *
	 * @param string $xml_body XML response body.
	 * @param string $calendar_url Calendar URL.
	 * @return array Array of events
	 *
	 * @since 1.0.0
	 */
	private static function parse_events_from_report( $xml_body, $calendar_url ) {
		$events = array();

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_body );
		if ( $xml === false ) {
			return $events;
		}

		$xml->registerXPathNamespace( 'd', 'DAV:' );
		$xml->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

		$responses = $xml->xpath( '//d:response' );

		foreach ( $responses as $response ) {
			$response->registerXPathNamespace( 'd', 'DAV:' );
			$response->registerXPathNamespace( 'c', 'urn:ietf:params:xml:ns:caldav' );

			$href                = (string) $response->xpath( './/d:href' )[0];
			$etag_nodes          = $response->xpath( './/d:getetag' );
			$calendar_data_nodes = $response->xpath( './/c:calendar-data' );

			if ( empty( $calendar_data_nodes ) ) {
				continue;
			}

			$ics_data = (string) $calendar_data_nodes[0];
			$etag     = ! empty( $etag_nodes ) ? (string) $etag_nodes[0] : '';

			// Parse ICS data.
			$event_data = OsAppleCalendarICSHelper::parse_ics_to_event_data( $ics_data );
			if ( $event_data ) {
				$event_data['caldav_url']        = $href;
				$event_data['caldav_etag']       = $etag;
				$event_data['apple_calendar_id'] = $calendar_url;
				$events[]                        = $event_data;
			}
		}

		return $events;
	}

	/**
	 * Create a new event in Apple Calendar
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $calendar_url Calendar URL.
	 * @param string $ics_data ICS data for the event.
	 * @param string $event_uid Unique event ID.
	 * @return array|WP_Error Success array or WP_Error
	 *
	 * @since 1.0.0
	 */
	public static function create_event( $agent_id, $calendar_url, $ics_data, $event_uid ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		// Build event URL.
		$event_url = rtrim( $calendar_url, '/' ) . '/' . $event_uid . '.ics';

		$response = wp_remote_request(
			$event_url,
			array(
				'method'    => 'PUT',
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
					'Content-Type'  => 'text/calendar; charset=utf-8',
					'If-None-Match' => '*', // Only create if doesn't exist.
				),
				'body'      => $ics_data,
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// 201 = Created, 204 = No Content (updated).
		if ( $status_code === 201 || $status_code === 204 ) {
			$etag = wp_remote_retrieve_header( $response, 'etag' );
			return array(
				'success'     => true,
				'event_url'   => $event_url,
				'etag'        => $etag,
				'status_code' => $status_code,
			);
		}

		return new WP_Error( 'caldav_error', 'Failed to create event. Status: ' . $status_code );
	}

	/**
	 * Update an existing event in Apple Calendar
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $event_url Full event URL.
	 * @param string $ics_data Updated ICS data.
	 * @param string $etag Current etag (for optimistic locking).
	 * @return array|WP_Error Success array or WP_Error
	 *
	 * @since 1.0.0
	 */
	public static function update_event( $agent_id, $event_url, $ics_data, $etag = '' ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		$headers = array(
			'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
			'Content-Type'  => 'text/calendar; charset=utf-8',
		);

		// Add If-Match header for optimistic locking if etag provided.
		if ( ! empty( $etag ) ) {
			$headers['If-Match'] = $etag;
		}

		$response = wp_remote_request(
			$event_url,
			array(
				'method'    => 'PUT',
				'headers'   => $headers,
				'body'      => $ics_data,
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 204 || $status_code === 201 ) {
			$new_etag = wp_remote_retrieve_header( $response, 'etag' );
			return array(
				'success'     => true,
				'etag'        => $new_etag,
				'status_code' => $status_code,
			);
		}

		if ( $status_code === 412 ) {
			return new WP_Error( 'precondition_failed', 'Event was modified by another client. Please refresh and try again.' );
		}

		return new WP_Error( 'caldav_error', 'Failed to update event. Status: ' . $status_code );
	}

	/**
	 * Delete an event from Apple Calendar
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $event_url Full event URL.
	 * @param string $etag Current etag (optional, for safety).
	 * @return array|WP_Error Success array or WP_Error
	 *
	 * @since 1.0.0
	 */
	public static function delete_event( $agent_id, $event_url, $etag = '' ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		$headers = array(
			'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
		);

		if ( ! empty( $etag ) ) {
			$headers['If-Match'] = $etag;
		}

		$response = wp_remote_request(
			$event_url,
			array(
				'method'    => 'DELETE',
				'headers'   => $headers,
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 204 || $status_code === 200 ) {
			return array(
				'success'     => true,
				'status_code' => $status_code,
			);
		}

		if ( $status_code === 404 ) {
			return array(
				'success'     => true, // Already deleted.
				'message'     => 'Event already deleted',
				'status_code' => $status_code,
			);
		}

		return new WP_Error( 'caldav_error', 'Failed to delete event. Status: ' . $status_code );
	}

	/**
	 * Get a specific event by URL
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $event_url Event URL.
	 * @return array|WP_Error Event data or WP_Error
	 *
	 * @since 1.0.0
	 */
	public static function get_event( $agent_id, $event_url ) {
		$credentials = OsAppleCalendarHelper::get_agent_credentials( $agent_id );
		if ( ! $credentials ) {
			return new WP_Error( 'no_credentials', 'No Apple Calendar credentials found.' );
		}

		$response = wp_remote_get(
			$event_url,
			array(
				'headers'   => array(
					'Authorization' => 'Basic ' . self::build_auth_header( $credentials['apple_id'], $credentials['app_password'] ),
				),
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new WP_Error( 'caldav_error', 'Failed to get event. Status: ' . $status_code );
		}

		$ics_data = wp_remote_retrieve_body( $response );
		$etag     = wp_remote_retrieve_header( $response, 'etag' );

		$event_data = OsAppleCalendarICSHelper::parse_ics_to_event_data( $ics_data );
		if ( $event_data ) {
			$event_data['caldav_url']  = $event_url;
			$event_data['caldav_etag'] = $etag;
		}

		return $event_data;
	}
}
