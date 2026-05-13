<?php
/* @var $recurring_bookings_data array */
/* @var $price_info string */
/* @var $recurring_bookings_errors array */
/* @var $non_bookable_count int */
?>
<div class="summary-header">
	<div class="summary-header-inner">
	  <span class="summary-header-label"><?php esc_html_e('Review Slots', 'latepoint-pro-features'); ?></span>
        <a href="#" class="latepoint-lightbox-summary-trigger"><i class="latepoint-icon-common-01"></i></a>
    </div>
</div>
<div class="recurring-bookings-preview-wrapper">
	<?php
    if($non_bookable_count){
        echo '<div class="recurring-bookings-error">'.sprintf(esc_html__('%s of %s time slots are not available. Please review them below.', 'latepoint-pro-features'), $non_bookable_count, count($recurring_bookings_data)).'</div>';
    }
    $index = 1;
        foreach ( $recurring_bookings_data as $stamp => $data ) {
        ?>
        <div data-stamp="<?php echo $stamp; ?>" class="recurring-booking-preview <?php echo $data['unchecked'] != 'yes' ? 'rbp-is-on' : 'rbp-is-off'; ?> <?php echo $data['is_bookable'] ? 'is-available' : 'is-not-available'; ?>" data-start-datetime-utc="<?php echo esc_attr($data['booking']->start_datetime_utc); ?>">
            <div class="rbp-index"><?php echo $index; ?></div>
            <div class="rbp-info">
                <?php if(!empty($data['original_start_datetime'])) echo '<div class="rbp-original-datetime">'.$data['original_start_datetime'].'</div>'; ?>
                <div class="rbp-date"><?php echo $data['booking']->get_nice_start_date_in_timezone(OsTimeHelper::get_timezone_name_from_session()); ?></div>
                <div class="rbp-time">
                    <span><?php echo $data['booking']->get_nice_start_time_in_timezone(OsTimeHelper::get_timezone_name_from_session()); ?></span>
                    <a href="#" class="rbp-time-edit"><i class="latepoint-icon latepoint-icon-edit-3"></i></a>
                </div>
            </div>
            <?php if($data['is_bookable']){ ?>
            <div class="rbp-checkbox"></div>
            <?php }else{
                echo '<div class="rbp-warn-slot"><div class="rbp-warn-slot-message">'.esc_html__('This slot is unavailable. You can choose a different one, or it will be skipped automatically.', 'latepoint-pro-features').'</div></div>';
            }?>
        </div>
	<?php
        $index++;
        } ?>
    <?php if($recurring_bookings_errors){
        foreach($recurring_bookings_errors as $error){
            echo '<div class="recurring-bookings-notice">'.$error.'</div>';
        }
    }
    ?>
</div>
<div class="recurring-bookings-preview-total-wrapper">
    <?php echo $price_info; ?>
</div>
<a href="#" class="recurring-bookings-preview-continue-btn latepoint-btn latepoint-btn-primary latepoint-btn-block">
    <span><?php esc_html_e('Continue', 'latepoint-pro-features'); ?></span>
    <i class="latepoint-icon latepoint-icon-arrow-2-right"></i>
</a>
