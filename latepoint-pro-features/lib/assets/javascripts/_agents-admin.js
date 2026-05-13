function latepoint_init_agent_schedule_tabs(){
  var syncing = false;
  jQuery('.latepoint-content-w').on('change', '.schedule-location-selector', function(){
    var $select = jQuery(this);
    var locationValue = $select.val();
    var $tabs = $select.closest('.white-box').find('.agent-schedule-tabs');
    var tabPrefix = $tabs.data('tab-prefix');
    var tabId = tabPrefix + '-tab-location-' + locationValue;
    $tabs.find('.schedule-tab-panel').removeClass('is-active');
    $tabs.find('#' + tabId).addClass('is-active');

    if (syncing) return;
    syncing = true;

    // Sync all other location dropdowns to the same value
    jQuery('.schedule-location-selector').not($select).each(function(){
      jQuery(this).val(locationValue).trigger('change');
    });

    syncing = false;
  });
}

jQuery(document).ready(function(){
  latepoint_init_agent_schedule_tabs();
});
