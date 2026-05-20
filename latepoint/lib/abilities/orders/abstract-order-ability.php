<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class LatePointAbstractOrderAbility extends LatePointAbstractAbility {

	public function serialize_order( OsOrderModel $o ): array {
		return [
			'id'                 => (int) $o->id,
			'status'             => $o->status ?? '',
			'payment_status'     => $o->payment_status ?? '',
			'fulfillment_status' => $o->fulfillment_status ?? '',
			'customer_id'        => (int) ( $o->customer_id ?? 0 ),
			'subtotal'           => (float) ( $o->subtotal ?? 0 ),
			'total'              => (float) ( $o->total ?? 0 ),
			'notes'              => $o->customer_comment ?? '',
			'created_at'         => ! empty( $o->created_at ) ? date( 'c', strtotime( $o->created_at ) ) : '',
			'updated_at'         => ! empty( $o->updated_at ) ? date( 'c', strtotime( $o->updated_at ) ) : '',
		];
	}

	public function serialize_invoice( OsInvoiceModel $i ): array {
		$order       = $i->order_id ? new OsOrderModel( (int) $i->order_id ) : new OsOrderModel();
		$customer_id = ( $order && ! $order->is_new_record() ) ? (int) $order->customer_id : 0;
		return [
			'id'          => (int) $i->id,
			'order_id'    => (int) ( $i->order_id ?? 0 ),
			'customer_id' => $customer_id,
			'status'      => $i->status ?? '',
			'subtotal'    => (float) ( $i->charge_amount ?? 0 ),
			'total'       => (float) ( $i->charge_amount ?? 0 ),
			'due_date'    => ! empty( $i->due_at ) ? date( 'c', strtotime( $i->due_at ) ) : '',
			'created_at'  => ! empty( $i->created_at ) ? date( 'c', strtotime( $i->created_at ) ) : '',
		];
	}

	public function serialize_transaction( OsTransactionModel $t ): array {
		return [
			'id'             => (int) $t->id,
			'order_id'       => (int) ( $t->order_id ?? 0 ),
			'customer_id'    => (int) ( $t->customer_id ?? 0 ),
			'status'         => $t->status ?? '',
			'payment_method' => $t->payment_method ?? '',
			'amount'         => (float) ( $t->amount ?? 0 ),
			'created_at'     => ! empty( $t->created_at ) ? date( 'c', strtotime( $t->created_at ) ) : '',
		];
	}

	protected function order_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'                 => [ 'type' => 'integer' ],
				'status'             => [ 'type' => 'string' ],
				'payment_status'     => [ 'type' => 'string' ],
				'fulfillment_status' => [ 'type' => 'string' ],
				'customer_id'        => [ 'type' => 'integer' ],
				'subtotal'           => [ 'type' => 'number' ],
				'total'              => [ 'type' => 'number' ],
				'notes'              => [ 'type' => 'string' ],
				'created_at'         => [ 'type' => 'string' ],
				'updated_at'         => [ 'type' => 'string' ],
			],
		];
	}

	protected function invoice_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'          => [ 'type' => 'integer' ],
				'order_id'    => [ 'type' => 'integer' ],
				'customer_id' => [ 'type' => 'integer' ],
				'status'      => [ 'type' => 'string' ],
				'subtotal'    => [ 'type' => 'number' ],
				'total'       => [ 'type' => 'number' ],
				'due_date'    => [ 'type' => 'string' ],
				'created_at'  => [ 'type' => 'string' ],
			],
		];
	}

	protected function transaction_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'             => [ 'type' => 'integer' ],
				'order_id'       => [ 'type' => 'integer' ],
				'customer_id'    => [ 'type' => 'integer' ],
				'status'         => [ 'type' => 'string' ],
				'payment_method' => [ 'type' => 'string' ],
				'amount'         => [ 'type' => 'number' ],
				'created_at'     => [ 'type' => 'string' ],
			],
		];
	}
}
