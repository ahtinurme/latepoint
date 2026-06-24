/*
 * Stebby front-end redirect handler for LatePoint.
 *
 * Stebby is a redirect processor. When the customer confirms a booking (or
 * initializes Stebby on the invoice payment form) we POST the form to the
 * request-token route and redirect the browser to the Stebby authorization
 * page. The customer logs in and authorizes; Stebby returns them to our
 * token_return route, which exchanges the reference for a token and charges the
 * booking server-side. There is no inline form to submit here.
 */

(function () {
    'use strict';

    function latepoint_stebby_selected_flow($content) {
        let $checked = $content.find('input[name="lp_stebby_flow"]:checked');
        if ($checked.length) {
            return $checked.val();
        }

        // Account flow disabled - a hidden field carries the only flow (voucher).
        return $content.find('input[name="lp_stebby_flow"]').val() || 'voucher';
    }

    async function latepoint_stebby_redirect_to_payment($form, $content, route_name, extra_params) {
        let params = jQuery.extend({lp_stebby_flow: latepoint_stebby_selected_flow($content)}, extra_params || {});

        let response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: latepoint_create_form_data($form, route_name, params)
        });

        if (response.status !== 'success' || !response.redirect_url) {
            throw new Error(response.message || latepoint_helper.stebby_msg_redirect_error || 'Unable to start the Stebby payment.');
        }

        // Reveal a real fallback link before navigating - a user click is honored
        // even when programmatic navigation is blocked.
        $content.find('.lp-stebby-redirect-notice').addClass('is-redirecting');
        $content.find('.lp-stebby-manual-redirect').attr('href', response.redirect_url).show();

        window.location.href = response.redirect_url;

        // Never resolves on purpose - LatePoint keeps the form in its loading
        // state while the browser navigates away to Stebby.
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
                return await latepoint_stebby_redirect_to_payment(
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
                return await latepoint_stebby_redirect_to_payment(
                    $transaction_payment_form,
                    $content,
                    latepoint_helper.stebby_route_request_token_for_transaction
                );
            });
        });
    });
})();
