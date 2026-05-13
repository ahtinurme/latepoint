<?php

class OsFeatureServiceExtrasHelper {
	public static string $step_code = 'booking__service_extras';

	public static function add_label_for_step( array $labels ): array {
		$labels[ self::$step_code ] = __( 'Service Extras', 'latepoint-pro-features' );
		return $labels;
	}

	public static function add_settings_for_step( array $settings ): array {
		$settings[ self::$step_code ] = [
			'side_panel_heading'     => 'Service Extras Selection',
			'side_panel_description' => 'Please select service extras for your appointment',
			'main_panel_heading'     => 'Service Extras',
		];
		return $settings;
	}

	public static function add_svg_for_step( string $svg, string $step_code ) {
		if ( $step_code == self::$step_code ) {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 73 73">
<path class="latepoint-step-svg-base" d="M4.7233448,23.4262314c1.6196971,0.337038,4.4639482,1.2246094,4.9765625,1.2246094
		c0.8955622,0,1.0312843-1.3012924,0.1518555-1.484375c-1.9912591-0.4145584-2.7713714-0.7819729-4.8227539-1.2089844
		c-0.4082031-0.0874023-0.8027344,0.1757813-0.887207,0.581543C4.0573292,22.9442978,4.3175831,23.3417587,4.7233448,23.4262314z"/>
	<path class="latepoint-step-svg-base" d="M10.9762745,19.8066025c0.144043,0.2192383,0.3833008,0.3383789,0.6274414,0.3383789
		c0.5899696,0,0.9558563-0.6604652,0.6264648-1.1616211c-1.1884766-1.8100586-2.59375-3.4785147-4.1762695-4.9594717
		c-0.3032227-0.2832031-0.7773438-0.2680664-1.0600586,0.034668c-0.2832031,0.3027344-0.2675781,0.7773438,0.034668,1.0600586
		C8.5246143,16.5190048,9.8527393,18.0961533,10.9762745,19.8066025z"/>
	<path class="latepoint-step-svg-base" d="M15.6989307,9.2524042c-0.418457-0.0317383-0.769043,0.2910156-0.7929688,0.7041016
		c-0.1479492,2.487793-0.0957031,5.0048828,0.1547852,7.4809561c0.0390625,0.3867188,0.3647461,0.6748047,0.7451172,0.6748047
		c0.4457436,0,0.791996-0.3842907,0.7470703-0.8251953c-0.2421875-2.39746-0.2924805-4.8334951-0.1499023-7.2416983
		C16.4279346,9.6317987,16.112505,9.2768183,15.6989307,9.2524042z"/>
	<path class="latepoint-step-svg-base" d="M19.0403614,62.6744003c9.5422974,0.4227295,22.1345196,0.7669678,31.6825542,1.0326538
		c2.0336914,0.0566406,4.1360474,0.2133179,5.878418-0.8370972c0.2503052-0.1508789,0.5575562-0.4707642,0.6748047-0.7385254
		c0.1908569-0.4359741-0.0411377-0.9656372-0.4159546-1.2589722s-1.3696899-0.0440674-1.84552-0.0334473
		c-0.3218994,0.0072021-0.6586914,0.0141602-0.9984131,0.0211792c0.8277054-0.8302956,1.2008667-1.8765869,1.1567993-2.9544678
		c-0.2223511-5.4171753-0.348938-9.7706909-0.401001-13.8059692c4.2993774-6.0353394,9.2467041-12.1600342,14.0784912-18.4216309
		c0.0198975-0.5657959,0.053894-0.8327637-0.0328369-1.2471313c0.0887451-0.2601929,0.0299683-0.5576782-0.1719971-0.7655029
		c-3.5903702-3.6888313-3.4343643-4.4806309-4.1938477-4.512207c-0.2402344-0.015625-0.4389648,0.081543-0.5883789,0.2475586
		c-3.2895889,3.6525669-6.1748505,7.9182701-8.9701538,12.0101318c0.0740356-2.2783203,0.1694336-4.5547485,0.3080444-6.7943115
		c0.1337891-2.1669922-1.5180664-4.0410156-3.6821289-4.1777344c-9.6884766-0.6098633-23.5908184-1.2041016-34.800293-0.5683594
		c-0.5235596,0.0298462-1.020752,0.1621094-1.4731445,0.3756104c-1.3308716,0.2626953-2.5427856,1.3068848-3.1723022,2.5577393
		c-1.14709,2.2790909-0.8694754,2.2196789-1.329895,30.392334C10.6228981,60.587925,12.8014507,62.398056,19.0403614,62.6744003z
		 M56.482132,31.7475204c2.480957-3.6337891,5.0415039-7.3823242,7.9003906-10.6699219
		c0.8613281,1.0888672,1.7714844,2.1416016,2.7167969,3.1420898C61.071579,31.351778,54.9651566,38.8672829,49.8991241,46.8188095
		c-4.9677429-2.3292274-8.3152275-5.0833893-12.9301758-8.0927734c0.8936272-1.359333,1.2069893-2.0359192,1.7744141-3.1914063
		c1.4018555,0.9404297,2.8823242,1.8945313,4.3208008,2.8222656c5.4951057,3.5390663,5.4074211,3.7446671,5.9627686,3.4509277
		C49.9197159,41.3383484,56.1744156,32.1977043,56.482132,31.7475204z M14.4137745,23.8437119
		c0.0327148-1.3359375,1.0600586-2.3999023,2.3901367-2.4750977c8.8149414-0.5009766,20.7875957-0.3037109,34.6206055,0.5673828
		c1.3398438,0.0844727,2.362793,1.2456055,2.2797852,2.5878906c-0.1868896,3.0209351-0.3168335,6.1072388-0.3861084,9.1760254
		c-1.5569878,2.2344093-3.060421,4.3225746-4.7910156,6.4386597c-3.0982552-2.0906677-6.6150932-4.2565727-9.6539307-6.3265991
		c-0.1791992-0.1210938-0.3999023-0.1611328-0.6108398-0.1054688c-0.7728767,0.1986389-0.5210228,1.2492561-2.9604492,4.8115234
		c-0.1747627,0.2543373-0.201931,0.6171951,0.0743408,0.920105c0.0396729,0.4812012,0.0579834,0.9837646,0.1539307,1.4086914
		c4.6937256,3.3377075,9.1712036,6.0063477,14.1136475,8.8283691c0.5525513,0.3154907,1.2504883,0.140686,1.5951538-0.3942261
		c0.6627197-1.0286255,1.3549805-2.0599365,2.0657959-3.0933838c0.0650635,3.4772949,0.1841431,7.2590942,0.3692017,11.7796631
		c0.0573692,1.3929405-1.0435982,2.5516052-2.4375,2.5556641c-10.6908722,0.0428123-23.1433048-0.6689453-34.2724609-0.6689453
		c-0.8717651,0-1.7561407,0.1188622-2.5859375-0.7285156c-0.4643555-0.4736328-0.7109375-1.1025391-0.6948242-1.7685547
		C13.9541626,46.1924324,14.1386328,35.0912399,14.4137745,23.8437119z"/>
			</svg>';
		}
		return $svg;
	}


