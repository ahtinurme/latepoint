<?php

class OsOTPModel extends OsModel {
	public $id,
		$contact_value,
		$contact_type,
		$delivery_method,
		$otp_hash,
		$expires_at,
		$attempts,
		$used_at,
		$updated_at,
		$created_at;

	function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_CUSTOMER_OTP_CODES;

		if ( $id ) {
			$this->load_by_id( $id );
		}
		$this->nice_names = [ 'contact_value' => __( 'Contact', 'latepoint' ) ];
	}



	protected function params_to_save( $role = 'admin' ): array {
		$params_to_save = [
			'id',
			'contact_value',
			'contact_type',
			'delivery_method',
			'otp_hash',
			'expires_at',
			'attempts',
			'used_at',
		];

		return $params_to_save;
	}


	protected function allowed_params( $role = 'admin' ): array {
		$allowed_params = [
			'id',
			'contact_value',
			'contact_type',
			'delivery_method',
			'otp_hash',
			'expires_at',
			'attempts',
			'used_at',
		];

		return $allowed_params;
	}


	protected function properties_to_validate(): array {
		$validations = [
			'contact_value' => [ 'presence' ],
			'otp_hash'      => [ 'presence' ],
		];

		return $validations;
	}
}
