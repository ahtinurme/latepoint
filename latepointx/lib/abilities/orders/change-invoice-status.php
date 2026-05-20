<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityChangeInvoiceStatus extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/change-invoice-status';
		$this->label       = __( 'Change invoice status', 'latepoint' );
		$this->description = __( 'Changes the payment status of an invoice (e.g. paid, unpaid, partially paid).', 'latepoint' );
		$this->permission  = 'booking__edit';
		$this->read_only   = false;
		$this->destructive = false;
		$this->idempotent  = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'     => [
					'type'        => 'integer',
					'description' => __( 'Invoice ID.', 'latepoint' ),
				],
				'status' => [
					'type'        => 'string',
					'description' => __( 'New invoice status.', 'latepoint' ),
				],
			],
			'required'   => [ 'id', 'status' ],
		];
	}

	public function get_output_schema(): array {
		return $this->invoice_output_schema();
	}

	public function execute( array $args ) {
		$invoice = new OsInvoiceModel( (int) $args['id'] );
		if ( $invoice->is_new_record() ) {
			return new WP_Error( 'not_found', __( 'Invoice not found.', 'latepoint' ), [ 'status' => 404 ] );
		}

		$invoice->status = sanitize_text_field( $args['status'] );
		if ( ! $invoice->save() ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to update invoice status.', 'latepoint' ),
				WP_DEBUG ? [ 'errors' => $invoice->get_error_messages() ] : [ 'status' => 422 ]
			);
		}

		return $this->serialize_invoice( new OsInvoiceModel( $invoice->id ) );
	}
}
