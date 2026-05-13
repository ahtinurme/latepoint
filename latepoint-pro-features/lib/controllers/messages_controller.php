<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsMessagesController' ) ) :


	class OsMessagesController extends OsController {



		public function __construct() {
			parent::__construct();
	  
			$this->views_folder = plugin_dir_path( __FILE__ ) . '../views/messages/';

			$this->action_access['customer'] = array_merge( $this->action_access['customer'], [ 'check_unread_messages','view_attachment','messages_for_booking', 'create' ] );

			$this->vars['page_header']   = __( 'Messages', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array(
				'label' => __( 'Messages', 'latepoint-pro-features' ),
				'link'  => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'messages', 'index' ) ),
			);
		}

		public function search_bookings() {
			$query     = $this->params['query'];
			$bookings  = new OsBookingModel();
			$sql_query = '%' . $query . '%';

			$booking_ids   = $bookings->filter_allowed_records()->select( LATEPOINT_TABLE_BOOKINGS . '.id' )->join( LATEPOINT_TABLE_CUSTOMERS, [ 'id' => LATEPOINT_TABLE_BOOKINGS . '.customer_id' ] )->where(
				[
					'OR' => [
						'concat_ws(" ", ' . LATEPOINT_TABLE_CUSTOMERS . '.first_name,' . LATEPOINT_TABLE_CUSTOMERS . '.last_name) LIKE ' => $sql_query,
						LATEPOINT_TABLE_CUSTOMERS . '.email LIKE' => $sql_query,
						LATEPOINT_TABLE_CUSTOMERS . '.phone LIKE' => $sql_query,
						LATEPOINT_TABLE_BOOKINGS . '.id' => $query,
					],
				] 
			)->set_limit( 4 )->get_results( ARRAY_A );
			$bookings      = new OsBookingModel();
			$bookings      = $bookings->where( [ 'id' => array_column( $booking_ids, 'id' ) ] )->get_results_as_models();
			$conversations = [];
			foreach ( $bookings as $booking ) {
				$last_message_for_booking = new OsMessageModel();
				$last_message_for_booking = $last_message_for_booking->where( [ 'booking_id' => $booking->id ] )->order_by( 'id desc' )->set_limit( 1 )->get_results_as_models();
				$conversations[]          = [
					'booking'      => $booking,
					'last_message' => $last_message_for_booking,
				];
			}
			$this->vars['conversations'] = $conversations;
			$this->format_render( 'conversations' );
		}

		public function messages_with_info_for_booking() {
			if ( ! isset( $this->params['selected_booking_id'] ) || empty( $this->params['selected_booking_id'] ) ) {
				return;
			}
			$selected_booking_id                          = $this->params['selected_booking_id'];
			$this->vars['booking']                        = new OsBookingModel( $selected_booking_id );
			$this->vars['selected_conversation_messages'] = ( $selected_booking_id ) ? OsMessagesHelper::get_messages_for_booking_id( $selected_booking_id ) : false;
			$this->vars['selected_booking_id']            = $selected_booking_id;
			$this->format_render( __FUNCTION__ );
		}

		public function backend_chat_box() {
			$this->vars['page_header'] = false;
			$conversations             = OsMessagesHelper::get_conversations_list();
			if ( $conversations ) {
				$this->vars['booking']                        = $conversations[0]['booking'];
				$this->vars['selected_booking_id']            = $conversations[0]['booking']->id;
				$this->vars['selected_conversation_messages'] = OsMessagesHelper::get_messages_for_booking_id( $conversations[0]['booking']->id );
				// if unread - update to be read because it will be read on open
				if ( ! $conversations[0]['last_message']->is_read && $conversations[0]['last_message']->author_type == 'customer' ) {
					OsMessagesHelper::set_messages_as_read( $conversations[0]['booking']->id );
				}
			} else {
				$this->vars['booking']                        = false;
				$this->vars['selected_booking_id']            = false;
				$this->vars['selected_conversation_messages'] = false;
			}
			$this->vars['conversations'] = $conversations;
			$this->format_render( __FUNCTION__ );
		}

		public function quick_form_chat_box() {
			if ( ! empty( $this->params['booking_id'] ) ) {
				$booking_id = $this->params['booking_id'];
				$messages   = OsMessagesHelper::get_messages_for_booking_id( $booking_id );
			} else {
				$booking_id = false;
				$messages   = [];
			}
			$this->vars['booking_id'] = $booking_id;
			$this->vars['messages']   = $messages;
		
			$this->format_render( __FUNCTION__ );
		}

		public function check_unread_messages() {
			$booking_id       = $this->params['booking_id'];
			$viewer_user_type = $this->params['viewer_user_type'];
			$unread_count     = OsMessagesHelper::count_unread_messages_for_booking( $booking_id, $viewer_user_type );
			$response_html    = ( $unread_count > 0 ) ? 'yes' : 'no';

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => $response_html,
					) 
				);
			}
		}

		public function view_attachment() {
			$message_id = $this->params['message_id'];
			$message    = new OsMessageModel( $message_id );
			if ( empty( $message->id ) ) {
				return;
			}

			$booking = new OsBookingModel( $message->booking_id );
			if ( empty( $booking->id ) ) {
				return;
			}

			if ( OsRolesHelper::can_user_make_action_on_model_record( $booking, 'view' ) ) {
				$file_path         = get_attached_file( $message->content );
				$file_content_type = get_post_mime_type( $message->content );
				$file_name         = basename( $file_path );
				header( 'Content-Type: ' . $file_content_type );
				header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
				header( 'Pragma: no-cache' );
				readfile( $file_path );
			}
			return;
		}

		public function messages_for_booking() {
			if ( empty( $this->params['booking_id'] ) || empty( $this->params['viewer_user_type'] ) ) {
				return;
			}

			$booking = new OsBookingModel( $this->params['booking_id'] );
			if ( empty( $booking ) || ! OsRolesHelper::can_user_make_action_on_model_record( $booking, 'view' ) ) {
				$this->send_json(
					array(
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => 'Not Allowed',
					) 
				);
			}


			if ( $this->params['viewer_user_type'] == LATEPOINT_USER_TYPE_CUSTOMER ) {
				$author_type = LATEPOINT_USER_TYPE_CUSTOMER;
			} elseif ( $this->params['viewer_user_type'] == 'backend_user' ) {
				$author_type = OsAuthHelper::get_current_user()->backend_user_type;
			}
			if ( empty( $author_type ) ) {
				return;
			}

			$messages = OsMessagesHelper::get_messages_for_booking_id( $booking->id );
			if ( ! empty( $messages ) ) {
				$last_message = $messages[ count( $messages ) - 1 ];
				if ( $last_message->author_type != $author_type && ! $last_message->is_read ) {
					OsMessagesHelper::set_messages_as_read( $booking->id );
				}
			}
			$this->vars['messages']   = $messages;
			$this->vars['booking_id'] = $booking->id;

			$this->format_render( __FUNCTION__ );
		}

		public function create() {
			$message_data = $this->params['message'];
			$is_valid     = false;
			if ( $message_data && ! empty( $message_data['author_type'] ) && ! empty( $message_data['booking_id'] ) && ! empty( $message_data['content'] ) && is_numeric( $message_data['booking_id'] ) ) {
				$booking = new OsBookingModel( $message_data['booking_id'] );
				switch ( $message_data['author_type'] ) {
					case 'backend_user':
						$author_id = OsAuthHelper::get_highest_current_user_id();
						// determine which backend user type is logged in and check for permissions
						switch ( OsAuthHelper::get_current_user()->backend_user_type ) {
							case LATEPOINT_USER_TYPE_ADMIN:
								$is_valid = true;
								break;
							case LATEPOINT_USER_TYPE_CUSTOM:
								if ( OsRolesHelper::are_all_records_allowed( 'agent' ) ) {
									$is_valid = true;
								} else {
									$allowed_agent_ids = OsRolesHelper::get_allowed_records( 'agent' );
									if ( $author_id && ! empty( $allowed_agent_ids ) && in_array( $booking->agent_id, $allowed_agent_ids ) ) {
										$is_valid = true;
									}
								}
								break;
							case LATEPOINT_USER_TYPE_AGENT:
								if ( $author_id && $author_id == $booking->agent_id ) {
									$is_valid = true;
								}
								break;
						}
						break;
					case LATEPOINT_USER_TYPE_CUSTOMER:
						$author_id = OsAuthHelper::get_logged_in_customer_id();
						if ( $author_id && $author_id == $booking->customer_id ) {
							$is_valid = true;
						}
						break;
				}
			}
			if ( $is_valid ) {
				$message_model = new OsMessageModel();
				$message_model->set_data(
					[
						'content'      => $message_data['content'],
						'content_type' => $message_data['content_type'] ?? LATEPOINT_MESSAGE_CONTENT_TYPE_TEXT,
						'author_id'    => $author_id,
						'booking_id'   => $message_data['booking_id'],
						'author_type'  => ( $message_data['author_type'] == LATEPOINT_USER_TYPE_CUSTOMER ) ? LATEPOINT_USER_TYPE_CUSTOMER : OsAuthHelper::get_current_user()->backend_user_type,
					]
				);
				if ( $message_model->save() ) {
					if ( $message_model->author_type == 'customer' ) {
						OsMessagesHelper::send_message_notification_to_agent( $message_model );
					} else {
						OsMessagesHelper::send_message_notification_to_customer( $message_model );
					}
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Success', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error sending message. Try again later.', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error sending message. Try again later.', 'latepoint-pro-features' );
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
	}
endif;
