<?php

class OsSettingsHelper {

	public static $loaded_values;


	private static $encrypted_settings = [
		'license',
		'google_calendar_client_secret',
		'facebook_app_secret',
		'google_client_secret',
		'braintree_secret_key',
		'braintree_merchant_id',
		'paypal_client_secret',
	];

	private static $settings_to_autoload = [
		'enable_google_login',
		'time_system',
		'date_format',
		'currency_symbol_before',
		'currency_symbol_after',
		'phone_format',
		'steps_show_timezone_selector',
		'show_booking_end_time',
		'stripe_publishable_key',
		'enable_facebook_login',
		'earliest_possible_booking',
		'enable_payments_local',
		'color_scheme_for_booking_form',
		'latest_possible_booking',
		'facebook_app_id',
		'google_client_id',
		'paypal_client_id',
		'paypal_currency_iso_code',
		'paypal_use_braintree_api',
		'stripe_secret_key',
		'steps_hide_agent_info',
		'booking_hash',
		'payments_environment',
		'list_of_phone_countries',
		'included_phone_countries',
		'wp_users_as_customers',
		'thousand_separator',
		'decimal_separator',
		'number_of_decimals',
		'default_phone_country',
	];

	private static $defaults = [
		'date_format'            => LATEPOINT_DEFAULT_DATE_FORMAT,
		'time_system'            => LATEPOINT_DEFAULT_TIME_SYSTEM,
		'currency_symbol_before' => '$',
	];

	public static function get_business_logo_url() {
		$default_logo_url = LATEPOINT_IMAGES_URL . 'logo.png';

		return OsImageHelper::get_image_url_by_id( OsSettingsHelper::get_settings_value( 'business_logo' ), 'thumbnail', $default_logo_url );
	}

	public static function get_business_logo_image( $height = '50px' ) {
		$url = self::get_business_logo_url();

		return '<img src="' . $url . '" style="height: ' . $height . '; width: auto"/>';
	}

	public static function get_encrypted_settings() {
		$encrypted_settings = apply_filters( 'latepoint_encrypted_settings', self::$encrypted_settings );

		return $encrypted_settings;
	}


	public static function get_active_addons(): array {
		return json_decode( OsSettingsHelper::get_settings_value( 'active_addons', '' ) ) ?? [];
	}

	public static function get_earliest_possible_booking_restriction( $service_id = 0 ) {
		if ( ! empty( $service_id ) ) {
			$service = new OsServiceModel( $service_id );
			if ( ! $service->is_new_record() && ! empty( $service->earliest_possible_booking ) && OsTimeHelper::is_valid_date( $service->earliest_possible_booking ) ) {
				return $service->earliest_possible_booking;
			}
		}
		$restriction_from_general_settings = OsSettingsHelper::get_settings_value( 'earliest_possible_booking', '' );
		if ( OsTimeHelper::is_valid_date( $restriction_from_general_settings ) ) {
			return $restriction_from_general_settings;
		} else {
			return '';
		}
	}

	public static function get_latest_possible_booking_restriction( $service_id = 0 ) {
		if ( ! empty( $service_id ) ) {
			$service = new OsServiceModel( $service_id );
			if ( ! $service->is_new_record() && ! empty( $service->latest_possible_booking ) && OsTimeHelper::is_valid_date( $service->latest_possible_booking ) ) {
				return $service->latest_possible_booking;
			}
		}
		$restriction_from_general_settings = OsSettingsHelper::get_settings_value( 'latest_possible_booking', '' );
		if ( OsTimeHelper::is_valid_date( $restriction_from_general_settings ) ) {
			return $restriction_from_general_settings;
		} else {
			return '';
		}
	}

	/**
	 * @param array $tables
	 *
	 * @return string
	 */
	public static function export_data( array $tables = [] ): string {

		if ( empty( $tables ) ) {
			$tables = OsDatabaseHelper::get_all_latepoint_tables();
		}

		global $wpdb;

		$output = [];

		foreach ( $tables as $table ) {
			// Check if table exists
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

			if ( ! $table_exists ) {
				continue; // Skip this table if it doesn't exist
			}
			// Get all rows from the table
			$rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );

			if ( $rows ) {
				// Get table create statement
				$create_table = $wpdb->get_row( "SHOW CREATE TABLE {$table}", ARRAY_N );
				$var_table    = str_replace( $wpdb->prefix, '{{TABLE_PREFIX}}', $table );
				$create_table = str_replace( $table, $var_table, $create_table );

				$output[ $var_table ] = [
					'create' => $create_table[1],
					'prefix' => $wpdb->prefix,
					'data'   => $rows,
				];
			}
		}

