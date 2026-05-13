<?php
/**
 * Apple Calendar Main Helper
 *
 * Central helper class for Apple Calendar integration with LatePoint.
 * Manages settings, credentials, calendar connections, and synchronization operations.
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Prevent redeclaration.
if ( class_exists( 'OsAppleCalendarHelper' ) ) {
	return;
}

/**
 * Apple Calendar Helper Class
 *
 * Main helper for Apple Calendar integration including credential management,
 * event synchronization, and settings management.
 *
 * @since 1.0.0
 */
class OsAppleCalendarHelper {
	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Check if Apple Calendar is enabled
	 *
	 * @return bool True if enabled
	 *
	 * @since 1.0.0
	 */
	public static function is_enabled() {
		return OsSettingsHelper::is_on( 'enable_apple_calendar' );
	}

	// ==========================================.
	// CREDENTIAL MANAGEMENT.
	// ==========================================.

	/**
	 * Check if agent is connected to Apple Calendar
	 *
	 * @param int $agent_id Agent ID.
	 * @return bool True if connected
	 *
	 * @since 1.0.0
	 */
	public static function is_agent_connected_to_apple_calendar( $agent_id ) {
		$credentials = self::get_agent_credentials( $agent_id );
		return ! empty( $credentials['apple_id'] ) && ! empty( $credentials['app_password'] );
	}

	/**
	 * Get agent's Apple Calendar credentials
	 *
	 * @param int $agent_id Agent ID.
	 * @return array|false Array with 'apple_id' and 'app_password' or false
	 *
	 * @since 1.0.0
	 */
	public static function get_agent_credentials( $agent_id ) {
		$apple_id           = OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_apple_id', $agent_id );
		$encrypted_password = OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_app_password', $agent_id );

		if ( empty( $apple_id ) || empty( $encrypted_password ) ) {
			return false;
		}

		// Decrypt password.
		$app_password = self::decrypt_password( $encrypted_password );

		return array(
			'apple_id'     => $apple_id,
			'app_password' => $app_password,
		);
	}

