<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsOutlookCalendarController' ) ) {

	/**
	 * Outlook calendar controller.
	 */
	class OsOutlookCalendarController extends OsController {
		/**
		 * Constructor.
		 */
		public function __construct() {
			parent::__construct();

			$this->views_folder = OUTLOOK_CALENDAR_DIR . 'lib/views/outlook_calendar/';

			$this->action_access['public'] = array_merge( $this->action_access['public'], array( 'subscription_notification', 'save_access_token', 'delete_access_token', 'heartbeat' ) );
			$this->vars['page_header']     = __( 'Agents', 'latepoint-outlook-calendar' );
			$this->vars['breadcrumbs'][]   = array(
				'label' => __( 'Agents', 'latepoint-outlook-calendar' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'agents', 'index' ) ),
			);
		}

		/**
		 * Outlook calender authorization starting point
		 *
		 * @return void
		 */
		public function start_authorization() {
			nocache_headers();
			// We need to redirect user to external URL.
			wp_redirect( OsOutlookCalendarRelayHelper::get_connect_url_for_agent( $this->params['agent_id'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}

		/**
		 * Save access token received from relay server
		 */
		public function save_access_token() {
			$payload = @file_get_contents( 'php://input' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$data    = json_decode( $payload, true );

			if ( ! isset( $data['wp_latepoint_agent_token'] ) || ! isset( $data['outlook_calendar_access_token'] ) ) {
				exit;
			}

			$meta     = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value( 'agent_token_for_outlook_auth', $data['wp_latepoint_agent_token'] );

			if ( empty( $agent_id ) ) {
				exit;
			}

			$agent = new OsAgentModel( $agent_id );
			$agent->save_meta_by_key( 'outlook_cal_access_token_relay', $data['outlook_calendar_access_token'] );
			$agent->save_meta_by_key( 'outlook_cal_connection_id_relay', $data['outlook_calendar_connection_id'] );
		}

		/**
		 * Delete access token from agent meta
		 */
		public function delete_access_token() {
			$payload = @file_get_contents( 'php://input' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$data    = json_decode( $payload, true );

			if ( ! isset( $data['wp_latepoint_agent_token'] ) ) {
				exit;
			}

			$meta     = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value( 'agent_token_for_outlook_auth', $data['wp_latepoint_agent_token'] );
			if ( empty( $agent_id ) ) {
				exit;
			}

			$agent = new OsAgentModel( $agent_id );
			$agent->delete_meta_by_key( 'outlook_cal_access_token_relay' );
			$agent->delete_meta_by_key( 'outlook_cal_connection_id_relay' );
		}

		/**
		 * Heartbeat check for relay server
		 */
		public function heartbeat() {
			$payload = @file_get_contents( 'php://input' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$data    = json_decode( $payload, true );

			if ( empty( $data['wp_latepoint_agent_token'] ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Token is missing',
					),
					404
				);
			}

			$meta     = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value( 'agent_token_for_outlook_auth', $data['wp_latepoint_agent_token'] );
			if ( empty( $agent_id ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Agent not found',
					),
					404
				);
			}

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => 'Heartbeat detected',
				),
				200
			);
		}

		/**
		 * Sync manager for bookings - displays list of bookings to sync to Outlook Calendar
		 *
		 * @return void
		 */
		public function list_bookings_for_sync() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}
			$agent = new OsAgentModel( $this->params['agent_id'] );

			$this->vars['pre_page_back_link'] = OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) );
			$this->vars['pre_page_header']    = __( 'Sync for', 'latepoint-outlook-calendar' ) . ' ' . $agent->full_name;
			$this->vars['page_header']        = array(
				array(
					'label'  => __( 'Bookings', 'latepoint-outlook-calendar' ),
					'active' => true,
					'link'   => OsRouterHelper::build_link( array( 'outlook_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
				array(
					'label' => __( 'Outlook Events', 'latepoint-outlook-calendar' ),
					'link'  => OsRouterHelper::build_link( array( 'outlook_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
			);

			$this->vars['breadcrumbs'][] = array(
				'label' => $agent->full_name,
				'link'  => OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) ),
			);
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Sync upcoming bookings', 'latepoint-outlook-calendar' ),
				'link'  => false,
			);
			$this->vars['agent']         = $agent;
			$calendar_id_for_push        = false;

			if ( OsOutlookCalendarHelper::is_agent_connected_to_outlook( $agent->id ) ) {
				$this->vars['is_outlook_calendar_authorized'] = true;
				$calendar_id_for_push                         = OsOutlookCalendarHelper::get_selected_calendar_id_for_push( $agent->id );
				// Check if this selected calendar actually belongs to this user.
				if ( $calendar_id_for_push && OsOutlookCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) ) {

					/**
					 * Ideal case
					 *
					 * @todo this should be taken from agent function by param or some other way
					 * Ref function from latepoint "get_total_synced_future_bookings"
					 * Ex. $agent->total_synced_future_bookings
					 * Calculate sync statistics
					 */
					$total_synced_future_bookings = 0;
					if ( $agent->future_bookings ) {
						foreach ( $agent->future_bookings as $booking ) {
							if ( $booking->get_meta_by_key( 'outlook_calendar_event_id', false ) ) {
								++$total_synced_future_bookings;
							}
						}
					}
					$this->vars['future_bookings']              = $agent->future_bookings;
					$this->vars['total_future_bookings']        = $agent->total_future_bookings;
					$this->vars['total_synced_future_bookings'] = $total_synced_future_bookings; // $agent->total_synced_future_bookings;
					$this->vars['synced_bookings_percent']      = $this->vars['total_future_bookings'] ? min( round( $this->vars['total_synced_future_bookings'] / $this->vars['total_future_bookings'] * 100 ), 100 ) : 0;
				} else {
					OsOutlookCalendarHelper::clear_calendar_connection_info_from_agent( $agent->id, array( 'token', 'calendar_ids_pull' ) );
					$calendar_id_for_push = false;
				}
			} else {
				$this->vars['is_outlook_calendar_authorized'] = false;
			}
			$this->vars['calendar_id_for_push'] = $calendar_id_for_push;
			$this->format_render( __FUNCTION__ );
		}

		/**
		 * Disconnect Outlook Calendar from agent
		 */
		public function disconnect() {
			$agent_id = $this->params['agent_id'];
			$agent    = new OsAgentModel( $agent_id );
			if ( ! $agent->id ) {
				return false;
			}

			// Get all watching subscriptions.
			$agent_subscriptions = $agent->get_meta_by_key( 'outlook_cal_agent_subscriptions' );
			if ( $agent_subscriptions ) {
				$agent_subscriptions_arr = json_decode( $agent_subscriptions, true );
				foreach ( $agent_subscriptions_arr as $subscription ) {
					OsOutlookCalendarHelper::stop_subscription( $agent->id, $subscription['calendar_id'] );
				}
			}

			OsOutlookCalendarHelper::clear_calendar_connection_info_from_agent( $agent->id );

			// Delete all Outlook events.
			$outlook_event_model = new OsOutlookCalendarEventModel();
			$outlook_events      = $outlook_event_model->where( array( 'agent_id' => $agent->id ) )->get_results_as_models();
			if ( $outlook_events ) {
				foreach ( $outlook_events as $outlook_event ) {
					$outlook_event->delete();
				}
			}

			$status        = LATEPOINT_STATUS_SUCCESS;
			$response_html = __( 'Outlook Calendar Disconnected', 'latepoint-outlook-calendar' );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					)
				);
			}
		}

		/**
		 * Enable calendar for push (send bookings to Outlook)
		 */
		public function enable_calendar_for_push() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$calendar_id = $this->params['calendar_id'];
			$agent_id    = $this->params['agent_id'];
			OsOutlookCalendarHelper::set_selected_calendar_id_for_push( $calendar_id, $agent_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Connected Calendar Updated', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Disable calendar for push. Remove selected calendar connection.
		 */
		public function disable_calendar_for_push() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$agent_id = $this->params['agent_id'];
			OsOutlookCalendarHelper::remove_calendar_for_push( $agent_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Disconnected calendar for push', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Sync a single booking to Outlook Calendar
		 */
		public function sync_booking() {
			if ( ! $this->params['booking_id'] ) {
				return;
			}

			if ( OsOutlookCalendarHelper::create_or_update_booking_in_outlook( $this->params['booking_id'] ) ) {
				$status = LATEPOINT_STATUS_SUCCESS;
				/* translators: %s: booking ID */
				$response_html = sprintf( __( 'Booking #%s Synced to Outlook Calendar Successfully', 'latepoint-outlook-calendar' ), $this->params['booking_id'] );
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				/* translators: %s: booking ID */
				$response_html = sprintf( __( 'Booking #%s Sync Failed', 'latepoint-outlook-calendar' ), $this->params['booking_id'] );
			}

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					)
				);
			}
		}

		/**
		 * Delete a booking from Outlook Calendar
		 */
		public function delete_booking() {
			if ( ! $this->params['booking_id'] ) {
				return;
			}

			if ( OsOutlookCalendarHelper::delete_booking_from_outlook( $this->params['booking_id'] ) ) {
				$status = LATEPOINT_STATUS_SUCCESS;
				/* translators: %s: booking ID */
				$response_html = sprintf( __( 'Booking #%s Removed from Outlook Calendar Successfully', 'latepoint-outlook-calendar' ), $this->params['booking_id'] );
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				/* translators: %s: booking ID */
				$response_html = sprintf( __( 'Booking #%s Removal Failed', 'latepoint-outlook-calendar' ), $this->params['booking_id'] );
			}

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					)
				);
			}
		}
		/**
		 * Load events from Outlook Calendar for sync
		 */
		public function load_events_for_sync() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}
			$agent                            = new OsAgentModel( $this->params['agent_id'] );
			$this->vars['agent']              = $agent;
			$this->vars['pre_page_back_link'] = OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) );
			$this->vars['pre_page_header']    = __( 'Sync for', 'latepoint-outlook-calendar' ) . ' ' . $agent->full_name;
			$this->vars['page_header']        = array(
				array(
					'label' => __( 'Bookings', 'latepoint-outlook-calendar' ),
					'link'  => OsRouterHelper::build_link( array( 'outlook_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
				array(
					'label'  => __( 'Outlook Events', 'latepoint-outlook-calendar' ),
					'active' => true,
					'link'   => OsRouterHelper::build_link( array( 'outlook_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
			);
			$this->vars['breadcrumbs'][]      = array(
				'label' => $agent->full_name,
				'link'  => OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) ),
			);
			$this->vars['breadcrumbs'][]      = array(
				'label' => __( 'Load Outlook Calendar Events', 'latepoint-outlook-calendar' ),
				'link'  => false,
			);
			$available_calendars              = OsOutlookCalendarHelper::get_list_of_calendars( $agent->id );
			$connected_calendars              = array();
			$disconnected_calendars           = array();

			$this->vars['is_outlook_calendar_authorized'] = false;
			try {
				$calendar_ids_for_pull = OsOutlookCalendarHelper::get_selected_calendar_ids_for_pull( $agent->id );

				if ( $calendar_ids_for_pull ) {
					$calendar_ids_for_pull_arr = explode( ',', $calendar_ids_for_pull );
					foreach ( $available_calendars as $available_calendar ) {
						if ( in_array( $available_calendar['id'], $calendar_ids_for_pull_arr, true ) ) {
							$connected_calendars[] = $available_calendar;
						} else {
							$disconnected_calendars[] = $available_calendar;
						}
					}

					$opt_params            = array(
						'today' => OsTimeHelper::today_date( 'c' ),
					);
					$calendars_with_events = array();

					foreach ( $calendar_ids_for_pull_arr as $calendar_id ) {
						$calendars_with_events[ $calendar_id ] = OsOutlookCalendarHelper::load_events_from_outlook_calendar( $calendar_id, $agent, $opt_params );
					}
					$this->vars['calendars_with_events'] = $calendars_with_events;
				} else {
					$disconnected_calendars = $available_calendars;
				}
				$this->vars['calendar_ids_for_pull']          = explode( ',', $calendar_ids_for_pull );
				$this->vars['is_outlook_calendar_authorized'] = true;
			} catch ( Exception $e ) {
				// error_log( '!LatePoint Error loading events for sync: ' . $e->getMessage() );.
				unset( $e );
			}
			$this->vars['available_calendars'] = $available_calendars;

			$agent_watch_channels                  = OsMetaHelper::get_agent_meta_by_key( 'outlook_cal_agent_subscriptions', $agent->id, '' );
			$this->vars['agent_watch_channels']    = json_decode( $agent_watch_channels, true );
			$this->vars['connected_calendars']     = $connected_calendars;
			$this->vars['disconnected_calendars']  = $disconnected_calendars;
			$this->vars['hide_outlook_event_name'] = OsSettingsHelper::is_on( 'outlook_calendar_hide_event_name' );

			$this->format_render( __FUNCTION__ );
		}

		/**
		 * Enable calendar for pull (import events from Outlook)
		 */
		public function enable_calendar_for_pull() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$calendar_id = $this->params['calendar_id'];
			$agent_id    = $this->params['agent_id'];
			OsOutlookCalendarHelper::add_calendar_for_pull( $calendar_id, $agent_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Calendar connected for importing events', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Disable calendar for pull. Remove selected calendar connection for import.
		 */
		public function disable_calendar_for_pull() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$calendar_id = $this->params['calendar_id'];
			$agent_id    = $this->params['agent_id'];
			OsOutlookCalendarHelper::remove_calendar_for_pull( $calendar_id, $agent_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Calendar disconnected', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Start watching a calendar (webhook subscription)
		 */
		public function start_watch() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			OsOutlookCalendarHelper::start_subscription( $agent_id, $calendar_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Auto-sync enabled for this calendar', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Stop watching a calendar (webhook subscription)
		 */
		public function stop_watch() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			OsOutlookCalendarHelper::stop_subscription( $agent_id, $calendar_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Auto-sync disabled for this calendar', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Refresh webhook subscription for a calendar
		 */
		public function refresh_watch() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			OsOutlookCalendarHelper::refresh_subscription( $agent_id, $calendar_id );

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Subscription refreshed successfully', 'latepoint-outlook-calendar' ),
					)
				);
			}
		}

		/**
		 * Sync a single Outlook event to local database
		 */
		public function sync_event() {
			if ( OsAuthHelper::is_agent_logged_in() && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$outlook_event_id    = $this->params['outlook_event_id'];
			$outlook_calendar_id = $this->params['calendar_id'];
			$agent_id            = $this->params['agent_id'];
			$agent               = new OsAgentModel( $agent_id );

			try {
				// Fetch the specific event from Outlook.
				$connection_data = OsOutlookCalendarRelayHelper::get_connection_data_for_agent( $agent );
				$ocal_event      = OsOutlookCalendarRelayHelper::get_event_from_calendar( $outlook_calendar_id, $outlook_event_id, $connection_data );

				if ( $ocal_event ) {
					// Save event info to our database.
					$outlook_event_in_db = new OsOutlookCalendarEventModel();

					// Check if event already exists in DB.
					$event = $outlook_event_in_db->where(
						array(
							'outlook_event_id'    => $outlook_event_id,
							'outlook_calendar_id' => $outlook_calendar_id,
						)
					)->set_limit( 1 )->get_results_as_models();

					// If not, create new.
					if ( ! $event ) {
						// Create new.
						$event                      = new OsOutlookCalendarEventModel();
						$event->outlook_event_id    = $ocal_event['id'];
						$event->outlook_calendar_id = $outlook_calendar_id;
					}

					$event->agent_id = $agent_id;
					$event->summary  = $ocal_event['subject'] ?? '';
					$event->web_link = $ocal_event['webLink'] ?? '';

					$start_date_obj = OsOutlookCalendarHelper::os_get_start_of_outlook_event( $ocal_event );
					$end_date_obj   = OsOutlookCalendarHelper::os_get_end_of_outlook_event( $ocal_event );

					if ( $start_date_obj && $end_date_obj ) {
						$event->start_date         = $start_date_obj->format( 'Y-m-d' );
						$event->end_date           = $end_date_obj->format( 'Y-m-d' );
						$event->start_time         = $start_date_obj->format( 'H' ) * 60 + $start_date_obj->format( 'i' );
						$event->end_time           = $end_date_obj->format( 'H' ) * 60 + $end_date_obj->format( 'i' );
						$event->start_datetime_utc = $start_date_obj->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
						$event->end_datetime_utc   = $end_date_obj->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

						// Save event to local database.
						$event_saved = $event->save();

						if ( $event_saved ) {
							// Handle recurrence if present.
							if ( ! empty( $ocal_event['recurrence'] ) ) {
								$event->update_recurrences( OsOutlookCalendarHelper::get_ocal_event_recurrences( $ocal_event ) );
							}
							$status        = LATEPOINT_STATUS_SUCCESS;
							$response_html = __( 'Event synced successfully', 'latepoint-outlook-calendar' );
						} else {
							$status        = LATEPOINT_STATUS_ERROR;
							$response_html = __( 'Failed to save event', 'latepoint-outlook-calendar' );
						}
					} else {
						$status        = LATEPOINT_STATUS_ERROR;
						$response_html = __( 'Invalid event date/time', 'latepoint-outlook-calendar' );
					}
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Event not found in Outlook Calendar', 'latepoint-outlook-calendar' );
				}
			} catch ( Exception $e ) {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error syncing event: ', 'latepoint-outlook-calendar' ) . $e->getMessage();
			}

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					)
				);
			}
		}

		/**
		 * Remove a synced Outlook event from local database
		 */
		public function unsync_event() {
			if ( OsAuthHelper::is_agent_logged_in() && isset( $this->params['agent_id'] ) && ( OsAuthHelper::get_logged_in_agent_id() !== $this->params['agent_id'] ) ) {
				$this->access_not_allowed();
				return;
			}

			$outlook_event_id = $this->params['outlook_event_id'];

			$event  = new OsOutlookCalendarEventModel();
			$events = $event->where( array( 'outlook_event_id' => $outlook_event_id ) )->get_results_as_models();

			if ( $events ) {
				foreach ( $events as $event_model ) {
					$event_model->delete();
				}

				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Event Unsynced Successfully', 'latepoint-outlook-calendar' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Event not found in local database', 'latepoint-outlook-calendar' );
			}

			if ( $this->get_return_format() === 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					)
				);
			}
		}

		/**
		 * Handle webhook notifications from Microsoft Graph API
		 * This endpoint receives both validation requests and change notifications
		 */
		public function subscription_notification() {
			// Log the incoming request for debugging.
			// error_log( '!LatePoint Outlook Calendar Notification: ' . print_r( $_GET, true ) );.
			// error_log( '!LatePoint Outlook Calendar Notification Body: ' . file_get_contents( 'php://input' ) );.

			// Step 1: Handle Microsoft Graph subscription validation.
			// When creating a subscription, Microsoft sends a validation token that must be returned.
			if ( isset( $_GET['validationToken'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Return the validation token as plain text with 200 status.
				header( 'Content-Type: text/plain' );
				http_response_code( 200 );
				// It is important to echo the token exactly as received in text/plain format.
				echo esc_html( $_GET['validationToken'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				exit;
			}

			// Step 2: Handle change notifications.
			$payload = @file_get_contents( 'php://input' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( empty( $payload ) ) {
				http_response_code( 202 ); // Accepted but no content.
				exit;
			}

			$data = json_decode( $payload, true );
			if ( empty( $data['value'] ) ) {
				http_response_code( 202 ); // Accepted but no notifications.
				exit;
			}

			// Get agent_id and calendar_id from URL parameters.
			$agent_id    = isset( $_GET['agent_id'] ) ? intval( $_GET['agent_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$calendar_id = isset( $_GET['calendar_id'] ) ? sanitize_text_field( $_GET['calendar_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! $agent_id || ! $calendar_id ) {
				// error_log( '!LatePoint Outlook Calendar Notification: Missing agent_id or calendar_id' );.
				http_response_code( 400 );
				exit;
			}

			// Process each notification in the batch.
			foreach ( $data['value'] as $notification ) {
				try {
					$subscription_id = $notification['subscriptionId'] ?? '';
					$change_type     = $notification['changeType'] ?? '';
					$resource        = $notification['resource'] ?? '';

					// error_log( "!LatePoint Outlook Notification: Change Type: $change_type, Resource: $resource" );.

					// Microsoft Graph sends notifications for created, updated, and deleted events.
					// We need to re-fetch the events from Outlook to get the latest state.
					$this->sync_calendar_events_for_agent( $agent_id, $calendar_id );
				} catch ( Exception $e ) {
					unset( $e );
				}
			}

			// Respond with 202 Accepted to acknowledge receipt.
			http_response_code( 202 );
			exit;
		}

		/**
		 * Sync calendar events for an agent after receiving a change notification
		 *
		 * @param int    $agent_id Agent ID.
		 * @param string $calendar_id Calendar ID.
		 * @return bool
		 */
		private function sync_calendar_events_for_agent( $agent_id, $calendar_id ) {
			$agent = new OsAgentModel( $agent_id );
			if ( ! $agent->id ) {
				// error_log( "!LatePoint Outlook: Agent not found: $agent_id" );.
				return false;
			}

			if ( ! OsOutlookCalendarHelper::is_agent_connected_to_outlook( $agent_id ) ) {
				// error_log( "!LatePoint Outlook: Agent not connected: $agent_id" );.
				return false;
			}

			try {
				// Fetch events from Outlook Calendar starting from today.
				$opt_params = array(
					'today' => OsTimeHelper::today_date( 'c' ),
				);

				$outlook_events = OsOutlookCalendarHelper::load_events_from_outlook_calendar( $calendar_id, $agent, $opt_params );

				if ( empty( $outlook_events ) ) {
					// error_log( "!LatePoint Outlook: No events found for calendar: $calendar_id" );.
					return true;
				}

				// Get existing synced event IDs from local database.
				$existing_event_ids = array();
				$event_model        = new OsOutlookCalendarEventModel();
				$existing_events    = $event_model->where(
					array(
						'agent_id'            => $agent_id,
						'outlook_calendar_id' => $calendar_id,
					)
				)->get_results_as_models();

				$existing_events_by_outlook_id = array();
				if ( $existing_events ) {
					foreach ( $existing_events as $existing_event ) {
						$existing_events_by_outlook_id[ $existing_event->outlook_event_id ] = $existing_event;
						$existing_event_ids[] = $existing_event->outlook_event_id;
					}
				}

				// Track which events were found in Outlook.
				$found_outlook_event_ids = array();

				// Sync events from Outlook to local database.
				foreach ( $outlook_events as $outlook_event ) {
					// Skip "free" events.
					if ( isset( $outlook_event['showAs'] ) && $outlook_event['showAs'] === 'free' ) {
						continue;
					}

					// Skip LatePoint bookings (events that originated from LatePoint).
					$outlook_event_id     = $outlook_event['id'];
					$connected_booking_id = OsMetaHelper::get_booking_id_by_meta_value( 'outlook_calendar_event_id', $outlook_event_id );
					if ( $connected_booking_id ) {
						continue;
					}

					$found_outlook_event_ids[] = $outlook_event_id;

					// Check if event already exists locally.
					if ( isset( $existing_events_by_outlook_id[ $outlook_event_id ] ) ) {
						// Update existing event.
						$event = $existing_events_by_outlook_id[ $outlook_event_id ];
					} else {
						// Create new event.
						$event                      = new OsOutlookCalendarEventModel();
						$event->outlook_event_id    = $outlook_event_id;
						$event->outlook_calendar_id = $calendar_id;
						$event->agent_id            = $agent_id;
					}

					// Update event details.
					$event->summary  = $outlook_event['subject'] ?? '';
					$event->web_link = $outlook_event['webLink'] ?? '';

					$start_date_obj = OsOutlookCalendarHelper::os_get_start_of_outlook_event( $outlook_event );
					$end_date_obj   = OsOutlookCalendarHelper::os_get_end_of_outlook_event( $outlook_event );

					if ( $start_date_obj && $end_date_obj ) {
						$event->start_date         = $start_date_obj->format( 'Y-m-d' );
						$event->end_date           = $end_date_obj->format( 'Y-m-d' );
						$event->start_time         = $start_date_obj->format( 'H' ) * 60 + $start_date_obj->format( 'i' );
						$event->end_time           = $end_date_obj->format( 'H' ) * 60 + $end_date_obj->format( 'i' );
						$event->start_datetime_utc = $start_date_obj->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
						$event->end_datetime_utc   = $end_date_obj->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

						$event->save();

						// Handle recurrence if present.
						if ( ! empty( $outlook_event['recurrence'] ) ) {
							$event->update_recurrences( OsOutlookCalendarHelper::get_ocal_event_recurrences( $outlook_event ) );
						}
					}
				}

				// Delete events that no longer exist in Outlook.
				$deleted_event_ids = array_diff( $existing_event_ids, $found_outlook_event_ids );
				foreach ( $deleted_event_ids as $deleted_event_id ) {
					if ( isset( $existing_events_by_outlook_id[ $deleted_event_id ] ) ) {
						$existing_events_by_outlook_id[ $deleted_event_id ]->delete();
						// error_log( "!LatePoint Outlook: Deleted event from local DB: $deleted_event_id" );.
					}
				}

				// error_log( "!LatePoint Outlook: Successfully synced calendar events for agent $agent_id" );.
				return true;
			} catch ( Exception $e ) {
				// error_log( '!LatePoint Outlook: Error syncing calendar events: ' . $e->getMessage() );.
				return false;
			}
		}

		/**
		 * Redirection for laravel link to open sync manager
		 */
		public function open_sync_manager_by_agent_token() {
			$agent_token = $this->params['agent_token'];
			$meta        = new OsAgentMetaModel();
			$agent_id    = $meta->get_object_id_by_value( 'agent_token_for_outlook_auth', $agent_token );
			if ( empty( $agent_id ) ) {
				exit;
			}

			wp_safe_redirect( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'outlook_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent_id ) ) );
		}
	}

}
