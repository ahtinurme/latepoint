<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListActivities extends LatePointAbstractActivityAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-activities';
		$this->label       = __( 'List activities', 'latepoint' );
		$this->description = __( 'Returns a paginated list of activity log entries with optional filters.', 'latepoint' );
		$this->permission  = 'activity__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				self::pagination(),
				[
					'entity_type' => [
						'type'        => 'string',
						'description' => __( 'Entity type to filter by (e.g. booking, customer).', 'latepoint' ),
					],
					'entity_id'   => [ 'type' => 'integer' ],
					'date_from'   => [
						'type'   => 'string',
						'format' => 'date',
					],
					'date_to'     => [
						'type'   => 'string',
						'format' => 'date',
					],
				]
			),
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'activities' => [
					'type'  => 'array',
					'items' => $this->activity_output_schema(),
				],
				'total'      => [ 'type' => 'integer' ],
				'page'       => [ 'type' => 'integer' ],
				'per_page'   => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsActivityModel();

		if ( ! empty( $args['entity_type'] ) && ! empty( $args['entity_id'] ) ) {
			$entity_type = sanitize_text_field( $args['entity_type'] );
			$entity_id   = (int) $args['entity_id'];
			switch ( $entity_type ) {
				case 'booking':
					$query->where( [ 'booking_id' => $entity_id ] );
					break;
				case 'customer':
					$query->where( [ 'customer_id' => $entity_id ] );
					break;
				case 'agent':
					$query->where( [ 'agent_id' => $entity_id ] );
					break;
				case 'order':
					$query->where( [ 'order_id' => $entity_id ] );
					break;
			}
		}

		if ( ! empty( $args['date_from'] ) ) {
			$query->where( [ 'created_at >=' => sanitize_text_field( $args['date_from'] ) . ' 00:00:00' ] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$query->where( [ 'created_at <=' => sanitize_text_field( $args['date_to'] ) . ' 23:59:59' ] );
		}

		$activities = ( clone $query )
			->order_by( 'created_at DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		$total      = $query->count();

		return [
			'activities' => array_map( [ $this, 'serialize_activity' ], $activities ),
			'total'      => (int) $total,
			'page'       => $page,
			'per_page'   => $per_page,
		];
	}
}
