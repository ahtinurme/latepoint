<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}


if (!class_exists('OsMailchimpController')) :


	class OsMailchimpController extends OsController {


		function __construct() {
			parent::__construct();
			$this->views_folder = plugin_dir_path(__FILE__) . '../views/mailchimp/';
		}

	}


endif;
