<form data-os-form-block-id="<?php echo $custom_field['id']; ?>"
			data-os-action="<?php echo OsRouterHelper::build_route_name('custom_fields', 'save'); ?>" 
			class="os-form-block os-form-block-type-<?php echo $custom_field['type']; ?> <?php if(empty($custom_field['label'])) echo 'os-is-editing'; ?>">
	<div class="os-form-block-i">
		<div class="os-form-block-header <?php if($custom_field['required'] == 'on') echo 'os-form-block-required'; ?>">
			<div class="os-form-block-drag"></div>
			<div class="os-form-block-name"><?php echo strip_tags(!empty($custom_field['label']) ? $custom_field['label'] : __('New Field', 'latepoint-pro-features')); ?></div>
			<div class="os-form-block-type"><?php echo $custom_field['type']; ?></div>
			<div class="os-form-block-edit-btn"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
		</div>
		<div class="os-form-block-params os-form-w">
      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Field Label', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
          <?php echo OsFormHelper::text_field('custom_fields['.$custom_field['id'].'][label]', false, $custom_field['label'], ['theme' => 'bordered', 'placeholder' => __('Enter Field Label', 'latepoint-pro-features'), 'class' => 'os-form-block-name-input']); ?>
        </div>
      </div>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Placeholder', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
					<?php echo OsFormHelper::text_field('custom_fields['.$custom_field['id'].'][placeholder]', false, $custom_field['placeholder'], ['theme' => 'bordered', 'placeholder' => __('Enter Field Placeholder', 'latepoint-pro-features')]); ?>
        </div>
      </div>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Field Type', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
				  <?php echo OsFormHelper::select_field('custom_fields['.$custom_field['id'].'][type]', 
				  																			false, 
				  																			$custom_field_types,
																								$custom_field['type'], 
																								['class' => 'os-form-block-type-select']); ?>
					<div class="custom-fields-google-places-api-status" <?php if($custom_field['type'] != 'google_address_autocomplete') echo 'style="display: none;"'; ?>">
						<?php if(empty(OsSettingsHelper::get_settings_value('google_places_api_key'))) echo '<div class="latepoint-message latepoint-message-warning os-mt-1">'.__('To use address field, enter a Google API key in general settings.', 'latepoint-pro-features').'</div>'; ?>
					</div>
        </div>
      </div>

      <div class="sub-section-row custom-fields-select-values" <?php if(!in_array($custom_field['type'],['select', 'multiselect'])) echo 'style="display: none;"'; ?>>
        <div class="sub-section-label">
          <h3><?php _e('Options List', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
					<?php echo OsFormHelper::textarea_field('custom_fields['.$custom_field['id'].'][options]', false, $custom_field['options'], array('theme' => 'bordered', 'rows' => 5, 'placeholder' => __('Choices for Select. Enter each choice on a new line.', 'latepoint-pro-features'))); ?>
        </div>
      </div>

        <div class="sub-section-row custom-fields-default-value-row" <?php if(!in_array($custom_field['type'], OsCustomFieldsHelper::get_custom_field_types_with_default_value())) echo 'style="display: none;"'; ?>>
            <div class="sub-section-label">
                <h3><?php esc_html_e('Default Value', 'latepoint-pro-features') ?></h3>
            </div>
            <div class="sub-section-content">
				<?php echo OsCustomFieldsHelper::get_custom_field_default_value_html($custom_field['type'], 'custom_fields['.$custom_field['id'].'][value]',  $custom_field['value'] ?? ''); ?>
            </div>
        </div>

      <?php if($fields_for == 'booking'){ ?>
        <div class="sub-section-row">
          <div class="sub-section-label">
            <h3><?php _e('Conditional', 'latepoint-pro-features') ?></h3>
          </div>
          <div class="sub-section-content">
            <?php echo OsFormHelper::toggler_field('custom_fields['.$custom_field['id'].'][conditional]', __('Show this field only when conditions are met', 'latepoint-pro-features'), ($custom_field['conditional'] == 'on'), "cf-conditions-for-". esc_attr($custom_field['id'])); ?>
            <div class="cf-conditions" id="cf-conditions-for-<?php echo esc_attr($custom_field['id']); ?>" style="<?php echo ($custom_field['conditional'] == 'on') ? 'display:  block;' : ''; ?>">
              <h3><?php _e('Show this field if:', 'latepoint-pro-features'); ?></h3>
              <?php
                if($custom_field['conditions']){
                  foreach($custom_field['conditions'] as $condition_id => $condition){
                    echo OsCustomFieldsHelper::generate_condition_form($custom_field['id'], $condition_id, $condition);
                  }
                }else{
                  echo OsCustomFieldsHelper::generate_condition_form($custom_field['id']);
                }
              ?>
            </div>
          </div>
        </div>
      <?php } ?>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Field Width', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
					<?php echo OsFormHelper::select_field('custom_fields['.$custom_field['id'].'][width]', false, array( 'os-col-12' => __('Full Width', 'latepoint-pro-features'),
													  																																																						'os-col-6' => __('Half Width', 'latepoint-pro-features')), $custom_field['width']); ?>
        </div>
      </div>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Field Visibility', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
					<?php echo OsFormHelper::select_field('custom_fields['.$custom_field['id'].'][visibility]', false, array( 'public' => __('Visible to Everyone', 'latepoint-pro-features'),
													  																																																																		'admin_agent' => __('Admin and Agents Only', 'latepoint-pro-features'),
													  																																																																		'hidden' => __('Temporary Hidden', 'latepoint-pro-features')), $custom_field['visibility']); ?>
        </div>
      </div>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Required', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
					<?php echo OsFormHelper::toggler_field('custom_fields['.$custom_field['id'].'][required]', __('Make this field required', 'latepoint-pro-features'), ($custom_field['required'] == 'on')); ?>
        </div>
      </div>

      <div class="sub-section-row">
        <div class="sub-section-label">
          <h3><?php _e('Hide from Summary', 'latepoint-pro-features') ?></h3>
        </div>
        <div class="sub-section-content">
					<?php echo OsFormHelper::toggler_field('custom_fields['.$custom_field['id'].'][hide_on_summary]', __('Hide from Summary Panel and Confirmation Step', 'latepoint-pro-features'), (isset($custom_field['hide_on_summary']) && $custom_field['hide_on_summary'] == 'on')); ?>
        </div>
      </div>

      <?php if($fields_for == 'customer'){ ?>
        <div class="sub-section-row">
          <div class="sub-section-label">
            <h3><?php _e('Show in Customers Table', 'latepoint-pro-features') ?></h3>
          </div>
          <div class="sub-section-content">
					  <?php echo OsFormHelper::toggler_field('custom_fields['.$custom_field['id'].'][show_in_customers_table]', __('Display this field as a column in the admin Customers table', 'latepoint-pro-features'), (isset($custom_field['show_in_customers_table']) && $custom_field['show_in_customers_table'] == 'on')); ?>
          </div>
        </div>
      <?php } ?>

      <div class="os-form-block-buttons">
				<a href="#" class="latepoint-btn latepoint-btn-danger pull-left os-remove-custom-field" data-os-prompt="<?php _e('Are you sure you want to delete this field?', 'latepoint-pro-features'); ?>"  data-os-after-call="latepoint_custom_field_removed" data-os-pass-this="yes" data-os-action="<?php echo OsRouterHelper::build_route_name('custom_fields', 'destroy'); ?>" data-os-params="<?php echo OsUtilHelper::build_os_params(['id' => $custom_field['id'], 'fields_for' => $fields_for], 'latepoint_destroy_custom_field_' . $custom_field['id']) ?>"><?php _e('Delete', 'latepoint-pro-features'); ?></a>
			  <button type="submit" class="os-form-block-save-btn latepoint-btn latepoint-btn-primary"><span><?php _e('Save Field', 'latepoint-pro-features'); ?></span></button>
		  </div>
		</div>
	</div>
	<?php echo OsFormHelper::hidden_field('custom_fields['.$custom_field['id'].'][id]', $custom_field['id'], ['class' => 'os-form-block-id']); ?>
	<?php echo OsFormHelper::hidden_field('fields_for', $fields_for); ?>
	<?php wp_nonce_field('latepoint_save_custom_fields', '_wpnonce', false); ?>
	<a href="#" data-os-prompt="<?php _e('Are you sure you want to delete this field?', 'latepoint-pro-features'); ?>"  data-os-after-call="latepoint_custom_field_removed" data-os-pass-this="yes" data-os-action="<?php echo OsRouterHelper::build_route_name('custom_fields', 'destroy'); ?>" data-os-params="<?php echo OsUtilHelper::build_os_params(['id' => $custom_field['id'], 'fields_for' => $fields_for], 'latepoint_destroy_custom_field_' . $custom_field['id']) ?>" class="os-remove-form-block"><i class="latepoint-icon latepoint-icon-cross"></i></a>
</form>