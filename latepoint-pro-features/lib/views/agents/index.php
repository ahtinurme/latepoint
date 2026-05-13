<?php
/**
 * @var $agents OsAgentModel[]
 * @var $disabled_agents OsAgentModel[]
 */
?>
<?php if($agents){ ?>
	<div class="index-agent-boxes os-resources-grid">
		<?php
			$today_date = new OsWpDateTime('today');
			foreach($agents as $agent){ ?>
				<div class="agent-box-w os-resource-grid-item agent-status-<?php echo esc_attr($agent->status); ?>">
                    <a href="#" class="instant-booking-settings-open" data-os-output-target="full-panel"
							data-os-after-call="latepoint_init_instant_booking_settings"
							data-os-action="<?php echo OsRouterHelper::build_route_name('settings', 'generate_instant_booking_page'); ?>"
							data-os-params="<?php echo OsUtilHelper::build_os_params(['agent_id' => $agent->id]); ?>"><i class="latepoint-icon latepoint-icon-zap"></i></a>
					<div class="agent-info-w">
						<div class="agent-avatar" style="background-image: url(<?php echo esc_url($agent->avatar_url); ?>)"></div>
						<div class="agent-info">
							<div class="agent-name"><?php echo esc_html($agent->full_name); ?></div>
							<div class="agent-phone"><?php echo esc_html($agent->phone); ?></div>
							<?php if($agent->wp_user_id) echo '<span class="agent-connection-icon"><img title="'.__('Connected to WordPress User', 'latepoint-pro-features').'" src="'.esc_attr(LatePoint::images_url().'wordpress-logo.png').'"/></span>'; ?>
							<?php do_action('latepoint_after_agent_info_on_index', $agent); ?>
						</div>
					</div>
					<div class="agent-schedule">
						<?php
						$agent_location_ids = OsConnectorHelper::get_connected_object_ids( 'location_id', [ 'agent_id' => $agent->id ] );
						$agent_location_ids = is_array( $agent_location_ids ) ? $agent_location_ids : [];
						$all_location_ids   = array_unique( array_merge( [ 0 ], $agent_location_ids ) );

						// Get work periods across all locations, grouped by weekday (already ordered by weight DESC)
						$weekday_periods = OsWorkPeriodsHelper::get_work_periods_grouped_by_weekday(
							new \LatePoint\Misc\Filter( [ 'agent_id' => $agent->id, 'location_id' => $all_location_ids ] )
						);

						for ( $i = 1; $i <= 7; $i++ ) {
							$is_day_off = true;
							if ( ! empty( $weekday_periods[ $i ] ) ) {
								// First period per location_id wins (highest weight due to ordering).
								$resolved = [];
								foreach ( $weekday_periods[ $i ] as $wp ) {
									if ( ! isset( $resolved[ $wp->location_id ] ) ) {
										$resolved[ $wp->location_id ] = ( $wp->start_time != $wp->end_time );
									}
								}
								$is_day_off = ! in_array( true, $resolved, true );
							}
							$status_class = $is_day_off ? 'is-off' : 'is-on';
							echo '<div class="schedule-day ' . esc_attr( $status_class ) . '">' . esc_html( OsBookingHelper::get_weekday_name_by_number( $i ) ) . '</div>';
						} ?>
					</div>
					<?php OsAgentHelper::generate_day_schedule_info(new \LatePoint\Misc\Filter(['agent_id' => $agent->id, 'date_from' => $today_date->format('Y-m-d')])); ?>

                    <div class="os-agent-foot">
                        <a href="<?php echo esc_url(OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'edit_form'), array('id' => $agent->id) )); ?>" class="latepoint-btn latepoint-btn-block latepoint-btn-secondary">
                            <i class="latepoint-icon latepoint-icon-edit-3"></i>
                            <span><?php esc_html_e('Edit Agent', 'latepoint-pro-features'); ?></span>
                        </a>
                    </div>
				</div>
				<?php
			}
		?>
		<?php if(OsRolesHelper::can_user('agent__create')){ ?>
            <?php echo OsUtilHelper::add_resource_link_html(__('New Agent', 'latepoint-pro-features'), OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'new_form') )); ?>
		<?php } ?>
	</div>
<?php }else{ ?>
  <div class="no-results-w">
    <div class="icon-w"><i class="latepoint-icon latepoint-icon-users"></i></div>
    <h2><?php _e('No Active Agents Found', 'latepoint-pro-features'); ?></h2>
    <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'new_form') ) ?>" class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus"></i><span><?php _e('New Agent', 'latepoint-pro-features'); ?></span></a>
  </div>
<?php } ?>
<?php if($disabled_agents){
    echo '<div class="disabled-items-wrapper">';
    echo '<div class="disabled-items-open-trigger"><span>'.sprintf(_n('%d Disabled Agent', '%d Disabled Agents', count($disabled_agents),'latepoint-pro-features'), count($disabled_agents)).'</span><i class="latepoint-icon latepoint-icon-chevron-down"></i></div>';
        echo '<div class="index-agent-boxes os-resources-grid disabled-items-boxes">';
        foreach($disabled_agents as $agent){ ?>
            <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'edit_form'), ['id' => $agent->id] ); ?>" class="agent-box-w os-resource-grid-item agent-status-<?php echo esc_attr($agent->status); ?>">
                <div class="agent-edit-icon"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
                <div class="agent-info-w">
                    <div class="agent-avatar" style="background-image: url(<?php echo esc_url($agent->avatar_url); ?>)"></div>
                    <div class="agent-info">
                        <div class="agent-name"><?php echo esc_html($agent->full_name); ?></div>
                        <div class="agent-phone"><?php echo esc_html($agent->phone); ?></div>
                        <?php if($agent->wp_user_id) echo '<span class="agent-connection-icon"><img title="'.__('Connected to WordPress User', 'latepoint-pro-features').'" src="'.esc_attr(LatePoint::images_url().'wordpress-logo.png').'"/></span>'; ?>
                        <?php do_action('latepoint_after_agent_info_on_index', $agent); ?>
                    </div>
                </div>
            </a>
            <?php
        }
        echo '</div>';
    echo '</div>';
}