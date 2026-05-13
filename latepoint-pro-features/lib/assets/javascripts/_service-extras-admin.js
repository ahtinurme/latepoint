/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class LatepointServiceExtrasAdminAddon {

  // Init
  constructor(){
    this.ready();
  }


  reload_service_extras_on_quick_form($booking_data_form){
    $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').addClass('os-loading');

    var route_name = $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').data('route-name');
    var form_data = new FormData($booking_data_form.closest('.order-quick-edit-form')[0]);
    form_data.append('order_item_id', $booking_data_form.data('order-item-id'));
    form_data.append('booking_id', $booking_data_form.data('booking-id'));
    var data = {
      action: latepoint_helper.route_action,
      route_name: route_name,
      params: latepoint_formdata_to_url_encoded_string(form_data),
      return_format: 'json'
    };

    jQuery.ajax({
      type: 'post',
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: (response) => {
        $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').removeClass('os-loading');
        if(response.status === latepoint_helper.response_status.success){
          $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').html(response.message);
          $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper .os-late-select').lateSelect();

        }else{
          alert("Error!");
        }
      }
    });
  }


  ready(){
    jQuery(() => {
      let thisClass = this;

      jQuery('body').on('latepoint:initBookingDataForm', '.quick-order-form-w', (event) => {

        // handle change event for agent/service/location dropdown picker...
        jQuery(event.target).find('.os-affects-service-extras').on('change', (event) => {
          this.reload_service_extras_on_quick_form(jQuery(event.target).closest('.order-item-booking-data-form-wrapper'));
        });

        jQuery(event.target).on('click', '.clear-lateselect', function(){
          jQuery('#'+jQuery(this).data('lateselect-id')).val('').closest('.os-form-group').find('select').trigger('change');
          jQuery('.clear-missing-lateselect').remove();
        });
      });
    });
  }
}
window.latepointServiceExtrasAdminAddon = new LatepointServiceExtrasAdminAddon();