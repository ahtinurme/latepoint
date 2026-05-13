<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsRemindersController' ) ) :


	class OsRemindersController extends OsController {

		public function __construct() {
			parent::__construct();

			$this->action_access['public'] = array_merge( $this->action_access['public'], [ 'process_reminders' ] );

			$this->views_folder = plugin_dir_path( __FILE__ ) . '../views/reminders/';

			$this->vars['page_header']   = __( 'Reminders', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Reminders', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'reminders', 'index' ) ),
			);
		}



		public function process_reminders() {
			OsProcessJobsHelper::process_scheduled_jobs();
		}
	}
endif;
