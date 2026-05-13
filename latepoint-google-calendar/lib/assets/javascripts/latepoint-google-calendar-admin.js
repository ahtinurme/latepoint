class LatepointGoogleCalendarAdminAddon {

  // Init
  constructor(){
    this.init();
  }

  booking_synced($elem){
    $elem.closest('.os-booking-tiny-box').removeClass('not-synced').addClass('is-synced');
    this.sync_update_progress($elem.closest('.syncing-calendar-wrapper').find('.os-sync-stats-and-progress-w'), false);
  }

  gcal_event_deleted($elem){
    $elem.closest('.os-booking-tiny-box').remove();
  }

  booking_unsynced($elem){
    $elem.closest('.os-booking-tiny-box').addClass('not-synced').removeClass('is-synced');
    this.sync_update_progress($elem.closest('.syncing-calendar-wrapper').find('.os-sync-stats-and-progress-w'), true);
  }


  sync_update_progress($wrapper, is_removed){
    var synced_total = $wrapper.find('.os-sync-value span').text();
    if(is_removed){
      synced_total = parseInt(synced_total) - 1;
    }else{
      synced_total = parseInt(synced_total) + 1;
    }
    $wrapper.find('.os-sync-value span').text(synced_total);
    $wrapper.find('.os-sync-progress').data('value', synced_total);
    var chart_total = $wrapper.find('.os-sync-progress').data('total');
    var chart_value = $wrapper.find('.os-sync-progress').data('value');
    if(chart_total > 0){
      var percent = Math.min(Math.round(chart_value / chart_total * 100), 100);
      $wrapper.find('.os-sync-progress-bar').css('width', percent + '%');
    }
  }

  remove_first_synced_booking_with_google(){
    var $trigger = jQuery('.os-booking-tiny-box.is-synced:first .os-booking-sync-google-trigger');
    if(!$trigger.length || jQuery('.remove-all-bookings-from-google-trigger').hasClass('stop-removing')){
      jQuery('.remove-all-bookings-from-google-trigger').removeClass('os-removing').removeClass('stop-removing').find('span').text(jQuery('.remove-all-bookings-from-google-trigger').data('label-remove'));
      return false;
    }

    var route = $trigger.data('os-remove-action');
    var params = $trigger.data('os-params');
    var data = { action: latepoint_helper.route_action, route_name: route, params: params, layout: 'none', return_format: 'json' };
    $trigger.addClass('os-loading');
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        if(response.status === "success"){
          $trigger.closest('.os-booking-tiny-box').removeClass('is-synced').addClass('not-synced');
          $trigger.removeClass('os-loading');
          latepointGoogleCalendarAdminAddon.sync_update_progress($trigger.closest('.syncing-calendar-wrapper').find('.os-sync-stats-and-progress-w'), true);
          latepointGoogleCalendarAdminAddon.remove_first_synced_booking_with_google();
        }
      }
    });
  }


  sync_next_booking_with_google(){
    var $trigger = jQuery('.os-booking-tiny-box.not-synced:first .os-booking-sync-google-trigger');
    if(!$trigger.length || jQuery('.sync-all-bookings-to-google-trigger').hasClass('stop-syncing')){
      jQuery('.sync-all-bookings-to-google-trigger').removeClass('os-syncing').removeClass('stop-syncing').find('span').text(jQuery('.sync-all-bookings-to-google-trigger').data('label-sync'));
      return false;
    }

    var route = $trigger.data('os-action');
    var params = $trigger.data('os-params');
    var data = { action: latepoint_helper.route_action, route_name: route, params: params, layout: 'none', return_format: 'json' };
    $trigger.addClass('os-loading');
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        if(response.status === "success"){
          $trigger.closest('.os-booking-tiny-box').removeClass('not-synced').addClass('is-synced');
          $trigger.removeClass('os-loading');
          latepointGoogleCalendarAdminAddon.sync_update_progress($trigger.closest('.syncing-calendar-wrapper').find('.os-sync-stats-and-progress-w'), false);
          latepointGoogleCalendarAdminAddon.sync_next_booking_with_google();
        }
      }
    });
  }

  /**
   *  On load, called to load the auth2 library and API client library.
   */
  init() {


    jQuery(document).ready(() => {


      jQuery('select.agent_google_calendar_selector').on('change', function(e){
        var route = jQuery(this).data('route');
        var calendar_id = jQuery(this).val();
        var agent_id = jQuery(this).data('agent-id');
        var data = { action: latepoint_helper.route_action,
                      route_name: route,
                      params: {calendar_id: calendar_id, agent_id: agent_id},
                      layout: 'none',
                      return_format: 'json' }
        jQuery.ajax({
          type : "post",
          dataType : "json",
          url : latepoint_timestamped_ajaxurl(),
          data : data,
          success: function(data){
            if(data.status === "success"){
              latepoint_add_notification(data.message);
              location.reload();
            }else{
              alert('Error! Connecting calendar for push failed.');
            }
          }
        });
      });


      jQuery('.sync-all-bookings-to-google-trigger').on('click', function(){
        if(jQuery(this).hasClass('os-syncing')){
          jQuery(this).addClass('stop-syncing');
          jQuery(this).find('span').text(jQuery(this).data('label-sync'));
        }else{
          jQuery(this).find('span').text(jQuery(this).data('label-cancel-sync'));
          jQuery(this).addClass('os-syncing');
          latepointGoogleCalendarAdminAddon.sync_next_booking_with_google();
        }
        return false;
      });


      jQuery('.remove-all-bookings-from-google-trigger').on('click', function(){

        if(jQuery(this).hasClass('os-removing')){
          jQuery(this).addClass('stop-removing');
          jQuery(this).find('span').text(jQuery(this).data('label-remove'));
        }else{
          if(!confirm(jQuery(this).data('os-prompt'))) return false;
          jQuery(this).find('span').text(jQuery(this).data('label-cancel-remove'));
          jQuery(this).addClass('os-removing');
          latepointGoogleCalendarAdminAddon.remove_first_synced_booking_with_google();
        }
        return false;
      });

      jQuery('.calendar-available-for-sync input[type="hidden"]').on('change', function(){
        var $elem_wrapper = jQuery(this).closest('.calendar-available-for-sync');
        var route = $elem_wrapper.data('route');
        var calendar_id = $elem_wrapper.data('calendar-id');
        var agent_id = $elem_wrapper.data('agent-id');
        var data = {
          action: latepoint_helper.route_action,
          route_name: route,
          params: {
            calendar_id: calendar_id,
            agent_id: agent_id
          },
          return_format: 'json'
        }
        $elem_wrapper.addClass('os-loading');
        jQuery.ajax({
          type : "post",
          dataType : "json",
          url : latepoint_timestamped_ajaxurl(),
          data : data,
          success: function(response){
            $elem_wrapper.removeClass('os-loading');
            if(response.status === "success"){
              latepoint_add_notification(response.message);
              location.reload();
            }else{
              alert(response.message, 'error');
            }
          }
        });
      });

      if(latepoint_helper.google_calendar_is_enabled && jQuery('#google-signin-button').length) {

        const client = google.accounts.oauth2.initCodeClient({
          client_id: latepoint_helper.google_calendar_client_id,
          scope: 'https://www.googleapis.com/auth/calendar',
          ux_mode: 'popup',
          callback: this.saveAuthCode,
        });

        jQuery('#google-signin-button').on('click', function(){
          client.requestCode();
        });
      }
    });
  }
  saveAuthCode(response) {
    var params = {  code: response.code,
                    agent_id: jQuery('#google-signin-button').data('agent-id')
                  };

    var data = {  action: 'latepoint_route_call',
                  route_name: jQuery('#google-signin-button').data('route'),
                  params: params,
                  layout: 'none',
                  return_format: 'json'
                };

    jQuery.ajax({
      type: 'POST',
      url: latepoint_timestamped_ajaxurl(),
      dataType : "json",
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      data: data,
      success: function(response) {
        latepoint_add_notification(response.message);
        location.reload();
      }
    });
  }
}

window.latepointGoogleCalendarAdminAddon = new LatepointGoogleCalendarAdminAddon();