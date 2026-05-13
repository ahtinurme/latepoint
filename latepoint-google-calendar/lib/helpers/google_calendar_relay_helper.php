<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class OsGoogleCalendarRelayHelper {
	public static function is_enabled() {
		return OsSettingsHelper::is_on('enable_google_calendar');
	}

	public static function get_access_token_for_agent_id($agent_id){
		$agent = new OsAgentModel($agent_id);
		return $agent->get_meta_by_key('google_cal_access_token_relay');
	}

	public static function do_request(string $path, string $connection_data = '', string $method = 'GET', array $vars = [], array $headers = []) {

		$default_vars = [];
		$default_headers = [
				'latepoint-version' => LATEPOINT_VERSION,
				'latepoint-domain' => OsUtilHelper::get_site_url(),
				'latepoint-license-key' => OsLicenseHelper::get_license_key()
			];

		if(!empty($connection_data)){
			$default_headers['connection-data'] = $connection_data;
		}


		$args = array(
			'timeout' => 15,
			'headers' => array_merge($default_headers, $headers),
			'body' => array_merge($default_vars, $vars),
			'sslverify' => false,
			'method' => $method
		);

		$url = GOOGLE_CALENDAR_RELAY_URL."/api/wp/v1/google-calendar/{$path}";

		$response = wp_remote_request($url, $args);

		if (!is_wp_error($response)) {
			return json_decode(wp_remote_retrieve_body($response), true);
		} else {
			$error_message = $response->get_error_message();
			throw new Exception($error_message);
		}
	}

	public static function get_connect_url_for_agent($agent_id){
		$agent = new OsAgentModel($agent_id);
		$agent_token = $agent->get_meta_by_key('agent_token_for_google_auth');
		if (empty($agent_token)) {
			$agent_token = OsUtilHelper::generate_uuid();
			$agent->save_meta_by_key('agent_token_for_google_auth', $agent_token);
		}
		$url = GOOGLE_CALENDAR_RELAY_URL.'/wp/google-calendar-connection/';
		$url .= $agent_token . '/' . strtr(base64_encode(implode('|||', [$agent->full_name, $agent->avatar_url, OsUtilHelper::get_site_url()])), '+/=', '-_.');
		return $url;
	}

	public static function remove_connection(string $connection_id) {
		try{
			$response = self::do_request("connections/{$connection_id}", '', 'DELETE');
			return ($response['data'] ?? false);
		}catch (Exception $e){
			OsDebugHelper::log('Error removing connection to Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}

	}

	public static function get_list_of_calendars(string $connection_data) {
		$calendars = [];
		try{
			$response = self::do_request('calendars', $connection_data);
			if (!empty($response['data'])) $calendars = $response['data'];
		}catch (Exception $e){
			OsDebugHelper::log('Error getting list of calendars from Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
		return $calendars;
	}

	public static function stop_watch(string $channel_id, string $resource_id, string $connection_data){
		try{
			$response = self::do_request("watch-channels/{$channel_id}/{$resource_id}", $connection_data, 'DELETE');
			return ($response['data'] ?? false);
		}catch (Exception $e){
			OsDebugHelper::log('Error stopping watch for Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function start_watch(string $agent_id, string $calendar_id, string $connection_data){
		try{
			$response = self::do_request("calendars/{$calendar_id}/watch-channels/{$agent_id}", $connection_data, 'POST');
			return ($response['data'] ?? false);
		}catch (Exception $e){
			OsDebugHelper::log('Error starting watch for Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function create_booking_in_gcal(string $calendar_id, string $connection_data, array $event_data) {
		try{
			$response = self::do_request("calendars/{$calendar_id}/events", $connection_data, 'POST', ['event' => $event_data]);
			return ($response['data'] ?? false);
		}catch (Exception $e){
			OsDebugHelper::log('Error creating booking in Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function update_booking_in_gcal(string $calendar_id, string $connection_data, string $google_calendar_event_id, array $event_data) {
		try{
			$response = self::do_request("calendars/{$calendar_id}/events/{$google_calendar_event_id}", $connection_data, 'PATCH', ['event' => $event_data]);
		}catch (Exception $e){
			OsDebugHelper::log('Error updating booking in Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function delete_booking_from_gcal(string $calendar_id, string $connection_data, string $google_calendar_event_id) {
		try{
			$response = self::do_request("calendars/{$calendar_id}/events/{$google_calendar_event_id}", $connection_data, 'DELETE');
		}catch (Exception $e){
			OsDebugHelper::log('Error deleting booking in Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function refresh_access_token(string $connection_data){
		try{
			$response = self::do_request("refresh-token", $connection_data);
			if(!empty($response['data']) && !empty($response['data']['new_access_token'])){
				return $response['data']['new_access_token'];
			}else{
				return false;
			}
		}catch (Exception $e){
			OsDebugHelper::log('Error getting event info from Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function get_event_from_gcal(string $calendar_id, string $connection_data, string $google_calendar_event_id) {
		try{
			$response = self::do_request("calendars/{$calendar_id}/events/{$google_calendar_event_id}", $connection_data);
			if(!empty($response['data'])){
				$gcal_event = OsGoogleCalendarHelper::build_google_event_from_array($response['data']);
				return $gcal_event;
			}else{
				return false;
			}
		}catch (Exception $e){
			OsDebugHelper::log('Error getting event info from Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
	}

	public static function list_events_for_calendar(string $calendar_id, string $connection_data, array $opt_params = []) {
		$events = [];
		$gcal_events = [];
		try{
			$response = self::do_request("calendars/{$calendar_id}/events", $connection_data, 'GET', [], ['opt-params' => json_encode($opt_params)]);
			if (!empty($response['data'])){
				$events = $response['data'];
				foreach($events as $event_data){
					$gcal_events[] = OsGoogleCalendarHelper::build_google_event_from_array(array_filter($event_data));
				}
			}
		}catch (Exception $e){
			OsDebugHelper::log('Error listing events from Google Calendar', 'google_calendar', ['error_message' => $e->getMessage()]);
		}
		return $gcal_events;
	}

	public static function get_connection_data_for_agent($agent){
		if(is_a($agent, 'OsAgentModel')){
			return json_encode(['access_token_data' => $agent->get_meta_by_key('google_cal_access_token_relay'), 'connection_id' => $agent->get_meta_by_key('google_cal_connection_id_relay')]);
		}else{
			return json_encode(['access_token_data' => OsMetaHelper::get_agent_meta_by_key('google_cal_access_token_relay', $agent), 'connection_id' => OsMetaHelper::get_agent_meta_by_key('google_cal_connection_id_relay', $agent)]);
		}
	}
}