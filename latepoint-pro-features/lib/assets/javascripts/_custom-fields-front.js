/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

class LatepointCustomFieldsFrontAddon {

  // Init
  constructor() {
    this.ready();
  }

  init_google_places_autosuggest($wrapper){
    if($wrapper.find('.latepoint-google-places-autocomplete').length){
      if(typeof google !== 'undefined'){
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
        console.error('Error loading Google API library');
      }
    }
  }

  init_file_upload_fields($wrapper){
    $wrapper.find('.os-form-file-upload-group').each(function() {
      // do nothing if already initialized
      if (jQuery(this).hasClass('os-initialized')) return true;
      jQuery(this).on('click', '.os-uploaded-file-info', function () {
        if (!jQuery(this).hasClass('is-uploaded')) return false;
      });
      // click on remove file button
      jQuery(this).on('click', '.uf-remove', function () {
        var $file_info = jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info');
        var $file_input = jQuery(this).closest('.os-form-group').find('input[type="file"]');
        if ($file_input.hasClass('required') && $file_info.has('is-uploaded')) {
          // file input is required and file was uploaded before, we can't clear it unless they pick another file to replace currento one
          if (confirm(latepoint_helper.custom_fields_remove_required_file_prompt)) $file_input.trigger('click');
        } else {
          if ($file_info.hasClass('is-uploaded')) {
            // file was uploaded before/ remove it from model and remove the hidden field that was carrying the url value
            if (!confirm(latepoint_helper.custom_fields_remove_file_prompt)) return false;
            var route_name = $file_info.closest('.os-form-group').find('input[type="file"]').data('route-name');
            var params = $file_info.closest('.os-form-group').find('input[type="file"]').data('params');
            var data = {
              action: latepoint_helper.route_action,
              route_name: route_name,
              params: params,
              return_format: 'json'
            }
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
      jQuery(this).on('change', 'input[type="file"]', function () {
        if (this.files.length) {
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').show().attr('href', '#').attr('target', '_self').find('.uf-name').text(this.files[0].name);
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').hide();
        } else {
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').hide().removeClass('is-uploaded');
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').show();
        }
      });
    });
  }

  ready(){
    jQuery(document).ready(() => {
      let $customer_cabinet = jQuery('.tab-content-customer-info-form');
      if($customer_cabinet.length){
        this.init_file_upload_fields($customer_cabinet);
        this.init_google_places_autosuggest($customer_cabinet);
      }
      // init custom fields, this is triggered when custom fields step for boooking or customer is initialised
      jQuery('body').on('latepoint:initStep', '.latepoint-booking-form-element', (e, data) => {
        var $step_content = jQuery('.latepoint-step-content[data-step-code="' + data.step_code + '"]');
        this.init_file_upload_fields($step_content);
        this.init_google_places_autosuggest($step_content);
        latepoint_init_form_masks();
			});
    });
  }
}


window.latepointCustomFieldsFrontAddon = new LatepointCustomFieldsFrontAddon();