	/**
	 * Save agent's Apple Calendar credentials
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $apple_id Apple ID (email).
	 * @param string $app_password 16-character app-specific password.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function save_agent_credentials( $agent_id, $apple_id, $app_password ) {
		// Encrypt password before storing.
		$encrypted_password = self::encrypt_password( $app_password );

		OsMetaHelper::save_agent_meta_by_key( 'apple_calendar_apple_id', $apple_id, $agent_id );
		OsMetaHelper::save_agent_meta_by_key( 'apple_calendar_app_password', $encrypted_password, $agent_id );

		return true;
	}

	/**
	 * Clear all Apple Calendar connection info for an agent
	 *
	 * @param int $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function clear_calendar_connection_info_from_agent( $agent_id ) {
		OsMetaHelper::delete_agent_meta_by_key( 'apple_calendar_apple_id', $agent_id );
		OsMetaHelper::delete_agent_meta_by_key( 'apple_calendar_app_password', $agent_id );
		OsMetaHelper::delete_agent_meta_by_key( 'apple_calendar_selected_calendar_id_for_push', $agent_id );
		OsMetaHelper::delete_agent_meta_by_key( 'apple_calendar_selected_calendar_ids_for_pull', $agent_id );

		return true;
	}

	/**
	 * Encrypt password for storage using AES-256-CBC
	 *
	 * @param string $password Plain text password.
	 * @return string Encrypted password with IV
	 *
	 * @since 1.0.0
	 */
	private static function encrypt_password( $password ) {
		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			self::error_log( 'OpenSSL not available for password encryption' );
			return base64_encode( $password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback to base64 (not secure, but better than crash).
		}

		$method = 'AES-256-CBC';
		$key    = hash( 'sha256', wp_salt( 'auth' ), true ); // Derive 256-bit key from WordPress salt.

		// Generate random IV.
		$iv_length = openssl_cipher_iv_length( $method );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		// Encrypt the password.
		$encrypted = openssl_encrypt( $password, $method, $key, OPENSSL_RAW_DATA, $iv );

		if ( $encrypted === false ) {
			self::error_log( 'Password encryption failed' );
			return base64_encode( $password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback to base64 (not secure, but better than crash).
		}

		// Combine IV and encrypted data, then base64 encode.
		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Needed for storage.
	}

	/**
	 * Decrypt password from storage using AES-256-CBC
	 *
	 * @param string $encrypted_password Encrypted password with IV.
	 * @return string Plain text password
	 *
	 * @since 1.0.0
	 */
	private static function decrypt_password( $encrypted_password ) {
		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			self::error_log( 'OpenSSL not available for password decryption' );
			return base64_decode( $encrypted_password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fallback for base64 encoded passwords.
		}

		$method = 'AES-256-CBC';
		$key    = hash( 'sha256', wp_salt( 'auth' ), true ); // Same key derivation as encryption.

		/**
		 * Decode the base64 encoded data.
		 * base64_decode can return false on failure, not just string.
		 *
		 * @var string|false $data
		 */
		$data = base64_decode( $encrypted_password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Needed for storage.

		if ( $data === false ) {
			self::error_log( 'Invalid encrypted password format' );
			return '';
		}

		// Extract IV and encrypted data.
		$iv_length = openssl_cipher_iv_length( $method );
		if ( strlen( $data ) < $iv_length ) {
			// Data is too short to contain IV - might be old XOR encrypted or base64 encoded.
			// Try fallback decryption for backwards compatibility.
			self::error_log( 'Password appears to use old encryption format' );
			return base64_decode( $encrypted_password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fallback.
		}

		$iv             = substr( $data, 0, $iv_length );
		$encrypted_data = substr( $data, $iv_length );

		// Decrypt the password.
		$password = openssl_decrypt( $encrypted_data, $method, $key, OPENSSL_RAW_DATA, $iv );

		if ( $password === false ) {
			self::error_log( 'Password decryption failed' );
			return '';
		}

		return $password;
	}

	// ==========================================.
	// CALENDAR SELECTION.
	// ==========================================.

	/**
	 * Get selected calendar ID for pushing bookings
	 *
	 * @param int $agent_id Agent ID.
	 * @return string|false Calendar ID or false
	 *
	 * @since 1.0.0
	 */
	public static function get_selected_calendar_id_for_push( $agent_id ) {
		return OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_selected_calendar_id_for_push', $agent_id );
	}

	/**
	 * Set selected calendar ID for pushing bookings
	 *
	 * @param string $calendar_id Calendar ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function set_selected_calendar_id_for_push( $calendar_id, $agent_id ) {
		return OsMetaHelper::save_agent_meta_by_key( 'apple_calendar_selected_calendar_id_for_push', $calendar_id, $agent_id );
	}

	/**
	 * Get selected calendar IDs for pulling events (comma-separated)
	 *
	 * @param int $agent_id Agent ID.
	 * @return string|false Comma-separated calendar IDs or false
	 *
	 * @since 1.0.0
	 */
	public static function get_selected_calendar_ids_for_pull( $agent_id ) {
		return OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_selected_calendar_ids_for_pull', $agent_id );
	}

	/**
	 * Set selected calendar IDs for pulling events
	 *
	 * @param string $calendar_ids Comma-separated calendar IDs.
	 * @param int    $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function set_selected_calendar_ids_for_pull( $calendar_ids, $agent_id ) {
		return OsMetaHelper::save_agent_meta_by_key( 'apple_calendar_selected_calendar_ids_for_pull', $calendar_ids, $agent_id );
	}

	/**
	 * Get calendar name by ID
	 *
	 * @param string $calendar_id Calendar ID/URL.
	 * @param int    $agent_id Agent ID.
	 * @return string|false Calendar name or false if not found
	 *
	 * @since 1.0.0
	 */
	public static function get_calendar_name_by_id( $calendar_id, $agent_id ) {
		// Get calendar list (fetches from CalDAV if not cached).
		$calendars = self::get_list_of_calendars( $agent_id );

		if ( ! empty( $calendars ) ) {
			foreach ( $calendars as $calendar ) {
				if ( $calendar['id'] === $calendar_id || $calendar['url'] === $calendar_id ) {
					return $calendar['name'];
				}
			}
		}

		// Return false if not found.
		return false;
	}

	/**
	 * Cache calendar list for agent
	 *
	 * @param int   $agent_id Agent ID.
	 * @param array $calendars Array of calendars.
	 * @param int   $expiration Cache expiration in seconds (default 1 hour).
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function cache_calendars( $agent_id, $calendars, $expiration = 3600 ) {
		return set_transient( 'apple_calendar_list_' . $agent_id, $calendars, $expiration );
	}

	// ==========================================.
	// EVENT TEMPLATES.
	// ==========================================.

	/**
	 * Get event title template
	 *
	 * @return string Template with variables
	 *
	 * @since 1.0.0
	 */
	public static function get_event_title_template() {
		return OsSettingsHelper::get_settings_value( 'apple_calendar_event_title_template', '{{service_name}}' );
	}

	/**
	 * Get event description template
	 *
	 * @return string Template with variables
	 *
	 * @since 1.0.0
	 */
	public static function get_event_description_template() {
		return OsSettingsHelper::get_settings_value( 'apple_calendar_event_description_template', 'Customer Name: <strong>{{customer_full_name}}</strong><br/>Phone: <strong>{{customer_phone}}</strong>' );
	}

	/**
	 * Get list of calendars for agent
	 *
	 * @param int $agent_id Agent ID.
	 * @return array Array of calendars
	 *
	 * @since 1.0.0
	 */
	public static function get_list_of_calendars( $agent_id ) {
		// Try to get from cache first.
		$cached    = get_transient( 'apple_calendar_list_' . $agent_id );
		$calendars = $cached ? $cached : false;

		if ( $calendars === false ) {
			// Not cached, fetch from CalDAV.
			$calendars = OsAppleCalendarCalDAVHelper::list_calendars( $agent_id );

			// Check if we got a WP_Error.
			if ( is_wp_error( $calendars ) ) {
				self::error_log( 'Apple Calendar fetch calendars error for agent ' . $agent_id . ': ' . $calendars->get_error_message() );
				return array();
			}

			if ( ! empty( $calendars ) ) {
				// Cache for 1 hour.
				self::cache_calendars( $agent_id, $calendars, 3600 );
			}
		}

		// Sort by name.
		if ( is_array( $calendars ) && ! empty( $calendars ) ) {
			usort(
				$calendars,
				static function ( $item1, $item2 ) {
					return $item1['name'] <=> $item2['name'];
				}
			);
		}

		return $calendars;
	}

	/**
	 * Get list of calendars formatted for select field
	 *
	 * @param int  $agent_id Agent ID.
	 * @param bool $include_empty Include empty option.
	 * @param bool $force_refresh Force refresh from CalDAV (bypass cache).
	 * @return array Calendars formatted for select
	 *
	 * @since 1.0.0
	 */
	public static function get_list_of_calendars_for_select( $agent_id, $include_empty = false, $force_refresh = true ) {
		// Clear cache to get fresh calendar list by default.
		if ( $force_refresh ) {
			delete_transient( 'apple_calendar_list_' . $agent_id );
		}

		$calendars            = self::get_list_of_calendars( $agent_id );
		$calendars_for_select = array();
		if ( $include_empty ) {
			$calendars_for_select[] = array(
				'value' => '',
				'label' => __( 'Do not sync', 'latepoint-apple-calendar' ),
			);
		}
		if ( ! empty( $calendars ) ) {
			foreach ( $calendars as $calendar ) {
				$calendars_for_select[] = array(
					'value' => $calendar['url'],
					'label' => $calendar['name'],
				);
			}
		}
		return $calendars_for_select;
	}

	// ==========================================.
	// SERVICE SETTINGS.
	// ==========================================.

	/**
	 * Check if customer should be included as attendee in calendar event
	 *
	 * @param int $service_id Service ID.
	 * @return bool True if enabled
	 *
	 * @since 1.0.0
	 */
	public static function should_customer_be_included_as_attendee( $service_id ) {
		return OsMetaHelper::get_service_meta_by_key( 'add_customer_to_apple_event', $service_id ) === 'on';
	}

	/**
	 * Check if agent should be included as attendee in calendar event
	 *
	 * @param int $service_id Service ID.
	 * @return bool True if enabled
	 *
	 * @since 1.0.0
	 */
	public static function should_agent_be_included_as_attendee( $service_id ) {
		return OsMetaHelper::get_service_meta_by_key( 'add_agent_to_apple_event', $service_id ) === 'on';
	}

	// ==========================================.
	// BOOKING SYNC.
	// ==========================================.

	/**
	 * Create or update a booking in Apple Calendar
	 *
	 * @param int      $booking_id Booking ID.
	 * @param int|null $agent_id Optional agent ID (will use booking's agent if not provided).
	 * @return bool True on success, false on failure
	 *
	 * @since 1.0.0
	 */
	public static function create_or_update_booking_in_apple_calendar( $booking_id, $agent_id = null ) {
		// Load booking.
		$booking = new OsBookingModel( $booking_id );
		if ( ! $booking->id ) {
			return false;
		}

		// Use booking's agent if not specified.
		if ( ! $agent_id ) {
			$agent_id = $booking->agent_id;
		}

		// Check if agent is connected.
		if ( ! self::is_agent_connected_to_apple_calendar( $agent_id ) ) {
			return false;
		}

		// Get selected calendar for pushing.
		$calendar_id = self::get_selected_calendar_id_for_push( $agent_id );
		if ( ! $calendar_id ) {
			return false;
		}

		// Generate ICS data from booking.
		$existing_event_uid = OsMetaHelper::get_booking_meta_by_key( 'apple_calendar_event_uid', $booking_id );
		$event_uid          = $existing_event_uid ? $existing_event_uid : 'latepoint-booking-' . $booking_id . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );

		$ics_data = OsAppleCalendarICSHelper::generate_ics_from_booking( $booking, $event_uid );

		// Check if event already exists by looking at booking meta (not the events table).
		// The events table is for external events only, not for LatePoint bookings.
		$existing_caldav_url  = OsMetaHelper::get_booking_meta_by_key( 'apple_calendar_caldav_url', $booking_id );
		$existing_caldav_etag = OsMetaHelper::get_booking_meta_by_key( 'apple_calendar_caldav_etag', $booking_id );

		if ( $existing_event_uid && $existing_caldav_url ) {
			// Update existing event.
			$result = OsAppleCalendarCalDAVHelper::update_event(
				$agent_id,
				$existing_caldav_url,
				$ics_data,
				$existing_caldav_etag ? $existing_caldav_etag : ''
			);

			if ( is_wp_error( $result ) ) {
				return false;
			}

			// Update etag in booking meta.
			if ( ! empty( $result['etag'] ) ) {
				OsMetaHelper::save_booking_meta_by_key( 'apple_calendar_caldav_etag', $result['etag'], $booking_id );
			}
		} else {
			// Create new event.
			$result = OsAppleCalendarCalDAVHelper::create_event(
				$agent_id,
				$calendar_id,
				$ics_data,
				$event_uid
			);

			// If create fails with 412 (event already exists), try to update it.
			if ( is_wp_error( $result ) && strpos( $result->get_error_message(), '412' ) !== false ) {
				// Event exists in Apple Calendar but not in our database.
				// Build the event URL and try to update without etag.
				$event_url = rtrim( $calendar_id, '/' ) . '/' . $event_uid . '.ics';
				$result    = OsAppleCalendarCalDAVHelper::update_event(
					$agent_id,
					$event_url,
					$ics_data,
					'' // No etag since we don't have the existing event.
				);
			}

			if ( is_wp_error( $result ) ) {
				return false;
			}

			// Store event info in booking meta (NOT in the events table).
			// The events table is for external calendar events only.
			$caldav_url = $result['event_url'] ?? ( rtrim( $calendar_id, '/' ) . '/' . $event_uid . '.ics' );
			OsMetaHelper::save_booking_meta_by_key( 'apple_calendar_event_uid', $event_uid, $booking_id );
			OsMetaHelper::save_booking_meta_by_key( 'apple_calendar_caldav_url', $caldav_url, $booking_id );
			if ( ! empty( $result['etag'] ) ) {
				OsMetaHelper::save_booking_meta_by_key( 'apple_calendar_caldav_etag', $result['etag'], $booking_id );
			}
		}

		return true;
	}

	/**
	 * Delete a booking from Apple Calendar
	 *
	 * @param int      $booking_id Booking ID.
	 * @param int|null $agent_id Optional agent ID.
	 * @return bool True on success, false on failure
	 *
	 * @since 1.0.0
	 */
	public static function delete_booking_from_apple_calendar( $booking_id, $agent_id = null ) {
		// Get event info from booking meta (not from the events table).
		$event_uid = OsMetaHelper::get_booking_meta_by_key( 'apple_calendar_event_uid', $booking_id );
		if ( ! $event_uid ) {
			return false;
		}

		// Load booking to get agent_id if not provided.
		if ( ! $agent_id ) {
			$booking = new OsBookingModel( $booking_id );
			if ( ! $booking->id ) {
				return false;
			}
			$agent_id = $booking->agent_id;
		}

		// Check if agent is connected.
		if ( ! self::is_agent_connected_to_apple_calendar( $agent_id ) ) {
			return false;
		}

		// Get CalDAV URL and etag from booking meta.
		$caldav_url  = OsMetaHelper::get_booking_meta_by_key( 'apple_calendar_caldav_url', $booking_id );
		$caldav_etag = OsMetaHelper::get_booking_meta_by_key( 'apple_calendar_caldav_etag', $booking_id );

		$delete_success = false;

		if ( $caldav_url ) {
			// Delete from Apple Calendar using stored CalDAV URL.
			$result = OsAppleCalendarCalDAVHelper::delete_event(
				$agent_id,
				$caldav_url,
				$caldav_etag ? $caldav_etag : ''
			);

			// Check if deletion was successful.
			if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
				$delete_success = true;
			}
		} else {
			// No CalDAV URL stored, try to delete directly from Apple Calendar.
			// by constructing the event URL from the calendar and UID.
			$calendar_id = self::get_selected_calendar_id_for_push( $agent_id );
			if ( $calendar_id ) {
				$event_url = rtrim( $calendar_id, '/' ) . '/' . $event_uid . '.ics';
				$result    = OsAppleCalendarCalDAVHelper::delete_event(
					$agent_id,
					$event_url,
					'' // No etag available.
				);

				if ( ! is_wp_error( $result ) && isset( $result['success'] ) && $result['success'] ) {
						$delete_success = true;
				}
			}
		}

		// Only delete booking meta if CalDAV delete was successful.
		if ( $delete_success ) {
			OsMetaHelper::delete_booking_meta( 'apple_calendar_event_uid', $booking_id );
			OsMetaHelper::delete_booking_meta( 'apple_calendar_caldav_url', $booking_id );
			OsMetaHelper::delete_booking_meta( 'apple_calendar_caldav_etag', $booking_id );
		}

		return $delete_success;
	}

	/**
	 * Sync Apple Calendar events to local database
	 *
	 * @param int                       $agent_id Agent ID.
	 * @param string|\DateTimeInterface $start_date Start date (Y-m-d) or DateTime object.
	 * @param string|\DateTimeInterface $end_date End date (Y-m-d) or DateTime object.
	 * @return array Sync statistics
	 *
	 * @since 1.0.0
	 */
	public static function sync_events_from_apple_calendar( $agent_id, $start_date, $end_date ) {
		$stats = array(
			'added'   => 0,
			'updated' => 0,
			'deleted' => 0,
			'errors'  => 0,
		);

		// Ensure dates are strings in Y-m-d format.
		if ( $start_date instanceof \DateTimeInterface ) {
			$start_date = $start_date->format( 'Y-m-d' );
		}
		if ( $end_date instanceof \DateTimeInterface ) {
			$end_date = $end_date->format( 'Y-m-d' );
		}

		// Check if agent is connected.
		if ( ! self::is_agent_connected_to_apple_calendar( $agent_id ) ) {
			return $stats;
		}

		// Get selected calendars for pulling.
		$calendar_ids = self::get_selected_calendar_ids_for_pull( $agent_id );
		if ( ! $calendar_ids ) {
			return $stats;
		}

		// Convert comma-separated string to array.
		$calendar_id_array = explode( ',', $calendar_ids );

		foreach ( $calendar_id_array as $calendar_id ) {
			$calendar_id = trim( $calendar_id );
			if ( empty( $calendar_id ) ) {
				continue;
			}

			// Get stored sync token for this calendar.
			$sync_token = self::get_sync_token( $calendar_id, $agent_id );

			// Use incremental sync if we have a sync token.
			$caldav_response = OsAppleCalendarCalDAVHelper::get_events_incremental(
				$agent_id,
				$calendar_id,
				$sync_token,
				$start_date,
				$end_date
			);

			// Handle invalid sync token - fall back to full sync.
			if ( is_wp_error( $caldav_response ) ) {
				if ( $caldav_response->get_error_code() === 'invalid_sync_token' ) {
					// Delete invalid sync token and retry with full sync.
					self::delete_sync_token( $calendar_id, $agent_id );
					$caldav_response = OsAppleCalendarCalDAVHelper::get_events_incremental(
						$agent_id,
						$calendar_id,
						null,
						$start_date,
						$end_date
					);
				}

				// If still error, log it and continue to next calendar.
				if ( is_wp_error( $caldav_response ) ) {
					++$stats['errors'];
					continue;
				}
			}

			// Extract events and new sync token from response.
			$caldav_events  = $caldav_response['events'] ?? array();
			$new_sync_token = $caldav_response['sync_token'] ?? null;
			$deleted_hrefs  = $caldav_response['deleted_hrefs'] ?? array();

			// Process deleted events.
			foreach ( $deleted_hrefs as $deleted_href ) {
				$apple_event   = new OsAppleCalendarEventModel();
				$deleted_event = $apple_event->where(
					array(
						'caldav_url' => $deleted_href,
						'agent_id'   => $agent_id,
					)
				)->set_limit( 1 )->get_results_as_models();

				if ( is_object( $deleted_event ) && $deleted_event instanceof OsAppleCalendarEventModel && $deleted_event->id ) {
					$deleted_event->delete();
					++$stats['deleted'];
				}
			}

			// Process added/updated events.
			foreach ( $caldav_events as $event_data ) {
				// Skip events created by LatePoint (these are stored in booking meta, not in the events table).
				// LatePoint-created events have UIDs starting with 'latepoint-booking-'.
				if ( ! empty( $event_data['apple_event_id'] ) && strpos( $event_data['apple_event_id'], 'latepoint-booking-' ) === 0 ) {
					continue;
				}

				// Check if event already exists.
				$apple_event    = new OsAppleCalendarEventModel();
				$existing_event = $apple_event->get_by_event_id( $event_data['apple_event_id'], $agent_id );

				if ( $existing_event ) {
					// Update if ETag changed.
					if ( $existing_event->caldav_etag !== $event_data['caldav_etag'] ) {
						$existing_event->set_data( $event_data );
						$existing_event->save();

						// Update recurrence rules if present.
						if ( isset( $event_data['recurrence'] ) && ! empty( $event_data['recurrence'] ) ) {
							$recurrence_models = array();
							foreach ( $event_data['recurrence'] as $recurrence_rule ) {
								$recurrence_model              = new OsAppleCalendarEventRecurrenceModel();
								$recurrence_model->lp_event_id = $existing_event->id;
								$recurrence_model->frequency   = $recurrence_rule['frequency'] ?? '';
								$recurrence_model->interval    = $recurrence_rule['interval'] ?? 1;
								$recurrence_model->count       = $recurrence_rule['count'] ?? null;
								$recurrence_model->until       = $recurrence_rule['until'] ?? null;
								$recurrence_model->weekday     = $recurrence_rule['weekday'] ?? null;

								// Fill in missing weekday for weekly recurrences.
								if ( ! empty( $recurrence_model->frequency ) && $recurrence_model->frequency === 'weekly' ) {
									if ( empty( $recurrence_model->weekday ) && isset( $event_data['start_date'] ) ) {
										$start_date = OsWpDateTime::os_createFromFormat( 'Y-m-d', $event_data['start_date'] );
										if ( $start_date ) {
											$recurrence_model->weekday = self::get_weekday_name_by_iso8601_number( (int) $start_date->format( 'N' ) );
										}
									}
								}

								$recurrence_models[] = $recurrence_model;
							}
							$existing_event->update_recurrences( $recurrence_models );
						}

						++$stats['updated'];
					}
				} else {
					// Create new event.
					$new_event              = new OsAppleCalendarEventModel();
					$event_data['agent_id'] = $agent_id;
					$new_event->set_data( $event_data );
					$new_event->save();
					++$stats['added'];
				}
			}

			// Save new sync token if available.
			if ( $new_sync_token ) {
				self::save_sync_token( $calendar_id, $agent_id, $new_sync_token );
			}

			// Update last sync timestamp.
			self::update_last_sync_time( $calendar_id, $agent_id );
		}

		return $stats;
	}

	/**
	 * Check if a booking status is syncable to Apple Calendar
	 *
	 * @param string $booking_status The booking status to check.
	 * @return bool True if the status should be synced
	 *
	 * @since 1.0.0
	 */
	public static function is_booking_status_syncable( $booking_status ) {
		return in_array( $booking_status, self::get_enabled_booking_statuses() );
	}

	/**
	 * Get the list of booking statuses that should be synced to Apple Calendar
	 * By default, uses LatePoint's timeslot blocking statuses
	 *
	 * @return array Array of booking statuses that should be synced
	 *
	 * @since 1.0.0
	 */
	public static function get_enabled_booking_statuses() {
		$timeslot_blocking_statuses = OsBookingHelper::get_timeslot_blocking_statuses();

		/**
		 * Filter the list of booking statuses that are enabled for synchronization with Apple Calendar
		 *
		 * @since 1.0.0
		 * @hook latepoint_apple_calendar_enabled_booking_statuses
		 *
		 * @param array<string> $statuses Array of enabled booking statuses.
		 * @return array<string> Filtered array of enabled booking statuses
		 */
		return apply_filters( 'latepoint_apple_calendar_enabled_booking_statuses', $timeslot_blocking_statuses );
	}

	/**
	 * Get a specific Apple Calendar event record by its Apple event ID
	 *
	 * @param string $apple_event_id The Apple Calendar event ID.
	 * @return OsAppleCalendarEventModel|false The event model or false if not found
	 *
	 * @since 1.0.0
	 */
	public static function get_record_by_apple_event_id( $apple_event_id ) {
		$event_model = new OsAppleCalendarEventModel();
		$result      = $event_model->where( array( 'apple_event_id' => $apple_event_id ) )->set_limit( 1 )->get_results_as_models();

		// When limit=1, get_results_as_models() returns a single object, not an array.
		if ( is_object( $result ) && $result instanceof OsAppleCalendarEventModel && $result->id ) {
			return $result;
		}

		return false;
	}

	/**
	 * Check if an event is a full day event
	 *
	 * @param int $start_time Start time in minutes.
	 * @param int $end_time End time in minutes.
	 * @return bool True if full day event
	 *
	 * @since 1.0.0
	 */
	public static function is_full_day_event( $start_time, $end_time ) {
		return $start_time === 0 && $end_time === 1439;
	}

	/**
	 * Get Apple Calendar events from LOCAL database for a specific date
	 * This queries the synced events table, not the live CalDAV API
	 *
	 * @param string|\DateTimeInterface $target_date Target date (Y-m-d) or DateTime object.
	 * @param int|array|false           $agent_id Agent ID(s) or false for all agents.
	 * @return array Array of event models
	 *
	 * @since 1.0.0
	 */
	public static function get_local_events_for_date( $target_date, $agent_id = false ) {
		$events_model = new OsAppleCalendarEventModel();

		// If target_date is already a DateTime object, use it directly, otherwise create from string.
		if ( $target_date instanceof \DateTimeInterface ) {
			$target_date_obj = $target_date;
		} else {
			if ( ! OsTimeHelper::is_valid_date( $target_date ) ) {
				return array();
			}
			$target_date_obj = OsWpDateTime::CreateFromFormat( 'Y-m-d', $target_date );
		}
		if ( ! $target_date_obj ) {
			return array();
		}

		$weekday = OsTimeHelper::get_db_weekday_by_number( (int) $target_date_obj->format( 'N' ) );
		$events_model->escape_by_ref( $weekday );
		$weekday_relative = ceil( $target_date_obj->format( 'j' ) / 7 ) . $weekday;

		if ( $target_date_obj->format( 't' ) - $target_date_obj->format( 'j' ) < 7 ) {
			$last_weekday_query = " OR (`weekday` = '-1{$weekday}') ";
		} else {
			$last_weekday_query = '';
		}

		$formatted_date = $target_date_obj->format( 'Y-m-d' );
		$events_model->escape_by_ref( $formatted_date );

		$query = 'SELECT events.start_date, events.end_date, events.start_time, events.end_time, events.id, events.summary, agent_id FROM ' . LATEPOINT_TABLE_APPLE_CALENDAR_EVENTS . ' as events
			LEFT JOIN ' . LATEPOINT_TABLE_APPLE_CALENDAR_RECURRENCES . " as recs ON events.id = recs.lp_event_id
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
				$agent_id = intval( $agent_id );
				$query   .= " AND agent_id = {$agent_id}";
			}
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly escaped using escape_by_ref.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! $results ) {
			return array();
		}

