<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class OsFeatureMessagesHelper {
	public static function add_messages_vars() {
		echo '<li><span class="var-label">' . __( 'Message Content:', 'latepoint-pro-features' ) . '</span> <span class="var-code os-click-to-copy">{message_content}</span></li>';
	}

	public static function add_messages_link_to_top_bar() {
		if ( ! OsRolesHelper::can_user( 'chat__edit' ) ) {
			return;
		}
		$count_unread               = OsMessagesHelper::count_new_messages_for_logged_in_backend_user();
		$unread_messages_count_html = ( $count_unread > 0 ) ? '<span class="notifications-count">' . $count_unread . '</span>' : '';
		echo '<a href="' . OsRouterHelper::build_link( [ 'messages', 'backend_chat_box' ] ) . '" title="' . __( 'Messages', 'latepoint-pro-features' ) . '" class="latepoint-top-iconed-link latepoint-top-messages-trigger">
            <i class="latepoint-icon latepoint-icon-bubble"></i>
            ' . $unread_messages_count_html . '
          </a>';
	}

	public static function delete_messages_for_deleted_booking_id( $booking_id ) {
		$messages = new OsMessageModel();
		$messages->delete_where( [ 'booking_id' => $booking_id ] );
	}


	public static function output_messages_on_quick_form( $booking ) {
		if ( $booking->is_new_record() ) {
			return false;
		}
		if ( ! OsRolesHelper::can_user( 'chat__edit' ) ) {
			return false;
		}
		$messages = OsMessagesHelper::get_messages_for_booking_id( $booking->id );
		echo '<div class="booking-messages-info-w" data-booking-id="' . $booking->id . '" data-route="' . OsRouterHelper::build_route_name( 'messages', 'quick_form_chat_box' ) . '">';
		echo '<div class="os-booking-messages-w">';
		if ( $messages ) {
			echo '<div class="os-bm-open-quick-messages">
                <i class="latepoint-icon latepoint-icon-bubble"></i>
                <span>' . sprintf( _n( 'Read %d Message', 'Read %d Messages', count( $messages ), 'latepoint-pro-features' ), count( $messages ) ) . '</span>
              </div>';
		} else {
			echo '<div class="os-bm-open-quick-messages">
                <i class="latepoint-icon latepoint-icon-bubble"></i>
                <span>' . __( 'Start Chatting', 'latepoint-pro-features' ) . '</span>
              </div>';
		}
		echo '</div>';
		echo '</div>';
	}

	public static function new_message_notification_template_settings() {
		?>
		<div class="white-box">
		<div class="white-box-header">
		  <div class="os-form-sub-header"><h3><?php _e( 'Chat Notifications', 'latepoint-pro-features' ); ?></h3></div>
		</div>
		<div class="white-box-content no-padding">
				<div class="sub-section-row">
			  <div class="sub-section-label"><h3><?php _e( 'Settings', 'latepoint-pro-features' ); ?></h3></div>
			  <div class="sub-section-content">
					<?php
					echo OsFormHelper::checkbox_field( 'settings[email_notification_customer_has_new_message]', __( 'Notify customers about new messages', 'latepoint-pro-features' ), LATEPOINT_VALUE_ON, OsSettingsHelper::is_on( 'email_notification_customer_has_new_message' ), [ 'data-toggle-element' => '#notificationCustomerHasNewMessageContent' ] );
					echo '<div class="lp-form-checkbox-contents" id="notificationCustomerHasNewMessageContent" ' . ( OsSettingsHelper::is_on( 'email_notification_customer_has_new_message' ) ? '' : 'style="display: none;"' ) . '>';
					  echo OsFormHelper::text_field( 'settings[email_notification_customer_has_new_message_subject]', __( 'Email Subject', 'latepoint-pro-features' ), OsMessagesHelper::email_notification_customer_has_new_message_subject() );
					  OsFormHelper::wp_editor_field( 'settings[email_notification_customer_has_new_message_content]', 'settings_email_notification_customer_has_new_message_content', __( 'Email Message', 'latepoint-pro-features' ), OsMessagesHelper::email_notification_customer_has_new_message_content() );
					echo '</div>';
					echo OsFormHelper::checkbox_field( 'settings[email_notification_agent_has_new_message]', __( 'Notify agents about new messages', 'latepoint-pro-features' ), LATEPOINT_VALUE_ON, OsSettingsHelper::is_on( 'email_notification_agent_has_new_message' ), [ 'data-toggle-element' => '#notificationAgentHasNewMessageContent' ] );
						echo '<div class="lp-form-checkbox-contents" id="notificationAgentHasNewMessageContent" ' . ( OsSettingsHelper::is_on( 'email_notification_agent_has_new_message' ) ? '' : 'style="display: none;"' ) . '>';
					  echo OsFormHelper::text_field( 'settings[email_notification_agent_has_new_message_subject]', __( 'Email Subject', 'latepoint-pro-features' ), OsMessagesHelper::email_notification_agent_has_new_message_subject() );
					  OsFormHelper::wp_editor_field( 'settings[email_notification_agent_has_new_message_content]', 'settings_email_notification_agent_has_new_message_content', __( 'Email Message', 'latepoint-pro-features' ), OsMessagesHelper::email_notification_agent_has_new_message_content() );
					echo '</div>';
					?>
				</div>
				</div>
			</div>
		</div>
		<?php
	}



	public static function output_messages_tab_on_customer_dashboard( $customer ) {
		$count_new_messages = OsMessagesHelper::count_new_messages_for_customer( $customer->id );
		?>
	<a href="#" data-tab-target=".tab-content-customer-booking-messages" class="latepoint-tab-trigger latepoint-trigger-messages-tab">
		<?php _e( 'Messages', 'latepoint-pro-features' ); ?>
		<?php if ( $count_new_messages ) {
			echo '<span class="lp-new-messages-count">' . $count_new_messages . '</span>';} ?>
	</a>
		<?php
	}

	public static function output_messages_tab_contents_on_customer_dashboard( $customer ) {
		$customer_bookings = $customer->get_bookings();
		?>
	<div class="latepoint-tab-content tab-content-customer-booking-messages">
		<?php if ( $customer_bookings ) { ?>
	  <div class="latepoint-chat-box-w" data-check-unread-route="<?php echo OsRouterHelper::build_route_name( 'messages', 'check_unread_messages' ); ?>" data-route="<?php echo OsRouterHelper::build_route_name( 'messages', 'messages_for_booking' ); ?>">
		<div class="lc-heading">
		  <div class="lc-conversations-header"><?php _e( 'Your Appointments', 'latepoint-pro-features' ); ?></div>
		  <div class="lc-contents-header"><?php _e( 'Conversation', 'latepoint-pro-features' ); ?></div>
		</div>
		<div class="lc-contents">
		  <div class="lc-conversations">
			<?php
			$active_booking = false;
			if ( $customer_bookings ) {
				$active_booking = $customer_bookings[0];
				foreach ( $customer_bookings as $booking ) {
					$unread_count = OsMessagesHelper::count_unread_messages_for_booking( $booking->id, 'customer' ); ?>
				<div class="lc-conversation <?php if ( $booking->id == $active_booking->id ) {
					echo ' lc-selected ';} ?><?php if ( $unread_count ) {
					echo ' has-unread ';} ?>" data-booking-id="<?php echo $booking->id; ?>">
				  <div class="lc-agent">
					<div class="lca-avatar" style="background-image: url('<?php echo esc_url( $booking->agent->get_avatar_url() ); ?>')"></div>
				  </div>
				  <div class="lc-info">
					<div class="lc-title"><?php echo esc_html( $booking->service->name ); ?></div>
					<div class="lc-meta"><?php echo $booking->get_nice_start_datetime_for_customer(); ?></div>
					<div class="lc-unread"><?php echo $unread_count; ?></div>
				  </div>
				</div>
					<?php
				}
			}
			?>
		  </div>
		  <div class="lcb-content">
			<div class="booking-messages-list">
			  <?php
				if ( $active_booking ) {
					$messages         = OsMessagesHelper::get_messages_for_booking_id( $active_booking->id );
					$viewer_user_type = 'customer';
					include plugin_dir_path( __FILE__ ) . '../views/messages/messages_for_booking.php';
				} ?>
			</div>
			<div class="os-booking-messages-input-w" data-avatar-url="<?php echo esc_url( $customer->get_avatar_url() ); ?>" data-author-type="customer" data-booking-id="<?php echo $active_booking->id; ?>" data-route="<?php echo OsRouterHelper::build_route_name( 'messages', 'create' ); ?>">
			  <input class="os-booking-messages-input" type="text" placeholder="<?php echo __( 'Type your message here..', 'latepoint-pro-features' ); ?>"/>
			  <div class="latepoint-btn latepoint-btn-primary os-bm-send-btn"><i class="latepoint-icon latepoint-icon-message-square"></i><span><?php echo __( 'Send', 'latepoint-pro-features' ); ?></span></div>
			</div>
		  </div>
		</div>
	  </div>
	  <?php } else { ?>
		<div class="latepoint-message-info latepoint-message"><?php _e( "You don't have any appointments to send messages.", 'latepoint-pro-features' ); ?></div>
	  <?php } ?>
	</div>
		<?php
	}
}