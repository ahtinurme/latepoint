<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class LatePointAbilityGetBookingStatuses extends LatePointAbstractBookingAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-booking-statuses';
		$this->label       = __( 'Get booking statuses', 'latepoint' );
		$this->description = __( 'Returns the list of valid booking status values and their labels.', 'latepoint' );
		$this->permission  = 'booking__view';
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
				'statuses' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'value' => [ 'type' => 'string' ],
							'label' => [ 'type' => 'string' ],
						],
					],
				],
			],
		];
	}

	public function execute( array $args ) {
		$statuses = [];
		foreach ( OsBookingHelper::get_statuses_list() as $value => $label ) {
			$statuses[] = [
				'value' => $value,
				'label' => $label,
			];
		}
		return [ 'statuses' => $statuses ];
	}
}
