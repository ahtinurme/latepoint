<div class="os-form-w">
	<?php $action = ($location_category->is_new_record()) ? 'create' : 'update'; ?>
  <form data-os-action="<?php echo OsRouterHelper::build_route_name('location_categories', $action); ?>" action="" <?php if($location_category->is_new_record()) echo ' data-os-success-action="reload"'; ?>>
  	<div class="os-row">
  		<div class="os-col-lg-6">
				<?php echo OsFormHelper::text_field('location_category[name]', __('Category Name', 'latepoint-pro-features'), $location_category->name); ?>
      </div>
      <div class="os-col-lg-6">
        <?php echo OsFormHelper::textarea_field('location_category[short_description]', __('Short Description', 'latepoint-pro-features'), $location_category->short_description, ['rows' => 1]); ?>
  		</div>
  		<div class="os-col-lg-12">
  			<div class="os-form-group">
	        <?php echo OsFormHelper::media_uploader_field('location_category[selection_image_id]', 0, __('Category Image', 'latepoint-pro-features'), __('Remove Image', 'latepoint-pro-features'), $location_category->selection_image_id); ?>
	      </div>
  		</div>
  	</div>
    <?php if(!$location_category->is_new_record()) echo OsFormHelper::hidden_field('location_category[id]', $location_category->id); ?>
    <div class="os-form-buttons os-flex">
      <?php echo OsFormHelper::button('submit', __('Save Category', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']);  ?>
      <?php
      if($location_category->is_new_record()){
	      echo '<a href="#" class="latepoint-btn latepoint-btn-secondary add-location-category-trigger">'. __('Cancel', 'latepoint-pro-features').'</a>';
      }else{
	      echo '<a href="#" class="latepoint-btn latepoint-btn-danger" style="margin-left: auto;" 
				        data-os-prompt="'.__('Are you sure you want to remove this category?', 'latepoint-pro-features').'" 
				        data-os-params="'. OsUtilHelper::build_os_params(['id' => $location_category->id], 'destroy_location_category_'.$location_category->id). '" 
				        data-os-success-action="reload" 
				        data-os-action="'.OsRouterHelper::build_route_name('location_categories', 'destroy').'">'.__('Delete Category', 'latepoint-pro-features').'</a>';
      } ?>
    </div>
	  <?php wp_nonce_field($location_category->is_new_record() ? 'new_location_category' : 'edit_location_category_'.$location_category->id); ?>
	</form>
</div>