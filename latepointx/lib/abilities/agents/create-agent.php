<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateAgent extends LatePointAbstractAgentAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-agent';
		$this->label       = __( 'Create agent', 'latepoint' );
		$this->description = __( 'Creates a new agent (staff member) who can be assigned to services and receive bookings.', 'latepoint' );
		$this->permission  = 'agent__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'first_name' => [
					'type'        => 'string',
					'description' => __( 'First name.', 'latepoint' ),
				],
				'last_name'  => [ 'type' => 'string' ],
				'email'      => [
					'type'   => 'string',
					'format' => 'email',
				],
				'phone'      => [ 'type' => 'string' ],
				'bio'        => [ 'type' => 'string' ],
				'wp_user_id' => [
					'type'        => 'integer',
					'description' => __( 'Link to WordPress user.', 'latepoint' ),
				],
			],
			'required'   => [ 'first_name', 'email' ],
		];
	}

	public function get_output_schema(): array {
		return $this->agent_output_schema();
	}

	public function execute( array $args ) {
		$agent             = new OsAgentModel();
		$agent->first_name = sanitize_text_field( $args['first_name'] );
		$agent->email      = sanitize_email( $args['email'] );
		$agent->status     = LATEPOINT_AGENT_STATUS_ACTIVE;

		if ( ! empty( $args['last_name'] ) ) {
			$agent->last_name = sanitize_text_field( $args['last_name'] );
		}
		if ( ! empty( $args['phone'] ) ) {
			$agent->phone = sanitize_text_field( $args['phone'] );
		}
		if ( ! empty( $args['bio'] ) ) {
			$agent->bio = sanitize_textarea_field( $args['bio'] );
		}
		if ( ! empty( $args['wp_user_id'] ) ) {
			$agent->wp_user_id = (int) $args['wp_user_id'];
		}

		if ( ! $agent->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create agent.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $agent->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_agent( new OsAgentModel( $agent->id ) );
	}
}
