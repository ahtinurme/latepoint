<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractAgentAbility extends LatePointAbstractAbility {

	public function serialize_agent( OsAgentModel $a ): array {
		return [
			'id'         => (int) $a->id,
			'first_name' => $a->first_name ?? '',
			'last_name'  => $a->last_name ?? '',
			'full_name'  => trim( ( $a->first_name ?? '' ) . ' ' . ( $a->last_name ?? '' ) ),
			'email'      => $a->email ?? '',
			'phone'      => $a->phone ?? '',
			'status'     => $a->status ?? '',
			'bio'        => $a->bio ?? '',
			'wp_user_id' => (int) ( $a->wp_user_id ?? 0 ),
			'created_at' => ! empty( $a->created_at ) ? date( 'c', strtotime( $a->created_at ) ) : '',
			'updated_at' => ! empty( $a->updated_at ) ? date( 'c', strtotime( $a->updated_at ) ) : '',
		];
	}

	protected function agent_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer' ],
				'first_name' => [ 'type' => 'string' ],
				'last_name'  => [ 'type' => 'string' ],
				'full_name'  => [ 'type' => 'string' ],
				'email'      => [ 'type' => 'string' ],
				'phone'      => [ 'type' => 'string' ],
				'status'     => [ 'type' => 'string' ],
				'bio'        => [ 'type' => 'string' ],
				'wp_user_id' => [ 'type' => 'integer' ],
				'created_at' => [ 'type' => 'string' ],
				'updated_at' => [ 'type' => 'string' ],
			],
		];
	}
}
