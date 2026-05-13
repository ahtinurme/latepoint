<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/* @var $activities OsActivityModel[] */
/* @var $coupon OsCouponModel */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="booking-activity-log-panel-w side-sub-panel-wrapper">
	<div class="side-sub-panel-header os-form-header">
		<h2><?php esc_html_e( 'Coupon History', 'latepoint-pro-features' ); ?></h2>
		<a href="#" class="booking-activity-log-panel-close latepoint-side-sub-panel-close latepoint-side-sub-panel-close-trigger"><i class="latepoint-icon latepoint-icon-x"></i></a>
	</div>
	<div class="side-sub-panel-content booking-activity-log-panel-i">
		<div class="booking-activities-list">
			<div class="quick-booking-info">
				<?php if ( $coupon->created_at ) echo '<span>' . esc_html__( 'Created On: ', 'latepoint-pro-features' ) . '</span><strong>' . esc_html( OsTimeHelper::get_readable_date( new OsWpDateTime( $coupon->created_at, new DateTimeZone( 'UTC' ) ) ) ) . '</strong>'; ?>
			</div>
			<?php if ( empty( $activities ) ) { ?>
				<div class="booking-activity-row">
					<div class="booking-activity-name"><?php esc_html_e( 'No activity found.', 'latepoint-pro-features' ); ?></div>
				</div>
			<?php } else { ?>
				<?php foreach ( $activities as $activity ) { ?>
					<div class="booking-activity-row">
						<div class="booking-activity-name"><?php echo esc_html( $activity->name ); ?></div>
						<div class="spacer"></div>
						<div class="booking-activity-date"><?php echo esc_html( $activity->nice_created_at ); ?></div>
						<?php echo $activity->get_link_to_object( '<i class="latepoint-icon latepoint-icon-file-text"></i>' ); ?>
					</div>
				<?php } ?>
			<?php } ?>
		</div>
	</div>
</div>
