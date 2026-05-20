<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** @var $customer_verification_info array */
/** @var $default_fields_for_customer array */
/** @var $customer OsCustomerModel */
/** @var $booking OsBookingModel */
?>
<div class="os-row">
    <?php
  if($default_fields_for_customer['first_name']['active']){
      echo OsFormHelper::text_field('customer[first_name]', __('First Name', 'latepoint'), $customer->first_name, array('validate' => $customer->get_validations_for_property('first_name'), 'class' => $default_fields_for_customer['first_name']['required'] ? 'required' : '', 'autocomplete' => 'given-name'), array('class' => $default_fields_for_customer['first_name']['width']));
  }
  if($default_fields_for_customer['last_name']['active']){
      echo OsFormHelper::text_field('customer[last_name]', __('Last Name', 'latepoint'), $customer->last_name, array('validate' => $customer->get_validations_for_property('last_name'), 'class' => $default_fields_for_customer['last_name']['required'] ? 'required' : '', 'autocomplete' => 'family-name'), array('class' => $default_fields_for_customer['last_name']['width']));
  }
  if($default_fields_for_customer['phone']['active']){
      echo '<div class="os-verifiable-field-wrapper '.esc_attr($default_fields_for_customer['phone']['width']).' os-col-sm-12">';
          if($customer_verification_info && $customer_verification_info['data']['contact_type'] == 'phone' && $customer_verification_info['data']['contact_value'] == $customer->phone){
            echo '<div class="os-verified-badge"><i class="latepoint-icon latepoint-icon-checkmark"></i></div>';
          }
          echo OsFormHelper::phone_number_field('customer[phone]', __('Phone Number', 'latepoint'), $customer->phone, array('validate' => $customer->get_validations_for_property('phone'), 'class' => $default_fields_for_customer['phone']['required'] ? 'required' : '', 'autocomplete' => 'tel'));
      echo '</div>';
  }
  if($default_fields_for_customer['email']['active']) {
	  echo '<div class="os-verifiable-field-wrapper ' . esc_attr( $default_fields_for_customer['email']['width'] ) . ' os-col-sm-12">';
	  if ( $customer_verification_info && $customer_verification_info['data']['contact_type'] == 'email' && $customer_verification_info['data']['contact_value'] == $customer->email ) {
		  echo '<div class="os-verified-badge"><i class="latepoint-icon latepoint-icon-checkmark"></i></div>';
	  }
	  echo OsFormHelper::text_field( 'customer[email]', __( 'Email Address', 'latepoint' ), $customer->email, array(
		  'validate'     => $customer->get_validations_for_property( 'email' ),
		  'class'        => 'required',
		  'autocomplete' => 'email',
	  ) );
	  echo '</div>';
  }
  if(OsSettingsHelper::is_on('steps_require_setting_password') && OsAuthHelper::is_customer_auth_enabled() && !OsAuthHelper::is_customer_logged_in() && ($customer->is_new_record() || $customer->is_guest)){
		echo OsFormHelper::password_field('customer[password]', __('Password', 'latepoint'), '', array('class' => 'required', 'autocomplete' => 'new-password'), array('class' => 'os-col-6'));
		echo OsFormHelper::password_field('customer[password_confirmation]', __('Confirm Password', 'latepoint'), '', array('class' => 'required', 'autocomplete' => 'new-password'), array('class' => 'os-col-6'));
  }
  if($default_fields_for_customer['notes']['active']){
      echo OsFormHelper::textarea_field('customer[notes]', __('Add Comments', 'latepoint'), $customer->notes, array('validate' => $customer->get_validations_for_property('notes'), 'class' => $default_fields_for_customer['notes']['required'] ? 'required' : '', 'autocomplete' => 'off'), array('class' => $default_fields_for_customer['notes']['width']));
  }
  do_action('latepoint_booking_steps_contact_after', $customer, $booking); ?>
</div>