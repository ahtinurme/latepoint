<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDeleteService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/delete-service';
		$this->label       = __( 'Delete service', 'latepoint' );
		$this->description = __( 'Permanently deletes a service and all its associated bookings. This cannot be undone.', 'latepoint' );
		$this->permission  = 'service__delete';
		$this->read_only   = false;
		$this->destructive = true;
		$this->idempotent  = false;
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
		return [
			'type'       => 'object',
			'properties' => [
				'deleted' => [ 'type' => 'boolean' ],
				'id'      => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$service = new OsServiceModel( (int) $args['id'] );
		if ( $service->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$id = (int) $service->id;
		if ( ! $service->delete() ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete service.', 'latepoint' ), [ 'status' => 500 ] );
		}

		return [
			'deleted' => true,
			'id'      => $id,
		];
	}
}
