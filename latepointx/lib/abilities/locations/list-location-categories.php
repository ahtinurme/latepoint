<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListLocationCategories extends LatePointAbstractLocationAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-location-categories';
		$this->label       = __( 'List location categories', 'latepoint' );
		$this->description = __( 'Returns all location categories.', 'latepoint' );
		$this->permission  = 'location__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [],
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'categories' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'   => [ 'type' => 'integer' ],
							'name' => [ 'type' => 'string' ],
						],
					],
				],
				'total'      => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$categories = ( new OsLocationCategoryModel() )->order_by( 'name ASC' )->get_results_as_models();
		$items      = array_map(
			fn( $c ) => [
				'id'   => (int) $c->id,
				'name' => $c->name ?? '',
			],
			$categories
		);
		return [
			'categories' => $items,
			'total'      => count( $items ),
		];
	}
}
