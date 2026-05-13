<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsCustomFieldsHelper {


	public function __construct() {
	}

	public static function allowed_fields() {
		$allowed_params = array(
			'label',
			'placeholder',
			'type',
			'width',
			'options',
			'required',
			'id',
		);

		return $allowed_params;
	}

	public static function get_google_places_api_url() {
		return 'https://maps.googleapis.com/maps/api/js?key=' . OsSettingsHelper::get_settings_value( 'google_places_api_key' ) . '&libraries=places&callback=Function.prototype';
	}

	public static function prepare_to_save( $array_to_filter ) {
		// !!TODO
		return $array_to_filter;
	}

	public static function get_custom_field_types() {
		$custom_field_types = [
			'text'                        => __( 'Text Field', 'latepoint-pro-features' ),
			'phone_number'                => __( 'Phone Number Field', 'latepoint-pro-features' ),
			'number'                      => __( 'Number Field', 'latepoint-pro-features' ),
			'hidden'                      => __( 'Hidden Field', 'latepoint-pro-features' ),
			'select'                      => __( 'Select Box', 'latepoint-pro-features' ),
			'multiselect'                 => __( 'Multiple Selectable Options', 'latepoint-pro-features' ),
			'textarea'                    => __( 'Text Area Field', 'latepoint-pro-features' ),
			'checkbox'                    => __( 'Checkbox', 'latepoint-pro-features' ),
			'google_address_autocomplete' => __( 'Google Address Autocomplete', 'latepoint-pro-features' ),
			'file_upload'                 => __( 'File Upload', 'latepoint-pro-features' ),
		];
		$custom_field_types = apply_filters( 'latepoint_custom_field_types', $custom_field_types );

		return $custom_field_types;
	}

	public static function get_custom_field_types_with_default_value() {
		/**
		 * Get the list of custom field types that can have a default value
		 *
		 * @param {array} $types The default list being filtered
		 * @returns {array} The filtered list of custom field types that can have a default value
		 *
		 * @since 1.2.3
		 * @hook latepoint_custom_field_types_with_default_value
		 *
		 */
		return apply_filters( 'latepoint_custom_field_types_with_default_value', [ 'text', 'phone_number', 'number', 'hidden', 'select', 'multiselect', 'textarea' ] );
	}

	/**
	 * Get the HTML for custom field default value, based on custom field type
	 *
	 * @param string $fieldType One of <code>OsCustomFieldsHelper::get_custom_field_types()</code>
	 * @param string $fieldName The name (custom field id included) associated with this default value.
	 * @param string $existingValue The pre-existing default value, if any.
	 *
	 * @return string
	 */
	public static function get_custom_field_default_value_html( string $fieldType, string $fieldName, string $existingValue = '' ): string {
		$atts = [
			'theme'       => 'bordered',
			'placeholder' => __( 'Enter Field Default Value', 'latepoint-pro-features' ),
		];

		switch ( $fieldType ) {
			case 'phone_number':
				$html = OsFormHelper::phone_number_field( $fieldName, false, $existingValue, $atts );
				break;
			case 'number':
				$html = OsFormHelper::number_field( $fieldName, false, $existingValue, null, null, $atts );
				break;
			case 'textarea':
				$html = OsFormHelper::textarea_field( $fieldName, false, $existingValue, $atts );
				break;
			case 'multiselect':
				$value         = json_decode( $existingValue );
				$existingValue = is_array( $value ) ? implode( "\n", $value ) : $existingValue;
				$html          = OsFormHelper::textarea_field( $fieldName, false, $existingValue, $atts );
				break;
			default:
				$html = OsFormHelper::text_field( $fieldName, false, $existingValue, $atts );

		}

		/**
		 * Get the HTML for custom field default value, based on custom field type
		 *
		 * @param {string} $html The html string being filtered
		 * @param {string} $fieldType One of <code>OsCustomFieldsHelper::get_custom_field_types()</code>.
		 * @param {string} $fieldName The name (custom field id included) associated with this default value.
		 * @param {string} $existingValue The pre-existing default value, if any.
		 * @returns {string} The filtered html string
		 *
		 * @since 1.2.3
		 * @hook latepoint_custom_field_default_value_html
		 *
		 */
		return apply_filters( 'latepoint_custom_field_default_value_html', $html, $fieldType, $fieldName, $existingValue );
	}

	public static function load_countries_list( $include_empty = true ) {
		$country_codes = [
			''   => __( 'No Restrictions', 'latepoint-pro-features' ),
			'au' => 'Australia',
			'at' => 'Austria',
			'be' => 'Belgium',
			'br' => 'Brazil',
			'ca' => 'Canada',
			'dk' => 'Denmark',
			'ee' => 'Estonia',
			'fi' => 'Finland',
			'fr' => 'France',
			'de' => 'Germany',
			'gr' => 'Greece',
			'hk' => 'Hong Kong',
			'in' => 'India',
			'ie' => 'Ireland',
			'it' => 'Italy',
			'jp' => 'Japan',
			'lv' => 'Latvia',
			'lt' => 'Lithuania',
			'lu' => 'Luxembourg',
			'my' => 'Malaysia',
			'mx' => 'Mexico',
			'nl' => 'Netherlands',
			'nz' => 'New Zealand',
			'no' => 'Norway',
			'pl' => 'Poland',
			'pt' => 'Portugal',
			'ro' => 'Romania',
			'sg' => 'Singapore',
			'sk' => 'Slovakia',
			'si' => 'Slovenia',
			'es' => 'Spain',
			'se' => 'Sweden',
			'ch' => 'Switzerland',
			'ae' => 'United Arab Emirates',
			'gb' => 'United Kingdom',
			'us' => 'United States',
		];

		return $country_codes;
	}

	/**
	 * @param $custom_fields
	 * @param OsModel $model
	 * @param $fields_for //could be 'customer' or 'booking'
	 *
	 * @return string
	 */
	public static function output_custom_fields_for_model( $custom_fields, OsModel $model, $fields_for = 'customer', $name_prefix = '', array $settings = [] ): string {
		$html = '';
		if ( empty( $custom_fields ) ) {
			return $html;
		}
		$name_prefix = empty( $name_prefix ) ? $fields_for : $name_prefix;
		foreach ( $custom_fields as $custom_field ) {
			if ( OsAuthHelper::get_highest_current_user_type() == 'customer' && $custom_field['visibility'] != 'public' ) {
				continue;
			}
			$required_class = ( $custom_field['required'] == LATEPOINT_VALUE_ON ) ? 'required' : '';
			$validate       = ( $custom_field['required'] == LATEPOINT_VALUE_ON ) ? [ 'presence' ] : [];
			$name           = $name_prefix . '[custom_fields][' . $custom_field['id'] . ']';
			$label          = $custom_field['label'];
			$meta_value     = $model->get_meta_by_key( $custom_field['id'] );
			if ( false !== $meta_value ) {
				$value = $meta_value;
			} elseif ( isset( $model->custom_fields ) && array_key_exists( $custom_field['id'], $model->custom_fields ) ) {
				$value = $model->custom_fields[ $custom_field['id'] ];
			} else {
				$value = $custom_field['value'] ?? '';
			}
			$placeholder = ! empty( $custom_field['placeholder'] ) ? $custom_field['placeholder'] : '';
			$width       = $custom_field['width'];
			switch ( $custom_field['type'] ) {
				case 'text':
					$html .= OsFormHelper::text_field(
						$name,
						$label,
						$value,
						[
							'class'       => $required_class,
							'placeholder' => $placeholder,
							'validate'    => $validate,
							'theme'       => 'simple',
						],
						[ 'class' => $width ] 
					);
					break;
				case 'phone_number':
					$html .= OsFormHelper::phone_number_field(
						$name,
						$label,
						$value,
						[
							'class'       => $required_class,
							'placeholder' => $placeholder,
							'validate'    => $validate,
							'theme'       => 'simple',
						],
						[ 'class' => $width ] 
					);
					break;
				case 'number':
					// TODO: Implement min/max in CF settings
					$html .= OsFormHelper::number_field(
						$name,
						$label,
						$value,
						null,
						null,
						[
							'class'       => $required_class,
							'placeholder' => $placeholder,
							'validate'    => $validate,
							'theme'       => 'simple',
						],
						[ 'class' => $width ] 
					);
					break;
				case 'hidden':
					$html .= OsFormHelper::hidden_field( $name, $value );
					break;
				case 'textarea':
					$html .= OsFormHelper::textarea_field(
						$name,
						$label,
						$value,
						[
							'class'       => $required_class,
							'placeholder' => $placeholder,
							'validate'    => $validate,
							'theme'       => 'simple',
						],
						[ 'class' => $width ] 
					);
					break;
				case 'select':
					$html .= OsFormHelper::select_field(
						$name,
						$label,
						OsFormHelper::generate_select_options_from_custom_field( $custom_field['options'] ),
						$value,
						[
							'class'       => $required_class,
							'placeholder' => $placeholder,
							'validate'    => $validate,
						],
						[ 'class' => $width ] 
					);
					break;
				case 'multiselect':
					$options         = OsFormHelper::generate_select_options_from_custom_field( $custom_field['options'] );
					$decoded_options = json_decode( $value );
					$default_options = is_array( $decoded_options ) ? $decoded_options : [ $value ];

					$html .= OsFormHelper::multi_checkbox_field(
						$name,
						$label,
						$options,
						$default_options,
						[
							'class' => $required_class,
						],
						[ 'class' => $width ] 
					);
					break;
				case 'checkbox':
					$html .= OsFormHelper::checkbox_field(
						$name,
						$label,
						'on',
						( $value == 'on' ),
						[
							'class'    => $required_class,
							'validate' => $validate,
						],
						[ 'class' => $width ] 
					);
					break;
				case 'google_address_autocomplete':
					$html .= OsFormHelper::text_field(
						$name,
						$label,
						$value,
						[
							'class'       => $required_class . ' latepoint-google-places-autocomplete',
							'placeholder' => $placeholder,
							'validate'    => $validate,
							'theme'       => 'simple',
						],
						[ 'class' => $width ] 
					);
					break;
				case 'file_upload':
					$atts = [ 'class' => $required_class ];
					if ( $value ) {
						$atts['data-route-name'] = OsRouterHelper::build_route_name( 'custom_fields', 'delete_custom_field_for_' . $fields_for );
						$atts['data-params']     = OsUtilHelper::build_os_params(
							[
								'model_id'        => $model->id,
								'custom_field_id' => $custom_field['id'],
							] 
						);
					}
					if ( $model instanceof OsBookingModel ) {
						// we are overriding a name for file fields as they are stored in a separate array $_FILES and we don't need to have "order_item_id" info there, just the booking id
						// first check if custom one is passed, it's used on frontend stepped booking form, where ALL file fields are uploaded for one booking at a time and don't need separate IDs
						if ( ! empty( $settings['custom_form_id_for_file_fields'] ) ) {
							$override_name = 'booking_files[' . $settings['custom_form_id_for_file_fields'] . '][custom_fields][' . $custom_field['id'] . ']';
						} else {
							$override_name = 'booking_files[' . $model->get_form_id() . '][custom_fields][' . $custom_field['id'] . ']';
						}
						$html .= OsFormHelper::file_upload_field( $override_name, $label, $value, $atts, [ 'class' => $width ] );
					} else {
						$html .= OsFormHelper::file_upload_field( $name, $label, $value, $atts, [ 'class' => $width ] );
					}
					break;
			}
			/**
			 * Get the HTML output for a custom field
			 *
			 * @param {string} $html The html string being filtered
			 * @param {string} $custom_field The custom field for which the html output is being filtered.
			 * @param {OsModel} $model The model (<code>OsCustomerModel</code>, <code>OsBookingModel</code>) for which the html output is being filtered.
			 * @param {string} $fields_for The target ('customer', 'booking') for which the html output is being filtered.
			 * @returns {string} The filtered html string
			 *
			 * @since 1.0.0
			 * @hook latepoint_custom_field_output_for_model
			 *
			 */
			$html = apply_filters( 'latepoint_custom_fields_output_for_model', $html, $custom_field, $model, $fields_for );
		}

		return $html;
	}

	public static function has_validation_errors( $custom_field ) {
		$errors = [];
		if ( empty( $custom_field['label'] ) ) {
			$errors[] = __( 'Field Label cannot be empty', 'latepoint-pro-features' );
		}
		if ( empty( $custom_field['type'] ) ) {
			$errors[] = __( 'Type cannot be empty', 'latepoint-pro-features' );
		} else {
			if ( $custom_field['type'] == 'select' ) {
				if ( empty( $custom_field['options'] ) ) {
					$errors[] = __( 'Options for select box cannot be blank', 'latepoint-pro-features' );
				}
			}
		}
		if ( empty( $errors ) ) {
			return false;
		} else {
			return $errors;
		}
	}

	public static function validate_fields( $fields_data, $fields_rules, $fields_for, $files_form_id ): array {
		$errors = [];
		foreach ( $fields_rules as $custom_field ) {
			if ( $custom_field['required'] != 'on' ) {
				continue;
			}
			switch ( $custom_field['type'] ) {
				case 'checkbox':
					// checkbox has different "required" validation
					if ( empty( $fields_data[ $custom_field['id'] ] ) || $fields_data[ $custom_field['id'] ] == 'off' ) {
						$error_message = sprintf( __( '%s field must be checked', 'latepoint-pro-features' ), $custom_field['label'] );
						$errors[]      = [
							'type'    => 'validation',
							'message' => $error_message,
						];
					}
					break;
				case 'file_upload':
					// file upload fields are only stored in the booking/customer object if they were already saved before (we save the url)
					// so if we don't see them in a model - try get them from file params
					if ( empty( $fields_data[ $custom_field['id'] ] ) && empty( OsFeatureCustomFieldsHelper::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $files_form_id ) ) ) {
						$error_message = sprintf( __( '%s must be attached', 'latepoint-pro-features' ), $custom_field['label'] );
						$errors[]      = [
							'type'    => 'validation',
							'message' => $error_message,
						];
					} else {
						// TODO check if extension is allowed
						if ( ! empty( OsFeatureCustomFieldsHelper::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $files_form_id ) ) ) {
							for ( $i = 0; $i < count( OsFeatureCustomFieldsHelper::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $files_form_id ) ); $i++ ) {
								$tmp_name    = OsFeatureCustomFieldsHelper::extract_file_properties_by_custom_field_id( 'tmp_name', $fields_for, $custom_field['id'], $files_form_id )[ $i ];
								$name        = OsFeatureCustomFieldsHelper::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $files_form_id )[ $i ];
								$wp_filetype = wp_check_filetype_and_ext( $tmp_name, $name, false );
								if ( empty( $wp_filetype['type'] ) || empty( $wp_filetype['ext'] ) ) {
									$error_message = sprintf( __( '%s file type is not supported.', 'latepoint-pro-features' ), $custom_field['label'] );
									$errors[]      = [
										'type'    => 'validation',
										'message' => $error_message,
									];
								}
							}
						}
					}
					break;
				default:
					if ( empty( $fields_data[ $custom_field['id'] ] ) ) {
						$error_message = sprintf( __( '%s cannot be blank', 'latepoint-pro-features' ), $custom_field['label'] );
						$errors[]      = [
							'type'    => 'validation',
							'message' => $error_message,
						];
					}
					break;
			}
		}

		return $errors;
	}

	public static function save( $custom_field, $fields_for = 'customer' ) {
		$custom_fields = self::get_custom_fields_arr( $fields_for, 'all' );
		if ( empty( $custom_field['id'] ) ) {
			$custom_field['id'] = self::generate_custom_field_id( $fields_for );
		}

		if ( $custom_field['type'] == 'multiselect' ) {
			$value                 = OsFormHelper::generate_select_options_from_custom_field( $custom_field['value'] );
			$custom_field['value'] = wp_json_encode( $value );
		}

		$custom_fields[ $custom_field['id'] ] = $custom_field;

		return self::save_custom_fields_arr( $custom_fields, $fields_for );
	}

	public static function delete( $custom_field_id, $fields_for = 'customer' ) {
		if ( isset( $custom_field_id ) && ! empty( $custom_field_id ) ) {
			$custom_fields = self::get_custom_fields_arr( $fields_for, 'all' );
			unset( $custom_fields[ $custom_field_id ] );

			return self::save_custom_fields_arr( $custom_fields, $fields_for );
		} else {
			return false;
		}
	}

	public static function get_checkbox_value( $value ) {
		return ( $value == 'on' ) ? __( 'Yes', 'latepoint-pro-features' ) : __( 'No', 'latepoint-pro-features' );
	}

	public static function get_multiselect_value( $value ) {
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? implode( ', ', $decoded ) : $value;
	}

	public static function generate_custom_field_id() {
		return 'cf_' . OsUtilHelper::random_text( 'alnum', 8 );
	}

	public static function generate_custom_field_condition_id() {
		return 'cfc_' . OsUtilHelper::random_text( 'alnum', 8 );
	}

	public static function generate_condition_form( $custom_field_id, $condition_id = false, $condition_data = false ) {
		$properties = [
			'service' => __( 'Service', 'latepoint-pro-features' ),
			'agent'   => __( 'Agent', 'latepoint-pro-features' ),
		];
		$properties = apply_filters( 'latepoint_custom_field_condition_properties', $properties );
		// new condition
		if ( ! $condition_id ) {
			$condition_id   = self::generate_custom_field_condition_id();
			$condition_data = [
				'property' => 'service',
				'operator' => false,
				'value'    => false,
			];
		}
		$html = '<div class="cf-condition" data-condition-id="' . $condition_id . '">' .
				'<button class="cf-remove-condition"><i class="latepoint-icon latepoint-icon-cross"></i></button>' .
				OsFormHelper::select_field(
					'custom_fields[' . $custom_field_id . '][conditions][' . $condition_id . '][property]',
					false,
					$properties,
					$condition_data['property'],
					[
						'class'      => 'custom-field-condition-property',
						'data-route' => OsRouterHelper::build_route_name( 'custom_fields', 'available_values_for_condition_property' ),
					],
					[ 'class' => 'custom-field-condition-property-w' ] 
				) .
				OsFormHelper::select_field(
					'custom_fields[' . $custom_field_id . '][conditions][' . $condition_id . '][operator]',
					false,
					array(
						'equal'     => __( 'is equal to', 'latepoint-pro-features' ),
						'not_equal' => __( 'is not equal to', 'latepoint-pro-features' ),
					),
					$condition_data['operator'],
					[],
					[ 'class' => 'custom-field-condition-operator-w' ] 
				) .
				OsFormHelper::multi_select_field( 'custom_fields[' . $custom_field_id . '][conditions][' . $condition_id . '][value]', false, OsFormHelper::model_options_for_multi_select( $condition_data['property'] ), $condition_data['value'] ? explode( ',', $condition_data['value'] ) : [], [], [ 'class' => 'custom-field-condition-values-w' ] ) .
				'<div class="custom-field-condition-property-w" 
                      data-os-action="' . OsRouterHelper::build_route_name( 'custom_fields', 'new_condition' ) . '" 
                      data-os-params="' . OsUtilHelper::build_os_params( [ 'custom_field_id' => $custom_field_id ] ) . '" 
                      data-os-pass-response="yes" 
                      data-os-pass-this="yes" 
                      data-os-before-after="none" 
                      data-os-after-call="latepointCustomFieldsAdminAddon.add_custom_field_condition"><button class="latepoint-btn-outline latepoint-btn"><i class="latepoint-icon latepoint-icon-plus2"></i><span>' . __( 'AND', 'latepoint-pro-features' ) . '</span></button></div>' .
				'</div>';

		return $html;
	}

	/**
	 * Get an array of custom fields that match the supplied conditions
	 *
	 * @param string $fields_for Can be 'customer' or 'booking'
	 * @param string $visibilityLevel Can be 'agent', 'customer', or 'all'
	 * @param OsBookingModel|false $booking_object Get only custom fields attached to a particular booking. Supply <code>false</code> to ignore such condition(s)
	 * @param array $atts Extra attributes by which to filter resultant custom fields (ex. type, hide_on_summary, etc.)
	 *
	 * @return array Array of custom fields that match the supplied conditions
	 */
	public static function get_custom_fields_arr( $fields_for = 'customer', $visibilityLevel = 'customer', $booking_object = false, $atts = [] ) {
		switch ( $visibilityLevel ) {
			case 'all':
				$visibility = [ 'public', 'admin_agent', 'hidden' ];
				break;
			case 'agent':
				$visibility = [ 'public', 'admin_agent' ];
				break;
			case 'customer':
			default:
				$visibility = [ 'public' ];
				break;
		}
		$custom_fields = OsSettingsHelper::get_settings_value( 'custom_fields_for_' . $fields_for, false );
		if ( $custom_fields ) {
			$custom_fields_arr = json_decode( $custom_fields, true );
			$visible_fields    = [];
			if ( $custom_fields_arr && is_array( $visibility ) ) {
				foreach ( $custom_fields_arr as $id => $custom_field ) {
					if ( ! isset( $custom_field['conditions'] ) ) {
						$custom_field['conditions'] = [];
					}
					if ( ! isset( $custom_field['hide_on_summary'] ) ) {
						$custom_field['hide_on_summary'] = 'off';
					}
					if ( ! isset( $custom_field['conditional'] ) ) {
						$custom_field['conditional'] = 'off';
					}
					if ( $booking_object && ! empty( $booking_object->custom_fields ) && ! empty( $booking_object->custom_fields[ $custom_field['id'] ] ) ) {
						$custom_field['value'] = $booking_object->custom_fields[ $custom_field['id'] ];
					}
					// if either booking object is not set, or condition when to show is satisfied - show it
					if ( ! $booking_object || ( self::is_condition_satisfied( $custom_field, $booking_object ) ) && ( ! isset( $custom_field['visibility'] ) || in_array( $custom_field['visibility'], $visibility ) ) ) {
						$visible_fields[ $id ] = $custom_field;
					}
				}
			}
			if ( ! empty( $atts ) ) {
				return array_filter(
					$visible_fields,
					function ( $visible_field ) use ( $atts ) {
						foreach ( $atts as $key => $value ) {
							if ( isset( $visible_field[ $key ] ) && $visible_field[ $key ] != $value ) {
								return false;
							}
						}

						return true;
					} 
				);
			}

			return $visible_fields;
		} else {
			return [];
		}
	}

	/**
	 * Get an array of custom fields that match the supplied conditions, formatted for <code>OsFormHelper::select_field()</code>'s <em>$options</em> argument
	 *
	 * @param string $fields_for Can be 'customer' or 'booking'
	 * @param string $visibilityLevel Can be 'agent', 'customer', or 'all'
	 * @param OsBookingModel|false $booking_object Get only custom fields attached to a particular booking. Supply <code>false</code> to ignore such condition(s)
	 * @param array $atts Extra attributes by which to filter resultant custom fields (ex. type, hide_on_summary, etc.)
	 *
	 * @return array Array of selectable custom fields that match the supplied conditions
	 */
	public static function get_custom_fields_for_select( string $fields_for = 'customer', string $visibilityLevel = 'customer', $booking_object = false, array $atts = [] ): array {
		$custom_fields_arr = self::get_custom_fields_arr( $fields_for, $visibilityLevel, $booking_object, $atts );
		$select_list       = [];

		foreach ( $custom_fields_arr as $custom_field ) {
			$select_list[ $custom_field['id'] ] = $custom_field['label'];
		}

		return $select_list;
	}

	/**
	 * Get an array of custom fields that match the supplied conditions, formatted for <code>OsFormHelper::multi_select_field()</code>'s <em>$options</em> argument
	 *
	 * @param string $fields_for Can be 'customer' or 'booking'
	 * @param string $visibilityLevel Can be 'agent', 'customer', or 'all'
	 * @param OsBookingModel|false $booking_object Get only custom fields attached to a particular booking. Supply <code>false</code> to ignore such condition(s)
	 * @param array $atts Extra attributes by which to filter resultant custom fields (ex. type, hide_on_summary, etc.)
	 *
	 * @return array Array of multi-selectable custom fields that match the supplied conditions
	 */
	public static function get_custom_fields_for_multi_select( string $fields_for = 'customer', string $visibilityLevel = 'customer', $booking_object = false, array $atts = [] ): array {
		$custom_fields_arr = self::get_custom_fields_arr( $fields_for, $visibilityLevel, $booking_object, $atts );
		$multi_select_list = [];

		foreach ( $custom_fields_arr as $custom_field ) {
			$multi_select_list[] = [
				'id'   => $custom_field['id'],
				'name' => $custom_field['label'],
			];
		}

		return $multi_select_list;
	}

	public static function is_condition_satisfied( $custom_field, $booking_object ) {
		// no conditions
		if ( ! $custom_field['conditions'] || $custom_field['conditional'] == 'off' ) {
			return true;
		}
		foreach ( $custom_field['conditions'] as $condition ) {
			$required_values = $condition['value'] ? explode( ',', $condition['value'] ) : [];
			$current_value   = false;
			switch ( $condition['property'] ) {
				case 'service':
					$current_value = $booking_object->service_id;
					break;
				case 'agent':
					$current_value = $booking_object->agent_id;
					break;
				case 'location':
					$current_value = $booking_object->location_id;
					break;
			}
			// not satisfied
			switch ( $condition['operator'] ) {
				case 'not_equal':
					if ( in_array( $current_value, $required_values ) ) {
						return false;
					}
					break;
				default:
					if ( ! in_array( $current_value, $required_values ) ) {
						return false;
					}
					break;
			}
		}

		// if reached here - means it is satisfied
		return true;
	}

	public static function save_custom_fields_arr( $custom_fields_arr, $fields_for = 'customer' ) {
		$custom_fields_arr = self::prepare_to_save( $custom_fields_arr );

		return OsSettingsHelper::save_setting_by_name( 'custom_fields_for_' . $fields_for, json_encode( $custom_fields_arr ) );
	}
}
