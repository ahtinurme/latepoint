<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityUpdateAgent extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/update-agent';
		$this->label       = __( 'Update agent', 'latepoint' );
		$this->description = __( 'Updates one or more fields on an existing agent profile. Only provided fields are changed.', 'latepoint' );
		$this->permission  = 'agent__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [
					'type'        => 'integer',
					'description' => __( 'Agent ID.', 'latepoint' ),
				],
				'first_name' => [ 'type' => 'string' ],
				'last_name'  => [ 'type' => 'string' ],
				'email'      => [
					'type'   => 'string',
					'format' => 'email',
				],
				'phone'      => [ 'type' => 'string' ],
				'bio'        => [ 'type' => 'string' ],
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

		if ( isset( $args['first_name'] ) ) {
			$agent->first_name = sanitize_text_field( $args['first_name'] );
		}
		if ( isset( $args['last_name'] ) ) {
			$agent->last_name = sanitize_text_field( $args['last_name'] );
		}
		if ( isset( $args['email'] ) ) {
			$agent->email = sanitize_email( $args['email'] );
		}
		if ( isset( $args['phone'] ) ) {
			$agent->phone = sanitize_text_field( $args['phone'] );
		}
		if ( isset( $args['bio'] ) ) {
			$agent->bio = sanitize_textarea_field( $args['bio'] );
		}

		if ( ! $agent->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update agent.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $agent->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_agent( new OsAgentModel( $agent->id ) );
	}
}