	public static function add_capabilities_for_controller( $required_capabilities ) {
		$required_capabilities['OsServiceExtrasController']['per_action'] = [ 'reload_service_extras_for_booking_data_form' => [ 'booking__view' ] ];
		return $required_capabilities;
	}

	public static function add_service_extras_data_vars_to_booking( array $data, OsModel $model ): array {
		if ( is_a( $model, 'OsBookingModel' ) ) {
			$data['service_extras']       = [];
			$service_extras_with_quantity = OsServiceExtrasHelper::get_service_extras_for_booking( $model );

			if ( $service_extras_with_quantity ) {
				foreach ( $service_extras_with_quantity as $id => $quantity ) {
					$service_extra                  = new OsServiceExtraModel( $id );
					$service_extra_data             = $service_extra->get_data_vars();
					$service_extra_data['quantity'] = $quantity;
					$data['service_extras'][]       = $service_extra_data;
				}
			}
		}
		return $data;
	}


	public static function should_step_be_skipped( bool $skip, string $step_code, OsCartModel $cart, OsCartItemModel $cart_item, OsBookingModel $booking ): bool {
		if ( $step_code == self::$step_code ) {

			if ( $booking->is_part_of_bundle() ) {
				// bundle bookings have preset duration, no need to ask customer for it
				$skip = true;
			} else {
				if ( $cart_item->is_booking() ) {
					$booking        = $cart_item->build_original_object_from_item_data();
					$service_extras = new OsServiceExtraModel();
					if ( empty( $booking->service_id ) ) {
						$skip = true;
					} else {
						// show only extras attached to a selected service
						$connected_ids = OsServiceExtrasConnectorHelper::get_connected_extras_ids_to_service( $booking->service_id );
						if ( empty( $connected_ids ) ) {
							// selected service has no connected service extras - skip the step
							$skip = true;
							return $skip;
						}
						$service_extras->where( [ 'id' => $connected_ids ] );
						$total_service_extras = $service_extras->should_be_active()->count();
						if ( $total_service_extras == 0 ) {
							$skip = true;
						}
					}
				} else {
					// not a booking, skip
					$skip = true;
				}
			}
		}
		return $skip;
	}

