<?php

class OsZoomHelper {

	const MEETING_TYPE_SCHEDULED = 2;

	private static function is_shared_meeting_mode( int $service_id ): bool {
		return method_exists( 'OsFeatureGroupBookingsHelper', 'is_shared_meeting_mode' )
			&& OsFeatureGroupBookingsHelper::is_shared_meeting_mode( $service_id );
	}

	public static function save_access_token(string $access_token): bool {
		$access_token_info = json_encode(['expiration_timestamp' => time(), 'token' => $access_token]);
		return OsSettingsHelper::save_setting_by_name('zoom_access_token_info', $access_token_info);
	}

	public static function get_saved_access_token(): string {
		$access_token_info = OsSettingsHelper::get_settings_value('zoom_access_token_info');
		if ($access_token_info) {
			$access_token_info = json_decode($access_token_info, true);
			if ($access_token_info['expiration_timestamp'] && $access_token_info['expiration_timestamp'] > time() && $access_token_info['token']) {
				return $access_token_info['token'];
			}
		}
		return '';
	}

	public static function get_access_token() {
		$access_token = self::get_saved_access_token();
		if (empty($access_token)) {
			$encoded_client_string = base64_encode(OsSettingsHelper::get_settings_value('zoom_client_id') . ':' . OsSettingsHelper::get_settings_value('zoom_client_secret'));
			$account_id = OsSettingsHelper::get_settings_value('zoom_account_id');
			$data = [
				'grant_type' => 'account_credentials',
				'account_id' => $account_id
			];

			$response = wp_remote_post('https://zoom.us/oauth/token', [
					'body' => $data,
					'headers' => [
						'Authorization' => 'Basic ' . $encoded_client_string,
					],
				]
			);
			if (!is_wp_error($response)) {
				$body = json_decode(wp_remote_retrieve_body($response), true);
				if (!empty($body['access_token'])) {
					self::save_access_token($body['access_token']);
					return $body['access_token'];
				} else {
					throw new Exception($body['reason']);
				}
			} else {
				$error_message = $response->get_error_message();
				throw new Exception($error_message);
			}
		}
		return $access_token;
	}


	public static function do_request(string $path, string $method = 'GET', array $data = []) {
		$endpoint = 'https://api.zoom.us/v2' . $path;
		try {
			$access_token = self::get_access_token();
			$args = ['headers' => [
				'Authorization' => 'Bearer ' . $access_token
			]];

			switch ($method) {
				case 'GET':
					$response = wp_remote_get($endpoint . '?' . http_build_query($data), $args);
					break;
				case 'POST':
					if(!empty($data)) $args['body'] = json_encode($data);
					$args['headers']['content-type'] = 'application/json';
					$response = wp_remote_post($endpoint, $args);
					break;
				case 'DELETE':
				case 'PUT':
				case 'PATCH':
					if(!empty($data)) $args['body'] = json_encode($data);
					$args['headers']['content-type'] = 'application/json';
					$args['method'] = $method;
					$response = wp_remote_request($endpoint, $args);
					break;
			}
			if (!is_wp_error($response)) {
				if(!empty($response['response'])){
					return ['data' => self::get_body_from_response($response), 'code' => $response['response']['code'], 'message' => $response['response']['message'], 'response' => $response];
				}else{
					return ['data' => [], 'code' => 500, 'message' => 'Empty response', 'response' => $response];
				}
			} else {
				$error_message = $response->get_error_message();
				return ['data' => [], 'code' => 500, 'message' => $error_message];
			}
		} catch (Exception $e) {
			return ['data' => [], 'code' => 500, 'message' => 'Missing or invalid token'];
		}
	}

	public static function get_body_from_response($response){
		return json_decode(wp_remote_retrieve_body($response), true);
	}


	public static function is_zoom_enabled_for_service($service_id) {
		return (OsMetaHelper::get_service_meta_by_key('enable_zoom', $service_id) == 'on');
	}

	public static function get_topic_template() {
		return OsSettingsHelper::get_settings_value('zoom_meeting_topic_template', '{{service_name}}');
	}

