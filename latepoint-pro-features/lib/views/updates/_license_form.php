<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="os-form-w">
	<h3><?php esc_html_e('License Key Registration', 'latepoint-pro-features'); ?></h3>
  <form action="" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('updates', 'save_license_information')); ?>" data-os-success-action="reload">
		<?php wp_nonce_field('latepoint_save_license_information'); ?>
		<?php if($license['status_message']){ ?>
			<?php if($license['is_active'] == 'yes'){ ?>
				<div class="os-form-message-w status-success"><ul><li><?php echo esc_html($license['status_message']); ?></li></ul></div>
			<?php }else{ ?>
				<div class="os-form-message-w status-error"><ul><li><?php echo esc_html($license['status_message']); ?></li></ul></div>
			<?php } ?>
		<?php }else{
			echo '<div class="os-form-message-w"><ul><li>'.esc_html__('Please enter your LatePoint license key to receive free plugin updates and install addons.', 'latepoint-pro-features').'</li></ul></div>';
		} ?>
  	<div class="os-row">
  		<div class="os-col-lg-3">
				<?php echo OsFormHelper::text_field('license[full_name]', __('Your Name', 'latepoint-pro-features'), $license['full_name'], ['theme' => 'simple']); ?>
			</div>
  		<div class="os-col-lg-3">
				<?php echo OsFormHelper::text_field('license[email]', __('Your Email Address', 'latepoint-pro-features'), $license['email'], ['theme' => 'simple']); ?>
  		</div>
  		<div class="os-col-6">
				<?php echo OsFormHelper::text_field('license[license_key]', __('License Key', 'latepoint-pro-features'), $license['license_key'], ['theme' => 'simple']); ?>
  		</div>
  	</div>
  	<div class="license-buttons-w">
        <button type="submit" class="latepoint-btn latepoint-btn-primary"><i class="latepoint-icon latepoint-icon-zap"></i><span><?php _e('Activate Now', 'latepoint-pro-features'); ?></span></button>
	    <a href="http://wpdocs.latepoint.com/how-to-find-my-license-key-for-latepoint/" target="_blank">Can't find license key?</a>
    </div>
	</form>
</div>