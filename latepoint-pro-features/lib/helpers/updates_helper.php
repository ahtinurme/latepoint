<?php

class OsUpdatesHelper {

	public static function set_update_message() {
		$addon_names = self::get_all_latepoint_addon_names();
		foreach ( $addon_names as $addon_name ) {
			add_action( 'in_plugin_update_message-' . self::build_addon_path( $addon_name ), 'OsUpdatesHelper::set_update_message_text', 10, 2 );
		}
	}


	public static function get_remote_url( $extra = '' ) {
		return base64_decode( LATEPOINT_PRO_REMOTE_HASH ) . $extra;
	}

	public static function set_update_message_text( $plugin_data, $new_data ) {
		if ( ! empty( $plugin_data['update'] ) && empty( $plugin_data['package'] ) ) {
			echo sprintf( __( 'To enable updates, you need to purchase a PRO license, install "PRO Features" addon and enter that key on %s page', 'latepoint-pro-features' ), '<a href="' . OsRouterHelper::build_link( [ 'updates', 'status' ] ) . '">' . __( 'Pro Features', 'latepoint-pro-features' ) . '</a>.' );
		}
	}

	public static function get_all_addons_info() {

		$cache_key   = 'latepoint_addons_info';
		$addons_info = get_transient( $cache_key );

		if ( false === $addons_info ) {


			// connect
			$vars = array(
				'domain'      => OsUtilHelper::get_site_url(),
				'license_key' => OsLicenseHelper::get_license_key(),
			);

				$url = OsUpdatesHelper::get_remote_url( '/wp/addons/get-all-addons-info' );


			$remote = wp_remote_post(
				$url,
				array(
					'body'      => $vars,
					'sslverify' => LATEPOINT_PRO_SSL_VERIFY,
					'timeout'   => 10,
					'headers'   => array(
						'Accept' => 'application/json',
					),
				) 
			);
			if ( is_wp_error( $remote ) || wp_remote_retrieve_response_code( $remote ) !== 200 || empty( wp_remote_retrieve_body( $remote ) ) ) {
				if ( is_wp_error( $remote ) ) {
					OsDebugHelper::log( 'Connection error', 'list_of_addons_error', [ 'error' => $remote->get_error_messages() ] );
				} else {
					OsDebugHelper::log( 'Connection error', 'list_of_addons_error', [ 'error' => 'Empty response' ] );
				}
				// cache for 60 seconds to not call it multiple times in a row
				set_transient( $cache_key, [], 60 );
				return [];
			} else {
				$response_body = json_decode( wp_remote_retrieve_body( $remote ), true );
				// check our internal status of the response
				if ( $response_body['status'] == 200 ) {
					set_transient( $cache_key, $response_body['addons'], DAY_IN_SECONDS );
					return $response_body['addons'];
				} else {
					OsDebugHelper::log( 'Error getting addon info', 'list_of_addons_error', [ 'error' => $response_body->message ] );
					// cache for 60 seconds to not call it multiple times in a row
					set_transient( $cache_key, [], 60 );
					return [];
				}
			}
		} else {
			return $addons_info;
		}
	}


	public static function get_all_latepoint_addon_names() {
		$addons_info = self::get_all_addons_info();
		$addon_names = array_keys( $addons_info );
		return apply_filters( 'latepoint_get_all_latepoint_addon_names', $addon_names );
	}


	public static function set_addon_info( $res, $action, $args ) {

		// do nothing if you're not getting plugin information right now
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		$latepoint_addons_names = self::get_all_latepoint_addon_names();


		// do nothing if it is not on of our addons
		if ( ! in_array( $args->slug, $latepoint_addons_names ) ) {
			return $res;
		}

		// get updates
		$addons_info = self::get_all_addons_info();

		if ( ! $addons_info || empty( $addons_info[ $args->slug ] ) ) {
			return $res;
		}


		$res = new stdClass();

		$res->name           = $addons_info[ $args->slug ]['name'];
		$res->slug           = $addons_info[ $args->slug ]['slug'];
		$res->version        = $addons_info[ $args->slug ]['version'];
		$res->tested         = $addons_info[ $args->slug ]['tested'];
		$res->requires       = $addons_info[ $args->slug ]['requires'];
		$res->author         = $addons_info[ $args->slug ]['author'];
		$res->author_profile = $addons_info[ $args->slug ]['author_profile'];
		$res->download_link  = $addons_info[ $args->slug ]['download_url'];
		$res->trunk          = $addons_info[ $args->slug ]['download_url'];
		$res->requires_php   = $addons_info[ $args->slug ]['requires_php'];
		$res->last_updated   = $addons_info[ $args->slug ]['last_updated'];

		$res->sections = array(
			'description'  => $addons_info[ $args->slug ]['sections']['description'],
			'installation' => $addons_info[ $args->slug ]['sections']['installation'],
			'changelog'    => $addons_info[ $args->slug ]['sections']['changelog'],
		);


		if ( ! empty( $addons_info[ $args->slug ]['banners'] ) ) {
			$res->banners = array(
				'low'  => $addons_info[ $args->slug ]['banners']['low'],
				'high' => $addons_info[ $args->slug ]['banners']['high'],
			);
		}

		return $res;
	}

	public static function build_addon_path( string $addon_name ) {
		return $addon_name . '/' . $addon_name . '.php';
	}

