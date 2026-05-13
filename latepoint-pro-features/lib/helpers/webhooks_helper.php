<?php 

class OsWebhooksHelper {
  
	public function __construct() {
	}

	public static function run_webhook( string $url, array $content = [], $activity_data = [] ) {
		$result = [
			'status'             => '',
			'message'            => '',
			'to'                 => $url,
			'content'            => $content,
			'processor_code'     => 'webhooks_addon',
			'processor_name'     => 'Webhooks Addon',
			'processed_datetime' => '',
			'extra_data'         => [
				'activity_data' => $activity_data,
			],
			'errors'             => [],
		];

		$status = wp_safe_remote_post( $url, [ 'body' => $content ] );
		if ( ! is_wp_error( $status ) ) {
			$result['status']  = LATEPOINT_STATUS_SUCCESS;
			$result['message'] = __( 'Webhook executed', 'latepoint-pro-features' );
		} else {
			$result['status']  = LATEPOINT_STATUS_ERROR;
			$result['message'] = $status->get_error_message();
		}
		OsWebhooksHelper::log_webhook( $result );

		return $result;
	}

	/**
	 * @param array $result
	 * @return OsActivityModel
	 */
	public static function log_webhook( array $result ) {
		if ( empty( $result['processed_datetime'] ) ) {
			$result['processed_datetime'] = OsTimeHelper::now_datetime_in_db_format();
		}
		$data = [
			'code'        => 'http_request',
			'description' => json_encode( $result ),
		];
		if ( ! empty( $result['extra_data']['activity_data'] ) ) {
			$data = array_merge( $data, $result['extra_data']['activity_data'] );
		}
		$activity = OsActivitiesHelper::create_activity( $data );
		return $activity;
	}

	public static function allowed_fields() {
		$allowed_params = array(
			'url',
			'name',
			'status',
			'trigger',
		);
		return $allowed_params;
	}

	public static function prepare_to_save( $array_to_filter ) {
		// !!TODO
		return $array_to_filter;
	}

	public static function has_validation_errors( $webhook ) {
		$errors = [];
		if ( empty( $webhook['url'] ) ) {
			$errors[] = __( 'Webhook URL cannot be empty', 'latepoint-pro-features' );
		} else {
			if ( ! wp_http_validate_url( $webhook['url'] ) ) {
				$errors[] = __( 'Webhook URL must be a valid URL', 'latepoint-pro-features' );
			}
		}
		if ( empty( $webhook['trigger'] ) ) {
			$errors[] = __( 'Select a trigger event for this hook', 'latepoint-pro-features' );
		}
		if ( empty( $errors ) ) {
			return false;
		} else {
			return $errors;
		}
	}

	public static function save( $webhook ) {
		$webhooks = self::get_webhooks_arr();
		if ( ! isset( $webhook['id'] ) || empty( $webhook['id'] ) ) {
			$webhook['id'] = self::generate_webhook_id();
		}
		$webhook['url']             = wp_http_validate_url( $webhook['url'] );
		$webhooks[ $webhook['id'] ] = $webhook;
		return self::save_webhooks_arr( $webhooks );
	}

	public static function delete( $webhook_id ) {
		if ( isset( $webhook_id ) && ! empty( $webhook_id ) ) {
			$webhooks = self::get_webhooks_arr();
			unset( $webhooks[ $webhook_id ] );
			return self::save_webhooks_arr( $webhooks );
		} else {
			return false;
		}
	}


	public static function generate_webhook_id() {
		return 'wh_' . OsUtilHelper::random_text( 'alnum', 8 );
	}

	public static function get_webhooks_arr() {
		$webhooks = OsSettingsHelper::get_settings_value( 'webhooks', false );
		if ( $webhooks ) {
			return json_decode( $webhooks, true );
		} else {
			return [];
		}
	}

	public static function save_webhooks_arr( $webhooks_arr ) {
		$webhooks_arr = self::prepare_to_save( $webhooks_arr );
		return OsSettingsHelper::save_setting_by_name( 'webhooks', json_encode( $webhooks_arr ) );
	}
}
