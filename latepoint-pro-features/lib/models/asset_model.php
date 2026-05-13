<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAssetModel extends OsModel {
	public $id;
	public $name;
	public $short_description;
	public $quantity = 1;
	public $status;
	public $created_at;
	public $updated_at;

	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_ASSETS;
		$this->nice_names = [ 'name' => __( 'Name', 'latepoint-pro-features' ) ];

		if ( $id ) {
			$this->load_by_id( $id );
			if ( empty( $this->quantity ) ) {
				$this->quantity = 1;
			}
		}
	}

	public function generate_data_vars(): array {
		return [
			'id'       => $this->id,
			'name'     => $this->name,
			'quantity' => $this->quantity,
		];
	}

	public function get_services() {
		if ( ! isset( $this->services ) ) {
			$connector = new OsAssetConnectorModel();
			$connector->where( [ 'asset_id' => $this->id ] )->group_by( 'service_id' );
			$services_rows = $connector->get_results();

			$this->services = [];

			if ( $services_rows ) {
				foreach ( $services_rows as $service_row ) {
					$service          = new OsServiceModel( $service_row->service_id );
					$this->services[] = $service;
				}
			}
		}
		return $this->services;
	}

	public function get_agents() {
		if ( ! isset( $this->agents ) ) {
			$connector = new OsAgentAssetConnectorModel();
			$connector->where( [ 'asset_id' => $this->id ] )->group_by( 'agent_id' );
			$agent_rows = $connector->get_results();

			$this->agents = [];

			if ( $agent_rows ) {
				foreach ( $agent_rows as $agent_row ) {
					$agent          = new OsAgentModel( $agent_row->agent_id );
					$this->agents[] = $agent;
				}
			}
		}
		return $this->agents;
	}

	public function has_agent( $agent_id ) {
		return OsAgentAssetsConnectorHelper::has_connection(
			[
				'asset_id' => $this->id,
				'agent_id' => $agent_id,
			]
		);
	}

	public function has_service( $service_id ) {
		return OsAssetsConnectorHelper::has_connection(
			[
				'asset_id'   => $this->id,
				'service_id' => $service_id,
			]
		);
	}

	public function should_be_active() {
		return $this->where( [ 'status' => LATEPOINT_ASSET_STATUS_ACTIVE ] );
	}

	public function delete( $id = false ) {
		if ( ! $id && isset( $this->id ) ) {
			$id = $this->id;
		}
		if ( $id && $this->db->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] ) ) {
			$this->db->delete( LATEPOINT_TABLE_SERVICES_ASSETS, [ 'asset_id' => $id ], [ '%d' ] );
			$this->db->delete( LATEPOINT_TABLE_BOOKINGS_ASSETS, [ 'asset_id' => $id ], [ '%d' ] );
			$this->db->delete( LATEPOINT_TABLE_AGENTS_ASSETS, [ 'asset_id' => $id ], [ '%d' ] );
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
				'asset_id'   => $this->id,
				'service_id' => $service_id,
			];
			if ( $service['connected'] == 'yes' ) {
				$connections_to_save[] = $connection;
			} else {
				$connections_to_remove[] = $connection;
			}
		}
		if ( ! empty( $connections_to_save ) ) {
			foreach ( $connections_to_save as $connection_to_save ) {
				OsAssetsConnectorHelper::save_connection( $connection_to_save );
			}
		}
		if ( ! empty( $connections_to_remove ) ) {
			foreach ( $connections_to_remove as $connection_to_remove ) {
				OsAssetsConnectorHelper::remove_connection( $connection_to_remove );
			}
		}
		return true;
	}

	public function save_connected_agents( $agents ) {
		if ( ! $agents ) {
			return true;
		}
		$connections_to_save   = [];
		$connections_to_remove = [];
		foreach ( $agents as $agent_key => $agent ) {
			$agent_id   = str_replace( 'agent_', '', $agent_key );
			$connection = [
				'asset_id' => $this->id,
				'agent_id' => $agent_id,
			];
			if ( $agent['connected'] == 'yes' ) {
				$connections_to_save[] = $connection;
			} else {
				$connections_to_remove[] = $connection;
			}
		}
		if ( ! empty( $connections_to_save ) ) {
			foreach ( $connections_to_save as $connection_to_save ) {
				OsAgentAssetsConnectorHelper::save_connection( $connection_to_save );
			}
		}
		if ( ! empty( $connections_to_remove ) ) {
			foreach ( $connections_to_remove as $connection_to_remove ) {
				OsAgentAssetsConnectorHelper::remove_connection( $connection_to_remove );
			}
		}
		return true;
	}

	public function count_number_of_connected_agents( $agent_id = false ) {
		if ( $this->is_new_record() ) {
			return 0;
		}
		$args = [ 'asset_id' => $this->id ];
		if ( $agent_id ) {
			$args['agent_id'] = $agent_id;
		}
		return OsAgentAssetsConnectorHelper::count_connections( $args, 'agent_id' );
	}

	public function count_number_of_connected_services( $service_id = false ) {
		if ( $this->is_new_record() ) {
			return 0;
		}
		$args = [ 'asset_id' => $this->id ];
		if ( $service_id ) {
			$args['service_id'] = $service_id;
		}
		return OsAssetsConnectorHelper::count_connections( $args, 'service_id' );
	}

	protected function allowed_params( $role = 'admin' ) {
		return [
			'id',
			'name',
			'short_description',
			'quantity',
			'status',
			'created_at',
			'updated_at',
		];
	}

	protected function params_to_save( $role = 'admin' ) {
		return [
			'id',
			'name',
			'short_description',
			'quantity',
			'status',
			'created_at',
			'updated_at',
		];
	}

	protected function properties_to_validate() {
		return [
			'name' => [ 'presence' ],
		];
	}
}
