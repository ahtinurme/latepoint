<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsDebugController' ) ) :


	class OsDebugController extends OsController {
	

		public function __construct() {
			parent::__construct();

			$this->views_folder        = plugin_dir_path( __FILE__ ) . '../views/debug/';
			$this->vars['page_header'] = __( 'LatePoint Status', 'latepoint-pro-features' );
		}

		public function reset_plugin_db_version() {
			$this->check_nonce( 'latepoint_reset_plugin_db_version' );

			delete_option( 'latepoint_db_version' );
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'DB Version was reset', 'latepoint-pro-features' ),
					) 
				);
			}
		}

		public function reset_addon_db_version() {
			$this->check_nonce( 'latepoint_reset_addon_db_version' );

			delete_option( $this->params['plugin_name'] . '_addon_db_version' );
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'DB Version was reset', 'latepoint-pro-features' ),
					) 
				);
			}
		}

		public function status() {

			$this->vars['page_header'] = OsMenuHelper::get_menu_items_by_id( 'settings' );

			$addons               = OsUpdatesHelper::get_list_of_addons();
			$this->vars['addons'] = $addons;

			$this->set_layout( 'admin' );
			$this->format_render( __FUNCTION__ );
		}
	}

endif;
