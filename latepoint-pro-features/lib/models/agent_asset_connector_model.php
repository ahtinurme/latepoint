<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAgentAssetConnectorModel extends OsModel {
	public $id;
	public $agent_id;
	public $asset_id;
	public $updated_at;
	public $created_at;

	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_AGENTS_ASSETS;
		$this->nice_names = [];

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}

	protected function params_to_save( $role = 'admin' ) {
		return [
			'id',
			'agent_id',
			'asset_id',
		];
	}

	protected function allowed_params( $role = 'admin' ) {
		return [
			'id',
			'agent_id',
			'asset_id',
		];
	}

	protected function properties_to_validate() {
		return [
			'agent_id' => [ 'presence' ],
			'asset_id' => [ 'presence' ],
		];
	}
}
