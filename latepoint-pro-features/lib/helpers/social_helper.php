<?php

class OsSocialHelper {

	public static function should_show_customer_login_social_options( $should_show ) {
		$should_show = ( OsSettingsHelper::is_using_facebook_login() || OsSettingsHelper::is_using_google_login() );
		return $should_show;
	}

	public static function output_customer_login_social_options() {
		if ( OsAuthHelper::is_customer_auth_enabled() && ( OsSettingsHelper::is_using_google_login() || OsSettingsHelper::is_using_facebook_login() ) ) { ?>
			<div class="os-social-login-options">
				<?php if ( OsSettingsHelper::is_using_facebook_login() ) { ?>
					<div id="facebook-signin-btn"
						 data-login-action="<?php echo OsRouterHelper::build_route_name( 'auth', 'login_customer_using_facebook_token' ); ?>"
						 class="os-social-login-facebook os-social-login-option">
						<img src="<?php echo LatePoint::images_url() . 'facebook-logo-compact.png' ?>"/>
						<span><?php _e( 'Continue with Facebook', 'latepoint-pro-features' ); ?></span>
					</div>
				<?php } ?>
				<?php if ( OsSettingsHelper::is_using_google_login() ) { ?>
					<div id="google-signin-btn"
						 data-login-action="<?php echo OsRouterHelper::build_route_name( 'auth', 'login_customer_using_google_token' ); ?>"
						 class="os-social-login-google os-social-login-option">
						<img src="<?php echo LatePoint::images_url() . 'google-logo-compact.png' ?>"/>
						<span><?php _e( 'Continue with Google', 'latepoint-pro-features' ); ?></span>
					</div>
				<?php } ?>
			</div>
		<?php }
	}

	public static function output_customer_settings() {
		?>
		<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e( 'Social Login', 'latepoint-pro-features' ) ?></h3>
		</div>
		<div class="sub-section-content">
			<?php echo OsFormHelper::toggler_field( 'settings[enable_google_login]', __( 'Enable login with Google', 'latepoint-pro-features' ), ( OsSettingsHelper::get_settings_value( 'enable_google_login' ) == 'on' ), 'lp-google-settings', false, [ 'sub_label' => __( 'Display Google Login button on customer login and registration forms', 'latepoint-pro-features' ) ] ); ?>
			<div class="os-mb-2"
				 id="lp-google-settings" <?php echo ( OsSettingsHelper::get_settings_value( 'enable_google_login' ) == 'on' ) ? '' : 'style="display: none;"' ?>>
				<?php echo OsFormHelper::text_field( 'settings[google_client_id]', __( 'Google Client ID', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'google_client_id' ), [ 'theme' => 'simple' ] ); ?>
				<?php echo OsFormHelper::password_field( 'settings[google_client_secret]', __( 'Google Client Secret', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'google_client_secret' ), [ 'theme' => 'simple' ] ); ?>
			</div>
			<?php echo OsFormHelper::toggler_field( 'settings[enable_facebook_login]', __( 'Enable login with Facebook', 'latepoint-pro-features' ), ( OsSettingsHelper::get_settings_value( 'enable_facebook_login' ) == 'on' ), 'lp-facebook-settings', false, [ 'sub_label' => __( 'Display Facebook Login button on customer login and registration forms', 'latepoint-pro-features' ) ] ); ?>
			<div id="lp-facebook-settings" <?php echo ( OsSettingsHelper::get_settings_value( 'enable_facebook_login' ) == 'on' ) ? '' : 'style="display: none;"' ?>>
				<?php echo OsFormHelper::text_field( 'settings[facebook_app_id]', __( 'Facebook App ID', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'facebook_app_id' ), [ 'theme' => 'simple' ] ); ?>
				<?php echo OsFormHelper::password_field( 'settings[facebook_app_secret]', __( 'Facebook App Secret', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'facebook_app_secret' ), [ 'theme' => 'simple' ] ); ?>
			</div>
		</div>
		</div>
		<?php
	}

	public static function set_social_user_by_token( array $social_user, string $social_network, string $token ): array {
		switch ( $social_network ) {
			case 'facebook':
				$social_user = OsSocialHelper::get_facebook_user_info_by_token( $token );
				break;
			case 'google':
				$social_user = OsSocialHelper::get_google_user_info_by_token( $token );
				break;
		}
		return $social_user;
	}

	public static function get_google_user_info_by_token( string $token ) {

		$url        = "https://www.googleapis.com/oauth2/v3/tokeninfo?id_token={$token}";
		$ch         = curl_init();
		$curlConfig = array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
		);
		curl_setopt_array( $ch, $curlConfig );
		$result = curl_exec( $ch );
		curl_close( $ch );
		$userinfo = json_decode( $result, true );

		$user = array();

		if ( $userinfo['sub'] ) {
				$user['social_id']  = $userinfo['sub'];
			$user['first_name']     = $userinfo['given_name'];
				$user['last_name']  = $userinfo['family_name'];
				$user['email']      = $userinfo['email'];
				$user['avatar_url'] = $userinfo['picture'];
		} else {
			$user['error'] = $userinfo['error_description'];
		}

		return $user;
	}

	public static function get_facebook_user_info_by_token( string $token ) {

		$url        = "https://graph.facebook.com/me?fields=id,email,last_name,first_name,picture.width(1000)&access_token={$token}";
		$ch         = curl_init();
		$curlConfig = array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
		);
		curl_setopt_array( $ch, $curlConfig );
		$result = curl_exec( $ch );
		curl_close( $ch );
		$userinfo = json_decode( $result, true );

		$user = array();

		if ( $userinfo['id'] ) {
				$user['social_id']  = $userinfo['id'];
			$user['first_name']     = $userinfo['first_name'];
				$user['last_name']  = $userinfo['last_name'];
				$user['email']      = $userinfo['email'];
				$user['avatar_url'] = isset( $userinfo['picture'] ) ? $userinfo['picture']['data']['url'] : '';
		} else {
			$user['error'] = $userinfo['error_description'];
		}

		return $user;
	}
}