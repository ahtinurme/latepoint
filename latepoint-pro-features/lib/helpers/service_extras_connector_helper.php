<?php 

class OsServiceExtrasConnectorHelper {

	public static function count_connections( $connection_query_arr, $group_by = false ) {
		$connection_model = new OsServiceExtraConnectorModel();
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
		$connection_model = new OsServiceExtraConnectorModel();
		$connection_model->delete_where( array( 'service_id' => $deleted_service_id ), array( '%d' ) );
	}

	public static function get_connected_extras_ids_to_service( $service_id ) {
		$extras_ids       = [];
		$connection_model = new OsServiceExtraConnectorModel();
		$results          = $connection_model->select( 'service_extra_id' )->where( [ 'service_id' => $service_id ] )->get_results();
		if ( $results ) {
			$extras_ids = array_map(
				function ( $s ) {
					return $s->service_extra_id;
				},
				$results 
			);
		}
		return $extras_ids;
	}

	public static function has_connection( $connection_arr ) {
		$connection_model = new OsServiceExtraConnectorModel();
		return $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
	}

	public static function save_connection( $connection_arr ) {
		$connection_model    = new OsServiceExtraConnectorModel();
		$existing_connection = $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
		if ( $existing_connection ) {
			// Update
		} else {
			// Insert
			$connection_model->set_data( $connection_arr );
			return $connection_model->save();
		}
	}



	public static function remove_connection( $connection_arr ) {
		$connection_model = new OsServiceExtraConnectorModel();
		if ( isset( $connection_arr['service_id'] ) && isset( $connection_arr['service_extra_id'] ) ) {
			$existing_connection = $connection_model->where( $connection_arr )->set_limit( 1 )->get_results_as_models();
			if ( $existing_connection ) {
				$existing_connection->delete();
			}
		}
	}
}
