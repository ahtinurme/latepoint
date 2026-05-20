<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetServiceAgents extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-service-agents';
		$this->label       = __( 'Get service agents', 'latepoint' );
		$this->description = __( 'Returns agents (staff) assigned to a service.', 'latepoint' );
		$this->permission  = 'service__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'service_id' => [
					'type'        => 'integer',
					'description' => __( 'Service ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'service_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'agents' => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
			],
		];
	}

	public function execute( array $args ) {
		$service = new OsServiceModel( (int) $args['service_id'] );
		if ( $service->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$agents = ( new OsAgentModel() )
			->join( LATEPOINT_TABLE_AGENTS_SERVICES, [ 'agent_id' => LATEPOINT_TABLE_AGENTS . '.id' ] )
			->where( [ LATEPOINT_TABLE_AGENTS_SERVICES . '.service_id' => $service->id ] )
			->get_results_as_models();

		return [ 'agents' => array_map( [ $this, 'serialize_agent' ], $agents ) ];
	}

	private function serialize_agent( OsAgentModel $a ): array {
		return [
			'id'         => (int) $a->id,
			'first_name' => $a->first_name ?? '',
			'last_name'  => $a->last_name ?? '',
			'full_name'  => trim( ( $a->first_name ?? '' ) . ' ' . ( $a->last_name ?? '' ) ),
			'email'      => $a->email ?? '',
			'phone'      => $a->phone ?? '',
			'status'     => $a->status ?? '',
			'bio'        => $a->bio ?? '',
			'wp_user_id' => (int) ( $a->wp_user_id ?? 0 ),
			'created_at' => ! empty( $a->created_at ) ? date( 'c', strtotime( $a->created_at ) ) : '',
			'updated_at' => ! empty( $a->updated_at ) ? date( 'c', strtotime( $a->updated_at ) ) : '',
		];
	}
}
