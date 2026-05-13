<?php
/**
 * Apple Calendar - List Bookings for Sync
 * Shows bookings and their sync status with Apple Calendar
 *
 * Variables available:
 *
 * @var OsAgentModel $agent                        - OsAgentModel instance
 * @var array        $future_bookings              - Array of future bookings
 * @var bool         $is_apple_calendar_connected  - Boolean indicating if Apple Calendar is connected
 * @var string|false $calendar_id_for_push         - Selected calendar ID for pushing bookings
 * @var int          $total_synced_future_bookings - Total number of future bookings synced
 * @var int          $total_future_bookings        - Total number of future bookings
 * @var float        $synced_bookings_percent      - Percentage of bookings synced
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$agent_id = $agent->id;
?>

<?php
if ( $is_apple_calendar_connected ) {
	if ( $calendar_id_for_push ) {
		$disconnect_prompt = __( 'Are you sure you want to stop syncing bookings to this calendar? Bookings that were already synced to this calendar will stay there, you can remove them by clicking on Unsync All Bookings button, prior to disconnecting this calendar.', 'latepoint-apple-calendar' );
		$disconnect_action = OsRouterHelper::build_route_name( 'apple_calendar', 'disable_calendar_for_push' );
		$disconnect_params = OsUtilHelper::build_os_params( array( 'agent_id' => $agent_id ) );
		?>
		<div class="channel-watch-status watch-status-on">
			<div class="status-watch-label">
				<i class="latepoint-icon latepoint-icon-check"></i>
				<span class="cw-status">
					<?php esc_html_e( 'New Bookings will be automatically synced to ', 'latepoint-apple-calendar' ); ?>
					<strong><?php echo esc_html( OsAppleCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent_id ) ); ?></strong>
				</span>
			</div>
			<a href="#"
				class="latepoint-link cw-danger"
				data-os-success-action="reload"
				data-os-action="<?php echo esc_attr( $disconnect_action ); ?>"
				data-os-params="<?php echo esc_attr( $disconnect_params ); ?>"
				data-os-prompt="<?php echo esc_attr( $disconnect_prompt ); ?>">
				<span class="latepoint-icon latepoint-icon-bell-off"></span>
				<span><?php esc_html_e( 'Stop Syncing', 'latepoint-apple-calendar' ); ?></span>
			</a>
		</div>
		<?php

		if ( $future_bookings ) {
			?>
			<div class="syncing-calendar-wrapper">
				<div class="os-sync-stats-and-progress-w">
					<div class="os-sync-stats">
						<div class="os-sync-value">
							<span><?php echo intval( $total_synced_future_bookings ); ?></span>
							<?php esc_html_e( ' of ', 'latepoint-apple-calendar' ); ?>
							<?php echo intval( $total_future_bookings ); ?>
						</div>
						<div class="os-sync-label">
							<?php esc_html_e( 'Bookings Synced to ', 'latepoint-apple-calendar' ); ?>
							<strong><?php echo esc_html( OsAppleCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent_id ) ); ?></strong>
						</div>
						<div class="os-sync-buttons">
							<a href="#" data-label-sync="<?php esc_html_e( 'Sync All Bookings to Apple', 'latepoint-apple-calendar' ); ?>" data-label-cancel-sync="<?php esc_html_e( 'Stop Syncing Now', 'latepoint-apple-calendar' ); ?>" class="sync-all-bookings-to-apple-trigger latepoint-btn latepoint-btn-outline latepoint-btn-sm">
								<i class="latepoint-icon latepoint-icon-grid-18"></i>
								<span><?php esc_html_e( 'Sync All Bookings', 'latepoint-apple-calendar' ); ?></span>
							</a>
							<a href="#" data-os-prompt="<?php esc_html_e( 'Are you sure you want to remove all synced bookings from Apple Calendar? They will remain in LatePoint, but will be removed from apple calendar.', 'latepoint-apple-calendar' ); ?>" data-label-remove="<?php esc_html_e( 'Remove Bookings from Apple Calendar', 'latepoint-apple-calendar' ); ?>" data-label-cancel-remove="<?php esc_html_e( 'Stop Removing', 'latepoint-apple-calendar' ); ?>" class="remove-all-bookings-from-apple-trigger latepoint-btn latepoint-btn-outline latepoint-btn-danger latepoint-btn-sm">
								<i class="latepoint-icon latepoint-icon-x"></i>
								<span><?php esc_html_e( 'Unsync All Bookings', 'latepoint-apple-calendar' ); ?></span>
							</a>
						</div>
					</div>
					<div class="os-sync-progress" data-total="<?php echo $total_future_bookings; ?>" data-value="<?php echo $total_synced_future_bookings; ?>">
						<div class="os-sync-progress-bar" style="width: <?php echo $synced_bookings_percent; ?>%"></div>
					</div>
				</div>

				<div class="os-booking-tiny-boxes-container">
					<?php
					// Using echo for dynamic wrapper divs that are conditionally opened/closed based on date changes.
					echo '<div class="os-booking-tiny-boxes-w">';
					$prev_date = false;
					foreach ( $future_bookings as $booking ) {
						$is_synced = $booking->get_meta_by_key( 'apple_calendar_event_uid', false );
						if ( ! $prev_date || $prev_date !== $booking->start_date ) {
							if ( $prev_date ) {
								echo '</div></div><div class="os-booking-tiny-boxes-w">';
							}
							$prev_date = $booking->start_date;
							echo '<div class="os-booking-tiny-box-date">';
							echo '<div class="os-day">' . esc_html( $booking->format_start_date_and_time( 'j' ) ) . '</div>';
							echo '<div class="os-month">' . esc_html( $booking->format_start_date_and_time( 'F' ) ) . '</div>';
							echo '</div>';
							echo '<div class="os-booking-tiny-boxes-i">';
						}
						?>
						<div <?php echo OsBookingHelper::quick_booking_btn_html( $booking->id ); ?>
							class="os-booking-tiny-box <?php echo $is_synced ? 'is-synced' : 'not-synced'; ?> booking-status-<?php echo esc_attr( $booking->status ); ?> <?php echo OsAppleCalendarHelper::is_booking_status_syncable( $booking->status ) ? 'booking-should-be-syncable' : ''; ?>">
							<div class="os-booking-unsync-apple-trigger"
								data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'delete_booking' ) ); ?>"
								data-os-after-call="latepointAppleCalendarAdminAddon.booking_unsynced"
								data-os-pass-this="yes"
								data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( array( 'booking_id' => $booking->id ) ) ); ?>"></div>
							<div class="os-booking-sync-apple-trigger"
								data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'sync_booking' ) ); ?>"
								data-os-remove-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'delete_booking' ) ); ?>"
								data-os-after-call="latepointAppleCalendarAdminAddon.booking_synced"
								data-os-pass-this="yes"
								data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( array( 'booking_id' => $booking->id ) ) ); ?>"></div>
							<div class="os-name"><?php echo esc_html( $booking->service->name ); ?></div>
							<div class="os-date"><?php echo esc_html( $booking->nice_start_date ); ?></div>
							<div class="os-date"><?php echo esc_html( $booking->nice_start_time . ' - ' . $booking->nice_end_time ); ?></div>
						</div>
						<?php
					}
					echo '</div></div>';
					?>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="no-results-w">
				<div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
				<h2><?php esc_html_e( 'This agent does not have any appointments yet', 'latepoint-apple-calendar' ); ?></h2>
				<a href="#" <?php echo OsBookingHelper::quick_booking_btn_html( false, array( 'agent_id' => $agent_id ) ); ?> class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus-square"></i><span><?php esc_html_e( 'Create Appointment', 'latepoint-apple-calendar' ); ?></span></a>
			</div>
			<?php
		}
	} else {
		?>
		<div class="os-pick-calendar-section">
			<div><?php esc_html_e( 'Pick a calendar that bookings will be synced to:', 'latepoint-apple-calendar' ); ?></div>
			<?php
			echo OsFormHelper::select_field(
				'selected_apple_calendar_id',
				false,
				array_merge(
					array(
						array(
							'value' => '',
							'label' => __( 'Select Calendar', 'latepoint-apple-calendar' ),
						),
					),
					OsAppleCalendarHelper::get_list_of_calendars_for_select( $agent_id )
				),
				OsAppleCalendarHelper::get_selected_calendar_id_for_push( $agent_id ),
				array(
					'class'         => 'agent_apple_calendar_selector',
					'data-agent-id' => $agent_id,
					'data-route'    => OsRouterHelper::build_route_name( 'apple_calendar', 'enable_calendar_for_push' ),
				)
			);
			?>
		</div>
		<?php
	}
} else {
	?>
	<div class="latepoint-message latepoint-message-error">
		<?php esc_html_e( 'This agent has not authorized access to their Apple Calendar yet. Go back to agent profile and authorize apple calendar sync.', 'latepoint-apple-calendar' ); ?>
	</div>
	<?php
}
?>
