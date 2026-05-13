<?php
/**
 * Apple Calendar - Settings Page
 * Global settings for Apple Calendar integration
 *
 * This view is shown in: Settings > External Calendars > Apple Calendar
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<!-- Setup Instructions Section -->
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Setup Instructions', 'latepoint-apple-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="latepoint-message latepoint-message-subtle">
			<?php esc_html_e( 'Apple Calendar uses CalDAV protocol for syncing. Each agent needs to generate an app-specific password from their Apple ID account.', 'latepoint-apple-calendar' ); ?>
		</div>
		<div class="apple-calendar-instructions">
			<h4><?php esc_html_e( 'How to Generate an App-Specific Password:', 'latepoint-apple-calendar' ); ?></h4>
			<ol class="instructions-list">
				<li>
					<?php esc_html_e( 'Go to', 'latepoint-apple-calendar' ); ?>
					<a href="https://appleid.apple.com/account/manage" target="_blank" rel="noopener">appleid.apple.com</a>
					<?php esc_html_e( 'and sign in with your Apple ID', 'latepoint-apple-calendar' ); ?>
				</li>
				<li><?php esc_html_e( 'Navigate to Sign-In and Security > App-Specific Passwords', 'latepoint-apple-calendar' ); ?></li>
				<li><?php esc_html_e( 'Click "Generate an app-specific password"', 'latepoint-apple-calendar' ); ?></li>
				<li><?php esc_html_e( 'Enter "LatePoint" as the password label', 'latepoint-apple-calendar' ); ?></li>
				<li><?php esc_html_e( 'Copy the 16-character password that is generated', 'latepoint-apple-calendar' ); ?></li>
				<li><?php esc_html_e( 'Go to the agent profile and enter the Apple ID and app-specific password', 'latepoint-apple-calendar' ); ?></li>
			</ol>
			<div class="instructions-note">
				<i class="latepoint-icon latepoint-icon-info"></i>
				<span>
					<?php esc_html_e( 'Your main Apple ID password is never stored or transmitted. Only the app-specific password is saved (encrypted).', 'latepoint-apple-calendar' ); ?>
				</span>
			</div>
		</div>
	</div>
</div>

<!-- Agent Connections Section -->
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Agent Connections', 'latepoint-apple-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="latepoint-message latepoint-message-subtle">
			<?php esc_html_e( 'Each agent can link their Apple Calendar from their agent profile form.', 'latepoint-apple-calendar' ); ?>
		</div>
		<div class="latepoint-ical-agent-connections">
			<?php
			if ( OsAppleCalendarHelper::is_enabled() ) {
				// Get all agents to show connection status.
				$agents     = new OsAgentModel();
				$all_agents = $agents->should_be_active()->get_results_as_models();

				if ( $all_agents ) {
					foreach ( $all_agents as $agent ) {
						$connected = OsAppleCalendarHelper::is_agent_connected_to_apple_calendar( $agent->id );
						echo '<div class="ical-agent-connection-status ' . ( $connected ? 'connected' : 'not-connected' ) . '">';
						echo '<div class="ical-agent-avatar" style="background-image: url(' . $agent->get_avatar_url() . ')"></div>';
						echo '<div class="ical-agent-info">';
						echo '<div class="ical-agent-name">' . esc_html( $agent->full_name ) . '</div>';
						echo '<div class="ical-agent-email">' . esc_html( $agent->email ) . '</div>';
						echo '<a href="' . OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) ) . '#latepointAppleCalendarSetup">';
						if ( $connected ) {
							echo '<i class="latepoint-icon latepoint-icon-check"></i><span>' . esc_html__( 'Access Granted', 'latepoint-apple-calendar' ) . '</span>';
						} else {
							echo '<span>' . esc_html__( 'Not Connected', 'latepoint-apple-calendar' ) . '</span>';
						}
						echo '</a>';

						if ( $connected ) {
							$calendar_id_for_push     = OsAppleCalendarHelper::get_selected_calendar_id_for_push( $agent->id );
							$calendar_name_for_push   = $calendar_id_for_push ? OsAppleCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) : false;
							$calendar_ids_for_pull    = OsAppleCalendarHelper::get_selected_calendar_ids_for_pull( $agent->id );
							$calendars_for_pull_count = $calendar_ids_for_pull ? count( explode( ',', $calendar_ids_for_pull ) ) : 0;

							echo '<div class="ical-agent-connection-breakdown">';
							echo '<div class="">' . __( 'Push bookings to:', 'latepoint-apple-calendar' ) . '</div><div class="">' . ( $calendar_name_for_push ? 'Selected' : 'Not Selected' ) . '</div>';
							echo '</div>';
							echo '<div class="ical-agent-connection-breakdown">';
							echo '<div class="">' . __( 'Pull availability from:', 'latepoint-apple-calendar' ) . '</div><div class="">' . ( $calendars_for_pull_count ? 'Selected' : 'Not Selected' ) . '</div>';
							echo '</div>';
						}

						echo '</div>';
						echo '</div>';
					}
				}
			}
			?>
		</div>
	</div>
</div>

<!-- Event Template Section -->
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Event Template', 'latepoint-apple-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="latepoint-message latepoint-message-subtle">
			<?php esc_html_e( 'You can use variables in your event title and description, they will be replaced with a value for the booking.', 'latepoint-apple-calendar' ); ?>
			<?php echo OsUtilHelper::template_variables_link_html(); ?>
		</div>
		<div class="os-row">
			<div class="os-col-12">
				<?php
				echo OsFormHelper::text_field(
					'settings[apple_calendar_event_title_template]',
					__( 'Template For Event Title', 'latepoint-apple-calendar' ),
					OsSettingsHelper::get_settings_value( 'apple_calendar_event_title_template', '{{service_name}}' ),
					array( 'theme' => 'bordered' )
				);

				OsFormHelper::wp_editor_field(
					'settings[apple_calendar_event_description_template]',
					'settings_apple_calendar_event_description_template',
					__( 'Template For Event Description', 'latepoint-apple-calendar' ),
					OsSettingsHelper::get_settings_value( 'apple_calendar_event_description_template', "Customer Name: <strong>{{customer_full_name}}</strong>\nPhone: <strong>{{customer_phone}}</strong>\nEmail: <strong>{{customer_email}}</strong>" ),
					array( 'editor_height' => 100 )
				);
				?>
			</div>
		</div>
	</div>
</div>

<!-- Other Settings Section -->
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Other Settings', 'latepoint-apple-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="os-row">
			<div class="os-col-12">
				<?php
				echo OsFormHelper::toggler_field(
					'settings[apple_calendar_hide_event_titles]',
					__( 'Hide titles of imported events', 'latepoint-apple-calendar' ),
					OsSettingsHelper::is_on( 'apple_calendar_hide_event_titles' ),
					false,
					false,
					array( 'sub_label' => __( 'For privacy reasons hides titles of events imported from Apple Calendar', 'latepoint-apple-calendar' ) )
				);
				?>
			</div>
		</div>
	</div>
</div>
