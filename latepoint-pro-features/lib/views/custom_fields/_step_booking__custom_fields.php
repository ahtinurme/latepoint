<?php
/**
 * @var $booking OsBookingModel
 * @var $current_step_code string
 * @var $custom_fields_for_booking array
 *
 */
?>
<div class="step-custom-fields-for-booking-w latepoint-step-content" data-step-code="<?php echo $current_step_code; ?>">
	<?php
	do_action( 'latepoint_before_step_content', $current_step_code );
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'before' );
	?>
    <div class="os-row">
		<?php
		if ( ! empty( $custom_fields_for_booking ) ) {
			echo OsCustomFieldsHelper::output_custom_fields_for_model( $custom_fields_for_booking, $booking, 'booking', '', [ 'custom_form_id_for_file_fields' => 'new' ] );
		} ?>
    </div>
	<?php
	echo OsStepsHelper::get_formatted_extra_step_content( $current_step_code, 'after' );
	do_action( 'latepoint_after_step_content', $current_step_code );
	?>
</div>