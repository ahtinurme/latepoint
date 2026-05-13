<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="os-intro-full-screen-w">
	<div class="os-intro-full-screen-i">
		<a href="<?php echo esc_url(OsRouterHelper::build_link(OsRouterHelper::build_route_name('dashboard', 'index'))); ?>" class="os-intro-full-screen-close-trigger"><span><?php esc_html_e('Dismiss', 'latepoint-pro-features'); ?></span><i class="latepoint-icon latepoint-icon-x"></i></a>
		<div class="os-intro-logo">
			<img src="<?php echo esc_attr(LATEPOINT_IMAGES_URL . 'logo.svg'); ?>" width="60" height="60" alt="LatePoint Dashboard">
		</div>
		<div class="os-intro-heading">
			Version 5
		</div>
		<div class="os-intro-sub-heading">
			What's New
		</div>
		<ul class="list-of-version-improvements">
			<li>
				<div class="improvement-heading">New "Pro Features" addon</div>
				<div class="improvement-description">To simplify plugin management we've merged 12 addons into a single "Pro Features" addon. <?php if(!empty($deactivated_plugins)) echo 'We have deactivated <strong>'.esc_html(implode(', ', $deactivated_plugins)).'</strong> addons for you because they have been replaced with a PRO addon.';?></div>
				<div class="improvement-install-pro os-loading" data-route-name="<?php echo esc_attr(OsRouterHelper::build_route_name('addons', 'install_pro_addon')); ?>">
					<span>Installing "Pro Features" addon</span>
				</div>
			</li>
			<li>
				<div class="improvement-heading">Refreshed User Interface</div>
				<div class="improvement-description">The new design is sleek, intuitive, and user-friendly, making it easier than ever to navigate and manage appointments.</div>
				<div class="improvement-media">
					<img src="https://cdn.latepoint.com/blog-media/v5-admin-ui.jpg" alt="">
				</div>
			</li>
			<li>
				<div class="improvement-heading">Sell Bundles</div>
				<div class="improvement-description">This new feature allows customers to purchase a bundle of bookings and conveniently schedule their appointments later from their customer cabinet. Boost your sales and enhance customer satisfaction with our new bundle and save option!</div>
				<div class="improvement-media">
		      <video controls="controls" width="625px">
						<source src="https://cdn.latepoint.com/blog-media/schedule-bundle-opt-2x.mp4" type="video/mp4"/>
		      </video>
					<div class="media-note">Scheduling purchased bundle</div>
				</div>
			</li>
			<li>
				<div class="improvement-heading">Shopping Cart</div>
				<div class="improvement-description">Now, your customers can book multiple different services in a single order with ease. This streamlined process saves time and makes it more convenient for customers to select and schedule the services they need in one go.</div>
				<div class="improvement-media">
					<img src="https://cdn.latepoint.com/blog-media/order-multiple.png" alt="">
				</div>
			</li>
			<li>
				<div class="improvement-heading">Visual Customizer</div>
				<div class="improvement-description">Improved booking form customizer lets you edit text directly on the preview, it also helps organize each step settings and show them whenever the step is selected, which lets your preview changes in real time.</div>
				<div class="improvement-media">
					<img src="https://cdn.latepoint.com/blog-media/visual-customizer-form.png" alt="">
				</div>
			</li>
		</ul>
	</div>
  <div class="os-intro-full-screen-footer">
    <a href="<?php echo esc_attr(OsRouterHelper::build_link(['dashboard', 'index'])); ?>" class="latepoint-btn latepoint-btn-lg"><span><?php esc_html_e('Start Using Version 5', 'latepoint-pro-features'); ?></span> <i class="latepoint-icon latepoint-icon-arrow-right"></i></a>
  </div>
</div>