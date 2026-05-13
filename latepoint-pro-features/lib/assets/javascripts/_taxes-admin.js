class LatepointTaxesAddon {

  // Init
  constructor(){
    this.ready();
  }

  init_new_tax_form(){
    this.init_tax_value_format(jQuery('.os-taxes-w .os-form-block:last-child'));
  }

  init_tax_value_format($tax_form){
    if($tax_form.find('.tax-type-selector').val() == 'fixed' && $tax_form.find('.tax-value').length) latepoint_mask_money($tax_form.find('.tax-value'));
    if($tax_form.find('.tax-type-selector').val() == 'percentage' && $tax_form.find('.tax-value').length) latepoint_mask_percent($tax_form.find('.tax-value'));
  }

  latepoint_tax_removed($elem){
    $elem.closest('.os-form-block').remove();
  }

  init_tax_forms(){
    jQuery('.os-taxes-w .os-form-block').each((index, form) => {
      this.init_tax_value_format(jQuery(form));
    });
  }

  ready(){
    jQuery(document).ready(() => {
      this.init_tax_forms();
      jQuery('.latepoint-content').on('change', '.tax-type-selector', (event) => {
        let $type_selector = jQuery(event.currentTarget);
        let $tax_form = $type_selector.closest('.os-form-block');
        $tax_form.find('.os-form-block-type').text($type_selector.val());
        this.init_tax_value_format($tax_form);
      });
    });
  }
}

window.latepointTaxesAddon = new LatepointTaxesAddon();