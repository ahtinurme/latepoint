<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListLocations extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-locations';
		$this->label       = __( 'List locations', 'latepoint' );
		$this->description = __( 'Returns all locations, optionally filtered by status.', 'latepoint' );
		$this->permission  = 'location__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'active', 'disabled' ],
					'description' => __( 'Filter by location status.', 'latepoint' ),
				],
			],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'locations' => [
					'type'  => 'array',
					'items' => $this->location_output_schema(),
				],
				'total'     => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$query = new OsLocationModel();
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		$locations = $query->order_by( 'name ASC' )->get_results_as_models();
		return [
			'locations' => array_map( [ $this, 'serialize_location' ], $locations ),
			'total'     => count( $locations ),
		];
	}
}
