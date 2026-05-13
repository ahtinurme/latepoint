<?php 

class OsMessagesHelper {

	public static function count_new_messages_for_customer( $customer_id ) {
		$unread_count  = 0;
		$booking_model = new OsBookingModel();
		$booking_ids   = $booking_model->select( 'id' )->where( [ 'customer_id' => $customer_id ] )->get_results();
		if ( $booking_ids ) {
			$booking_ids_arr = array_map(
				function ( $b ) {
					return $b->id;
				},
				$booking_ids 
			);
			if ( empty( $booking_ids_arr ) ) {
				return 0;
			}
			$message_model = new OsMessageModel();
			$unread_count  = $message_model->where(
				[
					'booking_id'      => $booking_ids_arr,
					'is_read'         => false,
					'author_type != ' => 'customer',
				] 
			)->count();
		} else {
			$booking_ids = false;
		}
		return $unread_count;
	}

	public static function count_new_messages_for_logged_in_backend_user() {
		$message_model = new OsMessageModel();
		$unread_count  = $message_model->filter_allowed_records()->where(
			[
				'is_read'     => false,
				'author_type' => 'customer',
			] 
		)->count();
		return $unread_count;
	}

	public static function set_messages_as_read( $booking_id ) {
		$messages = new OsMessageModel();
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $messages->table_name . ' SET is_read = true WHERE is_read != true AND booking_id = %d', $booking_id ) );
	}

	public static function get_messages_for_booking_id( $booking_id ) {
		$message_model = new OsMessageModel();
		$messages      = $message_model->where( [ 'booking_id' => $booking_id ] )->order_by( 'created_at asc' )->get_results_as_models();
		return $messages;
	}

	public static function get_message_attachment_link( $message_id ) {
		return OsRouterHelper::build_admin_post_link( [ 'messages', 'view_attachment' ], [ 'message_id' => $message_id ] );
	}

	public static function get_conversations_list( $for_customer = false ) {
		$conversations = [];
		$message_model = new OsMessageModel();

		if ( $for_customer ) {
			$customer_id = OsAuthHelper::get_logged_in_customer_id();
			if ( ! $customer_id ) {
				return $conversations;
			}
			$message_model->join( LATEPOINT_TABLE_BOOKINGS, [ 'id' => LATEPOINT_TABLE_MESSAGES . '.booking_id' ] )->where( [ LATEPOINT_TABLE_BOOKINGS . '.customer_id' => $customer_id ] );
		} else {
			$message_model->filter_allowed_records();
		}
		$booking_ids = $message_model->select( 'booking_id' )->group_by( 'booking_id' )->get_results();

		if ( $booking_ids ) {
			$booking_ids_arr = array_map(
				function ( $b ) {
					return $b->booking_id;
				},
				$booking_ids 
			);
			foreach ( $booking_ids_arr as $booking_id ) {
				$last_message_for_booking = new OsMessageModel();
				$last_message_for_booking = $last_message_for_booking->where( [ 'booking_id' => $booking_id ] )->order_by( 'id desc' )->set_limit( 1 )->get_results_as_models();
				if ( $last_message_for_booking ) {
					$booking = new OsBookingModel( $booking_id );
					if ( $for_customer ) {
						$is_read = ! ( ! $last_message_for_booking->is_read && $last_message_for_booking->author_type != 'customer' );
					} else {
						$is_read = ! ( ! $last_message_for_booking->is_read && $last_message_for_booking->author_type == 'customer' );
					}
					$conversations[] = [
						'booking'      => $booking,
						'last_message' => $last_message_for_booking,
						'is_read'      => $is_read,
					];
				}
			}
		}

		if ( $conversations ) {
			array_multisort( array_column( $conversations, 'is_read' ), SORT_ASC, $conversations );
		}

		return $conversations;
	}

	public static function count_unread_messages_for_booking( $booking_id, $for_author_type ) {
		$message_model     = new OsMessageModel();
		$author_type_query = ( $for_author_type == 'customer' ) ? [ 'author_type !=' => 'customer' ] : [ 'author_type' => 'customer' ];
		$unread_count      = $message_model->where( $author_type_query )->where(
			[
				'is_read'    => 0,
				'booking_id' => $booking_id,
			] 
		)->count();
		return $unread_count;
	}


	public static function send_message_notification_to_customer( $message_model ) {
		$booking  = $message_model->booking;
		$agent    = $booking->agent;
		$customer = $booking->customer;
		$order    = $booking->get_order();

		$to = $customer->email;
	
		$subject = self::email_notification_customer_has_new_message_subject();
		$content = self::email_notification_customer_has_new_message_content();

		$subject = OsReplacerHelper::replace_all_vars(
			$subject,
			array(
				'customer' => $customer,
				'agent'    => $agent,
				'booking'  => $booking,
				'order'    => $order,
			) 
		);
		$content = OsReplacerHelper::replace_all_vars(
			$content,
			array(
				'customer' => $customer,
				'agent'    => $agent,
				'booking'  => $booking,
				'order'    => $order,
			) 
		);

		if ( $message_model->content_type == LATEPOINT_MESSAGE_CONTENT_TYPE_ATTACHMENT ) {
			$content = $content = str_replace( '{{message_content}}', __( 'File Attachment', 'latepoint-pro-features' ), $content );
		} else {
			$content = str_replace( '{{message_content}}', $message_model->content, $content );
		}
		$mailer = new OsMailer();

		wp_mail( $to, $subject, $content, $mailer->get_headers() );
	}

	public static function send_message_notification_to_agent( $message_model ) {
		$booking  = $message_model->booking;
		$agent    = $booking->agent;
		$customer = $booking->customer;

		$order = $booking->get_order();

		$to = $agent->email;
		if ( ! empty( $agent->extra_emails ) ) {
			$to .= ', ' . $agent->extra_emails;
		}

		$subject = self::email_notification_agent_has_new_message_subject();
		$content = self::email_notification_agent_has_new_message_content();

		$subject = OsReplacerHelper::replace_all_vars(
			$subject,
			array(
				'customer' => $customer,
				'agent'    => $agent,
				'booking'  => $booking,
				'order'    => $order,
			) 
		);
		$content = OsReplacerHelper::replace_all_vars(
			$content,
			array(
				'customer' => $customer,
				'agent'    => $agent,
				'booking'  => $booking,
				'order'    => $order,
			) 
		);

		if ( $message_model->content_type == LATEPOINT_MESSAGE_CONTENT_TYPE_ATTACHMENT ) {
			$content = $content = str_replace( '{{message_content}}', __( 'File Attachment', 'latepoint-pro-features' ), $content );
		} else {
			$content = str_replace( '{{message_content}}', $message_model->content, $content );
		}

		$mailer = new OsMailer();
		wp_mail( $to, $subject, $content, $mailer->get_headers() );
	}

	public static function email_notification_customer_has_new_message_subject() {
		$default = __( 'You have a new message', 'latepoint-pro-features' );
		return OsSettingsHelper::get_settings_value( 'email_notification_customer_has_new_message_subject', $default );
	}

	public static function email_notification_agent_has_new_message_subject() {
		$default = __( 'You have a new message', 'latepoint-pro-features' );
		return OsSettingsHelper::get_settings_value( 'email_notification_agent_has_new_message_subject', $default );
	}

	public static function email_notification_customer_has_new_message_content() {
		$default = 'You have a new message from {{agent_full_name}} for {{service_name}} on {{start_date}}. Message says: <div style="padding-left:20px;margin:20px 0px;border-left:2px solid #ccc;color:#888">{{message_content}}</div>';
		$content = OsSettingsHelper::get_settings_value( 'email_notification_customer_has_new_message_content', $default );
		return $content;
	}

	public static function email_notification_agent_has_new_message_content() {
		$default = 'You have a new message from {{customer_full_name}} for {{service_name}} on {{start_date}}. Message says: <div style="padding-left:20px;margin:20px 0px;border-left:2px solid #ccc;color:#888">{{message_content}}</div>';
		$content = OsSettingsHelper::get_settings_value( 'email_notification_agent_has_new_message_content', $default );
		return $content;
	}
}
