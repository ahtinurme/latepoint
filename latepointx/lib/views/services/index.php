<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php if($uncategorized_services){ ?>
    <div class="os-item-category-w">
      <div class="os-form-sub-header sub-level"><h3><?php esc_html_e('Uncategorized', 'latepoint'); ?></h3></div>
      <div class="os-services-list os-resources-grid">
        <?php foreach ($uncategorized_services as $service): ?>
          <?php include('_service_index_item.php'); ?>
        <?php endforeach; ?>


		<?php if(OsRolesHelper::can_user('service__create')){ ?>
            <?php echo OsUtilHelper::add_resource_link_html(__('New Service', 'latepoint-pro-features'), OsRouterHelper::build_link(OsRouterHelper::build_route_name('services', 'new_form') )); ?>
		<?php } ?>
      </div>
    </div>
<?php } ?>
<?php if($service_categories){ ?>
  <?php foreach ($service_categories as $service_category): ?>
    <div class="os-item-category-w">
      <div class="os-form-sub-header sub-level"><h3><?php echo esc_html($service_category->name); ?></h3></div>
      <div class="os-services-list os-resources-grid">
      <?php
      $active_services = $service_category->get_active_services(true, true);
        if($active_services){ ?>
          <?php foreach ($active_services as $service): ?>
            <?php include('_service_index_item.php'); ?>
          <?php endforeach; ?>
          <?php 
        } ?>
		<?php if(OsRolesHelper::can_user('service__create')){ ?>
            <?php echo OsUtilHelper::add_resource_link_html(__('New Service', 'latepoint-pro-features'), OsRouterHelper::build_link(OsRouterHelper::build_route_name('services', 'new_form'), ['service_category_id' => $service_category->id] )); ?>
		<?php } ?>
      </div>
    </div>
  <?php endforeach; ?>
 
<?php }else{ ?>
  <?php if(!$uncategorized_services){ ?>
  <div class="no-results-w">
    <div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
    <h2><?php esc_html_e('No Active Services Found', 'latepoint'); ?></h2>
    <?php if(OsRolesHelper::can_user('service__create')){ ?>
        <a href="<?php echo esc_url(OsRouterHelper::build_link(OsRouterHelper::build_route_name('services', 'new_form') )); ?>" class="latepoint-btn">
          <i class="latepoint-icon latepoint-icon-plus-square"></i>
          <span><?php esc_html_e('Add Service', 'latepoint'); ?></span>
        </a>
    <?php } ?>
  </div>
<?php } ?>
<?php } ?>


<?php if($disabled_services){
    echo '<div class="disabled-items-wrapper">';
    // translators: %d number of services
    echo '<div class="disabled-items-open-trigger"><span>'.esc_html(sprintf(_n('%d Disabled Service', '%d Disabled Services', count($disabled_services),'latepoint'), count($disabled_services))).'</span><i class="latepoint-icon latepoint-icon-chevron-down"></i></div>';
        echo '<div class="os-services-list os-resources-grid disabled-items-boxes">';
        foreach($disabled_services as $service){
            include('_service_index_item.php');
        }
        echo '</div>';
    echo '</div>';
}
