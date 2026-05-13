<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAssetsConnectorHelper {

	public static function count_connections( $connection_query_arr, $group_by = false ) {
		$connection_model = new OsAssetConnectorModel();
		$connection_model->where( $connection_query_arr );
		if ( $group_by ) {
			$results = $connection_model->select( $group_by )->group_by( $group_by )->get_results();
			$total   = count( $results );
		} else {
			$total = $connection_model->count();
		}
		return $total;
	}

	public static function delete_service_connections_after_deletion( $deleted_service_id ) {
		$connection_model = new OsAssetConnectorModel();
		$connection_model->delete_where( [ 'service_id' => $deleted_service_id ], [ '%d' ] );
	}

	public static function get_connected_asset_ids_to_service( $service_id ) {
		$asset_ids        = [];
		$connection_model = new OsAssetConnectorModel();
		$results          = $connection_model->select( 'asset_id' )->where( [ 'service_id' => $service_id ] )->get_results();
		if ( $results ) {
			$asset_ids = array_map(
				function ( $row ) {
					return $row->asset_id;
				},
				$results
			);
		}
		return $asset_ids;
	}

	public static function has_connection( $connection_arr ) {
		$connection_model = new OsAssetConnectorModel();
		return $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
	}

	public static function save_connection( $connection_arr ) {
		$connection_model    = new OsAssetConnectorModel();
		$existing_connection = $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
		if ( ! $existing_connection ) {
			$connection_model->set_data( $connection_arr );
			return $connection_model->save();
		}
	}

	public static function remove_connection( $connection_arr ) {
		$connection_model = new OsAssetConnectorModel();
		if ( isset( $connection_arr['service_id'] ) && isset( $connection_arr['asset_id'] ) ) {
			$existing_connection = $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
			if ( $existing_connection ) {
				$existing_connection->delete();
			}
		}
	}
}
