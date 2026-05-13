<div class="os-location os-resource-grid-item os-location-status-<?php echo $location->status; ?>">
      <a href="#" class="instant-booking-settings-open" data-os-output-target="full-panel"
							data-os-after-call="latepoint_init_instant_booking_settings"
							data-os-action="<?php echo OsRouterHelper::build_route_name('settings', 'generate_instant_booking_page'); ?>"
							data-os-params="<?php echo OsUtilHelper::build_os_params(['location_id' => $location->id]); ?>"><i class="latepoint-icon latepoint-icon-zap"></i></a>
  <div class="os-location-body">
    <div class="os-location-address">
      <?php if($location->full_address){ ?>
        <iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="<?php echo $location->get_google_maps_link(true); ?>"></iframe>
      <?php }else{ ?>
          <div class="os-location-address-placeholder"><i class="latepoint-icon latepoint-icon-map"></i></div>
      <?php } ?>
    </div>

    <div class="os-location-header">
      <h3 class="location-name"><?php echo $location->name; ?></h3>
      <div class="os-location-info"><?php echo $location->full_address; ?></div>
    </div>
    <div class="os-location-agents">
      <div class="label"><?php _e('Agents:', 'latepoint-pro-features'); ?></div>
      <?php if($location->connected_agents){ ?>
        <div class="agents-avatars">
        <?php foreach($location->connected_agents as $agent){ ?>
          <div class="agent-avatar" style="background-image: url(<?php echo esc_url($agent->avatar_url); ?>)"></div>
        <?php } ?>
        </div>
      <?php }else{
        echo '<a href="'.OsRouterHelper::build_link(OsRouterHelper::build_route_name('locations', 'edit_form'), ['id' => $location->id] ).'" class="no-agents-for-location">'.__('No Agents Assigned', 'latepoint-pro-features').'</a>';
      } ?>
    </div>
  </div>
  <div class="os-location-foot">
    <a href="<?php echo esc_url(OsRouterHelper::build_link(OsRouterHelper::build_route_name('locations', 'edit_form'), array('id' => $location->id) )); ?>" class="latepoint-btn latepoint-btn-block latepoint-btn-secondary">
      <i class="latepoint-icon latepoint-icon-edit-3"></i>
      <span><?php esc_html_e('Edit Location', 'latepoint-pro-features'); ?></span>
    </a>
  </div>
</div>