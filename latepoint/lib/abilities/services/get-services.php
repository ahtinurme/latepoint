<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetServices extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-services';
		$this->label       = __( 'Get services', 'latepoint' );
		$this->description = __( 'Returns all services, optionally filtered by status.', 'latepoint' );
		$this->permission  = 'service__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status'      => [
					'type'        => 'string',
					'enum'        => [ 'active', 'disabled' ],
					'description' => __( 'Filter by status.', 'latepoint' ),
				],
				'category_id' => [
					'type'        => 'integer',
					'description' => __( 'Filter by category ID.', 'latepoint' ),
				],
			],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'services' => [
					'type'  => 'array',
					'items' => $this->service_output_schema(),
				],
				'total'    => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$query = new OsServiceModel();
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		if ( ! empty( $args['category_id'] ) ) {
			$query->where( [ 'category_id' => (int) $args['category_id'] ] );
		}
		$services = $query->order_by( 'order_number ASC, name ASC' )->get_results_as_models();
		return [
			'services' => array_map( [ $this, 'serialize_service' ], $services ),
			'total'    => count( $services ),
		];
	}
}
