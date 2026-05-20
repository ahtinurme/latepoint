<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDisableService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/disable-service';
		$this->label       = __( 'Disable service', 'latepoint' );
		$this->description = __( 'Disables a service so it no longer appears on the booking form. Existing bookings are not affected.', 'latepoint' );
		$this->permission  = 'service__edit';
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
					'description' => __( 'Service ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->service_output_schema();
	}

	public function execute( array $args ) {
		$service = new OsServiceModel( (int) $args['id'] );
		if ( $service->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		$service->status = LATEPOINT_SERVICE_STATUS_DISABLED;
		if ( ! $service->save() ) {
			return new WP_Error( 'save_failed', __( 'Failed to disable service.', 'latepoint' ), [ 'status' => 422 ] );
		}
		return $this->serialize_service( new OsServiceModel( $service->id ) );
	}
}
