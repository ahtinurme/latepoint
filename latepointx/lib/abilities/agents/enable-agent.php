<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityEnableAgent extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/enable-agent';
		$this->label       = __( 'Enable agent', 'latepoint' );
		$this->description = __( 'Activates an agent so they appear on the booking form and can receive new bookings.', 'latepoint' );
		$this->permission  = 'agent__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
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
		$agent->status = LATEPOINT_AGENT_STATUS_ACTIVE;
		if ( ! $agent->save() ) {
			return new WP_Error( 'save_failed', __( 'Failed to enable agent.', 'latepoint' ), [ 'status' => 422 ] );
		}
		return $this->serialize_agent( new OsAgentModel( $agent->id ) );
	}
}
