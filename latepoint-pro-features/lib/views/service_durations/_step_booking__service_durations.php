<?php
/**
 * @var $current_step_code string
 * @var $service_durations array
 * @var $booking OsBookingModel
 *
 * **/
 ?>
<div class="step-service-durations-w latepoint-step-content" data-step-code="<?php echo $current_step_code; ?>">
	<?php
	do_action('latepoint_before_step_content', $current_step_code);
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'before');
	?>
  <?php
		if (count($service_durations) > 1) { ?>
			<div class="os-service-durations os-animated-parent os-items os-as-grid os-three-columns os-selectable-items">
				<?php
				foreach ($service_durations as $service_duration) {
					$summary_duration_label = OsServiceHelper::get_summary_duration_label($service_duration['duration']);
					$is_priced = ($service_duration['charge_amount']) ? true : false;
					$custom_name = $service_duration['name'] ?? '';
					?>
					<div class="<?php echo (!empty($custom_name)) ? 'os-item-span-row' : ''; ?> os-duration-item os-animated-child os-selectable-item os-item with-floating-price <?php echo ($is_priced) ? 'os-priced-item' : ''; ?>"
                         tabindex="0"
						data-item-price="<?php echo $service_duration['charge_amount']; ?>"
						data-priced-item-type="service"
						data-summary-field-name="duration"
						data-summary-value="<?php echo esc_attr($summary_duration_label); ?>"
						data-item-id="<?php echo $service_duration['duration']; ?>"
						data-id-holder=".latepoint_duration">
						<div class="os-animated-self os-item-i os-service-duration-selector">
							<?php if(!empty($custom_name)) echo '<div class="os-duration-name">'.$custom_name.'</div>'; ?>
							<div class="os-duration-value-label">
							<?php if (($service_duration['duration'] >= 60) && !OsSettingsHelper::is_on('steps_show_duration_in_minutes')) { ?>
								<?php
								$hours = floor($service_duration['duration'] / 60);
								$minutes = $service_duration['duration'] % 60;
								?>
								<div class="os-duration-value"><?php echo $hours; ?></div>
								<div class="os-duration-label"><?php echo ($hours > 1) ? __('Hours', 'latepoint-pro-features') : __('Hour', 'latepoint-pro-features'); ?></div>
								<?php if ($minutes) echo '<div class="os-duration-sub-label"><span>' . $minutes . '</span> ' . __('Minutes', 'latepoint-pro-features') . '</div>'; ?>
							<?php } else { ?>
								<div class="os-duration-value"><?php echo $service_duration['duration']; ?></div>
								<div class="os-duration-label"><?php _e('Minutes', 'latepoint-pro-features'); ?></div>
							<?php } ?>
							</div>
							<?php if ($service_duration['charge_amount']) echo '<div class="os-duration-price">' . OsMoneyHelper::format_price($service_duration['charge_amount']) . '</div>'; ?>
						</div>
					</div>
					<?php
				} ?>
			</div>
			<?php
			echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'after');
			do_action('latepoint_after_step_content', $current_step_code);
			?>
			<?php
		}
	  echo OsFormHelper::hidden_field('booking[duration]', $booking->duration, ['class' => 'latepoint_duration', 'skip_id' => true]);
		?>
</div>