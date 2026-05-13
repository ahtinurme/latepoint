<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="latepoint-system-status-w">
	<div class="os-accordion-wrapper">
		<div class="os-accordion-title">
			<i class="latepoint-icon latepoint-icon-layers"></i>
			<h3><?php esc_html_e('Features', 'latepoint-pro-features'); ?></h3></div>
		<div class="os-accordion-content">
            <?php echo OsFormHelper::toggler_field('settings[pro_feature_toggle_messages]', __('Chat With Customers', 'latepoint-pro-features'), OsSettingsHelper::is_on('pro_feature_toggle_messages', true), false, 'large', ['nonce' => wp_create_nonce('update_settings'), 'instant_update_route' => OsRouterHelper::build_route_name('settings', 'update')]); ?>
        </div>
    </div>
	<div class="os-accordion-wrapper">
		<div class="os-accordion-title">
			<i class="latepoint-icon latepoint-icon-file-text"></i>
			<h3><?php esc_html_e('System Info', 'latepoint-pro-features'); ?></h3></div>
		<div class="os-accordion-content">
			<ul>
				<li>
					<?php
					esc_html_e('LatePoint Plugin Version:', 'latepoint-pro-features'); ?> <strong><?php echo esc_html(LATEPOINT_VERSION); ?></strong>
				</li>
				<li>
					<?php
					esc_html_e('LatePoint Database Version:', 'latepoint-pro-features'); ?>
					<strong><?php echo esc_html(OsSettingsHelper::get_db_version()); ?></strong>
					<?php echo '<a href="#" class="reset-db-version-link" data-os-action="' . esc_attr(OsRouterHelper::build_route_name('debug', 'reset_plugin_db_version')) . '"
					                      data-os-params="' . esc_attr(OsUtilHelper::build_os_params([], 'latepoint_reset_plugin_db_version')) . '"
					                      data-os-success-action="reload"><i class="latepoint-icon latepoint-icon-refresh-cw"></i><span>' . esc_html__('reset', 'latepoint-pro-features') . '</span></a>'; ?>
				</li>
				<li>
					<?php
					esc_html_e('PHP Version:', 'latepoint-pro-features'); ?> <strong><?php echo esc_html(phpversion()); ?></strong>
				</li>
				<li>
					<?php
					global $wpdb;
					esc_html_e('MySQL Version:', 'latepoint-pro-features'); ?> <strong><?php echo esc_html($wpdb->db_version()); ?></strong>
				</li>
				<li>
					<?php
					global $wpdb;
					esc_html_e('WordPress Version:', 'latepoint-pro-features'); ?> <strong><?php echo esc_html(get_bloginfo('version')); ?></strong>
				</li>
			</ul>
		</div>
	</div>
	<div class="os-accordion-wrapper">
		<div class="os-accordion-title">
			<i class="latepoint-icon latepoint-icon-box"></i>
			<h3><?php esc_html_e('Installed Addons', 'latepoint-pro-features'); ?></h3></div>
		<div class="os-accordion-content">
			<div class="installed-addons-wrapper">
				<?php foreach ($addons as $addon) {
					if (!is_plugin_active($addon->wp_plugin_path)) continue;
                    if ( ! function_exists( 'get_plugin_data' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					}
					$addon_data = get_plugin_data(OsAddonsHelper::get_addon_plugin_path($addon->wp_plugin_path));
					$installed_version = (isset($addon_data['Version'])) ? $addon_data['Version'] : '1.0.0';
					$update_available_html = (version_compare($addon->version, $installed_version) > 0) ? '<span class="os-iab-update-available">' . esc_html__('Update Available', 'latepoint-pro-features') . '</span>' : '';
					$current_addon_db_version = get_option($addon->wp_plugin_name . '_addon_db_version');
					echo '<div class="os-installed-addon-box">';
					echo '<h4>' . esc_html($addon->name) . '</h4>';
					echo '<div class="os-iab-version-info">' . $update_available_html . '
								<span>' . esc_html__('Core:', 'latepoint-pro-features') . '</span><strong>' . esc_html($installed_version) . '</strong>
								<span>' . esc_html__('Database:', 'latepoint-pro-features') . '</span><strong>' . esc_html($current_addon_db_version) . '</strong>
								<a class="reset-db-version-link" href="#" data-os-action="' . esc_attr(OsRouterHelper::build_route_name('debug', 'reset_addon_db_version')) . '" 
						                      data-os-params="' . esc_attr(OsUtilHelper::build_os_params(['plugin_name' => $addon->wp_plugin_name], 'latepoint_reset_addon_db_version')) . '" 
						                      data-os-success-action="reload"><i class="latepoint-icon latepoint-icon-refresh-cw"></i><span>' . esc_html__('reset', 'latepoint-pro-features') . '</span></a>
							</div>';
					echo '</div>';
				} ?>
			</div>
		</div>
	</div>
	<div class="os-accordion-wrapper">
		<div class="os-accordion-title">
			<i class="latepoint-icon latepoint-icon-refresh-cw"></i>
			<h3><?php esc_html_e('Tasks Due', 'latepoint-pro-features'); ?></h3></div>
		<div class="os-accordion-content">
			<?php
			$todo_html = '';
			$bookings = new OsBookingModel();
			$v4_bookings = $bookings->where(['order_item_id' => 'IS NULL'])->get_results_as_models();
			if($v4_bookings){
				$todo_html.= '<div>'.count($v4_bookings).' bookings need to be migrated</div>';
				$todo_html.= '<a href="'.OsRouterHelper::build_link(['updates', 'migrate_from_version4']).'" target="_blank">'.__('Run Migration', 'latepoint-pro-features').'</a>';
			}
			$transactions = new OsTransactionModel();
			$v4_transactions = $transactions->where(['order_id' => 'IS NULL'])->get_results_as_models();
			if($v4_transactions){
				$todo_html.= '<div>'.count($v4_transactions).' transactions need to be migrated</div>';
				$todo_html.= '<a href="'.OsRouterHelper::build_link(['updates', 'migrate_from_version4']).'" target="_blank">'.__('Run Migration', 'latepoint-pro-features').'</a>';
			}
			if($todo_html){
				echo $todo_html;
			}else{
				echo '<div>'.esc_html__('Nothing to do', 'latepoint-pro-features').'</div>';
			}
			?>
		</div>
	</div>
</div>