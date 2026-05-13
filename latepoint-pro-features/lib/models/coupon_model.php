<?php

class OsCouponModel extends OsModel {
	public $id;
	public $code;
	public $name;
	public $description;
	public $rules;
	public $status;
	public $discount_type;
	public $discount_value;
	public $active_from;
	public $active_to;
	public $updated_at;
	public $created_at;

	public function __construct( $id = false ) {
		$this->table_name = LATEPOINT_TABLE_COUPONS;
		$this->nice_names = array(
			'code' => __( 'Coupon Code', 'latepoint-pro-features' ),
		);

		parent::__construct( $id );
	}

	public function get_rule( $rule, $default = false ) {
		if ( $this->rules ) {
			$rules_arr = json_decode( $this->rules, true );
			if ( isset( $rules_arr[ $rule ] ) ) {
				return $rules_arr[ $rule ];
			}
		}
		return $default;
	}

	public function readable_discount(): string {
		if ( $this->discount_type == 'percent' ) {
			$discount_label  = OsMoneyHelper::format_price( $this->discount_value, false, true );
			$discount_label .= '%';
		} elseif ( $this->discount_type == 'fixed' ) {
			$discount_label = OsMoneyHelper::format_price( $this->discount_value );
		}
		return $discount_label;
	}


	protected function before_save() {
		$this->code = trim( strtoupper( $this->code ) );
	}

	protected function get_default_status() {
		return LATEPOINT_COUPON_STATUS_ACTIVE;
	}

	protected function before_create() {
		if ( empty( $this->status ) ) {
			$this->status = $this->get_default_status();
		}
	}


	public function get_nice_status() {
		return OsCouponsHelper::get_nice_status_name( $this->status );
	}
  

	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = array(
			'id',
			'code',
			'name',
			'description',
			'rules',
			'status',
			'discount_type',
			'discount_value',
			'active_from',
			'active_to',
			'updated_at',
			'created_at',
		);
		return $allowed_params;
	}

	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = array(
			'id',
			'code',
			'name',
			'description',
			'rules',
			'status',
			'discount_type',
			'discount_value',
			'active_from',
			'active_to',
			'updated_at',
			'created_at',
		);
		return $params_to_save;
	}

	protected function properties_to_validate() {
		$validations = array(
			'code'           => array( 'presence', 'uniqueness' ),
			'status'         => array( 'presence' ),
			'discount_type'  => array( 'presence' ),
			'discount_value' => array( 'presence' ),
		);
		return $validations;
	}
}
