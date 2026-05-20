<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="latepoint-top-bar-w">
	<a href="#" title="<?php esc_attr_e('Menu', 'latepoint'); ?>" class="latepoint-top-iconed-link latepoint-mobile-top-menu-trigger">
		<i class="latepoint-icon latepoint-icon-menu"></i>
	</a>
	<div class="latepoint-top-search-w">
		<div class="latepoint-top-search-input-w">
			<i class="latepoint-icon latepoint-icon-x latepoint-mobile-top-search-trigger-cancel"></i>
			<input type="text" data-route="<?php echo esc_attr(OsRouterHelper::build_route_name('search', 'query_results')); ?>"
			       class="latepoint-top-search"
			       name="search"
			       placeholder="<?php esc_attr_e('Search...', 'latepoint'); ?>">
		</div>
		<div class="latepoint-top-search-results-w"></div>
	</div>
	<a href="#" title="<?php esc_attr_e('Search', 'latepoint'); ?>"
	   class="latepoint-top-iconed-link latepoint-mobile-top-search-trigger"><i
			class="latepoint-icon latepoint-icon-search1"></i></a>
    <?php echo apply_filters('latepoint_upgrade_top_bar_link_html', '<a href="#" '.OsSettingsHelper::get_link_attributes_for_premium_features().' class="latepoint-unlock-features-link"><i class="latepoint-icon latepoint-icon-switch"></i><span>'.esc_html__('Unlock All Features', 'latepoint').'</span></a>'); ?>
	<?php do_action('latepoint_top_bar_before_actions'); ?>
	<a href="<?php echo esc_url(OsRouterHelper::build_link(['activities', 'index'])); ?>"
	   title="<?php esc_attr_e('Activity Log', 'latepoint'); ?>"
	   class="latepoint-top-iconed-link latepoint-top-activity-trigger">
		<i class="latepoint-icon latepoint-icon-clock"></i>
	</a>
	<a href="<?php echo esc_url(OsRouterHelper::build_link(['bookings', 'pending_approval'])); ?>"
	   title="<?php esc_attr_e('Pending Bookings', 'latepoint'); ?>"
	   class="latepoint-top-iconed-link latepoint-top-notifications-trigger">
		<i class="latepoint-icon latepoint-icon-box1"></i>
		<?php
		$count_pending_bookings = OsBookingHelper::count_pending_bookings();
		if ($count_pending_bookings > 0) echo '<span class="notifications-count">' . esc_html($count_pending_bookings) . '</span>'; ?>
	</a>
	<a href="#" <?php echo OsOrdersHelper::quick_order_btn_html(); ?>
	   title="<?php esc_attr_e('New Booking', 'latepoint'); ?>"
	   class="latepoint-mobile-top-new-appointment-btn-trigger latepoint-top-iconed-link">
		<i class="latepoint-icon latepoint-icon-plus"></i>
	</a>
	<?php do_action('latepoint_top_bar_after_actions'); ?>
	<a href="#"
	   class="latepoint-top-new-appointment-btn latepoint-btn latepoint-btn-primary" <?php echo OsOrdersHelper::quick_order_btn_html(); ?>>
		<i class="latepoint-icon latepoint-icon-plus"></i>
		<span><?php esc_html_e('Booking', 'latepoint'); ?></span>
	</a>

		<div class="latepoint-top-user-info-w">
			<div class="avatar-w top-user-info-toggler" style="background-image: url('<?php echo esc_url(OsAuthHelper::get_current_user()->get_avatar_url()); ?>');"></div>
            <div class="mobile-user-info-w">
                <div class="m-avatar-w" style="background-image: url('<?php echo esc_url(OsAuthHelper::get_current_user()->get_avatar_url()); ?>');"></div>
                <div class="muid-info">
                    <div class="muid-info-username"><?php echo esc_html(OsAuthHelper::get_current_user()->get_display_name()); ?></div>
                    <div class="muid-info-usertype"><?php echo esc_html(OsAuthHelper::get_current_user()->get_user_type_label()); ?></div>
                </div>
                <div class="m-logout">
                    <a href="<?php echo esc_url(wp_logout_url()); ?>">
                        <i class="latepoint-icon latepoint-icon-cancel"></i>
                        <span><?php esc_html_e('Logout', 'latepoint'); ?></span>
                    </a>
                </div>
            </div>
			<div class="latepoint-user-info-dropdown">
				<a href="#" class="latepoint-user-info-close top-user-info-toggler"><i class="latepoint-icon latepoint-icon-x"></i></a>
				<div class="latepoint-uid-head">
					<div class="uid-avatar-w">
						<div class="uid-avatar"
						     style="background-image: url('<?php echo esc_url(OsAuthHelper::get_current_user()->get_avatar_url()); ?>');"></div>
					</div>
					<div class="uid-info">
						<div class="uid-info-username"><?php echo esc_html(OsAuthHelper::get_current_user()->get_display_name()); ?></div>
						<div class="uid-info-usertype"><?php echo esc_html(OsAuthHelper::get_current_user()->get_user_type_label()); ?></div>
					</div>
				</div>
				<?php do_action('latepoint_top_bar_mobile_after_user'); ?>
				<ul>
					<?php if (OsAuthHelper::get_current_user()->get_link_to_settings()) { ?>
						<li>
							<a href="<?php echo esc_url(OsAuthHelper::get_current_user()->get_link_to_settings()); ?>">
								<i class="latepoint-icon latepoint-icon-cog1"></i>
								<span><?php esc_html_e('Settings', 'latepoint'); ?></span>
							</a>
						</li>
					<?php } ?>
					<li>
						<a href="<?php echo esc_url(wp_logout_url()); ?>">
							<i class="latepoint-icon latepoint-icon-cancel"></i>
							<span><?php esc_html_e('Logout', 'latepoint'); ?></span>
						</a>
					</li>
				</ul>
			</div>
		</div>
</div>