<div class="os-form-w">
	<?php $action = ($service_category->is_new_record()) ? 'create' : 'update'; ?>
  <form data-os-action="<?php echo OsRouterHelper::build_route_name('service_categories', $action); ?>" action="" <?php if($service_category->is_new_record()) echo ' data-os-success-action="reload"'; ?>>
  	<div class="os-row">
  		<div class="os-col-lg-6">
				<?php echo OsFormHelper::text_field('service_category[name]', __('Category Name', 'latepoint-pro-features'), $service_category->name); ?>
      </div>
      <div class="os-col-lg-6">
        <?php echo OsFormHelper::textarea_field('service_category[short_description]', __('Short Description', 'latepoint-pro-features'), $service_category->short_description, ['rows' => 1]); ?>
  		</div>
  		<div class="os-col-lg-12">
  			<div class="os-form-group">
	        <?php echo OsFormHelper::media_uploader_field('service_category[selection_image_id]', 0, __('Category Image', 'latepoint-pro-features'), __('Remove Image', 'latepoint-pro-features'), $service_category->selection_image_id); ?>
	      </div>
  		</div>
  	</div>
    <?php if(!$service_category->is_new_record()) echo OsFormHelper::hidden_field('service_category[id]', $service_category->id); ?>
    <div class="os-form-buttons os-flex">
      <?php echo OsFormHelper::button('submit', __('Save Category', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']);  ?>
      <?php
      if($service_category->is_new_record()){
	      echo '<a href="#" class="latepoint-btn latepoint-btn-secondary add-service-category-trigger">'. __('Cancel', 'latepoint-pro-features').'</a>';
      }else{
	      echo '<a href="#" class="latepoint-btn latepoint-btn-danger" style="margin-left: auto;" 
				        data-os-prompt="'.__('Are you sure you want to remove this category?', 'latepoint-pro-features').'" 
				        data-os-params="'. OsUtilHelper::build_os_params(['id' => $service_category->id], 'destroy_service_category_'.$service_category->id). '" 
				        data-os-success-action="reload" 
				        data-os-action="'.OsRouterHelper::build_route_name('service_categories', 'destroy').'">'.__('Delete Category', 'latepoint-pro-features').'</a>';
      } ?>
    </div>
	  <?php wp_nonce_field($service_category->is_new_record() ? 'new_service_category' : 'edit_service_category_'.$service_category->id); ?>
	</form>
</div>