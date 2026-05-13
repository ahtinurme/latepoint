<form data-os-form-block-id="<?php echo $tax->id; ?>"
			data-os-action="<?php echo OsRouterHelper::build_route_name('taxes', 'save'); ?>"
			class="os-form-block os-form-block-type-<?php echo $tax->type; ?> <?php if(empty($tax->name)) echo 'os-is-editing'; ?>">
	<div class="os-form-block-i">
		<div class="os-form-block-header">
			<div class="os-form-block-drag"></div>
			<div class="os-form-block-name"><?php echo !empty($tax->name) ? $tax->name : __('New Tax', 'latepoint-pro-features'); ?></div>
			<div class="os-form-block-type"><?php echo $tax->type; ?></div>
			<div class="os-form-block-edit-btn"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
		</div>
		<div class="os-form-block-params os-form-w">
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Tax Name', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
          <?php echo OsFormHelper::text_field('taxes['.$tax->id.'][name]', false, $tax->name, ['theme' => 'bordered', 'placeholder' => __('Enter Tax Name', 'latepoint-pro-features'), 'class' => 'os-form-block-name-input']); ?>
        </div>
      </div>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Tax Type', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
	        <div class="os-row">
		        <div class="os-col-4">
					  <?php echo OsFormHelper::select_field('taxes['.$tax->id.'][type]',
					                                        false,
					                                        [
																									  'percentage' => __('Percentage of the booking price', 'latepoint-pro-features'),
																									  'fixed' => __('Fixed amount', 'latepoint-pro-features')
																								  ],
																									$tax->type,
																									['class' => 'os-form-block-type-select tax-type-selector']); ?>
		        </div>
		        <div class="os-col-8">
			        <?php echo OsFormHelper::text_field('taxes['.$tax->id.'][value]', false, $tax->value, ['theme' => 'bordered', 'placeholder' => __('Enter Tax Value', 'latepoint-pro-features'), 'class' => 'os-form-block-value-input']); ?>
		        </div>
	        </div>
        </div>
      </div>

      <div class="os-form-block-buttons">
				<a href="#" class="latepoint-btn latepoint-btn-danger pull-left" data-os-prompt="<?php _e('Are you sure you want to delete this tax?', 'latepoint-pro-features'); ?>"
				   data-os-after-call="latepointTaxesAddon.latepoint_tax_removed"
				   data-os-pass-this="yes"
				   data-os-action="<?php echo OsRouterHelper::build_route_name('taxes', 'destroy'); ?>"
				   data-os-params="<?php echo OsUtilHelper::build_os_params(['id' => $tax->id]) ?>"><?php _e('Delete', 'latepoint-pro-features'); ?></a>
			  <button type="submit" class="os-form-block-save-btn latepoint-btn latepoint-btn-primary"><span><?php _e('Save Tax', 'latepoint-pro-features'); ?></span></button>
		  </div>
		</div>
	</div>
	<?php echo OsFormHelper::hidden_field('taxes['.$tax->id.'][id]', $tax->id, ['class' => 'os-form-block-id']); ?>
	<a href="#" data-os-prompt="<?php _e('Are you sure you want to delete this tax?', 'latepoint-pro-features'); ?>"
	   data-os-after-call="latepointTaxesAddon.latepoint_tax_removed"
	   data-os-pass-this="yes"
	   data-os-action="<?php echo OsRouterHelper::build_route_name('taxes', 'destroy'); ?>"
	   data-os-params="<?php echo OsUtilHelper::build_os_params(['id' => $tax->id]) ?>" class="os-remove-form-block"><i class="latepoint-icon latepoint-icon-cross"></i></a>
</form>