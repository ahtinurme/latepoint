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

	const TEMPLATES_CACHE_TTL  = 15 * MINUTE_IN_SECONDS;
	const TEMPLATES_PAGE_LIMIT = 100;
	const TEMPLATES_MAX_PAGES  = 50;

	/**
	 * Fetches every approved WhatsApp template for the connected business account,
	 * walking Meta's cursor pagination and caching the merged list in a transient
	 * to avoid redundant API calls. Returns a partial result if a later page fails.
	 *
	 * @return array
	 * @throws Exception When the first page fails (preserves original UI error path).
	 */
	public static function get_templates(): array {
		$cache_key = 'latepoint_whatsapp_meta_templates_' . md5( self::get_business_account_id() );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$templates = [];
		$params    = [ 'limit' => self::TEMPLATES_PAGE_LIMIT ];

		for ( $page = 1; $page <= self::TEMPLATES_MAX_PAGES; $page ++ ) {
			$response = self::do_request( 'message_templates', 'GET', $params );

			if ( $response['code'] != 200 ) {
				$error_message = $response['data']['error']['message'] ?? ( $response['message'] ?? 'Error parsing whatsapp request' );
				if ( empty( $templates ) ) {
					throw new Exception( $error_message );
				}
				if ( class_exists( 'OsDebugHelper' ) ) {
					OsDebugHelper::log( 'WhatsApp templates pagination stopped on page ' . $page . ': ' . $error_message, 'whatsapp_meta_error' );
				}
				break;
			}

			if ( ! empty( $response['data']['data'] ) && is_array( $response['data']['data'] ) ) {
				$templates = array_merge( $templates, $response['data']['data'] );
			}

			$next_cursor = $response['data']['paging']['cursors']['after'] ?? '';
			if ( '' === $next_cursor || empty( $response['data']['paging']['next'] ) ) {
				break;
			}

			$params['after'] = $next_cursor;
		}

		set_transient( $cache_key, $templates, self::TEMPLATES_CACHE_TTL );

		return $templates;
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