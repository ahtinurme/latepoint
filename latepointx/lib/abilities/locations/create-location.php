<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateLocation extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-location';
		$this->label       = __( 'Create location', 'latepoint' );
		$this->description = __( 'Creates a new business location where services can be offered and agents can be assigned.', 'latepoint' );
		$this->permission  = 'location__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'        => [
					'type'        => 'string',
					'description' => __( 'Location name.', 'latepoint' ),
				],
				'description' => [ 'type' => 'string' ],
				'address'     => [ 'type' => 'string' ],
				'phone'       => [ 'type' => 'string' ],
				'email'       => [ 'type' => 'string' ],
				'category_id' => [ 'type' => 'integer' ],
				'status'      => [
					'type'    => 'string',
					'enum'    => [ 'active', 'disabled' ],
					'default' => 'active',
				],
			],
			'required'   => [ 'name' ],
		];
	}

	public function get_output_schema(): array {
		return $this->location_output_schema();
	}

	public function execute( array $args ) {
		$location         = new OsLocationModel();
		$location->name   = sanitize_text_field( $args['name'] );
		$location->status = isset( $args['status'] ) ? sanitize_text_field( $args['status'] ) : LATEPOINT_LOCATION_STATUS_ACTIVE;

		if ( isset( $args['address'] ) ) {
			$location->full_address = sanitize_text_field( $args['address'] );
		}
		if ( isset( $args['category_id'] ) ) {
			$location->category_id = (int) $args['category_id'];
		}

		if ( ! $location->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create location.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $location->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_location( new OsLocationModel( $location->id ) );
	}
}
