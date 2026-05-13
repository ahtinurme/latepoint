<?php

class OsShortenerShortioHelper {



	public static function get_api_key() {
		return OsSettingsHelper::get_settings_value('shortio_api_key');
	}

	public static function get_domain() {
		return OsSettingsHelper::get_settings_value('shortio_api_domain');
	}


	public static function get_shortlink( $original_url ) {
		$short_url = self::shorten_url( $original_url );
		return $short_url ?: $original_url;
	}


	/**
	 * Shortens a URL using the Short.io API.
	 *
	 * @param string $original_url The original URL to shorten.
	 * @return string|false The shortened URL or false on failure.
	 */
	private static function shorten_url( string $original_url) {
		$domain = self::get_domain();
		$api_key = self::get_api_key();

		if (empty($api_key) || empty($domain)) {
			return false;
		}

		$data = array(
			'originalURL' => $original_url,
			'domain' => $domain,
			'allowDuplicates' => false,
		);

		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Authorization' => $api_key,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json'
			),
			'body' => json_encode($data),
			'timeout' => 15
		);

		$response = wp_remote_request(LATEPOINT_SHORT_IO_CONNECT_URL, $args);

		if (is_wp_error($response)) {
			OsDebugHelper::log('Short.io API Error: ' . $response->get_error_message());
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (isset($data['shortURL'])) {
			return $data['shortURL'];
		}

		if (isset($data['error'])) {
			OsDebugHelper::log('Short.io API Error: ' . $data['error']);
		}

		return false;
	}

	public static function short_content_links( $text, $vars ) {
		if ( !empty($vars['sender_type']) &&  $vars['sender_type'] === 'send_sms' ) {
		$pattern = '/(https?:\/\/[^\s<>\(\)\[\]]+?)([.,;!?]?)(?=\s|<|"|\'|\)|\]|$)/iu';

			$callback = static function ( $matches ) {
				$original_url = $matches[1];
				$punctuation = $matches[2] ?? '';
				return self::get_shortlink( $original_url ) . $punctuation;
			};

			$result = preg_replace_callback( $pattern, $callback, $text );
			if (!empty($result)) {
				return $result;
			}
		}


		return $text;
	}

}