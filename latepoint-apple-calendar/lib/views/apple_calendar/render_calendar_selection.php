<?php
/**
 * Apple Calendar Selector View
 * Allows agent to select which calendars to use for push/pull sync
 *
 * Variables available:
 *
 * @var int          $agent_id                    - Agent ID
 * @var array        $calendars                   - Array of available calendars
 * @var string|false $selected_calendar_for_push. - Currently selected calendar for push
 * @var array        $selected_calendars_for_pull - Array of selected calendar IDs for pull
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="apple-calendar-selector-panel">
	<div class="calendar-selector-header">
		<h4><?php esc_html_e( 'Select Calendars for Sync', 'latepoint-apple-calendar' ); ?></h4>
		<p><?php esc_html_e( 'Choose which calendars to use for syncing bookings between LatePoint and Apple Calendar.', 'latepoint-apple-calendar' ); ?></p>
	</div>

	<form class="apple-calendar-selection-form" data-agent-id="<?php echo intval( $agent_id ); ?>">
		<!-- Calendar for Pushing Bookings -->
		<div class="calendar-selection-section">
			<div class="selection-section-header">
				<h5>
					<i class="latepoint-icon latepoint-icon-arrow-right"></i>
					<?php esc_html_e( 'Push LatePoint Bookings To', 'latepoint-apple-calendar' ); ?>
				</h5>
				<p class="selection-help">
					<?php esc_html_e( 'Select one calendar where LatePoint bookings will be automatically created as events.', 'latepoint-apple-calendar' ); ?>
				</p>
			</div>

			<div class="calendar-options">
				<?php
				if ( ! empty( $calendars ) ) {
					$calendar_options = array( '' => __( '-- Select Calendar --', 'latepoint-apple-calendar' ) );
					foreach ( $calendars as $calendar ) {
						$label = $calendar['name'];
						if ( ! empty( $calendar['description'] ) ) {
							$label .= ' (' . $calendar['description'] . ')';
						}
						$calendar_options[ $calendar['url'] ] = $label;
					}

					echo OsFormHelper::select_field(
						'agent[apple_calendar_for_push]',
						false,
						$calendar_options,
						$selected_calendar_for_push
					);
				} else {
					?>
					<div class="os-form-message-w status-warning">
						<?php esc_html_e( 'No calendars found. Please check your connection.', 'latepoint-apple-calendar' ); ?>
					</div>
				<?php } ?>
			</div>
		</div>

		<!-- Calendars for Pulling Events -->
		<div class="calendar-selection-section">
			<div class="selection-section-header">
				<h5>
					<i class="latepoint-icon latepoint-icon-arrow-left"></i>
					<?php esc_html_e( 'Pull Events From (Block Times)', 'latepoint-apple-calendar' ); ?>
				</h5>
				<p class="selection-help">
					<?php esc_html_e( 'Select one or more calendars. Events from these calendars will block available time slots in LatePoint.', 'latepoint-apple-calendar' ); ?>
				</p>
			</div>

			<div class="calendar-options">
				<?php if ( ! empty( $calendars ) ) { ?>
					<?php
					foreach ( $calendars as $index => $calendar ) {
						$is_selected = in_array( $calendar['url'], $selected_calendars_for_pull );
						$label       = $calendar['name'];
						if ( ! empty( $calendar['description'] ) ) {
							$label .= '<div class="form-label-desc">' . esc_html( $calendar['description'] ) . '</div>';
						}

						echo OsFormHelper::checkbox_field(
							'agent[apple_calendars_for_pull][]',
							$label,
							$calendar['url'],
							$is_selected,
							array( 'id' => 'calendar_for_pull_' . $index ),
							array(),
							false  // Disable hidden off_value field for array-based checkboxes.
						);
					}
					?>
				<?php } else { ?>
					<div class="os-form-message-w status-warning">
						<?php esc_html_e( 'No calendars found. Please check your connection.', 'latepoint-apple-calendar' ); ?>
					</div>
				<?php } ?>
			</div>
		</div>

		<!-- Save Button -->
		<div class="calendar-selection-actions">
			<button type="submit" class="latepoint-btn latepoint-btn-primary">
				<i class="latepoint-icon latepoint-icon-checkmark"></i>
				<span><?php esc_html_e( 'Save Calendar Selection', 'latepoint-apple-calendar' ); ?></span>
			</button>
			<button type="button" class="latepoint-btn latepoint-btn-secondary apple-calendar-cancel-selection">
				<?php esc_html_e( 'Cancel', 'latepoint-apple-calendar' ); ?>
			</button>
		</div>

		<div class="apple-calendar-selection-result"></div>
	</form>
</div>
