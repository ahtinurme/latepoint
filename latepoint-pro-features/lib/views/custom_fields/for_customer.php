<div class="os-section-header"><h3><?php _e('Default Fields', 'latepoint-pro-features'); ?></h3></div>
<?php OsSettingsHelper::generate_default_form_fields($default_fields); ?>
<div class="os-section-header"><h3><?php _e('Custom Fields', 'latepoint-pro-features'); ?></h3></div>
<div class="os-custom-fields-w os-draggable-form-blocks os-form-blocks-w" data-order-update-route="<?php echo OsRouterHelper::build_route_name('custom_fields', 'update_order'); ?>" data-order-update-nonce="<?php echo esc_attr(wp_create_nonce('latepoint_update_custom_field_order')); ?>" data-fields-for="customer">
	<?php foreach($custom_fields_for_customers as $custom_field){ ?>
		<?php include('_custom_field_form.php'); ?>
	<?php } ?>
</div>

<div class="os-add-box add-custom-field-box add-custom-field-trigger" data-os-params="fields_for=<?php echo $fields_for; ?>" data-os-action="<?php echo OsRouterHelper::build_route_name('custom_fields', 'new_form'); ?>" data-os-output-target-do="append" data-os-output-target=".os-custom-fields-w">
	<div class="add-box-graphic-w">
		<div class="add-box-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
	</div>
	<div class="add-box-label"><?php _e('Add Custom Field', 'latepoint-pro-features'); ?></div>
</div>