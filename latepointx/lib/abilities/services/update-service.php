<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityUpdateService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-service';
		$this->label       = __( 'Update service', 'latepoint' );
		$this->description = __( 'Updates one or more fields on an existing service. Only provided fields are changed.', 'latepoint' );
		$this->permission  = 'service__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'           => [
					'type'        => 'integer',
					'description' => __( 'Service ID.', 'latepoint' ),
				],
				'name'         => [ 'type' => 'string' ],
				'description'  => [ 'type' => 'string' ],
				'duration'     => [ 'type' => 'integer' ],
				'price'        => [ 'type' => 'number' ],
				'category_id'  => [ 'type' => 'integer' ],
				'color'        => [ 'type' => 'string' ],
				'capacity_min' => [ 'type' => 'integer' ],
				'capacity_max' => [ 'type' => 'integer' ],
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

		if ( isset( $args['name'] ) ) {
			$service->name = sanitize_text_field( $args['name'] );
		}
		if ( isset( $args['description'] ) ) {
			$service->short_description = sanitize_textarea_field( $args['description'] );
		}
		if ( isset( $args['duration'] ) ) {
			$service->duration = (int) $args['duration'];
		}
		if ( isset( $args['price'] ) ) {
			$service->price_min = (float) $args['price'];
		}
		if ( isset( $args['category_id'] ) ) {
			$service->category_id = (int) $args['category_id'];
		}
		if ( isset( $args['color'] ) ) {
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
				__( 'Failed to update service.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $service->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_service( new OsServiceModel( $service->id ) );
	}
}
