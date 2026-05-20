<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractLocationAbility extends LatePointAbstractAbility {

	public function serialize_location( OsLocationModel $l ): array {
		return [
			'id'          => (int) $l->id,
			'name'        => $l->name ?? '',
			'description' => '',
			'status'      => $l->status ?? '',
			'address'     => $l->full_address ?? '',
			'phone'       => '',
			'email'       => '',
			'category_id' => (int) ( $l->category_id ?? 0 ),
			'created_at'  => ! empty( $l->created_at ) ? date( 'c', strtotime( $l->created_at ) ) : '',
			'updated_at'  => ! empty( $l->updated_at ) ? date( 'c', strtotime( $l->updated_at ) ) : '',
		];
	}

	protected function location_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'status'      => [ 'type' => 'string' ],
				'address'     => [ 'type' => 'string' ],
				'phone'       => [ 'type' => 'string' ],
				'email'       => [ 'type' => 'string' ],
				'category_id' => [ 'type' => 'integer' ],
				'created_at'  => [ 'type' => 'string' ],
				'updated_at'  => [ 'type' => 'string' ],
			],
		];
	}
}
