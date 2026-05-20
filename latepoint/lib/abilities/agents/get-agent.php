<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetAgent extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-agent';
		$this->label       = __( 'Get agent', 'latepoint' );
		$this->description = __( 'Returns a single agent by ID with full profile.', 'latepoint' );
		$this->permission  = 'agent__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Agent ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->agent_output_schema();
	}

	public function execute( array $args ) {
		$agent = new OsAgentModel( (int) $args['id'] );
		if ( $agent->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Agent not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		return $this->serialize_agent( $agent );
	}
}
