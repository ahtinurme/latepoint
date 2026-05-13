/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointCustomFieldsAdminAddon {

  // Init
  constructor(){
    this.ready();
  }

  init_new_custom_field_form(){
    this.init_custom_fields_conditions_form();
  }

  add_custom_field_condition($btn, response){
    $btn.closest('.cf-condition').after(response.message);
    this.init_custom_fields_conditions_form();
  }


  init_google_places_autosuggest($wrapper){
    if($wrapper.find('.latepoint-google-places-autocomplete').length){
      if(typeof google !== 'undefined' && typeof google.maps !== 'undefined'){
        $wrapper.find('.latepoint-google-places-autocomplete').each((index, input) => {
          if(jQuery(input).hasClass('os-initialized')) return true;
          const options = {
            fields: ["formatted_address"]
          };
          if(latepoint_helper.google_places_country_restriction) options.componentRestrictions = {country: latepoint_helper.google_places_country_restriction};
          let autocomplete = new google.maps.places.Autocomplete(input, options);
          jQuery(input).addClass('os-initialized');
        });
      }else{
        console.error('Error loading Google API library. Check if API keys is entered correctly.');
      }
    }
  }

  init_custom_fields_conditions_form(){
    jQuery('.os-late-select').lateSelect();
  }

  init_file_upload_fields($wrapper){
    $wrapper.find('.os-form-file-upload-group').each(function(){
      // do nothing if already initialized
      if(jQuery(this).hasClass('os-initialized')) return true;

      jQuery(this).on('click', '.os-uploaded-file-info', function(){
        if(!jQuery(this).hasClass('is-uploaded')) return false;
      });
      // click on remove file button
      jQuery(this).on('click', '.uf-remove', function(){
        var $file_info = jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info');
        var $file_input = jQuery(this).closest('.os-form-group').find('input[type="file"]');
        if($file_input.hasClass('required') && $file_info.has('is-uploaded')){
          // file input is required and file was uploaded before, we can't clear it unless they pick another file to replace currento one
          if(confirm(latepoint_helper.custom_fields_remove_required_file_prompt)) $file_input.trigger('click');
        }else{
          if($file_info.hasClass('is-uploaded')){
            // file was uploaded before/ remove it from model and remove the hidden field that was carrying the url value
            if(!confirm(latepoint_helper.custom_fields_remove_file_prompt)) return false;
            var route_name = $file_info.closest('.os-form-group').find('input[type="file"]').data('route-name');
            var params = $file_info.closest('.os-form-group').find('input[type="file"]').data('params');
            var data = {  action: latepoint_helper.route_action,
              route_name: route_name,
              params: params,
              return_format: 'json' }
            jQuery.ajax({
              type: "post",
              dataType: "json",
              url: latepoint_timestamped_ajaxurl(),
              data: data,
              success: function (data) {
                if (data.status === "success") {
                  $file_info.closest('.os-form-group').find('input[type="hidden"]').remove();
                }
              }
            });
          }
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').hide();
          $file_input.val(null).trigger('change');
        }
        return false;
      });

      // file for upload was selected, or cleared
      jQuery(this).on('change', 'input[type="file"]', function(){
        if(this.files.length){
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').show().attr('href', '#').attr('target', '_self').find('.uf-name').text(this.files[0].name);
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').hide();
        }else{
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').hide().removeClass('is-uploaded');
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').show();
        }
      });
      jQuery(this).addClass('os-initialized');
    });
  }

  reload_conditional_custom_fields_on_booking_data_form($booking_data_form){
    $booking_data_form.find('.latepoint-custom-fields-for-booking-wrapper').each((index, elem) => {
      let $custom_fields_wrapper = jQuery(elem);
      let $booking_data_form = $custom_fields_wrapper.closest('.order-item-booking-data-form-wrapper');

      $custom_fields_wrapper.addClass('os-loading');

      var route_name = $custom_fields_wrapper.data('route-name');
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
          $custom_fields_wrapper.removeClass('os-loading');
          if(response.status === latepoint_helper.response_status.success){
            $custom_fields_wrapper.html(response.message);
            latepoint_init_input_masks($custom_fields_wrapper);
            this.init_google_places_autosuggest($custom_fields_wrapper);
          }else{
            alert("Error!");
          }
        }
      });
    })

  }


  ready(){
    jQuery(() => {
      this.init_custom_fields_conditions_form();

      let $customer_form = jQuery('.customer-form-wrapper');
      if($customer_form.length){
        this.init_file_upload_fields($customer_form);
        this.init_google_places_autosuggest($customer_form);
      }

      let thisClass = this;

      jQuery('.latepoint-content-w').on('change', 'select.os-form-block-type-select', function(){
        jQuery(this).closest('.os-form-block').find('.custom-fields-google-places-api-status, .custom-fields-select-values').hide();
        switch(jQuery(this).val()){
          case 'select':
          case 'multiselect':
            jQuery(this).closest('.os-form-block').find('.custom-fields-select-values').show();
            break;
          case 'google_address_autocomplete':
            jQuery(this).closest('.os-form-block').find('.custom-fields-google-places-api-status').show();
            break;
        }
        thisClass.init_custom_fields_default_value_field(jQuery(this));
      });

      jQuery('body').on('latepoint:initInputMasks', '.quick-order-form-w', (event) => {
        this.init_file_upload_fields(jQuery(event.target));
        this.init_google_places_autosuggest(jQuery(event.target));
      });

      jQuery('body').on('latepoint:initBookingDataForm', '.quick-order-form-w', (event) => {
        let $booking_data_form = jQuery(event.target);
        this.init_file_upload_fields($booking_data_form);
        this.init_google_places_autosuggest($booking_data_form);


        // handle change event for agent/service/location dropdown picker...
        $booking_data_form.find('.os-affects-custom-fields').on('change', (event) => {
          this.reload_conditional_custom_fields_on_booking_data_form($booking_data_form);
        });
      });

      jQuery('.os-custom-fields-w').on('change', 'select.custom-field-condition-property', (event) => {
        var $select = jQuery(event.currentTarget);
        var params = {
          property: $select.val(),
          custom_field_id: $select.closest('.os-form-block').data('os-form-block-id'),
          condition_id: $select.closest('.cf-condition').data('condition-id')
        }
        var route_name = $select.data('route');
        var data = {  action: latepoint_helper.route_action,
          route_name: route_name,
          params: params,
          return_format: 'json' }

        jQuery.ajax({
          type: 'post',
          dataType : "json",
          url : latepoint_timestamped_ajaxurl(),
          data : data,
          success: (response) => {
            if(response.status === latepoint_helper.response_status.success){
              $select.closest('.cf-condition').find('.custom-field-condition-values-w').replaceWith(response.message);
              this.init_custom_fields_conditions_form();
            }else{
              alert("Error!");
            }
          }
        });
      });
      jQuery('.os-custom-fields-w').on('click', '.cf-remove-condition', (event) => {
        if(jQuery(event.currentTarget).closest('.cf-conditions').find('.cf-condition').length  > 1){
          jQuery(event.currentTarget).closest('.cf-condition').remove();
        }else{
          alert('You need to have at least one condition if your custom field is set to be conditional.')
        }
        return false;
      });

      this.init_custom_fields_conditions_form();
    });
  }

  init_custom_fields_default_value_field($field) {
    const fieldType = $field.val();
    let $formBlock = $field.closest('.os-form-block');
    let $defaultValueField = $formBlock.find(`*[name="custom_fields[${$formBlock.data('os-form-block-id')}][value]"]`);
    let $defaultValueFieldRow = $defaultValueField.closest('.custom-fields-default-value-row');

    if (JSON.parse(latepoint_helper.custom_field_types_with_default_value).includes(fieldType)) {
      let params = {
        field_type: $field.val(),
        field_name: `custom_fields[${$formBlock.data('os-form-block-id')}][value]`,
        field_value: $defaultValueField.val()
      };
      let data = {
        action: latepoint_helper.route_action,
        route_name: latepoint_helper.custom_field_default_value_field_html_route,
        params: params,
        return_format: 'json'
      };
      jQuery.ajax({
        type: 'post',
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: (response) => {
          if(response.status === latepoint_helper.response_status.success){
            $defaultValueField.closest('.os-form-group').replaceWith(response.message);
            $defaultValueFieldRow.show();
            latepoint_init_input_masks($formBlock);
          }else{
            $defaultValueField.val('');
            $defaultValueFieldRow.hide();
            alert(response.message);
          }
        }
      });
    } else {
      $defaultValueField.val('');
      $defaultValueFieldRow.hide();
    }
  }
}
window.latepointCustomFieldsAdminAddon = new LatepointCustomFieldsAdminAddon();