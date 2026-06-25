/*
 * Stebby (v3) front-end helper for LatePoint.
 *
 * The customer enters their ID code in the Stebby payment method content and
 * confirms the booking normally - the ID code is submitted with the form and a
 * Stebby ticket is redeemed server-side during conversion. There is no redirect
 * and no auto-submit; LatePoint reveals the booking content on its own. For the
 * invoice (transaction) payment form we just make the content visible.
 */
(function () {
    'use strict';

    jQuery(document).ready(function () {
        jQuery('body').on('latepoint:initOrderPaymentMethod', '.latepoint-transaction-payment-form', function (e, data) {
            if (data.payment_processor !== 'stebby' || data.payment_method !== 'stebby') {
                return;
            }
            jQuery(e.currentTarget).find('.lp-stebby-method-content[data-stebby-context="transaction"]').show();
        });
    });
})();
