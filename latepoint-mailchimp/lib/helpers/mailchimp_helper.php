<?php

class OsMailchimpHelper {
	public static $marketing_system_name = 'mailchimp';
	public static $error = false;
	public static $lists = null;

	public static $mailchimp = false;

	public static function get_api_key() {
		return OsSettingsHelper::get_settings_value('mailchimp_api_key');
	}

	public static function set_api_key() {
		if (self::get_api_key()) {
			try {
				self::$mailchimp = new \DrewM\MailChimp\MailChimp(self::get_api_key());
			} catch (Exception $e) {
				OsDebugHelper::log('Marketing system error: Mailchimp', 'marketing_system_error_mailchimp', $e->getMessage());
				self::$error = $e->getMessage();
			}
		}
	}

	public static function add_contact_to_list(string $list_id, string $contact_email, $contact_first_name = '', $contact_last_name = '', $activity_data = []): array {

		$result = [
			'status' => '',
			'message' => '',
			'to' => $list_id,
			'content' => $contact_email,
			'processor_code' => 'mailchimp',
			'processor_name' => 'Mailchimp',
			'processed_datetime' => '',
			'extra_data' => [
				'activity_data' => $activity_data
			],
			'errors' => [],
		];
		if (empty($list_id) || empty($contact_email)){
			$result['status'] = LATEPOINT_STATUS_ERROR;
			$result['message'] = __('Email Address or List ID can not be empty', 'latepoint-mailchimp');
			return $result;
		}

		try {
			$response = self::$mailchimp->post("lists/$list_id/members", [
				'email_address' => $contact_email,
				'status' => 'subscribed',
				'merge_fields' => [
					"FNAME" => $contact_first_name,
					"LNAME" => $contact_last_name
				]
			]);
			if (self::$mailchimp->success()) {
				$result['status'] = LATEPOINT_STATUS_SUCCESS;
				$result['message'] = __('Contact added to a Mailchimp list successfully', 'latepoint-mailchimp');
				OsMailchimpHelper::log_registering_contact_in_list($result);
			} else {
				if (!empty($response['status']) && $response['status'] === 400 && !empty($response['title']) && $response['title'] === 'Member Exists') {
					$result['status'] = LATEPOINT_STATUS_SUCCESS;
					$result['message'] = __('Contact already exists in the Mailchimp list', 'latepoint-mailchimp');
					OsMailchimpHelper::log_registering_contact_in_list($result);
				} else {
					$result['status'] = LATEPOINT_STATUS_ERROR;
					$result['message'] = self::$mailchimp->getLastError();
					OsDebugHelper::log('Marketing system error: Mailchimp, adding contact to list failed', 'marketing_system_error_mailchimp', self::$mailchimp->getLastError());
				}
			}

		} catch (Exception $e) {
			OsDebugHelper::log('Marketing system error: Mailchimp, adding contact to list failed', 'marketing_system_error_mailchimp', $e->getMessage());

			$result['status'] = LATEPOINT_STATUS_ERROR;
			$result['message'] = $e->getMessage();
		}
		return $result;
	}

	public static function get_list_of_audiences(): array {
		if (isset(self::$lists)) return self::$lists;
		self::$lists = [];
		$response = self::$mailchimp->get('lists');
		if ($response && !empty($response['lists'])) {
			foreach ($response['lists'] as $list) {
				self::$lists[$list['id']] = $list['name'];
			}
		}
		return self::$lists;
	}

	private static function log_registering_contact_in_list(array $result) {
		if (empty($result['processed_datetime'])) {
			$result['processed_datetime'] = OsTimeHelper::now_datetime_in_db_format();
		}
		$data = [
			'code' => 'mailchimp_contact_added_to_list',
			'description' => json_encode($result)
		];
		if (!empty($result['extra_data']['activity_data'])) $data = array_merge($data, $result['extra_data']['activity_data']);
		$activity = OsActivitiesHelper::create_activity($data);
		return $activity;
	}
}