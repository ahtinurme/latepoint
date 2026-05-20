<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDuplicateService extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/duplicate-service';
		$this->label       = __( 'Duplicate service', 'latepoint' );
		$this->description = __( 'Creates a full copy of an existing service including all its settings and agent assignments.', 'latepoint' );
		$this->permission  = 'service__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'   => [
					'type'        => 'integer',
					'description' => __( 'Service ID to duplicate.', 'latepoint' ),
				],
				'name' => [
					'type'        => 'string',
					'description' => __( 'Name for the new copy (optional — defaults to "Copy of <name>").', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->service_output_schema();
	}

	public function execute( array $args ) {
		$original = new OsServiceModel( (int) $args['id'] );
		if ( $original->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$copy       = new OsServiceModel();
		$copy->name = ! empty( $args['name'] )
			? sanitize_text_field( $args['name'] )
			/* translators: %s: original service name */
			: sprintf( __( 'Copy of %s', 'latepoint' ), $original->name );
		$copy->short_description = $original->short_description;
		$copy->duration          = $original->duration;
		$copy->price_min         = $original->price_min;
		$copy->category_id       = $original->category_id;
		$copy->bg_color          = $original->bg_color;
		$copy->capacity_min      = $original->capacity_min;
		$copy->capacity_max      = $original->capacity_max;
		$copy->status            = LATEPOINT_SERVICE_STATUS_DISABLED;

		if ( ! $copy->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to duplicate service.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $copy->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_service( new OsServiceModel( $copy->id ) );
	}
}