	public static function generate_meeting_password() {
		return OsUtilHelper::random_text('nozero', 5);
	}


	public static function get_zoom_user_id_for_agent_id($agent_id) {
		return OsMetaHelper::get_agent_meta_by_key('zoom_user_id', $agent_id);
	}

	public static function get_zoom_meeting_id_for_booking_id($booking_id) {
		return OsMetaHelper::get_booking_meta_by_key('zoom_meeting_id', $booking_id);
	}

	public static function get_zoom_meeting_url_for_booking_id($booking_id) {
		return OsMetaHelper::get_booking_meta_by_key('zoom_meeting_join_url', $booking_id);
	}

	public static function get_zoom_meeting_password_for_booking_id($booking_id) {
		return OsMetaHelper::get_booking_meta_by_key('zoom_meeting_password', $booking_id);
	}

	public static function delete_meeting_for_booking_id( $booking_id ) {
		$meeting_id = self::get_zoom_meeting_id_for_booking_id( $booking_id );
		if ( ! $meeting_id ) {
			return;
		}

		// Delete meeting from Zoom only if no other bookings share it.
		$meeting_in_other_bookings = self::meeting_in_other_bookings( $meeting_id, $booking_id );
		if ( empty( $meeting_in_other_bookings ) ) {
			$response = self::do_request( "/meetings/{$meeting_id}", 'DELETE' );
			if ( empty( $response ) || $response['code'] != 204 ) {
				OsDebugHelper::log( 'Error deleting zoom meeting', 'zoom_error', $response );
			}
		}
	}

	public static function meeting_in_other_bookings($meeting_id, $booking_id) {
		$meta = new OsBookingMetaModel();
		return $meta->where([
			'meta_key' => 'zoom_meeting_id',
			'meta_value'=> $meeting_id,
			'object_id !=' => $booking_id
		])->get_results_as_models();
	}

	public static function update_or_create_meeting_for_booking($booking) {
		$meeting_id = self::get_zoom_meeting_id_for_booking_id($booking->id);
		if ($meeting_id) {

			$topic = self::get_topic_template();
			$topic = OsReplacerHelper::replace_all_vars($topic, array('customer' => $booking->customer, 'agent' => $booking->agent, 'booking' => $booking, 'order' => $booking->get_order()));

			$response = self::do_request("/meetings/{$meeting_id}", 'PATCH', ['topic' => $topic,
				'type' => self::MEETING_TYPE_SCHEDULED,
				'start_time' => $booking->format_start_date_and_time('Y-m-d\TH:i:s'),
				'timezone' => OsTimeHelper::get_wp_timezone_name(),
				'duration' => $booking->get_total_duration()]);

			if(empty($response) || $response['code'] != 204){
				OsDebugHelper::log('Error updating zoom meeting', 'zoom_error', $response);
			}
		} else {
			self::create_meeting($booking);
		}
	}

	/**
	 * Create Booking Meeting
	 * @param OsBookingModel $booking
	 *
	 * @return bool
	 */
	public static function create_meeting(OsBookingModel $booking): bool {
		$zoom_user_id = self::get_zoom_user_id_for_agent_id($booking->agent_id);
		if (!$zoom_user_id) return false;


		// In shared meeting mode, reuse existing Zoom meeting from another group booking in the same slot.
		if ( self::is_shared_meeting_mode( $booking->service_id ) ) {
			$existing_meeting = self::get_existing_meeting( $booking );
			if ( ! empty( $existing_meeting ) ) {
				self::save_meeting_metadata( $booking->id, $existing_meeting );
				return true;
			}
		}


		$meeting_password = self::generate_meeting_password();

		$topic = self::get_topic_template();
		$topic = OsReplacerHelper::replace_booking_vars($topic, $booking);
		$topic = OsReplacerHelper::replace_customer_vars($topic, $booking->customer);

		$response = self::do_request("/users/{$zoom_user_id}/meetings", 'POST', ['topic' => $topic,
			'type' => self::MEETING_TYPE_SCHEDULED,
			'start_time' => $booking->format_start_date_and_time('Y-m-d\TH:i:s'),
			'timezone' => OsTimeHelper::get_wp_timezone_name(),
			'duration' => $booking->get_total_duration(),
			'password' => $meeting_password]);

		if($response && !empty($response['code']) && $response['code'] == 201){
			if ($response['data'] && $response['data']['join_url'] && $response['data']['id']) {
				self::save_meeting_metadata( $booking->id, [
					'zoom_meeting_id'       => $response['data']['id'],
					'zoom_meeting_password' => $meeting_password,
					'zoom_meeting_join_url' => $response['data']['join_url']
				]);
				return true;
			}else{
				OsDebugHelper::log('Error creating meeting', 'zoom_error', $response['response']);
			}
		}else{
			OsDebugHelper::log('Error creating meeting', 'zoom_error', $response);
		}

		return false;
	}

