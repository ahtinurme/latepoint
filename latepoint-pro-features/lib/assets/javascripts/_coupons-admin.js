/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

class LatepointCouponsAddon {

    // Init
    constructor() {
        this.ready();
    }

    reload_after_coupon_save(){
      if(jQuery('table.os-reload-on-coupon-update').length) latepoint_filter_table(jQuery('table.os-reload-on-coupon-update'), jQuery('table.os-reload-on-coupon-update'));
      latepoint_close_side_panel();
    }

    submit_quick_coupon_form(){
      let $quick_edit_form = jQuery('form.coupon-quick-edit-form');

      let errors = latepoint_validate_form($quick_edit_form);
      if(errors.length){
        let error_messages = errors.map(error =>  error.message ).join(', ');
        latepoint_add_notification(error_messages, 'error');
        return false;
      }

      $quick_edit_form.find('button[type="submit"]').addClass('os-loading');
      jQuery.ajax({
        type: "post",
        dataType: "json",
        processData: false,
        contentType: false,
        url: latepoint_timestamped_ajaxurl(),
        data: latepoint_create_form_data($quick_edit_form),
        success: (response) => {
          $quick_edit_form.find('button[type="submit"]').removeClass('os-loading');
          if(response.form_values_to_update){
            jQuery.each(response.form_values_to_update, function(name, value){
              $quick_edit_form.find('[name="'+ name +'"]').val(value);
            });
          }
          if (response.status === "success") {
            latepoint_add_notification(response.message);
            this.reload_after_coupon_save();
          }else{
            latepoint_add_notification(response.message, 'error');
          }
        }
      });

    }

    init_quick_coupon_form() {
        let $coupon_form_wrapper = jQuery('.quick-coupon-form-w');
        latepoint_init_input_masks($coupon_form_wrapper);
        $coupon_form_wrapper.find('.os-late-select').lateSelect();

        $coupon_form_wrapper.find('.latepoint-coupon-customers-w').each(function() {
            let $wrap   = jQuery(this);
            let $select = $wrap.find('select.os-late-select');
            let $list   = $wrap.find('.lateselect-w .ls-all-items-w');
            let $items  = $list.find('.ls-item');
            if ($items.length <= 5) return;

            let placeholder = $wrap.data('search-placeholder') || 'Start typing to search...';
            let cancel_label = $wrap.data('search-cancel-label') || 'cancel';

            let $search = jQuery(
                '<div class="customers-selector-search-w latepoint-coupon-customer-search-w">' +
                    '<i class="latepoint-icon latepoint-icon-search"></i>' +
                    '<input type="search" class="customers-selector-search-input" autocomplete="off" />' +
                    '<span class="customers-selector-cancel">' +
                        '<i class="latepoint-icon latepoint-icon-x"></i>' +
                        '<span></span>' +
                    '</span>' +
                '</div>'
            );
            $search.find('.customers-selector-search-input').attr('placeholder', placeholder);
            $search.find('.customers-selector-cancel > span').text(cancel_label);
            $list.prepend($search);

            let $input = $search.find('.customers-selector-search-input');

            $input.on('click', function(event) { event.stopPropagation(); });

            $input.on('keydown', function(event) {
                if (event.key === 'Enter') { event.preventDefault(); }
            });

            $input.on('input', function() {
                let raw_query = jQuery.trim($input.val()).toLowerCase();
                let tokens    = raw_query.length ? raw_query.split(/\s+/) : [];

                $items.each(function() {
                    let $item    = jQuery(this);
                    let value    = $item.data('value');
                    let $option  = $select.find('option[value="' + value + '"]');
                    let haystack = ($option.attr('data-search-text') || $option.text() || '').toLowerCase();

                    let matches = true;
                    for (let i = 0; i < tokens.length; i++) {
                        if (haystack.indexOf(tokens[i]) === -1) { matches = false; break; }
                    }
                    $item.toggle(matches);
                });
            });

            $search.find('.customers-selector-cancel').on('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                $input.val('').trigger('input').focus();
            });
        });

        $coupon_form_wrapper.find('.coupon-quick-edit-form').on('submit', (event) => {
            if (jQuery(event.target).find('button[type="submit"]').hasClass('os-loading')) return false;
            event.preventDefault();
            this.submit_quick_coupon_form();
        });

        $coupon_form_wrapper.find('.quick-coupon-form-view-log-btn').on('click', function() {
            let $trigger_elem = jQuery(this);
            $trigger_elem.addClass('os-loading');
            let route = $trigger_elem.data('route');
            let data = { action: 'latepoint_route_call', route_name: route, params: { coupon_id: $trigger_elem.data('coupon-id') }, return_format: 'json' };
            jQuery.ajax({
                type: 'post',
                dataType: 'json',
                url: latepoint_timestamped_ajaxurl(),
                data: data,
                success: function(response) {
                    $trigger_elem.removeClass('os-loading');
                    if (response.status === 'success') {
                        latepoint_display_in_side_sub_panel(response.message);
                        jQuery('body').addClass('has-side-sub-panel');
                    } else {
                        alert(response.message, 'error');
                    }
                }
            });
            return false;
        });
    }

    ready() {
        jQuery(document).ready(() => {
            jQuery('body.latepoint-admin').on('click', '.quick-order-form-w .apply-coupon-button', function () {
                latepoint_reload_price_breakdown();
                return false;
            });

            jQuery('body.latepoint-admin').on('change', '.quick-order-form-w #apply_coupon_toggler', function () {
                if (jQuery(this).val() == 'off') {
                    jQuery('#order_coupon_code').val('');
                    latepoint_reload_price_breakdown();
                }
            });
        });
    }
}


window.latepointCouponsAddon = new LatepointCouponsAddon();