	public static function add_service_extras_to_service_attributes( $attributes, $booking ) {
		$service_extras_for_booking = OsServiceExtrasHelper::get_service_extras_for_booking( $booking );
		$service_extras_names       = [];
		if ( ! empty( $service_extras_for_booking ) ) {
			foreach ( $service_extras_for_booking as $service_extra_id => $quantity ) {
				$service_extra          = new OsServiceExtraModel( $service_extra_id );
				$service_extras_names[] = ( $quantity > 1 ) ? $service_extra->name . '(' . $quantity . ')' : $service_extra->name;
			}
		}
		if ( ! empty( $service_extras_names ) ) {
			$attributes[] = [
				'label' => __( 'Extras', 'latepoint-pro-features' ),
				'value' => implode( ', ', $service_extras_names ),
			];
		}
		return $attributes;
	}

	public static function add_service_extras_to_bookings_data_for_csv( $bookings_data, $params = [] ) {
		$bookings_data[0][] = __( 'Service Extras', 'latepoint-pro-features' );
		return $bookings_data;
	}

	/**
	 * @param array $booking_row
	 * @param OsBookingModel $booking
	 * @param array $params
	 * @return array
	 */
	public static function add_service_extras_to_booking_row_for_csv( array $booking_row, OsBookingModel $booking, array $params = [] ): array {
		$service_extras_for_booking = OsServiceExtrasHelper::get_service_extras_for_booking( $booking );
		$service_extras_names       = [];
		foreach ( $service_extras_for_booking as $service_extra_id => $quantity ) {
			$service_extra          = new OsServiceExtraModel( $service_extra_id );
			$service_extras_names[] = ( $quantity > 1 ) ? $service_extra->name . '(' . $quantity . ')' : $service_extra->name;
		}
		$service_extras_names_str = ( empty( $service_extras_names ) ) ? __( 'None', 'latepoint-pro-features' ) : implode( ', ', $service_extras_names );
		$booking_row[]            = $service_extras_names_str;
		return $booking_row;
	}


	public static function replace_booking_vars_for_service_extras( $text, $booking ) {
		$service_extras_for_booking = OsServiceExtrasHelper::get_service_extras_for_booking( $booking );
		$service_extras_names       = [];
		if ( ! empty( $service_extras_for_booking ) ) {
			foreach ( $service_extras_for_booking as $service_extra_id => $quantity ) {
				$service_extra          = new OsServiceExtraModel( $service_extra_id );
				$service_extras_names[] = ( $quantity > 1 ) ? $service_extra->name . '(' . $quantity . ')' : $service_extra->name;
			}
		}
		$replacement = ( empty( $service_extras_names ) ) ? __( 'None', 'latepoint-pro-features' ) : implode( ', ', $service_extras_names );
		$text        = str_replace( '{{service_extras}}', $replacement, $text );
		return $text;
	}

