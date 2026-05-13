<?php
/**
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 *
 * @package LatePoint\OutlookCalendar
 */

if ( $is_outlook_calendar_authorized ) {
	if ( $available_calendars ) {
		if ( $connected_calendars ) {
			echo '<div class="os-section-header">';
			echo '<h3>' . esc_html__( 'Synced Calendars', 'latepoint-outlook-calendar' ) . '</h3>';
			echo '</div>';
			foreach ( $connected_calendars as $index => $connected_calendar ) {
				// This calendar is selected for pull.
				$agent_watch_channel   = OsOutlookCalendarHelper::is_calendar_being_watched( $connected_calendar['id'], $agent->id, $agent_watch_channels );
				$auto_sync_status_html = '';
				if ( $agent_watch_channel ) {

					$seconds_left = $agent_watch_channel['expiration'] - time();
					$days_left    = round( $seconds_left / 86400 );

					$auto_sync_status_html     .= '<div class="channel-watch-status watch-status-on">';
						$auto_sync_status_html .= '<div class="status-watch-label">';
						$auto_sync_status_html .= '<i class="latepoint-icon latepoint-icon-check"></i>';
						$auto_sync_status_html .= '<span class="cw-status">' . __( 'Auto-Sync is Enabled', 'latepoint-outlook-calendar' ) . '</span>';
						$auto_sync_status_html .= '</div>';

						/* translators: %d is the number of days until the token expires */
						$auto_sync_status_html .= '<div class="cw-token-info"><span class="cw-expires">' . sprintf( __( 'Token Expires in %d days', 'latepoint-outlook-calendar' ), $days_left ) . '</span>';
						$auto_sync_status_html .= '<a href="#" class="latepoint-link"
							data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'refresh_watch' ) . '"
							data-os-params="' . OsUtilHelper::build_os_params(
								array(
									'agent_id'    => $agent->id,
									'calendar_id' => $connected_calendar['id'],
								)
							) . '"
							data-os-success-action="reload"><span class="latepoint-icon latepoint-icon-grid-18"></span><span>' . __( 'Refresh Token', 'latepoint-outlook-calendar' ) . '</span></a>';
						$auto_sync_status_html .= '</div>';

						$auto_sync_status_html .= '<a href="#" class="latepoint-link cw-danger"
							data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'stop_watch' ) . '"
							data-os-params="' . OsUtilHelper::build_os_params(
								array(
									'agent_id'    => $agent->id,
									'calendar_id' => $connected_calendar['id'],
								)
							) . '"
							data-os-success-action="reload"><span class="latepoint-icon latepoint-icon-bell-off"></span><span>' . __( 'Disable Auto-Sync', 'latepoint-outlook-calendar' ) . '</span></a>';
					$auto_sync_status_html     .= '</div>';
				} else {
					$auto_sync_status_html .= '<div class="channel-watch-status watch-status-off">';
					$auto_sync_status_html .= '<div class="status-watch-label">';
					$auto_sync_status_html .= '<i class="latepoint-icon latepoint-icon-bell-off"></i>';
					$auto_sync_status_html .= '<span class="cw-status">' . __( 'Auto-Sync is disabled', 'latepoint-outlook-calendar' ) . '</span>';
					$auto_sync_status_html .= '</div>';
					$auto_sync_status_html .= '<a href="#" class="latepoint-link cw-enable"
						data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'start_watch' ) . '"
						data-os-params="' . OsUtilHelper::build_os_params(
							array(
								'agent_id'    => $agent->id,
								'calendar_id' => $connected_calendar['id'],
							)
						) . '"
						data-os-success-action="reload"><span class="latepoint-icon latepoint-icon-grid-18"></span><span>' . __( 'Enable Auto-Sync', 'latepoint-outlook-calendar' ) . '</span></a>';
					$auto_sync_status_html .= '</div>';
				}

				$prev_date           = false;
				$total_events        = 0;
				$total_synced_events = 0;
				$events_html         = '';
				$dated_events        = array();
				$recurring_events    = array();
				$saved_db_event_ids  = array();

				foreach ( $calendars_with_events[ $connected_calendar['id'] ] as $ocal_event ) {
					// If slot type is "free", skip it.
					if ( isset( $ocal_event['showAs'] ) && $ocal_event['showAs'] === 'free' ) {
						continue;
					}

					if ( $total_events >= 500 ) {
						break;
					}

					// If it's a LatePoint connected booking not an Outlook event, skip to next record.
					$outlook_event_id     = $ocal_event['id'];
					$connected_booking_id = OsMetaHelper::get_booking_id_by_meta_value( 'outlook_calendar_event_id', $outlook_event_id );
					if ( $connected_booking_id ) {
						continue;
					}

					$start_date_obj = OsOutlookCalendarHelper::os_get_start_of_outlook_event( $ocal_event );
					$end_date_obj   = OsOutlookCalendarHelper::os_get_end_of_outlook_event( $ocal_event );
					$today_date_obj = new OsWpDateTime( 'now', OsTimeHelper::get_wp_timezone() );
					$today_date_obj->setTime( 0, 0, 0 );

					if ( ! $start_date_obj || ! $end_date_obj ) {
						continue;
					}

					// Check if the event is in the past and skip it.
					if ( ! empty( $ocal_event['recurrence'] ) ) {
						// Check if event in the past for recurring end data and skip it.
						$recurrence_end_date_obj = OsOutlookCalendarHelper::os_get_end_of_recurrence_event( $ocal_event );
						if ( $recurrence_end_date_obj <= $today_date_obj ) {
							continue;
						}
					} elseif ( $start_date_obj <= $today_date_obj ) {
						// Check if event in the past for regular events and skip it.
						continue;
					}

					// Update the stats and required data for further use.
					++$total_events;

					$saved_event = OsOutlookCalendarHelper::get_record_by_outlook_event_id( $outlook_event_id );
					if ( $saved_event ) {
						++$total_synced_events;
						$saved_event_id       = $saved_event->id;
						$saved_db_event_ids[] = $saved_event_id;
					} else {
						$saved_event_id = false;
					}

					// Process the event for display.
					if ( ! empty( $ocal_event['recurrence'] ) ) {

						$recurrence_info                                      = OsOutlookCalendarHelper::get_ocal_event_recurrences( $ocal_event, false );
						$recurring_events[ $recurrence_info[0]->frequency ][] = array(
							'summary'          => $ocal_event['subject'] ?? '',
							'outlook_event_id' => $ocal_event['id'],
							'recurrence_info'  => $recurrence_info[0],
							'saved_event_id'   => $saved_event_id,
							'start_date'       => $start_date_obj->format( 'Y-m-d' ),
							'recurrence_code'  => $ocal_event['recurrence'],
							'time'             => $start_date_obj->format( 'g:i a' ) . ' - ' . $end_date_obj->format( 'g:i a' ),
						);
					} else {

						$dated_events[ $start_date_obj->format( 'Ymd' ) ]['day']      = $start_date_obj->format( 'j' );
						$dated_events[ $start_date_obj->format( 'Ymd' ) ]['month']    = $start_date_obj->format( 'F' );
						$dated_events[ $start_date_obj->format( 'Ymd' ) ]['events'][] = array(
							'summary'          => $ocal_event['subject'] ?? '',
							'outlook_event_id' => $ocal_event['id'],
							'saved_event_id'   => $saved_event_id,
							'start_date'       => $start_date_obj->format( 'M j, Y' ),
							'end_date'         => $end_date_obj->format( 'M j, Y' ),
							'time'             => $start_date_obj->format( 'g:i a' ) . ' - ' . $end_date_obj->format( 'g:i a' ),
						);
					}
				}
				ksort( $dated_events );
				if ( $dated_events ) {
					$events_html .= '<h3 class="event-type-header">' . __( 'One-Time Events:', 'latepoint-outlook-calendar' ) . '</h3>';
				}
				foreach ( $dated_events as $events_for_date ) {
					$events_html .= '<div class="os-booking-tiny-boxes-w">';
					$events_html .= '<div class="os-booking-tiny-box-date">
					<div class="os-day">' . $events_for_date['day'] . '</div>
					<div class="os-month">' . $events_for_date['month'] . '</div>
					</div>';
					$events_html .= '<div class="os-booking-tiny-boxes-i">';
					foreach ( $events_for_date['events'] as $event ) {
						$event_title      = $hide_outlook_event_name ? __( 'Event from Outlook Calendar', 'latepoint-outlook-calendar' ) : ( ( $event['summary'] ?? __( '(No Title)', 'latepoint-outlook-calendar' ) ) );
						$synced_class     = $event['saved_event_id'] ? 'is-synced' : 'not-synced';
						$events_html     .= '<div class="os-booking-tiny-box event-is-in-outlook ' . $synced_class . '">';
							$events_html .= '<div class="os-booking-unsync-outlook-trigger"
								data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'unsync_event' ) . '"
								data-os-after-call="latepointOutlookCalendarAdminAddon.booking_unsynced" data-os-pass-this="yes"
								data-os-params="' . OsUtilHelper::build_os_params(
									array(
										'outlook_event_id' => $event['outlook_event_id'],
										'calendar_id'      => $connected_calendar['id'],
										'agent_id'         => $agent->id,
									)
								) . '"></div>';
							$events_html .= '<div class="os-booking-sync-outlook-trigger"
								data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'sync_event' ) . '"
								data-os-after-call="latepointOutlookCalendarAdminAddon.booking_synced" data-os-pass-this="yes"
								data-os-params="' . OsUtilHelper::build_os_params(
									array(
										'outlook_event_id' => $event['outlook_event_id'],
										'calendar_id'      => $connected_calendar['id'],
										'agent_id'         => $agent->id,
									)
								) . '"></div>';
							$events_html .= '<div class="os-name">' . $event_title . '</div>
								<div class="os-date">' . $event['start_date'] . ( $event['start_date'] !== $event['end_date'] ? '-' . $event['end_date'] : '' ) . '</div>
								<div class="os-date">' . $event['time'] . '</div>';
						$events_html     .= '</div>';
					}
					$events_html .= '</div>';
					$events_html .= '</div>';
				}
				if ( ! empty( $recurring_events ) ) {
					$events_html .= '<h3 class="event-type-header">' . __( 'Recurring Events:', 'latepoint-outlook-calendar' ) . '</h3>';
					foreach ( $recurring_events as $frequency => $events_for_frequency ) {
						$events_html .= '<div class="os-booking-tiny-boxes-w">
				                        <div class="os-booking-tiny-box-date">
				                          <div class="os-month">' . ucwords( strtolower( $frequency ) ) . '</div>
				                        </div>
				                        <div class="os-booking-tiny-boxes-i">';
						foreach ( $events_for_frequency as $event ) {
							$event_title  = $hide_outlook_event_name ? __( 'Event from Outlook Calendar', 'latepoint-outlook-calendar' ) : ( ( $event['summary'] ?? __( '(No Title)', 'latepoint-outlook-calendar' ) ) );
							$synced_class = $event['saved_event_id'] ? 'is-synced' : 'not-synced';

							$recurrence_info = $event['recurrence_info'];
							$interval        = $recurrence_info->interval > 1 ? $recurrence_info->interval : '';
							$weekday         = OsOutlookCalendarHelper::translate_weekdays( $recurrence_info->weekday );
							switch ( $recurrence_info->frequency ) {
								case 'YEARLY':
									$interval = $interval ? $interval . __( ' years', 'latepoint-outlook-calendar' ) : 'year';
									$when     = __( 'Every', 'latepoint-outlook-calendar' ) . ' ' . $interval . ' ' . __( 'on', 'latepoint-outlook-calendar' ) . ' ' . date_i18n( 'F j', strtotime( $event['start_date'] ) );
									break;
								case 'MONTHLY':
									$interval = $interval ? $interval . __( ' months', 'latepoint-outlook-calendar' ) : 'month';
									$when     = $weekday ? $weekday : __( 'day', 'latepoint-outlook-calendar' ) . ' ' . date_i18n( 'j', strtotime( $event['start_date'] ) );
									switch ( mb_substr( $when, 0, 1 ) ) {
										case '-':
											$when = __( 'last', 'latepoint-outlook-calendar' ) . ' ' . str_replace( '-1', '', $weekday );
											break;
										case '1':
											$when = __( 'first', 'latepoint-outlook-calendar' ) . ' ' . str_replace( '1', '', $weekday );
											break;
										case '2':
											$when = __( 'second', 'latepoint-outlook-calendar' ) . ' ' . str_replace( '2', '', $weekday );
											break;
										case '3':
											$when = __( 'third', 'latepoint-outlook-calendar' ) . ' ' . str_replace( '3', '', $weekday );
											break;
										case '4':
											$when = __( 'fourth', 'latepoint-outlook-calendar' ) . ' ' . str_replace( '4', '', $weekday );
											break;
									}
									$when = __( 'Every', 'latepoint-outlook-calendar' ) . ' ' . $interval . ' ' . __( 'on', 'latepoint-outlook-calendar' ) . ' ' . $when;
									break;
								case 'WEEKLY':
									$interval = $interval ? $interval . __( ' weeks', 'latepoint-outlook-calendar' ) : 'week';
									$when     = __( 'Every', 'latepoint-outlook-calendar' ) . ' ' . $interval . ' ' . __( 'on', 'latepoint-outlook-calendar' ) . ' ' . $weekday;
									break;
								case 'DAILY':
									$interval = $interval ? $interval . __( ' days', 'latepoint-outlook-calendar' ) : 'day';
									$when     = __( 'Every', 'latepoint-outlook-calendar' ) . ' ' . $interval . ' ' . __( 'starting', 'latepoint-outlook-calendar' ) . ' ' . date_i18n( 'F j, Y', strtotime( $event['start_date'] ) );
									break;
							}
							$events_html     .= '<div class="os-booking-tiny-box event-is-in-outlook ' . $synced_class . '">';
								$events_html .= '<div class="os-booking-unsync-outlook-trigger"
									data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'unsync_event' ) . '"
									data-os-after-call="latepointOutlookCalendarAdminAddon.booking_unsynced" data-os-pass-this="yes"
									data-os-params="' . OsUtilHelper::build_os_params(
										array(
											'outlook_event_id' => $event['outlook_event_id'],
											'calendar_id' => $connected_calendar['id'],
											'agent_id'    => $agent->id,
										)
									) . '"></div>';
								$events_html .= '<div class="os-booking-sync-outlook-trigger"
									data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'sync_event' ) . '"
									data-os-after-call="latepointOutlookCalendarAdminAddon.booking_synced" data-os-pass-this="yes"
									data-os-params="' . OsUtilHelper::build_os_params(
										array(
											'outlook_event_id' => $event['outlook_event_id'],
											'calendar_id' => $connected_calendar['id'],
											'agent_id'    => $agent->id,
										)
									) . '"></div>';
								$events_html .= '<div class="os-name">' . $event_title . '</div>
												<div class="os-date">' . $when . '</div>
												<div class="os-date">' . $event['time'] . '</div>';
							$events_html     .= '</div>';
						}
						$events_html .= '</div>';
						$events_html .= '</div>';
					}
				}
				$deleted_events = new OsOutlookCalendarEventModel();
				if ( ! empty( $saved_db_event_ids ) ) {
					$deleted_events->where( array( 'id NOT IN ' => $saved_db_event_ids ) );
				}
				$deleted_events = $deleted_events->where(
					array(
						'start_date >'        => OsTimeHelper::today_date(),
						'agent_id'            => $agent->id,
						'outlook_calendar_id' => $connected_calendar['id'],
					)
				)->get_results_as_models();

				$deleted_recurring_events = new OsOutlookCalendarEventModel();
				if ( ! empty( $saved_db_event_ids ) ) {
					$deleted_recurring_events->where( array( LATEPOINT_TABLE_OUTLOOK_RECURRENCES . '.lp_event_id NOT IN ' => $saved_db_event_ids ) );
				}
				$deleted_recurring_events = $deleted_recurring_events->join( LATEPOINT_TABLE_OUTLOOK_RECURRENCES, array( 'lp_event_id' => LATEPOINT_TABLE_OUTLOOK_EVENTS . '.id' ) )->group_by( LATEPOINT_TABLE_OUTLOOK_EVENTS . '.id' )->where(
					array(
						'start_date <=' => OsTimeHelper::today_date(),
						'agent_id'      => $agent->id,
						LATEPOINT_TABLE_OUTLOOK_RECURRENCES . '.until >=' => OsTimeHelper::today_date(),
					)
				)->get_results_as_models();

				if ( $deleted_recurring_events ) {
					if ( $deleted_events ) {
						$deleted_events = array_merge( $deleted_events, $deleted_recurring_events );
					} else {
						$deleted_events = $deleted_recurring_events;
					}
				}

				if ( $deleted_events ) {
					$events_html .= '<h3 class="event-type-header">' . __( 'Not in Outlook Calendar anymore', 'latepoint-outlook-calendar' ) . '</h3>';
					$events_html .= '<div class="os-booking-tiny-boxes-w">
				                    <div class="os-booking-tiny-box-date">
				                      <div class="os-month">' . __( 'Not Found', 'latepoint-outlook-calendar' ) . '</div>
				                    </div>
				                    <div class="os-booking-tiny-boxes-i">';
					foreach ( $deleted_events as $event ) {
						$event_title  = $hide_outlook_event_name ? __( 'Event from Outlook Calendar', 'latepoint-outlook-calendar' ) : $event->summary;
						$events_html .= '<div class="os-booking-tiny-box is-synced-not-exist">
				                        	<div class="os-booking-unsync-outlook-trigger" data-os-action="' . OsRouterHelper::build_route_name( 'outlook_calendar', 'unsync_event' ) . '" data-os-after-call="latepointOutlookCalendarAdminAddon.ocal_event_deleted" data-os-pass-this="yes" data-os-params="' . OsUtilHelper::build_os_params( array( 'outlook_event_id' => $event->outlook_event_id ) ) . '"></div>
											<div class="os-name">' . $event_title . '</div>
											<div class="os-date">' . $event->nice_start_date . '</div>
											<div class="os-date">' . $event->nice_start_time . '</div>
				                        </div>';
					}
					$events_html .= '</div>';
					$events_html .= '</div>';
				}
				$synced_bookings_percent = $total_events ? min( round( $total_synced_events / $total_events * 100 ), 100 ) : 0;
				?>
				<div class="syncing-calendar-wrapper">
					<div class="os-sync-stats-and-progress-w">
						<div class="os-sync-toggler-w">
							<div class="calendar-available-for-sync" data-confirm="<?php esc_attr_e( 'Are you sure you want to disconnect this calendar?', 'latepoint-outlook-calendar' ); ?>" data-agent-id="<?php echo esc_attr( $agent->id ); ?>" data-calendar-id="<?php echo esc_attr( $connected_calendar['id'] ); ?>" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'outlook_calendar', 'disable_calendar_for_pull' ) ); ?>">
								<?php echo OsFormHelper::toggler_field( 'connected_calendar_' . $index, $connected_calendar['name'], true, false, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<?php echo $auto_sync_status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="os-sync-stats">
							<?php if ( $total_events ) { ?>
								<div class="os-sync-value"><?php echo '<span>' . esc_html( $total_synced_events ) . '</span>' . esc_html__( ' of ', 'latepoint-outlook-calendar' ) . esc_html( $total_events ); ?></div>
								<div class="os-sync-label"><?php esc_html_e( 'Events Synced', 'latepoint-outlook-calendar' ); ?></div>
							<?php } else { ?>
								<div class="os-sync-value">0</div>
								<div class="os-sync-label"><?php esc_html_e( 'Events available to sync', 'latepoint-outlook-calendar' ); ?></div>
							<?php } ?>
							<div class="os-sync-buttons">
								<?php if ( $total_events ) { ?>
									<a href="#" data-label-sync="<?php esc_attr_e( 'Sync All Events Now', 'latepoint-outlook-calendar' ); ?>" data-label-cancel-sync="<?php esc_attr_e( 'Stop Syncing Now', 'latepoint-outlook-calendar' ); ?>" class="sync-all-bookings-to-outlook-trigger latepoint-btn latepoint-btn-outline latepoint-btn-sm">
										<i class="latepoint-icon latepoint-icon-grid-18"></i>
										<span><?php esc_html_e( 'Sync All Events Now', 'latepoint-outlook-calendar' ); ?></span>
									</a>
								<?php } ?>
							</div>
						</div>
						<div class="os-sync-progress" data-total="<?php echo esc_attr( $total_events ); ?>" data-value="<?php echo esc_attr( $total_synced_events ); ?>">
							<div class="os-sync-progress-bar" style="width: <?php echo esc_attr( $synced_bookings_percent ); ?>%"></div>
						</div>
						<?php
						if ( $events_html ) {
							echo '<div class="os-booking-tiny-boxes-container">' . $events_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</div>
				</div>
				<?php
			}
		} else {
			echo '<div class="sync-message-empty">' . esc_html__( 'Pick calendars from the list below to start syncing events from.', 'latepoint-outlook-calendar' ) . '</div>';
		}

		if ( $disconnected_calendars ) {
			echo '<div class="os-section-header">';
			echo '<h3>' . esc_html__( 'Calendars available for sync', 'latepoint-outlook-calendar' ) . '</h3>';
			echo '</div>';
			foreach ( $disconnected_calendars as $index => $disconnected_calendar ) {
				?>
				<div class="calendar-available-for-sync is-disconnected" data-agent-id="<?php echo esc_attr( $agent->id ); ?>" data-calendar-id="<?php echo esc_attr( $disconnected_calendar['id'] ); ?>" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'outlook_calendar', 'enable_calendar_for_pull' ) ); ?>">
					<?php echo OsFormHelper::toggler_field( 'disconnected_calendar_' . $index, $disconnected_calendar['name'], false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php
			}
		}
	} else {
		echo '<div class="latepoint-message latepoint-message-error">' . esc_html__( 'This Outlook account does not have any calendars.', 'latepoint-outlook-calendar' ) . '</div>';
	}
} else {
	echo '<div class="latepoint-message latepoint-message-error">' . esc_html__( 'This agent has not authorized access to their Outlook Calendar yet. Open agent profile and click sign in with Outlook button.', 'latepoint-outlook-calendar' ) . '</div>';
}
