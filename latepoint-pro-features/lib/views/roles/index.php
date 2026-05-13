<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $default_user_roles array */
/* @var $custom_user_roles array */
?>
<div class="os-section-header"><h3><?php _e('Default Roles', 'latepoint-pro-features'); ?></h3></div>
<div class="os-default-roles-w os-mb-4 os-form-blocks-w">
<?php
if($default_user_roles){
	foreach($default_user_roles as $role){
		include('_form.php');
	}
}
?>
</div>
<div class="os-section-header"><h3><?php _e('Custom Roles', 'latepoint-pro-features'); ?></h3></div>
<div class="os-custom-roles-w os-form-blocks-w">
<?php
if($custom_user_roles){
	foreach($custom_user_roles as $role){
		include('_form.php');
	}
}
?>
</div>
<div class="os-add-box"
     data-os-after-call="latepointRoleManagerAddonAdmin.init_new_role_form"
     data-os-action="<?php echo OsRouterHelper::build_route_name('roles', 'new_form'); ?>"
     data-os-output-target-do="append"
     data-os-output-target=".os-custom-roles-w">
	<div class="add-box-graphic-w">
		<div class="add-box-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
	</div>
	<div class="add-box-label"><?php _e('Create Custom Role', 'latepoint-pro-features'); ?></div>
</div>