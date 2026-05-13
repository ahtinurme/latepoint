function latepoint_init_timezone_picker($booking_form_element) {


    $booking_form_element.on('change', '.latepoint_timezone_name', function (e) {
        var $field = jQuery(this);
        var data = {
            action: latepoint_helper.route_action,
            route_name: latepoint_helper.change_timezone_route,
            params: {timezone_name: jQuery(this).val()},
            layout: 'none',
            return_format: 'json'
        }
        $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: latepoint_timestamped_ajaxurl(),
            data: data,
            success: function (data) {
                $booking_form_element.removeClass('step-content-loading');
                if (data.status === "success") {
                    // reload datepicker if its the step
                    if($field.closest('.latepoint-booking-form-element').length){
                        if ($field.closest('.latepoint-booking-form-element').hasClass('current-step-booking__datepicker')) {
                            latepoint_reload_step($field.closest('.latepoint-booking-form-element'));
                        }
                    }else{
                        latepoint_reload_reschedule_calendar($field.closest('.reschedule-calendar-datepicker'));
                    }
                } else {

                }
            }
        });
    });

    if (!latepoint_helper.is_timezone_selected) {
        const tzid = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tzid) {
            if (tzid != $booking_form_element.find('.latepoint_timezone_name').val()) $booking_form_element.find('.latepoint_timezone_name').val(tzid).trigger('change');
        }
    }

    $booking_form_element.on('click', '.os-timezone-info-value', async function (e) {
        let $trigger = jQuery(e.currentTarget);
        $trigger.addClass('os-loading');
        let $container = false;
        if($trigger.closest('.latepoint-booking-form-element').length){
            $container = $trigger.closest('.latepoint-booking-form-element');
        }else{
            $container = $trigger.closest('.reschedule-calendar-datepicker');
        }
        let route_name = $trigger.data('route');

        let response = await jQuery.ajax({
            type: "post",
            dataType: "json",
            url: latepoint_timestamped_ajaxurl(),
            data: {
                action: 'latepoint_route_call',
                route_name: route_name,
                params: {timezone_name: $container.find('.latepoint_timezone_name').val()},
                layout: 'none',
                return_format: 'json'
            }
        });

        if (response.status === "success") {
            if ($container.find('.os-timezone-selector-wrapper-with-shadow').length) {
                $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
            }
            if($container.hasClass('reschedule-calendar-datepicker')){
                $container.append(response.message);
            }else{
                $container.find('.latepoint-form-w').append(response.message);
            }
            latepoint_init_timezone_picker_search($container);
            $trigger.removeClass('os-loading');
        } else {
            throw new Error(response.message);
        }
    });

}

function latepoint_init_timezone_picker_search($container) {
    let $search_input = $container.find('.os-timezones-filter-input');
    $search_input.trigger('focus');

    let $timezone_selector_wrapper = $container.find('.os-timezone-selector-wrapper-with-shadow');


    $container.find('.os-timezone-selector-close').on('click', function (e) {
        $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
    });

    $timezone_selector_wrapper.on('click', '.os-timezone-selector-option ', function (e) {
        $container.find('.latepoint_timezone_name').val(jQuery(this).data('value')).trigger('change');
        $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
        return false;
    });

    $search_input.on('keyup', function (e) {
        if (e.keyCode === 27) {
            // esc
            $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
            return;
        }
        let searchText = jQuery(this).val().toLowerCase();
        let matchFound = false;
        if (searchText) {
            jQuery('.os-selected-timezone-info').hide();
        } else {
            jQuery('.os-selected-timezone-info').show();
        }

        // Process each timezone group
        jQuery('.os-timezone-group').each(function () {
            let groupMatchFound = false;

            // Check each timezone option in this group
            jQuery(this).find('.os-timezone-selector-option').each(function () {
                let tzValue = jQuery(this).attr('data-value') || '';
                let tzName = jQuery(this).text() || '';

                // Check if the timezone matches the search text
                if (tzValue.toLowerCase().includes(searchText) || tzName.toLowerCase().includes(searchText)) {
                    jQuery(this).show();
                    groupMatchFound = true;
                    matchFound = true;
                } else {
                    jQuery(this).hide();
                }
            });

            // Show/hide the group based on whether any matches were found
            if (groupMatchFound) {
                jQuery(this).show();
            } else {
                jQuery(this).hide();
            }
        });

        // If no matches at all, show a message
        if (!matchFound && searchText !== '') {
            if (jQuery('.os-timezone-no-matches').length === 0) {
                jQuery('.os-timezones-list').append('<div class="os-timezone-no-matches">' + jQuery('.os-timezones-filter-input').data('not-found-message') + '</div>');
            } else {
                jQuery('.os-timezone-no-matches').show();
            }
        } else {
            jQuery('.os-timezone-no-matches').hide();
        }
    })
}


jQuery(document).ready(() => {

    jQuery('body').on('latepoint:initBookingForm', '.latepoint-booking-form-element', (e) => {
        let $booking_form_element = jQuery(e.currentTarget);
        latepoint_init_timezone_picker($booking_form_element);
    });
});