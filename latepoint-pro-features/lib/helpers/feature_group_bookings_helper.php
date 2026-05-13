<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureGroupBookingsHelper {

	public static string $step_code = 'booking__group_bookings';


	public static function add_total_attendees_to_booking_form_params( array $params, OsBookingModel $booking ) {
		if ( ! empty( $booking->total_attendees ) ) {
			$params['total_attendees'] = $booking->total_attendees;
		}

		return $params;
	}

	public static function add_step_show_next_btn_rules( $rules, $step_code ): array {
		$rules[ self::$step_code ] = true;

		return $rules;
	}

	public static function should_step_be_skipped( bool $skip, string $step_code, OsCartModel $cart, OsCartItemModel $cart_item, OsBookingModel $booking ): bool {
		if ( $step_code == self::$step_code ) {
			if ( $booking->is_part_of_bundle() ) {
				// bundle bookings have attendee quantity, no need to ask customer for it
				$skip = true;
			} else {
				if ( $cart_item->is_booking() ) {
					$booking = $cart_item->build_original_object_from_item_data();
					if ( empty( $booking->service_id ) || ! $booking->service->should_show_capacity_selector() ) {
						$skip = true;
					}
				} else {
					$skip = true;
				}
			}
		}

		return $skip;
	}

	public static function load_step_group_bookings( $step_code, $format = 'json' ) {
		if ( $step_code == self::$step_code ) {

			$group_bookings_controller                            = new OsGroupBookingsController();
			$group_bookings_controller->vars['booking']           = OsStepsHelper::$booking_object;
			$group_bookings_controller->vars['current_step_code'] = $step_code;
			$group_bookings_controller->set_layout( 'none' );
			$group_bookings_controller->set_return_format( $format );
			$group_bookings_controller->format_render(
				'_step_booking__group_bookings',
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
		$labels[ self::$step_code ] = __( 'Total Attendees', 'latepoint-pro-features' );

		return $labels;
	}


	public static function remove_group_bookings_step_if_preselected( array $presets, OsCartItemModel $active_cart_item, OsBookingModel $booking, OsCartModel $cart ) {
		if ( ! empty( $presets['selected_total_attendees'] ) || $booking->is_part_of_bundle() ) {
			OsStepsHelper::remove_step_by_name( 'booking__group_bookings' );
		}
	}


	public static function add_settings_for_step( array $settings ): array {
		$settings[ self::$step_code ] = [
			'side_panel_heading'     => 'Total Attendees',
			'side_panel_description' => 'Please select how many people are coming',
			'main_panel_heading'     => 'Total Attendees',
		];

		return $settings;
	}

	public static function add_step_for_group_bookings( array $steps ): array {
		$steps[ self::$step_code ] = [
			'after'  => 'services',
			'before' => 'datepicker',
		];

		return $steps;
	}

	public static function add_data_to_webhook( $vars, $booking ) {
		if ( $booking->total_attendees ) {
			$vars['total_attendees'] = $booking->total_attendees;
		}

		return $vars;
	}

	public static function adjust_deposit_amount_for_service( $amount, OsBookingModel $booking ) {
		if ( ( $booking->total_attendees > 1 ) && ! OsUtilHelper::is_on( $booking->service->get_meta_by_key( 'dont_multiply_deposit_amount_by_attendees', 'off' ) ) ) {
			$amount = $amount * $booking->total_attendees;
		}

		return $amount;
	}


	public static function adjust_full_amount_for_service( $amount, OsBookingModel $booking ) {
		if ( ( $booking->total_attendees > 1 ) && ! OsUtilHelper::is_on( $booking->service->get_meta_by_key( 'dont_multiply_charge_amount_by_attendees', 'off' ) ) ) {
			$amount = $amount * $booking->total_attendees;
		}

		return $amount;
	}

	public static function adjust_full_amount_for_service_extra( $amount, OsBookingModel $booking, OsServiceExtraModel $service_extra ) {
		if ( $booking->total_attendees > 1 && OsUtilHelper::is_on( $service_extra->multiplied_by_attendees ) ) {
			$amount = $amount * $booking->total_attendees;
		}

		return $amount;
	}

	public static function add_capacity_info_to_service_tile( $service ) {
		if ( $service->capacity_min && $service->capacity_max ) {
			?>
			<div class="service-info-row">
				<div class="label"><?php _e( 'Capacity:', 'latepoint-pro-features' ); ?></div>
				<div class="value">
					<strong><?php echo ( $service->capacity_min == $service->capacity_max ) ? $service->capacity_max : $service->capacity_min . ' - ' . $service->capacity_max; ?></strong> <?php _e( 'person', 'latepoint-pro-features' ); ?>
				</div>
			</div>
			<?php
		}
	}


	public static function add_service_extra_settings( $service_extra ) {
		echo OsFormHelper::checkbox_field( 'service_extra[multiplied_by_attendees]', __( 'Multiply cost of this service extra by number of attendees', 'latepoint-pro-features' ), 'on', OsUtilHelper::is_on( $service_extra->multiplied_by_attendees ) );
	}

	public static function replace_booking_vars_for_group_bookings( $text, $booking ) {
		$needles = [ '{{total_attendees}}' ];

		$replacements = [ $booking->total_attendees ];
		$text         = str_replace( $needles, $replacements, $text );

		return $text;
	}

	public static function save_service_info( $service, $is_new_record, $service_params ) {
		if ( $service ) {
			$value = isset( $service_params['fixed_total_attendees'] ) ? $service_params['fixed_total_attendees'] : 'off';
			$service->save_meta_by_key( 'fixed_total_attendees', $value );

			$value = isset( $service_params['block_timeslot_when_minimum_capacity_met'] ) ? $service_params['block_timeslot_when_minimum_capacity_met'] : 'off';
			$service->save_meta_by_key( 'block_timeslot_when_minimum_capacity_met', $value );

			$value = isset( $service_params['dont_multiply_charge_amount_by_attendees'] ) ? $service_params['dont_multiply_charge_amount_by_attendees'] : 'off';
			$service->save_meta_by_key( 'dont_multiply_charge_amount_by_attendees', $value );

			$value = isset( $service_params['dont_multiply_deposit_amount_by_attendees'] ) ? $service_params['dont_multiply_deposit_amount_by_attendees'] : 'off';
			$service->save_meta_by_key( 'dont_multiply_deposit_amount_by_attendees', $value );

			$value = isset( $service_params['virtual_meeting_mode'] ) && in_array( $service_params['virtual_meeting_mode'], array( 'shared', 'separate' ), true ) ? $service_params['virtual_meeting_mode'] : 'separate';
			$service->save_meta_by_key( 'virtual_meeting_mode', $value );
		}
	}

	/**
	 * Check if the service uses shared meeting mode for group bookings.
	 * In shared mode, all bookings in the same group slot share one meeting link.
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public static function is_shared_meeting_mode( int $service_id ): bool {
		return OsMetaHelper::get_service_meta_by_key( 'virtual_meeting_mode', $service_id, 'separate' ) === 'shared';
	}

	/**
	 * Check if at least one enabled external meeting system supports shared meeting mode.
	 * Used to conditionally show the Meeting Link Mode selector on the service edit form.
	 *
	 * @return bool
	 */
	public static function has_meeting_system_with_shared_mode_support(): bool {
		foreach ( OsMeetingSystemsHelper::get_list_of_external_meeting_systems( true ) as $system ) {
			if ( ! empty( $system['shared_meeting_mode'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all non-cancelled bookings in the same group slot as the given booking.
	 *
	 * @param OsBookingModel $booking The reference booking.
	 * @return OsBookingModel[]
	 */
	public static function get_group_bookings_for_slot( OsBookingModel $booking ): array {
		$group_bookings = new OsBookingModel();
		return $group_bookings->where(
			array(
				'start_datetime_utc' => $booking->start_datetime_utc,
				'end_datetime_utc'   => $booking->end_datetime_utc,
				'service_id'         => $booking->service_id,
				'location_id'        => $booking->location_id,
				'agent_id'           => $booking->agent_id,
			)
		)->should_not_be_cancelled()->get_results_as_models();
	}

	public static function add_group_bookings_vars() {
		echo '<li><span class="var-label">' . __( 'Total Attendees', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{{total_attendees}}</span></li>';
	}


	public static function add_capacity_to_service_attributes( $attributes, $booking ) {
		if ( $booking->service_id && $booking->service->should_show_capacity_selector() ) {
			$attributes[] = [
				'label' => __( 'Attendees', 'latepoint-pro-features' ),
				'value' => sprintf( _n( '%s person', '%s people', $booking->total_attendees, 'latepoint-pro-features' ), $booking->total_attendees ),
			];
		}

		return $attributes;
	}

	public static function add_attendees_to_service_row_item( array $service_row_item, OsBookingModel $booking ) {
		if ( ! empty( $service_row_item['items'] ) && ( $booking->total_attendees > 1 ) && OsUtilHelper::is_off( $booking->service->get_meta_by_key( 'dont_multiply_charge_amount_by_attendees', 'off' ) ) ) {
			$service_price = OsMoneyHelper::format_price( $booking->service->get_full_amount_for_duration( $booking->duration ), true, false );
			foreach ( $service_row_item['items'] as &$service_item ) {
				$service_item['note'] = '(' . $booking->total_attendees . ' x ' . $service_price . ')';
			}
		}

		return $service_row_item;
	}

	public static function output_total_attendees_on_quick_form( $booking, $order_item_id ) {
		$service          = $booking->service;
		$capacity_min     = empty( $service->capacity_min ) ? 1 : $service->capacity_min;
		$capacity_max     = empty( $service->capacity_max ) ? 1 : $service->capacity_max;
		$capacity_options = [];
		for ( $i = $capacity_min; $i <= $capacity_max; $i++ ) {
			$capacity_options[] = $i;
		}
		$hide = ( $booking->service_id && $booking->service->capacity_max > 1 ) ? '' : 'display: none;';
		echo '<div class="booking-total-attendees-selector-w" style="' . $hide . '">';
		echo '<div class="os-row">';
		echo '<div class="os-col-6">';
		echo OsFormHelper::select_field( 'order_items[' . $order_item_id . '][bookings][' . $booking->get_form_id() . '][total_attendees]', __( 'Total Attendees', 'latepoint-pro-features' ), $capacity_options, $booking->total_attendees, [ 'class' => 'os-affects-price' ] );
		echo '</div>';
		echo '<div class="os-col-6">';
		echo '<div class="capacity-info"><span>' . __( 'Max Capacity:', 'latepoint-pro-features' ) . '</span><strong>' . $capacity_max . '</strong></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}


	public static function output_capacity_on_service_form( $service ) {
		?>
		<div class="white-box section-anchor" id="stickySectionGroupBookings">
			<div class="white-box-header">
				<div class="os-form-sub-header"><h3><?php _e( 'Group Bookings', 'latepoint-pro-features' ); ?></h3>
				</div>
			</div>
			<div class="white-box-content no-padding">
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e( 'Capacity', 'latepoint-pro-features' ) ?></h3>
					</div>
					<div class="sub-section-content">
						<div class="os-row">
							<div class="os-col-lg-2">
								<?php echo OsFormHelper::text_field( 'service[capacity_min]', __( 'Minimum', 'latepoint-pro-features' ), $service->capacity_min, [ 'theme' => 'bordered' ] ); ?>
							</div>
							<div class="os-col-lg-2">
								<?php echo OsFormHelper::text_field( 'service[capacity_max]', __( 'Maximum', 'latepoint-pro-features' ), $service->capacity_max, [ 'theme' => 'bordered' ] ); ?>
							</div>
						</div>
					</div>
				</div>
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e( 'Pricing', 'latepoint-pro-features' ) ?></h3>
					</div>
					<div class="sub-section-content">
						<?php echo OsFormHelper::toggler_field( 'service[dont_multiply_charge_amount_by_attendees]', __( 'Do not multiply charge amount by the number of attendees', 'latepoint-pro-features' ), OsUtilHelper::is_on( $service->get_meta_by_key( 'dont_multiply_charge_amount_by_attendees', 'off' ) ) ); ?>
						<?php echo OsFormHelper::toggler_field( 'service[dont_multiply_deposit_amount_by_attendees]', __( 'Do not multiply deposit amount by the number of attendees', 'latepoint-pro-features' ), OsUtilHelper::is_on( $service->get_meta_by_key( 'dont_multiply_deposit_amount_by_attendees', 'off' ) ) ); ?>
					</div>
				</div>
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e( 'Other', 'latepoint-pro-features' ) ?></h3>
					</div>
					<div class="sub-section-content">
						<?php echo OsFormHelper::toggler_field( 'service[fixed_total_attendees]', __( 'Do not ask customers to select number of attendees', 'latepoint-pro-features' ), OsUtilHelper::is_on( $service->get_meta_by_key( 'fixed_total_attendees', 'off' ) ) ); ?>
						<?php echo OsFormHelper::toggler_field( 'service[block_timeslot_when_minimum_capacity_met]', __( 'Block timeslot if minimum capacity is reached', 'latepoint-pro-features' ), OsUtilHelper::is_on( $service->get_meta_by_key( 'block_timeslot_when_minimum_capacity_met', 'off' ) ) ); ?>
					</div>
				</div>
				<?php if ( self::has_meeting_system_with_shared_mode_support() ) : ?>
				<div class="sub-section-row">
					<div class="sub-section-label">
						<h3><?php _e( 'Meeting Link', 'latepoint-pro-features' ) ?></h3>
					</div>
					<div class="sub-section-content">
						<div class="os-row">
							<div class="os-col-lg-12">
								<?php
								echo OsFormHelper::select_field(
									'service[virtual_meeting_mode]',
									__( 'Meeting Link Mode for Group Bookings', 'latepoint-pro-features' ),
									array(
										array(
											'value' => 'separate',
											'label' => __( 'Separate - Each booking gets its own meeting link', 'latepoint-pro-features' ),
										),
										array(
											'value' => 'shared',
											'label' => __( 'Shared - All attendees in the same time slot get the same meeting link', 'latepoint-pro-features' ),
										),
									),
									$service->get_meta_by_key( 'virtual_meeting_mode', 'separate' )
								);
								?>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}


	public static function add_columns_to_bookings_table( $columns ) {
		$columns['booking']['total_attendees'] = __( 'Total Attendees', 'latepoint-pro-features' );

		return $columns;
	}


	public static function add_columns_to_booking_row_for_csv( $booking_row, $booking, $params = [] ) {
		$booking_row[] = $booking->total_attendees;

		return $booking_row;
	}


	public static function add_columns_to_bookings_data_for_csv( $bookings_data, $params = [] ) {
		$bookings_data[0][] = __( 'Total Attendees', 'latepoint-pro-features' );

		return $bookings_data;
	}

	public static function add_svg_for_step( string $svg, string $step_code ) {
		if ( $step_code == self::$step_code ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
					<path class="latepoint-step-svg-highlight" d="M12.4475956,46.2568436c-0.1044884,1.7254677-0.2875328,2.2941246,0.1235962,3.2275391 c0.2800293,1.0578613,1.2532349,2.0065918,2.4077148,2.4970703c2.5679932,1.0912819,3.8084583,0.576416,36.5757446,0.7905273 c1.5809326,0.0102539,4.2476807-0.1374512,5.786499-0.4538574c2.1460648-0.4416046,4.1996078-1.119503,4.6765137-3.3955078 c0.1690674-0.3930664,0.2585449-0.8137207,0.2453613-1.244873c-0.0195313-0.6503906-0.0566406-1.3046875-0.1044922-1.9511719 c-0.1210938-1.6845703-1.6621094-2.9892578-3.5175781-2.9892578c-0.015625,0-0.03125,0-0.046875,0l-42.6777344,0.5214844 C14.0725956,43.2812576,12.5491581,44.5976639,12.4475956,46.2568436z M58.6409569,44.2373123 c1.0712891,0,1.9560547,0.6972656,2.0214844,1.5976563c0.0458984,0.6259766,0.0830078,1.2587891,0.1005859,1.8876953 c0.0309868,1.0110512-0.9663086,1.7237892-2.0117188,1.7304688c-14.3534698,0.0823135-28.739151,0.728199-42.9609375,0.5419922 c-1.0929708-0.0137672-2.0631294-0.8028984-1.9785156-1.8085938c0.0527344-0.6113281,0.0957031-1.2294922,0.1337891-1.8378906 c0.0537109-0.8789063,0.9267578-1.5771484,1.9882813-1.5898438C16.0340576,44.757576,58.7426338,44.2373123,58.6409569,44.2373123z "/>
					<path class="latepoint-step-svg-base" d="M58.2141991,6.9736419l-0.5214844,4.9931645c-0.0457916,0.4391737,0.2963982,0.828125,0.7470703,0.828125 c0.3789063,0,0.7050781-0.2861328,0.7451172-0.671875l0.5214844-4.9931645 c0.0429688-0.4121094-0.2558594-0.78125-0.6679688-0.8242188C58.6360741,6.256845,58.2571678,6.5605559,58.2141991,6.9736419z"/>
					<path class="latepoint-step-svg-base" d="M65.2903671,8.9316502l-3.6796837,3.6767578c-0.4748344,0.4748325-0.1306915,1.2802734,0.5302734,1.2802734 c0.1914063,0,0.3837891-0.0732422,0.5302734-0.2197266L66.350914,9.992197c0.2929688-0.2929688,0.2929688-0.7675781,0-1.0605469 C66.0589218,8.639658,65.5843124,8.6377048,65.2903671,8.9316502z"/>
					<path class="latepoint-step-svg-base" d="M68.8108749,16.1767673c-0.1835938-0.3710938-0.6347656-0.5234375-1.0048828-0.3388672 c-1.1025391,0.5478516-2.3320313,0.7939453-3.5585938,0.7119141c-0.4033165-0.0234375-0.770504,0.2851563-0.7978477,0.6982422 s0.2851563,0.7705078,0.6982384,0.7978516c1.4586029,0.0992756,2.9659576-0.1902256,4.3242188-0.8642578 C68.8431015,16.9970798,68.9944687,16.5468845,68.8108749,16.1767673z"/>
					<path class="latepoint-step-svg-highlight" d="M7.0583744,24.3901463c1.7924805,0.6647949,3.8635864,0.6894531,5.857666,0.7006836 c12.414856,0.0710449,23.6358051,0.019043,36.0507202,0.0898438c1.8114014,0.0102539,4.8669434-0.1374512,6.630127-0.4538574 c1.7630615-0.3166504,3.4486084-0.7158203,4.5030518-1.8364258c0.5599365-0.5949707,0.8862305-1.326416,0.9301758-2.0551758 c0.1284103-0.495512,0.1391678-0.7500668-0.0229492-2.7072754c-0.125988-1.5260391-1.6530342-2.9814453-3.9726563-2.9814453 L8.1350956,15.6670017c-2.0859375,0.0224609-3.7490234,1.3085938-3.8671875,2.9931641 c-0.131978,1.8722496-0.2533808,2.0809135-0.0430298,2.7998047C4.332056,22.6867771,5.5573368,23.8335056,7.0583744,24.3901463z M5.7640018,18.764658c0.0615234-0.8681641,1.1318359-1.5849609,2.3867188-1.5976563l48.8994141-0.5205078 c1.2441406-0.0126953,2.3886719,0.7070313,2.4628906,1.6044922c0.0517578,0.625,0.09375,1.2558594,0.1142578,1.8818359 c0.0375061,1.0384789-1.2411385,1.7228012-2.4140625,1.7285156c-16.2836723,0.0816097-33.0511169,0.7308216-49.2275391,0.5429688 c-1.1799021-0.0141487-2.4750004-0.7440434-2.3740234-1.8007813C5.6712284,19.9912205,5.7220097,19.3730564,5.7640018,18.764658z" />
					<path class="latepoint-step-svg-highlight" d="M25.6985722,38.054451c1.9748383,1.0864716,2.6161232,0.5729103,28.2541523,0.7905273 c1.2214355,0.0102539,3.28125-0.1374512,4.4699707-0.4538574c1.6699829-0.4448471,2.8914299-1.0308228,3.4542236-2.7290039 c0.6960297-1.1023483,0.5326729-2.1277504,0.4388428-3.850584c-0.0966797-1.7070313-1.40625-3.0332031-2.9306641-3.0009766 l-32.9677734,0.5205078c-1.5166016,0.0253906-2.765625,1.3466797-2.8447266,3.0097637 c-0.0829926,1.7514267-0.3514214,2.8246078,0.5612793,4.0524902C24.4834843,37.0983963,25.0513554,37.698494,25.6985722,38.054451z M25.0706425,32.4111404c0.0419922-0.8740215,0.6445313-1.5683575,1.3710938-1.5800762l32.9667969-0.5205078 c0.0058594,0,0.0117188,0,0.0175781,0c0.7314453,0,1.3417969,0.6923828,1.3916016,1.5839844 c0.0351563,0.6289043,0.0634766,1.2646465,0.078125,1.8945293c0.0201225,0.8820457-0.556736,1.731514-1.3867188,1.7373047 c-10.9964714,0.0815811-22.1932869,0.7267456-33.1787109,0.5419922c-0.7375622-0.013092-1.4293518-0.7859573-1.3623047-1.8242188 C25.0081425,33.6347733,25.0423222,33.0185623,25.0706425,32.4111404z"/>
					<path class="latepoint-step-svg-highlight" d="M62.451992,63.2775955c0.5789719-1.0259094,0.4419289-1.8840179,0.3344727-3.6164551 c-0.1044922-1.6894531-1.4648438-2.9960938-3.1064453-2.9960938c-0.0146484,0-0.0302734,0-0.0449219,0l-36.3544922,0.5205078 c-1.6298828,0.0234375-2.9755859,1.3427734-3.0634766,3.0048828c-0.09375,1.795887-0.3370171,2.6628914,0.4232788,3.8208008 c0.3649292,0.8071289,1.0519409,1.5019531,1.8442383,1.8972168c2.1949348,1.0950089,3.3277054,0.5763168,31.1570454,0.7905273 c1.3469238,0.0102539,3.6184082-0.1374512,4.9293213-0.4538574C60.4500313,65.7912064,61.8896866,65.1745071,62.451992,63.2775955z M59.7708397,63.3798904c-12.1266251,0.0816307-24.4732285,0.7282944-36.5908203,0.5419922 c-0.9430161-0.0149651-1.6459942-0.8662491-1.578125-1.8183594c0.0439453-0.6103516,0.0820313-1.2265625,0.1132813-1.8339844 c0.0458984-0.8769531,0.7431641-1.5722656,1.5869141-1.5839844l36.3544922-0.5205078 c0.9013672-0.0332031,1.5761719,0.6855469,1.6328125,1.5888672c0.0390625,0.6289063,0.0693359,1.2617188,0.0859375,1.8916016 C61.4014854,62.6212692,60.6525688,63.3738251,59.7708397,63.3798904z"/>
				</svg>';
		}
		return $svg;
	}
}
