<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetLocationAgents extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-location-agents';
		$this->label       = __( 'Get location agents', 'latepoint' );
		$this->description = __( 'Returns all agents assigned to a location.', 'latepoint' );
		$this->permission  = 'location__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'location_id' => [
					'type'        => 'integer',
					'description' => __( 'Location ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'location_id' ],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'agents' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'         => [ 'type' => 'integer' ],
							'first_name' => [ 'type' => 'string' ],
							'last_name'  => [ 'type' => 'string' ],
							'full_name'  => [ 'type' => 'string' ],
							'email'      => [ 'type' => 'string' ],
						],
					],
				],
				'total'  => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$location = new OsLocationModel( (int) $args['location_id'] );
		if ( $location->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Location not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$connector  = new OsConnectorModel();
		$agent_rows = $connector
			->select( 'agent_id' )
			->where( [ 'location_id' => (int) $args['location_id'] ] )
			->group_by( 'agent_id' )
			->get_results();

		$agents = [];
		if ( $agent_rows ) {
			foreach ( $agent_rows as $row ) {
				$agent = new OsAgentModel( (int) $row->agent_id );
				if ( ! $agent->is_new_record() ) {
					$agents[] = [
						'id'         => (int) $agent->id,
						'first_name' => $agent->first_name ?? '',
						'last_name'  => $agent->last_name ?? '',
						'full_name'  => trim( ( $agent->first_name ?? '' ) . ' ' . ( $agent->last_name ?? '' ) ),
						'email'      => $agent->email ?? '',
					];
				}
			}
		}

		return [
			'agents' => $agents,
			'total'  => count( $agents ),
		];
	}
}
