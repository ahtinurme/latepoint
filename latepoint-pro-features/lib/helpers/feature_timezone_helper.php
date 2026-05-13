<?php

class OsFeatureTimezoneHelper {


	public static function output_timezone_after_datepicker( OsBookingModel $booking, OsWpDateTime $target_date, array $settings = [] ) {
		if ( OsSettingsHelper::is_on( 'steps_show_timezone_selector' ) ) {
			echo '<div class="os-timezone-info-wrapper"><div class="os-timezone-info-label">' . esc_html__( 'Timezone:', 'latepoint-pro-features' ) . ' </div><div class="os-timezone-info-value" data-route="' . esc_attr( OsRouterHelper::build_route_name( 'timezone_selector', 'timezone_picker' ) ) . '"><span>' . OsFeatureTimezoneHelper::nice_timezone_name( $settings['timezone_name'] ?? OsTimeHelper::get_wp_timezone_name() ) . '</span></div></div>';
		}
	}

	public static function nice_timezone_name( $timezone_name ) {
		if ( empty( $timezone_name ) ) {
			return esc_html__( 'n/a', 'latepoint-pro-features' );
		}
		$timezone_split = explode( '/', $timezone_name );
		if ( count( $timezone_split ) ) {
			return str_replace( '_', ' ', end( $timezone_split ) );
		} else {
			return $timezone_name;
		}
	}