	public static function update_transient( $transient ) {
		if ( empty( $transient->checked ) && ! is_admin() ) {
			return $transient;
		}


		$addon_names = self::get_all_latepoint_addon_names();
		foreach ( $addon_names as $addon_name ) {
			$addon_path = self::build_addon_path( $addon_name );
			if ( ! isset( $transient->checked[ $addon_path ] ) ) {
				continue;
			}
			$addons_info = self::get_all_addons_info();
			if ( ! $addons_info || empty( $addons_info[ $addon_name ] ) ) {
				continue;
			}

			if ( ! function_exists( 'get_plugin_data' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$addon_plugin_path = OsAddonsHelper::get_addon_plugin_path( $addon_path );

			if ( ! is_file( $addon_plugin_path ) ) {
				continue;
			}

			$installed_plugin_data = get_plugin_data( $addon_plugin_path );
			$current_version       = $installed_plugin_data['Version'];
			if ( empty( $current_version ) ) {
				continue;
			}
			// Create the plugin update object
			$res              = new stdClass();
			$res->slug        = $addon_name;
			$res->plugin      = $addon_path;
			$res->new_version = $addons_info[ $addon_name ]['version'];
			$res->tested      = $addons_info[ $addon_name ]['tested'];
			$res->package     = $addons_info[ $addon_name ]['download_url'];
			if ( version_compare( $current_version, $addons_info[ $addon_name ]['version'], '<' ) ) {
				// Add it to the transient
				$transient->response[ $addon_path ] = $res;
			} else {
				$transient->no_update[ $addon_path ] = $res;
			}
		}


		return $transient;
	}

	public static function purge_addon_cache( $upgrader, $options ) {

		if ( 'update' === $options['action'] && 'plugin' === $options['type'] && isset( $options['plugins'] ) ) {
			// Iterate through the plugins being updated and check if ours is there
			foreach ( $options['plugins'] as $plugin ) {
				if ( in_array( $plugin, self::get_all_latepoint_addon_names() ) ) {
					// Your action if it is your plugin
					// just clean the cache when new plugin version is installed
					delete_transient( self::build_cache_key( $plugin ) );

				}
			}
		}
	}

	public static function build_cache_key( string $addon_name ) {
		return $addon_name . '_update';
	}

	public static function is_update_available_for_addons() {
		return get_option( 'latepoint_addons_update_available', false );
	}

	public static function get_list_of_addons() {
		// connect
		$vars = array(
			'_nonce'      => wp_create_nonce( 'activate_licence' ),
			'version'     => LATEPOINT_VERSION,
			'domain'      => OsUtilHelper::get_site_url(),
			'marketplace' => LATEPOINT_MARKETPLACE,
			'license_key' => OsLicenseHelper::get_license_key(),
			'user_ip'     => OsUtilHelper::get_user_ip(),
		);
		$url  = OsUpdatesHelper::get_remote_url( '/wp/addons/load_addons_list' );


		$request = wp_remote_post(
			$url,
			array(
				'body'      => $vars,
				'sslverify' => LATEPOINT_PRO_SSL_VERIFY,
			) 
		);
		$addons  = false;
		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			$addons = json_decode( $request['body'] );
		} else {
			if ( is_wp_error( $request ) ) {
				OsDebugHelper::log( 'Connection error', 'list_of_addons_error', [ 'error' => $request->get_error_messages() ] );
			}
		}

		return $addons;
	}

	public static function get_addons_info() {
		// connect
		$vars = array(
			'_nonce'      => wp_create_nonce( 'activate_licence' ),
			'version'     => LATEPOINT_VERSION,
			'domain'      => OsUtilHelper::get_site_url(),
			'marketplace' => LATEPOINT_MARKETPLACE,
			'license_key' => OsLicenseHelper::get_license_key(),
			'user_ip'     => OsUtilHelper::get_user_ip(),
		);
		$url  = OsUpdatesHelper::get_remote_url( '/wp/addons/get_addons_info' );


		$request     = wp_remote_post(
			$url,
			array(
				'body'      => $vars,
				'sslverify' => LATEPOINT_PRO_SSL_VERIFY,
			) 
		);
		$addons_info = false;
		if ( ! is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
			$addons_info = json_decode( $request['body'] );
		} else {
			if ( is_wp_error( $request ) ) {
				OsDebugHelper::log( 'Connection error', 'load_addons_info_error', [ 'error' => $request->get_error_messages() ] );
			}
		}

		return $addons_info;
	}

	public static function check_addons_latest_version( $addons = false ) {
		if ( ! $addons ) {
			$addons = self::get_list_of_addons();
		}
		$addons_to_update = [];
		if ( $addons ) {
			foreach ( $addons as $addon ) {
				$is_installed = OsAddonsHelper::is_addon_installed( $addon->wp_plugin_path );
				if ( $is_installed ) {
					if ( ! function_exists( 'get_plugin_data' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$addon_data        = get_plugin_data( OsAddonsHelper::get_addon_plugin_path( $addon->wp_plugin_path ) );
					$installed_version = ( isset( $addon_data['Version'] ) ) ? $addon_data['Version'] : '1.0.0';
					if ( version_compare( $addon->version, $installed_version ) > 0 ) {
						$addons_to_update[] = $addon->wp_plugin_name;
					}
				}
			}
		}
		if ( $addons_to_update ) {
			update_option( 'latepoint_addons_update_available', true );
		} else {
			update_option( 'latepoint_addons_update_available', false );
		}
	}
}
