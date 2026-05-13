<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureCloudflareTurnstileHelper {

	public static function get_secret_key() {
		return OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_secret_key' );
	}

	public static function process_step_verify( $step_code, $booking_object, $params = [] ) {
		if ( $step_code == 'verify' ) {
			if ( OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' )
				&& ! empty( OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_site_key' ) )
				&& ! empty( OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_secret_key' ) )
			) {
				$result = OsFeatureCloudflareTurnstileHelper::verify_turnstile_token( $params['cf-turnstile-response'] );
				if ( is_wp_error( $result ) ) {
					wp_send_json(
						array(
							'status'  => LATEPOINT_STATUS_ERROR,
							'message' => $result->get_error_message(),
						)
					);
				}
			}
		}
	}

	public static function get_public_key() {
		return OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_site_key' );
	}

	public static function verify_turnstile_token( $token ) {
		if ( empty( $token ) ) {
			return new WP_Error( 'cloudflare_turnstile_key_invalid', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}

		$secret_key = self::get_secret_key();
		if ( empty( $secret_key ) ) {
			OsDebugHelper::log( __( 'Invalid secret key for cloudflare.', 'latepoint-pro-features' ), 'cloudflare_turnstile_error' );
			return new WP_Error( 'cloudflare_turnstile_key_invalid', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}

		$request_body = [
			'secret'   => $secret_key,
			'response' => $token,
		];

		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			[
				'body'    => $request_body,
				'timeout' => 15,
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
					'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			OsDebugHelper::log( $response->get_error_message(), 'cloudflare_turnstile_error' );
			return new WP_Error( 'cloudflare_turnstile_api_error', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			OsDebugHelper::log( "Turnstile API returned HTTP {$response_code}", 'cloudflare_turnstile_error' );
			return new WP_Error( 'cloudflare_turnstile_api_error', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			OsDebugHelper::log( __( 'Turnstile: Invalid JSON response from API', 'latepoint-pro-features' ), 'cloudflare_turnstile_error' );
			return new WP_Error( 'cloudflare_turnstile_json_error', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}

		// Check for API errors
		if ( isset( $data['error-codes'] ) && ! empty( $data['error-codes'] ) ) {
			OsDebugHelper::log( 'Turnstile API Errors: ' . implode( ', ', $data['error-codes'] ), 'cloudflare_turnstile_error' );
			return new WP_Error( 'cloudflare_turnstile_json_error', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}

		if ( isset( $data['success'] ) && $data['success'] === true ) {
			return true;
		} else {
			return new WP_Error( 'cloudflare_turnstile_key_invalid', __( 'Verification Error.', 'latepoint-pro-features' ) );
		}
	}

	public static function add_turnstile_to_verify_step( $step_code ) {
		if ( $step_code != 'verify' ) {
			return;
		}

		// Only add turnstile if enabled in settings and keys are present
		if (
			! OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' )
			|| empty( OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_site_key' ) )
			|| empty( OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_secret_key' ) )
		) {
			return;
		}
		$sitekey = OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_site_key' );
		// $sitekey = '3x00000000000000000000FF'; // ALWAYS_INTERACTIVE
		// $sitekey = '2x00000000000000000000AB'; // ALWAYS_BLOCKS
		// $sitekey = '1x00000000000000000000AA'; // ALWAYS_PASSES
		$html = '<div class="turnstile-widget-wrapper"><div class="turnstile-loading-message">' . esc_html__( 'Verifying that you are not a robot...', 'latepoint-pro-features' ) . '</div><div class="turnstile-widget" data-appearance="interaction-only" data-size="flexible" data-sitekey="' . esc_attr( $sitekey ) . '"></div></div>';
		echo $html;
	}



	public static function validate_turnstile_settings( $settings ) {
		if ( ! isset( $settings['enable_cloudflare_turnstile'] ) ) {
			return;
		}
		if ( $settings['enable_cloudflare_turnstile'] !== 'on' ) {
			return;
		}
		$site_key   = isset( $settings['cloudflare_turnstile_site_key'] ) ? trim( $settings['cloudflare_turnstile_site_key'] ) : '';
		$secret_key = isset( $settings['cloudflare_turnstile_secret_key'] ) ? trim( $settings['cloudflare_turnstile_secret_key'] ) : '';

		// Show error since keys are missing
		if ( empty( $site_key ) || empty( $secret_key ) ) {
			wp_send_json(
				array(
					'status'  => LATEPOINT_STATUS_ERROR,
					'message' => esc_html__( 'Cloudflare Turnstile will not work as required keys are empty. Please provide both Site Key and Secret Key.', 'latepoint-pro-features' ),
				)
			);
		}
	}

	public static function output_customer_security_settings( string $html = '' ) {
		ob_start();
		?>
			<?php echo OsFormHelper::toggler_field( 'settings[log_customer_ip_address]', __( 'Log Customer IP Address for Orders', 'latepoint-pro-features' ), ( OsSettingsHelper::is_on( 'log_customer_ip_address' ) ), false, false, [ 'sub_label' => __( 'If enabled, will save IP Address of a customer and you can view it under order log', 'latepoint-pro-features' ) ] ); ?>
			<?php echo OsFormHelper::toggler_field( 'settings[enable_cloudflare_turnstile]', __( 'Enable Cloudflare Turnstile CAPTCHA', 'latepoint-pro-features' ), ( OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' ) ), 'lp-cloudflare-turnstile-settings', false, [ 'sub_label' => __( 'Enable Captcha alternative from Cloudflare called Turnstile.', 'latepoint-pro-features' ) ] ); ?>
			<div id="lp-cloudflare-turnstile-settings" <?php echo ( OsSettingsHelper::is_on( 'enable_cloudflare_turnstile' ) ) ? '' : 'style="display: none;"' ?>>
				<?php echo OsFormHelper::text_field( 'settings[cloudflare_turnstile_site_key]', __( 'Turnstile Site Key', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_site_key' ), [ 'theme' => 'simple' ] ); ?>
				<?php echo OsFormHelper::password_field( 'settings[cloudflare_turnstile_secret_key]', __( 'Turnstile Secret Key', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'cloudflare_turnstile_secret_key' ), [ 'theme' => 'simple' ] ); ?>
			</div>
		<?php
		$html = ob_get_clean();
		return $html;
	}
}
