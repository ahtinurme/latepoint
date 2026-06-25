/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

/*
 * The single "type delete to confirm" modal for the whole plugin. One modal, one set of
 * os-delete-confirm-* classes, used for both:
 *
 *   1. Single-record delete — add the class os-delete-confirm to a normal data-os-action button that
 *      has data-os-prompt (services now, any tab later). Optional data-os-confirm-title sets the modal
 *      heading; the data-os-prompt text is the body. The gate in bin/actions.js calls
 *      latepoint_delete_confirm_show($trigger); on confirm the button's own data-os-action flow runs.
 *
 *   2. Bulk delete (e.g. appointments) — call it programmatically with an options object:
 *        latepoint_delete_confirm_show({ title: '...', body: '...', onConfirm: function(){ ... } });
 *      and run the bulk request inside onConfirm.
 *
 * Shared strings (prompt template, confirm word, buttons) come from the single
 * latepoint_delete_confirm_i18n localized object.
 */

function latepoint_delete_confirm_show(arg){
  var i18n = (typeof latepoint_delete_confirm_i18n !== 'undefined') ? latepoint_delete_confirm_i18n : {};
  var confirm_word = i18n.modal_confirm_word || 'delete';
  var title, body, onConfirm;

  if(arg && arg.jquery){
    // Single delete: a .os-delete-confirm data-os-action button. Heading comes from the optional
    // data-os-confirm-title; the body is the button's existing data-os-prompt message. On confirm,
    // re-fire its click with os-delete-approved set so the gate in bin/actions.js skips the modal and runs
    // its normal data-os-action flow.
    var $trigger = arg;
    title = $trigger.data('os-confirm-title') || '';
    body  = $trigger.data('os-prompt') || '';
    onConfirm = function(){ $trigger.data('os-delete-approved', true).trigger('click'); };
  } else {
    // Programmatic caller (e.g. appointments bulk delete): supplies its own title/body + confirm callback.
    arg = arg || {};
    title = arg.title || '';
    body  = arg.body || '';
    onConfirm = (typeof arg.onConfirm === 'function') ? arg.onConfirm : function(){};
  }

  // Build the modal via jQuery DOM methods so every user/translator supplied string is assigned via
  // .text()/.attr() (jQuery escapes it) — no risk of attribute break-out or HTML injection.
  var $modal = jQuery('<div class="os-delete-confirm-modal" />').attr('data-confirm-word', confirm_word);
  $modal.append('<div class="os-delete-confirm-icon"><i class="latepoint-icon latepoint-icon-trash-2"></i></div>');
  if(title) $modal.append(jQuery('<h3 class="os-delete-confirm-title" />').text(title));
  if(body) $modal.append(jQuery('<p class="os-delete-confirm-body" />').text(body));

  $modal.append(
    jQuery('<input type="text" class="os-delete-confirm-input" autocomplete="off" spellcheck="false" />')
      .attr('placeholder', i18n.modal_input_placeholder || '')
  );

  var $actions = jQuery('<div class="os-delete-confirm-actions" />');
  $actions.append(
    jQuery('<a href="#" class="latepoint-btn latepoint-btn-grey latepoint-btn-outline os-delete-confirm-cancel" />')
      .append(jQuery('<span />').text(i18n.modal_cancel || ''))
  );
  $actions.append(
    jQuery('<a href="#" class="latepoint-btn latepoint-btn-danger os-delete-confirm-go is-disabled" />')
      .attr('aria-disabled', 'true')
      .append(jQuery('<span />').text(i18n.modal_confirm || ''))
  );
  $modal.append($actions);

  latepoint_show_data_in_lightbox($modal[0].outerHTML, 'width-500 os-delete-confirm-lightbox', true);

  // latepoint_show_data_in_lightbox appends the lightbox synchronously, so the modal is already in the
  // DOM — store the confirm callback and focus the input without a fragile setTimeout.
  var $live = jQuery('.os-delete-confirm-lightbox .os-delete-confirm-modal');
  $live.data('onConfirm', onConfirm);
  $live.find('.os-delete-confirm-input').trigger('focus');
}

function latepoint_delete_confirm_run($go){
  var onConfirm = $go.closest('.os-delete-confirm-modal').data('onConfirm');
  latepoint_lightbox_close();
  if(typeof onConfirm === 'function') onConfirm();
}

function latepoint_init_delete_confirm(){
  var $body = jQuery('body');
  if($body.data('os-delete-confirm-bound')) return;
  $body.data('os-delete-confirm-bound', true);

  $body.on('input keyup paste', '.os-delete-confirm-input', function(){
    var $modal = jQuery(this).closest('.os-delete-confirm-modal');
    var required = String($modal.data('confirm-word') || 'delete').toLowerCase();
    var typed = String(jQuery(this).val() || '').trim().toLowerCase();
    var $go = $modal.find('.os-delete-confirm-go');
    if(typed === required){
      $go.removeClass('is-disabled').attr('aria-disabled', 'false');
    } else {
      $go.addClass('is-disabled').attr('aria-disabled', 'true');
    }
  });

  $body.on('keydown', '.os-delete-confirm-input', function(e){
    if(e.which !== 13) return;
    e.preventDefault();
    var $go = jQuery(this).closest('.os-delete-confirm-modal').find('.os-delete-confirm-go');
    if(!$go.hasClass('is-disabled')) $go.trigger('click');
  });

  $body.on('click', '.os-delete-confirm-cancel', function(e){
    e.preventDefault();
    latepoint_lightbox_close();
  });

  $body.on('click', '.os-delete-confirm-go', function(e){
    e.preventDefault();
    var $this = jQuery(this);
    if($this.hasClass('is-disabled') || $this.hasClass('os-loading')) return;
    latepoint_delete_confirm_run($this);
  });
}
