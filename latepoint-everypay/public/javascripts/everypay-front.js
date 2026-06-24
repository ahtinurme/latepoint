/*
 * EveryPay front-end redirect handler for LatePoint.
 *
 * When a customer reaches the EveryPay payment step (or initializes EveryPay on
 * the invoice payment form), the booking form is POSTed to the EveryPay
 * create-payment route and the browser is redirected to the EveryPay hosted
 * payment page automatically - there is no card form to fill and therefore no
 * Submit button to click.
 */

(function () {
    'use strict';

    async function latepoint_everypay_redirect_to_payment($form, route_name, extra_params) {
        let response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: latepoint_create_form_data($form, route_name, extra_params)
        });

        if (response.status !== 'success' || !response.payment_link) {
            throw new Error(response.message || 'Unable to start the EveryPay payment.');
        }

        // Reveal a real, clickable fallback link before attempting the automatic
        // redirect. A user click is honored even when programmatic navigation is
        // blocked (redirect/popup blockers, slow networks, restored sessions).
        $form.find('.lp-everypay-redirect-notice').addClass('is-redirecting');
        $form.find('.lp-everypay-manual-redirect').attr('href', response.payment_link).show();

        window.location.href = response.payment_link;

        // Never resolves on purpose - LatePoint keeps the form in its loading
        // state while the browser navigates away to the EveryPay hosted page.
        return new Promise(function () {});
    }

    jQuery(document).ready(function () {
        jQuery('body').on('latepoint:initPaymentMethod', '.latepoint-booking-form-element', function (e, data) {
            if (data.payment_method !== 'everypay') {
                return;
            }

            let $booking_form_element = jQuery(e.currentTarget);

            latepoint_add_action(data.callbacks_list, async function () {
                return await latepoint_everypay_redirect_to_payment(
                    $booking_form_element.find('.latepoint-form'),
                    latepoint_helper.everypay_route_create_payment,
                    {booking_form_page_url: window.location.href}
                );
            });
        });

        jQuery('body').on('latepoint:initOrderPaymentMethod', '.latepoint-transaction-payment-form', function (e, data) {
            if (data.payment_processor !== 'everypay' || data.payment_method !== 'everypay') {
                return;
            }

            let $transaction_payment_form = jQuery(e.currentTarget);

            latepoint_add_action(data.callbacks_list, async function () {
                return await latepoint_everypay_redirect_to_payment(
                    $transaction_payment_form,
                    latepoint_helper.everypay_route_create_payment_for_transaction
                );
            });
        });
    });
})();