		$events = array();
		foreach ( $results as $result ) {
			$event = new OsAppleCalendarEventModel();
			$event->load_from_row_data( $result );
			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Create or update an Apple Calendar event in database
	 *
	 * @param string $apple_event_id Apple Calendar event ID.
	 * @param string $calendar_url Calendar URL.
	 * @param int    $agent_id Agent ID.
	 * @return bool True if successful, false otherwise
	 *
	 * @since 1.0.0
	 */
	public static function create_or_update_apple_event_in_db( $apple_event_id, $calendar_url, $agent_id ) {
		if ( ! $apple_event_id || ! $agent_id || ! $calendar_url ) {
			return false;
		}

		// Construct full event URL (calendar URL + event UID.ics).
		$event_url = rtrim( $calendar_url, '/' ) . '/' . $apple_event_id . '.ics';

		// Fetch event from Apple Calendar.
		$event_data = OsAppleCalendarCalDAVHelper::get_event( $agent_id, $event_url );
		if ( is_wp_error( $event_data ) || ! $event_data ) {
			return false;
		}

		// Check if event already exists in database.
		$event_model    = new OsAppleCalendarEventModel();
		$existing_event = $event_model->where(
			array(
				'apple_event_id'    => $apple_event_id,
				'apple_calendar_id' => $calendar_url,
			)
		)->set_limit( 1 )->get_results_as_models();

		// When limit=1, get_results_as_models() returns a single object, not an array.
		if ( is_object( $existing_event ) && $existing_event instanceof OsAppleCalendarEventModel && $existing_event->id ) {
			// Update existing event.
			$event_in_db = $existing_event;
		} else {
			// Create new event.
			$event_in_db                    = new OsAppleCalendarEventModel();
			$event_in_db->apple_calendar_id = $calendar_url;
			$event_in_db->apple_event_id    = $apple_event_id;
		}

		// Update event data.
		$event_in_db->agent_id    = $agent_id;
		$event_in_db->summary     = $event_data['summary'] ?? '';
		$event_in_db->start_date  = $event_data['start_date'] ?? '';
		$event_in_db->end_date    = $event_data['end_date'] ?? '';
		$event_in_db->start_time  = $event_data['start_time'] ?? 0;
		$event_in_db->end_time    = $event_data['end_time'] ?? 0;
		$event_in_db->caldav_url  = $event_data['caldav_url'] ?? $event_url;
		$event_in_db->caldav_etag = $event_data['caldav_etag'] ?? '';

		// Save to database.
		$result = $event_in_db->save();

		if ( $result && isset( $event_data['recurrence'] ) && ! empty( $event_data['recurrence'] ) ) {
			// Save recurrence rules.
			// The ICS parser returns an array of recurrence rules (multiple rules if BYDAY has multiple weekdays).
			$recurrence_models = array();
			foreach ( $event_data['recurrence'] as $recurrence_rule ) {
				$recurrence_model              = new OsAppleCalendarEventRecurrenceModel();
				$recurrence_model->lp_event_id = $event_in_db->id;
				$recurrence_model->frequency   = $recurrence_rule['frequency'] ?? '';
				$recurrence_model->interval    = $recurrence_rule['interval'] ?? 1;
				$recurrence_model->count       = $recurrence_rule['count'] ?? null;
				$recurrence_model->until       = $recurrence_rule['until'] ?? null;
				$recurrence_model->weekday     = $recurrence_rule['weekday'] ?? null;

				// Fill in missing weekday for weekly recurrences (can happen with events created on mobile).
				if ( ! empty( $recurrence_model->frequency ) && $recurrence_model->frequency === 'weekly' ) {
					if ( empty( $recurrence_model->weekday ) && isset( $event_data['start_date'] ) ) {
						$start_date = OsWpDateTime::os_createFromFormat( 'Y-m-d', $event_data['start_date'] );
						if ( $start_date ) {
							$recurrence_model->weekday = self::get_weekday_name_by_iso8601_number( (int) $start_date->format( 'N' ) );
						}
					}
				}

				$recurrence_models[] = $recurrence_model;
			}
			$event_in_db->update_recurrences( $recurrence_models );
		}

		return $result;
	}

	/**
	 * Get weekday name (MO, TU, etc.) from ISO 8601 weekday number (1-7)
	 *
	 * @param int $number ISO 8601 weekday number (1 = Monday, 7 = Sunday).
	 * @return string Weekday name (MO, TU, WE, TH, FR, SA, SU)
	 *
	 * @since 1.0.0
	 */
	public static function get_weekday_name_by_iso8601_number( $number ) {
		$iso8601_weekdays = array(
			1 => 'MO', // Monday.
			2 => 'TU', // Tuesday.
			3 => 'WE', // Wednesday.
			4 => 'TH', // Thursday.
			5 => 'FR', // Friday.
			6 => 'SA', // Saturday.
			7 => 'SU', // Sunday.
		);
		return $iso8601_weekdays[ $number ] ?? '';
	}

	/**
	 * Unsync (delete) an Apple Calendar event from database
	 *
	 * @param string $apple_event_id Apple Calendar event ID.
	 * @return bool True if successful, false otherwise
	 *
	 * @since 1.0.0
	 */
	public static function unsync_apple_event_from_db( $apple_event_id ) {
		if ( ! $apple_event_id ) {
			return true;
		}

		$event_in_db      = new OsAppleCalendarEventModel();
		$events_to_unsync = $event_in_db->where( array( 'apple_event_id' => $apple_event_id ) )->get_results_as_models();

		if ( $events_to_unsync ) {
			foreach ( $events_to_unsync as $event_model ) {
				$event_model->delete();
			}
		}

		return true;
	}

	/**
	 * Check if auto-sync is enabled for a calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool True if auto-sync is enabled
	 *
	 * @since 1.0.0
	 */
	public static function is_auto_sync_enabled( $calendar_id, $agent_id ) {
		$auto_sync_calendars = OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_auto_sync_calendars', $agent_id );
		if ( empty( $auto_sync_calendars ) ) {
			return false;
		}

		$auto_sync_calendars_arr = explode( ',', $auto_sync_calendars );
		return in_array( $calendar_id, $auto_sync_calendars_arr );
	}

	/**
	 * Enable auto-sync for a calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function enable_auto_sync( $calendar_id, $agent_id ) {
		$auto_sync_calendars     = OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_auto_sync_calendars', $agent_id );
		$auto_sync_calendars_arr = $auto_sync_calendars ? explode( ',', $auto_sync_calendars ) : array();

		// Add calendar if not already in the list.
		if ( ! in_array( $calendar_id, $auto_sync_calendars_arr ) ) {
			$auto_sync_calendars_arr[] = $calendar_id;
			$updated_calendars         = implode( ',', $auto_sync_calendars_arr );
			OsMetaHelper::save_agent_meta_by_key( 'apple_calendar_auto_sync_calendars', $updated_calendars, $agent_id );
		}

		return true;
	}

	/**
	 * Disable auto-sync for a calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function disable_auto_sync( $calendar_id, $agent_id ) {
		$auto_sync_calendars = OsMetaHelper::get_agent_meta_by_key( 'apple_calendar_auto_sync_calendars', $agent_id );
		if ( empty( $auto_sync_calendars ) ) {
			return true;
		}

		$auto_sync_calendars_arr = explode( ',', $auto_sync_calendars );

		// Remove calendar from the list.
		$updated_calendars_arr = array_diff( $auto_sync_calendars_arr, array( $calendar_id ) );
		$updated_calendars     = implode( ',', $updated_calendars_arr );

		OsMetaHelper::save_agent_meta_by_key( 'apple_calendar_auto_sync_calendars', $updated_calendars, $agent_id );

		return true;
	}

	/**
	 * Get sync-token for a specific calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return string|null Sync token or null if not found
	 *
	 * @since 1.0.0
	 */
	public static function get_sync_token( $calendar_id, $agent_id ) {
		// Create a unique meta key for each calendar's sync token.
		$meta_key = 'apple_calendar_sync_token_' . md5( $calendar_id );
		return OsMetaHelper::get_agent_meta_by_key( $meta_key, $agent_id );
	}

	/**
	 * Save sync-token for a specific calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @param string $sync_token Sync token to save.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function save_sync_token( $calendar_id, $agent_id, $sync_token ) {
		// Create a unique meta key for each calendar's sync token.
		$meta_key = 'apple_calendar_sync_token_' . md5( $calendar_id );
		OsMetaHelper::save_agent_meta_by_key( $meta_key, $sync_token, $agent_id );
		return true;
	}

	/**
	 * Delete sync-token for a specific calendar (force full resync next time)
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function delete_sync_token( $calendar_id, $agent_id ) {
		// Create a unique meta key for each calendar's sync token.
		$meta_key = 'apple_calendar_sync_token_' . md5( $calendar_id );
		OsMetaHelper::delete_agent_meta_by_key( $meta_key, $agent_id );
		return true;
	}

	/**
	 * Get last sync timestamp for a calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return int|null Unix timestamp or null if never synced
	 *
	 * @since 1.0.0
	 */
	public static function get_last_sync_time( $calendar_id, $agent_id ) {
		$meta_key  = 'apple_calendar_last_sync_' . md5( $calendar_id );
		$timestamp = OsMetaHelper::get_agent_meta_by_key( $meta_key, $agent_id );
		return $timestamp ? (int) $timestamp : null;
	}

	/**
	 * Update last sync timestamp for a calendar
	 *
	 * @param string $calendar_id Calendar URL/ID.
	 * @param int    $agent_id Agent ID.
	 * @return bool True on success
	 *
	 * @since 1.0.0
	 */
	public static function update_last_sync_time( $calendar_id, $agent_id ) {
		$meta_key = 'apple_calendar_last_sync_' . md5( $calendar_id );
		OsMetaHelper::save_agent_meta_by_key( $meta_key, time(), $agent_id );
		return true;
	}

	/**
	 * Log a message to the error log with Apple Calendar prefix
	 *
	 * @param string $message The message to log.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function error_log( $message ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intended for debugging.
		error_log( '[LatePoint Apple Calendar] ' . $message );
	}
}
