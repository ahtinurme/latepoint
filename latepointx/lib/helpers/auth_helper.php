<?php

class OsAuthHelper {

	public static ?\LatePoint\Misc\User $current_user = null;
	public static $logged_in_customer_id              = false;

	private static int $max_login_attempts_per_contact = 20;
	private static int $max_login_attempts_per_ip      = 20;
	private static int $login_lockout_minutes          = 5;

	public static function reset_current_user() {
		self::$current_user = null;
	}

	public static function set_current_user() {
		if ( \OsWpUserHelper::is_user_logged_in() ) {
			// if wp user is logged in - load from it
			self::$current_user = \LatePoint\Misc\User::load_from_wp_user( \OsWpUserHelper::get_current_user() );
		} else {
			self::$current_user = new \LatePoint\Misc\User();
		}
		$customer = self::get_logged_in_customer();
		if ( $customer ) {
			self::$current_user->customer = $customer;
		}
	}

	public static function get_current_user(): \LatePoint\Misc\User {
		if ( ! self::$current_user ) {
			self::set_current_user();
		}

		return self::$current_user;
	}

	public static function get_highest_current_user_id() {
		$user_id = false;
		switch ( self::get_highest_current_user_type() ) {
			case LATEPOINT_USER_TYPE_ADMIN:
			case LATEPOINT_USER_TYPE_CUSTOM:
				$user_id = self::get_logged_in_wp_user_id();
				break;
			case LATEPOINT_USER_TYPE_AGENT:
				$user_id = self::get_logged_in_agent_id();
				break;
			case LATEPOINT_USER_TYPE_CUSTOMER:
				$user_id = self::get_logged_in_customer_id();
				break;
		}

		return $user_id;
	}

	public static function get_admin_or_agent_avatar_url() {
		$avatar_url = LATEPOINT_DEFAULT_AVATAR_URL;
		if ( self::is_agent_logged_in() ) {
			$agent      = self::get_logged_in_agent();
			$avatar_url = $agent->get_avatar_url();
		} elseif ( self::get_logged_in_wp_user_id() ) {
			$wp_user    = self::get_logged_in_wp_user();
			$avatar_url = get_avatar_url( $wp_user->user_email );
		}

		return $avatar_url;
	}

	public static function get_highest_current_user_type() {
		// check if WP admin is logged in
		$user_type = false;
		if ( self::get_current_user()->backend_user_type ) {
			// backend user, admin, agent or custom role
			return self::get_current_user()->backend_user_type;
		} elseif ( self::get_current_user()->customer ) {
			// customer
			return LATEPOINT_USER_TYPE_CUSTOMER;
		}

		return $user_type;
	}


