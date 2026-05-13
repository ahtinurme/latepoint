<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsOutlookEventRecurrenceModel' ) ) {

	/**
	 * Outlook event recurrence model.
	 */
	class OsOutlookEventRecurrenceModel extends OsModel {
		/**
		 * Recurrence ID.
		 *
		 * @var int
		 */
		public $id;

		/**
		 * Recurrence end date.
		 *
		 * @var string
		 */
		public $until;

		/**
		 * Associated event ID.
		 *
		 * @var int
		 */
		public $lp_event_id;

		/**
		 * Recurrence frequency.
		 *
		 * @var string
		 */
		public $frequency;

		/**
		 * Recurrence interval.
		 *
		 * @var int
		 */
		public $interval;

		/**
		 * Occurrence count.
		 *
		 * @var int
		 */
		public $count;

		/**
		 * Day of week.
		 *
		 * @var string
		 */
		public $weekday;

		/**
		 * Updated timestamp.
		 *
		 * @var string
		 */
		public $updated_at;

		/**
		 * Created timestamp.
		 *
		 * @var string
		 */
		public $created_at;

		/**
		 * Constructor.
		 *
		 * @param int|false $id Optional model ID.
		 */
		public function __construct( $id = false ) {
			parent::__construct();
			$this->table_name = LATEPOINT_TABLE_OUTLOOK_RECURRENCES;
			$this->nice_names = array(
				'lp_event_id' => __( 'Event', 'latepoint-outlook-calendar' ),
				'frequency'   => __( 'Frequency', 'latepoint-outlook-calendar' ),
				'interval'    => __( 'Interval', 'latepoint-outlook-calendar' ),
				'count'       => __( 'Count', 'latepoint-outlook-calendar' ),
				'until'       => __( 'Until', 'latepoint-outlook-calendar' ),
				'weekday'     => __( 'Weekday', 'latepoint-outlook-calendar' ),
			);

			if ( $id ) {
				$this->load_by_id( $id );
			}
		}

		/**
		 * Get parameters to save.
		 *
		 * @param string $role User role.
		 * @return array
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
			);
		}

		/**
		 * Get properties to validate.
		 *
		 * @return array
		 */
		protected function properties_to_validate() {
			return array(
				'lp_event_id' => array( 'presence' ),
				'frequency'   => array( 'presence' ),
			);
		}

		/**
		 * Get allowed parameters.
		 *
		 * @param string $role User role.
		 * @return array
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
			);
		}
	}

}
