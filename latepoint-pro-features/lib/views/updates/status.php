<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="version-and-license-info-w">
	<?php if($is_license_active){ ?>
		<div class="active-license-info is-active">
			<div class="version-check-icon"></div>
			<h3>License Status: <strong>Active</strong></h3>
			<div>
				<span>Thank you for using LatePoint</span>
			</div>
			<div class="license-info-buttons-w">
				<a href="#" class="latepoint-show-license-details">
					<i class="latepoint-icon latepoint-icon-file-text"></i>
					<span>License Info</span>
				</a>
				<a href="#" class="os-deactivate-license-btn" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('updates', 'remove_license')); ?>" data-os-params="<?php echo esc_attr(OsUtilHelper::build_os_params([], 'latepoint_remove_license')); ?>" data-os-success-action="reload">
					<i class="latepoint-icon latepoint-icon-slash"></i>
					<span>Deactivate</span>
				</a>
			</div>
			<div class="license-info-w" style="display: none;">
				<ul>
					<li><span>Name</span><strong><?php echo esc_html($license['full_name']); ?></strong></li>
					<li><span>Email</span><strong><?php echo esc_html($license['email']); ?></strong></li>
					<li><span>License Key</span><strong><?php echo esc_html(OsUtilHelper::obfuscate_license($license['license_key'])); ?></strong></li>
				</ul>
			</div>
		</div>
	<?php }else{ ?>
		<div class="active-license-info">
			<div class="version-warn-icon"></div>
			<h3>Activate Your License</h3>
			<div>
				<span>Register your license to access premium features.</span>
			</div>
			<div class="license-form-w">
				<?php include('_license_form.php'); ?>
			</div>
		</div>
	<?php } ?>
</div>
<div class="debug-info-wrapper" data-os-output-target="self" data-os-action-onload="<?php echo esc_attr(OsRouterHelper::build_route_name('debug', 'status')); ?>"></div>