<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LatePointAbilityListInvoices extends LatePointAbstractOrderAbility {

	protected function configure(): void {
		$this->id          = 'latepoint/list-invoices';
		$this->label       = __( 'List invoices', 'latepoint' );
		$this->description = __( 'Returns a paginated list of invoices with optional filters.', 'latepoint' );
		$this->permission  = 'booking__view';
		$this->read_only   = true;
	}

	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => array_merge(
				[
					'order_id'    => [ 'type' => 'integer' ],
					'customer_id' => [ 'type' => 'integer' ],
					'status'      => [ 'type' => 'string' ],
				],
				self::pagination()
			),
		];
	}

	public function get_output_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'invoices' => [
					'type'  => 'array',
					'items' => $this->invoice_output_schema(),
				],
				'total'    => [ 'type' => 'integer' ],
				'page'     => [ 'type' => 'integer' ],
				'per_page' => [ 'type' => 'integer' ],
			],
		];
	}

	public function execute( array $args ) {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$query = new OsInvoiceModel();

		if ( ! empty( $args['order_id'] ) ) {
			$query->where( [ 'order_id' => (int) $args['order_id'] ] );
		}
		if ( ! empty( $args['status'] ) ) {
			$query->where( [ 'status' => sanitize_text_field( $args['status'] ) ] );
		}
		if ( ! empty( $args['customer_id'] ) ) {
			global $wpdb;
			$customer_id  = (int) $args['customer_id'];
			$orders_table = LATEPOINT_TABLE_ORDERS;
			$order_ids    = $wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$orders_table} WHERE customer_id = %d", $customer_id )
			);
			if ( empty( $order_ids ) ) {
				return [
					'invoices' => [],
					'total'    => 0,
					'page'     => $page,
					'per_page' => $per_page,
				];
			}
			$query->where_in( 'order_id', $order_ids );
		}

		$total    = ( clone $query )->count();
		$invoices = $query
			->order_by( 'created_at DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();

		return [
			'invoices' => array_map( [ $this, 'serialize_invoice' ], $invoices ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
