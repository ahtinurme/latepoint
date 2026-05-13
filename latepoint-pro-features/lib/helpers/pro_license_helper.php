<?php

class OsProLicenseHelper {


	public static function verify_license_key( $license_data ) {

		$license_key         = trim( strtolower( $license_data['license_key'] ) );
		$license_owner_name  = $license_data['full_name'];
		$license_owner_email = $license_data['email'];

		if ( empty( $license_data['license_key'] ) ) {
			return [
				'status'  => LATEPOINT_STATUS_ERROR,
				'message' => __( 'Please enter your license key', 'latepoint-pro-features' ),
			];
		}

		$glued_license = implode( '*|||*', array( $license_owner_name, $license_owner_email, $license_key ) );

		OsSettingsHelper::save_setting_by_name( 'license', $glued_license );

		$is_valid_license = false;
		// connect
		$post = array(
			'_nonce'      => wp_create_nonce( 'activate_licence' ),
			'license_key' => $license_key,
			'domain'      => OsUtilHelper::get_site_url(),
			'user_ip'     => OsUtilHelper::get_user_ip(),
			'data'        => $glued_license,
		);

		$url = OsUpdatesHelper::get_remote_url( '/wp/activate-license' );


		$request = wp_remote_post(
			$url,
			array(
				'body'      => $post,
				'sslverify' => LATEPOINT_PRO_SSL_VERIFY,
			)
		);

		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			$response = json_decode( $request['body'], true );
			if ( empty( $response['status'] ) ) {
				$message = __( 'Unable to verify license. Please try again or contact us at license@latepoint.com. Error code: UDF732S83', 'latepoint-pro-features' );
			} else {
				$message = $response['message'];
				if ( $response['status'] == 200 ) {
					$is_valid_license = true;
				}
			}
		} else {
			if ( is_wp_error( $request ) ) {
				OsDebugHelper::log( 'Update plugin error', 'update_plugin_error', [ 'error' => $request->get_error_messages() ] );
			}
			$message = __( 'Unable to verify license. Please try again or contact us at license@latepoint.com. Error code: SUYF8362', 'latepoint-pro-features' );
		}

		if ( $is_valid_license ) {
			$status = LATEPOINT_STATUS_SUCCESS;
			OsSettingsHelper::save_setting_by_name( 'is_active_license', 'yes' );
			OsSettingsHelper::save_setting_by_name( 'license_status_message', $message );
		} else {
			$status = LATEPOINT_STATUS_ERROR;
			OsSettingsHelper::save_setting_by_name( 'is_active_license', 'no' );
			OsSettingsHelper::save_setting_by_name( 'license_status_message', $message );
		}

		return [
			'status'  => $status,
			'message' => $message,
		];
	}
}
