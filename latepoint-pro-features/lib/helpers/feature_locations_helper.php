<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureLocationsHelper {

	public static function load_step_locations( $step_code, $format = 'json' ) {
		if ( $step_code == 'booking__locations' ) {

			$locations_model             = new OslocationModel();
			$show_selected_locations_arr = OsStepsHelper::$restrictions['show_locations'] ? explode( ',', OsStepsHelper::$restrictions['show_locations'] ) : false;

			$connected_ids = OsConnectorHelper::get_connected_object_ids(
				'location_id',
				[
					'agent_id'   => OsStepsHelper::$booking_object->agent_id,
					'service_id' => OsStepsHelper::$booking_object->service_id,
				] 
			);



			// If date/time is selected - filter locations who are available at that time
			if ( OsStepsHelper::$booking_object->start_date && OsStepsHelper::$booking_object->start_time ) {
				$available_location_ids = [];
				$booking_request        = \LatePoint\Misc\BookingRequest::create_from_booking_model( OsStepsHelper::$booking_object );
				foreach ( $connected_ids as $location_id ) {
					$booking_request->location_id = $location_id;
					if ( OsBookingHelper::is_booking_request_available( $booking_request ) ) {
						$available_location_ids[] = $location_id;
					}
				}
				$connected_ids = array_intersect( $available_location_ids, $connected_ids );
			}


			// if "show only specific locations" is selected (restrictions) - remove ids that are not found in connection
			$show_locations_arr = ( ! empty( $show_selected_locations_arr ) && ! empty( $connected_ids ) ) ? array_intersect( $connected_ids, $show_selected_locations_arr ) : $connected_ids;

			if ( ! empty( $show_locations_arr ) ) {
				$locations_model->where_in( 'id', $show_locations_arr );
			}

			$locations = $locations_model->should_be_active()->order_by( 'order_number asc' )->get_results_as_models();

			$locations_controller                             = new OsLocationsController();
			$locations_controller->vars['show_locations_arr'] = $show_locations_arr;
			$locations_controller->vars['locations']          = $locations;
			$locations_controller->vars['booking']            = OsStepsHelper::$booking_object;
			$locations_controller->vars['current_step_code']  = $step_code;
			$locations_controller->set_layout( 'none' );
			$locations_controller->set_return_format( $format );
			$locations_controller->format_render(
				'_step_booking__locations',
				[],
				[
					'step_code'        => $step_code,
					'show_next_btn'    => OsStepsHelper::can_step_show_next_btn( $step_code ),
					'show_prev_btn'    => OsStepsHelper::can_step_show_prev_btn( $step_code ),
					'is_first_step'    => OsStepsHelper::is_first_step( $step_code ),
					'is_last_step'     => OsStepsHelper::is_last_step( $step_code ),
					'is_pre_last_step' => OsStepsHelper::is_pre_last_step( $step_code ),
				]
			);
		}
	}

	public static function add_label_for_step( array $labels ): array {
		$labels['booking__locations'] = __( 'Locations', 'latepoint-pro-features' );
		return $labels;
	}


	public static function add_settings_for_step( array $settings ): array {
		$settings['booking__locations'] = [
			'side_panel_heading'     => 'Location Selections',
			'side_panel_description' => 'Please select a location for your appointment',
			'main_panel_heading'     => 'Locations',
		];
		return $settings;
	}

	public static function add_step_for_locations( array $steps ): array {
		$steps['booking__locations'] = [];
		return $steps;
	}


	public static function add_capabilities_for_controllers( $capabilities ) {
		$capabilities['OsLocationsController']          = [
			'default'    => [ 'location__edit' ],
			'per_action' => [
				'edit_form' => [ 'location__view' ],
				'index'     => [ 'location__view' ],
				'new_form'  => [ 'location__create' ],
				'destroy'   => [ 'location__delete' ],
				'create'    => [ 'location__create' ],
			],
		];
		$capabilities['OsLocationCategoriesController'] = [
			'default'    => [ 'location__edit' ],
			'per_action' => [
				'edit_form' => [ 'location__view' ],
				'index'     => [ 'location__view' ],
				'new_form'  => [ 'location__create' ],
				'destroy'   => [ 'location__delete' ],
				'create'    => [ 'location__create' ],
			],
		];
		return $capabilities;
	}

	public static function add_location_data_vars_to_booking( array $data, OsModel $model ): array {
		if ( is_a( $model, 'OsBookingModel' ) ) {
			$data['location'] = $model->location->get_data_vars();
		}
		return $data;
	}

	public static function add_booking_location_to_webhook( $vars, $booking ) {
		if ( $booking->location_id ) {
			$vars['location'] = $booking->location->name;
		}
		return $vars;
	}

	public static function add_process_event_condition_properties( $properties, $event_type ) {
		switch ( $event_type ) {
			case 'booking_created':
				$properties['booking__location_id'] = __( 'Location', 'latepoint-pro-features' );
				break;
			case 'booking_updated':
				$properties['old__booking__location_id'] = __( 'Previous Location', 'latepoint-pro-features' );
				$properties['booking__location_id']      = __( 'Location', 'latepoint-pro-features' );
				break;
		}
		return $properties;
	}


	public static function add_values_for_process_event_condition_properties( $values, $property ) {
		switch ( $property ) {
			case 'booking__location_id':
				$values = OsFormHelper::model_options_for_multi_select( $property );
				break;
		}
		return $values;
	}

	public static function add_options_for_multi_select( $options, $model_name ) {
		if ( $model_name == 'location' || $model_name == 'OsLocationModel' ) {
			$location  = new OsLocationModel();
			$locations = $location->get_results_as_models();
			if ( $locations ) {
				foreach ( $locations as $location ) {
					$options[] = [
						'value' => $location->id,
						'label' => $location->name,
					];
				}
			}
		}
		return $options;
	}

	public static function add_values_for_condition_property( $values, $property ) {
		if ( $property == 'location' ) {
			$values = OsFormHelper::model_options_for_multi_select( 'location' );
		}
		return $values;
	}


	public static function add_custom_field_condition_properties( $properties ) {
		$properties['location'] = __( 'Location', 'latepoint-pro-features' );
		return $properties;
	}

	public static function add_location_to_booking_row_for_csv( $booking_row, $booking, $params = [] ) {
		$booking_row[] = $booking->location->name;
		return $booking_row;
	}


	public static function add_location_to_bookings_data_for_csv( $bookings_data, $params = [] ) {
		$bookings_data[0][] = __( 'Location', 'latepoint-pro-features' );
		return $bookings_data;
	}



	public static function add_location_categories_setting( string $settings_html, string $selected_step_code ): string {
		if ( $selected_step_code == 'booking__locations' ) {
			$settings_html .= OsFormHelper::toggler_field( 'settings[steps_show_location_categories]', __( 'Show location categories', 'latepoint-pro-features' ), OsSettingsHelper::steps_show_location_categories(), false, false, [ 'sub_label' => __( 'If turned on, locations will be displayed in categories', 'latepoint-pro-features' ) ] );
		}
		return $settings_html;
	}
}
