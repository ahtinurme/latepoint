<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-service';
		$this->label       = __( 'Create service', 'latepoint' );
		$this->description = __( 'Creates a new bookable service that customers can select when making appointments. Requires a name at minimum.', 'latepoint' );
		$this->permission  = 'service__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'name'         => [
					'type'        => 'string',
					'description' => __( 'Service name.', 'latepoint' ),
				],
				'description'  => [ 'type' => 'string' ],
				'duration'     => [
					'type'        => 'integer',
					'description' => __( 'Duration in minutes.', 'latepoint' ),
				],
				'price'        => [
					'type'        => 'number',
					'description' => __( 'Service price.', 'latepoint' ),
				],
				'category_id'  => [ 'type' => 'integer' ],
				'color'        => [ 'type' => 'string' ],
				'capacity_min' => [
					'type'    => 'integer',
					'default' => 1,
				],
				'capacity_max' => [
					'type'    => 'integer',
					'default' => 1,
				],
			],
			'required'   => [ 'name', 'duration' ],
		];
	}

	public function get_output_schema(): array {
		return $this->service_output_schema();
	}

	public function execute( array $args ) {
		$service           = new OsServiceModel();
		$service->name     = sanitize_text_field( $args['name'] );
		$service->duration = (int) $args['duration'];
		$service->status   = LATEPOINT_SERVICE_STATUS_ACTIVE;

		if ( isset( $args['description'] ) ) {
			$service->short_description = sanitize_textarea_field( $args['description'] );
		}
		if ( isset( $args['price'] ) ) {
			$service->price_min = (float) $args['price'];
		}
		if ( ! empty( $args['category_id'] ) ) {
			$service->category_id = (int) $args['category_id'];
		}
		if ( ! empty( $args['color'] ) ) {
			$service->bg_color = sanitize_hex_color( $args['color'] ) ?? sanitize_text_field( $args['color'] );
		}
		if ( isset( $args['capacity_min'] ) ) {
			$service->capacity_min = (int) $args['capacity_min'];
		}
		if ( isset( $args['capacity_max'] ) ) {
			$service->capacity_max = (int) $args['capacity_max'];
		}

		if ( ! $service->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create service.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $service->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_service( new OsServiceModel( $service->id ) );
	}
}
