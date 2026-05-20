<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListTransactions extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-transactions';
		$this->label       = __( 'List transactions', 'latepoint' );
		$this->description = __( 'Returns a paginated list of payment transactions.', 'latepoint' );
		$this->permission  = 'transaction__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'order_id'    => [ 'type' => 'integer' ],
					'customer_id' => [ 'type' => 'integer' ],
				],
				self::pagination()
			),
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'transactions' => [
					'type'  => 'array',
					'items' => $this->transaction_output_schema(),
				],
				'total'        => [ 'type' => 'integer' ],
				'page'         => [ 'type' => 'integer' ],
				'per_page'     => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsTransactionModel();

		if ( ! empty( $args['order_id'] ) ) {
			$query->where( [ 'order_id' => (int) $args['order_id'] ] );
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$query->where( [ 'customer_id' => (int) $args['customer_id'] ] );
		}

		$total        = ( clone $query )->count();
		$transactions = $query
			->order_by( 'created_at DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();

		return [
			'transactions' => array_map( [ $this, 'serialize_transaction' ], $transactions ),
			'total'        => (int) $total,
			'page'         => $page,
			'per_page'     => $per_page,
		];
	}
}
