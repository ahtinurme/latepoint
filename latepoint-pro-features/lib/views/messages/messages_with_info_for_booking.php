<div class="os-conversation-messages booking-messages-wrapper" data-route="<?php echo OsRouterHelper::build_route_name('messages', 'messages_for_booking'); ?>" data-check-unread-route="<?php echo OsRouterHelper::build_route_name('messages', 'check_unread_messages'); ?>">
	<div class="os-conversation-info">
		<div class="os-conversation-mobile-open-conversations os-toggle-conversations-panel">
			<div class="latepoint-icon latepoint-icon-user"></div>
		</div>
			<div class="os-conversation-info-customer-avatar" style="background-image:url(<?php echo esc_url($booking->customer->get_avatar_url());?>)"></div>
	    <div class="os-conversation-info-customer">
	      <span class="os-conversation-info-label"><?php _e('Chat with', 'latepoint-pro-features'); ?></span>
	      <a href="#" <?php echo OsCustomerHelper::quick_customer_btn_html($booking->customer_id); ?> class="os-conversation-info-customer-name"><?php echo esc_html($booking->customer->full_name);?></a>
	    </div>
		<div class="os-conversation-mobile-open-booking-info os-toggle-conversation-booking-info-panel">
			<div class="latepoint-icon latepoint-icon-info"></div>
		</div>
	</div>
	<div class="booking-messages-list-wrapper">
	<div class="booking-messages-list">
		<?php
			if($selected_conversation_messages){
			  foreach($selected_conversation_messages as $message){
			    if($message->content_type == LATEPOINT_MESSAGE_CONTENT_TYPE_TEXT){ ?>
			      <div class="os-booking-message-w os-bm-<?php echo $message->author_type; ?>">
			        <div class="os-booking-message"><?php echo $message->content; ?></div>
			        <div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url(<?php echo $message->get_author_avatar(); ?>);"></div><div class="os-bm-date"><?php echo OsTimeHelper::date_from_db($message->created_at, OsSettingsHelper::get_readable_datetime_format()); ?></div></div>
			      </div>
			    <?php
			    }elseif($message->content_type == LATEPOINT_MESSAGE_CONTENT_TYPE_ATTACHMENT){ ?>
			      <div class="os-booking-message-attachment-w os-bm-<?php echo $message->author_type; ?>">
			        <a target="_blank" href="<?php echo OsMessagesHelper::get_message_attachment_link($message->id); ?>" class="os-booking-message-attachment">
			          <i class="latepoint-icon latepoint-icon-paperclip"></i>
			          <span><?php echo basename(get_attached_file($message->content)); ?></span>
			        </a>
			        <div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url(<?php echo $message->get_author_avatar(); ?>);"></div><div class="os-bm-date"><?php echo OsTimeHelper::date_from_db($message->created_at, OsSettingsHelper::get_readable_datetime_format()); ?></div></div>
			      </div>
			      <?php
			    }
			    if(empty($message->is_read) && ($message->author_type == 'customer')){
			      $message->is_read = true; 
			      $message->save();
			    }
			  }
			}else{
			  echo '<div class="os-bm-no-messages">'.__('No Messages Exist.', 'latepoint-pro-features').'</div>';
		} ?>
	</div>
	</div>
    <div class="os-booking-messages-input-wrapper">
	  <div class="os-booking-messages-input-w" data-avatar-url="<?php echo esc_url($booking->customer->get_avatar_url()); ?>" data-author-type="backend_user" data-booking-id="<?php echo $booking->id; ?>" data-route="<?php echo OsRouterHelper::build_route_name('messages', 'create'); ?>">
	    <input class="os-booking-messages-input" type="text" placeholder="<?php echo __('Type your message here..', 'latepoint-pro-features'); ?>"/>
	    <div class="latepoint-btn latepoint-btn-primary os-bm-send-btn"><i class="latepoint-icon latepoint-icon-message-square"></i><span><?php echo __('Send', 'latepoint-pro-features'); ?></span></div>
	  <div class="latepoint-btn latepoint-btn-secondary os-bm-upload-file-btn"><i class="latepoint-icon latepoint-icon-paperclip"></i><span><?php echo __('Attach File', 'latepoint-pro-features'); ?></span></div>
      </div>
  </div>
</div>
	<div class="os-conversation-booking-info">
		<h3>
			<div class="latepoint-icon latepoint-icon-info"></div>
			<span><?php _e('Appointment Info', 'latepoint-pro-features'); ?></span>
			<div class="os-conversation-close-booking-info os-toggle-conversation-booking-info-panel">
				<div class="latepoint-icon latepoint-icon-x"></div>
			</div>
		</h3>
		<ul>
	  	<li>
	  		<div><?php echo __('Booking ID', 'latepoint-pro-features'); ?></div>
	  		<strong><?php echo $booking->id; ?></strong>
	  		<a href="#" <?php echo OsBookingHelper::quick_booking_btn_html($booking->id); ?>><i class="latepoint-icon latepoint-icon-edit-2"></i><span><?php _e('Edit', 'latepoint-pro-features'); ?></span></a>
	  	</li>
	  	<li><div><?php _e('Date/Time', 'latepoint-pro-features'); ?></div> <strong><?php echo $booking->nice_start_date; ?>, <?php echo $booking->nice_start_time; ?></strong></li>
	    <?php if(!empty($booking->location->full_address)){ ?>
	      <li><div><?php _e('Location', 'latepoint-pro-features'); ?></div> <strong><?php echo esc_html($booking->location->full_address); ?></strong></li>
	    <?php } ?>
	  	<li><div><?php _e('Customer', 'latepoint-pro-features'); ?></div> <a href="#" <?php echo OsCustomerHelper::quick_customer_btn_html($booking->customer_id); ?>><?php echo esc_html($booking->customer->full_name); ?></a></li>
	    <?php if(!OsSettingsHelper::is_on('steps_hide_agent_info')){ ?>
		  	<li><div><?php _e('Agent', 'latepoint-pro-features'); ?></div> <a href="<?php echo OsRouterHelper::build_link(['agents', 'edit_form'], ['id' => $booking->agent_id]); ?>"><?php echo esc_html($booking->agent->full_name); ?></a></li>
	    <?php } ?>
	  	<li><div><?php _e('Service', 'latepoint-pro-features'); ?></div> <a href="<?php echo OsRouterHelper::build_link(['services', 'edit_form'], ['id' => $booking->service_id]); ?>"><?php echo esc_html($booking->service->name); ?></a></li>
	  	<?php do_action('latepoint_conversation_booking_info_after', $booking); ?>
		</ul>
	</div>
