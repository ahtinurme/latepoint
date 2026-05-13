<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsServiceExtrasHelper {

	public static function booking_service_extras_ids_and_quantity_string( $booking ) {
		$booking_service_extras = self::get_service_extras_for_booking( $booking );
		$ids_and_quantity       = [];
		foreach ( $booking_service_extras as $service_extra_id => $quantity ) {
			$ids_and_quantity[] = $service_extra_id . ':' . $quantity;
		}
		return implode( ',', $ids_and_quantity );
	}


	public static function remove_service_extras_from_booking( $booking_id, $service_extras_ids_to_remove ) {
		if ( empty( $service_extras_ids_to_remove ) ) {
			return;
		}
		$booking_service_extra = new OsBookingServiceExtraModel();
		$items_to_delete       = $booking_service_extra->where(
			[
				'booking_id'       => $booking_id,
				'service_extra_id' => $service_extras_ids_to_remove,
			] 
		)->get_results_as_models();
		if ( $items_to_delete ) {
			foreach ( $items_to_delete as $item_to_delete ) {
				$item_to_delete->delete();
			}
		}
	}

	public static function format_service_extras_to_string( $booking_service_extras ) {
		$booking_service_extras_ids = [];
		if ( $booking_service_extras ) {
			foreach ( $booking_service_extras as $service_extra ) {
				$booking_service_extras_ids[] = $service_extra['id'] . ':' . $service_extra['quantity'];
			}
		}
		return implode( ',', $booking_service_extras_ids );
	}

	/**
	 * @param OsBookingModel $booking
	 * @return string
	 */
	public static function get_service_extras_selector_for_booking( OsBookingModel $booking, $order_item_id ): string {
		$html                        = '';
		$selected_extras_for_booking = self::get_service_extras_for_booking( $booking );
		$service_extras              = new OsServiceExtraModel();
		$service_extras              = $service_extras->should_be_active()->get_results_as_models();
		if ( $booking->service_id ) {
			// filter extras available for selected service
			$available_extras     = [];
			$available_extras_ids = [];
			foreach ( $service_extras as $service_extra ) {
				if ( $service_extra->has_service( $booking->service_id ) ) {
					$available_extras[]     = $service_extra;
					$available_extras_ids[] = $service_extra->id;
				}
			}
			$service_extras = $available_extras;
		}
		$missing = [];
		if ( $selected_extras_for_booking ) {
			// check if extras for this booking are not available for selected service
			foreach ( $selected_extras_for_booking as $service_extra_id => $qty ) {
				if ( ! in_array( $service_extra_id, $available_extras_ids ) ) {
					$service_extra = new OsServiceExtraModel( $service_extra_id );
					if ( $service_extra->name ) {
						$missing[] = $service_extra->name;
					}
				}
			}
		}
		$html .= '<div class="latepoint-service-extras-for-booking-wrapper reloadable-field-wrapper" data-route-name="' . OsRouterHelper::build_route_name( 'service_extras', 'reload_service_extras_for_booking_data_form' ) . '">';
		if ( $missing ) {
			$html .= '<div class="clear-missing-lateselect">' . sprintf( __( 'Service extras [ %s ] applied to this booking are not available for this service. Either pick a service that offers them, or clear them.', 'latepoint-pro-features' ), implode( ', ', $missing ) ) . '<a href="#" class="clear-lateselect" data-lateselect-id="booking_service_extras_ids">' . __( 'Clear', 'latepoint-pro-features' ) . '</a></div>';
		}
		$field_name = 'order_items[' . $order_item_id . '][bookings][' . $booking->get_form_id() . '][service_extras_ids]';
		if ( $service_extras ) {
			$html .= '<div class="os-form-group os-form-select-group os-form-group-transparent">';
			$html .= '<label for="">' . __( 'Service Extras', 'latepoint-pro-features' ) . '</label>';
			$html .= '<select data-hidden-connection="#' . OsFormHelper::name_to_id( $field_name ) . '" data-placeholder="' . __( 'Click here to select...', 'latepoint-pro-features' ) . '" multiple class="os-late-select os-affects-duration os-affects-price" name="temp_service_extras_ids">';
			foreach ( $service_extras as $service_extra ) {
				$selected = isset( $selected_extras_for_booking[ $service_extra->id ] ) ? 'selected="selected"' : '';
				if ( $service_extra->maximum_quantity > 1 ) {
					$quantity      = isset( $selected_extras_for_booking[ $service_extra->id ] ) ? $selected_extras_for_booking[ $service_extra->id ] : 1;
					$quantity_html = 'data-quantity="' . $quantity . '"';
				} else {
					$quantity_html = '';
				}
				$html .= '<option ' . $quantity_html . ' data-max-quantity="' . $service_extra->maximum_quantity . '" data-duration="' . $service_extra->duration . '" value="' . $service_extra->id . '" ' . $selected . '>' . $service_extra->name . '</option>';
			}
			$html .= '</select>';
			$html .= OsFormHelper::hidden_field( $field_name, self::booking_service_extras_ids_and_quantity_string( $booking ), [ 'class' => 'latepoint_service_extras_ids os-affects-price' ] );
			$html .= '</div>';
		} else {
			$html .= OsFormHelper::hidden_field( $field_name, '' );
		}
		$html .= '</div>';
		return $html;
	}


	/**
	 * @param OsBookingModel $booking
	 * @param bool $force_reload
	 * @return OsBookingServiceExtraModel[]
	 */
	public static function get_service_extras_for_booking( OsBookingModel $booking, bool $force_reload = false ): array {
		if ( ! $force_reload && ! empty( $booking->service_extras ) ) {
			return $booking->service_extras;
		}
		if ( $booking->is_new_record() ) {
			return [];
		}

		$booking_service_extras = self::get_service_extras_models_for_booking( $booking );

		$formatted_service_extras = [];
		foreach ( $booking_service_extras as $booking_service_extra ) {
			$formatted_service_extras[ $booking_service_extra->service_extra_id ] = $booking_service_extra->quantity;
		}
		return $formatted_service_extras;
	}

	/**
	 * @param OsBookingModel $booking
	 * @return OsServiceExtraModel[]
	 */
	public static function get_service_extras_models_for_booking( OsBookingModel $booking ): array {
		if ( $booking->is_new_record() ) {
			return [];
		}

		$booking_service_extra  = new OsBookingServiceExtraModel();
		$booking_service_extras = $booking_service_extra->where( [ 'booking_id' => $booking->id ] )->get_results_as_models();

		return $booking_service_extras;
	}

	public static function calculate_service_extras_prices( $amount, $booking ) {
		$booking->service_extras = self::get_service_extras_for_booking( $booking );
		if ( ! empty( $booking->service_extras ) ) {
			$service_extras = new OsServiceExtraModel();
			$service_extras = $service_extras->where( [ 'id' => array_keys( $booking->service_extras ) ] )->get_results_as_models();
			foreach ( $service_extras as $service_extra ) {
				$service_extra_price = $service_extra->charge_amount * $booking->service_extras[ $service_extra->id ];
				$service_extra_price = apply_filters( 'latepoint_full_amount_for_service_extra', $service_extra_price, $booking, $service_extra );
				$amount              = $amount + $service_extra_price;
			}
		}
		return $amount;
	}


	/**
	 * @param string $ids_and_quantities
	 * @return array
	 *
	 * Expects a string with one of these formats ID:QUANTITY,ID:QUANTITY... or ID,ID,ID
	 * will return an array with IDs as keys and quantities as values ['4' => 2, '7' => 1]
	 *
	 */
	public static function extract_service_extras( string $ids_and_quantities = '' ): array {
		$ids_and_quantities   = explode( ',', $ids_and_quantities );
		$clean_service_extras = [];
		if ( ! empty( $ids_and_quantities ) ) {
			foreach ( $ids_and_quantities as $id_and_quantity ) {
				list($id, $quantity) = array_pad( explode( ':', $id_and_quantity ), 2, 1 );
				if ( is_numeric( $id ) && is_numeric( $quantity ) ) {
					$clean_service_extras[ $id ] = $quantity;
				}
			}
		}
		return $clean_service_extras;
	}
}
