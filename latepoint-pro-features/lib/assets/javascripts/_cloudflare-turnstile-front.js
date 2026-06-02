/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

class LatepointCloudflareTurnstileManager {

    // Init
    constructor() {
        this.ready();
    }


    init_widget($booking_form_element){
        if(latepoint_helper.enable_cloudflare_turnstile === 'on' && $booking_form_element.find('.turnstile-widget').length){
            if(typeof turnstile === 'undefined'){
                this.show_error_message($booking_form_element, 'Verification service failed to load. Please refresh and try again.');
                return;
            }
            $booking_form_element.find('.latepoint-next-btn').css('visibility', 'hidden');
            const $turnstile_widget_wrapper = $booking_form_element.find('.turnstile-widget-wrapper');
            $turnstile_widget_wrapper.addClass('os-loading');
            const $turnstile_widget = $turnstile_widget_wrapper.find('.turnstile-widget');
            turnstile.render($turnstile_widget[0], {
                sitekey: $turnstile_widget.data('sitekey'),
                callback: (token) => this.on_success(token, $turnstile_widget[0], $booking_form_element),
                'error-callback': (error_code) => this.on_error(error_code, $turnstile_widget[0], $booking_form_element),
                'expired-callback': () => this.on_expired($turnstile_widget[0], $booking_form_element),
                'before-interactive-callback': () => this.on_require_interaction($turnstile_widget[0], $booking_form_element)
            });
        }
    }

    ready() {
        jQuery(document).ready(() => {
            jQuery('body').on('latepoint:initStep:verify', '.latepoint-booking-form-element', (e, data) => {
                let $booking_form_element = jQuery(e.target);
                this.init_widget($booking_form_element);
            });
            jQuery('body').on('latepoint:prevStepReInit', '.latepoint-booking-form-element', (e, data) => {
                if(data.current_step == 'verify'){
                    let $booking_form_element = jQuery(e.target);
                    this.reset($booking_form_element);
                }
            });
        });
    }

    on_require_interaction(element, $booking_form_element){
        $booking_form_element.find('.turnstile-widget-wrapper').removeClass('os-loading').addClass('required-interaction');
    }

    on_success(token, element, $booking_form_element) {
        element.setAttribute('data-turnstile-token', token);
        element.classList.add('turnstile-completed');
        $booking_form_element.find('.latepoint-next-btn').css('visibility', 'visible');
        $booking_form_element.find('.turnstile-widget-wrapper').removeClass('os-loading').fadeOut(1200);
    }

    on_error(error_code, element, $booking_form_element) {
        const errorMessages = {
            '110200': 'This domain is not authorized for verification. Please contact support.',
            '110100': 'Invalid site key configuration. Please contact the site administrator.',
            '110110': 'Invalid site key for this domain. Please contact the site administrator.',
            '300010': 'Verification error. Please try again later.',
            '300020': 'Invalid action for this site key.',
            '300030': 'Invalid cData parameter.',
            '600010': 'Invalid response parameter.',
            '600020': 'Invalid or already seen response parameter.',
            '600030': 'Response parameter not found.'
        };
        const message = errorMessages[error_code] || `Turnstile Verification failed. Error: ${error_code}`;

        // Older code for alert
        // $booking_form_element.find('.turnstile-widget-wrapper').show().addClass('os-loading');
        // $booking_form_element.find('.latepoint-next-btn').css('visibility', 'hidden');
        
        alert(message);
        
        // New code to show error message in the widget area
        this.show_error_message($booking_form_element, message);
        
        element.removeAttribute('data-turnstile-token');
        element.classList.remove('turnstile-completed');
    }

    show_error_message($booking_form_element, message) {
        const $wrapper = $booking_form_element.find('.turnstile-widget-wrapper');
        $wrapper.removeClass('os-loading');
        $wrapper.find('.turnstile-widget').hide();
        let $error_msg = $wrapper.find('.turnstile-error-message');
        if(!$error_msg.length){
            $wrapper.append('<div class="turnstile-error-message"></div>');
            $error_msg = $wrapper.find('.turnstile-error-message');
        }
        $error_msg.text(message).show();
        $booking_form_element.find('.latepoint-next-btn').css('visibility', 'hidden');
    }

    on_expired(element, $booking_form_element) {
        $booking_form_element.find('.turnstile-widget-wrapper').show().addClass('os-loading');
        $booking_form_element.find('.latepoint-next-btn').css('visibility', 'hidden');
        element.removeAttribute('data-turnstile-token');
        element.classList.remove('turnstile-completed');
    }

    reset($booking_form_element) {
        $booking_form_element.find('.turnstile-widget-wrapper').show().addClass('os-loading');
        let element = $booking_form_element.find('.turnstile-widget')[0];
        if (element) {
            element.removeAttribute('data-turnstile-token');
            element.classList.remove('turnstile-completed');
            turnstile.reset(element);
        }
    }
}


window.latepointCloudflareTurnstileManager = new LatepointCloudflareTurnstileManager();