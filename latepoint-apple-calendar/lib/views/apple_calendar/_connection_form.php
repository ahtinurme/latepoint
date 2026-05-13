<?php
/**
 * Apple Calendar Connection Form
 * Displayed on agent edit page
 *
 * Variables available:
 *
 * @var OsAgentModel $agent - OsAgentModel instance
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$agent_id     = $agent->id;
$is_connected = OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent_id );
$credentials  = $is_connected ? OsAppleCalendarHelper::get_agent_credentials( $agent_id ) : false;
?>

<div class="white-box" id="latepointAppleCalendarSetup">
	<div class="white-box-header">
		<div class="os-form-sub-header">
			<h3><?php esc_html_e( 'Apple Calendar Setup', 'latepoint-apple-calendar' ); ?></h3>
		</div>
	</div>
	<div class="white-box-content">
		<?php
		if ( $is_connected ) {
			$calendar_id_for_push     = OsAppleCalendarHelper::get_selected_calendar_id_for_push( $agent_id );
			$calendar_name_for_push   = $calendar_id_for_push ? OsAppleCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent_id ) : false;
			$calendar_ids_for_pull    = OsAppleCalendarHelper::get_selected_calendar_ids_for_pull( $agent_id );
			$calendars_for_pull_count = $calendar_ids_for_pull ? count( explode( ',', $calendar_ids_for_pull ) ) : 0;
			?>
			<!-- Connected State -->
			<div class="apple-calendar-connection-status status-connected">
				<!-- Connection Status Row -->
				<div class="connection-status-row">
					<div class="status-icon">
						<i class="latepoint-icon latepoint-icon-checkmark"></i>
					</div>
					<div class="status-content">
						<div class="status-label">
							<strong><?php esc_html_e( 'Connected to Apple Calendar', 'latepoint-apple-calendar' ); ?></strong>
						</div>
						<div class="status-details">
							<?php echo esc_html( $credentials['apple_id'] ); ?>
						</div>
						<div class="status-actions">
							<a href="#"
								class="apple-calendar-disconnect-btn"
								data-os-prompt="<?php esc_html_e( 'Are you sure you want to disconnect Apple Calendar from this agent? All synced bookings and events will be removed from the local database.', 'latepoint-apple-calendar' ); ?>"
								data-os-success-action="reload"
								data-os-action="<?php echo OsRouterHelper::build_route_name( 'apple_calendar', 'disconnect' ); ?>"
								data-os-params="<?php echo OsUtilHelper::build_os_params( array( 'agent_id' => $agent_id ) ); ?>">
								<?php esc_html_e( 'Disconnect', 'latepoint-apple-calendar' ); ?>
							</a>
						</div>
					</div>
					<div class="status-sync-link">
						<a href="<?php echo OsRouterHelper::build_link( array( 'apple_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent_id ) ); ?>"
							class="latepoint-link cw-enable">
							<span><?php esc_html_e( 'Open Sync Manager', 'latepoint-apple-calendar' ); ?></span>
							<span class="latepoint-icon latepoint-icon-arrow-right"></span>
						</a>
					</div>
				</div>

				<!-- Manage Calendar Selection -->
				<div class="selection-actions">
					<a href="#"
						class="latepoint-btn latepoint-btn-outline latepoint-btn-sm apple-calendar-manage-calendars"
						data-os-action="<?php echo OsRouterHelper::build_route_name( 'apple_calendar', 'render_calendar_selection' ); ?>"
						data-os-output-target=".apple-calendar-selector-wrapper"
						data-os-params="<?php echo OsUtilHelper::build_os_params( array( 'agent_id' => $agent_id ) ); ?>">
						<i class="latepoint-icon latepoint-icon-settings"></i>
						<span><?php esc_html_e( 'Manage Calendar Selection', 'latepoint-apple-calendar' ); ?></span>
					</a>
				</div>

				<div class="apple-calendar-selector-wrapper"></div>

				<!-- Sync Management Links -->
				<div class="channel-watch-status watch-status-on as-action-list">
					<div class="channel-action <?php echo ! $calendar_name_for_push ? 'override-status-warning' : ''; ?>">
						<div class="status-watch-label">
							<?php if ( $calendar_name_for_push ) { ?>
								<i class="latepoint-icon latepoint-icon-arrow-right"></i>
								<span class="cw-status"><?php esc_html_e( 'LatePoint bookings will be synced to', 'latepoint-apple-calendar' ) . ' <strong>' . esc_html( $calendar_name_for_push ) . '</strong>'; ?></span>
								<a href="<?php echo OsRouterHelper::build_link( array( 'apple_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent_id ) ); ?>">
									(<?php esc_html_e( 'Edit', 'latepoint-apple-calendar' ); ?>)
								</a>
							<?php } else { ?>
								<i class="latepoint-icon latepoint-icon-x"></i>
								<span class="cw-status"><?php esc_html_e( 'Calendar to push bookings to was not selected', 'latepoint-apple-calendar' ); ?></span>
								<a href="<?php echo OsRouterHelper::build_link( array( 'apple_calendar', 'list_bookings_for_sync' ), array( 'agent_id' => $agent_id ) ); ?>">
									(<?php esc_html_e( 'Select Calendar', 'latepoint-apple-calendar' ); ?>)
								</a>
							<?php } ?>
						</div>
					</div>

					<div class="channel-action <?php echo ! $calendars_for_pull_count ? 'override-status-warning' : ''; ?>">
						<div class="status-watch-label">
							<?php if ( $calendars_for_pull_count ) { ?>
								<i class="latepoint-icon latepoint-icon-arrow-left"></i>
								<span class="cw-status">
									<?php
									/* translators: %d: Number of connected calendars */
									printf( __( 'Events will be loaded from %d connected Apple Calendars', 'latepoint-apple-calendar' ), $calendars_for_pull_count );
									?>
								</span>
								<a href="<?php echo OsRouterHelper::build_link( array( 'apple_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent_id ) ); ?>">
									(<?php esc_html_e( 'Edit', 'latepoint-apple-calendar' ); ?>)
								</a>
							<?php } else { ?>
								<i class="latepoint-icon latepoint-icon-x"></i>
								<span class="cw-status"><?php esc_html_e( 'Calendars to pull events from were not selected', 'latepoint-apple-calendar' ); ?></span>
								<a href="<?php echo OsRouterHelper::build_link( array( 'apple_calendar', 'load_events_for_sync' ), array( 'agent_id' => $agent_id ) ); ?>">
									(<?php esc_html_e( 'Select Calendar', 'latepoint-apple-calendar' ); ?>)
								</a>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>

		<?php } else { ?>
			<!-- Not Connected State -->
			<div class="apple-calendar-connection-status status-disconnected">
				<div class="connection-instructions">
					<h4><?php esc_html_e( 'Connect Your Apple Calendar', 'latepoint-apple-calendar' ); ?></h4>
					<p><?php esc_html_e( 'To connect Apple Calendar, you need to create an app-specific password from your Apple ID account.', 'latepoint-apple-calendar' ); ?></p>
					<ol>
						<li>
							<?php
							// translators: %s is a link to appleid.apple.com!
							printf( esc_html__( 'Go to %s and sign in with your Apple ID', 'latepoint-apple-calendar' ), '<a href="https://appleid.apple.com/account/manage" target="_blank">appleid.apple.com</a>' );
							?>
						</li>
						<li><?php esc_html_e( 'Navigate to Sign-In and Security > App-Specific Passwords', 'latepoint-apple-calendar' ); ?></li>
						<li><?php esc_html_e( 'Click "Generate an app-specific password" and give it a name (e.g., "LatePoint")', 'latepoint-apple-calendar' ); ?></li>
						<li><?php esc_html_e( 'Copy the 16-character password and paste it below', 'latepoint-apple-calendar' ); ?></li>
					</ol>
				</div>

				<div class="apple-calendar-connection-form">
					<?php
					echo OsFormHelper::text_field(
						'apple_calendar_apple_id',
						__( 'Apple ID (Email)', 'latepoint-apple-calendar' ),
						'',
						array(
							'placeholder' => 'your@email.com',
							'type'        => 'email',
						)
					);

					echo OsFormHelper::password_field(
						'apple_calendar_app_password',
						__( 'App-Specific Password', 'latepoint-apple-calendar' ),
						'',
						array(
							'placeholder' => 'xxxx-xxxx-xxxx-xxxx',
							'maxlength'   => 19,
							'type'        => 'text',
						)
					);
					?>
					<div class="os-form-sub-header">
						<div class="sub-header-note">
							<?php esc_html_e( 'Enter the 16-character app-specific password from Apple (with or without dashes)', 'latepoint-apple-calendar' ); ?>
						</div>
					</div>

					<div class="apple-calendar-connection-actions">
						<button type="button"
							class="latepoint-btn latepoint-btn-primary apple-calendar-test-connection"
							data-agent-id="<?php echo intval( $agent_id ); ?>">
							<i class="latepoint-icon latepoint-icon-checkmark"></i>
							<span><?php esc_html_e( 'Connect to Apple Calendar', 'latepoint-apple-calendar' ); ?></span>
						</button>
					</div>

					<div class="apple-calendar-connection-result"></div>
				</div>
			</div>
		<?php } ?>
	</div>
</div>
