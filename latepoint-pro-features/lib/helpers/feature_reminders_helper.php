<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureRemindersHelper {
	public static function add_event_time_offset_settings_html( $html, \LatePoint\Misc\ProcessEvent $event ) {
		$html = '<div class="time-offset-actions">
		            <div class="time-offset-label">' . __( 'Actions will be executed:', 'latepoint-pro-features' ) . '</div>
		            ' . OsFormHelper::number_field( 'process[event][time_offset][value]', '', $event->time_offset ? $event->time_offset['value'] : 1, 1, null, [ 'theme' => 'bordered' ] ) . '
		            ' . OsFormHelper::select_field(
						'process[event][time_offset][unit]',
						'',
						[
							'minute' => __( 'minutes', 'latepoint-pro-features' ),
							'hour'   => __( 'hours', 'latepoint-pro-features' ),
							'day'    => __( 'days', 'latepoint-pro-features' ),
						],
						$event->time_offset ? $event->time_offset['unit'] : 'day' 
					) . '
		            ' . OsFormHelper::select_field(
						'process[event][time_offset][before_after]',
						'',
						[
							'before' => __( 'before the event', 'latepoint-pro-features' ),
							'after'  => __( 'after the event', 'latepoint-pro-features' ),
						],
						$event->time_offset ? $event->time_offset['before_after'] : 'after' 
					) . '
							</div>';
		return $html;
	}

	public static function process_scheduled_jobs() {
		OsProcessJobsHelper::process_scheduled_jobs();
	}
}
