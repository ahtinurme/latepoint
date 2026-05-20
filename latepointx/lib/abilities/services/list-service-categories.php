<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListServiceCategories extends LatePointAbstractServiceAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-service-categories';
		$this->label       = __( 'List service categories', 'latepoint' );
		$this->description = __( 'Returns all service categories.', 'latepoint' );
		$this->permission  = 'service__view';
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
			],
		];
	}

	public function execute( array $args ) {
		$categories = ( new OsServiceCategoryModel() )->order_by( 'order_number ASC, name ASC' )->get_results_as_models();
		return [
			'categories' => array_map(
				fn( OsServiceCategoryModel $c ) => [
					'id'   => (int) $c->id,
					'name' => $c->name ?? '',
				],
				$categories
			),
		];
	}
}
