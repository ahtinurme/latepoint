function latepoint_init_facebook_login($wrapper) {

    let is_booking_form = $wrapper.hasClass('latepoint-booking-form-element');

    if (!$wrapper.find('#facebook-signin-btn').length) return;
    $wrapper.find('#facebook-signin-btn').on('click', function () {
        FB.login(function (response) {
            if (response.status === 'connected' && response.authResponse) {
                var params = {
                    token: response.authResponse.accessToken,
                    nonce: latepoint_helper.social_login_nonce
                };
                var data = {
                    action: latepoint_helper.route_action,
                    route_name: $wrapper.find('#facebook-signin-btn').data('login-action'),
                    params: jQuery.param(params),
                    layout: 'none',
                    return_format: 'json'
                };
                if(is_booking_form) latepoint_step_content_change_start($wrapper);
                jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data,
                    success: function (data) {
                        if(is_booking_form){
                            // booking form
                            if (data.status === "success") {
                                $wrapper.find('input[name="auth[action]"]').val('social-login-facebook');
                                latepoint_reload_step($wrapper);
                            } else {
                                latepoint_show_message_inside_element(data.message, $wrapper.find('.os-step-existing-customer-login-w '));
                                if(is_booking_form) latepoint_step_content_change_end(false, $wrapper);
                            }
                        }else{
                            // login form
                            if (data.status === "success") {
                                location.reload();
                            }else{
                                latepoint_show_message_inside_element(data.message, $wrapper);
                            }
                        }
                    }
                });
            } else {

            }
        }, {scope: 'public_profile,email'});
    });
}

function latepoint_process_google_login(response, $booking_form_element = false) {

    var params = {
        token: response.credential,
        nonce: latepoint_helper.social_login_nonce
    };
    var data = {
        action: latepoint_helper.route_action,
        route_name: latepoint_helper.social_login_google_route,
        params: jQuery.param(params),
        layout: 'none',
        return_format: 'json'
    };
    if ($booking_form_element) latepoint_step_content_change_start($booking_form_element);
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: latepoint_timestamped_ajaxurl(),
        data: data,
        success: function (data) {
            if (data.status === "success") {
                if ($booking_form_element) {
                    $booking_form_element.find('input[name="auth[action]"]').val('social-login-google');
                    latepoint_reload_step($booking_form_element);
                } else {
                    location.reload();
                }
            } else {
                latepoint_show_message_inside_element(data.message, $booking_form_element.find('.os-step-existing-customer-login-w '));
                latepoint_step_content_change_end(false, $booking_form_element);
            }
        }
    });
}
async function latepoint_init_google_login($wrapper) {

    if (!$wrapper.find('#google-signin-btn').length || typeof google === 'undefined') return;

    let is_booking_form = $wrapper.hasClass('latepoint-booking-form-element');

    if(!window.latepoint_is_google_initialized){
        google.accounts.id.initialize({
            client_id: latepoint_helper.social_login_google_client_id,
            callback: (response) => {
                // response.credential contains the JWT ID token!
                if (is_booking_form) {
                    latepoint_process_google_login(response, $wrapper);
                } else {
                    latepoint_process_google_login(response);
                }
            }
        });
        window.latepoint_is_google_initialized = true;
    }

    $wrapper.find('#google-signin-btn').off('click.google-signin').on('click.google-signin', function(e) {
        e.preventDefault();

        // Create a temporary hidden button element for Google to render
        const tempButton = document.createElement('div');
        tempButton.style.display = 'none';
        document.body.appendChild(tempButton);

        // Render the Google button but hide it
        google.accounts.id.renderButton(tempButton, {
            theme: "outline",
            size: "medium"
        });

        // Trigger click on the hidden Google button
        setTimeout(() => {
            const googleBtn = tempButton.querySelector('[role="button"]');
            if (googleBtn) {
                googleBtn.click();
            }
            // Clean up
            document.body.removeChild(tempButton);
        }, 100);
    });
}


function latepoint_load_facebook_scripts(){
    if(latepoint_helper.social_login_facebook_app_id){
        window.fbAsyncInit = function() {
          FB.init({
            appId      : latepoint_helper.social_login_facebook_app_id,
            cookie     : true,
            xfbml      : true,
            version    : 'v9.0'
          });

          FB.AppEvents.logPageView();

        };

        (function(d, s, id){
           var js, fjs = d.getElementsByTagName(s)[0];
           if (d.getElementById(id)) {return;}
           js = d.createElement(s); js.id = id;
           js.src = 'https://connect.facebook.net/en_US/sdk.js';
           fjs.parentNode.insertBefore(js, fjs);
         }(document, 'script', 'facebook-jssdk'));
    }
}

function latepoint_init_customer_social_login() {

    if (jQuery('.latepoint-login-form-w').length){
        jQuery('.latepoint-login-form-w').each(function(){
            latepoint_init_facebook_login(jQuery(this));
            latepoint_init_google_login(jQuery(this));
        });
    }
}


jQuery(document).ready(() => {
    latepoint_load_facebook_scripts();
    jQuery('body').on('latepoint:initStep:customer', '.latepoint-booking-form-element', (e, data) => {
        latepoint_init_facebook_login(jQuery(e.target));
        latepoint_init_google_login(jQuery(e.target));
    });
});