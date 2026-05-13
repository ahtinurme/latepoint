<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="white-box" id="latepointOutlookCalendarSetup">
	<div class="white-box-header">
	<div class="os-form-sub-header">
		<h3><?php esc_html_e( 'Outlook Calendar Setup', 'latepoint-outlook-calendar' ); ?></h3>
	</div>
	</div>
	<div class="white-box-content">
	<?php
	if ( OsOutlookCalendarHelper::is_agent_connected_to_outlook( $agent->id ) ) {
		$calendar_id_for_push     = OsOutlookCalendarHelper::get_selected_calendar_id_for_push( $agent->id );
		$calendar_name_for_push   = $calendar_id_for_push ? OsOutlookCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) : false;
		$calendar_ids_for_pull    = OsOutlookCalendarHelper::get_selected_calendar_ids_for_pull( $agent->id );
		$calendars_for_pull_count = $calendar_ids_for_pull ? count( explode( ',', $calendar_ids_for_pull ) ) : 0;
		?>
		<div class="channel-watch-status watch-status-on as-action-list">
		<div class="channel-action">
			<div class="status-watch-label">
			<i class="latepoint-icon latepoint-icon-checkmark"></i>
			<span class="cw-status"><?php esc_html_e( 'Access granted', 'latepoint-outlook-calendar' ); ?></span>
			<a href="#" class="os-ocal-signout-btn sw-danger"
				data-os-prompt="<?php esc_attr_e( 'Are you sure you want to disconnect Outlook Calendar from this agent? All events imported from Outlook Calendar and all bookings that were added to Outlook Calendar will be removed.', 'latepoint-outlook-calendar' ); ?>"
				data-os-success-action="reload"
				data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'outlook_calendar', 'disconnect' ) ); ?>"
				data-os-params="<?php echo esc_attr( OsUtilHelper::build_os_params( array( 'agent_id' => $agent->id ) ) ); ?>">
				(<?php esc_html_e( 'Revoke', 'latepoint-outlook-calendar' ); ?>)
			</a>
			<?php
			if ( OsOutlookCalendarHelper::is_integrated_through_relay() ) {
				echo '<a href="' . esc_url( OsRouterHelper::build_admin_post_link( array( 'outlook_calendar', 'start_authorization' ), array( 'agent_id' => $agent->id ) ) ) . '">(' . esc_html__( 'Manage', 'latepoint-outlook-calendar' ) . ')</a>';
			}
			?>
			</div>
			<a
			href="<?php echo esc_url( OsRouterHelper::build_link( array( 'outlook_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ) ); ?>"
			class="latepoint-link cw-enable">
			<span><?php esc_html_e( 'Open Sync Manager', 'latepoint-outlook-calendar' ); ?></span>
			<span class="latepoint-icon latepoint-icon-arrow-right"></span>
			</a>
		</div>
		<div class="channel-action
		<?php
		if ( ! $calendar_name_for_push ) {
			echo ' override-status-warning';
		}
		?>
		">
			<div class="status-watch-label">
			<?php if ( $calendar_name_for_push ) { ?>
				<i class="latepoint-icon latepoint-icon-checkmark"></i>
				<span class="cw-status"><?php echo esc_html__( 'LatePoint bookings will be synced to', 'latepoint-outlook-calendar' ) . ' <strong>' . esc_html( $calendar_name_for_push ) . '</strong>'; ?></span>
				<a href="<?php echo esc_url( OsRouterHelper::build_link( array( 'outlook_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ) ); ?>">
				(<?php esc_html_e( 'Edit', 'latepoint-outlook-calendar' ); ?>)
				</a>
			<?php } else { ?>
				<i class="latepoint-icon latepoint-icon-x"></i>
				<span class="cw-status"><?php echo esc_html__( 'Calendar to push bookings to was not selected', 'latepoint-outlook-calendar' ); ?></span>
				<a href="<?php echo esc_url( OsRouterHelper::build_link( array( 'outlook_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent->id ) ) ); ?>">
				(<?php esc_html_e( 'Select Calendar', 'latepoint-outlook-calendar' ); ?>)
				</a>
			<?php } ?>
			</div>
		</div>
		<div class="channel-action
		<?php
		if ( ! $calendars_for_pull_count ) {
			echo ' override-status-warning';
		}
		?>
		">
			<div class="status-watch-label">
			<?php if ( $calendars_for_pull_count ) { ?>
				<i class="latepoint-icon latepoint-icon-checkmark"></i>
				<span class="cw-status">
				<?php
					/* translators: %d is the number of connected Outlook calendars */
					printf( esc_html__( 'Events will be loaded from %d connected Outlook Calendars', 'latepoint-outlook-calendar' ), intval( $calendars_for_pull_count ) );
				?>
				</span>
				<a href="<?php echo esc_url( OsRouterHelper::build_link( array( 'outlook_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent->id ) ) ); ?>">
				(<?php esc_html_e( 'Edit', 'latepoint-outlook-calendar' ); ?>)
				</a>
			<?php } else { ?>
				<i class="latepoint-icon latepoint-icon-x"></i>
				<span class="cw-status"><?php echo esc_html__( 'Calendars to pull events from were not selected', 'latepoint-outlook-calendar' ); ?></span>
				<a href="<?php echo esc_url( OsRouterHelper::build_link( array( 'outlook_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent->id ) ) ); ?>">
				(<?php esc_html_e( 'Select Calendar', 'latepoint-outlook-calendar' ); ?>)
				</a>
			<?php } ?>
			</div>
		</div>
		</div>
	<?php } else { ?>
		<div class="channel-watch-status watch-status-off">
		<div class="status-watch-label">
			<i class="latepoint-icon latepoint-icon-bell-off"></i>
			<span class="cw-status"><?php esc_html_e( 'Outlook Calendar is not connected, or access grant has expired.', 'latepoint-outlook-calendar' ); ?></span>
		</div>
		<?php if ( OsOutlookCalendarHelper::is_integrated_through_relay() ) { ?>
			<a class="connect-to-ocal-button" href="<?php echo esc_url( OsRouterHelper::build_admin_post_link( array( 'outlook_calendar', 'start_authorization' ), array( 'agent_id' => $agent->id ) ) ); ?>">
			<span><?php esc_html_e( 'Open Connection Manager', 'latepoint-outlook-calendar' ); ?></span>
			<i class="latepoint-icon latepoint-icon-arrow-right"></i>
			</a>
		<?php } ?>
		</div>
	<?php } ?>
	</div>
</div>
