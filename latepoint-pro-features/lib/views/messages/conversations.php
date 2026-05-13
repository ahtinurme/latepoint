<?php
if($conversations){
  foreach($conversations as $conversation){
		if(!$conversation['booking']->id) continue;?>
    <div data-os-after-call="latepoint_init_loaded_coversation" 
    			data-os-pass-this="yes" 
    			data-booking-id="<?php echo $conversation['booking']->id; ?>" 
    			data-os-action="<?php echo OsRouterHelper::build_route_name('messages', 'messages_with_info_for_booking'); ?>" 
    			data-os-params="<?php echo http_build_query(['selected_booking_id' => $conversation['booking']->id]) ?>" 
    			data-os-output-target=".os-conversation-content" 
    			class="os-conversation-box <?php echo ($selected_booking_id == $conversation['booking']->id) ? 'is-selected' : ''; ?> <?php echo ($conversation['last_message']->author_type == 'customer' && !$conversation['last_message']->is_read) ? 'is-new' : 'is-read'; ?>">
      <div class="os-conversation-customer-avatar" style="background-image:url(<?php echo $conversation['booking']->customer->get_avatar_url();?>)"></div>
      <div class="os-conversation-customer-info">
	      <div class="os-conversation-customer-name"><?php echo esc_html($conversation['booking']->customer->full_name);?></div>
	      <div class="os-conversation-booking-id"><?php echo __('ID: ', 'latepoint-pro-features').$conversation['booking']->id;?></div>
	      <div class="os-conversation-last-message"><?php echo ($conversation['last_message']) ? $conversation['last_message']->content : __('Start new conversation...', 'latepoint-pro-features');?></div>
      </div>
    </div>
    <?php
  }
}else{
	echo '<div class="no-conversations-found">'.__('No conversations found.').'</div>';
}