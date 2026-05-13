<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php if ($version_info['extra_html']) { ?>
	<?php echo '<div>' . $version_info['extra_html'] . '</div>'; ?>
<?php } ?>
<?php if (version_compare($version_info['version'], LATEPOINT_VERSION) > 0) { ?>
	<div class="new-version-message">
		<h3><?php esc_html_e('Update is Available', 'latepoint-pro-features') ?></h3>
		<div class="version-warn-icon"></div>
		<div class="new-version-info">
			<div class="version-info-text">
				<span>
                    <?php
                    // translators: %s is version number
                    echo sprintf(esc_html__('LatePoint %s is available.', 'latepoint-pro-features'), '<strong>'.$version_info['version'].'</strong>'); ?>
                </span>
				<span>
                    <?php
                    // translators: %s is version number
                    echo sprintf(esc_html__('You\'re running version %s', 'latepoint-pro-features'), '<strong>'.LATEPOINT_VERSION.'</strong>'); ?>
                </span>
			</div>
			<div class="version-buttons-w">


			<?php if (OsLicenseHelper::is_license_active()) { ?>
				<a class="update-latepoint-btn" href="#" data-os-success-action="reload"
				   data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('updates', 'update_plugin')); ?>">
					<i class="latepoint-icon latepoint-icon-grid-18"></i>
					<span><?php esc_html_e('Update Now', 'latepoint-pro-features'); ?></span>
				</a>
			<?php } else {
				echo '<span class="key-prompt">Enter your purchase key below to enable updates</span>';
			} ?>
			<a href="https://latepoint.com/changelog/" target="_blank" class="view-changelog-link">
				<i class="latepoint-icon latepoint-icon-external-link"></i>
				<span><?php esc_html_e('View Changelog', 'latepoint-pro-features'); ?></span>
			</a>
			</div>
		</div>
		<div class="new-version-update-prompt">
		</div>
	</div>
<?php } else { ?>
	<div class="new-version-message is-latest">
		<h3><?php esc_html_e('You are using the latest version', 'latepoint-pro-features') ?></h3>
		<div class="version-check-icon"></div>
		<div class="current-version-info">
			<span><?php esc_html_e('Installed Version: ', 'latepoint-pro-features') ?><strong><?php echo esc_html(LATEPOINT_VERSION); ?></strong></span>
		</div>
		<div class="version-buttons-w">
			<a href="https://latepoint.com/changelog/" target="_blank" class="view-changelog-link">
				<i class="latepoint-icon latepoint-icon-external-link"></i>
				<span><?php esc_html_e('View Changelog', 'latepoint-pro-features'); ?></span>
			</a>
			<a href="<?php echo esc_url($version_info['link']); ?>" target="_blank">
				<i class="latepoint-icon latepoint-icon-globe"></i>
				<span><?php esc_html_e('Official Website', 'latepoint-pro-features'); ?></span>
			</a>
		</div>
	</div>
<?php } ?>