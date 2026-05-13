<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsWebhooksController' ) ) :


	class OsWebhooksController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/webhooks/';
			$this->vars['page_header']   = __( 'Webhooks', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = [
				'label' => __( 'Webhooks', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( [ 'webhooks', 'for_booking' ] ),
			];
		}



		public function destroy() {
			// Verify CSRF nonce with action parameter
			$this->check_nonce( 'latepoint_destroy_webhook_' . ( $this->params['id'] ?? 'unknown' ) );

			if ( isset( $this->params['id'] ) && ! empty( $this->params['id'] ) ) {
				if ( OsWebhooksHelper::delete( $this->params['id'] ) ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Webhook Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Webhook', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Invalid Hook ID', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					) 
				);
			}
		}

		public function save() {
			// Verify CSRF nonce with action parameter
			$this->check_nonce( 'latepoint_save_webhooks' );

			if ( $this->params['webhooks'] ) {
				foreach ( $this->params['webhooks'] as $webhook ) {
					$validation_errors = OsWebhooksHelper::has_validation_errors( $webhook );
					if ( is_array( $validation_errors ) ) {
						$status        = LATEPOINT_STATUS_ERROR;
						$response_html = implode( ', ', $validation_errors );
					} else {
						if ( OsWebhooksHelper::save( $webhook ) ) {
							$status        = LATEPOINT_STATUS_SUCCESS;
							$response_html = __( 'Webhook Saved', 'latepoint-pro-features' );
						} else {
							$status        = LATEPOINT_STATUS_ERROR;
							$response_html = __( 'Error Saving Webhook', 'latepoint-pro-features' );
						}
					}
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Invalid params', 'latepoint-pro-features' );
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => $status,
						'message' => $response_html,
					) 
				);
			}
		}

		public function index() {
			$this->vars['webhooks'] = OsWebhooksHelper::get_webhooks_arr();
			$this->format_render( __FUNCTION__ );
		}

		public function new_form() {
			$this->vars['webhook'] = [
				'id'      => OsWebhooksHelper::generate_webhook_id(), 
				'url'     => '', 
				'name'    => '', 
				'status'  => 'active',
				'trigger' => 'new_booking',
			];
			$this->set_layout( 'none' );
			$this->format_render( __FUNCTION__ );
		}
	}

endif;
