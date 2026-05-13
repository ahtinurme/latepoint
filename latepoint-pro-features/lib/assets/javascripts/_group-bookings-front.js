/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
class LatepointGroupBookingsFrontFeature {

  // Init
  constructor() {
    this.ready();
  }

  init_total_attendees_selector($booking_form_element) {

  }

  ready(){
    jQuery(document).ready(() => {
      jQuery('body').on('latepoint:initStep:booking__group_bookings', '.latepoint-booking-form-element', (e, data) => {
        let $step_content = jQuery('.latepoint-step-content[data-step-code="booking__group_bookings"]');

        $step_content.on('change', '.total-attendees-selector-input', function () {
          let max_capacity = jQuery(this).closest('.total-attendees-selector-w').data('max-capacity');
          let min_capacity = jQuery(this).closest('.total-attendees-selector-w').data('min-capacity');
          let new_value = jQuery(this).val();
          new_value = Math.min(Number(max_capacity), Number(new_value));
          new_value = Math.max(Number(min_capacity), Number(new_value));
          jQuery(this).val(new_value);
          let new_value_formatted = new_value + ' ' + ((new_value > 1) ? jQuery(this).data('summary-plural') : jQuery(this).data('summary-singular'));

          let $booking_form_element = jQuery(this).closest('.latepoint-booking-form-element');
          latepoint_reload_summary($booking_form_element);
        });
        $step_content.on('click', '.total-attendees-selector', function () {
          let add_value = (jQuery(this).hasClass('total-attendees-selector-plus')) ? 1 : -1;
          let max_capacity = jQuery(this).closest('.total-attendees-selector-w').data('max-capacity');
          let min_capacity = jQuery(this).closest('.total-attendees-selector-w').data('min-capacity');
          let current_value = jQuery(this).closest('.total-attendees-selector-w').find('input.total-attendees-selector-input').val();
          let new_value = (Number(current_value) > 0) ? Math.max((Number(current_value) + add_value), 1) : 1;
          new_value = Math.min(Number(max_capacity), new_value);
          new_value = Math.max(Number(min_capacity), new_value);
          jQuery(this).closest('.total-attendees-selector-w').find('input').val(new_value).trigger('change');
          return false;
        });
			});
    });
  }
}


window.latepointGroupBookingsFrontFeature = new LatepointGroupBookingsFrontFeature();