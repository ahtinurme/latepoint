<?php
/**
 * @var $booking OsBookingModel
 * @var $current_step_code string
 * @var $recurrence_start_date OsWpDateTime
 * @var $repeat_end_date OsWpDateTime
 * @var $max_repeat_end_counter string
 *
 */
 ?>
<div class="step-recurring-bookings-w latepoint-step-content" data-step-code="<?php echo $current_step_code; ?>">
    <?php
	do_action('latepoint_before_step_content', $current_step_code);
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'before');
    ?>
    <?php echo OsFeatureRecurringBookingsHelper::generate_recurrence_rules_for_booking_form($booking->get_start_datetime_for_customer(), ['max_repeat_end_counter' => $max_repeat_end_counter, 'repeat_end_counter' => min($max_repeat_end_counter, 10)], $booking); ?>
    <div class="os-recurrence-preview-information"></div>
    <div class="os-recurrence-selection-fields-wrapper"></div>
    <div class="os-recurrence-datepicker-wrapper"></div>
    <?php
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'after');
	do_action('latepoint_after_step_content', $current_step_code);
    ?>
</div>