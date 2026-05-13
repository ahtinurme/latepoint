<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsWhatsAppController' ) ) :


	class OsWhatsAppController extends OsController {



		public function __construct() {
			parent::__construct();

			$this->views_folder        = plugin_dir_path( __FILE__ ) . '../views/whatsapp/';
			$this->vars['page_header'] = __( 'Whatsapp', 'latepoint-pro-features' );
		}

		public function load_template_preview() {
			$action_id   = sanitize_text_field( $this->params['action_id'] );
			$template_id = sanitize_text_field( $this->params['template_id'] );
			if ( empty( $action_id ) || empty( $template_id ) ) {
				wp_send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Data', 'latepoint-pro-features' ),
					) 
				);
			}
			$action = new \LatePoint\Misc\ProcessAction(
				[
					'type' => 'send_whatsapp',
					'id'   => $action_id,
				] 
			);

			$response_html = \OsWhatsappHelper::get_template_preview( $template_id, $action );
			wp_send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => $response_html,
				) 
			);
		}

		public function load_templates_for_action() {
			$html        = '';
			$action_id   = sanitize_text_field( $this->params['action_id'] );
			$template_id = sanitize_text_field( $this->params['template_id'] ?? '' );
			$process_id  = sanitize_text_field( $this->params['process_id'] ?? '' );

			if ( empty( $action_id ) ) {
				wp_send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __( 'Invalid Data', 'latepoint-pro-features' ),
					) 
				);
			}
			$templates_list = \OsWhatsappHelper::get_templates_list();

			if ( empty( $templates_list ) || isset( $templates_list['error'] ) ) {
				$html .= '<div class="no-templates-found-wrapper">';
				$html .= '<div>' . sprintf( __( 'No templates found in your WhatsApp account. %s to learn how to setup WhatsApp connection and add templates.', 'latepoint-pro-features' ), '<a href="https://wpdocs.latepoint.com/sending-whatsapp-messages-in-latepoint/" target="_blank">Click here</a>' ) . '</div>';
				$html .= '</div>';
			} else {

				$action = new \LatePoint\Misc\ProcessAction(
					[
						'type' => 'send_whatsapp',
						'id'   => $action_id,
					] 
				);
				if ( ! empty( $process_id ) ) {
					$process = new OsProcessModel( $process_id );
					$process->build_from_json();
					if ( ! $process->is_new_record() && ! empty( $process->actions ) ) {
						// load action settings from a process
						foreach ( $process->actions as $process_action ) {
							if ( $process_action->id == $action_id ) {
								$action       = $process_action;
								$action->type = 'send_whatsapp';
								break;
							}
						}
					}
				}
				if ( $template_id ) {
					$action->settings['template_id'] = $template_id;
				}
				$html                .= '<div class="latepoint-message latepoint-message-subtle">' . sprintf( __( 'You can use %s as values for the fields below. Make sure to read %s on how to set up your WhatsApp integration.', 'latepoint-pro-features' ), '<a href="#" class="open-template-variables-panel">' . __( 'Smart Variables', 'latepoint-pro-features' ) . '</a>', '<a href="https://wpdocs.latepoint.com/sending-whatsapp-messages-in-latepoint/" target="_blank">' . __( 'this article', 'latepoint-pro-features' ) . '</a>' ) . '</div>';
				$html                .= '<div class="os-row">';
				$html                .= '<div class="os-col-lg-4">';
					$html            .= \OsFormHelper::text_field(
						'process[actions][' . $action->id . '][settings][to_phone]',
						__( 'To Phone Number', 'latepoint-pro-features' ),
						( empty( $action->settings['to_phone'] ) ? '{{customer_phone}}' : $action->settings['to_phone'] ),
						[
							'theme'       => 'simple',
							'placeholder' => __( 'Phone Number', 'latepoint-pro-features' ),
						] 
					);
				$html                .= '</div>';
				$html                .= '<div class="os-col-lg-8">';
					$html            .= \OsFormHelper::select_field(
						'process[actions][' . $action->id . '][settings][template_id]',
						__( 'Template', 'latepoint-pro-features' ),
						\OsWhatsappHelper::get_templates_list(),
						$action->settings['template_id'] ?? '',
						[
							'class'          => 'process-action-type-whatsapp-template-selector',
							'data-action-id' => $action->id,
							'data-route'     => \OsRouterHelper::build_route_name( 'whatsapp', 'load_template_preview' ),
						] 
					);
				$html                .= '</div>';
				$html                .= '</div>';
				$html                .= '<div class="latepoint-whatsapp-template-preview-holder">';
				$selected_template_id = $action->settings['template_id'] ?? $templates_list[0]['value'];
				if ( $selected_template_id ) {
					$html .= \OsWhatsappHelper::get_template_preview( $selected_template_id, $action );
				}
				$html .= '</div>';
			}
			wp_send_json(
				array(
					'status'  => LATEPOINT_STATUS_SUCCESS,
					'message' => $html,
				) 
			);
		}
	}

endif;
