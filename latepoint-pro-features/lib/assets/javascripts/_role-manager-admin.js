/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class LatepointRoleManagerAddonAdmin {

	// Init
	constructor(){
		this.ready();
	}

	ready() {
    jQuery(() => {

    });
  }

	role_deleted($elem){
    $elem.closest('.os-form-block').remove();
	}

	init_edit_wp_user_form(){
		let $form = jQuery('.role-user-edit-form');
		$form.find('.os-late-select').lateSelect();
		$form.find('select.allowed_models_selector').on('change', function(){
			if(jQuery(this).val() == latepoint_helper.value_all){
				jQuery(this).closest('.os-form-group').next('div').hide();
			}else{
				jQuery(this).closest('.os-form-group').next('div').show();
			}
		});
		$form.find('input#custom_capabilities').on('change', function(){
			if(jQuery(this).val() == 'on'){
				$form.find('.custom-user-capabilities-w').show();
				$form.find('.default-user-capabilities-w').hide();
			}else{
				$form.find('.custom-user-capabilities-w').hide();
				$form.find('.default-user-capabilities-w').show();
			}
		});
	}

	init_new_role_form(){
		let $form = jQuery('.os-custom-roles-w .os-form-block:last-child');
    $form.addClass('os-is-editing').find('input[name="role[name]"]').trigger('select');
		this.init_role_form($form);
	}

	init_role_form($form){
		$form.find('input[name="role[name]"]').on('keyup', function(){
			$form.find('.update-from-name').text(jQuery(this).val());
		});
	}

}

window.latepointRoleManagerAddonAdmin = new LatepointRoleManagerAddonAdmin();