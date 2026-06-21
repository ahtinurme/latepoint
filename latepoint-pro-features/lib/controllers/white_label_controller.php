<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsWhiteLabelController' ) ) :

	class OsWhiteLabelController extends OsSettingsController {

		public function __construct() {
			parent::__construct();

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/white_label/';
			$this->vars['breadcrumbs'][] = [
				'label' => __( 'White Label', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( [ 'white_label', 'settings' ] ),
			];
		}

		public function settings() {

			if ( ! OsSettingsHelper::is_on( 'white_label_hide_menu' ) ) {
				$this->format_render( __FUNCTION__ );
			}
		}

		/**
		 * Clear all white_label_* settings, resetting to defaults.
		 */
		public function reset() {
			$this->check_nonce( 'update_settings' );

			$white_label_keys = [
				'white_label_enabled',
				'white_label_brand_name',
				'white_label_brand_logo',
				'white_label_plugin_row_name',
				'white_label_plugin_row_uri',
				'white_label_plugin_row_description',
				'white_label_plugin_row_author',
				'white_label_plugin_row_author_uri',
				'white_label_hide_menu',
			];

			foreach ( $white_label_keys as $key ) {
				OsSettingsHelper::remove_setting_by_name( $key );
			}

			$this->send_json(
				[
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => __( 'White Label settings reset to defaults.', 'latepoint-pro-features' ),
				]
			);
		}
	}

endif;
