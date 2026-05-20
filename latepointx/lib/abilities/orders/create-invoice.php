<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityCreateInvoice extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/create-invoice';
		$this->label       = __( 'Create invoice', 'latepoint' );
		$this->description = __( 'Creates a new invoice for an existing order. Does not process any payment.', 'latepoint' );
		$this->permission  = 'booking__create';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = false;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'order_id'        => [
					'type'        => 'integer',
					'description' => __( 'Order ID.', 'latepoint' ),
				],
				'charge_amount'   => [ 'type' => 'number' ],
				'payment_portion' => [ 'type' => 'string' ],
				'due_at'          => [
					'type'   => 'string',
					'format' => 'date',
				],
				'status'          => [ 'type' => 'string' ],
			],
			'required'   => [ 'order_id' ],
		];
	}

	public function get_output_schema(): array {
		return $this->invoice_output_schema();
	}

	public function execute( array $args ) {
		$invoice           = new OsInvoiceModel();
		$invoice->order_id = (int) $args['order_id'];

		if ( ! empty( $args['charge_amount'] ) ) {
			$invoice->charge_amount = (float) $args['charge_amount'];
		}
		if ( ! empty( $args['payment_portion'] ) ) {
			$invoice->payment_portion = sanitize_text_field( $args['payment_portion'] );
		}
		if ( ! empty( $args['due_at'] ) ) {
			$invoice->due_at = sanitize_text_field( $args['due_at'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$invoice->status = sanitize_text_field( $args['status'] );
		}

		if ( ! $invoice->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to create invoice.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $invoice->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_invoice( new OsInvoiceModel( $invoice->id ) );
	}
}