		// Generate filename
		$filename = 'latepoint_backup_' . date( 'Y-m-d_H-i-s' ) . '.json';

		$json = wp_json_encode( $output, JSON_PRETTY_PRINT );

		// Send headers for file download
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json ) );
		header( 'Pragma: no-cache' );

		return $json;
	}

	/**
	 * @param string $data
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function import_data( string $data ): bool {
		global $wpdb;

		if ( empty( $data ) ) {
			throw new Exception( __( 'Error reading uploaded file', 'latepoint' ) );
		}

		// update table name from original to new prefix
		$data = str_replace( '{{TABLE_PREFIX}}', $wpdb->prefix, $data );

		$data = json_decode( $data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( __( 'Invalid JSON file format', 'latepoint' ) );
		}

		// Get whitelist of allowed LatePoint tables.
		$allowed_tables = OsDatabaseHelper::get_all_latepoint_tables();

		// Validate all tables before processing to ensure security.
		foreach ( $data as $table => $table_data ) {
			// Security check: Ensure table is in whitelist.
			if ( ! in_array( $table, $allowed_tables, true ) ) {
				throw new Exception( sprintf( __( 'Security: Table "%s" is not allowed for import', 'latepoint' ), esc_html( $table ) ) );
			}

			// Security check: Validate required fields exist.
			if ( ! isset( $table_data['create'] ) || ! isset( $table_data['data'] ) ) {
				throw new Exception( sprintf( __( 'Invalid data structure for table "%s"', 'latepoint' ), esc_html( $table ) ) );
			}

			// Security check: Validate CREATE statement only creates the expected table.
			$create_statement = $table_data['create'];
			if ( ! preg_match( '/^\s*CREATE\s+TABLE\s+/i', $create_statement ) ) {
				throw new Exception( sprintf( __( 'Security: Invalid CREATE statement for table "%s"', 'latepoint' ), esc_html( $table ) ) );
			}

			// Extract table name from CREATE statement and validate it matches.
			if ( ! preg_match( '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`\'"]?(' . preg_quote( $table, '/' ) . ')[`\'"]?\s+/i', $create_statement ) ) {
				throw new Exception( sprintf( __( 'Security: CREATE statement table name mismatch for "%s"', 'latepoint' ), esc_html( $table ) ) );
			}

			// Security check: Ensure no dangerous SQL keywords in CREATE statement (whole-word match to avoid false positives on column names like "selection_image_id").
			$dangerous_keywords = array( 'SELECT', 'UNION', 'EXEC', 'EXECUTE', 'CALL', 'LOAD_FILE', 'INTO\s+OUTFILE', 'INTO\s+DUMPFILE' );
			foreach ( $dangerous_keywords as $keyword ) {
				if ( preg_match( '/\b' . $keyword . '\b/i', $create_statement ) ) {
					$display_keyword = str_replace( '\s+', ' ', $keyword );
					throw new Exception( sprintf( __( 'Security: Dangerous SQL keyword "%s" detected in CREATE statement', 'latepoint' ), esc_html( $display_keyword ) ) );
				}
			}
		}

		// Process each table after validation.
		foreach ( $data as $table => $table_data ) {
			// Drop table if exists
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );

			// Create table
			$result = $wpdb->query( $table_data['create'] );
			if ( $result === false ) {
				throw new Exception( sprintf( __( 'Error creating table "%s": %s', 'latepoint' ), esc_html( $table ), $wpdb->last_error ) );
			}

			// Insert data
			if ( is_array( $table_data['data'] ) ) {
				foreach ( $table_data['data'] as $row ) {
					if ( is_array( $row ) ) {
						// Security fix: Prevent mass assignment of wordpress_user_id during import.
						if ( $table === $wpdb->prefix . 'latepoint_customers' ) {
							unset( $row['wordpress_user_id'] );
						}

						$insert_result = $wpdb->insert( $table, $row );
						if ( $insert_result === false ) {
							// Log error but continue with other rows.
							error_log( sprintf( 'LatePoint Import: Error inserting data into %s: %s', $table, $wpdb->last_error ) );
						}
					}
				}
			}

			// Find auto-increment columns and their max values
			$columns = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM %i WHERE Extra = 'auto_increment'", $table ) );
			if ( is_array( $columns ) ) {
				foreach ( $columns as $column ) {
					if ( isset( $column->Field ) ) {
						// Sanitize column name to prevent SQL injection.
						$column_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $column->Field );

						// Get the maximum value for this column.
						$max_id = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(%i) FROM %i', $column_name, $table ) );

						// Set the auto_increment value to max + 1.
						if ( $max_id ) {
							$next_id = intval( $max_id ) + 1;
							$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i AUTO_INCREMENT = %d', $table, $next_id ) );
						}
					}
				}
			}
		}

		return true;
	}

	public static function run_autoload() {
		/**
		 * Default settings to be used in SettingsHelper when no value exists in DB
		 *
		 * @param {array} $default_settings Array of key => value pairs of setting names and their default values
		 * @returns {array} The filtered array of default settings
		 *
		 * @since 4.7.2
		 * @hook latepoint_settings_defaults
		 *
		 */
		self::$defaults = apply_filters( 'latepoint_settings_defaults', self::$defaults );
		foreach ( self::$defaults as $name => $default ) {
			self::$loaded_values[ $name ] = $default;
		}

		$settings_model = new OsSettingsModel();

		/**
		 * Settings to autoload in SettingsHelper on every page load, this is done to reduce the number of queries to DB
		 *
		 * @param {array} $settings Array of setting names to autoload
		 * @returns {array} The filtered array of setting names
		 *
		 * @since 4.7.2
		 * @hook latepoint_settings_to_autoload
		 *
		 */
		self::$settings_to_autoload = apply_filters( 'latepoint_settings_to_autoload', self::$settings_to_autoload );
		$settings_arr               = $settings_model->select( 'name, value' )->where( array( 'name' => self::$settings_to_autoload ) )->get_results();


		if ( $settings_arr && is_array( $settings_arr ) ) {
			foreach ( $settings_arr as $setting ) {
				if ( in_array( $setting->name, self::get_encrypted_settings() ) ) {
					self::$loaded_values[ $setting->name ] = OsEncryptHelper::decrypt_value( $setting->value );
				} else {
					self::$loaded_values[ $setting->name ] = $setting->value;
				}
			}
		}
	}


	// ENVIRONMENT SETTINGS

	// BASE ENVIRONMENT
	public static function is_env_live() {
		return ( LATEPOINT_ENV == LATEPOINT_ENV_LIVE );
	}

	public static function is_env_dev() {
		return ( LATEPOINT_ENV == LATEPOINT_ENV_DEV );
	}

	public static function is_env_demo() {
		return ( LATEPOINT_ENV == LATEPOINT_ENV_DEMO );
	}

	// SMS, EMAILS

	public static function is_sms_allowed() {
		return LATEPOINT_ALLOW_SMS;
	}

	public static function is_whatsapp_allowed() {
		return LATEPOINT_ALLOW_WHATSAPP;
	}

	public static function is_email_allowed() {
		return LATEPOINT_ALLOW_EMAILS;
	}

	// PAYMENTS ENVIRONMENT
	public static function is_env_payments_live() {
		return ( self::get_payments_environment() == LATEPOINT_PAYMENTS_ENV_LIVE );
	}

	public static function is_env_payments_dev() {
		return ( self::get_payments_environment() == LATEPOINT_PAYMENTS_ENV_DEV );
	}

	public static function is_env_payments_demo() {
		return ( self::get_payments_environment() == LATEPOINT_PAYMENTS_ENV_DEMO );
	}

	public static function get_payments_environment() {
		return self::get_settings_value( 'payments_environment', LATEPOINT_PAYMENTS_ENV_LIVE );
	}


	public static function append_payment_env_key( string $string, string $force_env = '' ): string {
		if ( $force_env ) {
			if ( $force_env == LATEPOINT_PAYMENTS_ENV_DEV ) {
				$string .= LATEPOINT_PAYMENTS_DEV_SUFFIX;
			}
		} else {
			if ( self::is_env_payments_dev() ) {
				$string .= LATEPOINT_PAYMENTS_DEV_SUFFIX;
			}
		}

		return $string;
	}

	public static function set_menu_layout_style( $layout ) {
		$layout = in_array( $layout, [ 'compact', 'full' ] ) ? $layout : 'full';
		update_user_meta( get_current_user_id(), 'latepoint_admin_menu_style', $layout );
	}

	public static function get_menu_layout_style(): string {
		$menu_style = get_user_meta( get_current_user_id(), 'latepoint_admin_menu_style', true );

		// Set default if not set
		if ( empty( $menu_style ) ) {
			$menu_style = 'full'; // default value
		}
		return $menu_style;
	}

	public static function get_time_system() {
		return self::get_settings_value( 'time_system', LATEPOINT_DEFAULT_TIME_SYSTEM );
	}

	public static function get_date_format() {
		return self::get_settings_value( 'date_format', LATEPOINT_DEFAULT_DATE_FORMAT );
	}

	public static function get_date_format_for_js() {
		$format = strtolower( self::get_date_format() );

		return str_replace( [ 'd', 'm', 'y' ], [ 'dd', 'mm', 'yyyy' ], $format );
	}

	public static function get_readable_datetime_format( $no_year = false, $no_seconds = true ) {
		return self::get_readable_date_format( $no_year ) . ', ' . self::get_readable_time_format( $no_seconds );
	}

	public static function get_order_types_list_for_any_agent_logic(): array {
		$order_types = [
			LATEPOINT_ANY_AGENT_ORDER_RANDOM     => __( 'Randomly picked agent', 'latepoint' ),
			LATEPOINT_ANY_AGENT_ORDER_PRICE_HIGH => __( 'Most expensive agent', 'latepoint' ),
			LATEPOINT_ANY_AGENT_ORDER_PRICE_LOW  => __( 'Least expensive agent', 'latepoint' ),
			LATEPOINT_ANY_AGENT_ORDER_BUSY_HIGH  => __( 'Agent with the most bookings on that day', 'latepoint' ),
			LATEPOINT_ANY_AGENT_ORDER_BUSY_LOW   => __( 'Agent with the least bookings on that day', 'latepoint' ),
		];

		/**
		 * Get the list of order types for agent selection logic when "ANY" agent is pre-selected in the booking form
		 *
		 * @param {array} $order_types Array of order types
		 *
		 * @returns {array} Filtered array of order types
		 * @since 4.7.6
		 * @hook latepoint_get_order_types_list_for_any_agent_logic
		 *
		 */
		return apply_filters( 'latepoint_get_order_types_list_for_any_agent_logic', $order_types );
	}

	public static function get_readable_time_format( $no_seconds = true ) {
		$seconds = $no_seconds ? '' : ':s';
		$format  = ( self::get_time_system() == '12' ) ? "g:i$seconds a" : "G:i$seconds";

		return $format;
	}

	public static function get_readable_date_format( $no_year = false, $short_month = false ) {
		if ( OsSettingsHelper::is_on( 'disable_verbose_date_output' ) ) {
			return self::get_date_format();
		}
		$format = ( $no_year ) ? 'F j' : 'M j, Y';
		switch ( self::get_date_format() ) {
			case 'm/d/Y':
			case 'm.d.Y':
				$format = ( $no_year ) ? 'F j' : 'M j, Y';
				break;
			case 'd.m.Y':
			case 'd/m/Y':
				$format = ( $no_year ) ? 'j F' : 'j M, Y';
				break;
			case 'Y-m-d':
				$format = ( $no_year ) ? 'F j' : 'Y, M j';
				break;
		}
		if ( $short_month ) {
			// if short month is requested - use month shorthand;
			$format = str_replace( 'F', 'M', $format );
		}

		return $format;
	}


	public static function get_booking_template_for_calendar() {
		return OsSettingsHelper::get_settings_value( 'booking_template_for_calendar', '{{service_name}}' );
	}


	public static function get_selected_columns_for_bookings_table() {
		return OsSettingsHelper::get_settings_value( 'bookings_table_columns', [] );
	}

	public static function get_available_columns_for_bookings_table() {
		$available_columns = [];

		$available_columns['customer'] = [
			'email' => __( 'Email', 'latepoint' ),
			'phone' => __( 'Phone', 'latepoint' ),
		];

		$available_columns['booking'] = [
			'booking_code'    => __( 'Code', 'latepoint' ),
			'duration'        => __( 'Duration', 'latepoint' ),
			'source_id'       => __( 'Source ID', 'latepoint' ),
			'payment_method'  => __( 'Payment Method', 'latepoint' ),
			'payment_portion' => __( 'Payment Portion', 'latepoint' ),
			'formatted_price' => __( 'Price', 'latepoint' ),
		];

		$available_columns = apply_filters( 'latepoint_bookings_table_columns', $available_columns );

		return $available_columns;
	}

	/**
	 * Returns a registry of ALL bookings table columns (fixed + extra), in default display order.
	 * Each entry: [ 'label', 'type' (fixed|extra), 'always_visible'?, 'condition'?, 'extra_type'?, 'extra_key'? ]
	 */
	public static function get_all_bookings_table_columns(): array {
		$columns = [
			'id'             => [
				'label'          => __( 'ID', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
			'service'        => [
				'label'     => __( 'Service', 'latepoint' ),
				'type'      => 'fixed',
				'condition' => 'multi_service',
			],
			'datetime'       => [
				'label'          => __( 'Date/Time', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
			'time_left'      => [
				'label'          => __( 'Time Left', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
			'agent'          => [
				'label'     => __( 'Agent', 'latepoint' ),
				'type'      => 'fixed',
				'condition' => 'multi_agent',
			],
			'location'       => [
				'label'     => __( 'Location', 'latepoint' ),
				'type'      => 'fixed',
				'condition' => 'multi_location',
			],
			'customer'       => [
				'label'          => __( 'Customer', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
			'status'         => [
				'label'          => __( 'Status', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
			'payment_status' => [
				'label'          => __( 'Payment Status', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
			'created_on'     => [
				'label'          => __( 'Created On', 'latepoint' ),
				'type'           => 'fixed',
				'always_visible' => true,
			],
		];

		// Append extra columns from the existing registry (includes addon filter results).
		foreach ( self::get_available_columns_for_bookings_table() as $extra_type => $type_columns ) {
			foreach ( $type_columns as $extra_key => $extra_label ) {
				$columns[ $extra_type . '.' . $extra_key ] = [
					'label'      => $extra_label,
					'type'       => 'extra',
					'extra_type' => $extra_type,
					'extra_key'  => $extra_key,
				];
			}
		}

		return apply_filters( 'latepoint_all_bookings_table_columns', $columns );
	}

	/**
	 * Returns the saved column order array (flat list of column keys).
	 * Returns [] if no order has been saved yet.
	 */
	public static function get_bookings_table_columns_order(): array {
		$order = self::get_settings_value( 'bookings_table_columns_order', [] );
		return is_array( $order ) ? $order : [];
	}

	/**
	 * Returns all columns sorted by the user-saved order.
	 * Falls back to default order when no order is saved (backward compat).
	 * Columns added by addons after the order was saved are appended at the end.
	 */
	public static function get_ordered_bookings_table_columns(): array {
		$all_columns = self::get_all_bookings_table_columns();
		$saved_order = self::get_bookings_table_columns_order();

		if ( empty( $saved_order ) ) {
			return $all_columns;
		}

		$ordered = [];
		foreach ( $saved_order as $key ) {
			if ( 'id' === $key ) continue;
			if ( isset( $all_columns[ $key ] ) ) {
				$ordered[ $key ] = $all_columns[ $key ];
			}
		}
		// Append any columns not in saved order (e.g. newly installed addon columns).
		foreach ( $all_columns as $key => $def ) {
			if ( 'id' === $key ) continue;
			if ( ! isset( $ordered[ $key ] ) ) {
				$ordered[ $key ] = $def;
			}
		}

		// ID column is always first regardless of saved order.
		if ( isset( $all_columns['id'] ) ) {
			$ordered = array_merge( [ 'id' => $all_columns['id'] ], $ordered );
		}

		return $ordered;
	}

	/**
	 * Determines whether a given column should be visible in the table.
	 */
	public static function is_bookings_column_visible( array $col_def, array $selected_columns, int $services_count, int $agents_count, int $locations_count ): bool {
		if ( ! empty( $col_def['always_visible'] ) ) {
			return true;
		}
		if ( ! empty( $col_def['condition'] ) ) {
			switch ( $col_def['condition'] ) {
				case 'multi_service':
					return $services_count > 1;
				case 'multi_agent':
					return $agents_count > 1;
				case 'multi_location':
					return $locations_count > 1;
			}
		}
		if ( 'extra' === $col_def['type'] ) {
			$extra_type = $col_def['extra_type'];
			$extra_key  = $col_def['extra_key'];
			return isset( $selected_columns[ $extra_type ] ) && in_array( $extra_key, $selected_columns[ $extra_type ], true );
		}
		return false;
	}

	public static function force_bite( $request ) {
	}

	public static function read_encoded( $str ) {
		return base64_decode( $str );
	}

	public static function get_db_version() {
		return get_option( 'latepoint_db_version' );
	}

	public static function force_release( $request ) {
	}

	public static function is_enabled_show_dial_code_with_flag() {
		return ( self::get_settings_value( 'show_dial_code_with_flag', LATEPOINT_VALUE_ON ) == LATEPOINT_VALUE_ON );
	}

	public static function is_using_google_login() {
		return self::is_on( 'enable_google_login' );
	}

	public static function is_using_facebook_login() {
		return self::is_on( 'enable_facebook_login' );
	}

	public static function is_using_social_login() {
		return ( self::is_using_google_login() || self::is_using_facebook_login() );
	}

	public static function get_steps_support_text() {
		$default = '<h5>Questions?</h5><p>Call (858) 939-3746 for help</p>';

		return self::get_settings_value( 'steps_support_text', $default );
	}

	public static function get_default_fields_for_customer() {
		$default_fields = [
			'first_name' => [
				'label'    => __( 'First Name', 'latepoint' ),
				'required' => true,
				'width'    => 'os-col-6',
				'active'   => true,
			],
			'last_name'  => [
				'label'    => __( 'Last Name', 'latepoint' ),
				'required' => true,
				'width'    => 'os-col-6',
				'active'   => true,
			],
			'email'      => [
				'label'    => __( 'Email Address', 'latepoint' ),
				'required' => true,
				'width'    => 'os-col-6',
				'active'   => true,
			],
			'phone'      => [
				'label'    => __( 'Phone Number', 'latepoint' ),
				'required' => false,
				'width'    => 'os-col-6',
				'active'   => true,
			],
			'notes'      => [
				'label'    => __( 'Comments', 'latepoint' ),
				'required' => false,
				'width'    => 'os-col-12',
				'active'   => true,
			],
		];

		$fields_from_db     = OsSettingsHelper::get_settings_value( 'default_fields_for_customer', '' );
		$fields_from_db_arr = json_decode( $fields_from_db, true );
		if ( $fields_from_db_arr ) {
			foreach ( $fields_from_db_arr as $name => $field_from_db ) {
				if ( isset( $default_fields[ $name ] ) ) {
					$default_fields[ $name ] = $field_from_db;
				}
			}
		}
		$default_fields = apply_filters( 'latepoint_default_fields_for_customer', $default_fields );

		return $default_fields;
	}

	public static function remove_setting_by_name( $name ) {
		$settings_model = new OsSettingsModel();
		$settings_model = $settings_model->delete_where( array( 'name' => $name ) );
		self::reset_loaded_value( $name );
	}

	public static function save_setting_by_name( $name, $value ) {
		$settings_model = new OsSettingsModel();
		$settings_model = $settings_model->where( array( 'name' => $name ) )->set_limit( 1 )->get_results_as_models();
		if ( $settings_model ) {
			$settings_model->value = self::prepare_value( $name, $value );
		} else {
			$settings_model        = new OsSettingsModel();
			$settings_model->name  = $name;
			$settings_model->value = self::prepare_value( $name, $value );
		}
		self::reset_loaded_value( $name );

		return $settings_model->save();
	}

	public static function reset_loaded_value( $name ) {
		unset( self::$loaded_values[ $name ] );
	}

	public static function prepare_value( $name, $value ) {
		if ( in_array( $name, self::get_encrypted_settings() ) ) {
			$value = OsEncryptHelper::encrypt_value( $value );
		}
		if ( is_array( $value ) ) {
			$value = maybe_serialize( $value );
		}

		return $value;
	}

	public static function get_settings_value( $name, $default = false ) {
		if ( isset( self::$loaded_values[ $name ] ) ) {
			return self::$loaded_values[ $name ];
		}
		$settings_model = new OsSettingsModel();
		$settings_model = $settings_model->where( array( 'name' => $name ) )->set_limit( 1 )->get_results_as_models();
		if ( $settings_model ) {
			if ( in_array( $name, self::get_encrypted_settings() ) ) {
				$value = OsEncryptHelper::decrypt_value( $settings_model->value );
			} else {
				$value = maybe_unserialize( $settings_model->value );
			}
		} else {
			$value = $default;
		}
		self::$loaded_values[ $name ] = $value;

		return self::$loaded_values[ $name ];
	}


	public static function get_any_agent_order() {
		return self::get_settings_value( 'any_agent_order', LATEPOINT_ANY_AGENT_ORDER_RANDOM );
	}

	public static function get_day_calendar_min_height() {
		$height = preg_replace( '/\D/', '', self::get_settings_value( 'day_calendar_min_height', 700 ) );
		if ( ! $height ) {
			$height = 700;
		}

		return $height;
	}

	public static function get_default_timeblock_interval() {
		$timeblock_interval = self::get_settings_value( 'timeblock_interval', LATEPOINT_DEFAULT_TIMEBLOCK_INTERVAL );
		if ( empty( $timeblock_interval ) ) {
			$timeblock_interval = LATEPOINT_DEFAULT_TIMEBLOCK_INTERVAL;
		}

		return intval( $timeblock_interval );
	}

	public static function get_customer_dashboard_url( $include_site_url = true ) {
		$path = self::get_settings_value( 'page_url_customer_dashboard', '/customer-dashboard' );

		return ( $include_site_url ) ? site_url( $path ) : $path;
	}

	public static function get_customer_login_url( $include_site_url = true ) {
		$path = self::get_settings_value( 'page_url_customer_login', '/customer-login' );

		return ( $include_site_url ) ? site_url( $path ) : $path;
	}


	// BOOKING STEPS

	public static function steps_show_service_categories() {
		return ( self::get_settings_value( 'steps_show_service_categories', 'on' ) == 'on' );
	}

	public static function steps_show_location_categories() {
		return ( self::get_settings_value( 'steps_show_location_categories', 'on' ) == 'on' );
	}

	public static function steps_show_agent_bio() {
		return ( self::get_settings_value( 'steps_show_agent_bio' ) == 'on' );
	}

	public static function get_booking_form_color_scheme() {
		return self::get_settings_value( 'color_scheme_for_booking_form', 'blue' );
	}

	public static function get_booking_form_border_radius() {
		return self::get_settings_value( 'border_radius', 'flat' );
	}

	public static function get_default_phone_country() {
		return OsSettingsHelper::get_settings_value( 'default_phone_country', 'us' );
	}

	public static function get_included_phone_countries(): array {
		if ( OsSettingsHelper::get_settings_value( 'list_of_phone_countries', LATEPOINT_ALL ) == LATEPOINT_ALL ) {
			return [];
		} else {
			return array_map( 'trim', explode( ',', OsSettingsHelper::get_settings_value( 'included_phone_countries', '' ) ) );
		}
	}


	public static function is_on( string $setting, $default = false ): bool {
		return ( self::get_settings_value( $setting, $default ) == LATEPOINT_VALUE_ON );
	}

	public static function is_off( string $setting, $default = false ): bool {
		return ( self::get_settings_value( $setting, $default ) != LATEPOINT_VALUE_ON );
	}

	public static function generate_default_form_fields( array $default_fields ) {
		?>
		<div class="os-default-fields" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'form_fields', 'update_default_fields' ) ); ?>">
			<form>
				<?php foreach ( $default_fields as $name => $default_field ) {
					$atts = [];
					?>
					<div class="os-default-field <?php echo $default_field['active'] ? '' : 'is-disabled'; ?>">
						<?php
							$active     = $default_field['active'] ? 'on' : 'off';
							$field_name = 'default_fields[' . $name . '][active]';
							echo '<div class="os-toggler ' . esc_attr( $active ) . '" data-for="' . esc_attr( OsFormHelper::name_to_id( $field_name ) ) . '"><div class="toggler-rail"><div class="toggler-pill"></div></div></div>';
							echo OsFormHelper::hidden_field( $field_name, $default_field['active'] );
						?>
						<div class="os-field-name"><?php echo esc_html( $default_field['label'] ); ?></div>
						<div class="os-field-setting">
							<?php echo OsFormHelper::checkbox_field( 'default_fields[' . $name . '][required]', __( 'Required?', 'latepoint' ), 'on', $default_field['required'], $atts ); ?>
						</div>
						<div class="os-field-setting">
							<?php echo OsFormHelper::select_field(
								'default_fields[' . $name . '][width]',
								false,
								array(
									'os-col-12' => __( 'Full Width', 'latepoint' ),
									'os-col-6'  => __( 'Half Width', 'latepoint' ),
								),
								$default_field['width'] 
							); ?>
						</div>
					</div>
				<?php } ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Get value set for start of week in WordPress, can be from 1-7 (representing Monday-Sunday)
	 * @return mixed
	 */
	public static function get_start_of_week(): int {
		$wp_value = intval( get_option( 'start_of_week', 0 ) );

		return $wp_value ? $wp_value : 7; // in WordPress they start count on sunday and it's 0, we are using PHP Date library that starts week on Monday and it's 1-7
	}

	public static function get_number_of_records_per_page(): int {
		return intval( OsSettingsHelper::get_settings_value( 'number_of_records_per_page', 20 ) );
	}

	public static function can_download_records_as_csv(): bool {
		return OsAuthHelper::is_admin_logged_in() || self::is_on( 'allow_non_admins_download_csv' );
	}

	public static function get_link_attributes_for_premium_features(): string {
		return ' data-os-action="' . OsRouterHelper::build_route_name( 'settings', 'premium_modal' ) . '" data-os-lightbox-classes="width-600" data-os-output-target="lightbox" ';
	}

	public static function generate_instant_booking_page_url( array $url_settings ): string {
		$url_settings['latepoint'] = 'instant';
		return OsUtilHelper::get_site_url() . '/?' . http_build_query( $url_settings );
	}

	public static function instant_page_background_patterns(): array {
		return [
			'pattern_1'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(45deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);',
			'pattern_2'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #a18cd1 0%, #fbc2eb 100%);',
			'pattern_3'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #fbc2eb 0%, #a6c1ee 100%);',
			'pattern_4'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(120deg, #f6d365 0%, #fda085 100%);',
			'pattern_5'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(180deg, #2af598 0%, #009efd 100%);',
			'pattern_6'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);',
			'pattern_7'  => 'background-color: transparent; background-size: auto; background-color: #DCD9D4; background-image: linear-gradient(to bottom, rgba(255,255,255,0.50) 0%, rgba(0,0,0,0.50) 100%), radial-gradient(at 50% 0%, rgba(255,255,255,0.10) 0%, rgba(0,0,0,0.50) 50%); background-blend-mode: soft-light,screen;',
			'pattern_8'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #f77062 0%, #fe5196 100%);',
			'pattern_9'  => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #00c6fb 0%, #005bea 100%);',
			'pattern_10' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #09203f 0%, #537895 100%);',
			'pattern_11' => 'background-color: transparent; background-size: auto; background-image: radial-gradient(73% 147%, #EADFDF 59%, #ECE2DF 100%), radial-gradient(91% 146%, rgba(255,255,255,0.50) 47%, rgba(0,0,0,0.50) 100%); background-blend-mode: screen;',
			'pattern_12' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, lightgrey 0%, lightgrey 1%, #e0e0e0 26%, #efefef 48%, #d9d9d9 75%, #bcbcbc 100%);',
			'pattern_13' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #fddb92 0%, #d1fdff 100%);',
			'pattern_14' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #dfe9f3 0%, white 100%);',
			'pattern_15' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(-225deg, #5271C4 0%, #B19FFF 48%, #ECA1FE 100%);',
			'pattern_16' => 'background-color: transparent; background-size: auto; background: linear-gradient(to bottom, rgba(255,255,255,0.15) 0%, rgba(0,0,0,0.15) 100%), radial-gradient(at top center, rgba(255,255,255,0.40) 0%, rgba(0,0,0,0.40) 120%) #989898;background-blend-mode: multiply,multiply;',
			'pattern_17' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to top, #f43b47 0%, #453a94 100%);',
			'pattern_18' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(to right, #222 0%, #111 100%);',
			'pattern_19' => 'background-color: transparent; background-size: auto; background-image: linear-gradient(-20deg, #d558c8 0%, #24d292 100%);',
		];
	}

	public static function get_default_wp_role_for_new_customers(): string {
		if ( ! wp_roles()->is_role( LATEPOINT_WP_CUSTOMER_ROLE ) ) {
			OsRolesHelper::register_customer_role();
		}
		return OsSettingsHelper::get_settings_value( 'default_wp_role_for_customer', LATEPOINT_WP_CUSTOMER_ROLE );
	}
}

?>