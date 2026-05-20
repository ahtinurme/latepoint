<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityRefundTransaction extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/refund-transaction';
		$this->label       = __( 'Refund transaction', 'latepoint' );
		$this->description = __( 'Marks a payment transaction as refunded. Does not process the actual refund through the payment gateway.', 'latepoint' );
		$this->permission  = 'transaction__edit';
		$this->read_only   = false;
		$this->destructive = true;
		$this->idempotent  = false;
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

		$transaction->status = 'refunded';
		if ( ! $transaction->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to refund transaction.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $transaction->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_transaction( new OsTransactionModel( $transaction->id ) );
	}
}
