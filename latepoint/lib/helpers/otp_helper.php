<?php
class OsOTPHelper {

	private static int $max_verification_attempts        = 10;  // Max attempts per OTP
	private static int $max_generation_attempts_per_hour = 60;    // Max OTP generations per hour
	public static int $otp_expires_in_minutes            = 10;    // Max OTP generations per hour
	public static int $verification_expires_in_minutes   = 30;

	public static function create_verification_token( $contact_value, $contact_type, $via = 'otp' ): string {
		$payload_data = [
			'contact_value' => $contact_value,
			'contact_type'  => $contact_type,
			'verified_via'  => $via,
			'exp'           => time() + ( self::$verification_expires_in_minutes * 60 ),
			'iat'           => time(),
		];

		$payload   = base64_encode( json_encode( $payload_data ) );
		$signature = hash_hmac( 'sha256', $payload, self::get_secret() );

		return $payload . '.' . $signature;
	}

	public static function get_secret() {
		return wp_salt( 'secure_auth' );
	}

	public static function validate_verification_token( $token ): array {
		if ( empty( $token ) ) {
			return [
				'valid' => false,
				'error' => 'Token required',
			];
		}

		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			return [
				'valid' => false,
				'error' => 'Malformed token',
			];
		}

		[$payload, $signature] = $parts;

