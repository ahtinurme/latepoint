<div class="os-conversations-wrapper <?php echo $conversations ? '' : 'mobile-show-conversations'; ?>">
	<div class="os-conversations-list-wrapper">
		<h3>
			<div class="latepoint-icon latepoint-icon-message-circle"></div>
			<span><?php _e('Your Conversations', 'latepoint-pro-features'); ?></span>
		</h3>
		<div class="osc-search-wrapper">
			<input class="osc-search-input" data-route="<?php echo OsRouterHelper::build_route_name('messages', 'search_bookings') ?>" type="text" placeholder="<?php _e('Find booking to start chat...', 'latepoint-pro-features'); ?>" />
		</div>
		<div class="os-search-conversations-list" style="display: none;">
		</div>
		<div class="os-conversations-list">
			<?php include_once('conversations.php'); ?>
	  </div>
  </div>
  <div class="os-conversation-content">
  	<?php
	  if($booking){
			include_once(dirname( __FILE__ ).'/messages_with_info_for_booking.php');
	  }else{
			echo '<div class="os-conversations-no-booking"></div>';
	  }
		?>
	</div>
</div>