<?php
if($is_google_calendar_authorized){
	if($available_calendars){
		if($connected_calendars){
			echo '<div class="os-section-header">';
				echo '<h3>'.__('Synced Calendars', 'latepoint-google-calendar').'</h3>';
			echo '</div>';
			foreach($connected_calendars as $index => $connected_calendar){
					// this calendar is selected for pull
					$agent_watch_channel = OsGoogleCalendarHelper::is_calendar_being_watched($connected_calendar['id'], $agent->id, $agent_watch_channels);
					$auto_sync_status_html = '';
					if($agent_watch_channel){
					  $auto_sync_status_html.= '<div class="channel-watch-status watch-status-on">';
					    $auto_sync_status_html.= '<div class="status-watch-label">';
					      $auto_sync_status_html.= '<i class="latepoint-icon latepoint-icon-check"></i>';
					      $auto_sync_status_html.= '<span class="cw-status">'.__('Auto-Sync is Enabled', 'latepoint-google-calendar').'</span>';
					    $auto_sync_status_html.= '</div>';

					    $seconds_left = ($agent_watch_channel['expiration'] / 1000) - time();
					    $days_left = round($seconds_left / 86400);

					    $auto_sync_status_html.= '<div class="cw-token-info"><span class="cw-expires">'.sprintf(__('Token Expires in %d days', 'latepoint-google-calendar'), $days_left).'</span>';
					    $auto_sync_status_html.= '<a href="#" class="latepoint-link" data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'refresh_watch').'" 
					                      data-os-params="'.OsUtilHelper::build_os_params(['agent_id' => $agent->id, 'calendar_id' => $connected_calendar['id']]).'" 
					                      data-os-success-action="reload"><span class="latepoint-icon latepoint-icon-grid-18"></span><span>'.__('Refresh Token', 'latepoint-google-calendar').'</span></a></div>';
					    $auto_sync_status_html.= '<a href="#" class="latepoint-link cw-danger" 
					                      data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'stop_watch').'" 
					                      data-os-params="'.OsUtilHelper::build_os_params(['agent_id' => $agent->id, 'calendar_id' => $connected_calendar['id']]).'" 
					                      data-os-success-action="reload"><span class="latepoint-icon latepoint-icon-bell-off"></span><span>'.__('Disable Auto-Sync', 'latepoint-google-calendar').'</span></a>';
					  $auto_sync_status_html.= '</div>';
					}else{
					  $auto_sync_status_html.= '<div class="channel-watch-status watch-status-off">';
					    $auto_sync_status_html.= '<div class="status-watch-label">';
					      $auto_sync_status_html.= '<i class="latepoint-icon latepoint-icon-bell-off"></i>';
					      $auto_sync_status_html.= '<span class="cw-status">'.__('Auto-Sync is disabled', 'latepoint-google-calendar').'</span>';
					    $auto_sync_status_html.= '</div>';
					    $auto_sync_status_html.= '<a href="#" class="latepoint-link cw-enable" 
					                      data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'start_watch').'" 
					                      data-os-params="'.OsUtilHelper::build_os_params(['agent_id' => $agent->id, 'calendar_id' => $connected_calendar['id']]).'" 
					                      data-os-success-action="reload"><span class="latepoint-icon latepoint-icon-grid-18"></span><span>'.__('Enable Auto-Sync', 'latepoint-google-calendar').'</span></a>';
					  $auto_sync_status_html.= '</div>';
					}

				  $prev_date = false;
				  $total_events = 0;
				  $total_synced_events = 0;
				  $events_html = '';
				  $dated_events = [];
				  $recurring_events = [];
				    foreach ($calendars_with_events[$connected_calendar['id']] as $gcal_event) {
				      // if slot type is to "free" skip it
				      if($gcal_event->getTransparency() == 'transparent') continue;
				      // recurring connected event, skip it for now
				      if(!empty($gcal_event->getRecurringEventId())) continue;
				      // if its a latepoint connected booking not a google event - skip to next record
				      $google_event_id = $gcal_event->getId();
				      $connected_booking_id = OsMetaHelper::get_booking_id_by_meta_value('google_calendar_event_id', $google_event_id);
				      if($connected_booking_id) continue;
				      $total_events++;
				      if($total_events >= 500) break;

				      $saved_event = OsGoogleCalendarHelper::get_record_by_google_event_id($google_event_id);
				      if($saved_event){
				        $total_synced_events++;
				        $saved_event_id = $saved_event->id;
				        $saved_db_event_ids[] = $saved_event_id;
				      }else{
				        $saved_event_id = false;
				      }
				      $start_date_obj = OsGoogleCalendarHelper::os_get_start_of_google_event($gcal_event);
				      $end_date_obj = OsGoogleCalendarHelper::os_get_end_of_google_event($gcal_event);
				      if(!$start_date_obj || !$end_date_obj) continue;
				      if(!empty($gcal_event->getRecurrence())){
				        $recurrence_info = OsGoogleCalendarHelper::get_gcal_event_recurrences($gcal_event, false);
				        $recurring_events[$recurrence_info[0]->frequency][] = ['summary' => $gcal_event->getSummary(),
				                                'google_event_id' => $gcal_event->getId(),
				                                'recurrence_info' => $recurrence_info[0],
				                                'saved_event_id' => $saved_event_id,
				                                'start_date' => $start_date_obj->format('Y-m-d'),
				                                'recurrence_code' => $gcal_event->getRecurrence(),
				                                'time' => $start_date_obj->format('g:i a') . ' - '. $end_date_obj->format('g:i a')];
				      }else{
				        $dated_events[$start_date_obj->format('Ymd')]['day'] = $start_date_obj->format('j');
				        $dated_events[$start_date_obj->format('Ymd')]['month'] = $start_date_obj->format('F');
				        $dated_events[$start_date_obj->format('Ymd')]['events'][] = ['summary' => $gcal_event->getSummary(),
				                                                          'google_event_id' => $gcal_event->getId(),
				                                                          'saved_event_id' => $saved_event_id,
				                                                          'start_date' => $start_date_obj->format("M j, Y"),
				                                                          'end_date' => $end_date_obj->format("M j, Y"),
				                                                          'time' => $start_date_obj->format('g:i a') . ' - '. $end_date_obj->format('g:i a')];
				      }
				    }
				  ksort($dated_events);
					if($dated_events) $events_html.= '<h3 class="event-type-header">'.__('One-Time Events:', 'latepoint-google-calendar').'</h3>';
				  foreach($dated_events as $events_for_date){
				    $events_html.= '<div class="os-booking-tiny-boxes-w">
				                      <div class="os-booking-tiny-box-date">
				                        <div class="os-day">'.$events_for_date['day'].'</div>
				                        <div class="os-month">'.$events_for_date['month'].'</div>
				                      </div>
				                      <div class="os-booking-tiny-boxes-i">';
				                        foreach($events_for_date['events'] as $event){
																	$title = $hide_gcal_event_name ? __('Event from Google Calendar', 'latepoint-google-calendar') : (($event['summary'] ?? __('(No Title)', 'latepoint-google-calendar')));
				                          $synced_class = $event['saved_event_id'] ? 'is-synced' : 'not-synced';
				                          $events_html.= '<div class="os-booking-tiny-box event-is-in-google '.$synced_class.'">
				                            <div class="os-booking-unsync-google-trigger" data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'unsync_event').'"
				                                                                        data-os-after-call="latepointGoogleCalendarAdminAddon.booking_unsynced" 
				                                                                        data-os-pass-this="yes" 
				                                                                        data-os-params="'.OsUtilHelper::build_os_params(['google_event_id' => $event['google_event_id'], 'calendar_id' => $connected_calendar['id'], 'agent_id' => $agent->id]).'"></div>
				                            <div class="os-booking-sync-google-trigger" data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'sync_event').'"
				                                                                        data-os-after-call="latepointGoogleCalendarAdminAddon.booking_synced" 
				                                                                        data-os-pass-this="yes" 
				                                                                        data-os-params="'.OsUtilHelper::build_os_params(['google_event_id' => $event['google_event_id'], 'calendar_id' => $connected_calendar['id'], 'agent_id' => $agent->id]).'"></div>
				                            <div class="os-name">'.$title.'</div>
				                            <div class="os-date">'.$event['start_date'].(($event['start_date'] != $event['end_date']) ? '-'.$event['end_date'] : '').'</div>
				                            <div class="os-date">'.$event['time'].'</div>
				                          </div>';
				                        }
				                      $events_html.= '</div>';
				                    $events_html.= '</div>';
				  }
				  if(!empty($recurring_events)){
				    $events_html.= '<h3 class="event-type-header">'.__('Recurring Events:', 'latepoint-google-calendar').'</h3>';
				    foreach($recurring_events as $frequency => $events_for_frequency){
				      $events_html.= '<div class="os-booking-tiny-boxes-w">
				                        <div class="os-booking-tiny-box-date">
				                          <div class="os-month">'.ucwords(strtolower($frequency)).'</div>
				                        </div>
				                        <div class="os-booking-tiny-boxes-i">';
				                          foreach($events_for_frequency as $event){
																		$title = $hide_gcal_event_name ? __('Event from Google Calendar', 'latepoint-google-calendar') : (($event['summary'] ?? __('(No Title)', 'latepoint-google-calendar')));
				                            $synced_class = $event['saved_event_id'] ? 'is-synced' : 'not-synced';

				                            $recurrence_info = $event['recurrence_info'];
				                            $interval = ($recurrence_info->interval > 1) ? $recurrence_info->interval : '';
				                            $weekday = OsGoogleCalendarHelper::translate_weekdays($recurrence_info->weekday);
				                            switch($recurrence_info->frequency){
				                              case 'YEARLY';
				                                $interval = ($interval) ? $interval.__(' years') : 'year';
				                                $when = __('Every', 'latepoint-google-calendar').' '.$interval.' '.__('on', 'latepoint-google-calendar').' '.date_i18n("F j", strtotime($event['start_date']));
				                                break;
				                              case 'MONTHLY':
				                                $interval = ($interval) ? $interval.__(' months') : 'month';
				                                $when = ($weekday) ? $weekday : __('day', 'latepoint-google-calendar').' '.date_i18n("j", strtotime($event['start_date']));
				                                switch(mb_substr($when, 0, 1)){
				                                  case '-':
				                                    $when = __('last','latepoint-google-calendar').' '.str_replace('-1', '', $weekday);
				                                    break;
				                                  case '1':
				                                    $when = __('first').' '.str_replace('1', '', $weekday);
				                                    break;
				                                  case '2':
				                                    $when = __('second').' '.str_replace('2', '', $weekday);
				                                    break;
				                                  case '3':
				                                    $when = __('third').' '.str_replace('3', '', $weekday);
				                                    break;
				                                  case '4':
				                                    $when = __('fourth').' '.str_replace('4', '', $weekday);
				                                    break;
				                                }
				                                $when = __('Every', 'latepoint-google-calendar').' '.$interval.' '.__('on', 'latepoint-google-calendar').' '.$when;
				                                break;
				                              case 'WEEKLY':
				                                $interval = ($interval) ? $interval.__(' weeks') : 'week';
				                                $when = __('Every', 'latepoint-google-calendar').' '.$interval.' '.__('on', 'latepoint-google-calendar').' '.$weekday;
				                                break;
				                              case 'DAILY';
				                                $interval = ($interval) ? $interval.__(' days') : 'day';
				                                $when = __('Every', 'latepoint-google-calendar').' '.$interval.' '.__('starting', 'latepoint-google-calendar').' '.date_i18n("F j, Y", strtotime($event['start_date']));
				                                break;
				                            }
				                            $events_html.= '<div class="os-booking-tiny-box event-is-in-google '.$synced_class.'">
				                              <div class="os-booking-unsync-google-trigger" data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'unsync_event').'"
				                                                                          data-os-after-call="latepointGoogleCalendarAdminAddon.booking_unsynced" 
				                                                                          data-os-pass-this="yes" 
				                                                                          data-os-params="'.OsUtilHelper::build_os_params(['google_event_id' => $event['google_event_id'], 'calendar_id' => $connected_calendar['id'], 'agent_id' => $agent->id]).'"></div>
				                              <div class="os-booking-sync-google-trigger" data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'sync_event').'"
				                                                                          data-os-after-call="latepointGoogleCalendarAdminAddon.booking_synced" 
				                                                                          data-os-pass-this="yes" 
				                                                                          data-os-params="'.OsUtilHelper::build_os_params(['google_event_id' => $event['google_event_id'], 'calendar_id' => $connected_calendar['id'], 'agent_id' => $agent->id]).'"></div>
				                              <div class="os-name">'.$title.'</div>
				                              <div class="os-date">'.$when.'</div>
				                              <div class="os-date">'.$event['time'].'</div>
				                            </div>';
				                          }
				                        $events_html.= '</div>';
				                      $events_html.= '</div>';
				    }
				  }
				  $deleted_events = new OsGoogleCalendarEventModel();
				  if(!empty($saved_db_event_ids)) $deleted_events->where(['id NOT IN ' => $saved_db_event_ids]);
				  $deleted_events = $deleted_events->where(['start_date >' => OsTimeHelper::today_date(), 'agent_id' => $agent->id, 'google_calendar_id' => $connected_calendar['id']])->get_results_as_models();

				  $deleted_recurring_events = new OsGoogleCalendarEventModel();
				  if(!empty($saved_db_event_ids)) $deleted_recurring_events->where([LATEPOINT_TABLE_GCAL_RECURRENCES.'.lp_event_id NOT IN ' => $saved_db_event_ids]);
				  $deleted_recurring_events = $deleted_recurring_events->join(LATEPOINT_TABLE_GCAL_RECURRENCES, ['lp_event_id' => LATEPOINT_TABLE_GCAL_EVENTS.'.id'])->group_by(LATEPOINT_TABLE_GCAL_EVENTS.'.id')->where(['start_date <=' => OsTimeHelper::today_date(), 'agent_id' => $agent->id, LATEPOINT_TABLE_GCAL_RECURRENCES.'.until >=' => OsTimeHelper::today_date()])->get_results_as_models();

				  if($deleted_recurring_events){
				    if($deleted_events){
				      $deleted_events = array_merge($deleted_events, $deleted_recurring_events);
				    }else{
				      $deleted_events = $deleted_recurring_events;
				    }
				  }

				  if($deleted_events){
				    $events_html.= '<h3 class="event-type-header">'.__('Not in Google Calendar anymore', 'latepoint-google-calendar').'</h3>';
				    $events_html.= '<div class="os-booking-tiny-boxes-w">
				                    <div class="os-booking-tiny-box-date">
				                      <div class="os-month">'.__('Not Found', 'latepoint-google-calendar').'</div>
				                    </div>
				                    <div class="os-booking-tiny-boxes-i">';
				                      foreach($deleted_events as $event){
																$title = $hide_gcal_event_name ? __('Event from Google Calendar', 'latepoint-google-calendar') : $event->summary;
				                        $events_html.= '<div class="os-booking-tiny-box is-synced-not-exist">
				                          <div class="os-booking-unsync-google-trigger" data-os-action="'.OsRouterHelper::build_route_name('google_calendar', 'unsync_event').'"
				                                                                      data-os-after-call="latepointGoogleCalendarAdminAddon.gcal_event_deleted" 
				                                                                      data-os-pass-this="yes" 
				                                                                      data-os-params="'.OsUtilHelper::build_os_params(['google_event_id' => $event->google_event_id]).'"></div>
				                          <div class="os-name">'.$title.'</div>
				                          <div class="os-date">'.$event->nice_start_date.'</div>
				                          <div class="os-date">'.$event->nice_start_time.'</div>
				                        </div>';
				                      }
				                    $events_html.= '</div>';
				                  $events_html.= '</div>';
				  }
				  $synced_bookings_percent = ($total_events) ? min(round(($total_synced_events / $total_events) * 100), 100) : 0;
				  ?>
					<div class="syncing-calendar-wrapper">
				  <div class="os-sync-stats-and-progress-w">
					  <div class="os-sync-toggler-w">
						  <div class="calendar-available-for-sync" data-confirm="<?php _e('Are you sure you want to disconnect this calendar?', 'latepoint-google-calendar') ?>" data-agent-id="<?php echo $agent->id; ?>" data-calendar-id="<?php echo $connected_calendar['id'] ?>" data-route="<?php echo OsRouterHelper::build_route_name('google_calendar', 'disable_calendar_for_pull') ?>">
								<?php echo OsFormHelper::toggler_field('connected_calendar_'.$index, $connected_calendar['title'], true, false, 'large'); ?>
							</div>
						  <?php echo $auto_sync_status_html; ?>
					  </div>
					  <div class="os-sync-stats">
						  <?php if($total_events){ ?>
						    <div class="os-sync-value"><?php echo '<span>'.$total_synced_events.'</span>'.__(' of ', 'latepoint-google-calendar').$total_events; ?></div>
						    <div class="os-sync-label"><?php _e('Events Synced', 'latepoint-google-calendar'); ?></div>
							<?php }else{ ?>
						    <div class="os-sync-value">0</div>
						    <div class="os-sync-label"><?php _e('Events available to sync', 'latepoint-google-calendar'); ?></div>
						  <?php }?>
						  <div class="os-sync-buttons">
							  <?php if($total_events){ ?>
						    <a href="#" data-label-sync="<?php _e('Sync All Events Now', 'latepoint-google-calendar'); ?>" data-label-cancel-sync="<?php _e('Stop Syncing Now', 'latepoint-google-calendar'); ?>" class="sync-all-bookings-to-google-trigger latepoint-btn latepoint-btn-outline latepoint-btn-sm">
						      <i class="latepoint-icon latepoint-icon-grid-18"></i>
						      <span><?php _e('Sync All Events Now', 'latepoint-google-calendar'); ?></span>
						    </a>
								<?php } ?>
						  </div>
					  </div>
					  <div class="os-sync-progress" data-total="<?php echo $total_events; ?>" data-value="<?php echo $total_synced_events; ?>">
						  <div class="os-sync-progress-bar" style="width: <?php echo $synced_bookings_percent; ?>%"></div>
					  </div>
					  <?php if($events_html) echo '<div class="os-booking-tiny-boxes-container">'. $events_html. '</div>'; ?>
				  </div>
				  </div>

					<?php
			}
		}else{
			echo '<div class="sync-message-empty">'.__('Pick calendars from the list below to start syncing events from.', 'latepoint-google-calendar').'</div>';
		}
		if($disconnected_calendars){
			echo '<div class="os-section-header">';
				echo '<h3>'.__('Calendars available for sync', 'latepoint-google-calendar').'</h3>';
			echo '</div>';
			foreach($disconnected_calendars as $index => $disconnected_calendar){ ?>
				<div class="calendar-available-for-sync is-disconnected" data-agent-id="<?php echo $agent->id; ?>" data-calendar-id="<?php echo $disconnected_calendar['id'] ?>" data-route="<?php echo OsRouterHelper::build_route_name('google_calendar', 'enable_calendar_for_pull') ?>">
					<?php echo OsFormHelper::toggler_field('disconnected_calendar_'.$index, $disconnected_calendar['title'], false); ?>
				</div><?php
			}
		}
	}else{
		echo '<div class="latepoint-message latepoint-message-error">'.__('This google account does not have any calendars.', 'latepoint-google-calendar').'</div>';
	}
	/*
	echo '<div class="os-section-header mt-4">';
		echo '<h3>'.__('Sync Settings', 'latepoint-google-calendar').'</h3>';
	echo '</div>';
	echo OsFormHelper::toggler_field('google_calendar_show_event_titles', __('Show titles of imported events', 'latepoint-google-calendar'), ($agent->get_meta_by_key('google_calendar_show_event_titles') == LATEPOINT_VALUE_ON), false, false, ['sub_label' => __('For privacy reasons titles of events imported from Google Calendar are hidden', 'latepoint-google-calendar')]);
	*/
}else{
	echo '<div class="latepoint-message latepoint-message-error">'.__('This agent has not authorized access to their Google Calendar yet. Open agent profile and click sign in with google button.', 'latepoint-google-calendar').'</div>';
}