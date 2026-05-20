<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractCustomerAbility extends LatePointAbstractAbility {

	public function serialize_customer( OsCustomerModel $c ): array {
		return [
			'id'         => (int) $c->id,
			'first_name' => $c->first_name ?? '',
			'last_name'  => $c->last_name ?? '',
			'full_name'  => trim( ( $c->first_name ?? '' ) . ' ' . ( $c->last_name ?? '' ) ),
			'email'      => $c->email ?? '',
			'phone'      => $c->phone ?? '',
			'notes'      => $c->notes ?? '',
			'wp_user_id' => (int) ( $c->wordpress_user_id ?? 0 ),
			'status'     => $c->status ?? '',
			'created_at' => ! empty( $c->created_at ) ? date( 'c', strtotime( $c->created_at ) ) : '',
			'updated_at' => ! empty( $c->updated_at ) ? date( 'c', strtotime( $c->updated_at ) ) : '',
		];
	}

	protected function customer_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'         => [ 'type' => 'integer' ],
				'first_name' => [ 'type' => 'string' ],
				'last_name'  => [ 'type' => 'string' ],
				'full_name'  => [ 'type' => 'string' ],
				'email'      => [ 'type' => 'string' ],
				'phone'      => [ 'type' => 'string' ],
				'notes'      => [ 'type' => 'string' ],
				'wp_user_id' => [ 'type' => 'integer' ],
				'status'     => [ 'type' => 'string' ],
				'created_at' => [ 'type' => 'string' ],
				'updated_at' => [ 'type' => 'string' ],
			],
		];
	}
}