	public static function output_booking_datetime_in_timezone( string $booking_datetime, OsBookingModel $booking, string $viewer ): string {
		if ( $viewer == 'customer' && $booking->customer_id ) {
			$timezone_name = $booking->customer->get_selected_timezone_name();
		} else {
			$timezone_name = OsTimeHelper::get_wp_timezone_name();
		}
		if ( $booking->start_time ) {
			$start_datetime = OsWpDateTime::os_createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $booking->start_date . ' ' . OsTimeHelper::minutes_to_army_hours_and_minutes( $booking->start_time ) . ':00', new DateTimeZone( OsTimeHelper::get_wp_timezone_name() ) );
			$end_datetime   = OsWpDateTime::os_createFromFormat( LATEPOINT_DATETIME_DB_FORMAT, $booking->end_date . ' ' . OsTimeHelper::minutes_to_army_hours_and_minutes( $booking->end_time ) . ':00', new DateTimeZone( OsTimeHelper::get_wp_timezone_name() ) );
			if ( $start_datetime && $end_datetime ) {
				$start_datetime->setTimeZone( new DateTimeZone( $timezone_name ) );
				$end_datetime->setTimeZone( new DateTimeZone( $timezone_name ) );

				$date_format     = OsSettingsHelper::get_readable_date_format();
				$time_format     = OsSettingsHelper::get_readable_time_format();
				$datetime_format = OsSettingsHelper::get_readable_datetime_format();

				if ( $start_datetime->format( 'Y-m-d' ) === $end_datetime->format( 'Y-m-d' ) ) {
					// same day, just add end time
					$booking_datetime = OsUtilHelper::translate_months( $start_datetime->format( $datetime_format ) );
					if ( OsSettingsHelper::is_on( 'show_booking_end_time' ) ) {
						$booking_datetime .= ' - ' . $end_datetime->format( $time_format );
					}
				} else {
					// different days, display both days
					$booking_datetime = OsUtilHelper::translate_months( $start_datetime->format( $datetime_format ) );
					if ( OsSettingsHelper::is_on( 'show_booking_end_time' ) ) {
						$booking_datetime .= ' - ' . $end_datetime->format( $datetime_format );
					}
				}
			} else {
				return __( 'Invalid Date/Time', 'latepoint-pro-features' );
			}

			if ( OsSettingsHelper::is_on( 'steps_show_timezone_info' ) ) {
				$booking_datetime .= ' <span class="os-timezone-info">(' . self::nice_timezone_name( $timezone_name ) . ')</span>';
			}
		}
		return $booking_datetime;
	}

	public static function add_timezone_selector_to_customer_dashboard( $customer ) {
		if ( OsSettingsHelper::is_on( 'steps_show_timezone_selector' ) ) {
			echo '<div class="latepoint-customer-timezone-selector-w" data-route-name="' . OsRouterHelper::build_route_name( 'timezone_selector', 'change_timezone' ) . '">';
			echo OsFormHelper::select_field( 'latepoint_timezone_selector', __( 'My Timezone:', 'latepoint-pro-features' ), OsTimeHelper::timezones_options_list( $customer->get_selected_timezone_name() ), $customer->get_selected_timezone_name() );
			echo '</div>';
		}
	}

	public static function add_timezone_information_to_datepicker( $booking, $timezone_name = false ) {
		if ( OsSettingsHelper::is_on( 'steps_show_timezone_info' ) ) {
			$timezone_name = $timezone_name ? $timezone_name : OsTimeHelper::get_timezone_name_from_session();
			echo '<div class="th-timezone"><strong>' . __( 'Timezone:', 'latepoint-pro-features' ) . '</strong> ' . self::nice_timezone_name( $timezone_name ) . '</div>';
		}
	}

	public static function add_timezone_vars_for_booking() {
		echo '<li><span class="var-label">' . __( 'Start Date (in customer timezone):', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{{start_date_customer_timezone}}</span></li>';
		echo '<li><span class="var-label">' . __( 'Start Time (in customer timezone):', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{{start_time_customer_timezone}}</span></li>';
		echo '<li><span class="var-label">' . __( 'End Time (in customer timezone)', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{{end_time_customer_timezone}}</span></li>';
	}


	public static function apply_timeshift_to_resources_grouped_by_day( $daily_resources, $booking_request, $date_from, $date_to, $settings ) {
		if ( ! empty( $settings['timezone_name'] ) && $settings['timezone_name'] != OsTimeHelper::get_wp_timezone_name() ) {
			$timezone_name         = $settings['timezone_name'];
			$timezone              = new DateTimeZone( $timezone_name );
			$resources_to_be_moved = [];
			for ( $day_date = clone $date_from; $day_date->format( 'Y-m-d' ) <= $date_to->format( 'Y-m-d' ); $day_date->modify( '+1 day' ) ) {
				$next_day = clone $day_date;
				$next_day->modify( '+1 day' );
				$prev_day = clone $day_date;
				$prev_day->modify( '-1 day' );
				$resources_to_be_moved_to_next_day = [];
				$total_resources                   = count( $daily_resources[ $day_date->format( 'Y-m-d' ) ] );
				for ( $i = 0; $i < $total_resources;$i++ ) {
					$temp_resource_for_next_day_move              = new \LatePoint\Misc\BookingResource();
					$temp_resource_for_next_day_move->agent_id    = $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->agent_id;
					$temp_resource_for_next_day_move->service_id  = $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->service_id;
					$temp_resource_for_next_day_move->location_id = $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->location_id;

					// loop and apply timeshift to WORK TIME PERIODS
					// -----------
					$total_work_time_periods = count( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods );
					for ( $j = 0; $j < $total_work_time_periods; $j++ ) {
						$new_work_period_for_prev_day = false;
						$new_work_period_for_next_day = false;

						$tz_start = new OsWpDateTime( $day_date->format( 'Y-m-d' ) . 'T' . OsTimeHelper::minutes_to_army_hours_and_minutes( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->start_time ) . ':00' );
						$tz_end   = new OsWpDateTime( $day_date->format( 'Y-m-d' ) . 'T' . OsTimeHelper::minutes_to_army_hours_and_minutes( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->end_time ) . ':00' );
						$tz_start->setTimezone( $timezone );
						$tz_end->setTimezone( $timezone );

						if ( $tz_start->format( 'Y-m-d' ) == $day_date->format( 'Y-m-d' ) && $tz_end->format( 'Y-m-d' ) == $day_date->format( 'Y-m-d' ) ) {
							// day hasn't changed, just update start and end times
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->end_time   = OsTimeHelper::convert_datetime_to_minutes( $tz_end );
						} elseif ( $tz_start->format( 'Y-m-d' ) < $day_date->format( 'Y-m-d' ) && $tz_end->format( 'Y-m-d' ) < $day_date->format( 'Y-m-d' ) ) {
							// both start and end of work period should be moved to a previous day
							$new_work_period_for_prev_day             = new \LatePoint\Misc\TimePeriod();
							$new_work_period_for_prev_day->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$new_work_period_for_prev_day->end_time   = OsTimeHelper::convert_datetime_to_minutes( $tz_end );
							// remove work periods from current day, because it was fully moved to previous day
							unset( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ] );
						} elseif ( $tz_start->format( 'Y-m-d' ) > $day_date->format( 'Y-m-d' ) && $tz_end->format( 'Y-m-d' ) > $day_date->format( 'Y-m-d' ) ) {
							// both start and end of work period should be moved to a next day
							$new_work_period_for_next_day             = new \LatePoint\Misc\TimePeriod();
							$new_work_period_for_next_day->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$new_work_period_for_next_day->end_time   = OsTimeHelper::convert_datetime_to_minutes( $tz_end );
							// remove work periods from current day, because it was fully moved to next day
							unset( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ] );
						} elseif ( $tz_start->format( 'Y-m-d' ) < $day_date->format( 'Y-m-d' ) ) {
							// only start time is leaking to previous day, create new period with a cutoff
							$new_work_period_for_prev_day             = new \LatePoint\Misc\TimePeriod();
							$new_work_period_for_prev_day->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$new_work_period_for_prev_day->end_time   = 24 * 60 - 1;
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->start_time = 0;
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->end_time   = OsTimeHelper::convert_datetime_to_minutes( $tz_end );
						} elseif ( $tz_end->format( 'Y-m-d' ) > $day_date->format( 'Y-m-d' ) ) {
							// only end time is leaking to the next day, create new period with a cutoff
							$new_work_period_for_next_day             = new \LatePoint\Misc\TimePeriod();
							$new_work_period_for_next_day->start_time = 0;
							$new_work_period_for_next_day->end_time   = OsTimeHelper::convert_datetime_to_minutes( $tz_end );
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_time_periods[ $j ]->end_time   = 24 * 60 - 1;
						}


						if ( $new_work_period_for_next_day ) {
							$temp_resource_for_next_day_move->work_time_periods[] = $new_work_period_for_next_day;
						}
						if ( $new_work_period_for_prev_day ) {
							if ( isset( $daily_resources[ $tz_start->format( 'Y-m-d' ) ] ) ) {
								for ( $p = 0; $p < count( $daily_resources[ $tz_start->format( 'Y-m-d' ) ] );$p++ ) {
									if ( $daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->agent_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->agent_id
										&& $daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->service_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->service_id
										&& $daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->location_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->location_id ) {
										// same resource found - add those work periods to this resource
											// add to the end of prev day
											$daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->work_time_periods[] = $new_work_period_for_prev_day;
									}
								}
							}
						}
					}

					// loop and apply timeshift to booking SLOTS
					// ----------
					$total_slots = count( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots );
					for ( $j = 0; $j < $total_slots; $j++ ) {
						$new_slot_for_prev_day = false;
						$new_slot_for_next_day = false;


						$tz_start = new OsWpDateTime( $day_date->format( 'Y-m-d' ) . 'T' . OsTimeHelper::minutes_to_army_hours_and_minutes( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ]->start_time ) . ':00' );
						$tz_start->setTimezone( $timezone );

						if ( $tz_start->format( 'Y-m-d' ) < $day_date->format( 'Y-m-d' ) ) {
							$new_slot_for_prev_day             = clone $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ];
							$new_slot_for_prev_day->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$new_slot_for_prev_day->start_date = $tz_start->format( 'Y-m-d' );
							unset( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ] );
						} elseif ( $tz_start->format( 'Y-m-d' ) > $day_date->format( 'Y-m-d' ) ) {
							$new_slot_for_next_day             = clone $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ];
							$new_slot_for_next_day->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$new_slot_for_next_day->start_date = $tz_start->format( 'Y-m-d' );
							unset( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ] );
						} else {
							// no date change, just apply the timeshift
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ]->start_time = OsTimeHelper::convert_datetime_to_minutes( $tz_start );
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->slots[ $j ]->start_date = $tz_start->format( 'Y-m-d' );
						}
						if ( $new_slot_for_next_day ) {
							$temp_resource_for_next_day_move->slots[] = $new_slot_for_next_day;
						}
						if ( $new_slot_for_prev_day ) {
							if ( isset( $daily_resources[ $tz_start->format( 'Y-m-d' ) ] ) ) {
								for ( $p = 0; $p < count( $daily_resources[ $tz_start->format( 'Y-m-d' ) ] );$p++ ) {
									if ( $daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->agent_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->agent_id
										&& $daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->service_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->service_id
										&& $daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->location_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->location_id ) {
										// same resource found - add those work periods to this resource
										$daily_resources[ $tz_start->format( 'Y-m-d' ) ][ $p ]->slots[] = $new_slot_for_prev_day;
									}
								}
							}
						}
					}


					// loop and apply timeshift to WORK MINUTES
					// -----------
					$total_work_minutes = count( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_minutes );
					for ( $j = 0; $j < $total_work_minutes; $j++ ) {
						$new_work_minute_for_prev_day = false;
						$new_work_minute_for_next_day = false;


						$tz_work_minutes = new OsWpDateTime( $day_date->format( 'Y-m-d' ) . 'T' . OsTimeHelper::minutes_to_army_hours_and_minutes( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_minutes[ $j ] ) . ':00' );
						$tz_work_minutes->setTimezone( $timezone );

						if ( $tz_work_minutes->format( 'Y-m-d' ) < $day_date->format( 'Y-m-d' ) ) {
							$new_work_minute_for_prev_day = OsTimeHelper::convert_datetime_to_minutes( $tz_work_minutes );
							unset( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_minutes[ $j ] );
						} elseif ( $tz_work_minutes->format( 'Y-m-d' ) > $day_date->format( 'Y-m-d' ) ) {
							$new_work_minute_for_next_day = OsTimeHelper::convert_datetime_to_minutes( $tz_work_minutes );
							unset( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_minutes[ $j ] );
						} else {
							// same day, just apply the timeshift
							$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->work_minutes[ $j ] = OsTimeHelper::convert_datetime_to_minutes( $tz_work_minutes );
						}

						if ( $new_work_minute_for_next_day !== false ) {
							$temp_resource_for_next_day_move->work_minutes[] = $new_work_minute_for_next_day;
						}
						if ( $new_work_minute_for_prev_day !== false ) {
							if ( isset( $daily_resources[ $tz_work_minutes->format( 'Y-m-d' ) ] ) ) {
								for ( $p = 0; $p < count( $daily_resources[ $tz_work_minutes->format( 'Y-m-d' ) ] );$p++ ) {
									if ( $daily_resources[ $tz_work_minutes->format( 'Y-m-d' ) ][ $p ]->agent_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->agent_id
										&& $daily_resources[ $tz_work_minutes->format( 'Y-m-d' ) ][ $p ]->service_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->service_id
										&& $daily_resources[ $tz_work_minutes->format( 'Y-m-d' ) ][ $p ]->location_id == $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $i ]->location_id ) {
										// same resource found - add those work periods to this resource
											// add to the end of prev day
											$daily_resources[ $tz_work_minutes->format( 'Y-m-d' ) ][ $p ]->work_minutes[] = $new_work_minute_for_prev_day;
									}
								}
							}
						}
					}
					// if temp resource for the next day has some data created to be moved - add it to a list of resources for next day that need to be moved
					if ( $temp_resource_for_next_day_move->work_time_periods || $temp_resource_for_next_day_move->slots || $temp_resource_for_next_day_move->work_minutes ) {
						$resources_to_be_moved_to_next_day[] = $temp_resource_for_next_day_move;
					}
				}
				if ( $resources_to_be_moved_to_next_day ) {
					$next_day = clone $day_date;
					$next_day->modify( '+1 day' );
					$resources_to_be_moved[ $next_day->format( 'Y-m-d' ) ] = $resources_to_be_moved_to_next_day;
				}
				if ( isset( $resources_to_be_moved[ $day_date->format( 'Y-m-d' ) ] ) ) {
					foreach ( $resources_to_be_moved[ $day_date->format( 'Y-m-d' ) ] as $resource_to_be_moved ) {
						// loop this day resources to find a matching one and append new data to it
						$total_daily_resources = count( $daily_resources[ $day_date->format( 'Y-m-d' ) ] );
						for ( $b = 0; $b < $total_daily_resources;$b++ ) {
							if ( $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->agent_id == $resource_to_be_moved->agent_id
								&& $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->service_id == $resource_to_be_moved->service_id
								&& $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->location_id == $resource_to_be_moved->location_id ) {
								// same resource found - add those work periods to this resource
									// add to the end of prev day
									$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->work_time_periods = array_merge( $resource_to_be_moved->work_time_periods, $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->work_time_periods );
									$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->slots             = array_merge( $resource_to_be_moved->slots, $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->slots );
									$daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->work_minutes      = array_merge( $resource_to_be_moved->work_minutes, $daily_resources[ $day_date->format( 'Y-m-d' ) ][ $b ]->work_minutes );
							}
						}
					}
					$resources_to_be_moved[ $day_date->format( 'Y-m-d' ) ] = [];
				}
			}
		}
		return $daily_resources;
	}


	public static function get_timezone_name_for_logged_in_customer( $timezone_name ) {
		if ( ! OsSettingsHelper::is_on( 'steps_show_timezone_selector' ) ) {
			return OsTimeHelper::get_wp_timezone_name();
		}
		if ( OsTimeHelper::is_timezone_saved_in_session() ) {
			$timezone_name = sanitize_text_field( wp_unslash( $_COOKIE[ LATEPOINT_SELECTED_TIMEZONE_COOKIE ] ) );
		} else {
			if ( OsAuthHelper::is_customer_logged_in() ) {
				$customer_timezone_name = OsMetaHelper::get_customer_meta_by_key( 'timezone_name', OsAuthHelper::get_logged_in_customer_id() );
				if ( ! empty( $customer_timezone_name ) ) {
					$timezone_name = $customer_timezone_name;
				} else {
					OsMetaHelper::save_customer_meta_by_key( 'timezone_name', OsTimeHelper::get_wp_timezone_name(), OsAuthHelper::get_logged_in_customer_id() );
					$timezone_name = OsTimeHelper::get_wp_timezone_name();
				}
			} else {
				$timezone_name = OsTimeHelper::get_wp_timezone_name();
			}
			OsTimeHelper::set_timezone_name_in_cookie( $timezone_name );
		}
		return $timezone_name;
	}


	public static function add_timezone_vars_for_customer() {
		echo '<li><span class="var-label">' . __( 'Customer Timezone', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{{customer_timezone}}</span></li>';
	}

	public static function replace_booking_vars_for_timezone( $text, $booking ) {
		$needles = [
			'{{start_date_customer_timezone}}',
			'{{start_time_customer_timezone}}',
			'{{end_time_customer_timezone}}',
			'{{customer_timezone}}',
		];

		$replacements = [
			$booking->nice_start_date_for_customer,
			$booking->nice_start_time_for_customer,
			$booking->nice_end_time_for_customer,
			$booking->customer->get_selected_timezone_name(),
		];
		$text         = str_replace( $needles, $replacements, $text );
		return $text;
	}


	public static function add_booking_form_class( $classes ) {
		if ( OsSettingsHelper::is_on( 'steps_show_timezone_selector' ) ) {
			$classes[] = 'addon-timezone-selector-active';
		}
		return $classes;
	}

	public static function add_timezone_settings( string $settings_html, string $selected_step_code ): string {
		if ( $selected_step_code == 'booking__datepicker' ) {
			$settings_html .= '<div class="sub-section-row">
      <div class="sub-section-label">
        <h3>' . __( 'Timezone Settings', 'latepoint-pro-features' ) . '</h3>
      </div>
      <div class="sub-section-content">' .
			OsFormHelper::toggler_field( 'settings[steps_show_timezone_selector]', __( 'Enable timezone selector', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'steps_show_timezone_selector' ), false, false, [ 'sub_label' => __( 'Will let customer change default timezone, will try to auto-detect timezone from the browser', 'latepoint-pro-features' ) ] ) .
			  OsFormHelper::toggler_field( 'settings[steps_show_timezone_info]', __( 'Show timezone information', 'latepoint-pro-features' ), OsSettingsHelper::is_on( 'steps_show_timezone_info' ), false, false, [ 'sub_label' => __( 'Timezone name will appear next to appointment time', 'latepoint-pro-features' ) ] )
			. '</div>
    </div>';
		}
		return $settings_html;
	}

	public static function generate_timezone_picker( $selected_timezone_name ): string {
		ob_start();
		?>
		<div class="os-timezone-selector-wrapper-with-shadow">
		<div class="os-timezone-selector-wrapper">
			<div class="os-timezone-selector-close">
				<i class="latepoint-icon latepoint-icon-common-01"></i>
			</div>
			<div class="os-timezones-filter-input-wrapper">
				<input class="os-timezones-filter-input" data-not-found-message="<?php esc_attr_e( 'No matching timezone found', 'latepoint-pro-features' ); ?>" type="text" placeholder="<?php esc_html_e( 'Type to search...', 'latepoint-pro-features' ); ?>">
			</div>
			<div class="os-timezones-list">
				<div class="os-selected-timezone-info">
					<i class="latepoint-icon latepoint-icon-checkmark"></i>
					<div class="os-selected-timezone-name"><?php echo OsFeatureTimezoneHelper::nice_timezone_name( $selected_timezone_name ); ?></div>
					<div class="os-selected-timezone-local-time">
						<?php
						try {
							$now = new OsWpDateTime( 'now', new DateTimeZone( $selected_timezone_name ) );
						} catch ( Exception $e ) {
							$now = OsTimeHelper::now_datetime_object();
						}
						echo $now->format( OsTimeHelper::get_time_format() );

						?></div>
				</div>
				<?php echo OsTimeHelper::timezones_options_list_styled( $selected_timezone_name ); ?>
			</div>
		</div>
		</div>
		<?php
		$html = ob_get_clean();
		return $html;
	}
}