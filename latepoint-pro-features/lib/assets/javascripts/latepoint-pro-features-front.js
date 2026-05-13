/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

// @codekit-prepend "_messages-front.js";
// @codekit-prepend "_custom-fields-front.js";
// @codekit-prepend "_group-bookings-front.js";
// @codekit-prepend "_recurring-bookings-front.js";
// @codekit-prepend "_social-front.js";
// @codekit-prepend "_timezone-front.js";
// @codekit-prepend "_cloudflare-turnstile-front.js";

// DOCUMENT READY
jQuery(document).ready(function ($) {
    latepoint_init_customer_social_login();
});