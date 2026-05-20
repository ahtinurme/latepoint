<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-service';
		$this->label       = __( 'Get service', 'latepoint' );
		$this->description = __( 'Returns a single service by ID with full detail.', 'latepoint' );
		$this->permission  = 'service__view';
		$this->read_only   = true;
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
		return $this->serialize_service( $service );
	}
}
