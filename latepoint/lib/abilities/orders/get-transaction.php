<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetTransaction extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-transaction';
		$this->label       = __( 'Get transaction', 'latepoint' );
		$this->description = __( 'Returns a single transaction by ID.', 'latepoint' );
		$this->permission  = 'transaction__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Transaction ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->transaction_output_schema();
	}

	public function execute( array $args ) {
		$transaction = new OsTransactionModel( (int) $args['id'] );
		if ( $transaction->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Transaction not found.', 'latepoint' ), [ 'status' => 404 ] );
		}
		return $this->serialize_transaction( $transaction );
	}
}
