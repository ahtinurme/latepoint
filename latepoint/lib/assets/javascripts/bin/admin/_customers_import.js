function  latepoint_init_customers_import() {

  jQuery('body.latepoint').on('submit', '.import-customers-form', async function (e) {
    e.preventDefault();
    let $form = jQuery(this);

    if($form.hasClass('os-loading')) return false;

    $form.addClass('os-loading');
    $form.find('button[type="submit"]').addClass('os-loading');

    try {
      let response = await jQuery.ajax({
        type: "post",
        dataType: "json",
        processData: false,
        contentType: false,
        url: latepoint_timestamped_ajaxurl(),
        data: latepoint_create_form_data($form)
      });

      $form.removeClass('os-loading').find('.os-loading').removeClass('os-loading');

      if (response.status === 'success') {
        $form.find('.latepoint-lightbox-content').html(response.message);

        latepoint_import_customers_set_next_btn($form);
        latepoint_import_customers_set_step($form);

      } else {
        latepoint_add_notification(response.message || 'Error', 'error');
        return false;
      }
    } catch (e) {
      $form.removeClass('os-loading').find('.os-loading').removeClass('os-loading');
      console.log(e);
    }
  });
}

function latepoint_import_customers_set_next_btn($form) {
  let step_form = $form.find('.customer-csv-step');
  if (step_form.data('hide-next-btn')) {
    $form.find('.latepoint-lightbox-footer').hide();
  } else {
    let btn_label = step_form.data('customer-csv-next-btn');
    if (btn_label) {
      $form.find('.latepoint-csv-next-btn').text(btn_label);
    }
  }
}

function latepoint_import_customers_set_step($form) {
  let step = $form.find('.customer-csv-step').data('customer-csv-step');
  if (step) {
    $form.find('input[name="step"]').val(step);
  }
}