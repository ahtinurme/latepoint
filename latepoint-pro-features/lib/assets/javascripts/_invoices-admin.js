/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointInvoicesAdminFeature {

    // Init
    constructor() {
        this.ready();
    }

    ready() {
        jQuery(() => {
            jQuery('body').on('latepoint:initOrderEditForm', (e) => {
                let $order_form = jQuery(e.currentTarget);
                return this.init_invoices_on_order_form($order_form);
            });
        });
    }

    async change_invoice_status(){
        let $invoice_document = jQuery('.invoice-document');
        
        $invoice_document.addClass('os-loading');
        
        let invoice_id = $invoice_document.data('invoice-id');
        let route = $invoice_document.data('route');
        let data = {
            action: 'latepoint_route_call',
            route_name: route,
            params: {
                invoice_id: invoice_id,
                status: $invoice_document.find('.invoice-change-status-selector').val(),
                _wpnonce: $invoice_document.find('input[name="_wpnonce_status"]').val()
            },
            return_format: 'json'
        }
        try {

            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            $invoice_document.removeClass('os-loading');
            if (response.status === "success") {
                jQuery('.invoice-document').replaceWith(response.message);
                this.init_invoice_preview();
                this.reload_invoice_tile(invoice_id);
                return true;
            } else {
                alert(response.message, 'error');
                throw new Error(response.message);
            }
        } catch (e) {
            throw e;
        }
    }

    init_quick_invoice_settings_form(){
        jQuery('.invoices-info-w').addClass('setting-new-invoice');
        jQuery('.invoice-settings-wrapper')[0].scrollIntoView();
        latepoint_init_input_masks(jQuery('.invoice-settings-wrapper'));
        latepoint_init_daterangepicker(jQuery('.invoice-settings-wrapper .os-date-range-picker'));

        jQuery('.invoice-settings-close').on('click', (e) => {
            jQuery('.quick-invoice-settings-form-wrapper').html('');
            jQuery('.invoices-info-w').removeClass('setting-new-invoice');
            return false;
        });

        jQuery('.create-invoice-button').on('click', async (e) => {
            e.preventDefault();
            let $button = jQuery(e.currentTarget);
            if($button.hasClass('os-loading')) return false;

            let $invoice_settings_form = jQuery('.invoice-settings-wrapper');
            let data = new FormData();
            data.append('params', $invoice_settings_form.find('select, textarea, input').serialize());
            data.append('action', latepoint_helper.route_action);
            data.append('route_name', $invoice_settings_form.data('route'));
            data.append('return_format', 'json');


            $button.addClass('os-loading');
            try {

                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: latepoint_formdata_to_url_encoded_string(data),
                });
                if (response.status === "success") {
                    $button.removeClass('os-loading');
                    if (response.status === "success") {
                        jQuery('.quick-invoice-settings-form-wrapper').html('');
                        jQuery('.invoices-info-w .list-of-invoices').append(response.message);
                    } else {
                        alert(response.message, 'error');
                    }
                    jQuery('.invoices-info-w').removeClass('setting-new-invoice');
                    return true;
                } else {
                    alert('Error');
                    throw new Error(response.message);
                }
            } catch (e) {
                throw e;
            }
        });
    }


    init_invoice_data_form(){
        latepoint_init_input_masks(jQuery('.invoice-data-form'));
        latepoint_init_daterangepicker(jQuery('.invoice-data-form .os-date-range-picker'));
    }

    init_email_invoice_form(){
      let $form = jQuery('.email-invoice-form');
    }

    init_invoice_data_updated(data){
        if(data.status == 'success'){
            latepoint_lightbox_close();
            this.init_invoice_preview();
            let $invoice_document = jQuery('.invoice-document');
            let invoice_id = $invoice_document.data('invoice-id');
            this.reload_invoice_tile(invoice_id);
        }else{
            alert(data.message);
        }
    }


    init_invoice_preview() {
        jQuery('.invoice-change-status-selector').on('change', async (e) => {
            return await this.change_invoice_status();
        })
    }

    async reload_invoice_tile(invoice_id){
        let $invoice_element = jQuery('.list-of-invoices .os-invoice-wrapper[data-invoice-id="'+invoice_id+'"]');

        $invoice_element.addClass('os-loading');
        let route = $invoice_element.data('reload-tile-route');
        let data = {
            action: 'latepoint_route_call',
            route_name: route,
            params: {
                invoice_id: invoice_id
            },
            return_format: 'json'
        }
        try {

            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            if (response.status === "success") {
                $invoice_element.removeClass('os-loading');
                if (response.status === "success") {
                    let was_selected = $invoice_element.hasClass('selected');
                    $invoice_element.replaceWith(response.message);
                    if(was_selected){
                        let $invoice_element = jQuery('.list-of-invoices .os-invoice-wrapper[data-invoice-id="'+invoice_id+'"]');
                        $invoice_element.addClass('selected');
                    }
                } else {
                    alert(response.message, 'error');
                }
                return true;
            } else {
                throw new Error(response.message);
            }
        } catch (e) {
            throw e;
        }
    }

    async init_invoices_on_order_form($order_form) {
        $order_form.on('click', '.os-invoice-wrapper', async (e) => {
            jQuery('.os-invoice-wrapper.selected').removeClass('selected');
            let $invoice_element = jQuery(e.currentTarget);

            $invoice_element.addClass('os-loading');
            let route = $invoice_element.data('route');
            let data = {
                action: 'latepoint_route_call',
                route_name: route,
                params: {
                    id: $invoice_element.data('invoice-id')
                },
                return_format: 'json'
            }
            try {

                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                if (response.status === "success") {
                    $invoice_element.removeClass('os-loading').addClass('selected');
                    if (response.status === "success") {
                        latepoint_display_in_side_sub_panel(response.message);
                        jQuery('body').addClass('has-side-sub-panel');
                        jQuery('.latepoint-deselect-invoice-trigger').on('click', (e) => {
                            e.preventDefault();
                            jQuery('.os-invoice-wrapper.selected').removeClass('selected');
                        })
                        this.init_invoice_preview();
                    } else {
                        alert(response.message, 'error');
                    }
                    return true;
                } else {
                    throw new Error(response.message);
                }
            } catch (e) {
                throw e;
            }
        });
    }


}

window.latepointInvoicesAdminFeature = new LatepointInvoicesAdminFeature();