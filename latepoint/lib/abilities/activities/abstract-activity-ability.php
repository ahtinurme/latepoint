<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractActivityAbility extends LatePointAbstractAbility {

	public function serialize_activity( OsActivityModel $a ): array {
		return [
			'id'          => (int) $a->id,
			'description' => $a->description ?? '',
			'agent_id'    => (int) $a->agent_id,
			'booking_id'  => (int) $a->booking_id,
			'code'        => $a->code ?? '',
			'created_at'  => ! empty( $a->created_at ) ? date( 'c', strtotime( $a->created_at ) ) : '',
		];
	}

	protected function activity_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'description' => [ 'type' => 'string' ],
				'agent_id'    => [ 'type' => 'integer' ],
				'booking_id'  => [ 'type' => 'integer' ],
				'code'        => [ 'type' => 'string' ],
				'created_at'  => [ 'type' => 'string' ],
			],
		];
	}
}
