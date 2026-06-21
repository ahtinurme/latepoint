<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsFeatureWhiteLabelHelper {

	/**
	 * Register hooks when the white label feature is enabled and white labeling is turned on.
	 * Called from latepoint-pro-features.php init_hooks().
	 */
	public static function init(): void {
		// Always register defaults so the settings page works regardless of toggle state.
		add_filter( 'latepoint_settings_defaults', [ __CLASS__, 'register_defaults' ] );
		add_filter( 'latepoint_settings_to_autoload', [ __CLASS__, 'register_autoload_keys' ] );

		if ( ! OsSettingsHelper::is_on( 'white_label_enabled' ) ) {
			return;
		}

		// Brand identity — single filter drives all surfaces via OsSettingsHelper::get_brand_name().
		add_filter( 'latepoint_brand_name', [ __CLASS__, 'filter_brand_name' ] );
		add_filter( 'latepoint_admin_side_menu_logo_url', [ __CLASS__, 'filter_admin_logo_url' ] );
		// add_filter( 'latepoint_admin_menu_icon', [ __CLASS__, 'filter_admin_menu_icon' ] );

		// Plugins list row.
		add_filter( 'all_plugins', [ __CLASS__, 'filter_plugin_row' ] );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'filter_plugin_row_meta' ], 20, 2 );
		add_action( 'admin_head', [ __CLASS__, 'output_plugins_page_css' ] );

		// Hide the White Label menu item from the admin sidebar (priority 20 runs after add_menu_links at 10).
		add_filter( 'latepoint_side_menu', [ __CLASS__, 'filter_hide_white_label_menu' ], 20 );

		// Always suppress pro upsells and NPS survey when white labeling is on.
		add_filter( 'latepoint_show_upgrade_link_on_plugins_page', '__return_false' );

		// Role display names.
		add_filter( 'latepoint_role_display_name', [ __CLASS__, 'filter_role_display_name' ], 10, 2 );
		add_filter( 'editable_roles', [ __CLASS__, 'filter_editable_roles' ] );
	}

	// -------------------------------------------------------------------------
	// Filter callbacks
	// -------------------------------------------------------------------------

	/**
	 * Replace the brand name if a custom name is configured.
	 *
	 * @param string $name Current brand name.
	 * @return string
	 */
	public static function filter_brand_name( string $name ): string {
		$custom = OsSettingsHelper::get_settings_value( 'white_label_brand_name', '' );
		return $custom !== '' ? $custom : $name;
	}

	/**
	 * Strip the brand prefix from LatePoint role display names.
	 *
	 * @param string $name Current role display name.
	 * @param string $type LatePoint user type constant.
	 * @return string
	 */
	public static function filter_role_display_name( string $name, string $type ): string {
		switch ( $type ) {
			case LATEPOINT_USER_TYPE_AGENT:
				return __( 'Agent', 'latepoint-pro-features' );
			case LATEPOINT_USER_TYPE_CUSTOMER:
				return __( 'Customer', 'latepoint-pro-features' );
			default:
				return $name;
		}
	}

	/**
	 * Rename LatePoint roles in the WordPress Users admin (editable_roles covers existing installs
	 * where the role display name is already stored in the database).
	 *
	 * @param array $roles All editable WP roles.
	 * @return array
	 */
	public static function filter_editable_roles( array $roles ): array {
		if ( isset( $roles[ LATEPOINT_WP_AGENT_ROLE ] ) ) {
			$roles[ LATEPOINT_WP_AGENT_ROLE ]['name'] = __( 'Agent', 'latepoint-pro-features' );
		}
		if ( isset( $roles[ LATEPOINT_WP_CUSTOMER_ROLE ] ) ) {
			$roles[ LATEPOINT_WP_CUSTOMER_ROLE ]['name'] = __( 'Customer', 'latepoint-pro-features' );
		}
		return $roles;
	}

	/**
	 * Replace the admin side-menu logo URL if a custom logo is uploaded.
	 *
	 * @param string $url Default logo URL.
	 * @return string
	 */
	public static function filter_admin_logo_url( string $url ): string {
		$image_id = (int) OsSettingsHelper::get_settings_value( 'white_label_brand_logo', 0 );
		if ( $image_id > 0 ) {
			$custom_url = OsImageHelper::get_image_url_by_id( $image_id );
			if ( $custom_url ) {
				return $custom_url;
			}
		}
		return $url;
	}

	/**
	 * Replace the WP admin top-level menu icon.
	 *
	 * @param string $icon Default icon value ('none').
	 * @return string
	 */
	// public static function filter_admin_menu_icon( string $icon ): string {
	// 	$image_id = (int) OsSettingsHelper::get_settings_value( 'white_label_brand_logo', 0 );
	// 	if ( $image_id > 0 ) {
	// 		$custom_url = OsImageHelper::get_image_url_by_id( $image_id );
	// 		if ( $custom_url ) {
	// 			return $custom_url;
	// 		}
	// 	}
	// 	return $icon;
	// }

	/**
	 * Rename the plugin in the plugins list.
	 *
	 * @param array $plugins All installed plugins.
	 * @return array
	 */
	public static function filter_plugin_row( array $plugins ): array {
		$free_plugin_file = 'latepoint/latepoint.php';

		// Read all override settings once.
		$brand_name     = OsSettingsHelper::get_settings_value( 'white_label_brand_name', '' );
		$row_name       = OsSettingsHelper::get_settings_value( 'white_label_plugin_row_name', '' );
		$row_uri        = OsSettingsHelper::get_settings_value( 'white_label_plugin_row_uri', '' );
		$row_desc       = OsSettingsHelper::get_settings_value( 'white_label_plugin_row_description', '' );
		$row_author     = OsSettingsHelper::get_settings_value( 'white_label_plugin_row_author', '' );
		$row_author_uri = OsSettingsHelper::get_settings_value( 'white_label_plugin_row_author_uri', '' );

		// Explicit overrides for the FREE plugin row.
		if ( isset( $plugins[ $free_plugin_file ] ) ) {
			if ( $row_name !== '' ) {
				$plugins[ $free_plugin_file ]['Name']  = $row_name;
				$plugins[ $free_plugin_file ]['Title'] = $row_name;
			} elseif ( $brand_name !== '' ) {
				foreach ( [ 'Name', 'Title' ] as $field ) {
					if ( ! empty( $plugins[ $free_plugin_file ][ $field ] ) ) {
						$plugins[ $free_plugin_file ][ $field ] = str_ireplace( 'LatePoint', $brand_name, $plugins[ $free_plugin_file ][ $field ] );
					}
				}
			}
			if ( $row_desc !== '' ) {
				$plugins[ $free_plugin_file ]['Description'] = $row_desc;
			} elseif ( $brand_name !== '' && ! empty( $plugins[ $free_plugin_file ]['Description'] ) ) {
				$plugins[ $free_plugin_file ]['Description'] = str_ireplace( 'LatePoint', $brand_name, $plugins[ $free_plugin_file ]['Description'] );
			}
			if ( $row_author !== '' ) {
				$plugins[ $free_plugin_file ]['Author']     = $row_author;
				$plugins[ $free_plugin_file ]['AuthorName'] = $row_author;
			} elseif ( $brand_name !== '' ) {
				foreach ( [ 'Author', 'AuthorName' ] as $field ) {
					if ( ! empty( $plugins[ $free_plugin_file ][ $field ] ) ) {
						$plugins[ $free_plugin_file ][ $field ] = str_ireplace( 'LatePoint', $brand_name, $plugins[ $free_plugin_file ][ $field ] );
					}
				}
			}
			$plugins[ $free_plugin_file ]['PluginURI'] = $row_uri !== '' ? $row_uri : '#';
			$plugins[ $free_plugin_file ]['AuthorURI'] = $row_author_uri !== '' ? $row_author_uri : '#';
		}

		// Override every other LatePoint family plugin.
		foreach ( $plugins as $file => &$data ) {
			if ( $file === $free_plugin_file ) {
				continue;
			}
			if ( strpos( $file, 'latepoint/' ) !== 0 && strpos( $file, 'latepoint-' ) !== 0 ) {
				continue;
			}
			// Name / Title / Description — substring-replace LatePoint → brand name.
			if ( $brand_name !== '' ) {
				foreach ( [ 'Name', 'Title', 'Description' ] as $field ) {
					if ( ! empty( $data[ $field ] ) ) {
						$data[ $field ] = str_ireplace( 'LatePoint', $brand_name, $data[ $field ] );
					}
				}
			}
			// Author — use explicit override, else substring-replace.
			if ( $row_author !== '' ) {
				$data['Author']     = $row_author;
				$data['AuthorName'] = $row_author;
			} elseif ( $brand_name !== '' ) {
				if ( ! empty( $data['Author'] ) ) {
					$data['Author'] = str_ireplace( 'LatePoint', $brand_name, $data['Author'] );
				}
				if ( ! empty( $data['AuthorName'] ) ) {
					$data['AuthorName'] = str_ireplace( 'LatePoint', $brand_name, $data['AuthorName'] );
				}
			}
			// Always neutralize LatePoint URLs — use user value or fall back to '#'.
			$data['PluginURI'] = $row_uri !== '' ? $row_uri : '#';
			$data['AuthorURI'] = $row_author_uri !== '' ? $row_author_uri : '#';
		}
		unset( $data );

		return $plugins;
	}

	/**
	 * Catch-all: replace any remaining "LatePoint" text in plugin row meta items
	 * (covers "Required by / Requires" dependency notices added by WP core).
	 *
	 * @param array  $plugin_meta Meta items for the row.
	 * @param string $plugin_file Plugin file basename.
	 * @return array
	 */
	public static function filter_plugin_row_meta( array $plugin_meta, string $plugin_file ): array {
		$brand_name = OsSettingsHelper::get_settings_value( 'white_label_brand_name', '' );
		if ( $brand_name === '' ) {
			return $plugin_meta;
		}
		if ( $plugin_file !== 'latepoint/latepoint.php'
			&& strpos( $plugin_file, 'latepoint/' ) !== 0
			&& strpos( $plugin_file, 'latepoint-' ) !== 0 ) {
			return $plugin_meta;
		}
		return array_map(
			static function ( $item ) use ( $brand_name ) {
				return str_ireplace( 'LatePoint', $brand_name, $item );
			},
			$plugin_meta
		);
	}

	/**
	 * Hide WP Plugin-Dependencies "Required by / Requires" notices on the plugins page.
	 * These divs are output directly by WP_Plugins_List_Table (no filter), so CSS is the only option.
	 */
	public static function output_plugins_page_css(): void {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}
		echo '<style>
			tr[data-plugin="latepoint/latepoint.php"] .required-by,
			tr[data-plugin="latepoint/latepoint.php"] .requires,
			tr[data-plugin^="latepoint-"] .required-by,
			tr[data-plugin^="latepoint-"] .requires { display: none; }
			</style>';
	}

	/**
	 * Remove the White Label child item from the LatePoint admin sidebar.
	 *
	 * @param array $menus Full sidebar menu tree.
	 * @return array
	 */
	public static function filter_hide_white_label_menu( array $menus ): array {
		if ( ! OsSettingsHelper::is_on( 'white_label_hide_menu' ) ) {
			return $menus;
		}
		foreach ( $menus as $i => $menu ) {
			if ( ! isset( $menu['id'] ) || $menu['id'] !== 'settings' ) {
				continue;
			}
			if ( empty( $menu['children'] ) ) {
				continue;
			}
			foreach ( $menu['children'] as $j => $child ) {
				if ( isset( $child['id'] ) && $child['id'] === 'white_label' ) {
					unset( $menus[ $i ]['children'][ $j ] );
					$menus[ $i ]['children'] = array_values( $menus[ $i ]['children'] );
					break 2;
				}
			}
		}
		return $menus;
	}

	// -------------------------------------------------------------------------
	// View helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a sub-section H3 heading with an optional info-icon tooltip.
	 *
	 * @param string $title   Heading text (already translated).
	 * @param string $tooltip Optional tooltip text. When empty, only the H3 is rendered.
	 * @return string
	 */
	public static function render_section_heading( string $title, string $tooltip = '' ): string {
		$html = '<h3>' . esc_html( $title );
		if ( $tooltip !== '' ) {
			$html .= ' <span class="wl-section-tooltip" data-tooltip="' . esc_attr( $tooltip ) . '">';
			$html .= '<i class="latepoint-icon latepoint-icon-info"></i>';
			$html .= '</span>';
		}
		$html .= '</h3>';
		return $html;
	}

	// -------------------------------------------------------------------------
	// Settings defaults / autoload
	// -------------------------------------------------------------------------

	/**
	 * @param array $defaults Existing setting defaults.
	 * @return array
	 */
	public static function register_defaults( array $defaults ): array {
		$defaults['white_label_enabled']                = LATEPOINT_VALUE_OFF;
		$defaults['white_label_brand_name']             = '';
		$defaults['white_label_brand_logo']             = '';
		$defaults['white_label_plugin_row_name']        = '';
		$defaults['white_label_plugin_row_uri']         = '';
		$defaults['white_label_plugin_row_description'] = '';
		$defaults['white_label_plugin_row_author']      = '';
		$defaults['white_label_plugin_row_author_uri']  = '';
		$defaults['white_label_hide_menu']              = LATEPOINT_VALUE_OFF;
		return $defaults;
	}

	/**
	 * @param array $keys Existing autoload keys.
	 * @return array
	 */
	public static function register_autoload_keys( array $keys ): array {
		return array_merge(
			$keys,
			[
				'white_label_enabled',
				'white_label_brand_name',
				'white_label_brand_logo',
				'white_label_plugin_row_name',
				'white_label_plugin_row_uri',
				'white_label_plugin_row_description',
				'white_label_plugin_row_author',
				'white_label_plugin_row_author_uri',
				'white_label_hide_menu',
			]
		);
	}
}
