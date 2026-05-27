/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointRazorpayConnectAdmin {

    constructor() {
        this.ready();
    }

    ready() {
        jQuery(document).ready(() => {

            // Handle "Start Connecting" / "Continue Setup" click
            jQuery('.razorpay-connect-status-wrapper').on('click', '.payment-start-connecting', function () {
                let $link = jQuery(this);
                $link.addClass('os-loading');
                var data = {
                    action: 'latepoint_route_call',
                    route_name: $link.data('route-name'),
                    params: { env: $link.data('env') },
                    layout: 'none',
                    return_format: 'json'
                };
                jQuery.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: latepoint_timestamped_ajaxurl(),
                    data: data,
                    success: (data) => {
                        window.location.href = data.url;
                    }
                });
                return false;
            });

            // Auto-poll connection status on page load
            if (jQuery('.razorpay-connect-status-wrapper').length) {
                jQuery('.razorpay-connect-status-wrapper').each((index, elem) => {
                    let $wrapper = jQuery(elem);
                    var data = {
                        action: 'latepoint_route_call',
                        route_name: $wrapper.data('route-name'),
                        params: { env: $wrapper.data('env') },
                        layout: 'none',
                        return_format: 'json'
                    };
                    jQuery.ajax({
                        type: 'post',
                        dataType: 'json',
                        url: latepoint_timestamped_ajaxurl(),
                        data: data,
                        success: (data) => {
                            this.reload_connect_status_wrapper($wrapper, data);
                        }
                    });
                });
            }
        });
    }

    reload_connect_status_wrapper($elem, data) {
        if (data.status === 'success') {
            if ($elem.hasClass('razorpay-connect-status-wrapper')) {
                $elem.html(data.message);
            } else {
                $elem.closest('.razorpay-connect-status-wrapper').html(data.message);
            }
        } else {
            alert(data.message);
        }
    }
}


window.latepointRazorpayConnectAdmin = new LatepointRazorpayConnectAdmin();
