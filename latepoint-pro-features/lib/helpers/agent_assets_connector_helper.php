<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAgentAssetsConnectorHelper {

	public static function count_connections( $connection_query_arr, $group_by = false ) {
		$connection_model = new OsAgentAssetConnectorModel();
		$connection_model->where( $connection_query_arr );
		if ( $group_by ) {
			$results = $connection_model->select( $group_by )->group_by( $group_by )->get_results();
			$total   = count( $results );
		} else {
			$total = $connection_model->count();
		}
		return $total;
	}

	public static function delete_agent_connections_after_deletion( $deleted_agent_id ) {
		$connection_model = new OsAgentAssetConnectorModel();
		$connection_model->delete_where( [ 'agent_id' => $deleted_agent_id ], [ '%d' ] );
	}

	public static function delete_asset_connections_after_deletion( $deleted_asset_id ) {
		$connection_model = new OsAgentAssetConnectorModel();
		$connection_model->delete_where( [ 'asset_id' => $deleted_asset_id ], [ '%d' ] );
	}

	public static function get_connected_asset_ids_to_agent( $agent_id ) {
		$asset_ids        = [];
		$connection_model = new OsAgentAssetConnectorModel();
		$results          = $connection_model->select( 'asset_id' )->where( [ 'agent_id' => $agent_id ] )->get_results();
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

	public static function get_connected_agent_ids_to_asset( $asset_id ) {
		$agent_ids        = [];
		$connection_model = new OsAgentAssetConnectorModel();
		$results          = $connection_model->select( 'agent_id' )->where( [ 'asset_id' => $asset_id ] )->get_results();
		if ( $results ) {
			$agent_ids = array_map(
				function ( $row ) {
					return $row->agent_id;
				},
				$results
			);
		}
		return $agent_ids;
	}

	public static function has_connection( $connection_arr ) {
		$connection_model = new OsAgentAssetConnectorModel();
		return $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
	}

	public static function save_connection( $connection_arr ) {
		$connection_model    = new OsAgentAssetConnectorModel();
		$existing_connection = $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
		if ( ! $existing_connection ) {
			$connection_model->set_data( $connection_arr );
			return $connection_model->save();
		}
	}

	public static function remove_connection( $connection_arr ) {
		$connection_model = new OsAgentAssetConnectorModel();
		if ( isset( $connection_arr['agent_id'] ) && isset( $connection_arr['asset_id'] ) ) {
			$existing_connection = $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
			if ( $existing_connection ) {
				$existing_connection->delete();
			}
		}
	}
}
