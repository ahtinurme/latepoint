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
		// The customer filter and the per-agent record scope both narrow the set of allowed
		// order_ids, so they must be intersected into a single where_in(): calling where_in()
		// twice on the same column overwrites the earlier constraint, it does not AND them.
		$order_id_constraint = null;

		if ( ! empty( $args['customer_id'] ) ) {
			global $wpdb;
			$orders_table        = LATEPOINT_TABLE_ORDERS;
			$order_id_constraint = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare( "SELECT id FROM {$orders_table} WHERE customer_id = %d", (int) $args['customer_id'] )
				)
			);
		}

		// Invoices have no own record-scope — restrict to orders the current user may access.
		$allowed_order_ids = $this->allowed_order_ids();
		if ( ! is_null( $allowed_order_ids ) ) {
			$order_id_constraint = is_null( $order_id_constraint )
				? $allowed_order_ids
				: array_values( array_intersect( $order_id_constraint, $allowed_order_ids ) );
		}

		if ( ! is_null( $order_id_constraint ) ) {
			if ( empty( $order_id_constraint ) ) {
				return [
					'invoices' => [],
					'total'    => 0,
					'page'     => $page,
					'per_page' => $per_page,
				];
			}
			$query->where_in( 'order_id', $order_id_constraint );
		}

		$invoices = ( clone $query )
			->order_by( 'created_at DESC' )
			->set_limit( $per_page )
			->set_offset( $offset )
			->get_results_as_models();
		$total    = $query->count();

		return [
			'invoices' => array_map( [ $this, 'serialize_invoice' ], $invoices ),
			'total'    => (int) $total,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}
}
