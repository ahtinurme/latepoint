<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsRecurringBookingsController' ) ) :


	class OsRecurringBookingsController extends OsController {


		public function __construct() {
			parent::__construct();

			$this->views_folder            = plugin_dir_path( __FILE__ ) . '../views/recurring_bookings/';
			$this->action_access['public'] = array_merge(
				$this->action_access['public'],
				[
					'load_datepicker_month',
					'pick_end_date_on_calendar',
					'recurring_bookings_preview',
					'pick_datetime_on_calendar',
					'reload_recurrence_rules',
				] 
			);
		}


		public function pick_end_date_on_calendar() {

			try {
				$target_datetime = new OsWpDateTime( sanitize_text_field( $this->params['preselected_date'] ?? '+2 months' ) );
			} catch ( Exception $e ) {
				$target_datetime = new OsWpDateTime( '+2 months' );
			}

			$this->vars['target_datetime'] = $target_datetime;

			$this->format_render( __FUNCTION__ );
		}

		public function pick_datetime_on_calendar() {
			OsStepsHelper::set_required_objects( $this->params );

			try {
				$target_datetime = new OsWpDateTime( sanitize_text_field( $this->params['preselected_datetime_utc'] ?? 'now' ), new DateTimeZone( 'UTC' ) );
				$target_datetime->setTimezone( new DateTimeZone( sanitize_text_field( $this->params['timezone_name'] ) ) );
			} catch ( Exception $e ) {
				$target_datetime = new OsWpDateTime( 'now' );
			}

			$this->vars['target_datetime'] = $target_datetime;
			$this->vars['booking']         = OsStepsHelper::$booking_object;

			$this->format_render( __FUNCTION__ );
		}

		public function load_datepicker_month() {
			try {
				$target_datetime = new OsWpDateTime( sanitize_text_field( $this->params['target_date_string'] ?? 'now' ) );
			} catch ( Exception $e ) {
				$target_datetime = new OsWpDateTime( 'now' );
			}

			$this->vars['target_datetime'] = $target_datetime;

			$this->format_render( __FUNCTION__ );
		}

		public function recurrence_rules_params(): array {
			$allowed_params = array(
				'repeat_unit',
				'repeat_interval',
				'repeat_on_weekdays',
				'repeat_end_operator',
				'repeat_end_date',
				'repeat_end_counter',
				'changed',
			);
			$settings       = [];
			foreach ( $allowed_params as $param ) {
				if ( ! empty( $this->params['recurrence']['rules'][ $param ] ) ) {
					$settings[ $param ] = sanitize_text_field( $this->params['recurrence']['rules'][ $param ] );
				}
			}
			return $settings;
		}

		public function reload_recurrence_rules() {
			OsStepsHelper::set_required_objects( $this->params );

			$recurrence_rules = $this->recurrence_rules_params();

			$recurrence_rules['max_repeat_end_counter'] = OsStepsHelper::$booking_object->service->get_meta_by_key( 'maximum_number_of_recurring_bookings', 20 );

			$status        = LATEPOINT_STATUS_SUCCESS;
			$response_html = OsFeatureRecurringBookingsHelper::generate_recurrence_rules_for_booking_form( OsStepsHelper::$booking_object->get_start_datetime_for_customer(), $recurrence_rules, OsStepsHelper::$booking_object );


			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					[
						'status'  => $status,
						'message' => $response_html,
					] 
				);
			}
		}


		public function recurring_bookings_preview() {
			OsStepsHelper::set_required_objects( $this->params );
			$recurrence = $this->params['recurrence'];

			$recurrence_rules = $recurrence['rules'] ?? [];
			if ( $recurrence['rules']['changed'] == 'no' ) {
				// reuse selections from booking preview
				$recurrence_overrides = $recurrence['overrides'];
			} else {
				$recurrence_overrides = [];
			}


			try {
				$customer_timezone = new DateTimeZone( sanitize_text_field( $this->params['timezone_name'] ) ) ?? OsTimeHelper::get_timezone_from_session();
			} catch ( Exception $e ) {
				$customer_timezone = OsTimeHelper::get_wp_timezone();
			}

			$recurring_bookings_data_and_errors = OsFeatureRecurringBookingsHelper::generate_recurring_bookings_data( OsStepsHelper::$booking_object, $recurrence_rules, $recurrence_overrides, $customer_timezone );


			$selection_form_fields_html = '';
			foreach ( $recurring_bookings_data_and_errors['bookings_data'] as $original_stamp => $recurring_bookings_datum ) {
				$selection_form_fields_html .= OsFormHelper::hidden_field( 'recurrence[overrides][' . $original_stamp . '][custom_day]', $recurring_bookings_datum['custom_day'] ?? '', [ 'class' => 'recurring_booking_custom_day' ] );
				$selection_form_fields_html .= OsFormHelper::hidden_field( 'recurrence[overrides][' . $original_stamp . '][custom_minutes]', $recurring_bookings_datum['custom_minutes'] ?? '', [ 'class' => 'recurring_booking_custom_minutes' ] );
				$selection_form_fields_html .= OsFormHelper::hidden_field( 'recurrence[overrides][' . $original_stamp . '][unchecked]', $recurring_bookings_datum['unchecked'] ?? 'no' );
			}

			$non_bookable_count = 0;
			foreach ( $recurring_bookings_data_and_errors['bookings_data'] as $original_stamp => $recurring_bookings_datum ) {
				if ( ! $recurring_bookings_datum['is_bookable'] ) {
					$non_bookable_count++;
				}
			}

			$this->vars['non_bookable_count']        = $non_bookable_count;
			$this->vars['recurring_bookings_data']   = $recurring_bookings_data_and_errors['bookings_data'];
			$this->vars['recurring_bookings_errors'] = $recurring_bookings_data_and_errors['errors'];
			$this->vars['price_info']                = OsFeatureRecurringBookingsHelper::generate_price_info_for_recurring_set( $recurring_bookings_data_and_errors['bookings_data'], OsStepsHelper::is_bundle_scheduling() );
			$this->vars['bookings_info']             = OsFeatureRecurringBookingsHelper::generate_bookings_info_for_recurring_set( $recurring_bookings_data_and_errors['bookings_data'] );

			if ( $this->get_return_format() == 'json' ) {
				$preview_html = $this->render( $this->views_folder . __FUNCTION__, 'none' );
				$this->send_json(
					array(
						'status'        => LATEPOINT_STATUS_SUCCESS,
						'preview'       => $preview_html,
						'fields'        => $selection_form_fields_html,
						'price_info'    => $this->vars['price_info'],
						'bookings_info' => $this->vars['bookings_info'],
					) 
				);
			}
		}
	}

endif;
