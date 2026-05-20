<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityRemoveOffPeriod extends LatePointAbstractCalendarAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/remove-off-period';
		$this->label       = __( 'Remove off period', 'latepoint' );
		$this->description = __( 'Permanently deletes an off/blocked period, making those times available for bookings again.', 'latepoint' );
		$this->permission  = 'agent__edit';
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
					'description' => __( 'Off period ID to remove.', 'latepoint' ),
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
		$period = new OsOffPeriodModel( (int) $args['id'] );
		if ( $period->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Off period not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$id = (int) $period->id;
		if ( ! $period->delete() ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete off period.', 'latepoint' ), [ 'status' => 500 ] );
		}

		return [
			'deleted' => true,
			'id'      => $id,
		];
	}
}
