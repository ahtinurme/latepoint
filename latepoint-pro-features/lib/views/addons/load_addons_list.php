<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if($categories){
	echo '<div class="addons-categories-wrapper">';
	echo '<div class="addon-category-filter-trigger is-selected">Show All</div>';
	foreach($categories as $category){
		echo '<div class="addon-category-filter-trigger" data-category="'.esc_attr($category->id).'">'.esc_html($category->name).'</div>';
	}
	echo '</div>';
}

if($messages){
	echo '<div class="addon-messages-wrapper">';
	foreach($messages as $message){
		echo '<div class="addon-message addon-message-type-'.esc_attr($message->type).'" data-message-id="'.esc_attr($message->id).'">';
			echo '<div>';
				echo $message->title ? '<div class="message-title">'.esc_html($message->title).'</div>' : '';
				echo '<div class="message-content">'.esc_html($message->content).'</div>';
			echo '</div>';
			echo '<div class="addon-message-buttons-wrapper">';
				echo '<a href="#" data-os-pass-this="yes" data-os-params="'.esc_attr(OsUtilHelper::build_os_params(['message_id' => $message->id])).'" data-os-action="'.esc_attr(OsRouterHelper::build_route_name('addons', 'dismiss_message')).'" data-os-after-call="latepoint_dismiss_message" class="message-dismiss-button"><i class="latepoint-icon latepoint-icon-x"></i><span>Dismiss</span></a>';
				echo '<a target="_blank" href="'.esc_url($message->link_url).'" class="message-link"><span>'.esc_html($message->link_title).'</span><i class="latepoint-icon latepoint-icon-arrow-right"></i></a>';
			echo '</div>';
		echo '</div>';
	}
	echo '</div>';
}
if($addons){ ?>
	<div class="addons-boxes-w" data-addons-nonce="<?php echo esc_attr(wp_create_nonce('latepoint_manage_addons')); ?>">
		<?php foreach($addons as $addon){ 
			$is_activated = is_plugin_active($addon->wp_plugin_path);
			$is_installed = OsAddonsHelper::is_addon_installed($addon->wp_plugin_path);
			$addon_css_class = '';
			$is_featured = false;
			if($is_activated) $addon_css_class.= ' status-activated';
			if($is_installed){
				$addon_css_class.= ' status-installed';
                if ( ! function_exists( 'get_plugin_data' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					}
				$addon_data = get_plugin_data(OsAddonsHelper::get_addon_plugin_path($addon->wp_plugin_path));
				$installed_version = (isset($addon_data['Version'])) ? $addon_data['Version'] : '1.0.0';
				if(version_compare($addon->version, $installed_version) > 0){
					$addon_css_class.= ' status-update-available';
				}
			}else{
				if($addon->is_featured == 'yes'){
					$addon_css_class.= ' status-is-featured';
					$is_featured = true;
				}
			}
			$addon_data_html = ' data-addon-path="'.esc_attr($addon->wp_plugin_path).'" data-addon-name="'.esc_attr($addon->wp_plugin_name).'" '; ?>
			<div class="addon-box <?php echo esc_attr($addon_css_class); ?>" data-category="<?php echo esc_attr($addon->categories); ?>">
				<?php if($is_featured) echo '<div class="addon-label"><i class="latepoint-icon latepoint-icon-star"></i><span>'.esc_html__('Featured', 'latepoint-pro-features').'</span></div>'; ?>
				<div class="addon-media" style="background-image: url(<?php echo esc_url($addon->media_url); ?>);"></div>
				<div class="addon-header">
					<h3 class="addon-name">
						<a target="_blank" href="<?php echo esc_url($addon->purchase_url); ?>">
							<span><?php echo esc_html($addon->name); ?></span>
							<i class="latepoint-icon latepoint-icon-external-link"></i>
						</a>
					</h3>
				</div>
				<div class="addon-body">
					<div class="addon-desc"><?php echo empty($addon->short_description) ? esc_html($addon->description) : esc_html($addon->short_description); ?></div>
					<div class="addon-meta">
						<?php 
						if($is_installed){
								if(version_compare($addon->version, $installed_version) > 0){
									echo '<div>'.esc_html__('Latest:', 'latepoint-pro-features').' '.esc_html($addon->version).'</div>';
									echo '<div>'.esc_html__('Installed:', 'latepoint-pro-features').' '.esc_html($installed_version).'</div>';
								}else{
									echo '<div>'.esc_html__('Installed:', 'latepoint-pro-features').' '.esc_html($installed_version).'</div>';
								}
						}else{
							echo '<div>'.__('Latest:', 'latepoint-pro-features').' '.esc_html($addon->version).'</div>';
						} ?>
					</div>
				</div>
				<div class="addon-footer">
						<?php 
							if(version_compare($addon->required_version, LATEPOINT_VERSION) > 0){
								echo '<a class="os-update-plugin-link" href="'. esc_url(OsRouterHelper::build_link(['updates', 'status'])).'"><span><i class="latepoint-icon latepoint-icon-refresh-cw"></i></span><span>'.esc_html__('Requires LatePoint', 'latepoint-pro-features').' v'.esc_html($addon->required_version).'</span></a>';
							}else{
								if($is_activated){
									// is activated
									if(version_compare($addon->version, $installed_version) > 0){
										if(!OsSettingsHelper::is_env_demo() || is_super_admin()){
											echo '<a href="#" class="os-install-addon-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'install_addon')).'" '.$addon_data_html.'>';
												echo '<span><i class="latepoint-icon latepoint-icon-grid-18"></i></span><span>'.esc_html__('Update Now', 'latepoint-pro-features').'</span>';
											echo '</a>';
										}
									}else{
										echo '<a href="#" class="os-subtle-addon-action-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'deactivate_addon')).'" '.$addon_data_html.'>';
											echo esc_html__('Deactivate', 'latepoint-pro-features');
										echo '</a>';
										echo '<div class="os-addon-activated-label"><span><i class="latepoint-icon latepoint-icon-check"></i></span><span>'.esc_html__('Active', 'latepoint-pro-features').'</span></div>';
									}
								}else{
									if(!OsSettingsHelper::is_env_demo() || current_user_can( 'setup_network' ) ){
										// check if its installed
										if($is_installed){
											// installed but outdated
											if(version_compare($addon->version, $installed_version) > 0){
												echo '<a href="#" class="os-install-addon-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'install_addon')).'" '.$addon_data_html.'>';
													echo '<span><i class="latepoint-icon latepoint-icon-grid-18"></i></span><span>'.esc_html__('Update Now', 'latepoint-pro-features').'</span>';
												echo '</a>';
											}else{
												echo '<a href="#" class="os-subtle-addon-action-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'delete_addon')).'" '.$addon_data_html.'>';
													echo esc_html__('Delete', 'latepoint-pro-features');
												echo '</a>';
												// installed but not activated
												echo '<a href="#" class="os-install-addon-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'activate_addon')).'" '.$addon_data_html.'>';
													echo '<span><i class="latepoint-icon latepoint-icon-box"></i></span><span>'.esc_html__('Activate', 'latepoint-pro-features').'</span>';
												echo '</a>';
											}
										}else{
											// not installed 
											if($addon->price > 0){
												if($addon->purchased){
													echo '<a href="#" class="os-install-addon-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'install_addon')).'" '.$addon_data_html.'>';
														echo '<span>'.esc_html__('Install Now', 'latepoint-pro-features').'</span>';
													echo '</a>';
												}else{
													echo '<a target="_blank" href="'.esc_url(OsRouterHelper::build_link(['updates', 'status'])).'" class="os-purchase-addon-btn">';
														echo '<span>'.esc_html__('Activate License to Install', 'latepoint-pro-features').'</span>';
													echo '</a>';
												}
											}else{
												echo '<a href="#" class="os-install-addon-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'install_addon')).'" '.$addon_data_html.'>';
													echo '<span>'.esc_html__('Install Now', 'latepoint-pro-features').'</span>';
												echo '</a>';
											}
										}
									}else{
										// demo
										if($is_installed){
											if($addon->price > 0) echo '<span class="addon-price">DEMO</span>';
											echo '<a href="#" class="os-install-addon-btn os-addon-action-btn" data-route-name="'.esc_attr(OsRouterHelper::build_route_name('addons', 'activate_addon')).'" '.$addon_data_html.'>';
												echo '<span><i class="latepoint-icon latepoint-icon-download"></i></span><span>'.esc_html__('Try for FREE', 'latepoint-pro-features').'</span>';
											echo '</a>';
										}else{
											if($addon->price > 0) echo '<span class="addon-price">DEMO</span>';
											echo '<a target="_blank" href="'.esc_url($addon->purchase_url).'" class="os-purchase-addon-btn">';
												echo '<span><i class="latepoint-icon latepoint-icon-external-link"></i></span><span>'.esc_html__('Learn More', 'latepoint-pro-features').'</span>';
											echo '</a>';
										}
									}
								}
							}?>
				</div>
			</div>
		<?php } ?>
	</div>
<?php } ?>
