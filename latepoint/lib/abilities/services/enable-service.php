<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityEnableService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/enable-service';
		$this->label       = __( 'Enable service', 'latepoint' );
		$this->description = __( 'Activates a service so it appears on the booking form and can be booked by customers.', 'latepoint' );
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
		$service->status = LATEPOINT_SERVICE_STATUS_ACTIVE;
		if ( ! $service->save() ) {
			return new WP_Error( 'save_failed', __( 'Failed to enable service.', 'latepoint' ), [ 'status' => 422 ] );
		}
		return $this->serialize_service( new OsServiceModel( $service->id ) );
	}
}
