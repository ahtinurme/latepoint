<?php
/**
 * Apple Calendar Controller
 *
 * Handles all Apple Calendar integration operations including connection management,
 * calendar selection, event syncing, and booking synchronization.
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'OsAppleCalendarController' ) ) {

	/**
	 * Apple Calendar Controller Class
	 *
	 * Manages Apple Calendar integration including authentication, calendar management,
	 * event synchronization, and booking operations.
	 *
	 * @since 1.0.0
	 */
	class OsAppleCalendarController extends OsController {
		/**
		 * Constructor
		 *
		 * Initializes the controller with views folder and breadcrumb settings.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			parent::__construct();

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/apple_calendar/';
			$this->vars['page_header']   = __( 'Agents', 'latepoint-apple-calendar' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Agents', 'latepoint-apple-calendar' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'agents', 'index' ) ),
			);
		}

		/**
		 * Test credentials and establish connection
		 *
		 * @since 1.0.0
		 */
		public function test_connection() {
			if ( ! $this->params['agent_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id     = $this->params['agent_id'];
			$apple_id     = $this->params['apple_id'];
			$app_password = $this->params['app_password'];

			// Validate inputs.
			if ( empty( $apple_id ) || empty( $app_password ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Apple ID and App-Specific Password are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			// Test connection first (before saving credentials).
			$result = OsAppleCalendarCalDAVHelper::test_connection( $apple_id, $app_password );

			// Only save credentials if connection is successful.
			if ( $result['success'] ) {
				OsAppleCalendarHelper::save_agent_credentials( $agent_id, $apple_id, $app_password );
			}

			if ( $result['success'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Connection successful!', 'latepoint-apple-calendar' ),
					)
				);
			} else {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $result['message'],
					)
				);
			}
		}

		/**
		 * Disconnect Apple Calendar from agent
		 *
		 * @since 1.0.0
		 */
		public function disconnect() {
			$agent_id = $this->params['agent_id'];
			$agent    = new OsAgentModel( $agent_id );

			if ( ! $agent->id ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent not found', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			// Clear all Apple Calendar data.
			OsAppleCalendarHelper::clear_calendar_connection_info_from_agent( $agent->id );

			// Delete all synced events from local database.
			$apple_event_model = new OsAppleCalendarEventModel();
			$apple_events      = $apple_event_model->where( array( 'agent_id' => $agent->id ) )->get_results_as_models();

			if ( $apple_events ) {
				foreach ( $apple_events as $apple_event ) {
					$apple_event->delete();
				}
			}

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Apple Calendar disconnected successfully', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Load calendar selection interface
		 *
		 * @since 1.0.0
		 */
		public function render_calendar_selection() {
			$agent_id = $this->params['agent_id'];

			if ( ! $agent_id ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			// Check if agent is connected.
			if ( ! OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent_id ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent is not connected to Apple Calendar', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			// Get list of calendars.
			$calendars = OsAppleCalendarCalDAVHelper::list_calendars( $agent_id );

			if ( is_wp_error( $calendars ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $calendars->get_error_message(),
					)
				);
				return;
			}

			// Cache calendars for future use.
			OsAppleCalendarHelper::cache_calendars( $agent_id, $calendars );

			// Get current selections.
			$selected_calendar_for_push      = OsAppleCalendarHelper::get_selected_calendar_id_for_push( $agent_id );
			$selected_calendars_for_pull     = OsAppleCalendarHelper::get_selected_calendar_ids_for_pull( $agent_id );
			$selected_calendars_for_pull_arr = $selected_calendars_for_pull ? explode( ',', $selected_calendars_for_pull ) : array();

			$this->vars['agent_id']                    = $agent_id;
			$this->vars['calendars']                   = $calendars;
			$this->vars['selected_calendar_for_push']  = $selected_calendar_for_push;
			$this->vars['selected_calendars_for_pull'] = $selected_calendars_for_pull_arr;

			$this->format_render( __FUNCTION__ );
		}

		/**
		 * Save calendar selections for an agent
		 *
		 * @since 1.0.0
		 */
		public function save_calendar_selections() {
			$agent_id = $this->params['agent_id'];

			if ( ! $agent_id ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$calendar_for_push  = $this->params['apple_calendar_for_push'] ?? '';
			$calendars_for_pull = $this->params['apple_calendars_for_pull'] ?? array();

			// Save selections.
			if ( ! empty( $calendar_for_push ) ) {
				OsAppleCalendarHelper::set_selected_calendar_id_for_push( $calendar_for_push, $agent_id );
			}

			if ( ! empty( $calendars_for_pull ) && is_array( $calendars_for_pull ) ) {
				$calendar_ids_string = implode( ',', $calendars_for_pull );
				OsAppleCalendarHelper::set_selected_calendar_ids_for_pull( $calendar_ids_string, $agent_id );
			} else {
				OsAppleCalendarHelper::set_selected_calendar_ids_for_pull( '', $agent_id );
			}

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Calendar selections saved successfully', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Manually sync a booking to Apple Calendar
		 *
		 * @since 1.0.0
		 */
		public function sync_booking() {
			if ( ! $this->params['booking_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Booking ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$booking_id = $this->params['booking_id'];

			if ( OsAppleCalendarHelper::create_or_update_booking_in_apple_calendar( $booking_id ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						// translators: %d is the booking ID.
						'message' => sprintf( __( 'Booking #%d synced to Apple Calendar successfully', 'latepoint-apple-calendar' ), $booking_id ),
					)
				);
			} else {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						// translators: %d is the booking ID.
						'message' => sprintf( __( 'Failed to sync Booking #%d to Apple Calendar', 'latepoint-apple-calendar' ), $booking_id ),
					)
				);
			}
		}

		/**
		 * Manually delete a booking from Apple Calendar
		 *
		 * @since 1.0.0
		 */
		public function delete_booking() {
			if ( ! $this->params['booking_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Booking ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$booking_id = $this->params['booking_id'];

			if ( OsAppleCalendarHelper::delete_booking_from_apple_calendar( $booking_id ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						// translators: %d is the booking ID.
						'message' => sprintf( __( 'Booking #%d removed from Apple Calendar successfully', 'latepoint-apple-calendar' ), $booking_id ),
					)
				);
			} else {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						// translators: %d is the booking ID.
						'message' => sprintf( __( 'Failed to remove Booking #%d from Apple Calendar', 'latepoint-apple-calendar' ), $booking_id ),
					)
				);
			}
		}

		/**
		 * Load events for sync - Shows Apple Calendar events and sync status
		 *
		 * @since 1.0.0
		 */
		public function load_events_for_sync() {
			// Check agent access permissions.
			if ( OsAuthHelper::is_agent_logged_in() && ( $this->params['agent_id'] !== OsAuthHelper::get_logged_in_agent_id() ) ) {
				$this->access_not_allowed();
				return;
			}

			$agent                            = new OsAgentModel( $this->params['agent_id'] );
			$this->vars['agent']              = $agent;
			$this->vars['pre_page_back_link'] = OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) );
			$this->vars['pre_page_header']    = __( 'Sync for', 'latepoint-apple-calendar' ) . ' ' . $agent->full_name;
			$this->vars['page_header']        = array(
				array(
					'label' => __( 'Bookings', 'latepoint-apple-calendar' ),
					'link'  => OsRouterHelper::build_link( array( 'apple_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
				array(
					'label'  => __( 'Apple Events', 'latepoint-apple-calendar' ),
					'active' => true,
					'link'   => OsRouterHelper::build_link( array( 'apple_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
			);
			$this->vars['breadcrumbs'][]      = array(
				'label' => $agent->full_name,
				'link'  => OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) ),
			);
			$this->vars['breadcrumbs'][]      = array(
				'label' => __( 'Load Apple Calendar Events', 'latepoint-apple-calendar' ),
				'link'  => false,
			);

			$available_calendars                       = array();
			$connected_calendars                       = array();
			$disconnected_calendars                    = array();
			$this->vars['is_apple_calendar_connected'] = false;

			if ( OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent->id ) ) {
				$this->vars['is_apple_calendar_connected'] = true;

				// Get list of available calendars.
				$available_calendars = OsAppleCalendarCalDAVHelper::list_calendars( $agent->id );
				if ( ! is_wp_error( $available_calendars ) ) {
					$calendar_ids_for_pull = OsAppleCalendarHelper::get_selected_calendar_ids_for_pull( $agent->id );

					if ( $calendar_ids_for_pull ) {
						$calendar_ids_for_pull_arr = explode( ',', $calendar_ids_for_pull );
						foreach ( $available_calendars as $available_calendar ) {
							if ( in_array( $available_calendar['url'], $calendar_ids_for_pull_arr ) ) {
								$connected_calendars[] = $available_calendar;
							} else {
								$disconnected_calendars[] = $available_calendar;
							}
						}

						// Load events from connected calendars.
						$calendars_with_events = array();
						// Set date range: today to 90 days in the future.
						$start_date = wp_date( 'Y-m-d' );
						$end_date   = wp_date( 'Y-m-d', strtotime( '+90 days' ) );

						// Create URL to ID mapping.
						$calendar_url_to_id = array();
						foreach ( $connected_calendars as $cal ) {
							$calendar_url_to_id[ $cal['url'] ] = $cal['id'];
						}

						foreach ( $calendar_ids_for_pull_arr as $calendar_url ) {
							// Get calendar ID for this URL.
							$calendar_id = $calendar_url_to_id[ $calendar_url ] ?? basename( $calendar_url );

							// Fetch events from Apple Calendar.
							$events = OsAppleCalendarCalDAVHelper::get_events_in_range( $agent->id, $calendar_url, $start_date, $end_date );

							if ( ! is_wp_error( $events ) ) {
								// Process events - don't expand recurring events, show them grouped like Google Calendar.
								$processed_events = array();
								foreach ( $events as $event ) {
									// Skip events created by LatePoint (they're shown in the Bookings tab).
									$event_uid = $event['apple_event_id'] ?? '';
									if ( strpos( $event_uid, 'latepoint-booking-' ) === 0 ) {
										continue;
									}

									// Add event with UID as unique ID (both recurring and non-recurring).
									$event['id']           = $event['apple_event_id'] ?? uniqid( 'apple-event-' );
									$event['calendar_url'] = $calendar_url;
									$processed_events[]    = $event;
								}
								// Key by calendar ID for view compatibility.
								$calendars_with_events[ $calendar_id ] = $processed_events;
							} else {
								// If error fetching events, continue with empty array.
								$calendars_with_events[ $calendar_id ] = array();
							}
						}
						$this->vars['calendars_with_events'] = $calendars_with_events;
					} else {
						$disconnected_calendars = $available_calendars;
					}
				}
			}

			$this->vars['available_calendars']    = $available_calendars;
			$this->vars['connected_calendars']    = $connected_calendars;
			$this->vars['disconnected_calendars'] = $disconnected_calendars;
			$this->vars['hide_apple_event_name']  = OsSettingsHelper::is_on( 'apple_calendar_hide_event_titles' );

			$this->format_render( __FUNCTION__ );
		}

		/**
		 * List bookings for sync - Shows LatePoint bookings and their sync status
		 *
		 * @since 1.0.0
		 */
		public function list_bookings_for_sync() {
			// Check agent access permissions.
			if ( OsAuthHelper::is_agent_logged_in() && ( $this->params['agent_id'] !== OsAuthHelper::get_logged_in_agent_id() ) ) {
				$this->access_not_allowed();
				return;
			}

			$agent = new OsAgentModel( $this->params['agent_id'] );

			$this->vars['pre_page_back_link'] = OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) );
			$this->vars['pre_page_header']    = __( 'Sync for', 'latepoint-apple-calendar' ) . ' ' . $agent->full_name;
			$this->vars['page_header']        = array(
				array(
					'label'  => __( 'Bookings', 'latepoint-apple-calendar' ),
					'active' => true,
					'link'   => OsRouterHelper::build_link( array( 'apple_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
				array(
					'label' => __( 'Apple Events', 'latepoint-apple-calendar' ),
					'link'  => OsRouterHelper::build_link( array( 'apple_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent->id ) ),
				),
			);

			$this->vars['breadcrumbs'][] = array(
				'label' => $agent->full_name,
				'link'  => OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) ),
			);
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Sync upcoming bookings', 'latepoint-apple-calendar' ),
				'link'  => false,
			);
			$this->vars['agent']         = $agent;
			$calendar_id_for_push        = false;

			if ( OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent->id ) ) {
				$this->vars['is_apple_calendar_connected'] = true;
				$calendar_id_for_push                      = OsAppleCalendarHelper::get_selected_calendar_id_for_push( $agent->id );

				// Check if this selected calendar actually belongs to this user.
				if ( $calendar_id_for_push ) {
					// Ensure calendars are cached for name lookup.
					if ( ! get_transient( 'apple_calendar_list_' . $agent->id ) ) {
						$calendars = OsAppleCalendarCalDAVHelper::list_calendars( $agent->id );
						if ( ! is_wp_error( $calendars ) ) {
							OsAppleCalendarHelper::cache_calendars( $agent->id, $calendars );
						}
					}

					if ( OsAppleCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) ) {
						$this->vars['future_bookings'] = $agent->future_bookings;

						// Calculate sync statistics.
						$total_synced = 0;
						if ( $this->vars['future_bookings'] ) {
							foreach ( $this->vars['future_bookings'] as $booking ) {
								if ( $booking->get_meta_by_key( 'apple_calendar_event_uid', false ) ) {
									++$total_synced;
								}
							}
						}

						$this->vars['total_future_bookings']        = count( $this->vars['future_bookings'] );
						$this->vars['total_synced_future_bookings'] = $total_synced;
						$this->vars['synced_bookings_percent']      = $this->vars['total_future_bookings'] ? min( round( $this->vars['total_synced_future_bookings'] / $this->vars['total_future_bookings'] * 100 ), 100 ) : 0;
					} else {
						$calendar_id_for_push = false;
					}
				}
			} else {
				$this->vars['is_apple_calendar_connected'] = false;
			}

			$this->vars['calendar_id_for_push'] = $calendar_id_for_push;
			$this->format_render( __FUNCTION__ );
		}

		/**
		 * Disable calendar for push (stop syncing bookings to this calendar)
		 *
		 * @since 1.0.0
		 */
		public function disable_calendar_for_push() {
			if ( ! $this->params['agent_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id = $this->params['agent_id'];
			OsAppleCalendarHelper::set_selected_calendar_id_for_push( '', $agent_id );

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Calendar disconnected from push sync', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Enable/disable calendar for pull (toggle event syncing)
		 *
		 * @since 1.0.0
		 */
		public function enable_calendar_for_pull() {
			if ( ! $this->params['agent_id'] || ! $this->params['calendar_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID and Calendar ID are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			// Get current calendars for pull.
			$current_calendars     = OsAppleCalendarHelper::get_selected_calendar_ids_for_pull( $agent_id );
			$current_calendars_arr = $current_calendars ? explode( ',', $current_calendars ) : array();

			// Add this calendar if not already in the list.
			if ( ! in_array( $calendar_id, $current_calendars_arr ) ) {
				$current_calendars_arr[] = $calendar_id;
				$updated_calendars       = implode( ',', $current_calendars_arr );
				OsAppleCalendarHelper::set_selected_calendar_ids_for_pull( $updated_calendars, $agent_id );
			}

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Calendar enabled for event sync', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Disable calendar for pull (stop syncing events from this calendar)
		 *
		 * @since 1.0.0
		 */
		public function disable_calendar_for_pull() {
			if ( ! $this->params['agent_id'] || ! $this->params['calendar_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID and Calendar ID are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			// Get current calendars for pull.
			$current_calendars     = OsAppleCalendarHelper::get_selected_calendar_ids_for_pull( $agent_id );
			$current_calendars_arr = $current_calendars ? explode( ',', $current_calendars ) : array();

			// Remove this calendar from the list.
			$updated_calendars_arr = array_diff( $current_calendars_arr, array( $calendar_id ) );
			$updated_calendars     = implode( ',', $updated_calendars_arr );
			OsAppleCalendarHelper::set_selected_calendar_ids_for_pull( $updated_calendars, $agent_id );

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Calendar disconnected from event sync', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Sync an Apple Calendar event to database
		 *
		 * @since 1.0.0
		 */
		public function sync_event() {
			if ( ! $this->params['apple_event_id'] || ! $this->params['agent_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Event ID and Agent ID are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			if ( OsAppleCalendarHelper::create_or_update_apple_event_in_db( $this->params['apple_event_id'], $this->params['calendar_id'], $this->params['agent_id'] ) ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Event Synced Successfully', 'latepoint-apple-calendar' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Event Sync Failed', 'latepoint-apple-calendar' );
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
		 * Enable auto-sync for a calendar
		 *
		 * @since 1.0.0
		 */
		public function enable_auto_sync() {
			if ( ! $this->params['agent_id'] || ! $this->params['calendar_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID and Calendar ID are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			// Enable auto-sync.
			OsAppleCalendarHelper::enable_auto_sync( $calendar_id, $agent_id );

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Auto-Sync with Apple Calendar Enabled', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Disable auto-sync for a calendar
		 *
		 * @since 1.0.0
		 */
		public function disable_auto_sync() {
			if ( ! $this->params['agent_id'] || ! $this->params['calendar_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID and Calendar ID are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			// Disable auto-sync.
			OsAppleCalendarHelper::disable_auto_sync( $calendar_id, $agent_id );

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Auto-Sync with Apple Calendar Disabled', 'latepoint-apple-calendar' ),
				)
			);
		}

		/**
		 * Unsync an Apple Calendar event from database
		 *
		 * @since 1.0.0
		 */
		public function unsync_event() {
			if ( ! $this->params['apple_event_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Event ID is required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			if ( OsAppleCalendarHelper::unsync_apple_event_from_db( $this->params['apple_event_id'] ) ) {
				$status        = LATEPOINT_STATUS_SUCCESS;
				$response_html = __( 'Event Unsynced Successfully', 'latepoint-apple-calendar' );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Event Unsync Failed', 'latepoint-apple-calendar' );
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
		 * Enable calendar for push (set calendar to sync bookings to)
		 *
		 * @since 1.0.0
		 */
		public function enable_calendar_for_push() {
			if ( ! $this->params['agent_id'] || ! $this->params['calendar_id'] ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Agent ID and Calendar ID are required', 'latepoint-apple-calendar' ),
					)
				);
				return;
			}

			$agent_id    = $this->params['agent_id'];
			$calendar_id = $this->params['calendar_id'];

			// Set this calendar for push.
			OsAppleCalendarHelper::set_selected_calendar_id_for_push( $calendar_id, $agent_id );

			$this->send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'Calendar enabled for booking sync', 'latepoint-apple-calendar' ),
				)
			);
		}
	}

}
