/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

function latepoint_check_horizontal_calendar_scroll(){
  if(jQuery('.daily-availability-calendar.horizontal-calendar').length){
    if(jQuery('.daily-availability-calendar.horizontal-calendar').width() < 700){
      jQuery('.daily-availability-calendar.horizontal-calendar').scrollLeft(jQuery('.os-day.selected').index() * jQuery('.os-day.selected').width());
    }
  }
}

function latepoint_calendar_custom_period_created(){
    latepoint_reload_calendar_view();
    latepoint_lightbox_close();
}

function latepoint_init_calendar_quick_actions(){
  latepoint_init_input_masks(jQuery('.quick-calendar-action-settings'));

  jQuery('.quick-calendar-action-day-off').on('click', function(){
    jQuery('.quick-calendar-actions-wrapper').addClass('showing-settings');
    jQuery('.quick-calendar-actions').hide();
    jQuery('.quick-calendar-action-settings').removeClass('setting-slot-off').addClass('setting-day-off');
    jQuery('.quick-calendar-action-settings input[name="blocked_period_settings[full_day_off]"]').val('yes');
    jQuery('.quick-calendar-action-toggle.selected').removeClass('selected');
    jQuery('.quick-calendar-action-toggle[data-period-type="full"]').addClass('selected');

    return false;
  });
  jQuery('.quick-calendar-action-slot-off').on('click', function(){
    jQuery('.quick-calendar-actions-wrapper').addClass('showing-settings');
    jQuery('.quick-calendar-actions').hide();
    jQuery('.quick-calendar-action-settings').removeClass('setting-day-off').addClass('setting-slot-off');
    jQuery('.quick-calendar-action-settings input[name="blocked_period_settings[full_day_off]"]').val('no');
    jQuery('.quick-calendar-action-toggle.selected').removeClass('selected');
    jQuery('.quick-calendar-action-toggle[data-period-type="partial"]').addClass('selected');
    return false;
  });

  jQuery('.quick-calendar-action-toggle').on('click', function(){
    if(jQuery(this).data('period-type') === 'full'){
      jQuery('.quick-calendar-action-day-off').trigger('click');
    }else{
      jQuery('.quick-calendar-action-slot-off').trigger('click');
    }
    return false;
  });
}


// ==========================================
// URL-based calendar state persistence
// ==========================================

var latepoint_calendar_filter_keys = [
  {param: 'services', name: 'calendar_settings[show_service_ids][]'},
  {param: 'agents', name: 'calendar_settings[show_agent_ids][]'},
  {param: 'locations', name: 'calendar_settings[show_location_ids][]'}
];

var latepoint_calendar_skip_url_update = false;

function latepoint_calendar_update_url(){
  if(latepoint_calendar_skip_url_update) return;

  var $form = jQuery('form.os-calendar-settings-form');
  if(!$form.length) return;

  var params = new URLSearchParams(window.location.search);

  // View
  var view = $form.find('input[name="calendar_settings[view]"]').val();
  if(view) params.set('view', view);

  // Date
  var date = $form.find('input[name="calendar_settings[target_date_string]"]').val();
  if(date) params.set('date', date);

  // Filter IDs — only include in URL if not all are checked (to keep URL clean)
  latepoint_calendar_filter_keys.forEach(function(filter){
    var $checkboxes = $form.find('input[name="' + filter.name + '"]');
    var $checked = $checkboxes.filter(':checked');
    if($checkboxes.length && $checked.length < $checkboxes.length){
      var ids = [];
      $checked.each(function(){ ids.push(jQuery(this).val()); });
      params.set(filter.param, ids.join(','));
    }else{
      params.delete(filter.param);
    }
  });

  var newUrl = window.location.pathname + '?' + params.toString();
  window.history.replaceState({}, '', newUrl);
}

function latepoint_calendar_restore_from_url(){
  var params = new URLSearchParams(window.location.search);
  var $form = jQuery('form.os-calendar-settings-form');
  if(!$form.length) return false;

  // Only restore if any calendar params exist in URL
  var hasCalendarParams = params.has('view') || params.has('date') || params.has('services') || params.has('agents') || params.has('locations');
  if(!hasCalendarParams) return false;

  // Restore view
  var view = params.get('view');
  if(view){
    $form.find('input[name="calendar_settings[view]"]').val(view);
    jQuery('.os-calendar-view-option.os-selected').removeClass('os-selected');
    jQuery('.os-calendar-view-option[data-value="' + view + '"]').addClass('os-selected');
    jQuery('.calendar-wrapper').attr('data-view', view);
  }

  // Restore date
  var date = params.get('date');
  if(date){
    $form.find('input[name="calendar_settings[target_date_string]"]').val(date);
  }

  // Restore filter checkboxes
  latepoint_calendar_filter_keys.forEach(function(filter){
    var raw = params.get(filter.param);
    if(raw !== null){
      var ids = raw.split(',');
      var $checkboxes = $form.find('input[name="' + filter.name + '"]');

      $checkboxes.each(function(){
        var $cb = jQuery(this);
        if(ids.indexOf($cb.val()) >= 0){
          $cb.prop('checked', true).attr('checked', 'checked');
        }else{
          $cb.prop('checked', false).removeAttr('checked');
        }
      });

      // Update latecheckbox filter-value label
      var $wrapper = $checkboxes.closest('.latecheckbox-w');
      if($wrapper.length){
        var total = $checkboxes.length;
        var checked = $checkboxes.filter(':checked').length;
        if(checked < total){
          $wrapper.find('.latecheckbox .filter-value').text(checked);
        }else{
          $wrapper.find('.latecheckbox .filter-value').text('All');
        }
      }
    }
  });

  return true;
}


