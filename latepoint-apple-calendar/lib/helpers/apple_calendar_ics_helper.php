<?php
/**
 * Apple Calendar ICS Helper
 *
 * Handles ICS (iCalendar) file format parsing and generation for Apple Calendar events.
 * Provides parsing and generation of ICS files following RFC 5545 specification.
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Prevent redeclaration.
if ( class_exists( 'OsAppleCalendarICSHelper' ) ) {
	return;
}

/**
 * Apple Calendar ICS Helper Class
 *
 * Handles parsing and generation of ICS (iCalendar) format for Apple Calendar integration.
 *
 * @since 1.0.0
 */
class OsAppleCalendarICSHelper {
	/**
	 * Parse ICS data to event array
	 *
	 * @param string $ics_data Raw ICS data.
	 * @return array|false Event data array or false on failure
	 *
	 * @since 1.0.0
	 */
	public static function parse_ics_to_event_data( $ics_data ) {
		if ( empty( $ics_data ) ) {
			return false;
		}

		$event = array();

		// Extract VEVENT block.
		if ( ! preg_match( '/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics_data, $matches ) ) {
			return false;
		}

		$vevent = $matches[1];

		// Parse UID.
		if ( preg_match( '/UID:(.+)$/m', $vevent, $matches ) ) {
			$event['apple_event_id'] = trim( $matches[1] );
		}

		// Parse Summary (title).
		if ( preg_match( '/SUMMARY:(.+)$/m', $vevent, $matches ) ) {
			$event['summary'] = self::decode_ics_value( trim( $matches[1] ) );
		} else {
			$event['summary'] = '';
		}

		// Parse Description.
		if ( preg_match( '/DESCRIPTION:(.+)$/m', $vevent, $matches ) ) {
			$event['description'] = self::decode_ics_value( trim( $matches[1] ) );
		}

		// Parse Start Date/Time.
		$start_parsed = self::parse_datetime_property( $vevent, 'DTSTART' );
		if ( $start_parsed ) {
			$event['start_date']         = $start_parsed['date'];
			$event['start_time']         = $start_parsed['time_minutes'];
			$event['start_datetime_utc'] = $start_parsed['datetime_utc'];
			$event['is_all_day']         = $start_parsed['is_all_day'];
		}

		// Parse End Date/Time.
		$end_parsed = self::parse_datetime_property( $vevent, 'DTEND' );
		if ( $end_parsed ) {
			$event['end_date']         = $end_parsed['date'];
			$event['end_time']         = $end_parsed['time_minutes'];
			$event['end_datetime_utc'] = $end_parsed['datetime_utc'];
		} elseif ( isset( $event['start_date'] ) ) {
			// No end date, use start date.
			$event['end_date']         = $event['start_date'];
			$event['end_time']         = $event['start_time'] ?? 0;
			$event['end_datetime_utc'] = $event['start_datetime_utc'] ?? '';
		}

		// Parse Recurrence Rule.
		$event['recurrence'] = self::parse_recurrence_rule( $vevent );

		return $event;
	}

	/**
	 * Parse datetime property from ICS
	 *
	 * @param string $vevent VEVENT content.
	 * @param string $property Property name (DTSTART or DTEND).
	 * @return array|false Parsed datetime array or false
	 *
	 * @since 1.0.0
	 */
	private static function parse_datetime_property( $vevent, $property ) {
		// Try to match with timezone.
		if ( preg_match( '/' . $property . ';TZID=([^:]+):(.+)$/m', $vevent, $matches ) ) {
			$timezone_id  = trim( $matches[1] );
			$datetime_str = trim( $matches[2] );

			try {
				$tz = new DateTimeZone( $timezone_id );
				$dt = DateTime::createFromFormat( 'Ymd\THis', $datetime_str, $tz );

				if ( $dt ) {
					return self::datetime_to_array( $dt, false );
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fallback to UTC parsing - ignore timezone parsing errors.
			}
		}

		// Try to match UTC datetime.
		if ( preg_match( '/' . $property . ':(.+)Z$/m', $vevent, $matches ) ) {
			$datetime_str = trim( $matches[1] );
			$dt           = DateTime::createFromFormat( 'Ymd\THis', $datetime_str, new DateTimeZone( 'UTC' ) );

			if ( $dt ) {
				return self::datetime_to_array( $dt, false );
			}
		}

		// Try to match date only (all-day event).
		if ( preg_match( '/' . $property . ';VALUE=DATE:(.+)$/m', $vevent, $matches ) ) {
			$date_str = trim( $matches[1] );
			$dt       = DateTime::createFromFormat( 'Ymd', $date_str, new DateTimeZone( 'UTC' ) );

			if ( $dt ) {
				return self::datetime_to_array( $dt, true );
			}
		}

		// Try simple date format.
		if ( preg_match( '/' . $property . ':(\d{8})$/m', $vevent, $matches ) ) {
			$date_str = trim( $matches[1] );
			$dt       = DateTime::createFromFormat( 'Ymd', $date_str, new DateTimeZone( 'UTC' ) );

			if ( $dt ) {
				return self::datetime_to_array( $dt, true );
			}
		}

		return false;
	}

	/**
	 * Convert DateTime object to event array format
	 *
	 * @param DateTime $dt DateTime object.
	 * @param bool     $is_all_day Whether this is an all-day event.
	 * @return array Datetime data array
	 *
	 * @since 1.0.0
	 */
	private static function datetime_to_array( $dt, $is_all_day = false ) {
		// Convert to local timezone.
		$local_tz = wp_timezone();
		$local_dt = clone $dt;
		$local_dt->setTimezone( $local_tz );

		$data = array(
			'date'         => $local_dt->format( 'Y-m-d' ),
			'datetime_utc' => $dt->format( 'Y-m-d H:i:s' ),
			'is_all_day'   => $is_all_day,
		);

		if ( $is_all_day ) {
			$data['time_minutes'] = 0;
		} else {
			// Convert to minutes since midnight.
			$hours                = (int) $local_dt->format( 'G' );
			$minutes              = (int) $local_dt->format( 'i' );
			$data['time_minutes'] = ( $hours * 60 ) + $minutes;
		}

		return $data;
	}

	/**
	 * Parse recurrence rule (RRULE)
	 * Returns an array of recurrence rules - if BYDAY has multiple days, they are split into separate rules
	 *
	 * @param string $vevent VEVENT content.
	 * @param bool   $split_weekdays Whether to split multiple weekdays into separate recurrence rules.
	 * @return array|null Array of recurrence data or null
	 *
	 * @since 1.0.0
	 */
	private static function parse_recurrence_rule( $vevent, $split_weekdays = true ) {
		if ( ! preg_match( '/RRULE:(.+)$/m', $vevent, $matches ) ) {
			return null;
		}

		$rrule_str   = trim( $matches[1] );
		$rrule_parts = explode( ';', $rrule_str );
		$rrule       = array();

		foreach ( $rrule_parts as $part ) {
			[$key, $value]               = explode( '=', $part, 2 );
			$rrule[ strtoupper( $key ) ] = $value;
		}

		$recurrence = array();

		// Frequency (DAILY, WEEKLY, MONTHLY, YEARLY).
		if ( isset( $rrule['FREQ'] ) ) {
			$recurrence['frequency'] = strtolower( $rrule['FREQ'] );
		}

		// Interval.
		if ( isset( $rrule['INTERVAL'] ) ) {
			$recurrence['interval'] = (int) $rrule['INTERVAL'];
		} else {
			$recurrence['interval'] = 1;
		}

		// Count.
		if ( isset( $rrule['COUNT'] ) ) {
			$recurrence['count'] = (int) $rrule['COUNT'];
		}

		// Until date.
		if ( isset( $rrule['UNTIL'] ) ) {
			$until_dt = DateTime::createFromFormat( 'Ymd\THis\Z', $rrule['UNTIL'] );
			if ( ! $until_dt ) {
				$until_dt = DateTime::createFromFormat( 'Ymd', $rrule['UNTIL'] );
			}
			if ( $until_dt ) {
				$recurrence['until'] = $until_dt->format( 'Y-m-d' );
			}
		}

		// By Day (for weekly/monthly) - split into separate recurrence rules if multiple days.
		$recurrences = array();
		if ( isset( $rrule['BYDAY'] ) ) {
			$weekdays = explode( ',', $rrule['BYDAY'] );
			if ( $split_weekdays && ( count( $weekdays ) > 1 ) ) {
				// Create separate recurrence rule for each weekday.
				foreach ( $weekdays as $byday ) {
					$rec_copy            = $recurrence;
					$rec_copy['weekday'] = trim( $byday );
					$recurrences[]       = $rec_copy;
				}
			} else {
				$recurrence['weekday'] = $rrule['BYDAY'];
				$recurrences[]         = $recurrence;
			}
		} else {
			$recurrences[] = $recurrence;
		}

		return $recurrences;
	}

	/**
	 * Decode ICS value (handle escaped characters and encoding)
	 *
	 * @param string $value ICS value.
	 * @return string Decoded value
	 *
	 * @since 1.0.0
	 */
	private static function decode_ics_value( $value ) {
		// Replace escaped characters.
		$value = str_replace( '\\n', "\n", $value );
		$value = str_replace( '\\,', ',', $value );
		$value = str_replace( '\\;', ';', $value );
		return str_replace( '\\\\', '\\', $value );
	}

	/**
	 * Encode ICS value (escape special characters)
	 *
	 * @param string $value Plain text value.
	 * @return string Encoded ICS value
	 *
	 * @since 1.0.0
	 */
	private static function encode_ics_value( $value ) {
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\\;', $value );
		$value = str_replace( ',', '\\,', $value );
		return str_replace( "\n", '\\n', $value );
	}

	/**
	 * Generate ICS data from LatePoint booking
	 *
	 * @param OsBookingModel $booking Booking object.
	 * @param string         $event_uid Optional event UID (will generate if not provided).
	 * @return string ICS data
	 *
	 * @since 1.0.0
	 */
	public static function generate_ics_from_booking( $booking, $event_uid = '' ) {
		if ( empty( $event_uid ) ) {
			$event_uid = 'latepoint-booking-' . $booking->id . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
		}

		// Get event templates.
		$title_template       = OsAppleCalendarHelper::get_event_title_template();
		$description_template = OsAppleCalendarHelper::get_event_description_template();

		// Get related entities for template replacement.
		$order = $booking->get_order();

		// Prepare variables for template replacement.
		$template_vars = array(
			'customer'    => $booking->customer,
			'agent'       => $booking->agent,
			'booking'     => $booking,
			'service'     => $booking->service,
			'order'       => $order,
			'transaction' => $order ? ( $order->get_transactions()[0] ?? null ) : null,
		);

		// Replace booking variables in title and description.
		$title       = OsReplacerHelper::replace_all_vars( $title_template, $template_vars );
		$description = OsReplacerHelper::replace_all_vars( $description_template, $template_vars );

		// Get start and end datetime.
		$start_datetime = new OsWpDateTime( $booking->start_date . ' ' . OsTimeHelper::minutes_to_hours_and_minutes( $booking->start_time ) );

		// Calculate end date - bookings may not have end_date property, use start_date for same-day bookings.
		$end_date     = ! empty( $booking->end_date ) ? $booking->end_date : $booking->start_date;
		$end_datetime = new OsWpDateTime( $end_date . ' ' . OsTimeHelper::minutes_to_hours_and_minutes( $booking->end_time ) );

		// Convert to UTC.
		$start_datetime->setTimezone( new DateTimeZone( 'UTC' ) );
		$end_datetime->setTimezone( new DateTimeZone( 'UTC' ) );

		// Build ICS.
		$ics  = "BEGIN:VCALENDAR\r\n";
		$ics .= "VERSION:2.0\r\n";
		$ics .= "PRODID:-//LatePoint//Apple Calendar Integration//EN\r\n";
		$ics .= "CALSCALE:GREGORIAN\r\n";
		$ics .= "METHOD:PUBLISH\r\n";
		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= 'UID:' . $event_uid . "\r\n";
		$ics .= 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";
		$ics .= 'DTSTART:' . $start_datetime->format( 'Ymd\THis\Z' ) . "\r\n";
		$ics .= 'DTEND:' . $end_datetime->format( 'Ymd\THis\Z' ) . "\r\n";
		$ics .= 'SUMMARY:' . self::encode_ics_value( $title ) . "\r\n";

		if ( ! empty( $description ) ) {
			$ics .= 'DESCRIPTION:' . self::encode_ics_value( wp_strip_all_tags( $description ) ) . "\r\n";
		}

		// Add attendees if enabled.
		$service = $booking->service;
		if ( $service && OsAppleCalendarHelper::should_customer_be_included_as_attendee( $service->id ) ) {
			$customer = $booking->customer;
			if ( $customer && ! empty( $customer->email ) ) {
				$ics .= 'ATTENDEE;CN=' . self::encode_ics_value( $customer->full_name ) . ';RSVP=TRUE:mailto:' . $customer->email . "\r\n";
			}
		}

		// Add agent as organizer if enabled.
		if ( $service && OsAppleCalendarHelper::should_agent_be_included_as_attendee( $service->id ) ) {
			$agent = $booking->agent;
			if ( $agent && ! empty( $agent->email ) ) {
				$ics .= 'ORGANIZER;CN=' . self::encode_ics_value( $agent->full_name ) . ':mailto:' . $agent->email . "\r\n";
			}
		}

		$ics .= "STATUS:CONFIRMED\r\n";
		$ics .= "SEQUENCE:0\r\n";
		$ics .= "END:VEVENT\r\n";
		$ics .= "END:VCALENDAR\r\n";

		return $ics;
	}
}
