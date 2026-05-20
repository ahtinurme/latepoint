<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityUpdateLocation extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-location';
		$this->label       = __( 'Update location', 'latepoint' );
		$this->description = __( 'Updates one or more fields on an existing location. Only provided fields are changed.', 'latepoint' );
		$this->permission  = 'location__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [
					'type'        => 'integer',
					'description' => __( 'Location ID to update.', 'latepoint' ),
				],
				'name'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'address'     => [ 'type' => 'string' ],
				'phone'       => [ 'type' => 'string' ],
				'email'       => [ 'type' => 'string' ],
				'category_id' => [ 'type' => 'integer' ],
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

		if ( isset( $args['name'] ) ) {
			$location->name = sanitize_text_field( $args['name'] );
		}
		if ( isset( $args['address'] ) ) {
			$location->full_address = sanitize_text_field( $args['address'] );
		}
		if ( isset( $args['category_id'] ) ) {
			$location->category_id = (int) $args['category_id'];
		}

		if ( ! $location->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update location.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $location->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_location( new OsLocationModel( $location->id ) );
	}
}