	public static function get_list_of_zoom_users($include_blank = true): array {

		$response = self::do_request('/users/', 'GET', ['status' => 'active']);

		$formatted_users = [['value' => '', 'label' => __('Do not connect', 'latepoint-zoom')]];
		if($response && !empty($response['code']) && $response['code'] == 200){
			if ($response['data']['users']) {
				foreach ($response['data']['users'] as $zoom_user) {
					$formatted_users[] = ['value' => $zoom_user['id'], 'label' => implode(' ', [$zoom_user['first_name'], $zoom_user['last_name']]) . ' (' . $zoom_user['email'] . ')'];
				}
			}
		}
		return $formatted_users;
	}

	public static function is_enabled() {
		return OsSettingsHelper::is_on('enable_zoom');
	}

	/**
	 * Get existing meeting
	 *
	 * @param OsBookingModel $booking
	 *
	 * @return array|false
	 */
	public static function get_existing_meeting( OsBookingModel $booking ) {
		$group_bookings = new OsBookingModel();
		$group_bookings = $group_bookings->where( [
			'start_datetime_utc' => $booking->start_datetime_utc,
			'end_datetime_utc'   => $booking->end_datetime_utc,
			'service_id'         => $booking->service_id,
			'location_id'        => $booking->location_id,
			'agent_id'           => $booking->agent_id
		] )->should_not_be_cancelled()->get_results_as_models();

		foreach ( $group_bookings as $group_booking ) {
			$meeting_id = OsMetaHelper::get_booking_meta_by_key( 'zoom_meeting_id', $group_booking->id );
			if ( ! empty( $meeting_id ) ) {
				return [
					'zoom_meeting_id'       => $meeting_id,
					'zoom_meeting_password' => OsMetaHelper::get_booking_meta_by_key( 'zoom_meeting_password', $group_booking->id ),
					'zoom_meeting_join_url' => OsMetaHelper::get_booking_meta_by_key( 'zoom_meeting_join_url', $group_booking->id ),
				];
			}
		}

		return false;
	}

	/**
	 * @param $booking_id
	 * @param array $meeting_data
	 *
	 * @return void
	 */
	private static function save_meeting_metadata( $booking_id, array $meeting_data ): void {
		OsMetaHelper::save_booking_meta_by_key( 'zoom_meeting_id', $meeting_data['zoom_meeting_id'], $booking_id );
		OsMetaHelper::save_booking_meta_by_key( 'zoom_meeting_password', $meeting_data['zoom_meeting_password'], $booking_id );
		OsMetaHelper::save_booking_meta_by_key( 'zoom_meeting_join_url', $meeting_data['zoom_meeting_join_url'], $booking_id );
	}

	/**
	 * @param OsBookingModel $booking
	 * @param OsBookingModel $old_booking
	 * @param array $compare_fields
	 *
	 * @return bool
	 */
	public static function is_booking_changed( OsBookingModel $booking, OsBookingModel $old_booking, array $compare_fields = [] ):bool {
		foreach ($compare_fields as $field) {
			if ($booking->$field !== $old_booking->$field) {
				return true;
			}
		}
		return false;
	}
}