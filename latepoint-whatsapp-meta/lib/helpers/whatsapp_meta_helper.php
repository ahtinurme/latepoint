<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class OsWhatsappMetaHelper {

	static array $templates;
	static string $phone_number_id;
	static string $business_account_id;
	static string $system_user_access_token;


	/**
	 * @param string $to
	 * @param array $data
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function send_whatsapp_message( string $to, array $data ): bool {

		$url = "https://graph.facebook.com/v22.0/" . self::get_phone_number_id() . "/messages";

		$payload = [
			'messaging_product' => 'whatsapp',
			'to'                => $to,
			'type'              => $data['type'],
			'template'          => [
				'name'       => $data['template_name'],
				'language'   =>
					[
						'code' => $data['template_language']
					],
				'components' => $data['components'],
			]
		];

		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . self::get_system_user_access_token(),
				'Content-Type'  => 'application/json'
			],
			'body'    => json_encode( $payload ),
			'method'  => 'POST',
			'timeout' => 30
		];

		$response = wp_remote_post( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			$message = json_decode( $response['body'], true );
			if ( ! empty( $message['error'] ) ) {
				$error_message = $message['error']['message'];
				if(!empty($message['error']['error_data']['details'])) $error_message.= ': '.$message['error']['error_data']['details'];
				throw new Exception( $error_message );
			} else {
				return true;
			}
		} else {
			throw new Exception( $response->get_error_message() );
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public static function get_templates(): array {
		$response = self::do_request( 'message_templates' );
		if ( $response['code'] == 200 ) {
			if ( ! empty( $response['data']['data'] ) ) {
				return $response['data']['data'];
			} else {
				return [];
			}
		} else {
			throw new Exception( $response['data']['error']['message'] ?? 'Error parsing whatsapp request' );
		}
	}

	public static function get_system_user_access_token(): string {
		return self::$system_user_access_token ?? OsSettingsHelper::get_settings_value( 'notifications_whatsapp_meta_system_user_access_token', '' );
	}

	public static function get_phone_number_id(): string {
		return self::$phone_number_id ?? OsSettingsHelper::get_settings_value( 'notifications_whatsapp_meta_phone_number_id', '' );
	}

	public static function get_business_account_id(): string {
		return self::$business_account_id ?? OsSettingsHelper::get_settings_value( 'notifications_whatsapp_meta_business_account_id', '' );
	}


	public static function do_request( string $path, string $method = 'GET', array $data = [] ): array {
		$business_account_id = self::get_business_account_id();
		$endpoint            = 'https://graph.facebook.com/v22.0/' . $business_account_id . '/' . $path;
		try {
			$access_token = self::get_system_user_access_token();
			$args         = [
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token
				]
			];

			switch ( $method ) {
				case 'GET':
					$response = wp_remote_get( $endpoint . '?' . http_build_query( $data ), $args );
					break;
				case 'POST':
					if ( ! empty( $data ) ) {
						$args['body'] = json_encode( $data );
					}
					$args['headers']['content-type'] = 'application/json';
					$response                        = wp_remote_post( $endpoint, $args );
					break;
				case 'DELETE':
				case 'PUT':
				case 'PATCH':
					if ( ! empty( $data ) ) {
						$args['body'] = json_encode( $data );
					}
					$args['headers']['content-type'] = 'application/json';
					$args['method']                  = $method;
					$response                        = wp_remote_request( $endpoint, $args );
					break;
			}
			if ( ! is_wp_error( $response ) ) {
				if ( ! empty( $response['response'] ) ) {
					return [ 'data' => self::get_body_from_response( $response ), 'code' => $response['response']['code'], 'message' => $response['response']['message'], 'response' => $response ];
				} else {
					return [ 'data' => [], 'code' => 500, 'message' => 'Empty response', 'response' => $response ];
				}
			} else {
				$error_message = $response->get_error_message();

				return [ 'data' => [], 'code' => 500, 'message' => $error_message ];
			}
		} catch ( Exception $e ) {
			return [ 'data' => [], 'code' => 500, 'message' => 'Missing or invalid token' ];
		}
	}

	public static function get_body_from_response( $response ) {
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

}