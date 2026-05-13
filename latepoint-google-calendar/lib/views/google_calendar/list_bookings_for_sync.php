<?php
if($is_google_calendar_authorized){
	if($calendar_id_for_push){
		$disconnect_prompt = __('Are you sure you want to stop syncing bookings to this calendar? Bookings that were already synced to this calendar will stay there, you can remove them by clicking on Unsync All Bookings button, prior to disconnecting this calendar.', 'latepoint-google-calendar');
		$disconnect_action = OsRouterHelper::build_route_name('google_calendar', 'disable_calendar_for_push');
		$disconnect_params = OsUtilHelper::build_os_params(['agent_id' => $agent->id]);
		echo '<div class="channel-watch-status watch-status-on">
						<div class="status-watch-label">
							<i class="latepoint-icon latepoint-icon-check"></i>
							<span class="cw-status">'.__('New Bookings will be automatically synced to ', 'latepoint-google-calendar').'<strong>'.OsGoogleCalendarHelper::get_calendar_name_by_id($calendar_id_for_push, $agent->id).'</strong></span>
						</div>
						<a href="#" class="latepoint-link cw-danger" data-os-success-action="reload" data-os-action="'.$disconnect_action.'" data-os-params="'.$disconnect_params.'" data-os-prompt="'.$disconnect_prompt.'">
							<span class="latepoint-icon latepoint-icon-bell-off"></span>
							<span>'.__('Stop Syncing', 'latepoint-google-calendar').'</span>
						</a>
					</div>';

		if($future_bookings){ ?>
			<div class="syncing-calendar-wrapper">
				<div class="os-sync-stats-and-progress-w">
					<div class="os-sync-stats">
						<div class="os-sync-value"><?php echo '<span>'.$total_synced_future_bookings.'</span>'.__(' of ', 'latepoint-google-calendar').$total_future_bookings; ?></div>
						<div class="os-sync-label">
							<?php echo __('Bookings Synced to ', 'latepoint-google-calendar').'<strong>'.OsGoogleCalendarHelper::get_calendar_name_by_id($calendar_id_for_push, $agent->id).'</strong>'; ?>
						</div>
						<div class="os-sync-buttons">
							<a href="#" data-label-sync="<?php _e('Sync All Bookings to Google', 'latepoint-google-calendar'); ?>" data-label-cancel-sync="<?php _e('Stop Syncing Now', 'latepoint-google-calendar'); ?>" class="sync-all-bookings-to-google-trigger latepoint-btn latepoint-btn-outline latepoint-btn-sm">
								<i class="latepoint-icon latepoint-icon-grid-18"></i>
								<span><?php _e('Sync All Bookings', 'latepoint-google-calendar'); ?></span>
							</a>
							<a href="#" data-os-prompt="<?php _e('Are you sure you want to remove all synced bookings from Google Calendar? They will remain in LatePoint, but will be removed from google calendar.', 'latepoint-google-calendar'); ?>" data-label-remove="<?php _e('Remove Bookings from Google Calendar', 'latepoint-google-calendar'); ?>" data-label-cancel-remove="<?php _e('Stop Removing', 'latepoint-google-calendar'); ?>" class="remove-all-bookings-from-google-trigger latepoint-btn latepoint-btn-outline latepoint-btn-danger latepoint-btn-sm">
								<i class="latepoint-icon latepoint-icon-x"></i>
								<span><?php _e('Unsync All Bookings', 'latepoint-google-calendar'); ?></span>
							</a>
						</div>
					</div>
					<div class="os-sync-progress" data-total="<?php echo $total_future_bookings; ?>" data-value="<?php echo $total_synced_future_bookings; ?>">
						<div class="os-sync-progress-bar" style="width: <?php echo $synced_bookings_percent; ?>%"></div>
					</div>
				</div>
				<div class="os-booking-tiny-boxes-container">
					<div class="os-booking-tiny-boxes-w">
						<?php
						$prev_date = false;
						foreach($future_bookings as $booking){
							$is_synced = $booking->get_meta_by_key('google_calendar_event_id', false);
							if(!$prev_date || $prev_date != $booking->start_date){
								if($prev_date) echo '</div></div><div class="os-booking-tiny-boxes-w">';
								$prev_date = $booking->start_date;
								echo '<div class="os-booking-tiny-box-date">
							<div class="os-day">'.$booking->format_start_date_and_time('j').'</div>
							<div class="os-month">'.$booking->format_start_date_and_time('F').'</div>
						</div><div class="os-booking-tiny-boxes-i">';
							} ?>
							<div <?php echo OsBookingHelper::quick_booking_btn_html($booking->id); ?> class="os-booking-tiny-box <?php echo ($is_synced) ? 'is-synced' : 'not-synced'; ?> booking-status-<?php echo $booking->status; ?> <?php if(OsGoogleCalendarHelper::is_booking_status_syncable($booking->status)) echo 'booking-should-be-syncable'; ?>">
								<div class="os-booking-unsync-google-trigger" data-os-action="<?php echo OsRouterHelper::build_route_name('google_calendar', 'delete_booking'); ?>"
								     data-os-after-call="latepointGoogleCalendarAdminAddon.booking_unsynced"
								     data-os-pass-this="yes"
								     data-os-params="<?php echo OsUtilHelper::build_os_params(['booking_id' => $booking->id]); ?>"></div>
								<div class="os-booking-sync-google-trigger" data-os-action="<?php echo OsRouterHelper::build_route_name('google_calendar', 'sync_booking'); ?>"
								     data-os-remove-action="<?php echo OsRouterHelper::build_route_name('google_calendar', 'delete_booking'); ?>"
								     data-os-after-call="latepointGoogleCalendarAdminAddon.booking_synced"
								     data-os-pass-this="yes"
								     data-os-params="<?php echo OsUtilHelper::build_os_params(['booking_id' => $booking->id]); ?>"></div>
								<div class="os-name"><?php echo esc_html($booking->service->name); ?></div>
								<div class="os-date"><?php echo $booking->nice_start_date; ?></div>
								<div class="os-date"><?php echo $booking->nice_start_time . ' - '. $booking->nice_end_time; ?></div>
							</div>
						<?php } ?>
					</div>
				</div>
			</div><?php
		}else{ ?>
			<div class="no-results-w">
			<div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
			<h2><?php _e('This agent does not have any appointments yet', 'latepoint-google-calendar'); ?></h2>
			<a href="#" <?php echo OsBookingHelper::quick_booking_btn_html(false, ['agent_id' => $agent->id]); ?> class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus-square"></i><span><?php _e('Create Appointment', 'latepoint'); ?></span></a>
			</div><?php
		}
	}else{
		echo '<div class="os-pick-calendar-section">';
		echo '<div>'.__('Pick a calendar that bookings will be synced to:', 'latepoint-google-calendar').'</div>';
		echo OsFormHelper::select_field('selected_google_calendar_id', false, array_merge([['value' => '', 'label' => __('Select Calendar', 'latepoint-google-calendar')]], OsGoogleCalendarHelper::get_list_of_calendars_for_select($agent->id)), OsGoogleCalendarHelper::get_selected_calendar_id_for_push($agent->id), ['class' => 'agent_google_calendar_selector', 'data-agent-id' => $agent->id, 'data-route' => OsRouterHelper::build_route_name('google_calendar', 'enable_calendar_for_push')]);
		echo '</div>';
	}
}else{
	echo '<div class="latepoint-message latepoint-message-error">'.__('This agent has not authorized access to their Google Calendar yet. Go back to agent profile and authorize google calendar sync.', 'latepoint-google-calendar').'</div>';
}