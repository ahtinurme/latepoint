<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureCustomFieldsHelper {

	public static string $step_code = 'booking__custom_fields';

	public static function filter_customer_custom_fields_on_steps( array $customer_params, array $params ): array {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );
		foreach ( $custom_fields_for_customer as $custom_field ) {
			$value = '';
			switch ( $custom_field['type'] ) {
				case 'text':
				case 'phone_number':
				case 'number':
				case 'hidden':
				case 'select':
				case 'checkbox':
				case 'google_address_autocomplete':
				case 'file_upload':
					$value = sanitize_text_field( $params['custom_fields'][ $custom_field['id'] ] );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $params['custom_fields'][ $custom_field['id'] ] );
					break;
			}
			$customer_params['custom_fields'][ $custom_field['id'] ] = $value;
		}
		return $customer_params;
	}

	public static function should_step_be_skipped( bool $skip, string $step_code, OsCartModel $cart, OsCartItemModel $cart_item, OsBookingModel $booking ): bool {
		if ( $step_code == self::$step_code ) {
			if ( $cart_item->is_booking() ) {
				$booking                   = $cart_item->build_original_object_from_item_data();
				$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'customer', $booking );
				if ( empty( $custom_fields_for_booking ) ) {
					$skip = true;
				}
			} else {
				$skip = true;
			}
		}

		return $skip;
	}

	public static function add_svg_for_step( string $svg, string $step_code ) {
		if ( $step_code == self::$step_code ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-highlight" d="M36.270771,27.7026501h16.8071289c0.4140625,0,0.75-0.3359375,0.75-0.75s-0.3359375-0.75-0.75-0.75H36.270771 c-0.4140625,0-0.75,0.3359375-0.75,0.75S35.8567085,27.7026501,36.270771,27.7026501z"/>
					<path class="latepoint-step-svg-highlight" d="M40.5549507,42.3081207c0,0.4140625,0.3359375,0.75,0.75,0.75h12.6015625c0.4140625,0,0.75-0.3359375,0.75-0.75 s-0.3359375-0.75-0.75-0.75H41.3049507C40.8908882,41.5581207,40.5549507,41.8940582,40.5549507,42.3081207z"/>
					<path class="latepoint-step-svg-highlight" d="M45.6980171,51.249527H29.9778023c-0.4140625,0-0.75,0.3359375-0.75,0.75s0.3359375,0.75,0.75,0.75h15.7202148 c0.4140625,0,0.75-0.3359375,0.75-0.75S46.1120796,51.249527,45.6980171,51.249527z"/>
					<path class="latepoint-step-svg-highlight" d="M62.1623726,11.5883932l0.3300781-3.3564453c0.0405273-0.4121094-0.2607422-0.7792969-0.6728516-0.8193359 c-0.4091797-0.0458984-0.77882,0.2597656-0.8203125,0.6728516l-0.3300781,3.3564453 c-0.0405273,0.4121094,0.2612305,0.7792969,0.6733398,0.8193359 C61.7317963,12.3070383,62.1204109,12.0155325,62.1623726,11.5883932z"/>
					<path class="latepoint-step-svg-highlight" d="M63.9743843,13.9233541c1.1010704-0.3369141,2.0717735-1.0410156,2.7333946-1.9814453 c0.2382813-0.3388672,0.1567383-0.8066406-0.1816406-1.0449219c-0.3383789-0.2392578-0.8066406-0.1572266-1.0449219,0.1816406 c-0.4711914,0.6699219-1.1621094,1.1708984-1.9462852,1.4111328c-0.3959961,0.1210938-0.6186523,0.5400391-0.4975586,0.9365234 C63.1588402,13.8212023,63.5774651,14.0450754,63.9743843,13.9233541z"/>
					<path class="latepoint-step-svg-highlight" d="M68.8601227,17.4516735c0.0356445-0.4121094-0.2695313-0.7763672-0.6826172-0.8115234l-3.859375-0.3349609 c-0.4072227-0.0390625-0.7758751,0.2695313-0.8115196,0.6826172c-0.0356445,0.4121094,0.2695313,0.7763672,0.6826134,0.8115234 l3.859375,0.3349609C68.4594727,18.1708145,68.8244781,17.8649578,68.8601227,17.4516735z"/>
					<path class="latepoint-step-svg-highlight" d="M4.7497134,18.4358044c1.0574932,1.9900436,1.9738078,2.5032253,13.2814941,11.7038574 c0.5604858,11.4355488,0.9589844,22.8789082,1.1829224,34.3259277c0.3128052,0.1918945,0.6256714,0.3835449,0.9384766,0.5751953 c0.1058846,0.3764038,0.416275,0.5851364,0.7949219,0.5466309c12.6464844-1.4892578,25.8935547-2.0419922,40.4916992-1.6767578 c0.4600639-0.0021172,0.763813-0.3514481,0.7685547-0.7421875c0.1805725-16.3819695-0.080349-32.8599472,0.0605469-49.1875 c0.003418-0.3740234-0.2685547-0.6923828-0.6376953-0.7480469c-14.1435547-2.140625-28.5092773-2.3291016-42.6953125-0.5664063 c-0.331604,0.0407715-0.5751953,0.2971191-0.6331177,0.6113281c-0.3464966,0.277832-0.6930542,0.5556641-1.0396118,0.8334961 c0.1156616,1.137207,0.0985718,2.392333,0.1765137,3.5629873c-2.2901011-1.8925772-4.5957651-3.8081045-6.9354258-5.7802725 c-0.7441406-0.6269531-1.6889648-0.9277344-2.683105-0.8378906C4.4105406,11.3600969,3.320657,15.7476349,4.7497134,18.4358044z M60.7629585,14.6196432c-0.1265907,15.9033155,0.1148987,31.8954544-0.046875,47.7734375 c-14.0498047-0.3193359-26.8598633,0.2099609-39.1044922,1.6074219c0.0154419-10.8208008-0.2228394-21.3803711-0.6828613-31.503418 c8.6963615,7.0753174,9.1210613,7.5400124,10.6517334,8.1962891c2.7804565,1.1923828,7.8590698,1.5974121,8.4487305,0.6987305 c0.0741577-0.0522461,0.1495361-0.1047363,0.2015381-0.1826172c0.1469727-0.2207031,0.1669922-0.5029297,0.0517578-0.7412109 c-1.0354347-2.1505203-2.3683548-6.0868149-3.1914063-6.7568359c-5.5252628-4.5023842-10.581501-8.5776329-16.84375-13.7214375 c-0.1300049-1.973877-0.2654419-3.9484863-0.4165039-5.9221182C33.4343452,12.4419088,47.1985054,12.6274557,60.7629585,14.6196432 z M9.5368834,13.0405416c9.0454321,7.6246099,17.5216217,14.4366217,26.5917969,21.8203125 c0.3883591,0.3987503,1.5395088,3.3786926,2.2700195,5.078125c-1.4580688-0.1650391-2.9936523-0.479248-4.7089233-0.8842773 c0.4859009-0.9790039,1.1461182-1.8769531,1.953064-2.6108398c0.3061523-0.2783203,0.3286133-0.7529297,0.0498047-1.0595703 c-0.2783203-0.3046875-0.7519531-0.328125-1.0595703-0.0498047c-0.9295654,0.8461914-1.6932373,1.8774414-2.2598877,3.0026855 c-8.9527779-7.1637478-17.1909065-14.1875877-25.8739014-21.1394062c-0.5556641-0.4443359-0.8725586-1.09375-0.8481445-1.7363272 C5.7526169,12.8167362,8.1288319,11.8543167,9.5368834,13.0405416z"/>
				</svg>';
		}

		return $svg;
	}

	/**
	 *
	 * Uploads files that were submitted through the booking form at the time of creation of order_intent, when we
	 * convert intent to booking later we will just use those URLs from intent custom fields in booking_data
	 *
	 * @param array $booking_data
	 *
	 * @return array
	 */
	public static function process_custom_fields_in_booking_data_for_order_intent( array $booking_data ): array {


		// get files from $_FILES object
		$files = OsParamsHelper::get_file( 'booking' );

		$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'agent' );
		if ( ! isset( $booking_data['custom_fields'] ) ) {
			$booking_data['custom_fields'] = [];
		}
		if ( $custom_fields_structure ) {
			foreach ( $custom_fields_structure as $custom_field ) {
				switch ( $custom_field['type'] ) {
					case 'file_upload':
						if ( ! empty( $files['name']['custom_fields'][ $custom_field['id'] ] ) ) {
							if ( ! function_exists( 'wp_handle_upload' ) ) {
								require_once ABSPATH . 'wp-admin/includes/file.php';
							}
							for ( $i = 0; $i < count( $files['name']['custom_fields'][ $custom_field['id'] ] ); $i++ ) {
								$file   = [
									'name'     => $files['name']['custom_fields'][ $custom_field['id'] ][ $i ],
									'type'     => $files['type']['custom_fields'][ $custom_field['id'] ][ $i ],
									'tmp_name' => $files['tmp_name']['custom_fields'][ $custom_field['id'] ][ $i ],
									'error'    => $files['error']['custom_fields'][ $custom_field['id'] ][ $i ],
									'size'     => $files['size']['custom_fields'][ $custom_field['id'] ][ $i ],
								];
								$result = wp_handle_upload( $file, [ 'test_form' => false ] );
								if ( ! isset( $result['error'] ) && ! empty( $result['url'] ) ) {
									$booking_data['custom_fields'][ $custom_field['id'] ] = $result['url'];
								}
							}
						}
						break;
					default:
						break;
				}
			}
		}

		return $booking_data;
	}

	public static function add_capabilities_for_controller( $required_capabilities ) {
		$required_capabilities['OsCustomFieldsController']['per_action'] = [ 'reload_custom_fields_for_booking_data_form' => [ 'booking__view' ] ];

		return $required_capabilities;
	}

	public static function add_custom_fields_to_processes( array $properties, string $event_type ): array {
		$custom_fields['customer'] = OsCustomFieldsHelper::get_custom_fields_arr( 'customer' );
		$custom_fields['booking']  = OsCustomFieldsHelper::get_custom_fields_arr( 'booking' );

		switch ( $event_type ) {
			case 'booking_created':
			case 'booking_updated':
				foreach ( $custom_fields['customer'] as $custom_field ) {
					$properties[ 'custom_fields_for_customer__' . $custom_field['id'] ] = __( 'Customer/', 'latepoint-pro-features' ) . $custom_field['label'];
				}
				foreach ( $custom_fields['booking'] as $custom_field ) {
					$properties[ 'custom_fields_for_booking__' . $custom_field['id'] ] = __( 'Booking/', 'latepoint-pro-features' ) . $custom_field['label'];
				}
				break;
			case 'customer_created':
				foreach ( $custom_fields['customer'] as $custom_field ) {
					$properties[ 'custom_fields_for_customer__' . $custom_field['id'] ] = __( 'Customer/', 'latepoint-pro-features' ) . $custom_field['label'];
				}
				break;
		}

		return $properties;
	}

	public static function output_google_autocomplete_settings() {
		echo '<div class="sub-section-row">
			      <div class="sub-section-label">
			        <h3>' . __( 'Google Places API', 'latepoint-pro-features' ) . '</h3>
			      </div>
			      <div class="sub-section-content">
						<div class="latepoint-message latepoint-message-subtle">' . __( 'In order for address autocomplete to work, you need an API key. To learn how to create an API key for Google Places API', 'latepoint-pro-features' ) . ' <a target="_blank" href="https://developers.google.com/maps/documentation/javascript/place-autocomplete#get-started">' . __( 'click here', 'latepoint-pro-features' ) . '</a></div>
							<div class="os-row">
								<div class="os-col-6">' . OsFormHelper::text_field( 'settings[google_places_api_key]', __( 'Google Places API key', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'google_places_api_key' ), [ 'theme' => 'simple' ] ) . '</div>
								<div class="os-col-6">' . OsFormHelper::select_field( 'settings[google_places_country_restriction]', __( 'Country Restriction', 'latepoint-pro-features' ), OsCustomFieldsHelper::load_countries_list(), OsSettingsHelper::get_settings_value( 'google_places_country_restriction', '' ) ) . '</div>
							</div>
						</div>
					</div>';
	}

	public static function add_customer_custom_fields_to_service_attributes( $attributes, $customer ) {
		$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'customer' );
		if ( isset( $customer->custom_fields ) && $customer->custom_fields ) {
			foreach ( $customer->custom_fields as $key => $custom_field ) {
				$value = ( $custom_fields_structure[ $key ]['type'] == 'checkbox' ) ? OsCustomFieldsHelper::get_checkbox_value( $custom_field ) : $custom_field;
				if ( ! empty( $value ) && isset( $custom_fields_structure[ $key ] ) && $custom_fields_structure[ $key ]['hide_on_summary'] != 'on' ) {
					$attributes[] = [
						'label' => $custom_fields_structure[ $key ]['label'],
						'value' => $value,
					];
				}
			}
		}

		return $attributes;
	}

	public static function add_booking_custom_fields_to_service_attributes( $attributes, $booking ) {
		$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'customer' );
		if ( isset( $booking->custom_fields ) && $booking->custom_fields ) {
			foreach ( $booking->custom_fields as $key => $custom_field ) {
				// we don't need to show file info in summary
				$cf_type = $custom_fields_structure[ $key ]['type'];
				if ( $cf_type == 'file_upload' ) {
					continue;
				}

				$value = ( $cf_type == 'checkbox' ) ? OsCustomFieldsHelper::get_checkbox_value( $custom_field ) : $custom_field;

				if ( $cf_type == 'multiselect' ) {
					$value = OsCustomFieldsHelper::get_multiselect_value( $value ); //json to string
				}

				if ( ! empty( $value ) && isset( $custom_fields_structure[ $key ] ) && $custom_fields_structure[ $key ]['hide_on_summary'] != 'on' ) {
					$attributes[] = [
						'label' => $custom_fields_structure[ $key ]['label'],
						'value' => $value,
					];
				}
			}
		}

		return $attributes;
	}


	public static function add_custom_fields_to_booking_form_params( array $params, OsBookingModel $booking ) {
		if ( ! empty( $booking->custom_fields ) ) {
			$params['custom_fields'] = $booking->custom_fields;
		}

		return $params;
	}


	public static function add_booking_custom_fields_data_vars_to_booking( array $data, OsModel $booking ): array {
		if ( is_a( $booking, 'OsBookingModel' ) ) {
			$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'all' );
			foreach ( $custom_fields_for_booking as $custom_field ) {
				$data['custom_fields'][ $custom_field['id'] ] = $booking->get_meta_by_key( $custom_field['id'] ) ?: ( $custom_field['value'] ?? '' );
			}
			$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );
			foreach ( $custom_fields_for_customer as $custom_field ) {
				$data['customer']['custom_fields'][ $custom_field['id'] ] = $booking->customer->get_meta_by_key( $custom_field['id'] ) ?: ( $custom_field['value'] ?? '' );
			}
		}

		return $data;
	}

	public static function add_customer_custom_fields_data_vars_to_customer( array $data, OsModel $customer ): array {
		if ( is_a( $customer, 'OsCustomerModel' ) ) {
			$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );
			foreach ( $custom_fields_for_customer as $custom_field ) {
				$data['custom_fields'][ $custom_field['id'] ] = $customer->get_meta_by_key( $custom_field['id'] ) ?: ( $custom_field['value'] ?? '' );
			}
		}

		return $data;
	}

	/**
	 * Customer custom fields that are flagged to be shown as columns in the admin Customers table.
	 *
	 * @return array<string, array>
	 */
	public static function get_customer_custom_fields_for_table(): array {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );

		return array_filter(
			$custom_fields_for_customer,
			function ( $custom_field ) {
				return ! empty( $custom_field['show_in_customers_table'] ) && $custom_field['show_in_customers_table'] == LATEPOINT_VALUE_ON;
			}
		);
	}

	public static function add_custom_fields_to_bookings_table_columns( $columns ) {
		$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'all' );
		if ( $custom_fields_for_booking ) {
			foreach ( $custom_fields_for_booking as $custom_field ) {
				$columns['booking'][ $custom_field['id'] ] = $custom_field['label'];
			}
		}
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );
		if ( $custom_fields_for_customer ) {
			foreach ( $custom_fields_for_customer as $custom_field ) {
				$columns['customer'][ $custom_field['id'] ] = $custom_field['label'];
			}
		}

		return $columns;
	}

	public static function add_custom_fields_for_contact_step( $customer, $booking_object ) {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'customer', $booking_object );
		echo OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_customer, $customer, 'customer' );
	}


	public static function replace_customer_vars_in_template( $text, $customer ) {
		if ( $customer ) {
			$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
			if ( ! empty( $custom_fields_for_customer ) ) {
				$needles      = [];
				$replacements = [];
				foreach ( $custom_fields_for_customer as $custom_field ) {
					$needles[]      = '{{' . $custom_field['id'] . '}}';
					$value          = OsFeatureCustomFieldsHelper::get_custom_field_value( $custom_field, $customer );
					$replacements[] = $value;
				}
				$text = str_replace( $needles, $replacements, $text );
			}
		}

		return $text;
	}


	public static function replace_booking_vars_in_template( $text, $booking ) {
		if ( $booking ) {
			$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'agent' );

			if ( ! empty( $custom_fields_for_booking ) ) {
				$needles      = [];
				$replacements = [];
				foreach ( $custom_fields_for_booking as $custom_field ) {
					$needles[]      = '{{' . $custom_field['id'] . '}}';
					$value          = OsFeatureCustomFieldsHelper::get_custom_field_value( $custom_field, $booking );
					$replacements[] = $value;
				}
				$text = str_replace( $needles, $replacements, $text );
			}
		}

		return $text;
	}

	/**
	 * Get formatted custom field value
	 *
	 * @param array $custom_field Custom field configuration
	 * @param object $model Model instance with get_meta_by_key method
	 * @return string Formatted field value
	 */
	public static function get_custom_field_value( array $custom_field, OsModel $model ): string {
		if ( ! isset( $custom_field['id'], $custom_field['type'] ) ) {
			return '';
		}

		$field_type    = $custom_field['type'];
		$default_value = $custom_field['value'] ?? '';

		$meta_value = $model->get_meta_by_key( $custom_field['id'] );

		// we can't use empty because if $meta_value is 0 we need to show it
		if ( $meta_value === null || $meta_value === '' ) {
			return $default_value;
		}

		switch ( $field_type ) {
			case 'checkbox':
				return OsCustomFieldsHelper::get_checkbox_value( $meta_value );
			case 'multiselect':
				return OsCustomFieldsHelper::get_multiselect_value( $meta_value );
			default:
				return $meta_value;
		}
	}


	public static function add_custom_fields_to_bookings_data_for_csv( $bookings_data, $params = [] ) {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
		// update labels row
		foreach ( $custom_fields_for_customer as $custom_field ) {
			$bookings_data[0][] = $custom_field['label'];
		}
		$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'agent' );
		// update labels row
		foreach ( $custom_fields_for_booking as $custom_field ) {
			$bookings_data[0][] = $custom_field['label'];
		}

		return $bookings_data;
	}

	public static function add_custom_fields_to_booking_row_for_csv( $booking_row, $booking, $params = [] ) {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
		foreach ( $custom_fields_for_customer as $custom_field ) {
			$booking_row[] = OsFeatureCustomFieldsHelper::get_custom_field_value( $custom_field, $booking->customer );
		}
		$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'agent' );
		foreach ( $custom_fields_for_booking as $custom_field ) {
			$booking_row[] = OsFeatureCustomFieldsHelper::get_custom_field_value( $custom_field, $booking );
		}

		return $booking_row;
	}

	public static function add_custom_fields_to_customers_data_for_csv( $customers_data, $params = [] ) {

		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
		// update labels row
		foreach ( $custom_fields_for_customer as $custom_field ) {
			$customers_data[0][] = $custom_field['label'];
		}

		return $customers_data;
	}

	public static function add_custom_fields_to_customer_row_for_csv( $customer_row, $customer, $params = [] ) {

		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
		foreach ( $custom_fields_for_customer as $custom_field ) {
			$customer_row[] = $customer->get_meta_by_key( $custom_field['id'] ) ?: ( $custom_field['value'] ?? '' );
		}

		return $customer_row;
	}


	public static function output_customer_custom_fields_on_customer_dashboard( $customer ) {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'customer' );
		if ( $custom_fields_for_customer ) {
			echo '<div class="os-row">' . OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_customer, $customer, 'customer' ) . '</div>';
		}
	}

	public static function output_customer_custom_fields_on_form( $customer ) {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
		if ( $custom_fields_for_customer ) { ?>
			<div class="os-form-sub-header">
				<h3><?php _e( 'Custom Fields', 'latepoint-pro-features' ); ?></h3>
			</div>
			<div class="os-row">
				<?php echo OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_customer, $customer, 'customer' ); ?>
			</div>
			<?php
		}
	}


	public static function output_booking_custom_fields_on_quick_form( $booking, $order_item_id ) {
		$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'agent', $booking );
		echo '<div class="latepoint-custom-fields-for-booking-wrapper" data-route-name="' . OsRouterHelper::build_route_name( 'custom_fields', 'reload_custom_fields_for_booking_data_form' ) . '">';
		if ( isset( $custom_fields_for_booking ) && ! empty( $custom_fields_for_booking ) ) { ?>
			<?php echo '<div class="os-row">' . OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_booking, $booking, 'booking', 'order_items[' . $order_item_id . '][bookings][' . $booking->get_form_id() . ']' ) . '</div>';
		}
		echo '</div>';
	}

	public static function output_customer_custom_fields_on_quick_form( $customer ) {
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );
		if ( isset( $custom_fields_for_customer ) && ! empty( $custom_fields_for_customer ) ) { ?>
			<?php echo '<div class="os-row">' . OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_customer, $customer, 'customer' ) . '</div>';
		}
	}


	public static function load_custom_fields_for_model( $model ) {
		if ( ( $model instanceof OsBookingModel ) || ( $model instanceof OsCustomerModel ) ) {
			$fields_for              = ( $model instanceof OsBookingModel ) ? 'booking' : 'customer';
			$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( $fields_for, 'agent' );
			$metas                   = [];
			$model->custom_fields    = [];
			if ( $model instanceof OsBookingModel ) {
				$metas = OsMetaHelper::get_booking_metas( $model->id );
			} elseif ( $model instanceof OsCustomerModel ) {
				$metas = OsMetaHelper::get_customer_metas( $model->id );
			}
			if ( $metas && $custom_fields_structure ) {
				foreach ( $custom_fields_structure as $key => $custom_field ) {
					if ( isset( $metas[ $key ] ) ) {
						$model->custom_fields[ $key ] = $metas[ $key ];
					}
				}
			}
		}

		return $model;
	}


	public static function set_custom_fields_data( $model, $data = [] ) {
		if ( ( $model instanceof OsBookingModel ) || ( $model instanceof OsCustomerModel ) ) {
			if ( $data && isset( $data['custom_fields'] ) ) {
				$fields_for              = ( $model instanceof OsBookingModel ) ? 'booking' : 'customer';
				$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( $fields_for, 'agent' );
				if ( ! isset( $model->custom_fields ) ) {
					$model->custom_fields = [];
				}
				foreach ( $data['custom_fields'] as $key => $custom_field ) {
					// check if data is allowed
					if ( isset( $custom_fields_structure[ $key ] ) ) {
						$value = $custom_field;
						if ( is_array( $value ) ) {
							$filteredArray = array_filter(
								$value,
								function ( $value ) {
									return $value !== '';
								} 
							); // get only non-empty values
							$value         = wp_json_encode( array_values( $filteredArray ) );
						}
						$model->custom_fields[ $key ] = $value;
					}
				}
			}
		}
	}

	public static function validate_custom_fields( $model, $alternative_validation = false, $skip_properties = [] ) {
		if ( $alternative_validation ) {
			return;
		}
		if ( ( $model instanceof OsBookingModel ) || ( $model instanceof OsCustomerModel ) ) {
			$fields_for              = ( $model instanceof OsBookingModel ) ? 'booking' : 'customer';
			$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( $fields_for, 'agent', ( $fields_for == 'booking' ) ? $model : false );
			if ( ! isset( $model->custom_fields ) ) {
				$model->custom_fields = [];
			}
			$errors = OsCustomFieldsHelper::validate_fields( $model->custom_fields, $custom_fields_structure, $fields_for, $model->get_form_id() );
			if ( $errors ) {
				foreach ( $errors as $error ) {
					$model->add_error( $error['type'], $error['message'] );
				}
			}
		}
	}

	public static function extract_file_properties_by_custom_field_id( string $file_property, $fields_for, string $custom_field_id, string $form_id = '' ): array {
		switch ( $fields_for ) {
			case 'customer':
				$files = OsParamsHelper::get_file( 'customer' );

				return $files[ $file_property ]['custom_fields'][ $custom_field_id ] ?? [];
				break;
			case 'booking':
				$files = OsParamsHelper::get_file( 'booking_files' );

				/*
				Try to get original form id if it was a new booking, it would have something like new_932843 instead of an integer ID, which would be in the $_FILES array,
				in backend we use something like new_999999 for each separate booking form, because they are all being sent in the same request, on a frontend tho we have a separate request
				for each booking, so that we don't need a custom name like that, instead we simply use 'new', so we need to check for that if the custom id that is passed here is not set in params
				*/
				return $files[ $file_property ][ $form_id ]['custom_fields'][ $custom_field_id ] ?? ( $files[ $file_property ]['new']['custom_fields'][ $custom_field_id ] ?? [] );
				break;
		}

		return [];
	}

	public static function save_custom_fields( $model ) {
		if ( $model->is_new_record() ) {
			return;
		}
		if ( ( $model instanceof OsCartItemModel ) ) {
			if ( $model->is_booking() ) {
				$fields_for = 'booking';
				$booking    = $model->build_original_object_from_item_data();

				$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( $fields_for, 'agent', ( $fields_for == 'booking' ) ? $booking : false );
				if ( $custom_fields_structure ) {
					foreach ( $custom_fields_structure as $custom_field ) {
						$model_form_id = 'new';
						if ( $custom_field['type'] == 'file_upload' && ! empty( self::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $model_form_id ) ) ) {

							if ( ! function_exists( 'wp_handle_upload' ) ) {
								require_once ABSPATH . 'wp-admin/includes/file.php';
							}
							$item_data = json_decode( $model->item_data, true );
							for ( $i = 0; $i < count( self::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $model_form_id ) ); $i++ ) {
								$file = [
									'name'     => self::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
									'type'     => self::extract_file_properties_by_custom_field_id( 'type', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
									'tmp_name' => self::extract_file_properties_by_custom_field_id( 'tmp_name', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
									'error'    => self::extract_file_properties_by_custom_field_id( 'error', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
									'size'     => self::extract_file_properties_by_custom_field_id( 'size', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
								];

								$same_file_already_exists = false;
								// check if there is already file for this field, compare it to the one that is being uploaded

								$existing_file_url = $item_data['custom_fields'][ $custom_field['id'] ] ?? false;
								if ( $existing_file_url ) {
									if ( OsSettingsHelper::is_env_dev() ) {
										// FOR LOCAL(SSL) TESTING USE:
										$context = stream_context_create(
											[
												'ssl' => [
													'verify_peer' => false,
													'verify_peer_name' => false,
												],
											] 
										);
									} else {
										$context = null;
									}
									$headers = get_headers( $existing_file_url, 1, $context );
									// compare existing file with the one that is being uploaded
									$same_file_already_exists = ( ! empty( $headers['Content-Length'] ) && ( $headers['Content-Length'] == filesize( $file['tmp_name'] ) ) && ( md5( file_get_contents( $existing_file_url, false, $context ) ) == md5_file( $file['tmp_name'] ) ) );
								}

								if ( ! $same_file_already_exists ) {
									try {
										$result = wp_handle_upload( $file, [ 'test_form' => false ] );
										if ( ! isset( $result['error'] ) && ! empty( $result['url'] ) ) {
											$item_data['custom_fields'][ $custom_field['id'] ] = $result['url'];
										}
									} catch ( Exception $e ) {
										OsDebugHelper::log( 'File upload error', 'file_upload_error', [ 'error_message' => $e->getMessage() ] );
									}
								}
							}
							$model->update_attributes( [ 'item_data' => json_encode( $item_data ) ] );
						}
					}
				}
			}
		}
		if ( ( $model instanceof OsBookingModel ) || ( $model instanceof OsCustomerModel ) ) {
			$fields_for = ( $model instanceof OsBookingModel ) ? 'booking' : 'customer';

			$custom_fields_structure = OsCustomFieldsHelper::get_custom_fields_arr( $fields_for, 'agent', ( $fields_for == 'booking' ) ? $model : false );
			if ( ! isset( $model->custom_fields ) ) {
				$model->custom_fields = [];
			}
			// ! important - we need to use form_id instead of get_form_id(), because get_form_id() overrides original form_id with a newly created ID for that model
			$model_form_id = $model->form_id;
			if ( $custom_fields_structure ) {
				foreach ( $custom_fields_structure as $custom_field ) {
					switch ( $custom_field['type'] ) {
						case 'file_upload':
							if ( ! empty( self::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $model_form_id ) ) ) {
								if ( ! function_exists( 'wp_handle_upload' ) ) {
									require_once ABSPATH . 'wp-admin/includes/file.php';
								}
								for ( $i = 0; $i < count( self::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $model_form_id ) ); $i++ ) {
									$file = [
										'name'     => self::extract_file_properties_by_custom_field_id( 'name', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
										'type'     => self::extract_file_properties_by_custom_field_id( 'type', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
										'tmp_name' => self::extract_file_properties_by_custom_field_id( 'tmp_name', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
										'error'    => self::extract_file_properties_by_custom_field_id( 'error', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
										'size'     => self::extract_file_properties_by_custom_field_id( 'size', $fields_for, $custom_field['id'], $model_form_id )[ $i ],
									];

									$same_file_already_exists = false;
									// check if there is already file for this field, compare it to the one that is being uploaded
									$existing_file_url = $model->get_meta_by_key( $custom_field['id'] );
									if ( $existing_file_url ) {
										if ( OsSettingsHelper::is_env_dev() ) {
											// FOR LOCAL(SSL) TESTING USE:
											$context = stream_context_create(
												[
													'ssl' => [
														'verify_peer'      => false,
														'verify_peer_name' => false,
													],
												] 
											);
										} else {
											$context = null;
										}
										$headers = get_headers( $existing_file_url, 1, $context );
										// compare existing file with the one that is being uploaded
										$same_file_already_exists = ( ! empty( $headers['Content-Length'] ) && ( $headers['Content-Length'] == filesize( $file['tmp_name'] ) ) && ( md5( file_get_contents( $existing_file_url, false, $context ) ) == md5_file( $file['tmp_name'] ) ) );
									}

									if ( ! $same_file_already_exists ) {
										try {
											$result = wp_handle_upload( $file, [ 'test_form' => false ] );
											if ( ! isset( $result['error'] ) && ! empty( $result['url'] ) ) {
												$model->save_meta_by_key( $custom_field['id'], $result['url'] );
											}
										} catch ( Exception $e ) {
											OsDebugHelper::log( 'File upload error', 'file_upload_error', [ 'error_message' => $e->getMessage() ] );
										}
									}
								}
							} elseif ( ! empty( $model->custom_fields[ $custom_field['id'] ] ) ) {
								// file is already saved and is part of booking data, assign set model's meta to it's URL
								$model->save_meta_by_key( $custom_field['id'], $model->custom_fields[ $custom_field['id'] ] );
							}
							break;
						default:
							if ( isset( $model->custom_fields[ $custom_field['id'] ] ) ) {
								$model->save_meta_by_key( $custom_field['id'], $model->custom_fields[ $custom_field['id'] ] );
							}
							break;
					}
				}
			}
		}
	}

	public static function output_custom_fields_vars() {
		$custom_fields_for_booking  = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'agent' );
		$custom_fields_for_customer = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'agent' );

		if ( $custom_fields_for_booking || $custom_fields_for_customer ) { ?>
			<div class="available-vars-block">
				<h4><?php _e( 'Custom Fields', 'latepoint-pro-features' ); ?></h4>
				<ul>
					<?php
					if ( $custom_fields_for_customer ) {
						echo '<li><strong>' . __( 'For Customer:', 'latepoint-pro-features' ) . '</strong></li>';
						foreach ( $custom_fields_for_customer as $custom_field ) { ?>
							<li><span class="var-label"><?php echo $custom_field['label']; ?></span> <span class="var-code os-click-to-copy">{{<?php echo $custom_field['id']; ?>}}</span></li>
						<?php }
					}
					if ( $custom_fields_for_booking ) {
						echo '<li style="padding-top: 10px;"><strong>' . __( 'For Booking:', 'latepoint-pro-features' ) . '</strong></li>';
						foreach ( $custom_fields_for_booking as $custom_field ) { ?>
							<li><span class="var-label"><?php echo $custom_field['label']; ?></span> <span class="var-code os-click-to-copy">{{<?php echo $custom_field['id']; ?>}}</span></li>
						<?php }
					} ?>
				</ul>
			</div>
		<?php }
	}


	public static function process_step_custom_fields( $step_code, $booking_object, $params = [] ) {
		if ( $step_code == self::$step_code ) {
			$booking_params            = OsParamsHelper::get_param( 'booking' );
			$custom_fields_data        = $booking_params['custom_fields'] ?? [];
			$custom_fields_for_booking = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'customer', $booking_object );

			$is_valid       = true;
			$errors         = OsCustomFieldsHelper::validate_fields( $custom_fields_data, $custom_fields_for_booking, 'booking', 'new' );
			$error_messages = [];
			if ( $errors ) {
				$is_valid = false;
				foreach ( $errors as $error ) {
					$error_messages[] = $error['message'];
				}
			}
			if ( ! $is_valid ) {
				wp_send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => $error_messages,
					) 
				);

				return;
			}
		}
	}


	public static function load_step_custom_fields_for_booking( $step_code, $format = 'json' ) {
		if ( $step_code == self::$step_code ) {
			$custom_fields_controller                                    = new OsCustomFieldsController();
			$custom_fields_controller->vars['custom_fields_for_booking'] = OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'customer', OsStepsHelper::$booking_object );
			$custom_fields_controller->vars['booking']                   = OsStepsHelper::$booking_object;
			$custom_fields_controller->vars['current_step_code']         = $step_code;
			$custom_fields_controller->set_layout( 'none' );
			$custom_fields_controller->set_return_format( $format );
			$custom_fields_controller->format_render(
				'_step_booking__custom_fields',
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

	public static function show_step_info( $step_code = '' ) {
		if ( $step_code == self::$step_code && ! OsCustomFieldsHelper::get_custom_fields_arr( 'booking', 'customer' ) ) {
			echo '<a href="' . OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'settings', 'payments' ) ) . '" class="step-message">' . __( 'You have not created any custom fields for booking, this step will be skipped', 'latepoint-pro-features' ) . '</a>';
		}
	}


	public static function add_step_show_next_btn_rules( $rules, $step_code ) {
		$rules[ self::$step_code ] = true;

		return $rules;
	}

	public static function add_settings_for_step( array $settings ): array {
		$settings[ self::$step_code ] = [
			'side_panel_heading'     => 'Custom Fields',
			'side_panel_description' => 'Please answer this set of questions to proceed.',
			'main_panel_heading'     => 'Custom Fields',
		];

		return $settings;
	}

	public static function add_step_for_custom_fields( array $steps ): array {
		$steps[ self::$step_code ] = [ 'after' => 'services' ];

		return $steps;
	}

	public static function add_label_for_step( array $labels ): array {
		$labels[ self::$step_code ] = __( 'Custom Fields for Booking', 'latepoint-pro-features' );

		return $labels;
	}

	public static function add_customer_import_fields( array $fields ): array {
		$custom_fields = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );
		foreach ( $custom_fields as $id => $custom_field ) {
			$fields[ $custom_field['id'] ] = $custom_field['label'];
		}
		return $fields;
	}

	public static function customer_import_custom_fields( OsCustomerModel $customer, array $row_data, array $column_mapping ) {
		$custom_fields = OsCustomFieldsHelper::get_custom_fields_arr( 'customer', 'all' );

		if ( empty( $custom_fields ) ) {
			return;
		}

		foreach ( $custom_fields as $cf_id => $custom_field ) {
			$columnIndex = array_search( $cf_id, $column_mapping );

			if ( $columnIndex !== false && ! empty( $row_data[ $columnIndex ] ) ) {
				$customer->save_meta_by_key( $cf_id, $row_data[ $columnIndex ] );
			}
		}
	}
}
