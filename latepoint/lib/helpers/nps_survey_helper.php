<?php
/**
 * LatePoint NPS Survey Helper.
 *
 * @since 5.2.8
 * @package LatePoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OsNpsSurveyHelper' ) ) {
	/**
	 * OsNpsSurveyHelper - NPS Survey Version Checker and Loader
	 */
	class OsNpsSurveyHelper {

		/**
		 * Instance of this class.
		 *
		 * @var OsNpsSurveyHelper
		 */
		private static $instance = null;

		/**
		 * Array of allowed screens where the NPS survey should be displayed.
		 * This ensures that the NPS survey is only displayed on LatePoint pages.
		 *
		 * @var array
		 */
		private static $allowed_screens = [
			'toplevel_page_latepoint',
		];

		/**
		 * Get instance.
		 *
		 * @return OsNpsSurveyHelper
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			$this->version_check();
			add_action( 'init', [ $this, 'load' ], 999 );

			add_action( 'admin_footer', [ $this, 'show_nps_notice' ], 999 );
			add_filter( 'nps_survey_post_data', [ $this, 'update_nps_survey_post_data' ] );
		}

		/**
		 * Version Check
		 *
		 * @return void
		 */
		public function version_check() {
			$file = realpath( LATEPOINT_ABSPATH . '/lib/kit/nps-survey/version.json' );

			// Is file exist?
			if ( is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$file_data = json_decode( file_get_contents( $file ), true );
				global $nps_survey_version, $nps_survey_init;
				$path    = realpath( LATEPOINT_ABSPATH . '/lib/kit/nps-survey/nps-survey.php' );
				$version = isset( $file_data['nps-survey'] ) ? $file_data['nps-survey'] : 0;

				if ( null === $nps_survey_version ) {
					$nps_survey_version = '1.0.0';
				}

				// Compare versions.
				if ( version_compare( $version, $nps_survey_version, '>=' ) ) {
					$nps_survey_version = $version;
					$nps_survey_init    = $path;
				}
			}
		}

		/**
		 * Load latest plugin
		 *
		 * @return void
		 */
		public function load() {
			global $nps_survey_version, $nps_survey_init;

			if ( is_file( realpath( $nps_survey_init ) ) ) {
				include_once realpath( $nps_survey_init );
			}
		}

		/**
		 * Count the number of bookings and determine if NPS survey should be shown.
		 *
		 * @return bool
		 */
		public function maybe_display_nps_survey() {
			// Check if user has manage_options capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			// Get total bookings count.
			$bookings_model = new OsBookingModel();
			$bookings_count = $bookings_model->count();

			// Get total customers count.
			$customers_model = new OsCustomerModel();
			$customers_count = $customers_model->count();

			// Show the NPS survey if there are at least 5 bookings or 3 customers.
			if ( $bookings_count >= 3 || $customers_count >= 3 ) {
				return true;
			}

			return false;
		}

		/**
		 * Render NPS Survey
		 *
		 * @return void
		 */
		public function show_nps_notice() {
			// Ensure the Nps_Survey class exists before proceeding.
			if ( ! class_exists( 'Nps_Survey' ) ) {
				return;
			}

			// Get current screen.
			$screen = get_current_screen();
			if ( ! $screen || ! in_array( $screen->id, self::$allowed_screens, true ) ) {
				return;
			}

			// Check if the constant WEEK_IN_SECONDS is already defined.
			if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
				define( 'WEEK_IN_SECONDS', 604800 );
			}

			// Display the NPS survey.
			Nps_Survey::show_nps_notice(
				'nps-survey-latepoint',
				[
					'show_if'          => $this->maybe_display_nps_survey(),
					'dismiss_timespan' => 2 * WEEK_IN_SECONDS,
					'display_after'    => 0,
					'plugin_slug'      => 'latepoint',
					'show_on_screens'  => self::$allowed_screens,
					'message'          => [
						'logo'                        => esc_url( LATEPOINT_IMAGES_URL . 'logo.png' ),
						'plugin_name'                 => __( 'Quick Question!', 'latepoint' ),
						'nps_rating_message'          => __( 'How would you rate LatePoint? Love it, hate it, or somewhere in between? Your honest answer helps us understand how we\'re doing.', 'latepoint' ),
						'feedback_title'              => __( 'Thanks a lot for your feedback!', 'latepoint' ),
						'feedback_content'            => __( 'Thanks for being part of the LatePoint community! Got feedback or suggestions? We\'d love to hear it.', 'latepoint' ),
						'plugin_rating_link'          => esc_url( 'https://wordpress.org/support/plugin/latepoint/reviews/#new-post' ),
						'plugin_rating_title'         => __( 'Thank you for your feedback', 'latepoint' ),
						'plugin_rating_content'       => __( 'We value your input. How can we improve your experience?', 'latepoint' ),
						'plugin_rating_button_string' => __( 'Rate LatePoint', 'latepoint' ),
						'rating_min_label'            => __( 'Hate it!', 'latepoint' ),
						'rating_max_label'            => __( 'Love it!', 'latepoint' ),
					],
					'privacy_policy'   => [
						'url' => 'https://latepoint.com/privacy-policy',
					],
				]
			);
		}

		/**
		 * Update the NPS survey post data.
		 * Add LatePoint plugin version to the NPS survey post data.
		 *
		 * @param array $post_data NPS survey post data.
		 * @return array
		 */
		public function update_nps_survey_post_data( $post_data ) {
			if ( isset( $post_data['plugin_slug'] ) && 'latepoint' === $post_data['plugin_slug'] ) {
				$post_data['plugin_version'] = LATEPOINT_VERSION;
			}

			return $post_data;
		}
	}
}
