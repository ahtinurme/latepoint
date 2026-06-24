/*
 * Stebby (v3) front-end handler for LatePoint.
 *
 * The customer enters their ID code in the Stebby payment method content. On
 * "Confirm booking" we POST the form (which includes the ID code) to the
 * request-token route; the server redeems a Stebby ticket and returns the URL to
 * continue the booking to its confirmation. There is no redirect to Stebby.
 */

(function () {
    'use strict';

    async function latepoint_stebby_submit($form, $content, route_name, extra_params) {
        let response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: latepoint_create_form_data($form, route_name, extra_params || {})
        });

        if (response.status !== 'success' || !response.redirect_url) {
            throw new Error(response.message || latepoint_helper.stebby_msg_redirect_error || 'Unable to complete the Stebby payment.');
        }

        window.location.href = response.redirect_url;

        // Never resolves on purpose - LatePoint keeps the form in its loading
        // state while the browser navigates to the booking confirmation.
        return new Promise(function () {});
    }

    jQuery(document).ready(function () {
        jQuery('body').on('latepoint:initPaymentMethod', '.latepoint-booking-form-element', function (e, data) {
            if (data.payment_method !== 'stebby') {
                return;
            }

            let $booking_form_element = jQuery(e.currentTarget);
            let $content = $booking_form_element.find('.lp-stebby-method-content[data-stebby-context="booking"]');

            latepoint_add_action(data.callbacks_list, async function () {
                return await latepoint_stebby_submit(
                    $booking_form_element.find('.latepoint-form'),
                    $content,
                    latepoint_helper.stebby_route_request_token,
                    {booking_form_page_url: window.location.href}
                );
            });
        });

        jQuery('body').on('latepoint:initOrderPaymentMethod', '.latepoint-transaction-payment-form', function (e, data) {
            if (data.payment_processor !== 'stebby' || data.payment_method !== 'stebby') {
                return;
            }

            let $transaction_payment_form = jQuery(e.currentTarget);
            let $content = $transaction_payment_form.find('.lp-stebby-method-content[data-stebby-context="transaction"]');
            $content.show();

            latepoint_add_action(data.callbacks_list, async function () {
                return await latepoint_stebby_submit(
                    $transaction_payment_form,
                    $content,
                    latepoint_helper.stebby_route_request_token_for_transaction
                );
            });
        });
    });
})();
