<?php
/**
 * @var $booking OsBookingModel
 * @var $current_step_code string
 * @var $service_extras array
 *
 */
 ?>
<div class="step-service-extras-w latepoint-step-content" data-step-code="<?php echo $current_step_code; ?>" data-clear-action="latepoint_clear_step_service_extras">
    <?php
	do_action('latepoint_before_step_content', $current_step_code);
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'before');
    ?>
  <?php
    if(!empty($service_extras) && is_array($service_extras)){ ?>
      <div class="os-service-extras os-animated-parent os-items os-selectable-items os-as-rows">
        <?php foreach($service_extras as $service_extra){
					$extra_classes = [];
					if(isset($booking->service_extras[$service_extra->id])) $extra_classes[] = 'selected';
					if(OsUtilHelper::is_off($service_extra->multiplied_by_attendees)) $extra_classes[] = 'not-multiplied-by-attendees';
					if($service_extra->maximum_quantity > 1) $extra_classes[] = 'has-quantity';
					if($service_extra->short_description) $extra_classes[] = 'with-description';
					?>
          <div class="os-item os-selectable-item os-priced-item os-allow-multiselect os-animated-child <?php echo implode(' ', $extra_classes); ?>"
						data-max-quantity="<?php echo $service_extra->maximum_quantity; ?>"
            data-summary-field-name="service-extras" 
            data-summary-value="<?php echo esc_attr($service_extra->name); ?>" 
            data-item-price="<?php echo $service_extra->charge_amount; ?>" 
            data-priced-item-type="service_extras" 
            data-id-holder=".latepoint_service_extras_ids" 
            data-item-id="<?php echo $service_extra->id; ?>" tabindex="0">
            <div class="os-service-extra-selector os-animated-self os-item-i">
              <?php if($service_extra->selection_image_id){ ?>
                <div class="os-item-img-w" style="background-image: url(<?php echo $service_extra->selection_image_url; ?>);"></div>
              <?php } ?>
              <div class="os-item-name-w">
                <div class="os-item-name"><?php echo $service_extra->name; ?></div>
                <?php if($service_extra->short_description){ ?>
                  <div class="os-item-desc"><?php echo wp_kses_post($service_extra->short_description); ?></div>
                <?php } ?>
              </div>
              <?php if($service_extra->charge_amount > 0){ ?>
                <div class="os-item-price-w">
                  <div class="os-item-price">
                    <?php echo $service_extra->get_formatted_charge_amount(); ?>
                  </div>
                </div>
              <?php } ?>
              <?php if($service_extra->maximum_quantity > 1){ ?>
              <div class="item-quantity-selector-w">
                <div class="item-quantity-selector item-quantity-selector-minus" data-sign="minus"></div>
                <input type="text" name="" class="item-quantity-selector-input" tabindex="0" value="<?php echo isset($booking->service_extras[$service_extra->id]) ? $booking->service_extras[$service_extra->id]->quantity : 0; ?>" placeholder="<?php _e('Qty', 'latepoint-pro-features'); ?>">
                <div class="item-quantity-selector item-quantity-selector-plus" data-sign="plus"></div>
              </div> 
              <?php 
              } ?>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>

    <?php
	echo OsStepsHelper::get_formatted_extra_step_content($current_step_code, 'after');
	do_action('latepoint_after_step_content', $current_step_code);
    ?>
    <?php echo OsFormHelper::hidden_field('booking[service_extras_ids]', '', [ 'class' => 'latepoint_service_extras_ids', 'skip_id' => true]); ?>
</div>