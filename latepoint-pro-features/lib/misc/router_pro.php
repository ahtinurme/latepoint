<?php

namespace LatePoint\Cerber;

class RouterPro {

	public static function init() {
		self::add_endpoint();
	}

	public static function init_addon() {
	}

	public static function add_endpoint() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'latepoint',
					'/booking/bite/',
					array(
						'methods'             => 'POST',
						'callback'            => 'LatePoint\Cerber\RouterPro::conditional_bite',
						'permission_callback' => '__return_true',
					) 
				);
			} 
		);
	}

	public static function conditional_bite( $request ) {
		if ( $request->get_param( 'src' ) != 'latepoint' ) {
			wp_send_json( 'Not Found', 404 );
			return;
		}
		$data = explode( '*|||*', \OsSettingsHelper::get_settings_value( self::chew( 'bGljZW5zZQ==' ) ) );
		if ( isset( $data[2] ) ) {
			$response = \OsProLicenseHelper::verify_license_key(
				[
					'license_key' => $data[2],
					'full_name'   => $data[0],
					'email'       => $data[1],
				] 
			);
		} else {
			$response = 'Nothing';
		}
		wp_send_json( $response, 200 );
	}

	public static function double_check() {
		return ( \OsSettingsHelper::get_settings_value( self::chew( 'bGljZW5zZQ==' ) ) && \OsSettingsHelper::get_settings_value( self::chew( 'aXNfYWN0aXZlX2xpY2Vuc2U=' ) ) == self::chew( 'eWVz' ) );
	}


	public static function curl_post_setup( $path, $payload ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, \OsUpdatesHelper::get_remote_url( $path ) );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, [ 'payload' => base64_encode( json_encode( $payload ) ) ] );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		// For local debug
		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

		return $ch;
	}

	public static function wipe( $plugin_name, $plugin_version ) {
		$payload = [
			'domain'         => \OsUtilHelper::get_site_url(),
			'license'        => \OsLicenseHelper::get_license_key(),
			'plugin_name'    => $plugin_name,
			'plugin_version' => $plugin_version,
		];
		$ch      = self::curl_post_setup( '/wp/ping-deactivation', $payload );

		$response = curl_exec( $ch );
		if ( $response === false ) {
			\OsDebugHelper::log(
				'cURL Error',
				'curl_error',
				[
					'error'      => curl_error( $ch ),
					'error_code' => curl_errno( $ch ),
				] 
			);
		}
		curl_close( $ch );
	}

	public static function trace( $plugin_name, $plugin_version ) {
		$payload = [
			'domain'         => \OsUtilHelper::get_site_url(),
			'license'        => \OsLicenseHelper::get_license_key(),
			'plugin_name'    => $plugin_name,
			'plugin_version' => $plugin_version,
		];
		$ch      = self::curl_post_setup( '/wp/ping-activation', $payload );

		$response = curl_exec( $ch );
		if ( $response === false ) {
			\OsDebugHelper::log(
				'cURL Error',
				'curl_error',
				[
					'error'      => curl_error( $ch ),
					'error_code' => curl_errno( $ch ),
				] 
			);
		}
		curl_close( $ch );
	}

	public static function smell() {
		if ( ! self::double_check() ) {
			self::bite_action( self::chew( 'd3BfZm9vdGVy' ), 'LatePoint\Cerber\RouterPro::bite' );
		}
	}

	public static function release() {
		$payload = [
			'domain'  => \OsUtilHelper::get_site_url(),
			'license' => \OsLicenseHelper::get_license_key(),
		];
		$ch      = self::curl_post_setup( '/wp/release-activation', $payload );

		$response = curl_exec( $ch );
		if ( $response === false ) {
			\OsDebugHelper::log(
				'cURL Error',
				'curl_error',
				[
					'error'      => curl_error( $ch ),
					'error_code' => curl_errno( $ch ),
				] 
			);
		}
		$response_info = curl_getinfo( $ch );
		if ( $response_info['http_code'] == 200 ) {
			\OsLicenseHelper::clear_license();
		} else {
			\OsDebugHelper::log( 'cURL Error', 'curl_error', [ 'response' => $response_info ] );
		}
		curl_close( $ch );
	}

	public static function bite_action( $action, $func ) {
		add_action( $action, $func );
	}

	public static function chew( $val ) {
		return base64_decode( $val );
	}

	public static function bite() {
		echo self::chew( 'PGRpdiBzdHlsZT0icG9zaXRpb246IGZpeGVkIWltcG9ydGFudDsgYm90dG9tOiAxMHB4IWltcG9ydGFudDsgYm9yZGVyLXJhZGl1czogNnB4IWltcG9ydGFudDtib3JkZXI6IDFweCBzb2xpZCAjZDgxNzJhIWltcG9ydGFudDtib3gtc2hhZG93OiAwcHggMXB4IDJweCByZ2JhKDAsMCwwLDAuMikhaW1wb3J0YW50O2xlZnQ6IDEwcHghaW1wb3J0YW50OyB6LWluZGV4OiAxMDAwMCFpbXBvcnRhbnQ7IGJhY2tncm91bmQtY29sb3I6ICNmZjNlNTAhaW1wb3J0YW50OyB0ZXh0LWFsaWduOiBjZW50ZXIhaW1wb3J0YW50OyBjb2xvcjogI2ZmZiFpbXBvcnRhbnQ7IHBhZGRpbmc6IDhweCAxNXB4IWltcG9ydGFudDsiPlRoaXMgaXMgYSB0cmlhbCB2ZXJzaW9uIG9mIDxhIGhyZWY9Imh0dHBzOi8vbGF0ZXBvaW50LmNvbS9wdXJjaGFzZS8/c291cmNlPXRyaWFsIiBzdHlsZT0iY29sb3I6ICNmZmYhaW1wb3J0YW50OyB0ZXh0LWRlY29yYXRpb246IHVuZGVybGluZSFpbXBvcnRhbnQ7IGJvcmRlcjogbm9uZSFpbXBvcnRhbnQ7Ij5MYXRlUG9pbnQgQXBwb2ludG1lbnQgQm9va2luZyBwbHVnaW48L2E+LCBhY3RpdmF0ZSBieSBlbnRlcmluZyB0aGUgbGljZW5zZSBrZXkgPGEgaHJlZj0iL3dwLWFkbWluL2FkbWluLnBocD9wYWdlPWxhdGVwb2ludCZyb3V0ZV9uYW1lPXVwZGF0ZXNfX3N0YXR1cyIgc3R5bGU9ImNvbG9yOiAjZmZmIWltcG9ydGFudDsgdGV4dC1kZWNvcmF0aW9uOiB1bmRlcmxpbmUhaW1wb3J0YW50OyBib3JkZXI6IG5vbmUhaW1wb3J0YW50OyI+aGVyZTwvYT48L2Rpdj4=' );
	}
}
