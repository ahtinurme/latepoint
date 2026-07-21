/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointPaypalConnectFront {

    constructor() {
        this.sdkInstance = null;
        this.sdkInitPromise = null;
        this.paypalOrderId = null;
        this.continueOrderIntentURL = null;
        this.continueTransactionIntentURL = null;
        this.ready();
    }

    ready() {
        jQuery(document).ready(() => {

            // INITIALIZE PAYMENT METHOD on booking form
            jQuery('body').on('latepoint:initPaymentMethod', '.latepoint-booking-form-element', (e, data) => {
				if (data.payment_method === 'paypal_buttons') {
                    latepoint_add_action(data.callbacks_list, async () => {
                        return await this.initPaypalPayment(jQuery(e.currentTarget), data.payment_method, false);
                    });
                }
            });

            // INITIALIZE PAYMENT METHOD on order payment form
            jQuery('body').on('latepoint:initOrderPaymentMethod', '.latepoint-transaction-payment-form', (e, data) => {
				if (data.payment_processor === 'paypal_ppcp' && data.payment_method === 'paypal_buttons') {
                    latepoint_add_action(data.callbacks_list, async () => {
                        return await this.initPaypalPayment(jQuery(e.currentTarget), data.payment_method, true);
                    });
                }
            });
        });
    }

    async ensureSdkInitialized() {
        if (this.sdkInstance) return this.sdkInstance;
        if (this.sdkInitPromise) return this.sdkInitPromise;

        const config = latepoint_helper.paypal_connect_config;

		const ppOptions = {
            clientId:             config.clientId,
            merchantId:           config.merchantId,
            partnerAttributionId: config.partnerAttributionId,
            components:           config.components,
            pageType:             'checkout',
            clientMetadataId:     crypto.randomUUID(),
        };

		if (config.locale) {
			ppOptions.locale = config.locale;
		}

        this.sdkInitPromise = window.paypal.createInstance(ppOptions);

        try {
            this.sdkInstance = await this.sdkInitPromise;
        } catch (err) {
            this.sdkInitPromise = null;
            throw err;
        }
        return this.sdkInstance;
    }

    _buildEligibilityOptions($element) {
        const config = latepoint_helper.paypal_connect_config;
        const opts = { currencyCode: config.currency };
        const amount = $element.find('.lp-payment-method-content[data-charge-amount]').data('charge-amount');
        if (amount) opts.amount = String(amount);
        return opts;
    }

    async initPaypalPayment($element, paymentMethod, isTransaction) {
        if (isTransaction) {
            await this.initPaypalButtonsForTransaction($element);
        } else {
            await this.initPaypalButtons($element, paymentMethod);
            await this.initCardFields($element);
        }
    }

    // === BOOKING FORM: PAYPAL / VENMO / PAY LATER BUTTONS ===

    async initPaypalButtons($booking_form_element, payment_method) {
        const $container = $booking_form_element.find('.lp-paypal-connect-button-container');
        if (!$container.length) return;

        const sdk = await this.ensureSdkInitialized();
        const config = latepoint_helper.paypal_connect_config;

        const eligibility = await sdk.findEligibleMethods(this._buildEligibilityOptions($booking_form_element));

        const self = this;

        const makeCallbacks = (sourceType) => ({
            onApprove: async (data) => {
                $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
                try {
                    const response = await self.captureOrder(data.orderId);
                    if (response.status === 'success') {
                        sessionStorage.setItem('lp_paypal_payment_source', response.payment_source_type || sourceType);
                        $booking_form_element.find('input[name="cart[payment_token]"]').val(response.capture_id);
                        latepoint_submit_booking_form($booking_form_element.find('.latepoint-form'));
                    } else {
                        $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                        latepoint_show_message_inside_element(response.message, $booking_form_element.find('.latepoint-body'), 'error');
                    }
                } catch (err) {
                    $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                    latepoint_show_message_inside_element(err.message || 'Payment error', $booking_form_element.find('.latepoint-body'), 'error');
                }
            },
            onCancel: () => {
                $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
            },
            onError: (err) => {
                console.error('PayPal error:', err);
                $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                const errorMessage = (err && err.message) || 'An error occurred with PayPal. Please try again or use a different payment method.';
                latepoint_show_message_inside_element(errorMessage, $booking_form_element.find('.latepoint-body'), 'error');
            },
        });

        // PayPal button
        if (eligibility.isEligible('paypal')) {
            const paypalBtn = $container.find('#latepoint-paypal-button')[0];
            if (paypalBtn) {
                paypalBtn.hidden = false;
                const session = sdk.createPayPalOneTimePaymentSession(makeCallbacks('paypal'));
                paypalBtn.addEventListener('click', async () => {
                    $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
                    try {
                        await session.start(
                            { presentationMode: 'auto' },
                            self.createOrderForBooking($booking_form_element, payment_method)
                        );
                    } catch (err) {
                        $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                        latepoint_show_message_inside_element(
                            (err && err.message) || 'An error occurred with PayPal. Please try again.',
                            $booking_form_element.find('.latepoint-body'),
                            'error'
                        );
                    }
                });
            }
        }

        // Venmo button
        if (config.enableVenmo && eligibility.isEligible('venmo')) {
            const venmoBtn = $container.find('#latepoint-venmo-button')[0];
            if (venmoBtn) {
                venmoBtn.hidden = false;
                const session = sdk.createVenmoOneTimePaymentSession(makeCallbacks('venmo'));
                venmoBtn.addEventListener('click', async () => {
                    $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
                    try {
                        await session.start(
                            { presentationMode: 'auto' },
                            self.createOrderForBooking($booking_form_element, payment_method)
                        );
                    } catch (err) {
                        $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                        latepoint_show_message_inside_element(
                            (err && err.message) || 'An error occurred with PayPal. Please try again.',
                            $booking_form_element.find('.latepoint-body'),
                            'error'
                        );
                    }
                });
            }
        }
		console.log('Eligibility for Pay Later:', eligibility.isEligible('paylater'));
        // Pay Later button
        if (config.enablePayLater && eligibility.isEligible('paylater')) {
            const paylaterDetails = eligibility.getDetails('paylater');
            const paylaterBtn = $container.find('#latepoint-paylater-button')[0];
            if (paylaterBtn) {
                if (paylaterDetails) {
                    paylaterBtn.productCode = paylaterDetails.productCode;
                    paylaterBtn.countryCode = paylaterDetails.countryCode;
                }
                paylaterBtn.hidden = false;
                const session = sdk.createPayLaterOneTimePaymentSession(makeCallbacks('paylater'));
                paylaterBtn.addEventListener('click', async () => {
                    $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
                    try {
                        await session.start(
                            { presentationMode: 'auto' },
                            self.createOrderForBooking($booking_form_element, payment_method)
                        );
                    } catch (err) {
                        $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                        latepoint_show_message_inside_element(
                            (err && err.message) || 'An error occurred with PayPal. Please try again.',
                            $booking_form_element.find('.latepoint-body'),
                            'error'
                        );
                    }
                });
            }
        }
    }

    async createOrderForBooking($element, paymentMethod) {
        const data = latepoint_create_form_data($element.find('.latepoint-form'), latepoint_helper.paypal_connect_route_create_order, {booking_form_page_url: window.location.href});

        const response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data
        });

        if (response.status !== 'success') {
            throw new Error(response.message);
        }

        this.paypalOrderId = response.paypal_order_id;
        this.continueOrderIntentURL = response.continue_order_intent_url;
        return { orderId: response.paypal_order_id };
    }

    // === BOOKING FORM: CARD FIELDS (ACDC) ===

    async initCardFields($booking_form_element) {
        const $container = $booking_form_element.find('.lp-paypal-card-fields-container');
        if (!$container.length) return;

        const config = latepoint_helper.paypal_connect_config;
        if (!config.acdcEligible) {
            $container.hide();
            $booking_form_element.find('.lp-paypal-card-separator').hide();
            return;
        }

        const sdk = await this.ensureSdkInitialized();

        const eligibility = await sdk.findEligibleMethods(this._buildEligibilityOptions($booking_form_element));

        if (!eligibility.isEligible('advanced_cards')) {
            $container.hide();
            $booking_form_element.find('.lp-paypal-card-separator').hide();
            return;
        }

        const cardSession = sdk.createCardFieldsOneTimePaymentSession();

        const numberField = cardSession.createCardFieldsComponent({
            type: 'number',
            placeholder: 'Card number',
        });
        const expiryField = cardSession.createCardFieldsComponent({
            type: 'expiry',
            placeholder: 'MM / YY',
        });
        const cvvField = cardSession.createCardFieldsComponent({
            type: 'cvv',
            placeholder: 'CVV',
        });

        const numberContainer = $container.find('.lp-card-number-field')[0];
        const expiryContainer = $container.find('.lp-card-expiry-field')[0];
        const cvvContainer = $container.find('.lp-card-cvv-field')[0];

        if (numberContainer) numberContainer.appendChild(numberField);
        if (expiryContainer) expiryContainer.appendChild(expiryField);
        if (cvvContainer) cvvContainer.appendChild(cvvField);

        const self = this;

        $container.find('.lp-card-submit-btn').on('click', async () => {
            $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
            try {
                const orderId = await self.createOrderForCardFields($booking_form_element);

                const { data, state } = await cardSession.submit(orderId);

                switch (state) {
                    case 'succeeded': {
                        const { orderId: approvedOrderId, ...liabilityShift } = data;
                        console.log('3DS liability shift:', liabilityShift);

                        if (liabilityShift.liabilityShift === 'NO') {
                            throw new Error('Card authentication failed. Please try a different card or payment method.');
                        }

                        const captureResponse = await self.captureOrder(approvedOrderId);
                        if (captureResponse.status === 'success') {
                            sessionStorage.setItem('lp_paypal_payment_source', 'card');
                            $booking_form_element.find('input[name="cart[payment_token]"]').val(captureResponse.capture_id);
                            latepoint_submit_booking_form($booking_form_element.find('.latepoint-form'));
                        } else {
                            throw new Error(captureResponse.message || 'Capture failed');
                        }
                        break;
                    }
                    case 'canceled': {
                        $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                        break;
                    }
                    case 'failed': {
                        throw new Error((data && data.message) || 'Card payment failed. Please try again.');
                    }
                    default: {
                        console.warn('Unhandled card submit state:', state, data);
                        throw new Error('An unexpected error occurred. Please try again.');
                    }
                }
            } catch (err) {
                console.error('Card payment error:', err);
                $booking_form_element.removeClass('step-content-loading').addClass('step-content-loaded');
                const errorMessage = (err && err.message) || 'An error occurred. Please try again or use a different payment method.';
                latepoint_show_message_inside_element(errorMessage, $booking_form_element.find('.latepoint-body'), 'error');
            }
        });
    }

    async createOrderForCardFields($element) {
        const $methodInput = $element.find('input[name="cart[payment_method]"]');
        const originalMethod = $methodInput.val();
        $methodInput.val('paypal_card');

        const data = latepoint_create_form_data(
            $element.find('.latepoint-form'),
            latepoint_helper.paypal_connect_route_create_order,
            {booking_form_page_url: window.location.href}
        );

        $methodInput.val(originalMethod);

        const response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data
        });

        if (response.status !== 'success') {
            throw new Error(response.message);
        }

        this.paypalOrderId = response.paypal_order_id;
        this.continueOrderIntentURL = response.continue_order_intent_url;
        return response.paypal_order_id;
    }

    // === TRANSACTION PAYMENT: BUTTONS ONLY ===

    async initPaypalButtonsForTransaction($transaction_intent_form) {
        const $container = $transaction_intent_form.find('.lp-paypal-connect-button-container');
        if (!$container.length) return;
        $transaction_intent_form.find('.latepoint-lightbox-footer').hide();
        $transaction_intent_form.find('.clean-layout-content-footer').hide();

        const sdk = await this.ensureSdkInitialized();
        const config = latepoint_helper.paypal_connect_config;

        const eligibility = await sdk.findEligibleMethods(this._buildEligibilityOptions($transaction_intent_form));

        const self = this;

        const makeCallbacks = (sourceType) => ({
            onApprove: async (data) => {
                try {
                    const response = await self.captureOrder(data.orderId);
                    if (response.status === 'success') {
                        $transaction_intent_form.find('input[name="payment_token"]').val(response.capture_id);
                        $transaction_intent_form.submit();
                    } else {
                        latepoint_show_message_inside_element(response.message, $transaction_intent_form, 'error');
                    }
                } catch (err) {
                    latepoint_show_message_inside_element(err.message || 'Payment error', $transaction_intent_form, 'error');
                }
            },
            onCancel: () => {},
            onError: (err) => {
                console.error('PayPal transaction error:', err);
                const errorMessage = (err && err.message) || 'An error occurred with PayPal. Please try again or use a different payment method.';
                latepoint_show_message_inside_element(errorMessage, $transaction_intent_form, 'error');
            },
        });

        // PayPal button
        if (eligibility.isEligible('paypal')) {
            const paypalBtn = $container.find('#latepoint-paypal-button')[0];
            if (paypalBtn) {
                paypalBtn.hidden = false;
                const session = sdk.createPayPalOneTimePaymentSession(makeCallbacks('paypal'));
                paypalBtn.addEventListener('click', async () => {
                    try {
                        await session.start(
                            { presentationMode: 'auto' },
                            self.createOrderForTransaction($transaction_intent_form)
                        );
                    } catch (err) {
                        latepoint_show_message_inside_element(
                            (err && err.message) || 'An error occurred with PayPal. Please try again.',
                            $transaction_intent_form,
                            'error'
                        );
                    }
                });
            }
        }

        // Venmo button
        if (config.enableVenmo && eligibility.isEligible('venmo')) {
            const venmoBtn = $container.find('#latepoint-venmo-button')[0];
            if (venmoBtn) {
                venmoBtn.hidden = false;
                const session = sdk.createVenmoOneTimePaymentSession(makeCallbacks('venmo'));
                venmoBtn.addEventListener('click', async () => {
                    try {
                        await session.start(
                            { presentationMode: 'auto' },
                            self.createOrderForTransaction($transaction_intent_form)
                        );
                    } catch (err) {
                        latepoint_show_message_inside_element(
                            (err && err.message) || 'An error occurred with PayPal. Please try again.',
                            $transaction_intent_form,
                            'error'
                        );
                    }
                });
            }
        }

        // Pay Later button
        if (config.enablePayLater && eligibility.isEligible('paylater')) {
            const paylaterDetails = eligibility.getDetails('paylater');
            const paylaterBtn = $container.find('#latepoint-paylater-button')[0];
            if (paylaterBtn) {
                if (paylaterDetails) {
                    paylaterBtn.productCode = paylaterDetails.productCode;
                    paylaterBtn.countryCode = paylaterDetails.countryCode;
                }
                paylaterBtn.hidden = false;
                const session = sdk.createPayLaterOneTimePaymentSession(makeCallbacks('paylater'));
                paylaterBtn.addEventListener('click', async () => {
                    try {
                        await session.start(
                            { presentationMode: 'auto' },
                            self.createOrderForTransaction($transaction_intent_form)
                        );
                    } catch (err) {
                        latepoint_show_message_inside_element(
                            (err && err.message) || 'An error occurred with PayPal. Please try again.',
                            $transaction_intent_form,
                            'error'
                        );
                    }
                });
            }
        }

        // Card fields (ACDC)
        const $cardContainer = $transaction_intent_form.find('.lp-paypal-card-fields-container');
        if ($cardContainer.length && config.acdcEligible && eligibility.isEligible('advanced_cards')) {
            const cardSession = sdk.createCardFieldsOneTimePaymentSession();

            const numberField = cardSession.createCardFieldsComponent({ type: 'number', placeholder: 'Card number' });
            const expiryField = cardSession.createCardFieldsComponent({ type: 'expiry', placeholder: 'MM / YY' });
            const cvvField = cardSession.createCardFieldsComponent({ type: 'cvv', placeholder: 'CVV' });

            const numberEl = $cardContainer.find('.lp-card-number-field')[0];
            const expiryEl = $cardContainer.find('.lp-card-expiry-field')[0];
            const cvvEl = $cardContainer.find('.lp-card-cvv-field')[0];

            if (numberEl) numberEl.appendChild(numberField);
            if (expiryEl) expiryEl.appendChild(expiryField);
            if (cvvEl) cvvEl.appendChild(cvvField);

            $cardContainer.find('.lp-card-submit-btn').on('click', async () => {
                $transaction_intent_form.addClass('os-loading');
                try {
                    const createResponse = await self.createOrderForTransaction($transaction_intent_form);
                    const orderId = createResponse.orderId;

                    const { data, state } = await cardSession.submit(orderId);

                    switch (state) {
                        case 'succeeded': {
                            const { orderId: approvedOrderId, ...liabilityShift } = data;
                            if (liabilityShift.liabilityShift === 'NO') {
                                throw new Error('Card authentication failed. Please try a different card or payment method.');
                            }
                            const captureResponse = await self.captureOrder(approvedOrderId);
							if (captureResponse.status === 'success') {
                                $transaction_intent_form.find('input[name="payment_token"]').val(captureResponse.capture_id);
                                $transaction_intent_form.removeClass('os-loading');
                                $transaction_intent_form.submit();
                            } else {
                                throw new Error(captureResponse.message || 'Capture failed');
                            }
                            break;
                        }
                        case 'canceled': {
                            $transaction_intent_form.removeClass('os-loading');
                            break;
                        }
                        case 'failed': {
                            throw new Error((data && data.message) || 'Card payment failed. Please try again.');
                        }
                        default: {
                            throw new Error('An unexpected error occurred. Please try again.');
                        }
                    }
                } catch (err) {
                    console.error('Card payment error:', err);
                    $transaction_intent_form.removeClass('os-loading');
                    latepoint_show_message_inside_element(
                        (err && err.message) || 'An error occurred. Please try again.',
                        $transaction_intent_form,
                        'error'
                    );
                }
            });
        } else if ($cardContainer.length) {
            $cardContainer.hide();
            $transaction_intent_form.find('.lp-paypal-card-separator').hide();
        }
    }

    async createOrderForTransaction($form) {
        const data = latepoint_create_form_data($form, latepoint_helper.paypal_connect_route_create_order_for_transaction);

        const response = await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            processData: false,
            contentType: false,
            url: latepoint_timestamped_ajaxurl(),
            data
        });

        if (response.status !== 'success') {
            throw new Error(response.message);
        }

        this.paypalOrderId = response.paypal_order_id;
        this.continueTransactionIntentURL = response.continue_transaction_intent_url;
        return { orderId: response.paypal_order_id };
    }

    // === SHARED: CAPTURE ORDER ===

    async captureOrder(paypalOrderId) {
        return await jQuery.ajax({
            type: 'post',
            dataType: 'json',
            url: latepoint_timestamped_ajaxurl(),
            data: {
                action: 'latepoint_route_call',
                route_name: latepoint_helper.paypal_connect_route_capture_order,
                params: {paypal_order_id: paypalOrderId},
                layout: 'none',
                return_format: 'json'
            }
        });
    }
}


if (latepoint_helper.is_paypal_connect_enabled) window.latepointPaypalConnectFront = new LatepointPaypalConnectFront();
