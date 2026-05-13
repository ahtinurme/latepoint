<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureNoShowRestrictionsHelper {

	/**
	 * Stores the restricted customer ID when require_payment action is used.
	 *
	 * @var int|null
	 */
	private static $restricted_customer_id = null;

	/**
	 * Check no-show restriction when processing the verify step.
	 *
	 * Same pattern as OsFeatureCloudflareTurnstileHelper::process_step_verify().
	 * Blocks the booking or flags for required payment based on settings.
	 *
	 * Hooked to latepoint_process_step at priority 20.
	 *
	 * @param string         $step_code      The current step code.
	 * @param OsBookingModel $booking_object The booking object.
	 * @param array          $params         Step parameters.
	 */
	public static function verify_step_no_show_restriction( $step_code, $booking_object, $params = [] ) {
		if ( 'verify' !== $step_code ) {
			return;
		}

		$customer = OsStepsHelper::get_customer_object();
		if ( $customer->is_new_record() ) {
			return;
		}

		$count     = (int) $customer->get_meta_by_key( 'no_show_count', '0' );
		$threshold = (int) OsSettingsHelper::get_settings_value( 'no_show_restriction_threshold', '3' );

		if ( $count < $threshold ) {
			return;
		}

		$action = OsSettingsHelper::get_settings_value( 'no_show_restriction_action', 'block' );

		if ( 'block' === $action ) {
			wp_send_json(
				array(
					'status'  => LATEPOINT_STATUS_ERROR,
					'message' => esc_html__( 'Your account has been restricted from booking due to repeated missed appointments. Please contact us for assistance.', 'latepoint-pro-features' ),
				)
			);
		}

		if ( 'require_payment' === $action ) {
			self::$restricted_customer_id = $customer->id;
		}
	}

	/**
	 * Filter enabled payment times to remove "Pay Later" for restricted customers.
	 *
	 * Hooked to latepoint_get_enabled_payment_times.
	 *
	 * @param array $enabled_payment_times The enabled payment times.
	 *
	 * @return array Filtered payment times.
	 */
	public static function filter_payment_times( $enabled_payment_times ) {
		if ( empty( self::$restricted_customer_id ) ) {
			return $enabled_payment_times;
		}

		unset( $enabled_payment_times[ LATEPOINT_PAYMENT_TIME_LATER ] );

		return $enabled_payment_times;
	}

	/**
	 * Handle booking status change to/from no_show.
	 *
	 * When a booking status changes to no_show, add the booking ID to the customer's
	 * no_show_booking_ids meta and increment the no_show_count. When changing from
	 * no_show to another status, remove the booking ID and decrement the count.
	 *
	 * @param OsBookingModel $booking     The updated booking.
	 * @param OsBookingModel $old_booking The old booking data.
	 */
	public static function handle_booking_status_change( OsBookingModel $booking, OsBookingModel $old_booking ) {
		$old_status = $old_booking->status;
		$new_status = $booking->status;

		if ( $old_status === $new_status ) {
			return;
		}

		if ( empty( $booking->customer_id ) ) {
			return;
		}

		$customer = new OsCustomerModel( $booking->customer_id );
		if ( $customer->is_new_record() ) {
			return;
		}

		if ( LATEPOINT_BOOKING_STATUS_NO_SHOW === $new_status ) {
			self::add_no_show_booking( $customer, $booking->id );
		} elseif ( LATEPOINT_BOOKING_STATUS_NO_SHOW === $old_status ) {
			self::remove_no_show_booking( $customer, $booking->id );
		}
	}

	/**
	 * Handle newly created booking with no_show status.
	 *
	 * @param OsBookingModel $booking The newly created booking.
	 */
	public static function handle_booking_created( OsBookingModel $booking ) {
		if ( LATEPOINT_BOOKING_STATUS_NO_SHOW !== $booking->status ) {
			return;
		}

		if ( empty( $booking->customer_id ) ) {
			return;
		}

		$customer = new OsCustomerModel( $booking->customer_id );
		if ( $customer->is_new_record() ) {
			return;
		}

		self::add_no_show_booking( $customer, $booking->id );
	}

	/**
	 * Add a booking ID to the customer's no-show tracking meta.
	 *
	 * @param OsCustomerModel $customer   The customer model.
	 * @param int             $booking_id The booking ID to add.
	 */
	private static function add_no_show_booking( OsCustomerModel $customer, int $booking_id ) {
		$booking_ids = self::get_no_show_booking_ids( $customer );

		if ( in_array( $booking_id, $booking_ids, true ) ) {
			return;
		}

		$booking_ids[] = $booking_id;
		$count         = count( $booking_ids );

		$customer->save_meta_by_key( 'no_show_booking_ids', wp_json_encode( $booking_ids ) );
		$customer->save_meta_by_key( 'no_show_count', $count );
		$customer->save_meta_by_key( 'no_show_flag', 'on' );
	}

	/**
	 * Remove a booking ID from the customer's no-show tracking meta.
	 *
	 * @param OsCustomerModel $customer   The customer model.
	 * @param int             $booking_id The booking ID to remove.
	 */
	private static function remove_no_show_booking( OsCustomerModel $customer, int $booking_id ) {
		$booking_ids = self::get_no_show_booking_ids( $customer );

		$booking_ids = array_values(
			array_filter(
				$booking_ids,
				function ( $id ) use ( $booking_id ) {
					return $id !== $booking_id;
				}
			)
		);

		$count = count( $booking_ids );

		$customer->save_meta_by_key( 'no_show_booking_ids', wp_json_encode( $booking_ids ) );
		$customer->save_meta_by_key( 'no_show_count', $count );
		$customer->save_meta_by_key( 'no_show_flag', $count > 0 ? 'on' : 'off' );
	}

	/**
	 * Get the list of no-show booking IDs for a customer.
	 *
	 * @param OsCustomerModel $customer The customer model.
	 *
	 * @return int[]
	 */
	private static function get_no_show_booking_ids( OsCustomerModel $customer ): array {
		$raw = $customer->get_meta_by_key( 'no_show_booking_ids', '[]' );
		$ids = json_decode( $raw, true );

		if ( ! is_array( $ids ) ) {
			return [];
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Output a no-show warning badge on the admin customer edit form.
	 *
	 * Hooked to latepoint_customer_edit_form_after.
	 *
	 * @param OsCustomerModel $customer The customer being edited.
	 */
	public static function output_customer_no_show_warning( OsCustomerModel $customer ) {
		if ( $customer->is_new_record() ) {
			return;
		}

		$count = (int) $customer->get_meta_by_key( 'no_show_count', '0' );
		if ( $count < 1 ) {
			return;
		}

		$threshold  = (int) OsSettingsHelper::get_settings_value( 'no_show_restriction_threshold', '3' );
		$restricted = $count >= $threshold;

		if ( ! $restricted ) {
			return;
		}
		?>
		<div class="no-show-customer-warning no-show-customer-restricted latepoint-message latepoint-message-error" style="background: #fef2f2; border: 1px solid #fecaca;">
			<div style="text-align: left; font-size: 12px;">
				<span>
				<?php printf(
					/* translators: %d: number of no-show appointments */
					esc_html( _n( '%d No-Show Appointment.', '%d No-Show Appointments.', $count, 'latepoint-pro-features' ) ),
					$count
				); ?>
				</span>
				<span>
				<?php esc_html_e( 'This customer has exceeded the no-show threshold and is restricted from booking.', 'latepoint-pro-features' ); ?>
				<span>
			</div>
		</div>
		<?php
	}

	/**
	 * Output no-show restrictions settings in the admin settings page.
	 *
	 * Hooked to latepoint_general_settings_section_restrictions_after.
	 */
	public static function add_settings() {
		?>
		<div class="sub-section-row">
			<div class="sub-section-label">
				<h3><?php esc_html_e( 'No-Show Restrictions', 'latepoint-pro-features' ); ?></h3>
			</div>
			<div class="sub-section-content">
				<?php
				echo OsFormHelper::toggler_field(
					'settings[no_show_restrictions_enabled]',
					__( 'Enable No-Show Customers Restrictions', 'latepoint-pro-features' ),
					OsSettingsHelper::is_on( 'no_show_restrictions_enabled' ),
					'noShowRestrictionsSettings',
					false,
					[ 'sub_label' => __( 'Restrict booking for customers who repeatedly miss appointments, by blocking or requiring upfront payment', 'latepoint-pro-features' ) ]
				);
				?>
				<div id="noShowRestrictionsSettings"
					style="<?php echo OsSettingsHelper::is_on( 'no_show_restrictions_enabled' ) ? '' : 'display:none;'; ?>">
					<div class="os-row">
						<div class="os-col-lg-6">
							<?php
							echo OsFormHelper::text_field(
								'settings[no_show_restriction_threshold]',
								__( 'No-Show Threshold', 'latepoint-pro-features' ),
								OsSettingsHelper::get_settings_value( 'no_show_restriction_threshold', '3' ),
								[
									'theme' => 'simple',
									'type'  => 'number',
								]
							);
							?>
						</div>
						<div class="os-col-lg-6">
							<?php
							echo OsFormHelper::select_field(
								'settings[no_show_restriction_action]',
								__( 'Action', 'latepoint-pro-features' ),
								[
									'block'           => __( 'Block bookings entirely', 'latepoint-pro-features' ),
									'require_payment' => __( 'Require upfront payment', 'latepoint-pro-features' ),
								],
								OsSettingsHelper::get_settings_value( 'no_show_restriction_action', 'block' ),
								[ 'theme' => 'simple' ]
							);
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
