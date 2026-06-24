/*
 * Moves the "pay with package" option tiles into the LatePoint payment-method
 * grid so they sit next to the payment processors (EveryPay/Stebby) instead of
 * in a separate block above. The tiles are rendered hidden by the server; we
 * relocate them once the payment selection step has rendered.
 */
(function () {
    'use strict';

    function relocate($form) {
        var $tiles = $form.find('.lp-prepayment-tiles');
        if (!$tiles.length) {
            return;
        }
        var $grid = $form.find('.lp-options-grid').first();
        if (!$grid.length) {
            return;
        }
        $grid.prepend($tiles.children('.lp-prepayment-tile'));
        $tiles.remove();
    }

    jQuery(function ($) {
        $('body').on('latepoint:initStep', '.latepoint-booking-form-element', function (e, data) {
            var step_code = data && data.step_code;
            if (step_code !== 'payment__methods' && step_code !== 'payment__processors') {
                return;
            }
            relocate($(e.currentTarget));
        });
    });
})();
