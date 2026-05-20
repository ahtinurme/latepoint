<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetActivity extends LatePointAbstractActivityAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-activity';
		$this->label       = __( 'Get activity', 'latepoint' );
		$this->description = __( 'Returns a single activity log entry by ID.', 'latepoint' );
		$this->permission  = 'activity__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Activity ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->activity_output_schema();
	}

	public function execute( array $args ) {
		$activity = new OsActivityModel( (int) $args['id'] );
		if ( $activity->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Activity not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		return $this->serialize_activity( $activity );
	}
}
