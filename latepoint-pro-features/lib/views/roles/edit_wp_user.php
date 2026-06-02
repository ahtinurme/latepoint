<?php
/* @var $user \LatePoint\Misc\User */
/* @var $available_actions_grouped array */
?>
<div class="os-form-w quick-user-role-form-w">
<form action="" data-os-action="<?php echo OsRouterHelper::build_route_name('roles', 'update_wp_user'); ?>" class="role-user-edit-form">
	<?php wp_nonce_field('latepoint_update_wp_user'); ?>
	<div class="os-form-header">
		<h2><?php _e('Edit User', 'latepoint-pro-features'); ?></h2>
		<a href="#" class="latepoint-side-panel-close latepoint-side-panel-close-trigger"><i class="latepoint-icon latepoint-icon-x"></i></a>
	</div>
	<div class="os-form-content">
			<div class="ru-main-info">
				<div class="ru-avatar" style="background-image: url(<?php echo esc_attr(get_avatar_url($user->wp_user->user_email)); ?>)"></div>
				<div class="ru-wp-user-name">
					<div class="ru-name"><?php echo $user->wp_user->display_name; ?></div>
					<div class="ru-email"><?php echo $user->wp_user->user_email; ?></div>
					<div class="ru-user-meta">
						<div class="ru-role"><?php echo $user->get_user_type_label(); ?></div>
						<a href="<?php echo get_edit_user_link($user->wp_user->ID); ?>" target="__blank" class="ru-wp-user-link">
							<span><?php _e('WP User', 'latepoint-pro-features'); ?></span>
							<i class="latepoint-icon latepoint-icon-external-link"></i>
						</a>
					</div>
				</div>
			</div>
			<?php
			if($user->backend_user_type == LATEPOINT_USER_TYPE_AGENT){
				$list = OsAgentHelper::get_agents_list();
				$list[''] = __('Not Connected', 'latepoint-pro-features');
				echo OsFormHelper::select_field('assigned_agent', __('Connected to agent', 'latepoint-pro-features'), $list, $user->agent->id ?? '');
			}
			$can_set_custom_records = ($user->backend_user_type == LATEPOINT_USER_TYPE_CUSTOM);
			?>
			<div class="os-form-sub-header">
				<h3><?php _e('Allowed Records', 'latepoint-pro-features'); ?></h3>
			</div>
			<?php
				if($user->backend_user_type == LATEPOINT_USER_TYPE_ADMIN){
					echo '<div class="latepoint-message latepoint-message-subtle">'.__('This user has "Administrator" role and can access all records', 'latepoint-pro-features').'</div>';
				}elseif($user->backend_user_type == LATEPOINT_USER_TYPE_AGENT){
					echo '<div class="latepoint-message latepoint-message-subtle">'.__('This user has "Agent" role and can execute permitted actions only on records that belong to a LatePoint agent they are connected to.', 'latepoint-pro-features').'</div>';
				}
				if($can_set_custom_records){
					echo '<div class="custom-user-records-w">';
					foreach(OsRolesHelper::get_model_types_for_allowed_records() as $model_type){
			      echo OsFormHelper::select_field('allowed_records['.$model_type.']', OsRolesHelper::name_for_action($model_type), ['all' => __('All Connected', 'latepoint-pro-features'), 'custom' => __('Custom Selection', 'latepoint-pro-features')], ($user->are_all_records_allowed($model_type, true) ? 'all' : 'custom'), ['class' => 'allowed_models_selector']);
						echo '<div style="'.($user->are_all_records_allowed($model_type, true) ? 'display: none;' : '').'">';
						echo OsFormHelper::multi_select_field('allowed_records[custom]['.$model_type.']', false, OsFormHelper::model_options_for_multi_select($model_type), (($user->get_allowed_records($model_type, true) == LATEPOINT_ALL) ? [] : $user->get_allowed_records($model_type, true)));
						echo '</div>';
					}
					echo '</div>';
				}
			?>

			<div class="os-form-sub-header">
				<h3><?php _e('Permitted Actions', 'latepoint-pro-features'); ?></h3>
				<?php if($user->backend_user_type != LATEPOINT_USER_TYPE_ADMIN) echo '<div class="os-form-sub-header-actions">'.OsFormHelper::toggler_field('custom_capabilities', __('Custom', 'latepoint-pro-features'), $user->is_custom_capabilities(), false, 'small').'</div>'; ?>
			</div>
			<div class="custom-user-capabilities-w" style="<?php echo (!$user->is_custom_capabilities()) ? 'display: none;' : ''; ?>">
				<div class="role-actions-grid">
					<?php
			    foreach($available_actions_grouped as $group => $actions){
						echo '<div class="role-actions-item">';
							echo '<h3>'.OsRolesHelper::name_for_action($group).'</h3>';
							echo '<div class="role-actions-action">';
								foreach($actions as $action){
									$group_action = $group.'__'.$action;
									echo '<div class="role-toggler-wrapper">'.OsFormHelper::toggler_field('capabilities['.$group_action.']', OsRolesHelper::name_for_action($action), in_array($group_action, $user->get_capabilities()), false,'small').'</div>';
								}
							echo '</div>';
						echo '</div>';
			    }?>
		    </div>
			</div>
			<div class="default-user-capabilities-w" style="<?php echo ($user->is_custom_capabilities()) ? 'display: none;' : ''; ?>">
				<div class="latepoint-message latepoint-message-subtle"><?php esc_html_e('Permitted actions are defined by user\'s role settings.', 'latepoint-pro-features'); ?></div>
			</div>
	</div>
	<div class="os-form-buttons">
		<?php echo OsFormHelper::hidden_field('wp_user_id', $user->wp_user->ID); ?>
		<button type="submit" class="latepoint-btn latepoint-btn-block latepoint-btn-lg"><?php _e('Save Changes', 'latepoint-pro-features'); ?></button>
	</div>
</form>
</div>