	public static function add_service_extras_vars() {
		echo '<li><span class="var-label">' . __( 'Service Extras:', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{{service_extras}}</span></li>';
	}


	public static function save_service_extras_for_booking( OsBookingModel $booking, $old_booking = false ) {
		// if service extras were not set - means we don't need to do anything, only if they were set, but empty [] - then we remove them
		if ( ! isset( $booking->service_extras ) ) {
			return;
		}
		// Remove not existing extras
		$existing_service_extras = OsServiceExtrasHelper::get_service_extras_for_booking( $booking, true );
		$ids_to_remove           = array_diff( array_keys( $existing_service_extras ), array_keys( $booking->service_extras ) );
		OsServiceExtrasHelper::remove_service_extras_from_booking( $booking->id, $ids_to_remove );

		// Add new extras
		$ids_to_save = array_diff( array_keys( $booking->service_extras ), array_keys( $existing_service_extras ) );
		if ( ! empty( $ids_to_save ) ) {
			foreach ( $ids_to_save as $service_extra_id ) {
				$service_extra = new OsServiceExtraModel( $service_extra_id );
				if ( $service_extra ) {
					$booking_service_extra                   = new OsBookingServiceExtraModel();
					$booking_service_extra->booking_id       = $booking->id;
					$booking_service_extra->service_extra_id = $service_extra_id;
					$booking_service_extra->price            = $service_extra->charge_amount;
					$booking_service_extra->duration         = $service_extra->duration;
					$booking_service_extra->quantity         = $booking->service_extras[ $service_extra_id ] ? $booking->service_extras[ $service_extra_id ] : 1;
					$booking_service_extra->save();
				}
			}
		}

		// Update quantity of existing ones
		$ids_to_update = array_intersect( array_keys( $booking->service_extras ), array_keys( $existing_service_extras ) );
		if ( ! empty( $ids_to_update ) ) {
			foreach ( $ids_to_update as $id_to_update ) {
				$booking_service_extra = new OsBookingServiceExtraModel();
				$items_to_update       = $booking_service_extra->where(
					[
						'booking_id'       => $booking->id,
						'service_extra_id' => $id_to_update,
					] 
				)->get_results_as_models();
				if ( $items_to_update ) {
					foreach ( $items_to_update as $item_to_update ) {
						$item_to_update->update_attributes( [ 'quantity' => $booking->service_extras[ $id_to_update ] ] );
					}
				}
			}
		}
	}


	public static function delete_service_extras_for_booking( $booking_id ) {
		if ( ! $booking_id ) {
			return;
		}
		$booking_service_extras = new OsBookingServiceExtraModel();
		$booking_service_extras->delete_where( [ 'booking_id' => $booking_id ] );
	}


	public static function add_service_extras_to_quick_form( $booking, $order_item_id ) {
		echo OsServiceExtrasHelper::get_service_extras_selector_for_booking( $booking, $order_item_id );
	}

	public static function add_service_extras_to_price_breakdown_service_row( array $service_row, OsBookingModel $booking ) {
		//      this booking object should already have service extras inside of it, no need to get them again
		$booking->service_extras = OsServiceExtrasHelper::get_service_extras_for_booking( $booking );
		if ( empty( $booking->service_extras ) ) {
			return $service_row;
		}
		$service_row['sub_items'] = [];


		$service_extras = new OsServiceExtraModel();
		$service_extras = $service_extras->where( [ 'id' => array_keys( $booking->service_extras ) ] )->get_results_as_models();

		foreach ( $service_extras as $service_extra ) {
			$price                      = $service_extra->charge_amount * $booking->service_extras[ $service_extra->id ];
			$price                      = apply_filters( 'latepoint_full_amount_for_service_extra', $price, $booking, $service_extra );
			$service_row['sub_items'][] = [
				'style'     => 'sub',
				'label'     => $service_extra->name,
				'raw_value' => OsMoneyHelper::pad_to_db_format( $price ),
				'value'     => OsMoneyHelper::format_price( $price, true, false ),
				'note'      => ( $booking->service_extras[ $service_extra->id ] > 1 ) ? '(' . $booking->service_extras[ $service_extra->id ] . ' x ' . $service_extra->get_formatted_charge_amount() . ')' : '',
			];
		}
		return $service_row;
	}

	public static function sync_service_extras_on_price_reload( OsOrderModel $order ) {
		if ( $order->exists() && ! empty( $order->get_items() ) ) {
			$orderItemsParams = OsParamsHelper::get_param( 'order_items' );
			if ( $orderItemsParams ) {
				foreach ( $orderItemsParams as $orderItemParams ) {
					if ( $orderItemParams['variant'] == LATEPOINT_ITEM_VARIANT_BOOKING && ! empty( $orderItemParams['bookings'] ) ) {
						foreach ( $orderItemParams['bookings'] as $booking ) {
							if ( ! empty( $booking['id'] ) ) {
								OsServiceExtrasHelper::remove_service_extras_from_booking( $booking['id'], array_keys( $booking->service_extras ?? [] ) );
							}
						}
					}
				}
			}
		}

		return $order;
	}

	public static function add_extras_to_form_params( array $params, OsBookingModel $booking ) {
		if ( empty( $booking->service_extras ) ) {
			return $params;
		}
		$params['service_extras_ids'] = OsServiceExtrasHelper::booking_service_extras_ids_and_quantity_string( $booking );
		return $params;
	}

	public static function set_allowed_params_for_service_extra_model( $allowed_params, $booking, $role ) {
		if ( is_a( $booking, 'OsBookingModel' ) ) {
			$allowed_params[] = 'service_extras';
		}
		return $allowed_params;
	}


	public static function set_data_for_models( $booking, $data = [] ) {
		if ( is_a( $booking, 'OsBookingModel' ) ) {
			$booking->service_extras = isset( $data['service_extras_ids'] ) ? OsServiceExtrasHelper::extract_service_extras( $data['service_extras_ids'] ) : null;
		}
	}


	public static function calculated_total_duration( $total_duration, OsBookingModel $booking ) {
		$booking->service_extras = OsServiceExtrasHelper::get_service_extras_for_booking( $booking );
		if ( ! empty( $booking->service_extras ) ) {
			$service_extras  = new OsServiceExtraModel();
			$service_extras  = $service_extras->select( 'id, duration' )->where( [ 'id' => array_keys( $booking->service_extras ) ] )->get_results();
			$extras_duration = 0;
			foreach ( $service_extras as $service_extra ) {
				$quantity         = $booking->service_extras[ $service_extra->id ];
				$extras_duration += $service_extra->duration * $quantity;
			}
			$total_duration = $total_duration + $extras_duration;
		}
		return $total_duration;
	}


	public static function add_step_show_next_btn_rules( $rules, $step_code ) {
		$rules[ self::$step_code ] = true;
		return $rules;
	}

	public static function add_step_for_service_extras( array $steps ): array {
		$steps[ self::$step_code ] = [
			'after'  => 'services',
			'before' => 'datepicker',
		];
		return $steps;
	}


	public static function load_step_service_extras( $step_code, $format = 'json' ) {
		if ( $step_code == self::$step_code ) {
			$service_extras = new OsServiceExtraModel();
			if ( OsStepsHelper::$booking_object->service_id ) {
				// show only extras attached to a selected service
				$connected_ids = OsServiceExtrasConnectorHelper::get_connected_extras_ids_to_service( OsStepsHelper::$booking_object->service_id );
				$service_extras->where( [ 'id' => $connected_ids ] );
			}
			$service_extras = $service_extras->should_be_active()->get_results_as_models();

			$service_extras_controller                            = new OsServiceExtrasController();
			$service_extras_controller->vars['service_extras']    = $service_extras;
			$service_extras_controller->vars['booking']           = OsStepsHelper::$booking_object;
			$service_extras_controller->vars['current_step_code'] = $step_code;
			$service_extras_controller->set_layout( 'none' );
			$service_extras_controller->set_return_format( $format );
			$service_extras_controller->format_render(
				'_step_booking__service_extras',
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

	public static function process_service_extras_step( $current_step, $booking, $params = [] ) {
		if ( $current_step == self::$step_code ) {
			$status                  = LATEPOINT_STATUS_SUCCESS;
			$booking_params          = OsParamsHelper::get_param( 'booking' );
			$booking_service_extras  = OsServiceExtrasHelper::extract_service_extras( $booking_params['service_extras_ids'] ?? '' );
			$minimum_extras_required = $booking->service->get_meta_by_key( 'minimum_service_extras_required' ) == 'on';
			$minimum_extras_count    = intval( $booking->service->get_meta_by_key( 'minimum_service_extras_value', '0' ) );
			$maximum_extras_required = $booking->service->get_meta_by_key( 'maximum_service_extras_required' ) == 'on';
			$maximum_extras_count    = intval( $booking->service->get_meta_by_key( 'maximum_service_extras_value', '0' ) );

			if ( $minimum_extras_required && $minimum_extras_count > 0 && count( $booking_service_extras ) < $minimum_extras_count ) {
				$status  = LATEPOINT_STATUS_ERROR;
				$message = sprintf( esc_html__( 'Please select at least %d service extras', 'latepoint-pro-features' ), $minimum_extras_count );
			}

			if ( $status != LATEPOINT_STATUS_ERROR && $maximum_extras_required > 0 && $maximum_extras_count > 0 && count( $booking_service_extras ) > $maximum_extras_count ) {
				$status  = LATEPOINT_STATUS_ERROR;
				$message = sprintf( esc_html__( 'You cannot select more than %d service extras', 'latepoint-pro-features' ), $maximum_extras_count );
			}

			if ( $status == LATEPOINT_STATUS_ERROR ) {
				wp_send_json(
					[
						'status'       => $status,
						'message'      => $message,
						'send_to_step' => $current_step,
					] 
				);
				exit;
			}
		}
	}

	public static function save_extras_in_service( $service, $is_new_record, $service_params ) {
		if ( isset( $service_params['service_extras'] ) ) {
			$connections_to_save   = [];
			$connections_to_remove = [];
			foreach ( $service_params['service_extras'] as $service_extra_key => $service_extra ) {
				$service_extra_id = str_replace( 'service_extra_', '', $service_extra_key );
				$connection       = [
					'service_id'       => $service->id,
					'service_extra_id' => $service_extra_id,
				];
				if ( $service_extra['connected'] == 'yes' ) {
					$connections_to_save[] = $connection;
				} else {
					$connections_to_remove[] = $connection;
				}
			}
			if ( ! empty( $connections_to_save ) ) {
				foreach ( $connections_to_save as $connection_to_save ) {
					OsServiceExtrasConnectorHelper::save_connection( $connection_to_save );
				}
			}
			if ( ! empty( $connections_to_remove ) ) {
				foreach ( $connections_to_remove as $connection_to_remove ) {
					OsServiceExtrasConnectorHelper::remove_connection( $connection_to_remove );
				}
			}
		}

		if ( isset( $service_params['meta'] ) ) {
			foreach ( [ 'minimum_service_extras_required', 'minimum_service_extras_value', 'maximum_service_extras_required', 'maximum_service_extras_value' ] as $service_meta_key ) {
				if ( $service_meta_value = ( $service_params['meta'][ $service_meta_key ] ?? false ) ) {
					$service->save_meta_by_key( $service_meta_key, $service_meta_value );
				}
			}
		}
	}

	public static function output_extras_on_service_form( $service ) {
		$service_extras = new OsServiceExtraModel();
		$service_extras = $service_extras->get_results_as_models();
		?>
		<div class="white-box section-anchor" id="stickySectionServiceExtras">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php _e( 'Service Extras', 'latepoint-pro-features' ); ?></h3>
					<div class="os-form-sub-header-actions">
						<?php echo OsFormHelper::checkbox_field( 'select_all_service_extras', __( 'Select All', 'latepoint-pro-features' ), 'off', false, [ 'class' => 'os-select-all-toggler' ] ); ?>
					</div>
				</div>
			</div>
			<div class="white-box-content">
				<?php
				if ( $service_extras ) {
					echo '<div class="os-complex-connections-selector">';
					foreach ( $service_extras as $service_extra ) {
						$is_active_service_extra       = $service->is_new_record() ? true : $service_extra->has_service( $service->id );
						$is_active_service_extra_value = $is_active_service_extra ? 'yes' : 'no';
						$active_class                  = $is_active_service_extra ? 'active' : '';
						?>

					<div class="connection <?php echo $active_class; ?>">
						<div class="connection-i selector-trigger">
							<div class="connection-avatar"><img src="<?php echo $service_extra->get_selection_image_url(); ?>"/></div>
							<h3 class="connection-name"><?php echo $service_extra->name; ?></h3>
							<?php echo OsFormHelper::hidden_field( 'service[service_extras][service_extra_' . $service_extra->id . '][connected]', $is_active_service_extra_value, array( 'class' => 'connection-child-is-connected' ) ); ?>
						</div>
						</div><?php
					}
					echo '</div>';
					?>

					<?php
				} else {
					echo '<div class="latepoint-message latepoint-message-subtle">' . __( 'You have not created any service extras yet.', 'latepoint-pro-features' ) . '</div>';
				}
				?>
			</div>
		</div>
		<div class="white-box section-anchor" id="stickySectionRestrictionsExtras">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php _e( 'Restrictions for Service Extras', 'latepoint-pro-features' ); ?></h3>
				</div>
			</div>
			<div class="white-box-content">
				<?php echo OsFormHelper::toggler_field( 'service[meta][minimum_service_extras_required]', esc_html__( 'Require a minimum to be selected', 'latepoint-pro-features' ), ( $service->get_meta_by_key( 'minimum_service_extras_required' ) == 'on' ), 'lp-minimum-service-extras-required', false, [ 'sub_label' => __( 'Requires user to pick a minimum number of service extras', 'latepoint-pro-features' ) ] ); ?>
				<div
					id="lp-minimum-service-extras-required" <?php echo ( $service->get_meta_by_key( 'minimum_service_extras_required' ) == 'on' ) ? '' : 'style="display: none;"' ?>>
					<div class="merged-fields os-mt-1">
						<div class="merged-label"><?php esc_html_e( 'At least', 'latepoint-pro-features' ); ?></div>
						<?php echo OsFormHelper::text_field( 'service[meta][minimum_service_extras_value]', false, $service->get_meta_by_key( 'minimum_service_extras_value', '0' ), [ 'placeholder' => esc_html__( 'Value', 'latepoint-pro-features' ) ] ); ?>
						<div
							class="merged-label"><?php esc_html_e( 'service extras must be selected', 'latepoint-pro-features' ); ?></div>
					</div>
				</div>
				<?php echo OsFormHelper::toggler_field( 'service[meta][maximum_service_extras_required]', esc_html__( 'Limit maximum number that can be selected', 'latepoint-pro-features' ), ( $service->get_meta_by_key( 'maximum_service_extras_required' ) == 'on' ), 'lp-maximum-service-extras-required', false, [ 'sub_label' => __( 'Limits user from picking more than a set maximum', 'latepoint-pro-features' ) ] ); ?>
				<div
					id="lp-maximum-service-extras-required" <?php echo ( $service->get_meta_by_key( 'maximum_service_extras_required' ) == 'on' ) ? '' : 'style="display: none;"' ?>>
					<div class="merged-fields os-mt-1">
						<div class="merged-label"><?php esc_html_e( 'At most', 'latepoint-pro-features' ); ?></div>
						<?php echo OsFormHelper::text_field( 'service[meta][maximum_service_extras_value]', false, $service->get_meta_by_key( 'maximum_service_extras_value', '' ), [ 'placeholder' => esc_html__( 'Value', 'latepoint-pro-features' ) ] ); ?>
						<div
							class="merged-label"><?php esc_html_e( 'service extras can be selected', 'latepoint-pro-features' ); ?></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}