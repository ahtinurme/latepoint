<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class OsFeatureAssetsHelper {

	/**
	 * Add capabilities for the assets controller.
	 *
	 * @param array $required_capabilities Existing capabilities.
	 * @return array Modified capabilities.
	 */
	public static function add_capabilities_for_controller( $required_capabilities ) {
		$required_capabilities['OsAssetsController'] = [];
		return $required_capabilities;
	}

	/**
	 * Register asset-related capabilities in the roles system.
	 *
	 * @param array $actions Existing actions list.
	 * @return array Modified actions list.
	 */
	public static function add_asset_capabilities( $actions ) {
		$actions[] = 'asset__view';
		$actions[] = 'asset__delete';
		$actions[] = 'asset__create';
		$actions[] = 'asset__edit';
		return $actions;
	}

	/**
	 * Output the assets connection selector on the service edit form.
	 *
	 * @param OsServiceModel $service Service being edited.
	 * @return void
	 */
	public static function output_assets_on_service_form( $service ) {
		$assets = new OsAssetModel();
		$assets = $assets->should_be_active()->get_results_as_models();
		?>
		<div class="white-box section-anchor" id="stickySectionAssets">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php esc_html_e( 'Assets', 'latepoint-pro-features' ); ?></h3>
					<div class="os-form-sub-header-actions">
						<?php echo OsFormHelper::checkbox_field( 'select_all_assets', __( 'Select All', 'latepoint-pro-features' ), 'off', false, [ 'class' => 'os-select-all-toggler' ] ); ?>
					</div>
				</div>
			</div>
			<div class="white-box-content">
				<?php
				if ( $assets ) {
					echo '<div class="os-complex-connections-selector">';
					foreach ( $assets as $asset ) {
						$is_active       = $service->is_new_record() ? false : $asset->has_service( $service->id );
						$is_active_value = $is_active ? 'yes' : 'no';
						$active_class    = $is_active ? 'active' : '';
						?>
						<div class="connection <?php echo esc_attr( $active_class ); ?>">
							<div class="connection-i selector-trigger">
								<h3 class="connection-name"><?php echo esc_html( $asset->name ); ?></h3>
								<div class="connection-info"><?php echo esc_html( sprintf( __( 'Qty: %d', 'latepoint-pro-features' ), $asset->quantity ) ); ?></div>
								<?php echo OsFormHelper::hidden_field( 'service[assets][asset_' . $asset->id . '][connected]', $is_active_value, [ 'class' => 'connection-child-is-connected' ] ); ?>
							</div>
						</div>
						<?php
					}
					echo '</div>';
				} else {
					echo '<div class="latepoint-message latepoint-message-subtle">' . esc_html__( 'You have not created any assets yet.', 'latepoint-pro-features' ) . '</div>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save asset connections when a service is saved.
	 *
	 * @param OsServiceModel $service The service being saved.
	 * @param bool $is_new_record Whether this is a new record.
	 * @param array $service_params The submitted service parameters.
	 * @return void
	 */
	public static function save_assets_in_service( $service, $is_new_record, $service_params ) {
		if ( ! isset( $service_params['assets'] ) ) {
			return;
		}

		$connections_to_save   = [];
		$connections_to_remove = [];
		foreach ( $service_params['assets'] as $asset_key => $asset_data ) {
			$asset_id   = str_replace( 'asset_', '', $asset_key );
			$connection = [
				'service_id' => $service->id,
				'asset_id'   => $asset_id,
			];
			if ( $asset_data['connected'] == 'yes' ) {
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
	}

	/**
	 * Assign assets to a booking when it is created.
	 *
	 * @param OsBookingModel $booking The created booking.
	 * @return void
	 */
	public static function assign_assets_for_booking( $booking ) {
		OsAssetsHelper::save_assets_for_booking( $booking );
	}

	/**
	 * Delete asset assignments when a booking is deleted.
	 *
	 * @param int $booking_id The booking ID.
	 * @return void
	 */
	public static function delete_assets_for_booking( $booking_id ) {
		OsAssetsHelper::delete_assets_for_booking( $booking_id );
	}

	/**
	 * Filter booking assets by physical asset availability.
	 *
	 * Hooks into latepoint_get_resources_grouped_by_day (priority 12, after timezone at 10).
	 * For each slot, checks if any connected physical asset has exceeded its capacity.
	 * If so, marks the slot as fully booked.
	 *
	 * @param array $daily_assets Array of BookingResource objects grouped by date.
	 * @param \LatePoint\Misc\BookingRequest $booking_request The booking request.
	 * @param DateTime $date_from Start date.
	 * @param DateTime $date_to End date.
	 * @param array $settings Settings array.
	 * @return array Modified daily assets.
	 */
	public static function filter_by_asset_availability( $daily_assets, $booking_request, $date_from, $date_to, $settings ) {
		if ( empty( $booking_request->service_id ) ) {
			return $daily_assets;
		}

		// Get all physical asset IDs connected to this service.
		$asset_ids = OsAssetsConnectorHelper::get_connected_asset_ids_to_service( $booking_request->service_id );
		if ( empty( $asset_ids ) ) {
			return $daily_assets;
		}

		// Load asset models to get their quantities and agent connections.
		$asset_quantities    = [];
		$asset_agent_ids_map = [];
		foreach ( $asset_ids as $asset_id ) {
			$asset_model = new OsAssetModel( $asset_id );
			if ( $asset_model->status == LATEPOINT_ASSET_STATUS_ACTIVE ) {
				$asset_quantities[ $asset_id ]    = max( 1, intval( $asset_model->quantity ) );
				$asset_agent_ids_map[ $asset_id ] = OsAgentAssetsConnectorHelper::get_connected_agent_ids_to_asset( $asset_id );
			}
		}
		if ( empty( $asset_quantities ) ) {
			return $daily_assets;
		}

		$active_asset_ids = array_keys( $asset_quantities );

		// Batch query: get all bookings in the date range that use these assets.
		$asset_bookings_by_day = self::get_asset_bookings_grouped_by_day(
			$active_asset_ids,
			$date_from->format( 'Y-m-d' ),
			$date_to->format( 'Y-m-d' ),
			$settings['exclude_booking_ids'] ?? []
		);

		// For each day and each BookingAsset, check slot availability.
		foreach ( $daily_assets as $date => $booking_assets ) {
			foreach ( $booking_assets as $booking_asset ) {
				// Determine which assets apply to this agent.
				$agent_id          = $booking_asset->agent_id;
				$applicable_assets = [];
				foreach ( $active_asset_ids as $asset_id ) {
					$connected_agents = $asset_agent_ids_map[ $asset_id ];
					// Empty means all agents; otherwise check if this agent is connected.
					if ( empty( $connected_agents ) || in_array( $agent_id, $connected_agents ) ) {
						$applicable_assets[] = $asset_id;
					}
				}
				if ( empty( $applicable_assets ) ) {
					continue;
				}

				foreach ( $booking_asset->slots as $slot ) {
					// Skip already fully booked slots.
					if ( $slot->booked_capacity >= $slot->max_capacity ) {
						continue;
					}

					$slot_start = $slot->start_time;
					$slot_end   = $slot->start_time + $booking_request->duration;

					// Check each applicable physical asset.
					foreach ( $applicable_assets as $asset_id ) {
						$quantity         = $asset_quantities[ $asset_id ];
						$concurrent_count = 0;

						if ( ! empty( $asset_bookings_by_day[ $date ][ $asset_id ] ) ) {
							foreach ( $asset_bookings_by_day[ $date ][ $asset_id ] as $booked ) {
								// Check if this booked period overlaps with the slot.
								$booked_start = intval( $booked->start_time ) - intval( $booked->buffer_before );
								$booked_end   = intval( $booked->end_time ) + intval( $booked->buffer_after );

								if ( $booked_start < $slot_end && $booked_end > $slot_start ) {
									++$concurrent_count;
								}
							}
						}

						// If this asset is at capacity, block the slot.
						if ( $concurrent_count >= $quantity ) {
							$slot->booked_capacity = $slot->max_capacity;
							break; // No need to check remaining assets.
						}
					}
				}
			}
		}

		return $daily_assets;
	}

	/**
	 * Query bookings that consume specific assets in a date range, grouped by day and asset_id.
	 *
	 * @param array $asset_ids Array of asset IDs.
	 * @param string $date_from Start date (Y-m-d).
	 * @param string $date_to End date (Y-m-d).
	 * @param array $exclude_booking_ids Booking IDs to exclude.
	 * @return array Grouped as $result[$date][$asset_id][] = object{start_time, end_time, buffer_before, buffer_after}.
	 */
	private static function get_asset_bookings_grouped_by_day( array $asset_ids, string $date_from, string $date_to, array $exclude_booking_ids = [] ): array {
		global $wpdb;

		$grouped = [];

		if ( empty( $asset_ids ) ) {
			return $grouped;
		}

		$blocking_statuses = OsBookingHelper::get_timeslot_blocking_statuses();
		if ( empty( $blocking_statuses ) ) {
			return $grouped;
		}

		$asset_placeholders  = implode( ',', array_fill( 0, count( $asset_ids ), '%d' ) );
		$status_placeholders = implode( ',', array_fill( 0, count( $blocking_statuses ), '%s' ) );

		$query = 'SELECT b.start_date, b.start_time, b.end_time, b.buffer_before, b.buffer_after, br.asset_id
			FROM ' . LATEPOINT_TABLE_BOOKINGS . ' b
			INNER JOIN ' . LATEPOINT_TABLE_BOOKINGS_ASSETS . " br ON br.booking_id = b.id
			WHERE br.asset_id IN ($asset_placeholders)
			AND b.start_date >= %s
			AND b.start_date <= %s
			AND b.status IN ($status_placeholders)";

		$params = array_merge(
			$asset_ids,
			[ $date_from, $date_to ],
			$blocking_statuses
		);

		if ( ! empty( $exclude_booking_ids ) ) {
			$exclude_placeholders = implode( ',', array_fill( 0, count( $exclude_booking_ids ), '%d' ) );
			$query               .= " AND b.id NOT IN ($exclude_placeholders)";
			$params               = array_merge( $params, $exclude_booking_ids );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with placeholders and prepared below.
		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		if ( $results ) {
			foreach ( $results as $row ) {
				$date     = $row->start_date;
				$asset_id = $row->asset_id;

				if ( ! isset( $grouped[ $date ] ) ) {
					$grouped[ $date ] = [];
				}
				if ( ! isset( $grouped[ $date ][ $asset_id ] ) ) {
					$grouped[ $date ][ $asset_id ] = [];
				}

				$grouped[ $date ][ $asset_id ][] = $row;
			}
		}

		return $grouped;
	}
}
