/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointRazorpayConnectFront {

    constructor() {
        this.ready();
    }

    ready() {
        jQuery(document).ready(() => {

            // BOOKING FORM — init payment method
            jQuery('body').on('latepoint:initPaymentMethod', '.latepoint-booking-form-element', (e, data) => {
                if (data.payment_method === 'razorpay_checkout') {
                    latepoint_add_action(data.callbacks_list, async () => {
                        return await this.createRazorpayOrder(jQuery(e.currentTarget));
                    });
                }
            });

            // INVOICE PAYMENT FORM — init payment method
            jQuery('body').on('latepoint:initOrderPaymentMethod', '.latepoint-transaction-payment-form', (e, data) => {
                if (data.payment_processor === 'razorpay_connect' && data.payment_method === 'razorpay_checkout') {
                    latepoint_add_action(data.callbacks_list, async () => {
                        return await this.createRazorpayOrderForTransaction(jQuery(e.currentTarget));
                    });
                }
            });

        });
    }

    async createRazorpayOrder($booking_form_element) {
        let formData = latepoint_create_form_data(
            $booking_form_element.find('.latepoint-form'),
            latepoint_helper.razorpay_connect_route_create_order,
            { booking_form_page_url: window.location.href }
        );

        let response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: formData
        });

        if (response.status !== 'success') {
            alert(response.message);
            throw new Error(response.message);
        }

        if (response.amount > 0) {
            let options = Object.assign({}, response.options, {
                handler: (rzpResponse) => {
                    $booking_form_element.find('input[name="cart[payment_token]"]').val(rzpResponse.razorpay_payment_id);
                    latepoint_trigger_next_btn($booking_form_element);
                },
                modal: {
                    ondismiss: () => {
                        $booking_form_element.find('.latepoint-prev-btn').trigger('click');
                    }
                }
            });

            let rzp = new Razorpay(options);
            rzp.open();
        } else {
            return true;
        }
    }

    async createRazorpayOrderForTransaction($transaction_form) {
        let formData = latepoint_create_form_data(
            $transaction_form,
            latepoint_helper.razorpay_connect_route_create_order_for_transaction
        );

        let response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data: formData
        });

        if (response.status !== 'success') {
            alert(response.message);
            throw new Error(response.message);
        }

        if (response.amount > 0) {
            let options = Object.assign({}, response.options, {
                handler: async (rzpResponse) => {
                    $transaction_form.find('input[name="payment_token"]').val(rzpResponse.razorpay_payment_id);
                    return await $transaction_form.trigger('submit');
                },
                modal: {
                    ondismiss: () => {
                        let $access_key = $transaction_form.find('input[name="key"]').val();
                        show_summary_before_payment($access_key);
                    }
                }
            });

            let rzp = new Razorpay(options);
            rzp.open();
        } else {
            return true;
        }
    }
}


if (latepoint_helper.is_razorpay_connect_enabled) window.latepointRazorpayConnectFront = new LatepointRazorpayConnectFront();