// ==========================================
// Calendar initialization & reload
// ==========================================

function latepoint_init_calendars(){
  latepoint_check_horizontal_calendar_scroll();
  jQuery('.os-calendar-settings-extra .latecheckbox').lateCheckbox();



  jQuery('.os-calendar-view-toggle').on('click', '.os-calendar-view-option', function(){
    jQuery(this).closest('.os-calendar-view-toggle').find('.os-calendar-view-option.os-selected').removeClass('os-selected')
    jQuery(this).addClass('os-selected');
    jQuery('input[name="' + jQuery(this).closest('.os-calendar-view-toggle').data('update-element-by-name') + '"]').val(jQuery(this).data('value')).trigger('change');
    return false;
  });

  jQuery('.calendar-settings-toggler').on('click', function(){
    jQuery('.os-calendar-settings-form').toggleClass('show-extra-settings');
    return false;
  });

  jQuery('.os-calendar-settings-form').on('change', 'input[name="calendar_settings[view]"]', function(){
    jQuery(this).closest('.calendar-wrapper').attr('data-view', jQuery(this).val());
  });

  jQuery('.os-calendar-settings-form').on('change', 'select, input, .latecheckbox ', function(){
    latepoint_reload_calendar_view();
  });


  jQuery('.calendar-view-wrapper').on('click', '.weekly-calendar-agent-selector', function(){
    jQuery('.weekly-calendar-agent-selector.selected').removeClass('selected');
    jQuery(this).addClass('selected');
    jQuery('.os-calendar-settings-form input[name="calendar_settings[selected_agent_id]"]').val(jQuery(this).data('agent-id'));
    jQuery('.agent-weekly-calendar.selected').removeClass('selected');
    jQuery('.agent-weekly-calendar[data-agent-id="'+jQuery(this).data('agent-id')+'"]').addClass('selected');
    return false;
  });

  jQuery('.calendar-view-wrapper').on('click', '.daily-calendar-action-navigation-btn', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery(this).data('target-date')).trigger('change');
    return false;
  });

  jQuery('.calendar-view-wrapper').on('click', '.daily-availability-calendar .os-day', function(){
    jQuery('.os-monthly-calendar-days-w .os-day.selected').removeClass('selected');
    jQuery(this).addClass('selected');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery(this).data('date')).trigger('change');
    return false;
  });


  jQuery('.os-calendar-today-btn').on('click', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery(this).data('target-date')).trigger('change');
    return false;
  });

  jQuery('.os-calendar-prev-btn').on('click', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery('input[name="prev_target_date"]').val()).trigger('change');
    return false;
  });

  jQuery('.os-calendar-next-btn').on('click', function(){
    jQuery(this).addClass('os-loading');
    jQuery('input[name="calendar_settings[target_date_string]"]').val(jQuery('input[name="next_target_date"]').val()).trigger('change');
    return false;
  });

  // Restore calendar state from URL params on page load
  if(latepoint_calendar_restore_from_url()){
    latepoint_calendar_skip_url_update = true;
    latepoint_reload_calendar_view();
    latepoint_calendar_skip_url_update = false;
  }

}

function latepoint_reload_calendar_view(){
  let $calendar_wrapper = jQuery('.calendar-view-wrapper');
  if(!$calendar_wrapper.length) return;
  $calendar_wrapper.addClass('os-loading');

  // Update URL with current filter state
  latepoint_calendar_update_url();

  let calendar_settings = new FormData(jQuery('form.os-calendar-settings-form')[0]);

  let data = new FormData();
  data.append('params', latepoint_formdata_to_url_encoded_string(calendar_settings));
  data.append('action', latepoint_helper.route_action);
  data.append('route_name', $calendar_wrapper.data('route'));
  data.append('return_format', 'json');

  jQuery.ajax({
    type: "post",
    dataType: "json",
    processData: false,
    contentType: false,
    url: latepoint_timestamped_ajaxurl(),
    data: data,
    success: function (response) {
      if (response.status === "success") {
        $calendar_wrapper.html(response.message).removeClass('os-loading');
        jQuery('.os-calendar-today-btn, .os-calendar-prev-btn, .os-calendar-next-btn').removeClass('os-loading');
        jQuery('.os-current-month-label .current-month').text(response.top_date_label);
        jQuery('.os-current-month-label .current-year').text(response.top_date_year);
        latepoint_check_horizontal_calendar_scroll();
      }
    }
  });

}