<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

?>
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Agent Connections', 'latepoint-outlook-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'Each agent can link their Outlook Calendar from their agent profile form.', 'latepoint-outlook-calendar' ); ?></div>
		<div class="latepoint-ocal-agent-connections">
			<?php
			if ( OsOutlookCalendarHelper::is_enabled() ) {
				$agents = new OsAgentModel();
				$agents = $agents->should_be_active()->get_results_as_models();
				foreach ( $agents as $agent ) {
					$connected = OsOutlookCalendarHelper::is_agent_connected_to_outlook( $agent->id );
					echo '<div class="ocal-agent-connection-status ' . ( $connected ? 'connected' : 'not-connected' ) . '">';
					echo '<div class="ocal-agent-avatar" style="background-image: url(' . esc_url( $agent->get_avatar_url() ) . ')"></div>';
					echo '<div class="ocal-agent-info">';
					echo '<div class="ocal-agent-name">' . esc_html( $agent->full_name ) . '</div>';
					echo '<div class="ocal-agent-email">' . esc_html( $agent->email ) . '</div>';
					echo '<a href="' . esc_url( OsRouterHelper::build_link( array( 'agents', 'edit_form' ), array( 'id' => $agent->id ) ) ) . '#latepointOutlookCalendarSetup">';
					if ( $connected ) {
						echo '<i class="latepoint-icon latepoint-icon-check"></i><span>' . esc_html__( 'Access Granted', 'latepoint-outlook-calendar' ) . '</span>';
					} else {
						echo '<span>' . esc_html__( 'Not Connected', 'latepoint-outlook-calendar' ) . '</span>';
					}
					echo '</a>';
					if ( $connected ) {
						$calendar_id_for_push     = OsOutlookCalendarHelper::get_selected_calendar_id_for_push( $agent->id );
						$calendar_name_for_push   = $calendar_id_for_push ? OsOutlookCalendarHelper::get_calendar_name_by_id( $calendar_id_for_push, $agent->id ) : false;
						$calendar_ids_for_pull    = OsOutlookCalendarHelper::get_selected_calendar_ids_for_pull( $agent->id );
						$calendars_for_pull_count = $calendar_ids_for_pull ? count( explode( ',', $calendar_ids_for_pull ) ) : 0;

						echo '<div class="ocal-agent-connection-breakdown">';
						echo '<div class="">' . esc_html__( 'Push bookings to:', 'latepoint-outlook-calendar' ) . '</div><div class="">' . ( $calendar_name_for_push ? 'Selected' : 'Not Selected' ) . '</div>';
						echo '</div>';
						echo '<div class="ocal-agent-connection-breakdown">';
						echo '<div class="">' . esc_html__( 'Pull availability from:', 'latepoint-outlook-calendar' ) . '</div><div class="">' . ( $calendars_for_pull_count ? 'Selected' : 'Not Selected' ) . '</div>';
						echo '</div>';
					}
					echo '</div>';
					echo '</div>';
				}
				?>
			<?php } ?>
		</div>
	</div>
</div>
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Event Template', 'latepoint-outlook-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'You can use variables in your event title and description, they will be replaced with a value for the booking. ', 'latepoint-outlook-calendar' ); ?><?php echo OsUtilHelper::template_variables_link_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="os-row">
			<div class="os-col-12">
				<?php echo OsFormHelper::text_field( 'settings[outlook_calendar_event_summary_template]', __( 'Template For Event Title', 'latepoint-outlook-calendar' ), OsOutlookCalendarHelper::get_event_title_template(), array( 'theme' => 'bordered' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php OsFormHelper::wp_editor_field( 'settings[outlook_calendar_event_description_template]', 'settings_outlook_calendar_event_description_template', __( 'Template For Event Description', 'latepoint-outlook-calendar' ), OsOutlookCalendarHelper::get_event_description_template(), array( 'editor_height' => 100 ) ); ?>
			</div>
		</div>
	</div>
</div>
<div class="sub-section-row">
	<div class="sub-section-label">
		<h3><?php esc_html_e( 'Other Settings', 'latepoint-outlook-calendar' ); ?></h3>
	</div>
	<div class="sub-section-content">
		<div class="os-row">
			<div class="os-col-12">
				<?php
				echo OsFormHelper::toggler_field( 'settings[outlook_calendar_hide_event_name]', __( 'Hide titles of imported events', 'latepoint-outlook-calendar' ), OsSettingsHelper::is_on( 'outlook_calendar_hide_event_name' ), false, false, array( 'sub_label' => __( 'For privacy reasons hides titles of events imported from Outlook Calendar', 'latepoint-outlook-calendar' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		</div>
	</div>
</div>
