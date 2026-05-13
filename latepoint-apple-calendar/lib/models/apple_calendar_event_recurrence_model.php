<?php
/**
 * Apple Calendar Event Recurrence Model
 *
 * Represents recurrence rules (RRULE) for Apple Calendar events.
 * Handles repeating events (daily, weekly, monthly, yearly).
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Prevent redeclaration.
if ( class_exists( 'OsAppleCalendarEventRecurrenceModel' ) ) {
	return;
}

/**
 * Apple Calendar Event Recurrence Model Class
 *
 * Handles recurrence rules for repeating Apple Calendar events.
 *
 * @since 1.0.0
 */
class OsAppleCalendarEventRecurrenceModel extends OsModel {
	/**
	 * Recurrence ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Until date (YYYY-MM-DD).
	 *
	 * @var string
	 */
	public $until;

	/**
	 * LatePoint event ID.
	 *
	 * @var int
	 */
	public $lp_event_id;

	/**
	 * Frequency (daily, weekly, monthly, yearly).
	 *
	 * @var string
	 */
	public $frequency;

	/**
	 * Interval (e.g., every 2 weeks).
	 *
	 * @var int
	 */
	public $interval;

	/**
	 * Count (number of occurrences).
	 *
	 * @var int
	 */
	public $count;

	/**
	 * Weekday (MO, TU, WE, TH, FR, SA, SU).
	 *
	 * @var string
	 */
	public $weekday;

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
	 * @param int|false $id Recurrence ID.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_APPLE_CALENDAR_RECURRENCES;
		$this->nice_names = array();

		if ( $id ) {
			$this->load_by_id( $id );
		}
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
			'until',
			'lp_event_id',
			'frequency',
			'interval',
			'count',
			'weekday',
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
			'until',
			'lp_event_id',
			'frequency',
			'interval',
			'count',
			'weekday',
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
			'lp_event_id' => array( 'presence' ),
			'frequency'   => array( 'presence' ),
		);
	}
}
