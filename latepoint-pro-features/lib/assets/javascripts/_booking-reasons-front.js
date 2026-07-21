/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

// Opens after the cancellation reason lightbox is rendered (data-os-after-call).
// Submits the cancellation to the core request_cancellation route with the reason attached;
// the reason itself is stored by OsFeatureBookingReasonsHelper on latepoint_booking_updated.
function latepoint_init_booking_cancellation() {
    let $lightbox = jQuery('.booking-reason-lightbox');
    if (!$lightbox.length) return;

    $lightbox.on('click', '.latepoint-confirm-cancellation-trigger', function () {
        let $trigger = jQuery(this);
        let $wrapper = $trigger.closest('.booking-reason-lightbox');

        $trigger.addClass('os-loading');
        let params = {
            id: $wrapper.find('input[type="hidden"].latepoint_booking_id').val(),
            key: $wrapper.find('input[type="hidden"].latepoint_manage_booking_key').val(),
            cancellation_reason: $wrapper.find('.latepoint-booking-reason').val(),
            _wpnonce: $wrapper.find('input[name="_wpnonce"]').val(),
        };
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: latepoint_timestamped_ajaxurl(),
            data: {
                action: latepoint_helper.route_action,
                route_name: $trigger.data('route-name'),
                params: params,
                layout: 'none',
                return_format: 'json'
            },
            success: function (response) {
                $trigger.removeClass('os-loading');
                if (response.status === "success") {
                    latepoint_add_notification(response.message);
                    location.reload();
                } else {
                    latepoint_show_message_inside_element(response.message, $wrapper.find('.latepoint-lightbox-content'), 'error');
                }
            }
        });
        return false;
    });
}
