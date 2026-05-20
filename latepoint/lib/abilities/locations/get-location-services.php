<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetLocationServices extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-location-services';
		$this->label       = __( 'Get location services', 'latepoint' );
		$this->description = __( 'Returns all services available at a location.', 'latepoint' );
		$this->permission  = 'location__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'location_id' => [
					'type'        => 'integer',
					'description' => __( 'Location ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'location_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'services' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'   => [ 'type' => 'integer' ],
							'name' => [ 'type' => 'string' ],
						],
					],
				],
				'total'    => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$location = new OsLocationModel( (int) $args['location_id'] );
		if ( $location->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Location not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$connector    = new OsConnectorModel();
		$service_rows = $connector
			->select( 'service_id' )
			->where( [ 'location_id' => (int) $args['location_id'] ] )
			->group_by( 'service_id' )
			->get_results();

		$services = [];
		if ( $service_rows ) {
			foreach ( $service_rows as $row ) {
				$service = new OsServiceModel( (int) $row->service_id );
				if ( ! $service->is_new_record() ) {
					$services[] = [
						'id'   => (int) $service->id,
						'name' => $service->name ?? '',
					];
				}
			}
		}

		return [
			'services' => $services,
			'total'    => count( $services ),
		];
	}
}
