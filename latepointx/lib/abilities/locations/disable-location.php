<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDisableLocation extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/disable-location';
		$this->label       = __( 'Disable location', 'latepoint' );
		$this->description = __( 'Disables a location so it no longer appears on the booking form. Existing bookings are not affected.', 'latepoint' );
		$this->permission  = 'location__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
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

		$location->status = LATEPOINT_LOCATION_STATUS_DISABLED;
		if ( ! $location->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to disable location.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $location->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_location( new OsLocationModel( $location->id ) );
	}
}
