<div class="os-mini-agent-profile-w">
	<a href="#" class="os-floating-popup-close"><i class="latepoint-icon latepoint-icon-x"></i></a>
	<div class="os-ma-agent-info-w">
		<div class="os-ma-avatar" style="background-image: url(<?php echo $agent->get_avatar_url(); ?>)"></div>
		<div class="os-ma-agent-info">
			<div class="ma-agent-name"><?php echo esc_html($agent->full_name); ?><a target="_blank" href="<?php echo OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $agent->id]); ?>"><i class="latepoint-icon latepoint-icon-external-link"></i></a></div>
			<?php if (!empty($agent->email)) { ?><div class="ma-agent-info-list-item"><span><?php _e('email:', 'latepoint-pro-features'); ?></span><strong><?php echo $agent->email; ?></strong></div><?php } ?>
			<?php if (!empty($agent->phone)) { ?><div class="ma-agent-info-list-item"><span><?php _e('phone:', 'latepoint-pro-features'); ?></span><strong><?php echo $agent->phone; ?></strong></div><?php } ?>
		</div>
	</div>
	<?php OsAgentHelper::generate_day_schedule_info($filter); ?>
	<div class="agent-timeline-w">
		<div class="agent-timeline">
			<?php 
			$work_start_end_time = OsWorkPeriodsHelper::get_work_start_end_time_for_date($filter);
			$work_start_minutes = $work_start_end_time[0];
			$work_end_minutes = $work_start_end_time[1];
			$work_total_minutes = $work_end_minutes - $work_start_minutes;
			$timeblock_interval = OsSettingsHelper::get_default_timeblock_interval();
			$bookings = OsBookingHelper::get_bookings($filter, true);
			if($bookings && $work_total_minutes){
				$overlaps_count = 1;
				$total_attendees_in_group = 0;
				$total_bookings_in_group = 0;
				$total_bookings = count($bookings);
				foreach($bookings as $index => $booking){
					$next_booking = (($index + 1) < $total_bookings - 1) ? $bookings[$index + 1] : false;
					if(OsBookingHelper::check_if_group_bookings($booking, $next_booking)){
						// skip this output because multiple bookings in the same slot because next booking has the same start and end time 
						$total_attendees_in_group+= $booking->total_attendees;
						$total_bookings_in_group++;
						continue;
					}else{

						$width = ($booking->end_time - $booking->start_time) / $work_total_minutes * 100;
						$left = ($booking->start_time - $work_start_minutes) / $work_total_minutes * 100;
						if($width <= 0 || $left >= 100 || (($left + $width) <= 0)) continue;
						if($left < 0){
							$width = $width + $left;
							$left = 0;
						}
						if(($left + $width) > 100) $width = 100 - $left;
						$max_capacity = OsServiceHelper::get_max_capacity($booking->service);
						if($max_capacity > 1){
						  $action_html = OsBookingHelper::group_booking_btn_html($booking->id);
						}else{
							$action_html = OsBookingHelper::quick_booking_btn_html($booking->id);
						}

						$custom_height = (isset($overlaps_count) && $overlaps_count > 1) ? 'height:'.(26 / $overlaps_count).'px;' : '';

						echo '<div class="booking-block" '.$action_html.' style="background-color: '.$booking->service->bg_color.'; left: '.$left.'%; width: '.$width.'%;'.$custom_height.'"></div>';

						// time overlaps
						$overlaps_count = ($next_booking && ($next_booking->start_time < $booking->end_time)) ? $overlaps_count + 1 : 1;
						// reset
						$total_attendees_in_group = 0;
					}
					
				}
			}
			do_action('latepoint_appointments_timeline', OsWpDateTime::os_createFromFormat('Y-m-d', $filter->date_from), ['agent_id' => $agent->id,'work_start_minutes' => $work_start_minutes, 'work_end_minutes' => $work_end_minutes, 'work_total_minutes' => $work_total_minutes]);
			?>
		</div>
	</div>
</div>