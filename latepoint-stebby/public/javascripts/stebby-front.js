/*
 * Stebby (v3) front-end handler for LatePoint.
 *
 * The customer enters their voucher code in the Stebby payment content and clicks
 * "Pay with Stebby voucher". We POST the form (with the code) to the redeem
 * route; the server redeems the Stebby voucher during conversion and returns the
 * URL to continue to the confirmation. No redirect to Stebby, no auto-submit.
 */
(function () {
    'use strict';

    function stebby_voucher_code($content) {
        return ($content.find('.lp-stebby-voucher-input').val() || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    }

    async function stebby_pay($content, $form, route_name) {
        var $btn = $content.find('.lp-stebby-pay-btn');
        var $error = $content.find('.lp-stebby-error');

        if (!stebby_voucher_code($content)) {
            $error.text(latepoint_helper.stebby_msg_enter_voucher_code || 'Please enter your Stebby voucher code.').show();
            return;
        }
        $error.hide();
        $btn.addClass('os-loading').attr('disabled', true);

        try {
            var response = await jQuery.ajax({
                type: 'post',
                dataType: 'json',
                processData: false,
                contentType: false,
                url: latepoint_timestamped_ajaxurl(),
                data: latepoint_create_form_data($form, route_name, {booking_form_page_url: window.location.href})
            });

            if (response.status === 'success' && response.redirect_url) {
                window.location.href = response.redirect_url;
                return;
            }
            $error.text(response.message || 'Unable to complete the Stebby payment.').show();
        } catch (e) {
            $error.text('Unable to complete the Stebby payment.').show();
        }

        $btn.removeClass('os-loading').attr('disabled', false);
    }

    jQuery(document).ready(function () {
        // Booking checkout pay step.
        jQuery('body').on('click', '.lp-stebby-method-content[data-stebby-context="booking"] .lp-stebby-pay-btn', function (e) {
            e.preventDefault();
            var $content = jQuery(this).closest('.lp-stebby-method-content');
            var $form = jQuery(this).closest('.latepoint-booking-form-element').find('.latepoint-form');
            stebby_pay($content, $form, latepoint_helper.stebby_route_request_token);
        });

        // Invoice / order payment form.
        jQuery('body').on('latepoint:initOrderPaymentMethod', '.latepoint-transaction-payment-form', function (e, data) {
            if (data.payment_processor === 'stebby' && data.payment_method === 'stebby') {
                jQuery(e.currentTarget).find('.lp-stebby-method-content[data-stebby-context="transaction"]').show();
            }
        });

        jQuery('body').on('click', '.lp-stebby-method-content[data-stebby-context="transaction"] .lp-stebby-pay-btn', function (e) {
            e.preventDefault();
            var $content = jQuery(this).closest('.lp-stebby-method-content');
            var $form = jQuery(this).closest('.latepoint-transaction-payment-form');
            stebby_pay($content, $form, latepoint_helper.stebby_route_request_token_for_transaction);
        });
    });
})();
