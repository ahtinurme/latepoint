<?php

class OsServiceExtraConnectorModel extends OsModel {
	public $id;
	public $service_id;
	public $service_extra_id;
	public $updated_at;
	public $created_at;

	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_SERVICES_SERVICE_EXTRAS;
		$this->nice_names = array();

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}


	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = array(
			'id', 
			'service_id',
			'service_extra_id',
		);
		return $params_to_save;
	}

	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = array(
			'id', 
			'service_id',
			'service_extra_id',
		);
		return $allowed_params;
	}


	protected function properties_to_validate() {
		$validations = array(
			'service_id'       => array( 'presence' ),
			'service_extra_id' => array( 'presence' ),
		);
		return $validations;
	}
}
