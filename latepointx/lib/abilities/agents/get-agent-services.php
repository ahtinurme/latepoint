<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetAgentServices extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-agent-services';
		$this->label       = __( 'Get agent services', 'latepoint' );
		$this->description = __( 'Returns the services offered by a specific agent.', 'latepoint' );
		$this->permission  = 'agent__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'agent_id' => [
					'type'        => 'integer',
					'description' => __( 'Agent ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'agent_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'services' => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
			],
		];
	}

	public function execute( array $args ) {
		$agent = new OsAgentModel( (int) $args['agent_id'] );
		if ( $agent->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Agent not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$services = ( new OsServiceModel() )
			->join( LATEPOINT_TABLE_AGENTS_SERVICES, [ 'service_id' => LATEPOINT_TABLE_SERVICES . '.id' ] )
			->where( [ LATEPOINT_TABLE_AGENTS_SERVICES . '.agent_id' => $agent->id ] )
			->order_by( 'order_number ASC, name ASC' )
			->get_results_as_models();

		return [ 'services' => array_map( [ $this, 'serialize_service' ], $services ) ];
	}

	private function serialize_service( OsServiceModel $s ): array {
		return [
			'id'           => (int) $s->id,
			'name'         => $s->name ?? '',
			'description'  => $s->short_description ?? '',
			'status'       => $s->status ?? '',
			'duration'     => (int) ( $s->duration ?? 0 ),
			'price'        => (float) ( $s->price_min ?? 0 ),
			'category_id'  => (int) ( $s->category_id ?? 0 ),
			'color'        => $s->bg_color ?? '',
			'capacity_min' => (int) ( $s->capacity_min ?? 1 ),
			'capacity_max' => (int) ( $s->capacity_max ?? 1 ),
			'created_at'   => ! empty( $s->created_at ) ? date( 'c', strtotime( $s->created_at ) ) : '',
			'updated_at'   => ! empty( $s->updated_at ) ? date( 'c', strtotime( $s->updated_at ) ) : '',
		];
	}
}
