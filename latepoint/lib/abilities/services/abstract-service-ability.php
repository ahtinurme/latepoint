<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractServiceAbility extends LatePointAbstractAbility {

	public function serialize_service( OsServiceModel $s ): array {
		return [
			'id'           => (int) $s->id,
			'name'         => $s->name ?? '',
			'description'  => $s->short_description ?? '',
			'status'       => $s->status ?? '',
			'duration'     => (int) ( $s->duration ?? 0 ),
			'price'        => (float) ( $s->price_min ?? 0 ),
			'category_id'  => (int) ( $s->category_id ?? 0 ),
			'color'        => $s->bg_color ?? '',
			'capacity_min' => (int) ( $s->capacity_min ?? 1 ),
			'capacity_max' => (int) ( $s->capacity_max ?? 1 ),
			'created_at'   => ! empty( $s->created_at ) ? date( 'c', strtotime( $s->created_at ) ) : '',
			'updated_at'   => ! empty( $s->updated_at ) ? date( 'c', strtotime( $s->updated_at ) ) : '',
		];
	}

	protected function service_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'           => [ 'type' => 'integer' ],
				'name'         => [ 'type' => 'string' ],
				'description'  => [ 'type' => 'string' ],
				'status'       => [ 'type' => 'string' ],
				'duration'     => [
					'type'        => 'integer',
					'description' => __( 'Duration in minutes.', 'latepoint' ),
				],
				'price'        => [
					'type'        => 'number',
					'description' => __( 'Service price.', 'latepoint' ),
				],
				'category_id'  => [ 'type' => 'integer' ],
				'color'        => [ 'type' => 'string' ],
				'capacity_min' => [ 'type' => 'integer' ],
				'capacity_max' => [ 'type' => 'integer' ],
				'created_at'   => [ 'type' => 'string' ],
				'updated_at'   => [ 'type' => 'string' ],
			],
		];
	}
}
