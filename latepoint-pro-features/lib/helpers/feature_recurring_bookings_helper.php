<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureRecurringBookingsHelper {

	public static string $step_code = 'booking__recurring_bookings';


	public static function save_recurring_settings_in_service( $service, $is_new_record, $service_params ) {

		if ( isset( $service_params['meta'] ) ) {
			foreach ( [ 'allow_recurring_bookings', 'maximum_number_of_recurring_bookings', 'allow_recurring_periods' ] as $service_meta_key ) {
				if ( $service_meta_value = ( $service_params['meta'][ $service_meta_key ] ?? false ) ) {
					$service->save_meta_by_key( $service_meta_key, $service_meta_value );
				}
			}
		}
	}


	public static function add_recurring_modal_to_datepicker_step( string $step_code ) {
		if ( $step_code == 'booking__datepicker' ) {
			?>
			<div class="os-recurring-suggestion-wrapper">
				<div class="os-recurring-suggestion">
					<div class="os-recurring-suggestion-heading"><?php esc_html_e( 'Make it a recurring appointment?', 'latepoint-pro-features' ) ?></div>
					<div class="os-recurring-suggestion-sub-heading"><?php esc_html_e( 'You will set the frequency in the next step.', 'latepoint-pro-features' ) ?></div>
					<div class="os-recurring-suggestion-options">
						<div class="os-recurring-suggestion-option" data-value="<?php echo LATEPOINT_VALUE_ON; ?>"><?php esc_html_e( 'Yes', 'latepoint-pro-features' ); ?></div>
						<div class="os-recurring-suggestion-option" data-value="<?php echo LATEPOINT_VALUE_OFF; ?>"><?php esc_html_e( 'No', 'latepoint-pro-features' ); ?></div>
					</div>
				</div>
			</div>
			<?php
		}
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


	public static function output_recurrence_settings_on_service_form( $service ) {
		?>
		<div class="white-box section-anchor" id="stickySectionRecurring">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php _e( 'Recurring Appointments', 'latepoint-pro-features' ); ?></h3>
				</div>
			</div>
			<div class="white-box-content">
				<?php echo OsFormHelper::toggler_field( 'service[meta][allow_recurring_bookings]', esc_html__( 'Allow Recurring Appointments', 'latepoint-pro-features' ), ( $service->get_meta_by_key( 'allow_recurring_bookings' ) == 'on' ), 'lp-allow-recurring-bookings', false, [ 'sub_label' => __( 'Allows customers to book this service in a recurring schedule', 'latepoint-pro-features' ) ] ); ?>
				<div id="lp-allow-recurring-bookings" <?php echo ( $service->get_meta_by_key( 'allow_recurring_bookings' ) == 'on' ) ? '' : 'style="display: none;"' ?>>
					<div class="merged-fields os-mt-1">
						<div class="merged-label"><?php esc_html_e( 'Allow a maximum of', 'latepoint-pro-features' ); ?></div>
						<?php echo OsFormHelper::text_field( 'service[meta][maximum_number_of_recurring_bookings]', false, $service->get_meta_by_key( 'maximum_number_of_recurring_bookings', '20' ), [ 'placeholder' => esc_html__( 'Value', 'latepoint-pro-features' ) ] ); ?>
						<div class="merged-label"><?php esc_html_e( 'future appointments', 'latepoint-pro-features' ); ?></div>
					</div>
					<div class="merged-fields os-mt-1">
						<div class="merged-label"><?php esc_html_e( 'Allow Repeat Intervals', 'latepoint-pro-features' ); ?></div>
						<?php echo OsFormHelper::select_field(
							'service[meta][allow_recurring_periods]',
							false,
							[
								'all'   => __( 'All', 'latepoint-pro-features' ),
								'day'   => __( 'Daily', 'latepoint-pro-features' ),
								'week'  => __( 'Weekly', 'latepoint-pro-features' ),
								'month' => __( 'Monthly', 'latepoint-pro-features' ),
							],
							$service->get_meta_by_key( 'allow_recurring_periods', 'all' )
						); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param OsBookingModel[] $bookings
	 *
	 * @return string
	 */
	public static function output_recurring_info_for_sequence_of_bookings( string $html, array $bookings = [], bool $same_location = false, bool $same_agent = false ): string {
		if ( empty( $bookings ) ) {
			return $html;
		}

		$main_booking = $bookings[0];

		$html .= OsBookingHelper::generate_summary_for_booking( $main_booking, $main_booking->cart_item_id );
		$html .= OsFeatureRecurringBookingsHelper::output_recurrent_bookings_summary( $bookings );

		if ( ( ! $same_agent && OsAgentHelper::count_agents() > 1 ) || ! $same_location ) {

			$html .= '<div class="booking-summary-info-w">';
			$html .= '<div class="summary-boxes-columns">';

			ob_start();
			if ( ! $same_agent && OsAgentHelper::count_agents() > 1 ) {
				OsAgentHelper::generate_summary_for_agent( $main_booking );
			}
			if ( ! $same_location ) {
				OsLocationHelper::generate_summary_for_location( $main_booking );
			}
			$html .= ob_get_clean();

			$html .= '</div>';
			$html .= '</div>';
		}


		return $html;
	}

	public static function get_max_limit_of_allowed_recurring_bookings( OsBookingModel $booking ) {
		if ( $booking->is_bundle_scheduling() ) {
			$remaining_slots       = OsBundlesHelper::get_remaining_slots_for_bundle_order_item( $booking->order_item_id );
			$max_limit_of_bookings = $remaining_slots;
		} else {
			$max_limit_of_bookings = $booking->service->get_meta_by_key( 'maximum_number_of_recurring_bookings', 20 );
		}
		return $max_limit_of_bookings;
	}

	public static function output_recurring_info_on_booking_summary( string $summary_html, OsBookingModel $booking, ?string $cart_item_id, string $viewer ): string {
		if ( ! empty( $booking->generate_recurrent_sequence ) ) {
			$recurring_bookings_data_and_errors = OsFeatureRecurringBookingsHelper::generate_recurring_bookings_data( $booking, $booking->generate_recurrent_sequence['rules'], $booking->generate_recurrent_sequence['overrides'], $booking->get_customer_timezone() );
			$bookings_data                      = $recurring_bookings_data_and_errors['bookings_data'];
			$bookings                           = [];
			foreach ( $bookings_data as $booking_data ) {
				if ( $booking_data['unchecked'] == 'yes' ) {
					continue;
				}
				$bookings[] = $booking_data['booking'];
			}
			$summary_html .= self::output_recurrent_bookings_summary( $bookings );
		}

		return $summary_html;
	}

	public static function add_step_for_recurring_bookings( array $steps ): array {
		$steps[ self::$step_code ] = [ 'after' => 'datepicker' ];

		return $steps;
	}

	public static function add_step_show_next_btn_rules( $rules, $step_code ): array {
		$rules[ self::$step_code ] = false;

		return $rules;
	}

	public static function should_step_be_skipped( bool $skip, string $step_code, OsCartModel $cart, OsCartItemModel $active_cart_item, OsBookingModel $booking ): bool {
		if ( $step_code == self::$step_code ) {
			if ( $active_cart_item->is_booking() ) {
				$booking = $active_cart_item->build_original_object_from_item_data();
				if ( empty( $booking->generate_recurrent_sequence ) ) {
					return true;
				}
			} else {
				$skip = true;
			}
		}

		return $skip;
	}


	public static function add_settings_for_step( array $settings ): array {
		$settings[ self::$step_code ] = [
			'side_panel_heading'     => 'Recurring Appointment',
			'side_panel_description' => 'Please select if you want to make it a recurring appointment',
			'main_panel_heading'     => 'Recurring Appointment',
		];

		return $settings;
	}


	public static function add_label_for_step( array $labels ): array {
		$labels[ self::$step_code ] = __( 'Recurring Appointment', 'latepoint-pro-features' );

		return $labels;
	}

	public static function load_step_recurring_bookings( $step_code, $format = 'json' ) {
		if ( $step_code == self::$step_code ) {

			$recurring_bookings_controller                                = new OsRecurringBookingsController();
			$recurring_bookings_controller->vars['booking']               = OsStepsHelper::$booking_object;
			$recurring_bookings_controller->vars['current_step_code']     = $step_code;
			$recurring_bookings_controller->vars['recurrence_start_date'] = OsStepsHelper::$booking_object->get_start_datetime( OsStepsHelper::$booking_object->get_customer_timezone_name() );
			$recurring_bookings_controller->vars['repeat_end_date']       = OsStepsHelper::$booking_object->get_start_datetime( OsStepsHelper::$booking_object->get_customer_timezone_name() )->modify( '+2 months' );

			if ( OsStepsHelper::is_bundle_scheduling() ) {
				$recurring_bookings_controller->vars['max_repeat_end_counter'] = OsBundlesHelper::get_remaining_slots_for_bundle_order_item( OsStepsHelper::$booking_object->order_item_id );
			} else {
				$recurring_bookings_controller->vars['max_repeat_end_counter'] = OsStepsHelper::$booking_object->service->get_meta_by_key( 'maximum_number_of_recurring_bookings', 20 );
			}

			$recurring_bookings_controller->set_layout( 'none' );
			$recurring_bookings_controller->set_return_format( $format );
			$recurring_bookings_controller->format_render(
				'_step_booking__recurring_bookings',
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

	public static function generate_bookings_info_for_recurring_set( array $recurring_bookings_data ): string {
		$total_selected = 0;

		foreach ( $recurring_bookings_data as $data ) {
			if ( $data['unchecked'] != 'yes' && $data['is_bookable'] ) {
				$total_selected++;
			}
		}

		$html = '<div class="rb-bookings-info"><div class="rb-bookings-info-label">' . sprintf( esc_html__( 'Found %s Available Slots', 'latepoint-pro-features' ), $total_selected ) . '</div><div class="rb-bookings-info-link-wrapper"><a href="#" class="rb-bookings-info-link">' . __( 'Review', 'latepoint-pro-features' ) . '</a></div></div>';

		return $html;
	}


	public static function generate_price_info_for_recurring_set( array $recurring_bookings_data, bool $is_bundle_scheduling = false ): string {
		$total_count    = count( $recurring_bookings_data );
		$total_selected = 0;
		$temp_cart      = new OsCartModel();


		foreach ( $recurring_bookings_data as $data ) {
			if ( $data['unchecked'] != 'yes' && $data['is_bookable'] ) {
				$total_selected++;

				$item            = new OsCartItemModel();
				$item->variant   = LATEPOINT_ITEM_VARIANT_BOOKING;
				$item->item_data = wp_json_encode( $data['booking']->generate_params_for_booking_form() );
				$temp_cart->add_item( $item, false );
			}
		}

		$html = '<div class="rb-preview-count"><div class="rb-preview-count-label">' . __( 'Selected', 'latepoint-pro-features' ) . '</div><div class="rb-preview-count-number">' . $total_selected . '</div><div class="rb-preview-count-label">' . __( 'of', 'latepoint-pro-features' ) . '</div><div class="rb-preview-count-number">' . $total_count . '</div></div>';

		if ( ! $is_bundle_scheduling && ( $temp_cart->get_subtotal() > 0 || OsSettingsHelper::is_off( 'hide_breakdown_if_subtotal_zero' ) ) ) {
			$html .= '<div class="rb-preview-price">' . OsMoneyHelper::format_price( $temp_cart->get_total(), true, false ) . '</div>';
		}

		return $html;
	}

	public static function generate_recurring_bookings_data( OsBookingModel $first_booking, array $rules, array $overrides, DateTimeZone $customer_timezone, $max_limit_of_bookings = false ): array {
		$max_limit_of_bookings = $max_limit_of_bookings ? $max_limit_of_bookings : OsFeatureRecurringBookingsHelper::get_max_limit_of_allowed_recurring_bookings( $first_booking );

		$errors              = [];
		$bookings            = [];
		$condition_satisfied = false;

		$first_booking_start_date = $first_booking->get_start_datetime_object();
		$first_booking_start_date->setTimezone( $customer_timezone );
		$loop_booking_start_date = clone $first_booking_start_date;

		$bookings[]              = $first_booking;
		$bookings_count          = 1;
		$skipped_first_identical = false;
		$i                       = 1;

		do {
			$booking = clone $first_booking;

			$modify_by = [];

			if ( $rules['repeat_unit'] == 'week' ) {
				$recurring_weekdays = explode( ',', $rules['repeat_on_weekdays'] );
				foreach ( $recurring_weekdays as $weekday ) {
					$modify_by[] = $weekday - $loop_booking_start_date->format( 'N' );
				}
			} else {
				$modify_by[] = 0;
			}

			foreach ( $modify_by as $modify ) {
				$weekday_loop_start_date = clone $loop_booking_start_date;

				$modify_str = ( $modify > 0 ) ? '+' . $modify . ' days' : $modify . ' days';
				$weekday_loop_start_date->modify( $modify_str );
				if ( $weekday_loop_start_date < $first_booking_start_date ) {
					continue;
				}
				$wp_tz_loop_booking_start_date = clone $weekday_loop_start_date;
				$wp_tz_loop_booking_start_date->setTimezone( OsTimeHelper::get_wp_timezone() );
				$booking->start_date = $wp_tz_loop_booking_start_date->format( 'Y-m-d' );
				$booking->start_time = OsTimeHelper::convert_datetime_to_minutes( $wp_tz_loop_booking_start_date );

				if ( $booking->is_start_date_and_time_set() ) {
					$booking->calculate_end_date_and_time();
					$booking->set_utc_datetimes();
				}

				// check for ending conditions before adding booking to a list
				if ( $rules['repeat_end_operator'] == 'date' ) {
					if ( $booking->start_date > $rules['repeat_end_date'] ) {
						$condition_satisfied = true;
						break;
					}
				} else {
					if ( ( $bookings_count ) >= $rules['repeat_end_counter'] ) {
						$condition_satisfied = true;
						break;
					}
				}

				if ( $bookings_count >= $max_limit_of_bookings ) {
					$condition_satisfied = true;
					if ( $first_booking->is_bundle_scheduling() ) {
						$errors[] = sprintf( esc_html__( 'This bundle has only %s remaining appointments to schedule.', 'latepoint-pro-features' ), $max_limit_of_bookings );
					} else {
						$errors[] = sprintf( esc_html__( 'You can only schedule a maximum of %s recurring bookings at once.', 'latepoint-pro-features' ), $max_limit_of_bookings );
					}
					break;
				}

				if ( $modify == 0 & ! $skipped_first_identical ) {
					// skip the first booking with the same time, because we already added it before the loop
					$skipped_first_identical = true;
				} else {
					$bookings[] = clone $booking;
					$bookings_count++;
				}
			}
			$loop_booking_start_date = clone $first_booking_start_date;
			$loop_booking_start_date->modify( '+' . ( $i * $rules['repeat_interval'] ) . ' ' . $rules['repeat_unit'] );
			$i++;

		} while ( ! $condition_satisfied );

		$recurring_bookings_data = [];

		foreach ( $bookings as $booking ) {
			$original_stamp = 'stamp_' . $booking->get_start_datetime()->getTimestamp();
			if ( ! empty( $overrides[ $original_stamp ]['custom_day'] ) ) {
				try {
					$datetime_in_customer_timezone = new OsWpDateTime( $overrides[ $original_stamp ]['custom_day'] . 'T' . OsTimeHelper::minutes_to_army_hours_and_minutes( $overrides[ $original_stamp ]['custom_minutes'] ?? '0' ), $customer_timezone );
					$original_start_datetime       = $booking->get_nice_start_datetime_for_customer( $customer_timezone );
					$datetime_in_wp_timezone       = clone $datetime_in_customer_timezone;
					$datetime_in_wp_timezone->setTimezone( OsTimeHelper::get_wp_timezone() );
					$booking->start_date = $datetime_in_wp_timezone->format( 'Y-m-d' );
					$booking->start_time = OsTimeHelper::convert_datetime_to_minutes( $datetime_in_wp_timezone );
					if ( $booking->is_start_date_and_time_set() ) {
						$booking->calculate_end_date_and_time();
						$booking->set_utc_datetimes();
						if ( $original_start_datetime != $booking->get_nice_start_datetime_for_customer( $customer_timezone ) ) {
							$recurring_bookings_data[ $original_stamp ]['original_start_datetime'] = $original_start_datetime;
							$recurring_bookings_data[ $original_stamp ]['custom_day']              = $overrides[ $original_stamp ]['custom_day'];
							$recurring_bookings_data[ $original_stamp ]['custom_minutes']          = $overrides[ $original_stamp ]['custom_minutes'];
						}
					}
				} catch ( Exception $e ) {

				}
			}
			$recurring_bookings_data[ $original_stamp ]['booking']     = $booking;
			$recurring_bookings_data[ $original_stamp ]['is_bookable'] = $booking->is_bookable(
				[
					'log_errors'          => false,
					'skip_customer_check' => true,
				]
			);
			$recurring_bookings_data[ $original_stamp ]['unchecked']   = $overrides[ $original_stamp ]['unchecked'] ?? 'no';
		}

		return [
			'bookings_data' => $recurring_bookings_data,
			'errors'        => $errors,
		];
	}

	/**
	 * @param OsWpDateTime $start
	 * @param array $rules
	 *
	 * @return string
	 */
	public static function generate_recurrence_rules_for_booking_form( OsWpDateTime $start, array $rules, OsBookingModel $booking ): string {
		$defaults = [
			'repeat_unit'            => 'week',
			'repeat_interval'        => 1,
			'repeat_on_weekdays'     => $start->format( 'N' ),
			'repeat_end_operator'    => 'count',
			'repeat_end_date'        => '',
			'repeat_end_counter'     => 10,
			'changed'                => 'yes',
			'max_repeat_end_counter' => 100,
		];

		$rules = OsUtilHelper::merge_default_atts( $defaults, $rules );

		if ( empty( $rules['repeat_end_date'] ) ) {
			$end                      = clone $start;
			$rules['repeat_end_date'] = $end->modify( '+2 months' )->format( 'Y-m-d' );
		} else {
			$end = OsWpDateTime::os_createFromFormat( 'Y-m-d', sanitize_text_field( $rules['repeat_end_date'] ) );
		}

		$start_utc = clone $start;
		$start_utc->setTimezone( new DateTimeZone( 'UTC' ) );

		$repeat_units         = [];
		$allowed_repeat_units = $booking->service->get_meta_by_key( 'allow_recurring_periods', 'all' );
		if ( $allowed_repeat_units == 'all' ) {
			$repeat_units = [
				'day'   => __( 'day', 'latepoint-pro-features' ),
				'week'  => __( 'week', 'latepoint-pro-features' ),
				'month' => __( 'month', 'latepoint-pro-features' ),
			];
		} else {
			switch ( $allowed_repeat_units ) {
				case 'day':
					$rules['repeat_unit'] = 'day';
					$repeat_units['day']  = __( 'day', 'latepoint-pro-features' );
					break;
				case 'week':
					$rules['repeat_unit'] = 'week';
					$repeat_units['week'] = __( 'week', 'latepoint-pro-features' );
					break;
				case 'month':
					$rules['repeat_unit']  = 'month';
					$repeat_units['month'] = __( 'month', 'latepoint-pro-features' );
					break;
			}
		}

		ob_start();
		?>
		<div class="os-recurrence-rules" data-route-name="<?php echo OsRouterHelper::build_route_name( 'recurring_bookings', 'reload_recurrence_rules' ); ?>"
			 data-repeat-unit="<?php echo esc_attr( $rules['repeat_unit'] ); ?>" data-ends="<?php echo esc_attr( $rules['repeat_end_operator'] ); ?>">
			<div class="os-recurrence-interval">
				<div class="os-ri-label"><?php _e( 'First on', 'latepoint-pro-features' ); ?></div>
				<div class="os-start-recurrence-datetime-picker"
					 data-start-datetime-utc="<?php echo esc_attr( $start_utc->format( LATEPOINT_DATETIME_DB_FORMAT ) ); ?>"><?php echo $start->format( OsSettingsHelper::get_readable_datetime_format() ); ?></div>
			</div>
			<div class="os-recurrence-interval">
				<div class="os-ri-label"><?php _e( 'Repeats every', 'latepoint-pro-features' ); ?></div>
				<div class="os-ri-input"><?php echo OsFormHelper::number_field( 'recurrence[rules][repeat_interval]', '', $rules['repeat_interval'], 1, 500, [ 'skip_id' => true ] ); ?></div>
				<div class="os-ri-select"><?php echo OsFormHelper::select_field( 'recurrence[rules][repeat_unit]', '', $repeat_units, $rules['repeat_unit'], [ 'skip_id' => true ] ); ?></div>
			</div>
			<div class="os-recurrence-weekdays">
				<?php
				$start_of_week = OsSettingsHelper::get_start_of_week();
				$weekdays      = OsBookingHelper::get_weekdays_arr();

				$repeat_on_weekdays_arr = explode( ',', $rules['repeat_on_weekdays'] );

				// Output the divs for each weekday
				for ( $i = $start_of_week - 1; $i < $start_of_week - 1 + 7; $i++ ) {
					// Calculate the index within the range of 0-6
					$index    = $i % 7;
					$selected = ( in_array( $i + 1, $repeat_on_weekdays_arr ) ) ? 'os-weekday-selected' : '';

					// Output the div for the current weekday
					echo '<div class="weekday weekday-' . esc_attr( $index + 1 ) . ' ' . $selected . '" data-weekday="' . ( $index + 1 ) . '">' . esc_html( $weekdays[ $index ] ) . '</div>';
				}
				echo OsFormHelper::hidden_field( 'recurrence[rules][repeat_on_weekdays]', $rules['repeat_on_weekdays'], [ 'skip_id' => true ] );
				?>
			</div>
			<div class="os-recurrence-ends">
				<div class="os-ri-label"><?php _e( 'Ends', 'latepoint-pro-features' ); ?></div>
				<div class="os-ri-select">
				<?php
					echo OsFormHelper::select_field(
						'recurrence[rules][repeat_end_operator]',
						'',
						[
							'count' => __( 'After', 'latepoint-pro-features' ),
							'date'  => __( 'On', 'latepoint-pro-features' ),
						],
						$rules['repeat_end_operator'],
						[ 'skip_id' => true ]
					);
				?>
				</div>
				<div class="os-ri-end-option-count">
					<div class="os-ri-input"><?php echo OsFormHelper::number_field( 'recurrence[rules][repeat_end_counter]', '', $rules['repeat_end_counter'], 2, $rules['max_repeat_end_counter'], [ 'skip_id' => true ] ); ?></div>
					<div class="os-ri-label"><?php _e( 'occurrences', 'latepoint-pro-features' ); ?></div>
				</div>
				<div class="os-ri-end-option-date">
					<div class="os-end-recurrence-datetime-picker" data-preselected-day="<?php echo esc_attr( $rules['repeat_end_date'] ) ?>"
						 data-route-name="<?php echo esc_attr( OsRouterHelper::build_route_name( 'recurring_bookings', 'pick_end_date_on_calendar' ) ); ?>"><?php echo $end->format( OsSettingsHelper::get_readable_date_format() ); ?></div>
					<?php echo OsFormHelper::hidden_field( 'recurrence[rules][repeat_end_date]', $rules['repeat_end_date'], [ 'skip_id' => true ] ); ?>
				</div>
			</div>
			<?php echo OsFormHelper::hidden_field( 'recurrence[rules][changed]', $rules['changed'], [ 'skip_id' => true ] ); ?>
		</div>
		<?php
		$html = ob_get_clean();

		return $html;
	}

	/**
	 *
	 * @param array $connected_bookings
	 *
	 * @return string
	 */
	public static function output_recurrent_bookings_summary( array $connected_bookings, bool $skip_first = true ): string {
		$html           = '';
		$total_bookings = $skip_first ? count( $connected_bookings ) - 1 : count( $connected_bookings );
		if ( empty( $total_bookings ) ) {
			return $html;
		}

		$html .= '<div class="os-recurring-bookings-wrapper">';
		$html .= '<div class="os-recurring-bookings-count">' . sprintf( __( '+%s Recurring Appointments:', 'latepoint-pro-features' ), $total_bookings ) . '</div>';
		$html .= '<div class="os-recurring-unfolded-bookings">';
		$i     = 0;
		foreach ( $connected_bookings as $booking ) {
			$i++;
			// skip the first one because it's time is printed in the main summary
			if ( $skip_first && $i == 1 ) {
				continue;
			}
			$html .= '<div class="os-recurring-unfolded-booking">';
			$html .= '<div>' . $booking->get_nice_start_date_for_customer( false, true ) . '</div>';
			$html .= '<div>' . $booking->get_nice_start_time_for_customer() . '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
		if ( count( $connected_bookings ) > LATEPOINT_RECURRING_BOOKINGS_UNFOLDED_COUNT ) {
			$html .= '<div class="os-recurring-bookings-unfold"><i class="latepoint-icon latepoint-icon-chevron-right"></i><div>' . __( 'show all', 'latepoint-pro-features' ) . '</div></div>';
		}
		$html .= '</div>';
		return $html;
	}
}
