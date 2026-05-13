<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsOutlookCalendarEventModel' ) ) {

	/**
	 * Outlook calendar event model.
	 */
	class OsOutlookCalendarEventModel extends OsModel {
		/**
		 * Event ID.
		 *
		 * @var int
		 */
		public $id;

		/**
		 * Event summary.
		 *
		 * @var string
		 */
		public $summary;

		/**
		 * Start date.
		 *
		 * @var string
		 */
		public $start_date;

		/**
		 * End date.
		 *
		 * @var string
		 */
		public $end_date;

		/**
		 * Start time in minutes.
		 *
		 * @var int
		 */
		public $start_time;

		/**
		 * End time in minutes.
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
		 * Outlook calendar ID.
		 *
		 * @var string
		 */
		public $outlook_calendar_id;

		/**
		 * Outlook event ID.
		 *
		 * @var string
		 */
		public $outlook_event_id;

		/**
		 * Web link to event.
		 *
		 * @var string
		 */
		public $web_link;

		/**
		 * Start datetime in UTC.
		 *
		 * @var string
		 */
		public $start_datetime_utc;

		/**
		 * End datetime in UTC.
		 *
		 * @var string
		 */
		public $end_datetime_utc;

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
			$this->table_name = LATEPOINT_TABLE_OUTLOOK_EVENTS;
			$this->nice_names = array(
				'summary'             => __( 'Summary', 'latepoint-outlook-calendar' ),
				'start_date'          => __( 'Start Date', 'latepoint-outlook-calendar' ),
				'end_date'            => __( 'End Date', 'latepoint-outlook-calendar' ),
				'start_time'          => __( 'Start Time', 'latepoint-outlook-calendar' ),
				'end_time'            => __( 'End Time', 'latepoint-outlook-calendar' ),
				'agent_id'            => __( 'Agent', 'latepoint-outlook-calendar' ),
				'outlook_calendar_id' => __( 'Outlook Calendar ID', 'latepoint-outlook-calendar' ),
				'outlook_event_id'    => __( 'Outlook Event ID', 'latepoint-outlook-calendar' ),
			);

			if ( $id ) {
				$this->load_by_id( $id );
			}
		}

		/**
		 * Delete event and its recurrences.
		 *
		 * @param int|false $id Optional event ID.
		 * @return bool
		 */
		public function delete( $id = false ) {
			if ( ! $id && isset( $this->id ) ) {
				$id = $this->id;
			}
			if ( $id ) {
				// Delete recurrences.
				$recurrence = new OsOutlookEventRecurrenceModel();
				$recurrence->delete_where( array( 'lp_event_id' => $id ) );
			}
			return parent::delete( $id );
		}

		/**
		 * Update recurrences for this event.
		 *
		 * @param array $recurrences Recurrence data.
		 * @return bool
		 */
		public function update_recurrences( $recurrences ) {
			if ( ! $this->id ) {
				return false;
			}

			// Delete old recurrences.
			$recurrence = new OsOutlookEventRecurrenceModel();
			$recurrence->delete_where( array( 'lp_event_id' => $this->id ) );

			// Create new recurrences.
			if ( ! empty( $recurrences ) ) {
				foreach ( $recurrences as $recurrence_data ) {
					$recurrence              = new OsOutlookEventRecurrenceModel();
					$recurrence->lp_event_id = $this->id;
					$recurrence->frequency   = $recurrence_data->frequency ?? null;
					$recurrence->interval    = $recurrence_data->interval ?? null;
					$recurrence->count       = $recurrence_data->count ?? null;
					$recurrence->until       = $recurrence_data->until ?? null;
					$recurrence->weekday     = $recurrence_data->weekday ?? null;
					$recurrence->save();
				}
			}
			return true;
		}

		/**
		 * Get params to save.
		 *
		 * @param string $role User role.
		 * @return array
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
				'outlook_calendar_id',
				'outlook_event_id',
				'web_link',
				'start_datetime_utc',
				'end_datetime_utc',
			);
		}

		/**
		 * Get validation rules.
		 *
		 * @return array
		 */
		protected function properties_to_validate() {
			return array(
				'start_date' => array( 'presence' ),
				'start_time' => array( 'presence' ),
				'agent_id'   => array( 'presence' ),
			);
		}

		/**
		 * Get allowed params.
		 *
		 * @param string $role User role.
		 * @return array
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
				'outlook_calendar_id',
				'outlook_event_id',
				'web_link',
				'start_datetime_utc',
				'end_datetime_utc',
			);
		}

		/**
		 * Get associated agent.
		 *
		 * @return OsAgentModel
		 */
		protected function get_agent() {
			if ( $this->agent_id ) {
				if ( ! isset( $this->agent ) || ( isset( $this->agent->id ) && ( $this->agent->id !== $this->agent_id ) ) ) {
					$this->agent = new OsAgentModel( $this->agent_id );
				}
			} else {
				$this->agent = new OsAgentModel();
			}
			return $this->agent;
		}
	}

}
