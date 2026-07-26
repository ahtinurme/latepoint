<?php
/** @var $bundle OsBundleModel */
/** @var $order_item OsOrderItemModel */
/** @var $bundle_services_with_booked_count OsServiceModel[] */
/** @var $order OsOrderModel */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="customer-bundle-tile">
	<div class="customer-bundle-tile-inner">
		<div class="bundle-main-info-wrapper">
			<div class="bundle-main-info">
				<h6 class="bundle-name"><?php echo esc_html($bundle->name); ?></h6>
				<div class="bundle-order-info"><?php echo esc_html__('Order', 'latepoint').' <a href="#" '.OsCustomerHelper::generate_order_summary_btn($order->id).'>#'.esc_html($order->confirmation_code).'</a>'; ?></div>
				<?php /* ===== CUSTOM CODE START (yumefit: package valid-until) ===== */ ?>
				<?php if (function_exists('yumefit_bundle_expiry_date')) { $yf_expiry = yumefit_bundle_expiry_date($order, $bundle); if ($yf_expiry) { ?>
					<div class="bundle-valid-until" style="margin-top:4px;font-size:13px;color:#6b6b6b;"><?php echo esc_html__('Valid until', 'latepoint').': '.esc_html($yf_expiry->format('d.m.Y')); ?></div>
				<?php } } ?>
				<?php /* ===== CUSTOM CODE END (yumefit: package valid-until) ===== */ ?>
			</div>
			<div class="bundle-icon">
				<i class="latepoint-icon latepoint-icon-layers"></i>
			</div>
		</div>
		<div class="bundle-services">
			<?php foreach($bundle_services_with_booked_count as $service){ ?>
				<div class="bundle-included-service-wrapper">
					<div class="bundle-included-service-name"><?php echo esc_html($service->name); ?></div>
					<?php
                        // translators: %1$d number of scheduled appointments, %2$d is total available
						$scheduled_text = $service->join_attributes['total_scheduled_bookings'] ? sprintf(__('%1$d of %2$d Scheduled', 'latepoint'), $service->join_attributes['total_scheduled_bookings'],  $service->join_attributes['quantity']) : __('Not Scheduled', 'latepoint');
						echo '<div class="bundle-included-service-quantity">'.esc_html($scheduled_text).'</div>';
						?>
				</div>
			<?php } ?>
		</div>
		<div class="customer-bundle-bottom-actions">
			<?php /* ===== CUSTOM CODE START (yumefit: no scheduling button on expired package) ===== */ ?>
			<?php if (!empty($yf_expiry) && $yf_expiry < new DateTime(current_time('Y-m-d'))) { ?>
				<div style="text-align:center;color:#6b6b6b;font-weight:600;padding:10px 0;"><?php esc_html_e('Package expired', 'latepoint-yumefit-rules'); ?></div>
			<?php } else { ?>
			<a href="#" class="latepoint-btn latepoint-btn-primary latepoint-btn-outline latepoint-btn-block" <?php echo OsCustomerHelper::generate_bundle_scheduling_btn($order_item->id); ?>>
				<span><?php esc_html_e('Start Scheduling', 'latepoint'); ?></span>
				<i class="latepoint-icon latepoint-icon-arrow-right1"></i>
			</a>
			<?php } ?>
			<?php /* ===== CUSTOM CODE END (yumefit: no scheduling button on expired package) ===== */ ?>
		</div>
	</div>
	<div class="customer-bundle-tile-shadow"></div>
	<div class="customer-bundle-tile-shadow"></div>
</div>