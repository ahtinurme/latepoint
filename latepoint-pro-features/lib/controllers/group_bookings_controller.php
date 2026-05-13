<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsGroupBookingsController' ) ) :


	class OsGroupBookingsController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder = plugin_dir_path( __FILE__ ) . '../views/group_bookings/';
		}
	}

endif;
