<?php
/**
 * @var $booking OsBookingModel
 * @var $current_step_code string
 *
 */
 ?>
<div class="step-group-bookings-w latepoint-step-content" data-step-code="<?php echo $current_step_code; ?>">
    <?php
	do_action('latepoint_before_step_content', $current_step_code);
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'before');
    ?>
	<div class="select-total-attendees-w style-centered">
		<div class="select-total-attendees-label">
			<h4><?php _e('How Many People?', 'latepoint-pro-features'); ?></h4>
			<div class="sta-sub-label"><?php _e('Maximum capacity is', 'latepoint-pro-features'); ?>
				<span><?php echo ($booking->service) ? $booking->service->capacity_max : 1 ?></span></div>
		</div>
		<div class="total-attendees-selector-w"
		     data-min-capacity="<?php echo ($booking->service) ? $booking->service->capacity_min : 1 ?>"
		     data-max-capacity="<?php echo ($booking->service) ? $booking->service->capacity_max : 1 ?>">
			<div class="total-attendees-selector total-attendees-selector-minus"><i
					class="latepoint-icon latepoint-icon-minus"></i></div>
			<input type="text" tabindex="0" data-summary-singular="<?php _e('Person', 'latepoint-pro-features'); ?>"
			       data-summary-plural="<?php _e('People', 'latepoint-pro-features'); ?>" name="booking[total_attendees]"
			       class="total-attendees-selector-input latepoint_total_attendees"
			       value="<?php echo ($booking->service) ? max($booking->total_attendees, $booking->service->capacity_min) : $booking->total_attendees; ?>"
			       placeholder="<?php _e('Qty', 'latepoint-pro-features'); ?>">
			<div class="total-attendees-selector total-attendees-selector-plus"><i
					class="latepoint-icon latepoint-icon-plus"></i></div>
		</div>
	</div>
    <?php
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'after');
	do_action('latepoint_after_step_content', $current_step_code);
    ?>
</div>