<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetLocation extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-location';
		$this->label       = __( 'Get location', 'latepoint' );
		$this->description = __( 'Returns a single location by ID.', 'latepoint' );
		$this->permission  = 'location__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Location ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->location_output_schema();
	}

	public function execute( array $args ) {
		$location = new OsLocationModel( (int) $args['id'] );
		if ( $location->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Location not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		return $this->serialize_location( $location );
	}
}
