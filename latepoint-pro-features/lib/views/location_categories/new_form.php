<div class="os-form-w">
  <div class="os-form-header">
    <h3><?php _e('Add Location Category', 'latepoint-pro-features'); ?></h3>
  </div>
  <form action="" data-os-after-call="latepoint_reload_select_location_categories" id="newLocationCategoryForm" data-os-action="<?php echo OsRouterHelper::build_route_name('location_categories', 'create'); ?>">
    <div class="os-row">
      <div class="os-col-12">
        <?php echo OsFormHelper::text_field('location_category[name]', __('Category Name', 'latepoint-pro-features')); ?>
        <?php echo OsFormHelper::textarea_field('location_category[short_description]', __('Quick Description', 'latepoint-pro-features')); ?>
      </div>
    </div>

    <div class="os-form-buttons">
      <?php echo OsFormHelper::button('submit', __('Save Location Category', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']); ?>
    </div>
  </form>
</div>