	public static function login_wp_user( $user ) {
		clean_user_cache( $user->ID );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );
		update_user_caches( $user );
	}

	public static function login_customer( $contact_value, $password, $contact_type = 'email' ) {
		if ( empty( $contact_value ) || empty( $password ) || ! in_array( $contact_type, self::get_enabled_contact_types_for_customer_auth() ) ) {
			return false;
		}
		$available_contact_types = OsAuthHelper::get_available_contact_types_for_customer_auth();
		if ( ! isset( $available_contact_types[ $contact_type ] ) ) {
			return false;
		}

		if ( ! self::check_login_rate_limit( $contact_value, $contact_type ) ) {
			return false;
		}

		if ( self::can_wp_users_login_as_customers() ) {
			// if WP users enabled - customers can only login using existing password of the wordpress account
			$wp_user_id = false;
			if ( $contact_type == 'email' ) {
				$email      = sanitize_email( $contact_value );
				$wp_user_id = email_exists( $email );
			} elseif ( $contact_type == 'phone' ) {
				$phone = OsUtilHelper::sanitize_phone_number( $contact_value );
				// find latepoint customer by phone because wp users don't have a phone field
				$customer = new OsCustomerModel();
				$customer = $customer->where( array( 'phone' => $phone ) )->set_limit( 1 )->get_results_as_models();
				if ( $customer ) {
					$email = $customer->email;
					if ( $customer->wordpress_user_id ) {
						$wp_user_id = $customer->wordpress_user_id;
					} else {
						$wp_user_id = email_exists( $customer->email );
					}
				}
			}
			if ( $wp_user_id && ! empty( $email ) && ! empty( $password ) ) {
				$wp_user = wp_signon(
					[
						'user_login'    => $email,
						'user_password' => $password,
					]
				);
				if ( ! is_wp_error( $wp_user ) ) {
					// successfully logged into wp user
					// check if latepoint customer exists in db for this wp user
					wp_set_current_user( $wp_user->ID );
					$customer = OsCustomerHelper::get_customer_for_wp_user( $wp_user );
					if ( $customer->id ) {
						self::clear_login_rate_limits( $contact_value, $contact_type );
						return $customer;
					} else {
						OsDebugHelper::log( 'Can not login because can not create LatePoint Customer from WP User', 'customer_login_error', $customer->get_error_messages() );

						return false;
					}
				} else {
					self::record_failed_login_attempt( $contact_value, $contact_type );
					return false;
				}
			} else {
				return false;
			}
		} else {
			$customer = new OsCustomerModel();
			if ( $contact_type == 'email' ) {
				$email    = sanitize_email( $contact_value );
				$customer = $customer->where( array( 'email' => $email ) )->set_limit( 1 )->get_results_as_models();
			} elseif ( $contact_type == 'phone' ) {
				$phone    = OsUtilHelper::sanitize_phone_number( $contact_value );
				$customer = $customer->where( array( 'phone' => $phone ) )->set_limit( 1 )->get_results_as_models();
			}
			if ( $customer && OsAuthHelper::verify_password( $password, $customer->password ) ) {
				OsAuthHelper::authorize_customer( $customer->id );
				self::clear_login_rate_limits( $contact_value, $contact_type );

				return $customer;
			} else {
				self::record_failed_login_attempt( $contact_value, $contact_type );
				return false;
			}
		}
	}

	private static function check_login_rate_limit( string $contact_value, string $contact_type ): bool {
		// Per-contact check
		$contact_key = self::get_login_rate_limit_key( 'contact', $contact_value . '::' . $contact_type );
		$attempts    = absint( get_transient( $contact_key ) );
		if ( $attempts >= self::$max_login_attempts_per_contact ) {
			OsDebugHelper::log(
				'Too many login attempts for contact',
				'login_rate_limit',
				[
					'contact' => $contact_value,
					'type'    => $contact_type,
				]
			);
			return false;
		}

		// Per-IP check
		$client_ip = OsUtilHelper::get_user_ip();
		if ( ! empty( $client_ip ) && $client_ip !== 'n/a' ) {
			$ip_key      = self::get_login_rate_limit_key( 'ip', $client_ip );
			$ip_attempts = absint( get_transient( $ip_key ) );
			if ( $ip_attempts >= self::$max_login_attempts_per_ip ) {
				OsDebugHelper::log( 'Too many login attempts from IP', 'login_rate_limit_ip', [ 'ip' => $client_ip ] );
				return false;
			}
		}

		return true;
	}

	private static function record_failed_login_attempt( string $contact_value, string $contact_type ): void {
		$lockout_duration = self::$login_lockout_minutes * MINUTE_IN_SECONDS;
		$client_ip        = OsUtilHelper::get_user_ip();

		$contact_key = self::get_login_rate_limit_key( 'contact', $contact_value . '::' . $contact_type );
		$attempts    = absint( get_transient( $contact_key ) );
		set_transient( $contact_key, $attempts + 1, $lockout_duration );

		if ( ! empty( $client_ip ) && $client_ip !== 'n/a' ) {
			$ip_key      = self::get_login_rate_limit_key( 'ip', $client_ip );
			$ip_attempts = absint( get_transient( $ip_key ) );
			set_transient( $ip_key, $ip_attempts + 1, $lockout_duration );
		}
	}

	private static function clear_login_rate_limits( string $contact_value, string $contact_type ): void {
		delete_transient( self::get_login_rate_limit_key( 'contact', $contact_value . '::' . $contact_type ) );

		$client_ip = OsUtilHelper::get_user_ip();
		if ( ! empty( $client_ip ) && $client_ip !== 'n/a' ) {
			delete_transient( self::get_login_rate_limit_key( 'ip', $client_ip ) );
		}
	}

	private static function get_login_rate_limit_key( string $type, string $identifier ): string {
		return 'latepoint_login_' . $type . '_' . substr( wp_hash( $identifier ), 0, 20 );
	}

	public static function can_wp_users_login_as_customers(): bool {
		return OsSettingsHelper::is_on( 'wp_users_as_customers', false );
	}


	// CUSTOMERS
	// ---------------

	public static function logout_customer() {
		if ( self::can_wp_users_login_as_customers() ) {
			wp_logout();
		} else {
			OsSessionsHelper::destroy_customer_session_cookie();
		}
	}

	public static function authorize_customer( $customer_id ): bool {
		$customer = new OsCustomerModel();
		$customer = $customer->where( [ 'id' => $customer_id ] )->set_limit( 1 )->get_results_as_models();
		if ( empty( $customer ) ) {
			OsDebugHelper::log( 'Tried to authorize customer with invalid ID', 'customer_authorization', [ 'customer_id' => $customer_id ] );

			return false;
		}

		if ( self::can_wp_users_login_as_customers() ) {

			if ( $customer->wordpress_user_id ) {
				$wp_user = get_user_by( 'id', $customer->wordpress_user_id );
				// check if WP User exists, if not - create new one and get ID, otherwise get ID from customer record, since its valid
				$wordpress_user_id = ( $wp_user ) ? $customer->wordpress_user_id : OsCustomerHelper::create_wp_user_for_customer( $customer );
			} else {
				$wordpress_user_id = OsCustomerHelper::create_wp_user_for_customer( $customer );
			}

			if ( $wordpress_user_id ) {
				$wp_user = get_user_by( 'id', $wordpress_user_id );
				if ( $wp_user ) {
					self::login_wp_user( $wp_user );
				} else {
					OsDebugHelper::log(
						'WordPress user ID for customer is not found or can not be created.',
						'customer_create_error',
						[
							'customer_id'       => $customer_id,
							'wordpress_user_id' => $wordpress_user_id,
						]
					);

					return false;
				}
			} else {
				OsDebugHelper::log( 'WordPress user ID for customer is not found or can not be created.', 'customer_create_error', [ 'customer_id' => $customer_id ] );

				return false;
			}
		}
		OsSessionsHelper::start_or_use_session_for_customer( $customer_id );
		OsStepsHelper::$customer_object = $customer;

		return true;
	}

	public static function get_logged_in_customer_uuid() {
		$customer = self::get_logged_in_customer();
		if ( $customer ) {
			return $customer->get_uuid();
		} else {
			return false;
		}
	}

	public static function get_logged_in_customer_id() {
		if ( OsAuthHelper::is_customer_auth_disabled() ) {
			return false;
		}
		if ( self::can_wp_users_login_as_customers() ) {
			// using wp users as customers
			if ( OsWpUserHelper::is_user_logged_in() ) {
				$wp_user = wp_get_current_user();
				// search connected latepoint customer
				$customer = OsCustomerHelper::get_customer_for_wp_user( $wp_user );
				if ( $customer->id ) {
					return $customer->id;
				} else {
					OsDebugHelper::log( 'Can not create LatePoint Customer from WP User', 'customer_create_error', $customer->get_error_messages() );

					return false;
				}
			} else {
				return false;
			}
		} else {
			if ( self::$logged_in_customer_id ) {
				return self::$logged_in_customer_id;
			}
			$customer_id = OsSessionsHelper::get_customer_id_from_session();
			// make sure customer with this ID exists in database
			$customer = new OsCustomerModel( $customer_id );
			if ( ! $customer->is_new_record() ) {
				self::$logged_in_customer_id = $customer_id;

				return self::$logged_in_customer_id;
			} else {
				// customer not found, destroy this invalid customer ID in session cookie
				OsSessionsHelper::destroy_customer_session_cookie();

				return false;
			}
		}
	}

	public static function is_customer_logged_in() {
		return self::get_logged_in_customer_id();
	}

	public static function get_logged_in_customer() {
		$customer = false;
		if ( self::is_customer_logged_in() ) {
			$customer = new OsCustomerModel( self::get_logged_in_customer_id() );
			if ( $customer->is_new_record() ) {
				$customer = false;
			}
		}

		return $customer;
	}


	// AGENTS
	// -------------

	public static function get_logged_in_agent_id() {
		$agent_id = false;
		if ( self::is_agent_logged_in() ) {
			if ( self::get_current_user()->agent && self::get_current_user()->agent->id ) {
				$agent_id = self::get_current_user()->agent->id;
			}
		}

		return $agent_id;
	}

	public static function is_agent_logged_in() {
		return ( self::get_current_user()->backend_user_type == LATEPOINT_USER_TYPE_AGENT );
	}

	public static function get_logged_in_agent() {
		$agent = false;
		if ( self::is_agent_logged_in() ) {
			$agent = new OsAgentModel();
			$agent = $agent->where( [ 'wp_user_id' => self::get_logged_in_wp_user_id() ] )->set_limit( 1 )->get_results_as_models();
		}

		return $agent;
	}


	public static function is_custom_backend_user_logged_in() {
		return ( self::get_current_user()->backend_user_type == LATEPOINT_USER_TYPE_CUSTOM );
	}

	public static function is_admin_logged_in() {
		return ( self::get_current_user()->backend_user_type == LATEPOINT_USER_TYPE_ADMIN );
	}

	public static function get_logged_in_admin_user() {
		$admin_user = false;
		if ( self::is_admin_logged_in() ) {
			$admin_user = self::get_logged_in_wp_user();
		}

		return $admin_user;
	}

	public static function get_logged_in_admin_user_id() {
		$admin_id = false;
		if ( self::is_admin_logged_in() ) {
			$admin_id = self::get_logged_in_wp_user_id();
		}

		return $admin_id;
	}

	public static function get_logged_in_custom_user_id() {
		$admin_id = false;
		if ( self::is_custom_backend_user_logged_in() ) {
			$admin_id = self::get_logged_in_wp_user_id();
		}

		return $admin_id;
	}


	public static function get_default_customer_authentication_method(): string {
		$method = OsSettingsHelper::get_settings_value( 'default_customer_authentication_method', 'password' );

		$enabled_auth_methods = self::get_enabled_customer_authentication_methods();
		if ( empty( $enabled_auth_methods ) ) {
			$method = '';
		} elseif ( count( $enabled_auth_methods ) == 1 ) {
			$method = $enabled_auth_methods[0];
		} else {
			$method = in_array( $method, $enabled_auth_methods ) ? $method : reset( $enabled_auth_methods );
		}

		/**
		 * Default auth method
		 *
		 * @param {string} $method default auth method value
		 * @returns {string} The filtered auth method value
		 *
		 * @since 5.2.0
		 * @hook latepoint_default_contact_type_for_customer_auth
		 *
		 */
		return apply_filters( 'latepoint_default_contact_type_for_customer_auth', $method );
	}

	public static function get_available_customer_authentication_methods(): array {
		$methods = [
			'password' => __( 'Password', 'latepoint' ),
			'otp'      => __( 'One-time code', 'latepoint' ),
		];

		/**
		 * Enabled auth method
		 *
		 * @param {array} $methods available auth methods
		 * @returns {array} The filtered array of available auth methods
		 *
		 * @since 5.2.0
		 * @hook latepoint_get_available_customer_authentication_methods
		 *
		 */
		return apply_filters( 'latepoint_get_available_customer_authentication_methods', $methods );
	}


	public static function get_enabled_customer_authentication_methods(): array {
		$selected_method = OsSettingsHelper::get_settings_value( 'selected_customer_authentication_method', 'password' );

		switch ( $selected_method ) {
			case 'password':
				$auth_methods = [ 'password' ];
				break;
			case 'otp':
				$auth_methods = [ 'otp' ];
				break;
			case 'password_or_otp':
				$auth_methods = [ 'password', 'otp' ];
				break;
			default:
				$auth_methods = [ 'password' ];
		}

		/**
		 * Enabled auth method
		 *
		 * @param {array} $auth_methods enabled auth methods
		 * @returns {array} The filtered array of enabled auth methods
		 *
		 * @since 5.2.0
		 * @hook latepoint_get_enabled_customer_authentication_methods
		 *
		 */
		return apply_filters( 'latepoint_get_enabled_customer_authentication_methods', $auth_methods );
	}


	public static function get_selected_customer_authentication_method(): string {
		$via = OsSettingsHelper::get_settings_value( 'selected_customer_authentication_method', 'password' );
		if ( in_array( $via, self::get_enabled_customer_authentication_methods( true ) ) ) {
			return $via;
		} else {
			OsSettingsHelper::save_setting_by_name( 'selected_customer_authentication_method', 'password' );

			return 'password';
		}
	}

	public static function get_customer_authentication_method_options( $keys_only = false ): array {
		$options = [
			'password' => __( 'Password', 'latepoint' ),
			'otp'      => __( 'One-time code', 'latepoint' ),
		];

		return $keys_only ? array_keys( $options ) : $options;
	}


	public static function get_default_contact_type_for_customer_auth(): string {
		$method = OsSettingsHelper::get_settings_value( 'default_contact_type_for_customer_auth', 'email' );

		$enabled_contact_types = self::get_enabled_contact_types_for_customer_auth();
		if ( empty( $enabled_contact_types ) ) {
			$method = '';
		} elseif ( count( $enabled_contact_types ) == 1 ) {
			$method = $enabled_contact_types[0];
		} else {
			$method = in_array( $method, $enabled_contact_types ) ? $method : reset( $enabled_contact_types );
		}

		/**
		 * Default auth method
		 *
		 * @param {string} $method default auth method value
		 * @returns {string} The filtered auth method value
		 *
		 * @since 5.2.0
		 * @hook latepoint_default_contact_type_for_customer_auth
		 *
		 */
		return apply_filters( 'latepoint_default_contact_type_for_customer_auth', $method );
	}

	public static function get_available_contact_types_for_customer_auth(): array {
		$contact_types = [
			'email' => __( 'Email Address', 'latepoint' ),
			'phone' => __( 'Phone Number', 'latepoint' ),
		];

		/**
		 * Enabled auth method
		 *
		 * @param {array} $contact_types available contact types
		 * @returns {array} The filtered array of available contact types
		 *
		 * @since 5.2.0
		 * @hook latepoint_get_available_contact_types_for_customer_auth
		 *
		 */
		return apply_filters( 'latepoint_get_available_contact_types_for_customer_auth', $contact_types );
	}

	public static function get_enabled_contact_types_for_customer_auth(): array {
		$customer_authentication_field_type = OsSettingsHelper::get_settings_value( 'selected_customer_authentication_field_type', 'email' );

		switch ( $customer_authentication_field_type ) {
			case 'email':
				$contact_types = [ 'email' ];
				break;
			case 'phone':
				$contact_types = [ 'phone' ];
				break;
			case 'email_or_phone':
				$contact_types = [ 'email', 'phone' ];
				break;
			case 'disabled':
				$contact_types = [];
				break;
			default:
				$contact_types = [ 'email' ];
		}

		/**
		 * Enabled auth method
		 *
		 * @param {array} $contact_types enabled contact types
		 * @returns {array} The filtered array of enabled contact types
		 *
		 * @since 5.2.0
		 * @hook latepoint_get_enabled_contact_types_for_customer_auth
		 *
		 */
		return apply_filters( 'latepoint_get_enabled_contact_types_for_customer_auth', $contact_types );
	}

	public static function is_customer_auth_enabled(): bool {
		return ( self::get_selected_customer_authentication_field_type() != 'disabled' );
	}

	public static function is_customer_auth_disabled(): bool {
		return ! self::is_customer_auth_enabled();
	}

	public static function count_total_customers_violating_auth_rules(): array {

		$violators = [
			'field'  => '',
			'values' => [],
		];
		$field     = '';

		if ( OsAuthHelper::is_customer_auth_enabled() ) {
			// auth enabled
			$auth_field = OsAuthHelper::get_selected_customer_authentication_field_type();
			if ( $auth_field == 'email' ) {
				$field = 'email';
			}
			if ( $auth_field == 'phone' ) {
				$field = 'phone';
			}
		} else {
			// auth disabled
			$merge_data = OsSettingsHelper::get_settings_value( 'default_contact_merge_behavior', 'email' );
			if ( $merge_data == 'email' ) {
				$field = 'email';
			}
			if ( $merge_data == 'phone' ) {
				$field = 'phone';
			}
		}

		if ( $field ) {
			$customers_model     = new OsCustomerModel();
			$result              = $customers_model->select( $field )
									 ->where( [ $field . ' !=' => '' ] )
									 ->group_by( $field )
									 ->having( 'COUNT(*) > 1' )
									 ->get_results( ARRAY_A );
			$violators['values'] = ( array_column( $result, $field ) );
			$violators['field']  = $field;
		}

		return $violators;
	}

	public static function get_selected_customer_authentication_field_type(): string {
		$via = OsSettingsHelper::get_settings_value( 'selected_customer_authentication_field_type' );
		if ( empty( $via ) ) {
			// load from legacy setting
			if ( OsSettingsHelper::is_on( 'steps_hide_login_register_tabs' ) ) {
				OsSettingsHelper::save_setting_by_name( 'selected_customer_authentication_field_type', 'disabled' );

				return 'disabled';
			} else {
				OsSettingsHelper::save_setting_by_name( 'selected_customer_authentication_field_type', 'email' );

				return 'email';
			}
		}
		if ( in_array( $via, self::get_customer_authentication_field_type_options( true ) ) ) {
			return $via;
		} else {
			OsSettingsHelper::save_setting_by_name( 'selected_customer_authentication_field_type', 'email' );

			return 'email';
		}
	}

	public static function get_customer_authentication_field_type_options( $keys_only = false ): array {
		$options = [
			'email'    => __( 'Email Address', 'latepoint' ),
			'phone'    => __( 'Phone Number', 'latepoint' ),
			'disabled' => __( 'Disable ability to login', 'latepoint' ),
		];

		return $keys_only ? array_keys( $options ) : $options;
	}


	public static function is_classic_auth_flow(): bool {
		return ( OsSettingsHelper::get_settings_value( 'modern_auth_flow_for_customers', LATEPOINT_VALUE_OFF ) == LATEPOINT_VALUE_OFF );
	}

	public static function is_otp_auth_enabled(): bool {
		return in_array( 'otp', self::get_enabled_customer_authentication_methods() );
	}


	public static function get_default_delivery_method_for_customer_auth_contact_type( string $contact_type ) {
		$delivery_method = 'email';
		switch ( $contact_type ) {
			case 'email':
				$delivery_method = 'email';
				break;
			case 'phone':
				$delivery_method = 'sms';
				break;
		}

		/**
		 * Get default delivery method for auth contact type
		 *
		 * @param {string} $delivery_method delivery method (email, sms, whatsapp)
		 * @param {string} $contact_type contact type selected for auth (email, phone)
		 *
		 * @returns {string} Filtered delivery method
		 * @since 5.2.0
		 * @hook latepoint_get_default_delivery_method_for_customer_auth
		 *
		 */
		return apply_filters( 'latepoint_get_default_delivery_method_for_customer_auth', $delivery_method, $contact_type );
	}

	public static function auth_form_html( $is_classic, OsCustomerModel $customer, string $selected_contact_type_for_auth = '', $selected_delivery_method = '' ): string {
		if ( empty( $selected_contact_type_for_auth ) ) {
			$selected_contact_type_for_auth = OsAuthHelper::get_default_contact_type_for_customer_auth();
		}
		if ( empty( $selected_delivery_method ) ) {
			$selected_delivery_method = self::get_default_delivery_method_for_customer_auth_contact_type( $selected_contact_type_for_auth );
		}
		$enabled_contact_types_for_customer_auth = OsAuthHelper::get_enabled_contact_types_for_customer_auth();
		$otp_allowed                             = self::is_otp_auth_enabled();
		$multiple_auth_methods_enabled           = ( count( self::get_enabled_customer_authentication_methods() ) > 1 );
		$html                                    = '';
		if ( ! $is_classic ) {
			$html .= '<div class="latepoint-customer-auth-wrapper">';
			$html .= '<div class="latepoint-customer-otp-request-wrapper hide-when-entering-otp">';
			// NEW STYLE SHOWING JUST THE AUTH OPTIONS FIRST
			if ( in_array( 'email', $enabled_contact_types_for_customer_auth ) ) {
				$html .= '<div data-login-method="email" class="customer-login-method-wrapper ' . ( $selected_contact_type_for_auth == 'email' ? '' : 'os-hidden' ) . '">';
				$html .= OsFormHelper::text_field(
					'auth[email]',
					__( 'Your Email Address', 'latepoint' ),
					$customer->email,
					array(
						'validate'     => $customer->get_validations_for_property( 'email' ),
						'class'        => 'required',
						'autocomplete' => 'email',
					)
				);
				$html .= '</div>';
			}
			if ( in_array( 'phone', $enabled_contact_types_for_customer_auth ) ) {
				$html .= '<div data-login-method="phone" class="customer-login-method-wrapper ' . ( $selected_contact_type_for_auth == 'phone' ? '' : 'os-hidden' ) . '">';
				$html .= OsFormHelper::phone_number_field(
					'auth[phone]',
					__( 'Your Phone Number', 'latepoint' ),
					$customer->phone,
					array(
						'validate'     => $customer->get_validations_for_property( 'phone' ),
						'class'        => 'required',
						'theme'        => 'simple',
						'autocomplete' => 'tel',
					)
				);
				$html .= '</div>';
			}
			$html .= '<a tabindex="0" class="latepoint-btn latepoint-btn-block latepoint-btn-primary latepoint-request-otp-button" data-otp-request-route="' . OsRouterHelper::build_route_name( 'auth', 'request_otp' ) . '"><span>' . __( 'Continue', 'latepoint' ) . '</span></a>';
			$html .= '</div>';
			$html .= '</div>';
		} else {
			ob_start();
			?>
			<div class="os-customer-login-w os-customer-wrapped-box os-unwrapped">
				<?php if ( count( $enabled_contact_types_for_customer_auth ) > 1 ) { ?>
					<div class="login-options-wrapper">
						<div class="latepoint-customer-box-title"><?php _e( 'Sign in', 'latepoint' ); ?></div>
						<?php if ( in_array( 'email', $enabled_contact_types_for_customer_auth ) && in_array( 'phone', $enabled_contact_types_for_customer_auth ) ) { ?>
							<div class="login-options-col login-options-via">
								<div class="login-options-via-wrapper">
									<div data-login-method="email" data-is-otp-enabled="<?php echo OsOTPHelper::is_otp_enabled_for_contact_type( 'email', 'email' ) ? 'yes' : 'no'; ?>"
										 data-otp-delivery-method="email"
										 class="login-option <?php echo $selected_contact_type_for_auth == 'email' ? 'os-selected os-default' : ''; ?>"><?php _e( 'Email', 'latepoint' ); ?></div>
									<div data-login-method="phone" data-is-otp-enabled="<?php echo OsOTPHelper::is_otp_enabled_for_contact_type( 'phone', 'sms' ) ? 'yes' : 'no'; ?>"
										 data-otp-delivery-method="sms"
										 class="login-option <?php echo $selected_contact_type_for_auth == 'phone' ? 'os-selected os-default' : ''; ?>"><?php _e( 'Phone', 'latepoint' ); ?></div>
								</div>
							</div>
						<?php } ?>
					</div>
				<?php } ?>
				<?php
				if ( in_array( 'email', $enabled_contact_types_for_customer_auth ) ) {
					echo '<div data-login-method="email" class="customer-login-method-wrapper ' . ( $selected_contact_type_for_auth == 'email' ? '' : 'os-hidden' ) . '">';
					echo OsFormHelper::text_field( 'auth[email]', __( 'Your Email Address', 'latepoint' ), '', [ 'autocomplete' => 'email' ] );
					echo '</div>';
				}
				?>
				<?php
				if ( in_array( 'phone', $enabled_contact_types_for_customer_auth ) ) {
					echo '<div data-login-method="phone" class="customer-login-method-wrapper ' . ( $selected_contact_type_for_auth == 'phone' ? '' : 'os-hidden' ) . '">';
					echo OsFormHelper::phone_number_field( 'auth[phone]', __( 'Your Phone Number', 'latepoint' ), '', [ 'autocomplete' => 'tel' ] );
					echo '</div>';
				}
				?>
				<div class="os-customer-login-password-fields-w" <?php echo ( OsAuthHelper::get_default_customer_authentication_method() == 'otp' ) ? 'style="display:none;"' : ''; ?>>
					<?php echo OsFormHelper::password_field(
						'auth[password]',
						__( 'Your Password', 'latepoint' ),
						'',
						array(
							'class'        => 'required',
							'autocomplete' => 'current-password',
						) 
					); ?>
					<a href="#" class="latepoint-btn latepoint-btn-primary latepoint-btn-link step-forgot-password-btn"
					   data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'customer_cabinet', 'request_password_reset_token' ) ); ?>"
					   data-os-output-target=".os-password-reset-form-holder"
					   data-os-after-call="latepoint_reset_password_from_booking_init"
					   data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( [ 'from_booking' => true ] ) ); ?>"><span><?php esc_html_e( 'Forgot?', 'latepoint' ); ?></span></a>
				</div>

				<?php if ( $otp_allowed ) { ?>
					<div class="os-customer-otp-notice" <?php echo ( OsAuthHelper::get_default_customer_authentication_method() == 'otp' ) ? '' : 'style="display:none;"'; ?>>
						<?php _e( 'You will receive a 6-digit code to log in.', 'latepoint' ); ?>
					</div>
				<?php } ?>
				<div class="os-customer-login-buttons">
					<?php if ( $multiple_auth_methods_enabled ) { ?>
						<div class="login-options-col">
							<div class="latepoint-customer-otp-option">
								<?php if ( OsAuthHelper::get_default_customer_authentication_method() == 'otp' ) { ?>
									<label><input type="checkbox" name="auth[via]" value="password"
												  class="login-with-password-toggle os-opposite"><span><?php _e( 'Use password instead', 'latepoint' ); ?></span></label>
								<?php } else { ?>
									<label><input type="checkbox" name="auth[via]" value="otp"
												  class="login-with-password-toggle"><span><?php _e( 'Send me a sign-in code', 'latepoint' ); ?></span></label>
								<?php } ?>
							</div>
						</div>
					<?php } else {
						echo OsFormHelper::hidden_field( 'auth[via]', OsAuthHelper::get_default_customer_authentication_method() );
					} ?>
					<a data-otp-request-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'auth', 'request_otp' ) ); ?>"
					   data-password-login-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'auth', 'login_customer' ) ); ?>" href="#"
					   class="latepoint-btn latepoint-btn-primary step-login-existing-customer-btn <?php echo $multiple_auth_methods_enabled ? '' : 'latepoint-btn-block'; ?>"><span><?php esc_html_e( 'Continue', 'latepoint' ); ?></span></a>
				</div>
			</div>
			<?php
			$html = ob_get_clean();
		}
		$html .= OsFormHelper::hidden_field( 'auth[contact_type]', $selected_contact_type_for_auth );
		$html .= OsFormHelper::hidden_field( 'auth[delivery_method]', $selected_delivery_method );
		$html .= OsFormHelper::hidden_field( 'auth[action]', 'register' );
		$html .= wp_nonce_field( 'auth_nonce', 'auth[nonce]', true, false );


		return $html;
	}


	// WP USER
	public static function get_logged_in_wp_user_id() {
		return OsWpUserHelper::get_current_user_id();
	}

	public static function get_logged_in_wp_user() {
		return OsWpUserHelper::get_current_user();
	}


	// UTILS

	public static function hash_password( $password ) {
		return wp_hash_password( $password, PASSWORD_DEFAULT );
	}

	public static function verify_password( $password, $hash ) {
		return wp_check_password( $password, $hash );
	}
}
