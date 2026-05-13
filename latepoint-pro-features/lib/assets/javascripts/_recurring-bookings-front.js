/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
class LatepointRecurringBookingsFrontFeature {

    // Init
    constructor() {
        this.ready();
    }

    ready() {
        jQuery(document).ready(() => {
            jQuery('body').on('latepoint:initStep:booking__recurring_bookings', '.latepoint-booking-form-element', (e, data) => {
                let $booking_form_element = jQuery(e.target);
                let $step_content = $booking_form_element.find('.latepoint-step-content[data-step-code="booking__recurring_bookings"]');
                this.init_recurrence_rules($step_content.find('.os-recurrence-rules'));

                return this.preview_recurring_bookings($booking_form_element);
            });
            jQuery('body').on('latepoint:initBookingForm', '.latepoint-booking-form-element', (e, data) => {
                let $booking_form_element = jQuery(e.target);
                $booking_form_element.on('click', '.os-recurring-bookings-unfold', (e) => {
                   e.preventDefault();
                   jQuery(e.currentTarget).closest('.cart-item-wrapper').toggleClass('show-all-recurring-bookings');
                });
                return true;
            });
        });
    }

    init_recurrence_rules($recurrence_rules) {
        let $step_content = $recurrence_rules.closest('.latepoint-step-content');
        let $booking_form_element = $recurrence_rules.closest('.latepoint-booking-form-element');

        $recurrence_rules.on('change', 'select, input', (e) => {
            $booking_form_element.find('.os-recurrence-rules input[name="recurrence[rules][changed]"]').val('yes');
            return this.preview_recurring_bookings($booking_form_element);
        });

        $recurrence_rules.on('change', 'select[name="recurrence[rules][repeat_end_operator]"]', (e) => {
            let $select = jQuery(e.currentTarget);
            $recurrence_rules.attr('data-ends', $select.val());
        });

        $recurrence_rules.on('change', 'select[name="recurrence[rules][repeat_unit]"]', (e) => {
            let $select = jQuery(e.currentTarget);
            $recurrence_rules.attr('data-repeat-unit', $select.val());
        });

        $recurrence_rules.on('click', '.os-end-recurrence-datetime-picker', async (e) => {
            let $trigger = jQuery(e.currentTarget);
            $trigger.addClass('os-loading');
            let $booking_form_element = $trigger.closest('.latepoint-booking-form-element');
            $booking_form_element.find('.latepoint-footer').addClass('force-hide');


            let data = new FormData();
            data.append('params', jQuery.param({preselected_day: $trigger.data('preselected-day')}));
            data.append('action', latepoint_helper.route_action);
            data.append('route_name', $trigger.data('route-name'));
            data.append('return_format', 'json');

            try {
                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    processData: false,
                    contentType: false,
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                if (response.status == 'success') {
                    $step_content.addClass('show-datepicker').find('.os-recurrence-datepicker-wrapper').html(response.message);
                    this.init_calendar_navigation($step_content.find('.os-recurrence-datepicker-wrapper .os-dates-and-times-w'));
                    let $day = $step_content.find('.os-recurrence-datepicker-wrapper .os-day[data-date="' + $trigger.data('preselected-day') + '"]');

                    $day.addClass('selected');
                    $trigger.removeClass('os-loading');
                } else {
                    $booking_form_element.find('.latepoint-footer').removeClass('force-hide');
                    throw new Error(response.message);
                }
            } catch (e) {
                console.log(e);
                throw (e);
            }
        });

        $recurrence_rules.on('click', '.os-start-recurrence-datetime-picker', async (e) => {
            e.preventDefault();
            let $trigger = jQuery(e.currentTarget);
            return this.load_datetime_picker($trigger, $trigger.data('start-datetime-utc'));
        });

        $recurrence_rules.on('click', '.os-recurrence-weekdays .weekday', (e) => {
            let $weekday = jQuery(e.currentTarget);
            let total_selected = $weekday.closest('.os-recurrence-weekdays').find('.os-weekday-selected').length;

            if ($weekday.hasClass('os-weekday-selected') && total_selected > 1) {
                $weekday.removeClass('os-weekday-selected');
            } else {
                $weekday.addClass('os-weekday-selected');
            }
            $weekday.closest('.os-recurrence-weekdays').find('input[name="recurrence[rules][repeat_on_weekdays]"]').val($weekday.closest('.os-recurrence-weekdays').find('.os-weekday-selected').map(function () {
                return jQuery(this).data('weekday');
            }).get().join(',')).trigger('change');
            return false;
        });
    }

