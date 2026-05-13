<?php
/**
 * Apple Calendar - Load Events for Sync
 * Shows calendars available for pulling events to block time slots
 *
 * Variables available:
 *
 * @var OsAgentModel $agent                       - OsAgentModel instance
 * @var bool         $is_apple_calendar_connected - Boolean indicating if Apple Calendar is connected
 * @var array        $available_calendars         - Array of available calendars from Apple
 * @var array        $connected_calendars         - Array of calendars selected for pull
 * @var array        $disconnected_calendars      - Array of calendars not selected for pull
 * @var array        $calendars_with_events       - Array of events grouped by calendar
 * @var string       $data_params                 - Data parameters for AJAX calls
 * @var bool         $hide_apple_event_name       - Boolean to hide Apple event names
 *
 * @package LatePoint
 * @subpackage LatePoint_Apple_Calendar
 * @since 1.0.0
 *
 * phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date -- Using date() for display purposes only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$agent_id = $agent->id;
?>

<?php
if ( $is_apple_calendar_connected ) {
	if ( $available_calendars ) {
		if ( $connected_calendars ) {
			?>
			<div class="os-section-header">
				<h3><?php esc_html_e( 'Synced Calendars', 'latepoint-apple-calendar' ); ?></h3>
			</div>
			<?php
			foreach ( $connected_calendars as $index => $connected_calendar ) {
				// Check if auto-sync is enabled for this calendar.
				$auto_sync_enabled     = OsAppleCalendarHelper::is_auto_sync_enabled( $connected_calendar['url'], $agent->id );
				$auto_sync_status_html = '';

				$data_params = OsUtilHelper::build_os_params(
					array(
						'agent_id'    => $agent->id,
						'calendar_id' => $connected_calendar['url'],
					)
				);

				// Build auto-sync status HTML (stored as string for later output).
				ob_start();
				if ( $auto_sync_enabled ) {
					?>
					<div class="channel-watch-status watch-status-on">
						<div class="status-watch-label">
							<i class="latepoint-icon latepoint-icon-check"></i>
							<span class="cw-status"><?php esc_html_e( 'Auto-Sync is Enabled', 'latepoint-apple-calendar' ); ?></span>
						</div>
						<a href="#"
							class="latepoint-link cw-danger"
							data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'disable_auto_sync' ) ); ?>"
							data-os-params="<?php echo esc_attr( $data_params ); ?>"
							data-os-success-action="reload">
							<span class="latepoint-icon latepoint-icon-bell-off"></span>
							<span><?php esc_html_e( 'Disable Auto-Sync', 'latepoint-apple-calendar' ); ?></span>
						</a>
					</div>
					<?php
				} else {
					?>
					<div class="channel-watch-status watch-status-off">
						<div class="status-watch-label">
							<i class="latepoint-icon latepoint-icon-bell-off"></i>
							<span class="cw-status"><?php esc_html_e( 'Auto-Sync is disabled', 'latepoint-apple-calendar' ); ?></span>
						</div>
						<a href="#"
							class="latepoint-link cw-enable"
							data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'enable_auto_sync' ) ); ?>"
							data-os-params="<?php echo esc_attr( $data_params ); ?>"
							data-os-success-action="reload">
							<span class="latepoint-icon latepoint-icon-grid-18"></span>
							<span><?php esc_html_e( 'Enable Auto-Sync', 'latepoint-apple-calendar' ); ?></span>
						</a>
					</div>
					<?php
				}
				$auto_sync_status_html = ob_get_clean();

				$total_events        = 0;
				$total_synced_events = 0;
				$events_html         = '';
				$dated_events        = array();
				$recurring_events    = array();
				$saved_db_event_ids  = array();
				$calendar_events     = $calendars_with_events[ $connected_calendar['id'] ] ?? array();
				if ( ! empty( $calendar_events ) ) {
					foreach ( $calendar_events as $apple_event ) {
						if ( isset( $apple_event['transparency'] ) && $apple_event['transparency'] === 'transparent' ) {
							continue;
						}
						++$total_events;
						if ( $total_events >= 500 ) {
							break;
						}

						// Use apple_event_id for database lookup (base UID without date suffix for recurring events).
						$apple_event_id = $apple_event['apple_event_id'] ?? $apple_event['id'];
						$saved_event    = OsAppleCalendarHelper::get_record_by_apple_event_id( $apple_event_id );
						$saved_event_id = $saved_event ? $saved_event->id : false;

						if ( $saved_event ) {
							++$total_synced_events;
							$saved_db_event_ids[] = $saved_event_id;
						}

						$time_display = isset( $apple_event['is_all_day'] ) && $apple_event['is_all_day'] ? __( 'Full Day', 'latepoint-apple-calendar' ) : ( ( isset( $apple_event['start_time'] ) ? OsTimeHelper::minutes_to_hours_and_minutes( $apple_event['start_time'] ) : '12:00 am' ) . ' - ' . ( isset( $apple_event['end_time'] ) ? OsTimeHelper::minutes_to_hours_and_minutes( $apple_event['end_time'] ) : '12:00 am' ) );

						// Check if this is a recurring event.
						if ( ! empty( $apple_event['recurrence'] ) && is_array( $apple_event['recurrence'] ) ) {
							// Get the first recurrence rule (they should all have same frequency).
							$recurrence_info = $apple_event['recurrence'][0];
							$frequency       = isset( $recurrence_info['frequency'] ) ? strtoupper( $recurrence_info['frequency'] ) : 'DAILY';

							// Add to recurring events grouped by frequency.
							$recurring_events[ $frequency ][] = array(
								'summary'         => $apple_event['summary'] ?? __( '(No Title)', 'latepoint-apple-calendar' ),
								'apple_event_id'  => $apple_event_id,
								'saved_event_id'  => $saved_event_id,
								'calendar_url'    => $apple_event['calendar_url'] ?? '',
								'start_date'      => $apple_event['start_date'],
								'recurrence_info' => $recurrence_info,
								'time'            => $time_display,
							);
						} else {
							// One-time event - group by date.
							$start_date_key                              = date( 'Ymd', strtotime( $apple_event['start_date'] ) );
							$dated_events[ $start_date_key ]['day']      = date( 'j', strtotime( $apple_event['start_date'] ) );
							$dated_events[ $start_date_key ]['month']    = date( 'F', strtotime( $apple_event['start_date'] ) );
							$dated_events[ $start_date_key ]['events'][] = array(
								'summary'        => $apple_event['summary'] ?? __( '(No Title)', 'latepoint-apple-calendar' ),
								'apple_event_id' => $apple_event_id,
								'saved_event_id' => $saved_event_id,
								'calendar_url'   => $apple_event['calendar_url'] ?? '',
								'start_date'     => date( 'M j, Y', strtotime( $apple_event['start_date'] ) ),
								'end_date'       => date( 'M j, Y', strtotime( $apple_event['end_date'] ) ),
								'time'           => $time_display,
							);
						}
					}
				}
				ksort( $dated_events );

				// Build events HTML (stored as string for later output).
				ob_start();
				if ( $dated_events ) {
					?>
					<h3 class="event-type-header"><?php esc_html_e( 'One-Time Events:', 'latepoint-apple-calendar' ); ?></h3>
					<?php
				}
				foreach ( $dated_events as $events_for_date ) {
					?>
					<div class="os-booking-tiny-boxes-w">
						<div class="os-booking-tiny-box-date">
							<div class="os-day"><?php echo esc_html( $events_for_date['day'] ); ?></div>
							<div class="os-month"><?php echo esc_html( $events_for_date['month'] ); ?></div>
						</div>
						<div class="os-booking-tiny-boxes-i">
							<?php
							foreach ( $events_for_date['events'] as $event ) {
								$event_title  = $hide_apple_event_name ? __( 'Event from Apple Calendar', 'latepoint-apple-calendar' ) : $event['summary'];
								$synced_class = $event['saved_event_id'] ? 'is-synced' : 'not-synced';
								$data_params  = OsUtilHelper::build_os_params(
									array(
										'apple_event_id' => $event['apple_event_id'],
										'calendar_id'    => $event['calendar_url'],
										'agent_id'       => $agent_id,
									)
								);
								?>
								<div class="os-booking-tiny-box event-is-in-apple <?php echo esc_attr( $synced_class ); ?>">
									<div class="os-booking-unsync-apple-trigger"
										data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'unsync_event' ) ); ?>"
										data-os-after-call="latepointAppleCalendarAdminAddon.event_unsynced"
										data-os-pass-this="yes"
										data-os-params="<?php echo esc_attr( $data_params ); ?>">
									</div>
									<div class="os-booking-sync-apple-trigger"
										data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'sync_event' ) ); ?>"
										data-os-after-call="latepointAppleCalendarAdminAddon.event_synced"
										data-os-pass-this="yes"
										data-os-params="<?php echo esc_attr( $data_params ); ?>">
									</div>
									<div class="os-name"><?php echo esc_html( $event_title ); ?></div>
									<div class="os-date">
										<?php
										echo esc_html( $event['start_date'] );
										if ( $event['start_date'] !== $event['end_date'] ) {
											echo '-' . esc_html( $event['end_date'] );
										}
										?>
									</div>
									<div class="os-date"><?php echo esc_html( $event['time'] ); ?></div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
					<?php
				}
				$events_html = ob_get_clean();

				// Display recurring events grouped by frequency.
				if ( ! empty( $recurring_events ) ) {
					ob_start();
					?>
					<h3 class="event-type-header"><?php esc_html_e( 'Recurring Events:', 'latepoint-apple-calendar' ); ?></h3>
					<?php
					foreach ( $recurring_events as $frequency => $events_for_frequency ) {
						?>
						<div class="os-booking-tiny-boxes-w">
							<div class="os-booking-tiny-box-date">
								<div class="os-month"><?php echo esc_html( ucwords( strtolower( $frequency ) ) ); ?></div>
							</div>
							<div class="os-booking-tiny-boxes-i">
								<?php
								foreach ( $events_for_frequency as $event ) {
									$event_title  = $hide_apple_event_name ? __( 'Event from Apple Calendar', 'latepoint-apple-calendar' ) : $event['summary'];
									$synced_class = $event['saved_event_id'] ? 'is-synced' : 'not-synced';

									$data_params = OsUtilHelper::build_os_params(
										array(
											'apple_event_id' => $event['apple_event_id'],
											'calendar_id' => $event['calendar_url'],
											'agent_id'    => $agent_id,
										)
									);

									$recurrence_info = $event['recurrence_info'];
									$interval        = isset( $recurrence_info['interval'] ) && $recurrence_info['interval'] > 1 ? $recurrence_info['interval'] : '';
									$weekday         = $recurrence_info['weekday'] ?? '';

									// Translate weekday abbreviations.
									$weekday = str_replace(
										array( 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ),
										array(
											__( 'Mon', 'latepoint-apple-calendar' ),
											__( 'Tue', 'latepoint-apple-calendar' ),
											__( 'Wed', 'latepoint-apple-calendar' ),
											__( 'Thu', 'latepoint-apple-calendar' ),
											__( 'Fri', 'latepoint-apple-calendar' ),
											__( 'Sat', 'latepoint-apple-calendar' ),
											__( 'Sun', 'latepoint-apple-calendar' ),
										),
										$weekday
									);

									// Build human-readable recurrence description.
									switch ( $frequency ) {
										case 'YEARLY':
											$interval_text = $interval ? $interval . ' ' . __( 'years', 'latepoint-apple-calendar' ) : __( 'year', 'latepoint-apple-calendar' );
											$when          = __( 'Every', 'latepoint-apple-calendar' ) . ' ' . $interval_text . ' ' . __( 'on', 'latepoint-apple-calendar' ) . ' ' . date_i18n( 'F j', strtotime( $event['start_date'] ) );
											break;
										case 'MONTHLY':
											$interval_text = $interval ? $interval . ' ' . __( 'months', 'latepoint-apple-calendar' ) : __( 'month', 'latepoint-apple-calendar' );
											$day_of_month  = $weekday ? $weekday : __( 'day', 'latepoint-apple-calendar' ) . ' ' . date_i18n( 'j', strtotime( $event['start_date'] ) );
											// Handle special monthly patterns (1st Monday, last Friday, etc.).
											if ( $weekday ) {
												$first_char = mb_substr( $weekday, 0, 1 );
												switch ( $first_char ) {
													case '-':
														$day_of_month = __( 'last', 'latepoint-apple-calendar' ) . ' ' . str_replace( '-1', '', $weekday );
														break;
													case '1':
														$day_of_month = __( 'first', 'latepoint-apple-calendar' ) . ' ' . str_replace( '1', '', $weekday );
														break;
													case '2':
														$day_of_month = __( 'second', 'latepoint-apple-calendar' ) . ' ' . str_replace( '2', '', $weekday );
														break;
													case '3':
														$day_of_month = __( 'third', 'latepoint-apple-calendar' ) . ' ' . str_replace( '3', '', $weekday );
														break;
													case '4':
														$day_of_month = __( 'fourth', 'latepoint-apple-calendar' ) . ' ' . str_replace( '4', '', $weekday );
														break;
												}
											}
											$when = __( 'Every', 'latepoint-apple-calendar' ) . ' ' . $interval_text . ' ' . __( 'on', 'latepoint-apple-calendar' ) . ' ' . $day_of_month;
											break;
										case 'WEEKLY':
											$interval_text = $interval ? $interval . ' ' . __( 'weeks', 'latepoint-apple-calendar' ) : __( 'week', 'latepoint-apple-calendar' );
											$when          = __( 'Every', 'latepoint-apple-calendar' ) . ' ' . $interval_text . ' ' . __( 'on', 'latepoint-apple-calendar' ) . ' ' . $weekday;
											break;
										case 'DAILY':
										default:
											$interval_text = $interval ? $interval . ' ' . __( 'days', 'latepoint-apple-calendar' ) : __( 'day', 'latepoint-apple-calendar' );
											$when          = __( 'Every', 'latepoint-apple-calendar' ) . ' ' . $interval_text . ' ' . __( 'starting', 'latepoint-apple-calendar' ) . ' ' . date_i18n( 'F j, Y', strtotime( $event['start_date'] ) );
											break;
									}
									?>
									<div class="os-booking-tiny-box event-is-in-apple <?php echo esc_attr( $synced_class ); ?>">
										<div class="os-booking-unsync-apple-trigger"
											data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'unsync_event' ) ); ?>"
											data-os-after-call="latepointAppleCalendarAdminAddon.event_unsynced"
											data-os-pass-this="yes"
											data-os-params="<?php echo esc_attr( $data_params ); ?>">
										</div>
										<div class="os-booking-sync-apple-trigger"
											data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'sync_event' ) ); ?>"
											data-os-after-call="latepointAppleCalendarAdminAddon.event_synced"
											data-os-pass-this="yes"
											data-os-params="<?php echo esc_attr( $data_params ); ?>">
										</div>
										<div class="os-name"><?php echo esc_html( $event_title ); ?></div>
										<div class="os-date"><?php echo esc_html( $when ); ?></div>
										<div class="os-date"><?php echo esc_html( $event['time'] ); ?></div>
									</div>
									<?php
								}
								?>
							</div>
						</div>
						<?php
					}
					$events_html .= ob_get_clean();
				}
				$synced_bookings_percent = $total_events ? min( round( $total_synced_events / $total_events * 100 ), 100 ) : 0;
				?>

				<!-- Calendar Sync Card -->
				<div class="syncing-calendar-wrapper">
					<div class="os-sync-stats-and-progress-w">
						<div class="os-sync-toggler-w">
							<div class="calendar-available-for-sync"
								data-confirm="<?php esc_html_e( 'Are you sure you want to disconnect this calendar? Events will no longer block time slots in LatePoint.', 'latepoint-apple-calendar' ); ?>"
								data-agent-id="<?php echo $agent_id; ?>"
								data-calendar-id="<?php echo $connected_calendar['url']; ?>"
								data-route="<?php echo OsRouterHelper::build_route_name( 'apple_calendar', 'disable_calendar_for_pull' ); ?>">
								<?php echo OsFormHelper::toggler_field( 'connected_calendar_' . $index, $connected_calendar['name'], true, false, 'large' ); ?>
							</div>
							<?php echo $auto_sync_status_html; ?>
						</div>

						<div class="os-sync-stats">
							<?php if ( $total_events ) { ?>
								<div class="os-sync-value">
									<span><?php echo intval( $total_synced_events ); ?></span>
									<?php esc_html_e( ' of ', 'latepoint-apple-calendar' ); ?>
									<?php echo intval( $total_events ); ?>
								</div>
								<div class="os-sync-label"><?php esc_html_e( 'Events Synced', 'latepoint-apple-calendar' ); ?></div>
							<?php } else { ?>
								<div class="os-sync-value">0</div>
								<div class="os-sync-label"><?php esc_html_e( 'Events available to sync', 'latepoint-apple-calendar' ); ?></div>
							<?php } ?>

							<div class="os-sync-buttons">
								<?php if ( $total_events ) { ?>
									<a href="#"
										data-label-sync="<?php esc_html_e( 'Sync All Events Now', 'latepoint-apple-calendar' ); ?>"
										data-label-cancel-sync="<?php esc_html_e( 'Stop Syncing Now', 'latepoint-apple-calendar' ); ?>"
										class="sync-all-events-from-apple-trigger latepoint-btn latepoint-btn-outline latepoint-btn-sm">
										<i class="latepoint-icon latepoint-icon-grid-18"></i>
										<span><?php esc_html_e( 'Sync All Events Now', 'latepoint-apple-calendar' ); ?></span>
									</a>
								<?php } ?>
							</div>
						</div>

						<div class="os-sync-progress" data-total="<?php echo $total_events; ?>" data-value="<?php echo $total_synced_events; ?>">
							<div class="os-sync-progress-bar" style="width: <?php echo $synced_bookings_percent; ?>%"></div>
						</div>

						<?php if ( $events_html ) { ?>
							<div class="os-booking-tiny-boxes-container">
								<?php echo $events_html; ?>
							</div>
						<?php } ?>
					</div>
				</div>
				<?php
			}
		} else {
			?>
			<div class="sync-message-empty">
				<?php esc_html_e( 'Pick calendars from the list below to start syncing events from.', 'latepoint-apple-calendar' ); ?>
			</div>
			<?php
		}
		if ( $disconnected_calendars ) {
			?>
			<div class="os-section-header">
				<h3><?php esc_html_e( 'Calendars available for sync', 'latepoint-apple-calendar' ); ?></h3>
			</div>
			<?php
			foreach ( $disconnected_calendars as $index => $disconnected_calendar ) {
				?>
				<div class="calendar-available-for-sync is-disconnected"
					data-agent-id="<?php echo intval( $agent_id ); ?>"
					data-calendar-id="<?php echo esc_attr( $disconnected_calendar['url'] ); ?>"
					data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'apple_calendar', 'enable_calendar_for_pull' ) ); ?>">
					<?php echo OsFormHelper::toggler_field( 'disconnected_calendar_' . $index, $disconnected_calendar['name'], false ); ?>
				</div>
				<?php
			}
		}
	} else {
		?>
		<div class="latepoint-message latepoint-message-error">
			<?php esc_html_e( 'This apple account does not have any calendars.', 'latepoint-apple-calendar' ); ?>
		</div>
		<?php
	}
} else {
	?>
	<div class="latepoint-message latepoint-message-error">
		<?php esc_html_e( 'This agent has not authorized access to their Apple Calendar yet. Open agent profile and connect apple calendar.', 'latepoint-apple-calendar' ); ?>
	</div>
	<?php
}
?>
