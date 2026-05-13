<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsBookingAssetConnectorModel extends OsModel {
	public $id;
	public $booking_id;
	public $asset_id;
	public $updated_at;
	public $created_at;

	public function __construct( $id = false ) {
		parent::__construct();
		$this->table_name = LATEPOINT_TABLE_BOOKINGS_ASSETS;
		$this->nice_names = [];

		if ( $id ) {
			$this->load_by_id( $id );
		}
	}

	protected function params_to_save( $role = 'admin' ) {
		return [
			'id',
			'booking_id',
			'asset_id',
		];
	}

	protected function allowed_params( $role = 'admin' ) {
		return [
			'id',
			'booking_id',
			'asset_id',
		];
	}

	protected function properties_to_validate() {
		return [
			'booking_id' => [ 'presence' ],
			'asset_id'   => [ 'presence' ],
		];
	}
}
