<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityDeleteAgent extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/delete-agent';
		$this->label       = __( 'Delete agent', 'latepoint' );
		$this->description = __( 'Permanently deletes an agent and all their associated bookings. This cannot be undone.', 'latepoint' );
		$this->permission  = 'agent__delete';
		$this->read_only   = false;
		$this->destructive = true;
		$this->idempotent  = false;
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
		return [
			'type'       => 'object',
			'properties' => [
				'deleted' => [ 'type' => 'boolean' ],
				'id'      => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$agent = new OsAgentModel( (int) $args['id'] );
		if ( $agent->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Agent not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$id = (int) $agent->id;
		if ( ! $agent->delete() ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete agent.', 'latepoint' ), [ 'status' => 500 ] );
		}

		return [
			'deleted' => true,
			'id'      => $id,
		];
	}
}
