<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetTotalCustomersCount extends LatePointAbstractCustomerAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-total-customers-count';
		$this->label       = __( 'Get total customers count', 'latepoint' );
		$this->description = __( 'Returns the total number of customers in the system.', 'latepoint' );
		$this->permission  = 'customer__view';
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
			'properties' => [ 'count' => [ 'type' => 'integer' ] ],
		];
	}

	public function execute( array $args ) {
		return [ 'count' => (int) ( new OsCustomerModel() )->count() ];
	}
}
