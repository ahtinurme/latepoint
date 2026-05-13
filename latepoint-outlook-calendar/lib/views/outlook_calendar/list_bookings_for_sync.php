<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( $is_outlook_calendar_authorized ) {
	if ( $calendar_id_for_push ) {
		$disconnect_prompt = __( 'Are you sure you want to stop syncing bookings to this calendar? Bookings that were already synced to this calendar will stay there, you can remove them by clicking on Unsync All Bookings button, prior to disconnecting this calendar.', 'latepoint-outlook-calendar' );
		$disconnect_action = OsRouterHelper::build_route_name( 'outlook_calendar', 'disable_calendar_for_push' );
		$disconnect_params = OsUtilHelper::build_os_params( array( 'agent_id' => $agent->id ) );
		echo '<div class="channel-watch-status watch-status-on">
						<div class="status-watch-label">
							<i class="latepoint-icon latepoint-icon-check"></i>
							<span class="cw-status">' . esc_html__( 'New Bookings will be automatically synced to ', 'latepoint-outlook-calendar' ) . '<strong>' . esc_html( OsOutlookCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) ) . '</strong></span>
						</div>
						<a href="#" class="latepoint-link cw-danger" data-os-success-action="reload" data-os-action="' . esc_attr( $disconnect_action ) . '" data-os-params="' . esc_attr( $disconnect_params ) . '" data-os-prompt="' . esc_attr( $disconnect_prompt ) . '">
							<span class="latepoint-icon latepoint-icon-bell-off"></span>
							<span>' . esc_html__( 'Stop Syncing', 'latepoint-outlook-calendar' ) . '</span>
						</a>
					</div>';

		if ( $future_bookings ) { ?>
			<div class="syncing-calendar-wrapper">
				<div class="os-sync-stats-and-progress-w">
					<div class="os-sync-stats">
						<div class="os-sync-value"><?php echo '<span>' . esc_html( $total_synced_future_bookings ) . '</span>' . esc_html__( ' of ', 'latepoint-outlook-calendar' ) . esc_html( $total_future_bookings ); ?></div>
						<div class="os-sync-label">
							<?php echo esc_html__( 'Bookings Synced to ', 'latepoint-outlook-calendar' ) . '<strong>' . esc_html( OsOutlookCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) ) . '</strong>'; ?>
						</div>
						<div class="os-sync-buttons">
							<a href="#" data-label-sync="<?php esc_attr_e( 'Sync All Bookings to Outlook', 'latepoint-outlook-calendar' ); ?>" data-label-cancel-sync="<?php esc_attr_e( 'Stop Syncing Now', 'latepoint-outlook-calendar' ); ?>" class="sync-all-bookings-to-outlook-trigger latepoint-btn latepoint-btn-outline latepoint-btn-sm">
								<i class="latepoint-icon latepoint-icon-grid-18"></i>
								<span><?php esc_html_e( 'Sync All Bookings', 'latepoint-outlook-calendar' ); ?></span>
							</a>
							<a href="#" data-os-prompt="<?php esc_attr_e( 'Are you sure you want to remove all synced bookings from Outlook Calendar? They will remain in LatePoint, but will be removed from Outlook calendar.', 'latepoint-outlook-calendar' ); ?>" data-label-remove="<?php esc_attr_e( 'Remove Bookings from Outlook Calendar', 'latepoint-outlook-calendar' ); ?>" data-label-cancel-remove="<?php esc_attr_e( 'Stop Removing', 'latepoint-outlook-calendar' ); ?>" class="remove-all-bookings-from-outlook-trigger latepoint-btn latepoint-btn-outline latepoint-btn-danger latepoint-btn-sm">
								<i class="latepoint-icon latepoint-icon-x"></i>
								<span><?php esc_html_e( 'Unsync All Bookings', 'latepoint-outlook-calendar' ); ?></span>
							</a>
						</div>
					</div>
					<div class="os-sync-progress" data-total="<?php echo esc_attr( $total_future_bookings ); ?>" data-value="<?php echo esc_attr( $total_synced_future_bookings ); ?>">
						<div class="os-sync-progress-bar" style="width: <?php echo esc_attr( $synced_bookings_percent ); ?>%"></div>
					</div>
				</div>
				<div class="os-booking-tiny-boxes-container">
					<div class="os-booking-tiny-boxes-w">
						<?php
						$prev_date = false;
						foreach ( $future_bookings as $booking ) {
							$is_synced = $booking->get_meta_by_key( 'outlook_calendar_event_id', false );
							if ( ! $prev_date || $prev_date !== $booking->start_date ) {
								if ( $prev_date ) {
									echo '</div></div><div class="os-booking-tiny-boxes-w">';
								}
								$prev_date = $booking->start_date;
								echo '<div class="os-booking-tiny-box-date">
									<div class="os-day">' . esc_html( $booking->format_start_date_and_time( 'j' ) ) . '</div>
									<div class="os-month">' . esc_html( $booking->format_start_date_and_time( 'F' ) ) . '</div>
								</div>
								<div class="os-booking-tiny-boxes-i">';
							}
							?>
							<div <?php echo OsBookingHelper::quick_booking_btn_html( $booking->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								class="os-booking-tiny-box <?php echo $is_synced ? 'is-synced' : 'not-synced'; ?> booking-status-<?php echo esc_attr( $booking->status ); ?> <?php echo OsOutlookCalendarHelper::is_booking_status_syncable( $booking->status ) ? ' booking-should-be-syncable' : ''; ?>">
								<div class="os-booking-unsync-outlook-trigger" data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'outlook_calendar', 'delete_booking' ) ); ?>"
									data-os-after-call="latepointOutlookCalendarAdminAddon.booking_unsynced"
									data-os-pass-this="yes"
									data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( array( 'booking_id' => $booking->id ) ) ); ?>"></div>
								<div class="os-booking-sync-outlook-trigger" data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'outlook_calendar', 'sync_booking' ) ); ?>"
									data-os-remove-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'outlook_calendar', 'delete_booking' ) ); ?>"
									data-os-after-call="latepointOutlookCalendarAdminAddon.booking_synced"
									data-os-pass-this="yes"
									data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( array( 'booking_id' => $booking->id ) ) ); ?>"></div>
								<div class="os-name"><?php echo esc_html( $booking->service->name ); ?></div>
								<div class="os-date"><?php echo esc_html( $booking->nice_start_date ); ?></div>
								<div class="os-date"><?php echo esc_html( $booking->nice_start_time . ' - ' . $booking->nice_end_time ); ?></div>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="no-results-w">
			<div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
			<h2><?php esc_html_e( 'This agent does not have any appointments yet', 'latepoint-outlook-calendar' ); ?></h2>
			<a href="#" <?php echo OsBookingHelper::quick_booking_btn_html( false, array( 'agent_id' => $agent->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus-square"></i>
				<span>
					<?php esc_html_e( 'Create Appointment', 'latepoint-outlook-calendar' ); ?>
				</span>
			</a>
			</div>
			<?php
		}
	} else {
		echo '<div class="os-pick-calendar-section">';
		echo '<div>' . esc_html__( 'Pick a calendar that bookings will be synced to:', 'latepoint-outlook-calendar' ) . '</div>';

		$select_cal_options = array_merge(
			array(
				array(
					'value' => '',
					'label' => __( 'Select Calendar', 'latepoint-outlook-calendar' ),
				),
			),
			OsOutlookCalendarHelper::get_list_of_calendars_for_select( $agent->id )
		);
		$select_cal_value   = OsOutlookCalendarHelper::get_selected_calendar_id_for_push( $agent->id );
		$select_cal_atts    = array(
			'class'         => 'agent_outlook_calendar_selector',
			'data-agent-id' => $agent->id,
			'data-route'    => OsRouterHelper::build_route_name( 'outlook_calendar', 'enable_calendar_for_push' ),
		);
		echo OsFormHelper::select_field( 'selected_outlook_calendar_id', false, $select_cal_options, $select_cal_value, $select_cal_atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
} else {
	echo '<div class="latepoint-message latepoint-message-error">' . esc_html__( 'This agent has not authorized access to their Outlook Calendar yet. Go back to agent profile and authorize Outlook calendar sync.', 'latepoint-outlook-calendar' ) . '</div>';
}