		// Verify signature
		$expected_signature = hash_hmac( 'sha256', $payload, self::get_secret() );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return [
				'valid' => false,
				'error' => 'Invalid signature',
			];
		}

		// Decode payload
		$data = json_decode( base64_decode( $payload ), true );
		if ( ! $data ) {
			return [
				'valid' => false,
				'error' => 'Invalid payload',
			];
		}

		// Check expiration
		if ( time() > $data['exp'] ) {
			return [
				'valid' => false,
				'error' => 'Token expired',
			];
		}

		return [
			'valid' => true,
			'data'  => $data,
		];
	}


	public static function generateAndSendOTP( $contact_value, $contact_type, $delivery_method ) {
		if ( ! self::isValidCombination( $contact_type, $delivery_method ) ) {
			return new WP_Error( 'otp_generation_error', 'Invalid delivery method for contact type' );
		}

		if ( ! self::checkRateLimit( $contact_value ) ) {
			return new WP_Error( 'otp_generation_error', 'Too many attempts. Please try again later.' );
		}

		if ( $contact_type == 'email' ) {
			if ( ! OsUtilHelper::is_valid_email( $contact_value ) ) {
				return new WP_Error( 'otp_generation_error', 'Invalid email address' );
			}
		}

		// Cancel old active OTPs for this contact
		self::cancelOldOTPs( $contact_value );

		// Generate new OTP
		$otp_code   = str_pad( random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$otp_hash   = wp_hash_password( $otp_code );
		$expires_at = OsTimeHelper::custom_datetime_utc_in_db_format( sprintf( '+%d minutes', self::$otp_expires_in_minutes ) );

		$otp                  = new OsOTPModel();
		$otp->contact_value   = $contact_value;
		$otp->contact_type    = $contact_type;
		$otp->delivery_method = $delivery_method;
		$otp->otp_hash        = $otp_hash;
		$otp->expires_at      = $expires_at;
		$otp->status          = LATEPOINT_CUSTOMER_OTP_CODE_STATUS_ACTIVE;
		$otp->attempts        = 0;
		if ( ! $otp->save() ) {
			return new WP_Error( 'otp_generation_error', $otp->get_error_messages() );
		}

		// Send OTP
		return self::sendOTP( $otp_code, $otp );
	}


	public static function otp_input_box_html( string $contact_type, string $contact_value, string $delivery_method ): string {

		$title = ( 'email' === $contact_type ) ? esc_html__( 'Verify your email', 'latepoint' ) : esc_html__( 'Verify your phone number', 'latepoint' );

		$message          = '';
		$message         .= '<div class="latepoint-customer-otp-input-wrapper os-customer-wrapped-box">';
			$message     .= '<div class="latepoint-customer-otp-close"><i class="latepoint-icon latepoint-icon-common-01"></i></div>';
			$message     .= '<div class="latepoint-customer-box-title">' . $title . '</div>';
			$message     .= '<div class="latepoint-customer-box-desc">' . sprintf( esc_html__( 'Enter the code we sent to %s', 'latepoint' ), $contact_value ) . '</div>';
			$message     .= '<div class="latepoint-customer-otp-input-code-wrapper">';
				$message .= OsFormHelper::otp_code_field( 'otp[otp_code]' );
			$message     .= '</div>';
			$message     .= '<a tabindex="0" class="latepoint-btn latepoint-btn-block latepoint-btn-primary latepoint-verify-otp-button" data-route="' . OsRouterHelper::build_route_name( 'auth', 'verify_otp' ) . '"><span>' . __( 'Verify', 'latepoint' ) . '</span></a>';
			$message     .= '<div class="latepoint-customer-otp-sub-wrapper">';
				$message .= '<div class="latepoint-customer-otp-sub">' . sprintf( esc_html__( 'The code will expire in %s minutes', 'latepoint' ), OsOTPHelper::$otp_expires_in_minutes ) . '</div>';
				$message .= '<a tabindex="0" href="#" class="latepoint-customer-otp-resend" data-otp-resend-route="' . OsRouterHelper::build_route_name( 'auth', 'resend_otp' ) . '">' . esc_html__( 'Resend code', 'latepoint' ) . '</a>';
			$message     .= '</div>';
			$message     .= wp_nonce_field( 'otp_verify_otp_nonce', 'otp[verify_nonce]', true, false );
			$message     .= wp_nonce_field( 'otp_resend_otp_nonce', 'otp[resend_nonce]', true, false );
			$message     .= OsFormHelper::hidden_field( 'otp[contact_type]', $contact_type );
			$message     .= OsFormHelper::hidden_field( 'otp[contact_value]', $contact_value );
			$message     .= OsFormHelper::hidden_field( 'otp[delivery_method]', $delivery_method );
		$message         .= '</div>';
		return $message;
	}

	public static function is_customer_contact_verified( OsCustomerModel $customer, string $contact_value, string $contact_type ): bool {
		$verified_contact_values = json_decode( $customer->get_meta_by_key( 'verified_contact_values', '' ), true );
		if ( $verified_contact_values ) {
			return in_array( $contact_value, $verified_contact_values[ $contact_type ] );
		} else {
			return false;
		}
	}


	public static function add_verified_contact_for_customer_from_verification_token( OsCustomerModel $customer, string $verification_token ): void {
		$verification_info = OsOTPHelper::validate_verification_token( $verification_token );
		if ( $verification_info['valid'] && ! empty( $verification_info['data']['contact_value'] ) ) {
			self::add_verified_contact_for_customer( $customer, $verification_info['data']['contact_value'], $verification_info['data']['contact_type'] );
		}
	}

	public static function is_token_matching_to_contact_value( string $verification_token, string $contact_value ): bool {
		$verification_info = OsOTPHelper::validate_verification_token( $verification_token );
		if ( $verification_info['valid'] && ! empty( $verification_info['data']['contact_value'] ) && $verification_info['data']['contact_value'] == $contact_value ) {
			return true;
		}
		return false;
	}

	public static function add_verified_contact_for_customer( OsCustomerModel $customer, string $contact_value, string $contact_type ) {
		if ( ! $customer->is_new_record() && ! empty( $contact_value ) && in_array( $contact_type, self::valid_contact_types_for_customer() ) ) {
			if ( ! self::is_customer_contact_verified( $customer, $contact_value, $contact_type ) ) {
				$verified_contact_values                    = json_decode( $customer->get_meta_by_key( 'verified_contact_values', '' ), true );
				$verified_contact_values[ $contact_type ][] = $contact_value;
				$customer->save_meta_by_key( 'verified_contact_values', wp_json_encode( $verified_contact_values ) );
			}
		}
	}

	public static function verifyOTP( $otp_code, $contact_value, $contact_type = 'email', $delivery_method = 'email' ) {
		// Expire old OTPs first
		self::expireExpiredOTPs();

		$otp        = new OsOTPModel();
		$active_otp = $otp->where(
			[
				'contact_value'   => $contact_value,
				'contact_type'    => $contact_type,
				'delivery_method' => $delivery_method,
				'status'          => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_ACTIVE,
				'attempts <'      => self::$max_verification_attempts,
			]
		)->set_limit( 1 )->get_results_as_models();

		if ( empty( $active_otp ) ) {
			return new WP_Error( 'otp_generation_error', 'Invalid Code' );
		}

		if ( wp_check_password( $otp_code, $active_otp->otp_hash ) ) {
			// Mark this OTP as used
			$active_otp->update_attributes(
				[
					'status'  => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_USED,
					'used_at' => OsTimeHelper::now_datetime_in_format( LATEPOINT_DATETIME_DB_FORMAT ),
				]
			);

			// Cancel other active OTPs for this contact
			$other_otps = new OsOTPModel();
			$other_otps = $other_otps->where(
				[
					'contact_value' => $contact_value,
					'status'        => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_ACTIVE,
				]
			)->get_results_as_models();
			if ( $other_otps ) {
				foreach ( $other_otps as $otp ) {
					$otp->update_attributes( [ 'status' => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_CANCELLED ] );
				}
			}

			return [
				'status'        => LATEPOINT_STATUS_SUCCESS,
				'contact_value' => $contact_value,
			];
		}

		$active_otp->update_attributes( [ 'attempts' => $active_otp->attempts + 1 ] );

		return new WP_Error( 'otp_generation_error', 'Invalid Code' );
	}

	private static function sendOTP( string $otp_code, OsOTPModel $otp ): array {

		$result = [
			'status'             => LATEPOINT_STATUS_ERROR,
			'message'            => __( 'OTP was not sent.', 'latepoint' ),
			'to'                 => $otp->contact_value,
			'delivery_method'    => $otp->delivery_method,
			'contact_type'       => $otp->contact_type,
			'processed_datetime' => '',
			'extra_data'         => [
				'activity_data' => [],
			],
			'errors'             => [],
		];
		switch ( $otp->delivery_method ) {
			case 'email':
				$subject     = __( 'Your OTP Code', 'latepoint' );
				$content     = sprintf( esc_html__( 'Your OTP code is: %s', 'latepoint' ), $otp_code );
				$send_result = OsNotificationsHelper::send(
					$otp->delivery_method,
					[
						'to'      => $otp->contact_value,
						'subject' => $subject,
						'content' => $content,
					]
				);
				if ( $send_result['status'] == LATEPOINT_STATUS_SUCCESS ) {
					$result['processed_datetime'] = OsTimeHelper::now_datetime_in_db_format();
					$result['status']             = LATEPOINT_STATUS_SUCCESS;
				} else {
					$result['message'] = __( 'Failed to send email', 'latepoint' );
				}
				break;
			case 'sms':
				$subject     = __( 'Your OTP Code', 'latepoint' );
				$content     = sprintf( esc_html__( 'Your OTP code is: %s', 'latepoint' ), $otp_code );
				$send_result = OsNotificationsHelper::send(
					$otp->delivery_method,
					[
						'to'      => $otp->contact_value,
						'subject' => $subject,
						'content' => $content,
					]
				);
				if ( $send_result['status'] == LATEPOINT_STATUS_SUCCESS ) {
					$result['processed_datetime'] = OsTimeHelper::now_datetime_in_db_format();
					$result['status']             = LATEPOINT_STATUS_SUCCESS;
				} else {
					$result['message'] = __( 'Failed to send SMS', 'latepoint' );
				}
				break;
		}


		/**
		 * Result of sending an OTP code
		 *
		 * @since 5.2.0
		 * @hook latepoint_notifications_send_otp_code
		 *
		 * @param {array} $result The array of data describing the result of operation
		 * @param {string} $otp_code
		 * @param {OsOTPModel} $otp
		 *
		 * @returns {array} The filtered array of data describing the result of operation
		 */
		$result = apply_filters( 'latepoint_notifications_send_otp_code', $result, $otp_code, $otp );

		return $result;
	}

	public static function valid_contact_types_for_customer(): array {
		$contact_types = [ 'email', 'phone' ];
		/**
		 * List of valid contact types for customers
		 *
		 * @since 5.2.0
		 * @hook latepoint_valid_contact_types_for_customer
		 *
		 * @param {array} $contact_types The array of contact types
		 *
		 * @returns {array} The filtered array of contact types
		 */
		$result = apply_filters( 'latepoint_valid_contact_types_for_customer', $contact_types );

		return $result;
	}

	private static function cancelOldOTPs( $contact_value ) {
		$old_otps = new OsOTPModel();
		$old_otps = $old_otps->where(
			[
				'contact_value' => $contact_value,
				'status'        => 'active',
			]
		)->get_results_as_models();
		if ( $old_otps ) {
			foreach ( $old_otps as $otp ) {
				$otp->update_attributes( [ 'status' => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_CANCELLED ] );
			}
		}
	}

	private static function expireExpiredOTPs() {
		$otps         = new OsOTPModel();
		$expired_otps = $otps->where(
			[
				'status'       => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_ACTIVE,
				'expires_at <' => OsTimeHelper::now_datetime_utc_in_db_format(),
			]
		)->get_results_as_models();
		if ( $expired_otps ) {
			foreach ( $expired_otps as $otp ) {
				$otp->update_attributes( [ 'status' => LATEPOINT_CUSTOMER_OTP_CODE_STATUS_EXPIRED ] );
			}
		}
	}

	private static function isValidCombination( $contact_type, $delivery_method ) {
		$valid_combinations = [
			'email' => [ 'email' ],
			'phone' => [ 'sms', 'whatsapp' ],
		];
		/**
		 * Delivery methods for contact types
		 *
		 * @since 5.2.0
		 * @hook latepoint_otp_delivery_methods_for_contact_types
		 *
		 * @param {array} $methods available delivery methods
		 * @returns {array} The filtered array of available delivery methods
		 */
		$valid_combinations = apply_filters( 'latepoint_otp_delivery_methods_for_contact_types', $valid_combinations );

		return in_array( $delivery_method, $valid_combinations[ $contact_type ] ?? [] );
	}

	private static function checkRateLimit( $contact_value ): bool {

		$otps            = new OsOTPModel();
		$recent_attempts = $otps->where(
			[
				'contact_value' => $contact_value,
				'created_at >'  => OsTimeHelper::custom_datetime_utc_in_db_format( '-1 hour' ),
			]
		)->count();


		return $recent_attempts < self::$max_generation_attempts_per_hour;
	}


	// Cleanup old records
	public static function scheduledCleanup() {
		$otps = new OsOTPModel();
		$otps->delete_where(
			[
				'created_at <' => OsTimeHelper::custom_datetime_utc_in_db_format( '-30 days' ),
				'status'       => [
					LATEPOINT_CUSTOMER_OTP_CODE_STATUS_USED,
					LATEPOINT_CUSTOMER_OTP_CODE_STATUS_EXPIRED,
					LATEPOINT_CUSTOMER_OTP_CODE_STATUS_CANCELLED,
				],
			]
		);
	}

	public static function is_otp_enabled_for_contact_type( string $contact_type, string $delivery_method ): bool {
		$is_enabled = false;
		if ( $contact_type == 'email' && $delivery_method == 'email' ) {
			$is_enabled = true;
		}

		/**
		 * Determines if OTP is enabled for a selected contact type and delivery method
		 *
		 * @since 5.2.0
		 * @hook latepoint_is_otp_enabled_for_contact_type
		 *
		 * @param {bool} $is_enabled if otp delivery is enabled for a supplied contact and delivery method
		 * @param {string} $contact_type a contact type for OTP
		 * @param {string} $delivery_method a delivery method for OTP
		 *
		 * @returns {bool} Filtered value of whether OTP is enabled for this delivery method
		 */
		return apply_filters( 'latepoint_is_otp_enabled_for_contact_type', $is_enabled, $contact_type, $delivery_method );
	}
}
