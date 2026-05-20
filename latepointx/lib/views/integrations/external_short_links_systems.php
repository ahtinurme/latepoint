<?php

/**
 * @var $available_short_links_systems array
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


?>
<div class="latepoint-settings-w os-form-w">
  <form action="" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('settings', 'update')); ?>">
	  <?php wp_nonce_field('update_settings'); ?>
		<div class="os-section-header"><h3><?php esc_html_e('Available URL Shortener', 'latepoint'); ?></h3></div>
		<?php
		if($available_short_links_systems){
			echo '<div class="os-togglable-items-w">';
				foreach($available_short_links_systems as $short_links){ ?>
			      <div class="os-togglable-item-w">
			        <div class="os-togglable-item-head">
			          <div class="os-toggler-w">
			            <?php echo OsFormHelper::toggler_field('settings[enable_'.$short_links['code'].']', false, OsShortLinksSystemsHelper::is_external_short_links_system_enabled($short_links['code']), 'toggleShortLinksSystemSettings_'.$short_links['code'], 'large'); ?>
			          </div>
			          <?php if(!empty($short_links['image_url'])) echo '<img class="os-togglable-item-logo-img" src="'.esc_url($short_links['image_url']).'"/>'; ?>
			          <div class="os-togglable-item-name"><?php echo esc_html($short_links['name']); ?></div>
			        </div>
			        <div class="os-togglable-item-body" style="<?php echo OsShortLinksSystemsHelper::is_external_short_links_system_enabled($short_links['code']) ? '' : 'display: none'; ?>" id="toggleShortLinksSystemSettings_<?php echo esc_attr($short_links['code']); ?>">
			          <?php
								/**
								 * Hook your short links system settings here
								 *
								 * @since 5.1.94
								 * @hook latepoint_external_short_links_system_settings
								 *
								 * @param {string} Code of the short links system
								 */
			          do_action('latepoint_external_short_links_system_settings', $short_links['code']);
								?>
			        </div>
			      </div>
				  <?php
				}
			echo '</div>';
	    echo '<div class="os-form-buttons">';
	      echo OsFormHelper::button('submit', __('Save Settings', 'latepoint'), 'submit', ['class' => 'latepoint-btn']);
	    echo '</div>';
		}else{
			echo OsUtilHelper::generate_missing_addon_link(__('Requires upgrade to a premium version', 'latepoint'));
		} ?>
  </form>
</div>