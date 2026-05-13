<?php

class OsFeatureWebhooksHelper {

	public static function add_webhook_code( $codes ) {
		$codes['http_request'] = __( 'Webhook Run', 'latepoint-pro-features' );
		return $codes;
	}

	public static function prepare_data_for_run( \LatePoint\Misc\ProcessAction $action ) {
		if ( $action->type != 'trigger_webhook' ) {
			return $action;
		}
		$content = [];

		foreach ( $action->selected_data_objects as $data_object ) {
			$content = array_merge( $content, self::get_vars_for_data_object( $data_object ) );
		}
		$action->prepared_data_for_run['content'] = $content;
		$action->prepared_data_for_run['url']     = esc_html( \OsReplacerHelper::replace_all_vars( $action->settings['url'], $action->replacement_vars ) );
		return $action;
	}

	public static function process_webhook_action( array $result, \LatePoint\Misc\ProcessAction $action ): array {
		if ( $action->type != 'trigger_webhook' ) {
			return $result;
		}

		$result = OsWebhooksHelper::run_webhook( $action->prepared_data_for_run['url'], $action->prepared_data_for_run['content'], $action->prepared_data_for_run['activity_data'] );
		return $result;
	}

	public static function generate_webhook_preview( string $html, \LatePoint\Misc\ProcessAction $action ): string {
		if ( $action->type != 'trigger_webhook' ) {
			return $html;
		}
		$html .= '<div class="action-preview-to"><span class="os-label">' . __( 'URL:', 'latepoint-pro-features' ) . '</span><a href="' . $action->prepared_data_for_run['url'] . '" target="_blank" class="os-value">' . $action->prepared_data_for_run['url'] . '</a></div>';
		$html .= '<pre class="format-json">' . json_encode( $action->prepared_data_for_run['content'], JSON_PRETTY_PRINT ) . '</pre>';
		return $html;
	}

	public static function get_vars_for_data_object( array $data_object ): array {
		$vars = [];
		switch ( $data_object['model'] ) {
			case 'order':
				$order = new OsOrderModel( $data_object['id'] );
				$vars  = $order->get_data_vars();
				$vars  = apply_filters( 'latepoint_webhook_variables_for_order', $vars, $data_object );
				break;
			case 'booking':
				$booking = new OsBookingModel( $data_object['id'] );
				$vars    = $booking->get_data_vars();
				$vars    = apply_filters( 'latepoint_webhook_variables_for_booking', $vars, $data_object );
				break;
			case 'customer':
				$customer = new OsCustomerModel( $data_object['id'] );
				$vars     = $customer->get_data_vars();
				$vars     = apply_filters( 'latepoint_webhook_variables_for_customer', $vars, $data_object );
				break;
			case 'agent':
				$agent = new OsAgentModel( $data_object['id'] );
				$vars  = $agent->get_data_vars();
				$vars  = apply_filters( 'latepoint_webhook_variables_for_agent', $vars, $data_object );
				break;
			case 'transaction':
				$transaction = new OsTransactionModel( $data_object['id'] );
				$vars        = $transaction->get_data_vars();
				$vars        = apply_filters( 'latepoint_webhook_variables_for_transaction', $vars, $data_object );
				break;
			case 'invoice':
				$invoice = new OsInvoiceModel( $data_object['id'] );
				$vars    = $invoice->get_data_vars();
				$vars    = apply_filters( 'latepoint_webhook_variables_for_invoice', $vars, $data_object );
				break;
			case 'payment_request':
				$payment_request = new OsPaymentRequestModel( $data_object['id'] );
				$vars            = $payment_request->get_data_vars();
				$vars            = apply_filters( 'latepoint_webhook_variables_for_payment_request', $vars, $data_object );
				break;
		}
		return apply_filters( 'latepoint_webhook_variables_for_data_object', $vars, $data_object );
	}

	public static function add_webhook_settings( string $html, \LatePoint\Misc\ProcessAction $action ): string {
		if ( $action->type == 'trigger_webhook' ) {
			$html = \OsFormHelper::text_field(
				'process[actions][' . $action->id . '][settings][url]',
				__( 'URL', 'latepoint-pro-features' ),
				$action->settings['url'] ?? '',
				[
					'theme'       => 'bordered',
					'placeholder' => __( 'URL', 'latepoint-pro-features' ),
				] 
			);
		}
		return $html;
	}
}
