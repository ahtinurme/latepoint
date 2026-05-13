<?php if($service_extras){ ?>
  <div class="os-services-list os-resources-grid">
		<?php foreach($service_extras as $service_extra){ ?>
			<div class="os-service os-resource-grid-item os-service-status-<?php echo $service_extra->status; ?>">
			  <div class="os-service-header">
			    <h3 class="service-name"><?php echo $service_extra->name; ?></h3>
			  </div>
			  <div class="os-service-body">
			    <div class="os-service-agents">
			      <div class="label"><?php _e('Services:', 'latepoint-pro-features'); ?></div>
			      <div class="selected-count <?php echo (count($service_extra->services) < $total_services) ? '' : 'selected-count-all'; ?>">
			      	<?php echo (count($service_extra->services) < $total_services) ? (count($service_extra->services).__(' of ', 'latepoint-pro-features').$total_services) : __('All Selected', 'latepoint-pro-features'); ?>
			      </div>
			    </div>
			    <div class="os-service-info">
			      <div class="service-info-row">
			        <div class="label"><?php _e('Duration:', 'latepoint-pro-features'); ?></div>
			        <div class="value"><strong><?php echo $service_extra->duration; ?></strong> <?php _e('min', 'latepoint-pro-features'); ?></div>
			      </div>
			      <div class="service-info-row">
			        <div class="label"><?php _e('Price:', 'latepoint-pro-features'); ?></div>
			        <div class="value"><strong><?php echo $service_extra->formatted_charge_amount; ?></strong></div>
			      </div>
			      <div class="service-info-row">
			        <div class="label"><?php _e('Max Qty:', 'latepoint-pro-features'); ?></div>
			        <div class="value"><strong><?php echo $service_extra->maximum_quantity; ?></strong></div>
			      </div>
			    </div>
			  </div>
			  <div class="os-service-foot">
			    <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('service_extras', 'edit_form'), array('id' => $service_extra->id) ) ?>" class="latepoint-btn latepoint-btn-block latepoint-btn-secondary">
			      <i class="latepoint-icon latepoint-icon-edit-3"></i>
			      <span><?php _e('Edit Extra', 'latepoint-pro-features'); ?></span>
			    </a>
			  </div>
			</div>
    <?php } ?>

	<?php if(OsRolesHelper::can_user('service_extra__create')){ ?>
		<?php echo OsUtilHelper::add_resource_link_html(__('New Service Extra', 'latepoint-pro-features'), OsRouterHelper::build_link(OsRouterHelper::build_route_name('service_extras', 'new_form') )); ?>
	<?php } ?>
  </div><?php
}else{ ?>
	<div class="no-results-w">
	  <div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
	  <h2><?php _e('No Service Extras Found', 'latepoint-pro-features'); ?></h2>
        <?php if(OsRolesHelper::can_user('service_extra__create')){ ?>
          <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('service_extras', 'new_form') ) ?>" class="latepoint-btn">
            <i class="latepoint-icon latepoint-icon-plus-square"></i>
            <span><?php _e('Add Service Extra', 'latepoint-pro-features'); ?></span>
          </a>
        <?php } ?>
	</div>
<?php } ?>