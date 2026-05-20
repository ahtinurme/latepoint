<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetAgents extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-agents';
		$this->label       = __( 'Get agents', 'latepoint' );
		$this->description = __( 'Returns all agents (staff), optionally filtered by status.', 'latepoint' );
		$this->permission  = 'agent__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'active', 'disabled' ],
					'description' => __( 'Filter by status.', 'latepoint' ),
				],
			],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'agents' => [
					'type'  => 'array',
					'items' => $this->agent_output_schema(),
				],
				'total'  => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$query = new OsAgentModel();
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		$agents = $query->order_by( 'last_name ASC, first_name ASC' )->get_results_as_models();
		return [
			'agents' => array_map( [ $this, 'serialize_agent' ], $agents ),
			'total'  => count( $agents ),
		];
	}
}
