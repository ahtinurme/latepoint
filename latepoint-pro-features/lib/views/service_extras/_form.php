<div class="os-form-w">
  <form action="" 
        data-os-success-action="redirect" 
        data-os-redirect-to="<?php echo OsRouterHelper::build_link(['service_extras', 'index']); ?>" 
        data-os-action="<?php echo $service_extra->is_new_record() ? OsRouterHelper::build_route_name('service_extras', 'create') : OsRouterHelper::build_route_name('service_extras', 'update'); ?>">

    <div class="os-row">
      <div class="os-col-lg-6">
        <div class="white-box">
          <div class="white-box-header">
            <div class="os-form-sub-header">
              <h3><?php _e('Basic Information', 'latepoint-pro-features'); ?></h3>
              <?php if(!$service_extra->is_new_record()){ ?>
                <div class="os-form-sub-header-actions"><?php echo __('Service Extra ID:', 'latepoint-pro-features').$service_extra->id; ?></div>
              <?php } ?>  
            </div>
          </div>
          <div class="white-box-content">
            <?php echo OsFormHelper::text_field('service_extra[name]', __('Service Extra Name', 'latepoint-pro-features'), $service_extra->name); ?>
            <div class="os-row">
              <div class="os-col-lg-4">
                <?php echo OsFormHelper::text_field('service_extra[duration]', __('Duration (minutes)', 'latepoint-pro-features'), $service_extra->duration); ?>
              </div>
              <div class="os-col-lg-4">
                <?php echo OsFormHelper::money_field('service_extra[charge_amount]', __('Charge Amount', 'latepoint-pro-features'), $service_extra->charge_amount); ?>
              </div>
              <div class="os-col-lg-4">
                <?php echo OsFormHelper::text_field('service_extra[maximum_quantity]', __('Maximum Quantity', 'latepoint-pro-features'), $service_extra->maximum_quantity); ?>
              </div>
            </div>
            <?php echo OsFormHelper::select_field('service_extra[status]', __('Status', 'latepoint-pro-features'), array(LATEPOINT_SERVICE_EXTRA_STATUS_ACTIVE => __('Active', 'latepoint-pro-features'), LATEPOINT_SERVICE_EXTRA_STATUS_DISABLED => __('Disabled', 'latepoint-pro-features')), $service_extra->status); ?>
            <?php echo OsFormHelper::textarea_field('service_extra[short_description]', __('Short Description', 'latepoint-pro-features'), $service_extra->short_description, array('rows' => 3)); ?>
            <?php do_action('latepoint_after_service_extra_form', $service_extra); ?>
          </div>
        </div>
      </div>
      <div class="os-col-lg-6">

        <div class="white-box">
          <div class="white-box-header">
            <div class="os-form-sub-header"><h3><?php _e('Media', 'latepoint-pro-features'); ?></h3></div>
          </div>
          <div class="white-box-content">
            <div class="label-with-description">
              <h3><?php _e('Selection Image', 'latepoint-pro-features'); ?></h3>
              <div class="label-desc"><?php _e('This image will be used as a background image of the service extra tile on booking form', 'latepoint-pro-features'); ?></div>
            </div>
            <?php echo OsFormHelper::media_uploader_field('service_extra[selection_image_id]', 0, __('Service Extra Image', 'latepoint-pro-features'), __('Remove Image', 'latepoint-pro-features'), $service_extra->selection_image_id); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="white-box">
      <div class="white-box-header">
        <div class="os-form-sub-header">
          <h3><?php _e('Connected Services', 'latepoint-pro-features'); ?></h3>
          <div class="os-form-sub-header-actions">
            <?php echo OsFormHelper::checkbox_field('select_all_services', __('Select All', 'latepoint-pro-features'), 'off', false, ['class' => 'os-select-all-toggler']); ?>
          </div>
        </div>
      </div>
      <div class="white-box-content">

        <div class="os-complex-connections-selector">
        <?php if($services){
          foreach($services as $service){
            $is_active_service = $service_extra->is_new_record() ? true : $service_extra->has_service($service->id);
            $is_active_service_value = $is_active_service ? 'yes' : 'no';
            $active_class = $is_active_service ? 'active' : '';
            ?>

            <div class="connection <?php echo $active_class; ?>">
              <div class="connection-i selector-trigger">
                <div class="connection-avatar"><img src="<?php echo $service->get_selection_image_url(); ?>"/></div>
                <h3 class="connection-name"><?php echo $service->name; ?></h3>
                <?php echo OsFormHelper::hidden_field('service_extra[services][service_'.$service->id.'][connected]', $is_active_service_value, array('class' => 'connection-child-is-connected'));?>
              </div>
            </div><?php
          }
        }
        ?>
        </div>
      </div>
    </div>


    <div class="os-form-buttons os-flex">
    <?php 
      if($service_extra->is_new_record()){
        echo OsFormHelper::button('submit', __('Add Service Extra', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']);
      }else{
        echo OsFormHelper::hidden_field('service_extra[id]', $service_extra->id);
        echo '<a href="#" class="latepoint-btn latepoint-btn-danger remove-service-btn" style="margin-left: auto;" 
                data-os-prompt="'.__('Are you sure you want to remove this service extra? You can also change status to disabled if you want to temprorary disable it instead.', 'latepoint-pro-features').'" 
                data-os-redirect-to="'.OsRouterHelper::build_link(['service_extras', 'index']).'" 
                data-os-params="'. OsUtilHelper::build_os_params(['id' => $service_extra->id]). '" 
                data-os-success-action="redirect" 
                data-os-action="'.OsRouterHelper::build_route_name('service_extras', 'destroy').'">'.__('Delete Service Extra', 'latepoint-pro-features').'</a>';
        echo OsFormHelper::button('submit', __('Save Changes', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']);
      }

      ?>
    </div>
  </form>
</div>