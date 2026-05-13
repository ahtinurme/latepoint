function latepoint_webhook_removed($elem){
  $elem.closest('.os-webhook-form').remove();
}

function latepoint_webhook_updated($elem){
  location.reload();
}

function latepoint_init_webhooks_form(){
  jQuery('.latepoint-content-w').on('click', '.os-webhook-form-info', function(){
    jQuery(this).closest('.os-webhook-form').toggleClass('os-is-editing');
    return false;
  });
  jQuery('.latepoint-content-w').on('change', 'select.os-webhook-type-select', function(){
    if(jQuery(this).val() == 'select'){
      jQuery(this).closest('.os-webhook-form').find('.os-webhook-select-values').show();
    }else{
      jQuery(this).closest('.os-webhook-form').find('.os-webhook-select-values').hide();
    }
  });
  jQuery('.latepoint-content-w').on('keyup', '.os-webhook-name-input', function(){
    jQuery(this).closest('.os-webhook-form').find('.os-webhook-name').text(jQuery(this).val());
  });
}

( function( $ ) {
  "use strict";

  // DOCUMENT READY
  $( function() {
    latepoint_init_webhooks_form();
  });



} )( jQuery );