<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityGetInvoice extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/get-invoice';
		$this->label       = __( 'Get invoice', 'latepoint' );
		$this->description = __( 'Returns a single invoice by ID.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id' => [
					'type'        => 'integer',
					'description' => __( 'Invoice ID.', 'latepoint' ),
				],
			],
			'required'   => [ 'id' ],
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
		return $this->serialize_invoice( $invoice );
	}
}
