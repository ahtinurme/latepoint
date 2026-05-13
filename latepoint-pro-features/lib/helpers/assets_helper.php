<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsAssetsHelper {

	/**
	 * Get asset IDs assigned to a booking.
	 *
	 * @param OsBookingModel $booking Booking model.
	 * @return array Array of asset IDs.
	 */
	public static function get_asset_ids_for_booking( OsBookingModel $booking ) {
		$asset_ids = [];
		if ( empty( $booking->id ) ) {
			return $asset_ids;
		}
		$connector = new OsBookingAssetConnectorModel();
		$results   = $connector->select( 'asset_id' )->where( [ 'booking_id' => $booking->id ] )->get_results();
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

	/**
	 * Assign connected assets to a booking.
	 *
	 * @param OsBookingModel $booking Booking model.
	 * @return void
	 */
	public static function save_assets_for_booking( OsBookingModel $booking ) {
		if ( empty( $booking->id ) || empty( $booking->service_id ) ) {
			return;
		}

		// Remove existing asset assignments for this booking.
		self::delete_assets_for_booking( $booking->id );

		// Get all assets connected to this service.
		$asset_ids = OsAssetsConnectorHelper::get_connected_asset_ids_to_service( $booking->service_id );
		if ( empty( $asset_ids ) ) {
			return;
		}

		// Assign each connected asset to the booking (filtered by agent connections).
		foreach ( $asset_ids as $asset_id ) {
			$connected_agent_ids = OsAgentAssetsConnectorHelper::get_connected_agent_ids_to_asset( $asset_id );
			// Skip if this asset has specific agent connections and the booking's agent is not one of them.
			if ( ! empty( $connected_agent_ids ) && ! empty( $booking->agent_id ) && ! in_array( $booking->agent_id, $connected_agent_ids ) ) {
				continue;
			}
			$connector             = new OsBookingAssetConnectorModel();
			$connector->booking_id = $booking->id;
			$connector->asset_id   = $asset_id;
			$connector->save();
		}
	}

	/**
	 * Delete all asset assignments for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public static function delete_assets_for_booking( $booking_id ) {
		if ( ! $booking_id ) {
			return;
		}
		$connector = new OsBookingAssetConnectorModel();
		$connector->delete_where( [ 'booking_id' => $booking_id ] );
	}
}
