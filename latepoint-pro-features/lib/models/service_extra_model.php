<?php

class OsServiceExtraModel extends OsModel {
	public $id;
	public $name;
	public $short_description;
	public $charge_amount;
	public $duration;
	public $maximum_quantity = 1;
	public $selection_image_id;
	public $description_image_id;
	public $multiplied_by_attendees = true;
	public $status;
	public $created_at;
	public $updated_at;

	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_SERVICE_EXTRAS;
		$this->nice_names = [ 'name' => __( 'Name', 'latepoint-pro-features' ) ];

		if ( $id ) {
			$this->load_by_id( $id );
			if ( empty( $this->maximum_quantity ) ) {
				$this->maximum_quantity = 1;
			}
		}
	}

	public function generate_data_vars(): array {
		return [
			'id'                => $this->id,
			'duration'          => $this->duration,
			'short_description' => $this->short_description,
			'name'              => $this->name,
		];
	}

	public function get_formatted_charge_amount() {
		if ( $this->charge_amount > 0 ) {
			return OsMoneyHelper::format_price( $this->charge_amount, true, false );
		} else {
			return 0;
		}
	}

	public function get_selection_image_url() {
		$default_service_image_url = LATEPOINT_IMAGES_URL . 'service-image.png';
		return OsImageHelper::get_image_url_by_id( $this->selection_image_id, 'thumbnail', $default_service_image_url );
	}

	public function get_services() {
		if ( ! isset( $this->services ) ) {
			$connector = new OsServiceExtraConnectorModel();
			$connector->where( [ 'service_extra_id' => $this->id ] )->group_by( 'service_id' );
			$services_rows = $connector->get_results();

			$this->services = array();

			if ( $services_rows ) {
				foreach ( $services_rows as $service_row ) {
					$service          = new OsServiceModel( $service_row->service_id );
					$this->services[] = $service;
				}
			}
		}
		return $this->services;
	}


	public function has_service( $service_id ) {
		return OsServiceExtrasConnectorHelper::has_connection(
			[
				'service_extra_id' => $this->id,
				'service_id'       => $service_id,
			] 
		);
	}

	public function should_be_active() {
		return $this->where( [ 'status' => LATEPOINT_SERVICE_EXTRA_STATUS_ACTIVE ] );
	}


	public function delete( $id = false ) {
		if ( ! $id && isset( $this->id ) ) {
			$id = $this->id;
		}
		if ( $id && $this->db->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) ) ) {
			$this->db->delete( LATEPOINT_TABLE_SERVICES_SERVICE_EXTRAS, array( 'service_extra_id' => $id ), array( '%d' ) );
			return true;
		} else {
			return false;
		}
	}


	public function save_connected_services( $services ) {
		if ( ! $services ) {
			return true;
		}
		$connections_to_save   = [];
		$connections_to_remove = [];
		foreach ( $services as $service_key => $service ) {
			$service_id = str_replace( 'service_', '', $service_key );
			$connection = [
				'service_extra_id' => $this->id,
				'service_id'       => $service_id,
			];
			if ( $service['connected'] == 'yes' ) {
				$connections_to_save[] = $connection;
			} else {
				$connections_to_remove[] = $connection;
			}
		}
		if ( ! empty( $connections_to_save ) ) {
			foreach ( $connections_to_save as $connection_to_save ) {
				OsServiceExtrasConnectorHelper::save_connection( $connection_to_save );
			}
		}
		if ( ! empty( $connections_to_remove ) ) {
			foreach ( $connections_to_remove as $connection_to_remove ) {
				OsServiceExtrasConnectorHelper::remove_connection( $connection_to_remove );
			}
		}
		return true;
	}

	public function count_number_of_connected_services( $service_id = false ) {
		if ( $this->is_new_record() ) {
			return 0;
		}
		$args = [ 'service_extra_id' => $this->id ];
		if ( $service_id ) {
			$args['service_id'] = $service_id;
		}
		return OsServiceExtrasConnectorHelper::count_connections( $args, 'service_id' );
	}

	protected function allowed_params( $role = 'admin' ) {
		$allowed_params = array(
			'id',
			'name',
			'short_description',
			'charge_amount',
			'duration',
			'maximum_quantity',
			'selection_image_id',
			'description_image_id',
			'multiplied_by_attendees',
			'status',
			'created_at',
			'updated_at',
		);
		return $allowed_params;
	}
  
	protected function params_to_save( $role = 'admin' ) {
		$params_to_save = array(
			'id',
			'name',
			'short_description',
			'charge_amount',
			'duration',
			'maximum_quantity',
			'selection_image_id',
			'description_image_id',
			'multiplied_by_attendees',
			'status',
			'created_at',
			'updated_at',
		);
		return $params_to_save;
	}


	protected function params_to_sanitize() {
		return [ 'charge_amount' => 'money' ];
	}

	protected function properties_to_validate() {
		$validations = array(
			'name' => array( 'presence' ),
		);
		return $validations;
	}
}
