<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


if (!class_exists('OsGoogleCalendarController')) :


	class OsGoogleCalendarController extends OsController {


		function __construct() {
			parent::__construct();

			$this->views_folder = plugin_dir_path(__FILE__) . '../views/google_calendar/';

			$this->action_access['public'] = array_merge($this->action_access['public'], ['event_watch_updated', 'save_access_token', 'delete_access_token', 'heartbeat']);
			$this->vars['page_header'] = __('Agents', 'latepoint-google-calendar');
			$this->vars['breadcrumbs'][] = array('label' => __('Agents', 'latepoint-google-calendar'), 'link' => OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'index')));
		}


		function connect() {
			$client = OsGoogleCalendarHelper::get_client();
			$auth_result = $client->fetchAccessTokenWithAuthCode($this->params['code']);
			$access_token = $client->getAccessToken();

			$agent = new OsAgentModel($this->params['agent_id']);
			$agent->save_meta_by_key('google_cal_access_token', json_encode($access_token));

			$status = LATEPOINT_STATUS_SUCCESS;
			$response_html = __('Google Calendar Connected', 'latepoint-google-calendar');

			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}

		}

		public function disconnect() {
			$agent_id = $this->params['agent_id'];
			$agent = new OsAgentModel($agent_id);
			if (!$agent->id) return false;
			// get all watching channels
			$agent_watch_channels = $agent->get_meta_by_key('google_cal_agent_watch_channels');
			if ($agent_watch_channels) {
				$agent_watch_channels = json_decode($agent_watch_channels, true);
				$agent_watch_channel = false;
				foreach ($agent_watch_channels as $channel) {
					OsGoogleCalendarHelper::stop_watch($agent->id, $channel['calendar_id']);
				}
			}
			OsGoogleCalendarHelper::clear_calendar_connection_info_from_agent($agent->id);
			$gcal_event_model = new OsGoogleCalendarEventModel();
			$gcal_events = $gcal_event_model->where(['agent_id' => $agent->id])->get_results_as_models();
			if ($gcal_events) {
				foreach ($gcal_events as $gcal_event) {
					$gcal_event->delete();
				}
			}
			$status = LATEPOINT_STATUS_SUCCESS;
			$response_html = __('Google Calendar Disconnected', 'latepoint-google-calendar');
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function delete_booking() {
			if (!$this->params['booking_id']) return;
			if (OsGoogleCalendarHelper::delete_booking_from_gcal($this->params['booking_id'])) {
				$status = LATEPOINT_STATUS_SUCCESS;
				$response_html = __('Booking #' . $this->params['booking_id'] . ' Removed from Google Calendar Successfully', 'latepoint-google-calendar');
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Booking #' . $this->params['booking_id'] . ' Removal Failed', 'latepoint-google-calendar');
			}


			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function sync_booking() {
			if (!$this->params['booking_id']) return;
			if (OsGoogleCalendarHelper::create_or_update_booking_in_gcal($this->params['booking_id'])) {
				$status = LATEPOINT_STATUS_SUCCESS;
				$response_html = __('Booking #' . $this->params['booking_id'] . ' Synced to Google Calendar Successfully', 'latepoint-google-calendar');
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Booking #' . $this->params['booking_id'] . ' Sync Failed', 'latepoint-google-calendar');
			}


			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function start_authorization() {
			nocache_headers();
			wp_redirect(OsGoogleCalendarRelayHelper::get_connect_url_for_agent($this->params['agent_id']));
		}

		public function delete_access_token(){

			$payload = @file_get_contents('php://input');
			$data = json_decode($payload, true);

			if (!isset($data['wp_latepoint_agent_token'])) exit;

			$meta = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value('agent_token_for_google_auth', $data['wp_latepoint_agent_token']);
			if (empty($agent_id)) exit;

			$agent = new OsAgentModel($agent_id);
			$agent->delete_meta_by_key('google_cal_access_token_relay');
			$agent->delete_meta_by_key('google_cal_connection_id_relay');
		}

		public function save_access_token() {

			$payload = @file_get_contents('php://input');
			$data = json_decode($payload, true);


			if (!isset($data['wp_latepoint_agent_token']) || !isset($data['google_calendar_access_token'])) exit;

			$meta = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value('agent_token_for_google_auth', $data['wp_latepoint_agent_token']);
			if (empty($agent_id)) exit;

			$agent = new OsAgentModel($agent_id);
			$agent->save_meta_by_key('google_cal_access_token_relay', $data['google_calendar_access_token']);
			$agent->save_meta_by_key('google_cal_connection_id_relay', $data['google_calendar_connection_id']);
		}


		/*
		 * This action will be triggered by google push notification, when an event from a "watched" channel/calendar has been updated
		 * More info: https://developers.google.com/calendar/api/guides/push
		 *
		 * example data sent with push request:
		 * $_SERVER['HTTP_X_GOOG_CHANNEL_ID'] => gcal_watch_7v9bf57b20967\n
		 * $_SERVER['HTTP_X_GOOG_CHANNEL_EXPIRATION'] => Wed, 03 Apr 2024 22:13:16 GMT\n
		 * $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'] => exists\n
		 * $_SERVER['HTTP_X_GOOG_MESSAGE_NUMBER'] => 6619118\n
		 * $_SERVER['HTTP_X_GOOG_RESOURCE_ID'] => YVll40HrjgCU7eUXYajS8aMn2qo\n
		 * $_SERVER['HTTP_X_GOOG_RESOURCE_URI'] => https://www.googleapis.com/calendar/v3/calendars/primary/events?maxResults=250&alt=json\n
		*/
		public function event_watch_updated() {
			if (!isset($this->params['agent_id']) || !isset($this->params['calendar_id'])) exit;
			$agent = new OsAgentModel($this->params['agent_id']);
			$calendar_id = $this->params['calendar_id'];
			if (!$agent || !$calendar_id) exit;
			$agent_watch_channels = $agent->get_meta_by_key('google_cal_agent_watch_channels');
			if (!$agent_watch_channels) exit;
			$agent_watch_channels = json_decode($agent_watch_channels, true);
			$agent_watch_channel = false;
			foreach ($agent_watch_channels as $channel) {
				if ($channel['calendar_id'] == $calendar_id) {
					$agent_watch_channel = $channel;
					break;
				}
			}
			if (!$agent_watch_channel) exit;
			try {
				$client = OsGoogleCalendarHelper::get_authorized_client_for_agent($agent->id);
				if ($client) {
					$g_service = new \LatePoint\GoogleCalendarAddon\Google\Service\Calendar($client);

					$opt_params = array(
						'timeZone' => OsTimeHelper::get_wp_timezone_name(),
					);

					if (!empty($agent_watch_channel['next_sync_token'])) {
						$opt_params['syncToken'] = $agent_watch_channel['next_sync_token'];
					} else {
						$opt_params['timeMin'] = OsTimeHelper::today_date('c');
					}
					$gcal_events = $g_service->events->listEvents($calendar_id, $opt_params);
					$total_events = 0;
					while (true) {
						// loop through all pages of events
						foreach ($gcal_events->getItems() as $gcal_event) {
							$booking_id = OsMetaHelper::get_booking_id_by_meta_value('google_calendar_event_id', $gcal_event->getId());
							if ($booking_id) {
								// it is a latepoint booking
								if ($gcal_event->status == 'cancelled') {
									// unsync from our db
									$booking = new OsBookingModel($booking_id);
									if ($booking->id) $booking->delete_meta_by_key('google_calendar_event_id');
									OsBookingHelper::change_booking_status($booking_id, LATEPOINT_BOOKING_STATUS_CANCELLED);
								} else {
									OsGoogleCalendarHelper::update_booking_from_gcal_event($gcal_event, $booking_id);
								}
							} else {
								// it is a google calendar event (not latepoint booking)
								if ($gcal_event->status == 'confirmed' && $gcal_event->transparency != 'transparent') {
									OsGoogleCalendarHelper::create_or_update_google_event_in_db($gcal_event, $this->params['calendar_id'], $this->params['agent_id']);
								} elseif ($gcal_event->status == 'cancelled' || $gcal_event->transparency == 'transparent') {
									// if cancelled or is set to slot in gcal set to "FREE" unsync it
									OsGoogleCalendarHelper::unsync_google_event_from_db($gcal_event->getId());
								}
							}

							$total_events++;
							if ($total_events >= 1000) break;
						}
						$pageToken = $gcal_events->getNextPageToken();
						$syncToken = $gcal_events->getNextSyncToken();
						if (!empty($syncToken)) {
							// save synctoken
							$agent_watch_channel['next_sync_token'] = $syncToken;

							for ($i = 0; $i < count($agent_watch_channels); $i++) {
								if ($agent_watch_channels[$i]['calendar_id'] == $calendar_id) {
									$agent_watch_channels[$i]['next_sync_token'] = $syncToken;
									$agent->save_meta_by_key('google_cal_agent_watch_channels', json_encode($agent_watch_channels));
									break;
								}
							}
						}
						if ($pageToken) {
							// not last page, get next page
							$opt_params['pageToken'] = $pageToken;
							$gcal_events = $g_service->events->listEvents(OsGoogleCalendarHelper::get_selected_calendar_id_for_push($agent->id), $opt_params);
						} else {
							// last page - break
							break;
						}
					}
				}
			} catch (Exception $e) {
				error_log('!LatePoint Error reacting to event watch trigger: ' . $e->getMessage());
			}
		}


		public function unsync_event() {
			if (!$this->params['google_event_id']) return;
			if (OsGoogleCalendarHelper::unsync_google_event_from_db($this->params['google_event_id'])) {
				$status = LATEPOINT_STATUS_SUCCESS;
				$response_html = __('Event Unsynced Successfully', 'latepoint-google-calendar');
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Event Unsync Failed', 'latepoint-google-calendar');
			}


			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}


		function disable_calendar_for_pull() {
			if (OsAuthHelper::is_agent_logged_in() && ($this->params['agent_id'] != OsAuthHelper::get_logged_in_agent_id())) {
				$this->send_json(array('status' => LATEPOINT_STATUS_ERROR, 'message' => 'Not Allowed'));
			}
			if (!isset($this->params['agent_id']) || !isset($this->params['calendar_id'])) {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Calendar Sync Failed. Missing Agent and Calendar information', 'latepoint-google-calendar');
			} else {
				if (OsGoogleCalendarHelper::remove_calendar_for_pull($this->params['calendar_id'], $this->params['agent_id'])) {
					$status = LATEPOINT_STATUS_SUCCESS;
					$response_html = __('Calendar Sync Enabled', 'latepoint-google-calendar');
				} else {
					$status = LATEPOINT_STATUS_ERROR;
					$response_html = __('Calendar Sync Failed', 'latepoint-google-calendar');
				}
			}

			$this->send_json(array('status' => $status, 'message' => $response_html));
		}

		public function open_sync_manager_by_agent_token(){
			$agent_token = $this->params['agent_token'];
			$meta = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value('agent_token_for_google_auth', $agent_token);
			if (empty($agent_id)) exit;

			wp_redirect(OsRouterHelper::build_link(OsRouterHelper::build_route_name('google_calendar', 'list_bookings_for_sync'), ['agent_id' => $agent_id]));
		}

		function enable_calendar_for_pull() {
			if (OsAuthHelper::is_agent_logged_in() && ($this->params['agent_id'] != OsAuthHelper::get_logged_in_agent_id())) {
				$this->send_json(array('status' => LATEPOINT_STATUS_ERROR, 'message' => 'Not Allowed'));
			}
			if (!isset($this->params['agent_id']) || !isset($this->params['calendar_id'])) {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Calendar Sync Failed. Missing Agent and Calendar information', 'latepoint-google-calendar');
			} else {
				if (OsGoogleCalendarHelper::add_calendar_for_pull($this->params['calendar_id'], $this->params['agent_id'])) {
					$status = LATEPOINT_STATUS_SUCCESS;
					$response_html = __('Calendar Sync Enabled', 'latepoint-google-calendar');
				} else {
					$status = LATEPOINT_STATUS_ERROR;
					$response_html = __('Calendar Sync Failed', 'latepoint-google-calendar');
				}
			}

			$this->send_json(array('status' => $status, 'message' => $response_html));
		}

		public function sync_event() {
			if (!$this->params['google_event_id'] || !$this->params['agent_id']) return;
			if (OsGoogleCalendarHelper::create_or_update_google_event_in_db($this->params['google_event_id'], $this->params['calendar_id'], $this->params['agent_id'])) {
				$status = LATEPOINT_STATUS_SUCCESS;
				$response_html = __('Event Synced Successfully', 'latepoint-google-calendar');
			} else {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = __('Event Sync Failed', 'latepoint-google-calendar');
			}


			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function start_watch() {
			try {
				OsGoogleCalendarHelper::start_watch($this->params['agent_id'], $this->params['calendar_id']);
				$status = LATEPOINT_STATUS_SUCCESS;
				$response_html = __('Auto-sync with Google Calendar Enabled', 'latepoint-google-calendar');
			} catch (Exception $e) {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = $e->getMessage();
			}
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function refresh_watch() {
			try {
				OsGoogleCalendarHelper::refresh_watch($this->params['agent_id'], $this->params['calendar_id']);
				$status = LATEPOINT_STATUS_SUCCESS;
				$response_html = __('Token Refreshed', 'latepoint-google-calendar');
			} catch (Exception $e) {
				$status = LATEPOINT_STATUS_ERROR;
				$response_html = $e->getMessage();
			}
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function stop_watch() {
			OsGoogleCalendarHelper::stop_watch($this->params['agent_id'], $this->params['calendar_id']);
			$status = LATEPOINT_STATUS_SUCCESS;
			$response_html = __('Auto-sync Disabled', 'latepoint-google-calendar');
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => $status, 'message' => $response_html));
			}
		}

		public function heartbeat(){
			$payload = @file_get_contents('php://input');
			$data = json_decode($payload, true);

			if(empty($data['wp_latepoint_agent_token'])) $this->send_json(array('status' => LATEPOINT_STATUS_ERROR, 'message' => 'Token is missing'), 404);

			$meta = new OsAgentMetaModel();
			$agent_id = $meta->get_object_id_by_value('agent_token_for_google_auth', $data['wp_latepoint_agent_token']);
			if (empty($agent_id)) $this->send_json(array('status' => LATEPOINT_STATUS_ERROR, 'message' => 'Agent not found'), 404);

			$this->send_json(array('status' => LATEPOINT_STATUS_SUCCESS, 'message' => 'Heartbeat detected'), 200);
		}

		public function load_events_for_sync() {
			if (OsAuthHelper::is_agent_logged_in() && ($this->params['agent_id'] != OsAuthHelper::get_logged_in_agent_id())) {
				$this->access_not_allowed();
				return;
			}
			$agent = new OsAgentModel($this->params['agent_id']);
			$this->vars['agent'] = $agent;
			$this->vars['pre_page_back_link'] = OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id]);
			$this->vars['pre_page_header'] = __('Sync for', 'latepoint-google-calendar') . ' ' . $agent->full_name;
			$this->vars['page_header'] = [['label' => __('Bookings', 'latepoint-google-calendar'), 'link' => OsRouterHelper::build_link(['google_calendar', 'list_bookings_for_sync'], ['agent_id' => $agent->id])],
				['label' => __('Google Events', 'latepoint-google-calendar'), 'active' => true, 'link' => OsRouterHelper::build_link(['google_calendar', 'load_events_for_sync'], ['agent_id' => $agent->id])]];
			$this->vars['breadcrumbs'][] = array('label' => $agent->full_name, 'link' => OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id]));
			$this->vars['breadcrumbs'][] = array('label' => __('Load Google Calendar Events', 'latepoint-google-calendar'), 'link' => false);
			$available_calendars = OsGoogleCalendarHelper::get_list_of_calendars($agent->id);
			$connected_calendars = [];
			$disconnected_calendars = [];

			$this->vars['is_google_calendar_authorized'] = false;
			try {
				$calendar_ids_for_pull = OsGoogleCalendarHelper::get_selected_calendar_ids_for_pull($agent->id);


				if ($calendar_ids_for_pull) {
					$opt_params = array(
						'timeZone' => OsTimeHelper::get_wp_timezone_name(),
						'timeMin' => OsTimeHelper::today_date('c'),
					);
					$calendar_ids_for_pull_arr = explode(',', $calendar_ids_for_pull);
					foreach ($available_calendars as $available_calendar) {
						if (in_array($available_calendar['id'], $calendar_ids_for_pull_arr)) {
							$connected_calendars[] = $available_calendar;
						} else {
							$disconnected_calendars[] = $available_calendar;
						}
					}
					$calendars_with_events = [];

					foreach ($calendar_ids_for_pull_arr as $calendar_id) {
						$calendars_with_events[$calendar_id] = OsGoogleCalendarHelper::load_events_from_google_calendar($calendar_id, $agent, $opt_params);
					}
					$this->vars['calendars_with_events'] = $calendars_with_events;
				} else {
					$disconnected_calendars = $available_calendars;
				}
				$this->vars['calendar_ids_for_pull'] = explode(',', $calendar_ids_for_pull);
				$this->vars['is_google_calendar_authorized'] = true;
			} catch (Exception $e) {
				error_log('!LatePoint Error loading events for sync: ' . $e->getMessage());
			}
			$this->vars['available_calendars'] = $available_calendars;

			$agent_watch_channels = OsMetaHelper::get_agent_meta_by_key('google_cal_agent_watch_channels', $agent->id, '');
			$this->vars['agent_watch_channels'] = json_decode($agent_watch_channels, true);
			$this->vars['connected_calendars'] = $connected_calendars;
			$this->vars['disconnected_calendars'] = $disconnected_calendars;
			$this->vars['hide_gcal_event_name'] = OsSettingsHelper::is_on('google_calendar_hide_event_name');

			$this->format_render(__FUNCTION__);
		}

		public function list_bookings_for_sync() {
			if (OsAuthHelper::is_agent_logged_in() && ($this->params['agent_id'] != OsAuthHelper::get_logged_in_agent_id())) {
				$this->access_not_allowed();
				return;
			}
			$agent = new OsAgentModel($this->params['agent_id']);

			$this->vars['pre_page_back_link'] = OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id]);
			$this->vars['pre_page_header'] = __('Sync for', 'latepoint-google-calendar') . ' ' . $agent->full_name;
			$this->vars['page_header'] = [['label' => __('Bookings', 'latepoint-google-calendar'), 'active' => true, 'link' => OsRouterHelper::build_link(['google_calendar', 'list_bookings_for_sync'], ['agent_id' => $agent->id])],
				['label' => __('Google Events', 'latepoint-google-calendar'), 'link' => OsRouterHelper::build_link(['google_calendar', 'load_events_for_sync'], ['agent_id' => $agent->id])]];

			$this->vars['breadcrumbs'][] = array('label' => $agent->full_name, 'link' => OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id]));
			$this->vars['breadcrumbs'][] = array('label' => __('Sync upcoming bookings', 'latepoint-google-calendar'), 'link' => false);
			$this->vars['agent'] = $agent;
			$calendar_id_for_push = false;

			if (OsGoogleCalendarHelper::is_agent_connected_to_gcal($agent->id)) {
				$this->vars['is_google_calendar_authorized'] = true;
				$calendar_id_for_push = OsGoogleCalendarHelper::get_selected_calendar_id_for_push($agent->id);
				// check if this selected calendar actually belongs to this user
				if ($calendar_id_for_push && OsGoogleCalendarHelper::get_calendar_name_by_id($calendar_id_for_push, $agent->id)) {
					$this->vars['future_bookings'] = $agent->future_bookings;
					$this->vars['total_future_bookings'] = $agent->total_future_bookings;
					$this->vars['total_synced_future_bookings'] = $agent->total_synced_future_bookings;
					$this->vars['synced_bookings_percent'] = ($this->vars['total_future_bookings']) ? min(round(($this->vars['total_synced_future_bookings'] / $this->vars['total_future_bookings']) * 100), 100) : 0;
				} else {
					OsGoogleCalendarHelper::clear_calendar_connection_info_from_agent($agent->id, ['token', 'calendar_ids_pull']);
					$calendar_id_for_push = false;
				}
			} else {
				$this->vars['is_google_calendar_authorized'] = false;
			}
			$this->vars['calendar_id_for_push'] = $calendar_id_for_push;
			$this->format_render(__FUNCTION__);
		}

		public function disable_calendar_for_push() {
			if (OsAuthHelper::is_agent_logged_in() && ($this->params['agent_id'] != OsAuthHelper::get_logged_in_agent_id())) {
				$this->access_not_allowed();
				return;
			}
			$agent_id = $this->params['agent_id'];
			OsGoogleCalendarHelper::remove_calendar_for_push($agent_id);
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => LATEPOINT_STATUS_SUCCESS, 'message' => __('Disconnected calendar for push', 'latepoint-google-calendar')));
			}
		}

		public function enable_calendar_for_push() {
			if (OsAuthHelper::is_agent_logged_in() && ($this->params['agent_id'] != OsAuthHelper::get_logged_in_agent_id())) {
				$this->access_not_allowed();
				return;
			}
			$calendar_id = $this->params['calendar_id'];
			$agent_id = $this->params['agent_id'];
			OsGoogleCalendarHelper::set_selected_calendar_id_for_push($calendar_id, $agent_id);
			if ($this->get_return_format() == 'json') {
				$this->send_json(array('status' => LATEPOINT_STATUS_SUCCESS, 'message' => __('Connected Calendar Updated', 'latepoint-google-calendar')));
			}
		}

	}


endif;