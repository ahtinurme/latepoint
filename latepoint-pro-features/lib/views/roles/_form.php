<?php
/* @var $role \LatePoint\Misc\Role */
/* @var $available_actions_grouped array */
?>
<?php $role_users = OsRolesHelper::get_users_for_role($role); ?>
<form action="" data-os-action="<?php echo OsRouterHelper::build_route_name('roles', 'save'); ?>" class="os-form-block os-user-type-form">
	<?php wp_nonce_field('latepoint_save_role'); ?>
	<div class="os-form-block-i">
  <div class="os-form-block-header">
    <div class="os-form-block-drag"></div>
    <div class="os-form-block-name update-from-name"><?php echo $role->name; ?></div>
    <div class="os-form-block-type"><?php echo sprintf(_n('%d user', '%d users', count($role_users), 'latepoint-pro-features'), count($role_users)); ?></div>
    <div class="os-form-block-edit-btn">
	    <i class="latepoint-icon latepoint-icon-edit-3"></i>
    </div>
  </div>
  <div class="os-form-block-params os-form-w">
	  <?php if($role->user_type == LATEPOINT_USER_TYPE_CUSTOM){ ?>
	    <div class="sub-section-row">
	      <div class="sub-section-label">
	        <h3><?php _e('Name', 'latepoint-pro-features') ?></h3>
	      </div>
	      <div class="sub-section-content">
		      <?php echo OsFormHelper::text_field('role[name]', false, $role->name, ['theme' => 'bordered']); ?>
	      </div>
	    </div>
	  <?php } ?>
    <?php echo OsFormHelper::hidden_field('role[wp_role]', $role->wp_role); ?>
    <?php echo OsFormHelper::hidden_field('role[user_type]', $role->user_type); ?>
    <div class="sub-section-row">
      <div class="sub-section-label">
        <h3><?php _e('Users', 'latepoint-pro-features') ?></h3>
      </div>
      <div class="sub-section-content">
	        <?php
	        if($role_users) {
						echo '<div class="role-users-wrapper">';
		        foreach ($role_users as $user) {
							/* @var $user \LatePoint\Misc\User */
							$data_html = 'data-os-output-target="side-panel" 
							data-os-after-call="latepointRoleManagerAddonAdmin.init_edit_wp_user_form"
							data-os-action="'.OsRouterHelper::build_route_name('roles', 'edit_wp_user').'" 
							data-os-params="'.OsUtilHelper::build_os_params(['id' => $user->wp_user->ID], 'latepoint_edit_wp_user').'"';
			        echo '<div class="role-user-wrapper" '.$data_html.'>';
								echo '<div class="ru-main-info">';
					        echo '<div class="ru-avatar" style="background-image: url(' . esc_attr(get_avatar_url($user->wp_user->user_email)) . ')"></div>';
					        echo '<div class="ru-wp-user-name"><div class="ru-name">' . $user->wp_user->display_name . '</div><div class="ru-email">' . $user->wp_user->user_email . '</div></div>';
				        echo '</div>';
								if(in_array($user->backend_user_type, [LATEPOINT_USER_TYPE_AGENT, LATEPOINT_USER_TYPE_CUSTOM])){
									if ($user->is_custom_capabilities() || $user->is_custom_allowed_records()) echo '<span class="ru-meta-permissions"><i class="latepoint-icon latepoint-icon-eye"></i> <span>'.__('Custom Settings', 'latepoint-pro-features').'</span></span>';
									if($user->backend_user_type == LATEPOINT_USER_TYPE_AGENT) {
										echo '<div class="ru-meta-info">';
											echo '<span class="ru-meta-connected-user">';
												echo ($user->agent) ? '<i class="latepoint-icon latepoint-icon-checkmark"></i> ' . __('Connected to:', 'latepoint-pro-features') . ' <strong>' . $user->agent->full_name . '</strong>' : '<i class="latepoint-icon latepoint-icon-slash"></i> ' . __('Not connected').' <a href="#">'.__('Connect to an agent', 'latepoint-pro-features').'</a>';
											echo '</span>';
										echo '</div>';
									}
								}
			        echo '</div>';
		        }
		        echo '</div>';
	        }else{
						echo '<div class="latepoint-message latepoint-message-subtle">'.sprintf(__('You have not assigned any WordPress users to this role. Create a new WP user or edit existing user and assign them a role called: %s', 'latepoint-pro-features'), '"<span class="update-from-name">'.$role->get_wp_role_display_name().'</span>"').'</div>';
	        }
	        ?>
      </div>
    </div>
    <div class="sub-section-row">
      <div class="sub-section-label">
        <h3><?php _e('Allowed Records', 'latepoint-pro-features') ?></h3>
      </div>
      <div class="sub-section-content">

	        <?php
	        switch($role->user_type){
						case LATEPOINT_USER_TYPE_ADMIN:
							echo '<div class="latepoint-message latepoint-message-subtle">'.sprintf(__('Users with "%s" role are allowed to perform all available actions on any agent, location and service records.', 'latepoint-pro-features'), $user->get_user_type_label()).'</div>';
							break;
						case LATEPOINT_USER_TYPE_AGENT:
							echo '<div class="latepoint-message latepoint-message-subtle">'.sprintf(__('Users with "%s" role can execute permitted actions only on records that belong to a LatePoint agent they are connected to.', 'latepoint-pro-features'), $user->get_user_type_label()).'</div>';
							break;
		        case LATEPOINT_USER_TYPE_CUSTOM:
							echo '<div class="latepoint-message latepoint-message-subtle">'.__('Once you assign users to this role, they will appear in "Users" section above, click on each user to set restrictions on which records each of them can access.', 'latepoint-pro-features').'</div>';
							break;
	        }
					?>
      </div>
    </div>
    <div class="sub-section-row">
      <div class="sub-section-label">
        <h3><?php _e('Permitted Actions', 'latepoint-pro-features') ?></h3>
      </div>
      <div class="sub-section-content">
        <div class="role-actions-grid">
        <?php
        if($role->user_type == LATEPOINT_USER_TYPE_ADMIN){
					echo '<div class="latepoint-message latepoint-message-subtle">'.sprintf(__('Users with "%s" role are permitted to execute all available actions in the system.', 'latepoint-pro-features'), $user->get_user_type_label()).'</div>';
        }else{
	        foreach($available_actions_grouped as $group => $actions){
						echo '<div class="role-actions-item">';
							echo '<div class="role-actions-group-name">';
								echo '<h3>'.OsRolesHelper::name_for_action($group).'</h3>';
								echo ('<div class="role-actions-group-description">'.OsRolesHelper::description_for_action($group).'</div>') ?? '';
							echo '</div>';
							foreach($actions as $action){
								echo '<div class="role-toggler-wrapper">'.OsFormHelper::toggler_field('role[capabilities]['.$group.'__'.$action.']', OsRolesHelper::name_for_action($action), $role->is_action_permitted($group.'__'.$action), false).'</div>';
							}
						echo '</div>';
	        }
        }
        ?>
        </div>
      </div>
    </div>
    <?php if($role->user_type != LATEPOINT_USER_TYPE_ADMIN){ ?>
	    <div class="os-form-block-buttons">
				<?php if($role->user_type == LATEPOINT_USER_TYPE_CUSTOM){ ?>
		    <a href="#" class="latepoint-btn latepoint-btn-danger pull-left os-remove-role"
		       data-os-prompt="<?php _e('Are you sure you want to delete this role?', 'latepoint-pro-features'); ?>"
		       data-os-after-call="latepointRoleManagerAddonAdmin.role_deleted"
		       data-os-pass-this="yes"
		       data-os-action="<?php echo OsRouterHelper::build_route_name('roles', 'destroy'); ?>"
		       data-os-params="<?php echo OsUtilHelper::build_os_params(['wp_role' => $role->wp_role], 'latepoint_destroy_role') ?>"><?php _e('Delete', 'latepoint-pro-features'); ?>
		    </a>
		    <?php } ?>
		    <button type="submit" class="os-form-block-save-btn latepoint-btn latepoint-btn-primary"><span><?php _e('Save Changes', 'latepoint-pro-features'); ?></span></button>
	    </div>
		<?php } ?>
  </div>
  </div>
	<?php if($role->user_type == LATEPOINT_USER_TYPE_CUSTOM){ ?>
	<a href="#"
	   data-os-prompt="<?php _e('Are you sure you want to delete this role?', 'latepoint-pro-features'); ?>"
	   data-os-after-call="latepointRoleManagerAddonAdmin.role_deleted"
	   data-os-pass-this="yes"
	   data-os-action="<?php echo OsRouterHelper::build_route_name('roles', 'destroy'); ?>"
	   data-os-params="<?php echo OsUtilHelper::build_os_params(['wp_role' => $role->wp_role], 'latepoint_destroy_role') ?>" class="os-remove-form-block"><i class="latepoint-icon latepoint-icon-cross"></i></a>
	<?php } ?>
</form>