    async load_datetime_picker($trigger, preselected_datetime_utc){
        $trigger.addClass('os-loading');
        let $booking_form_element = $trigger.closest('.latepoint-booking-form-element');
        $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
        let $booking_form = $booking_form_element.find('form.latepoint-form');
        let $recurring_step_content = $booking_form_element.find('.step-recurring-bookings-w.latepoint-step-content');
        $booking_form_element.find('.latepoint-footer').addClass('force-hide');


        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                processData: false,
                contentType: false,
                url: latepoint_timestamped_ajaxurl(),
                data: latepoint_create_form_data($booking_form, latepoint_helper.pick_datetime_on_calendar_route, { preselected_datetime_utc: preselected_datetime_utc }),
            });
            if (response.status == 'success') {
                $recurring_step_content.addClass('show-datepicker').find('.os-recurrence-datepicker-wrapper').html(response.message);
                this.init_calendar_navigation($recurring_step_content.find('.os-recurrence-datepicker-wrapper .os-dates-and-times-w'));
                let $day = $recurring_step_content.find('.os-recurrence-datepicker-wrapper .os-day.selected');
                latepoint_generate_day_timeslots($day);
                $booking_form_element.removeClass('step-content-loading').addClass('step-content-mid-loading');
                setTimeout(function () {
                    $booking_form_element.removeClass('step-content-mid-loading').addClass('step-content-loaded');
                    $recurring_step_content.find('.time-selector-w')[0].scrollIntoView({block: "nearest", behavior: 'smooth'});
                }, 50);

                $trigger.removeClass('os-loading');
            } else {
                $booking_form_element.find('.latepoint-footer').removeClass('force-hide');
                throw new Error(response.message);
            }
        } catch (e) {
            console.log(e);
            throw (e);
        }
    }

    init_calendar_navigation($calendar) {
        $calendar.find('.os-month-next-btn').on('click', async () => {
            return this.calendar_load_new_month($calendar, 'next');
        });
        $calendar.find('.os-month-prev-btn').on('click', async () => {
            return this.calendar_load_new_month($calendar, 'prev');
        });
    }


    calendar_set_month_label($calendar) {
        $calendar.find('.os-current-month-label .current-year').text($calendar.find('.os-monthly-calendar-days-w.active').data('calendar-year'));
        $calendar.find('.os-current-month-label .current-month').text($calendar.find('.os-monthly-calendar-days-w.active').data('calendar-month-label'));
    }


    async calendar_load_new_month($calendar, direction = 'next') {
        try {
            let $active_month = $calendar.find('.os-monthly-calendar-days-w.active');
            let $month_to_load = (direction === 'next') ? $active_month.next('.os-monthly-calendar-days-w') : $active_month.prev('.os-monthly-calendar-days-w');

            if ($month_to_load.length) {
                $active_month.removeClass('active');
                $month_to_load.addClass('active');
                if(direction === 'next') {
                    $calendar.find('.os-month-prev-btn').removeClass('disabled');
                }
                this.calendar_set_month_label($calendar);
                return true;
            } else {
                let $btn = (direction === 'next') ? $calendar.find('.os-month-next-btn') : $calendar.find('.os-month-prev-btn');
                let next_month_route_name = $btn.data('route');
                $btn.addClass('os-loading');
                let calendar_year = $active_month.data('calendar-year');
                let calendar_month = $active_month.data('calendar-month');
                if(direction === 'next') {
                    if (calendar_month == 12) {
                        calendar_year = calendar_year + 1;
                        calendar_month = 1;
                    } else {
                        calendar_month = calendar_month + 1;
                    }
                }else{
                    if (calendar_month == 1) {
                        calendar_year = calendar_year - 1;
                        calendar_month = 12;
                    } else {
                        calendar_month = calendar_month - 1;
                    }
                }
                let form_data = new FormData($calendar.closest('.latepoint-form')[0]);
                form_data.set('target_date_string', `${calendar_year}-${calendar_month}-1`);
                let params = latepoint_formdata_to_url_encoded_string(form_data);
                let data = {
                    action: latepoint_helper.route_action,
                    route_name: next_month_route_name,
                    params: params,
                    layout: 'none',
                    return_format: 'json'
                }
                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                $btn.removeClass('os-loading');
                if (response.status === "success") {
                    if(direction === 'next'){
                        $calendar.find('.os-months').append(response.message);
                        $active_month.removeClass('active').next('.os-monthly-calendar-days-w').addClass('active');
                    }else{
                        $calendar.find('.os-months').prepend(response.message);
                        $active_month.removeClass('active').prev('.os-monthly-calendar-days-w').addClass('active');
                    }
                    this.calendar_set_month_label($calendar);
                    return true;
                } else {
                    console.log(response.message);
                    return false;
                }

            }
        } catch (e) {
            console.log(e);
            alert('Error:' + e);
            return false;
        }
    }



    init_recurring_bookings_preview($booking_form_element) {
        $booking_form_element.find('.recurring-bookings-preview-continue-btn').on('click', function (e){
            e.preventDefault();
            jQuery(this).closest('.latepoint-w').removeClass('show-summary-on-mobile');
            latepoint_trigger_next_btn($booking_form_element);
            return false;
        });


        $booking_form_element.find('.rb-bookings-info-link').on('click keydown', function(event) {
            event.preventDefault();

            if(event.type === 'keydown' && event.key !== ' ' &&  event.key !== 'Enter') return;
            jQuery(this).closest('.latepoint-w').toggleClass('show-summary-on-mobile');
            return false;
        });

        $booking_form_element.find('.recurring-booking-preview').on('click', '.rbp-time-edit', (e) => {
            e.preventDefault();
            let $trigger = jQuery(e.currentTarget).closest('.recurring-booking-preview');
            $trigger.closest('.recurring-bookings-preview-wrapper').find('.recurring-booking-preview.is-editing').removeClass('is-editing');
            $trigger.addClass('is-editing');
            $trigger.closest('.latepoint-w').removeClass('show-summary-on-mobile');
            return this.load_datetime_picker($trigger, $trigger.data('start-datetime-utc'));
        });

        $booking_form_element.find('.recurring-booking-preview').on('click', '.rbp-checkbox', (e) => {
            let $checkbox = jQuery(e.currentTarget);
            let $preview = $checkbox.closest('.recurring-booking-preview');
            let $recurring_bookings_fields = $checkbox.closest('.latepoint-booking-form-element').find('.os-recurrence-selection-fields-wrapper');
            if ($preview.hasClass('rbp-is-on')) {
                if($checkbox.closest('.recurring-bookings-preview-wrapper').find('.rbp-is-on').length > 1){
                    $preview.removeClass('rbp-is-on').addClass('rbp-is-off');
                    $recurring_bookings_fields.find('input[name="recurrence[overrides]['+$preview.data('stamp')+'][unchecked]"]').val('yes');
                }else{
                    alert('At least one has to be selected');
                }
            } else {
                $preview.removeClass('rbp-is-off').addClass('rbp-is-on');
                $recurring_bookings_fields.find('input[name="recurrence[overrides]['+$preview.data('stamp')+'][unchecked]"]').val('no');
            }

            $checkbox.closest('.latepoint-booking-form-element').find('.os-recurrence-rules input[name="recurrence[rules][changed]"]').val('no');
            return this.preview_recurring_bookings($checkbox.closest('.latepoint-booking-form-element'), true);
        });
    }

    async preview_recurring_bookings($booking_form_element, reload_price_only = false) {
        latepoint_hide_next_btn($booking_form_element);

        $booking_form_element.closest('.latepoint-w').removeClass('latepoint-without-summary').addClass('latepoint-with-summary').addClass('latepoint-summary-is-open');

        let $summary_panel = $booking_form_element.closest('.latepoint-with-summary');
        if (!$summary_panel.length) return;

        if(!reload_price_only) $booking_form_element.find('.latepoint-summary-w').addClass('os-loading');

        $booking_form_element.find('.recurring-bookings-preview-total-wrapper').addClass('os-loading');
        let $booking_form = $booking_form_element.find('.latepoint-form');
        let form_data = new FormData($booking_form[0]);
        let data = {
            action: latepoint_helper.route_action,
            route_name: latepoint_helper.recurring_bookings_preview_route,
            params: latepoint_formdata_to_url_encoded_string(form_data),
            layout: 'none',
            return_format: 'json'
        }

        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            if (response.status === 'success') {
                if(reload_price_only){
                    $booking_form_element.find('.recurring-bookings-preview-total-wrapper').html(response.price_info).removeClass('os-loading');
                    $booking_form_element.find('.os-recurrence-preview-information').html(response.bookings_info);
                }else{
                    $booking_form_element.find('.os-summary-contents').html(response.preview);
                    $booking_form_element.find('.os-recurrence-selection-fields-wrapper').html(response.fields);
                    $booking_form_element.find('.os-recurrence-preview-information').html(response.bookings_info);
                    $booking_form_element.find('.latepoint-summary-w').removeClass('os-loading');
                    this.init_recurring_bookings_preview($booking_form_element);
                }
                latepoint_show_next_btn($booking_form_element);
                return true;
            } else {
                throw new Error(response.message ? response.message : 'Error reloading summary');
            }
        } catch (e) {
            throw e;
        }

    }

    async reload_recurrence_rules($booking_form_element, changed = true) {
        $booking_form_element.find('.os-recurrence-rules input[name="recurrence[rules][changed]"]').val(changed ? 'yes' : 'no');
        $booking_form_element.find('.latepoint-summary-w').addClass('os-loading');
        let $booking_form = $booking_form_element.find('.latepoint-form');
        let form_data = new FormData($booking_form[0]);
        let data = {
            action: latepoint_helper.route_action,
            route_name: $booking_form_element.find('.os-recurrence-rules').data('route-name'),
            params: latepoint_formdata_to_url_encoded_string(form_data),
            layout: 'none',
            return_format: 'json'
        }

        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            if (response.status === 'success') {
                $booking_form_element.find('.os-recurrence-rules').replaceWith(response.message);
                $booking_form_element.find('.os-recurrence-datepicker-wrapper').html('').closest('.step-recurring-bookings-w').removeClass('show-datepicker');
                this.init_recurrence_rules($booking_form_element.find('.os-recurrence-rules'));
                $booking_form_element.find('.latepoint-footer').removeClass('force-hide');
                return await this.preview_recurring_bookings($booking_form_element);
            } else {
                throw new Error(response.message ? response.message : 'Error reloading summary');
            }
        } catch (e) {
            throw e;
        }
    }
}


window.latepointRecurringBookingsFrontFeature = new LatepointRecurringBookingsFrontFeature();