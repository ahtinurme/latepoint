<?php
/**
 * Apple Calendar Event Model
 *
 * Represents an event from Apple Calendar (iCloud Calendar) synced to LatePoint.
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Prevent redeclaration.
if ( class_exists( 'OsAppleCalendarEventModel' ) ) {
	return;
}

/**
 * Apple Calendar Event Model Class
 *
 * Handles Apple Calendar events synced with LatePoint.
 *
 * @since 1.0.0
 */
class OsAppleCalendarEventModel extends OsModel {
	/**
	 * Event ID.
	 *
	 * @var int|null
	 */
	public $id;

	/**
	 * Event summary/title.
	 *
	 * @var string
	 */
	public $summary;

	/**
	 * Start date (YYYY-MM-DD).
	 *
	 * @var string
	 */
	public $start_date;

	/**
	 * End date (YYYY-MM-DD).
	 *
	 * @var string
	 */
	public $end_date;

	/**
	 * Start time (minutes since midnight).
	 *
	 * @var int
	 */
	public $start_time;

	/**
	 * End time (minutes since midnight).
	 *
	 * @var int
	 */
	public $end_time;

	/**
	 * Agent ID.
	 *
	 * @var int
	 */
	public $agent_id;

	/**
	 * Apple Calendar ID.
	 *
	 * @var string
	 */
	public $apple_calendar_id;

	/**
	 * Apple Event ID (UID).
	 *
	 * @var string
	 */
	public $apple_event_id;

	/**
	 * CalDAV ETag.
	 *
	 * @var string
	 */
	public $caldav_etag;

	/**
	 * CalDAV URL.
	 *
	 * @var string
	 */
	public $caldav_url;

	/**
	 * Start datetime (UTC).
	 *
	 * @var string
	 */
	public $start_datetime_utc;

	/**
	 * End datetime (UTC).
	 *
	 * @var string
	 */
	public $end_datetime_utc;

	/**
	 * Created at timestamp.
	 *
	 * @var string
	 */
	public $created_at;

	/**
	 * Updated at timestamp.
	 *
	 * @var string
	 */
	public $updated_at;

	/**
	 * Constructor
	 *
	 * @param int|false $id Event ID.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_APPLE_CALENDAR_EVENTS;
		$this->nice_names = array();

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}

	/**
	 * Get formatted start date
	 *
	 * @return string Formatted date (e.g., "Jan 15, 2026").
	 *
	 * @since 1.0.0
	 */
	protected function get_nice_start_date() {
		$d = OsWpDateTime::os_createFromFormat( 'Y-m-d', $this->start_date );
		return $d->format( 'M j, Y' );
	}

	/**
	 * Get formatted start time
	 *
	 * @return string Formatted time (e.g., "2:00 pm").
	 *
	 * @since 1.0.0
	 */
	protected function get_nice_start_time() {
		return OsTimeHelper::minutes_to_hours_and_minutes( $this->start_time );
	}

	/**
	 * Define allowed parameters for this model
	 *
	 * @param string $role User role.
	 * @return array Allowed parameters.
	 *
	 * @since 1.0.0
	 */
	protected function allowed_params( $role = 'admin' ) {
		return array(
			'id',
			'summary',
			'start_date',
			'end_date',
			'start_time',
			'end_time',
			'agent_id',
			'apple_calendar_id',
			'apple_event_id',
			'caldav_etag',
			'caldav_url',
			'start_datetime_utc',
			'end_datetime_utc',
			'created_at',
			'updated_at',
		);
	}

	/**
	 * Define parameters to save
	 *
	 * @param string $role User role.
	 * @return array Parameters to save.
	 *
	 * @since 1.0.0
	 */
	protected function params_to_save( $role = 'admin' ) {
		return array(
			'id',
			'summary',
			'start_date',
			'end_date',
			'start_time',
			'end_time',
			'agent_id',
			'apple_calendar_id',
			'apple_event_id',
			'caldav_etag',
			'caldav_url',
			'start_datetime_utc',
			'end_datetime_utc',
			'created_at',
			'updated_at',
		);
	}

	/**
	 * Define validation rules
	 *
	 * @return array Validation rules.
	 *
	 * @since 1.0.0
	 */
	protected function properties_to_validate() {
		return array(
			'apple_event_id' => array( 'presence' ),
			'agent_id'       => array( 'presence' ),
			'start_date'     => array( 'presence' ),
		);
	}

	/**
	 * Delete event and associated recurrences
	 *
	 * @param int|false $id Event ID.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function delete( $id = false ) {
		if ( ! $id && isset( $this->id ) ) {
			$id = $this->id;
		}

		if ( $id && $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) ) ) {
			// Also delete associated recurrence rules.
			$this->db->delete( LATEPOINT_TABLE_APPLE_CALENDAR_RECURRENCES, array( 'lp_event_id' => $id ), array( '%d' ) );
			return true;
		}

		return false;
	}

	/**
	 * Update recurrence rules for this event
	 *
	 * @param array $recurrences Array of OsAppleCalendarEventRecurrenceModel objects.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update_recurrences( $recurrences ) {
		if ( ! $this->id ) {
			return;
		}

		// Delete existing recurrences.
		$this->db->delete( LATEPOINT_TABLE_APPLE_CALENDAR_RECURRENCES, array( 'lp_event_id' => $this->id ), array( '%d' ) );

		if ( ! empty( $recurrences ) ) {
			// Save new recurrences.
			foreach ( $recurrences as $recurrence ) {
				$recurrence->lp_event_id = $this->id;
				$recurrence->save();
			}
		}
	}

	/**
	 * Get event by Apple event ID and agent ID
	 *
	 * @param string $apple_event_id Apple Calendar event ID.
	 * @param int    $agent_id Agent ID.
	 * @return OsAppleCalendarEventModel|false Event object or false if not found.
	 *
	 * @since 1.0.0
	 */
	public function get_by_event_id( $apple_event_id, $agent_id ) {
		$event_model = new OsAppleCalendarEventModel();
		$result      = $event_model->where(
			array(
				'apple_event_id' => $apple_event_id,
				'agent_id'       => $agent_id,
			)
		)->set_limit( 1 )->get_results_as_models();

		// When limit=1, get_results_as_models() returns a single object, not an array.
		if ( is_object( $result ) && $result instanceof OsAppleCalendarEventModel && $result->id ) {
			return $result;
		}

		// Fallback: check if it's an array (in case behavior differs).
		if ( is_array( $result ) && ! empty( $result ) && $result[0] instanceof OsAppleCalendarEventModel ) {
			return $result[0];
		}

		return false;
	}
}
