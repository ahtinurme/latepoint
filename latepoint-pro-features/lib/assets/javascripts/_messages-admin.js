function latepoint_init_booking_messages_file_upload(){

  // UPLOAD/REMOVE IMAGE LINK LOGIC
  jQuery('body').on( 'click', '.os-bm-upload-file-btn', function( event ){
    var frame;
    var $input = jQuery(this);
    var $wrapper = $input.closest('.os-booking-messages-input-w');

    event.preventDefault();
    
    // If the media frame already exists, reopen it.
    if ( frame ) {
      frame.open();
      return false;
    }
    
    // Create a new media frame
    frame = wp.media({
      title: 'Select or Upload Media',
      button: { text: 'Use this media' },
      multiple: false
    });

    frame.on( 'select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      var avatar_url = $wrapper.data('avatar-url');
      jQuery('.booking-messages-wrapper .booking-messages-list').append('<div class="os-booking-message-attachment-w os-bm-agent"><div class="os-booking-message-attachment"><i class="latepoint-icon latepoint-icon-paperclip"></i><span>' + attachment.filename + '</span></div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>').scrollTop(jQuery('.booking-messages-wrapper .booking-messages-list')[0].scrollHeight);


      var params = { message: {
                        content: attachment.id, 
                        content_type: 'attachment',
                        author_type: $wrapper.data('author-type'),
                        booking_id: $wrapper.data('booking-id') 
                      }
                    };
      var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 

      jQuery.ajax({
        type : "post",
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: function(response){
          $wrapper.find('.latepoint-btn').removeClass('os-loading');
          if(response.status === "success"){

          }else{
            alert(response.message);
          }
        }
      });
    });

    frame.open();
    
    return false;
  });
}


function latepoint_send_booking_message($wrapper){
    var $input = $wrapper.find('.os-booking-messages-input');
    var message_content = $input.val();

    if (!message_content) return;

    var params = { message: {
                    content: message_content, 
                    author_type: $wrapper.data('author-type'),
                    booking_id: $wrapper.data('booking-id') }
                  };
    var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 
    $wrapper.find('.latepoint-btn').addClass('os-loading');
    jQuery('.booking-messages-wrapper').find('.os-bm-no-messages').remove();
    var avatar_url = $wrapper.data('avatar-url');
    jQuery('.booking-messages-wrapper .booking-messages-list').append('<div class="os-booking-message-w os-bm-agent"><div class="os-booking-message">' + message_content + '</div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>');
    latepoint_admin_messages_scroll_chat();

    $input.val('');
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        $wrapper.removeClass('os-is-typing');
        $wrapper.find('.latepoint-btn').removeClass('os-loading');
        if(response.status === "success"){

        }else{
          $input.val(message_content);
          alert(response.message);
        }
      }
    });
    return false;
}

function latepoint_init_booking_messages(){
  // FILE UPLOADER
  latepoint_init_booking_messages_file_upload();
  clearInterval(latepoint_helper.latepoint_message_refresh_timer);

  jQuery('body').on('click', '.os-bm-send-btn', function(event){
    var $wrapper = jQuery(this).closest('.os-booking-messages-input-w');
    latepoint_send_booking_message($wrapper);
    return false;
  });
  // INPUT TEXT BOX
  jQuery('body').on('keyup', '.os-booking-messages-input', function(event){
    var $input = jQuery(this);
    var $wrapper = $input.closest('.os-booking-messages-input-w');
    if(event.keyCode == 13){
      event.preventDefault();
      latepoint_send_booking_message($wrapper);
      return false;
    }else{
      if($input.val()){
        $wrapper.addClass('os-is-typing');
      }else{
        $wrapper.removeClass('os-is-typing');
      }
    }
  });


  latepoint_helper.latepoint_message_refresh_timer = setInterval(function(){
    if (!document.hidden && jQuery('.booking-messages-wrapper').length) {
      var $messages_panel = jQuery('.booking-messages-wrapper');
      var data = {
        action: 'latepoint_route_call',
        route_name: $messages_panel.data('check-unread-route'),
        params: {
          booking_id: $messages_panel.find('.os-booking-messages-input-w').data('booking-id'),
          viewer_user_type: 'backend_user'
        },
        return_format: 'json'
      }
      jQuery.ajax({
        type : "post",
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: function(response){
          if(response.status === "success"){
            if(response.message == 'yes'){
              latepoint_admin_reload_chat_messages();
            }
          }
        }
      });
    }
  }, 3000);
}


function latepoint_init_loaded_coversation($conversation){
  jQuery('.os-conversation-box.is-selected').removeClass('is-selected');
  $conversation.addClass('is-selected').removeClass('is-new').addClass('is-read');
  jQuery('.os-booking-messages-input-w').data('booking-id', $conversation.data('booking-id'));
  jQuery('.os-conversations-wrapper').removeClass('mobile-show-conversations').removeClass('mobile-show-booking-info');
  jQuery('.os-conversations-wrapper .booking-messages-list').parent().scrollTop(jQuery('.os-conversations-wrapper .booking-messages-list')[0].scrollHeight);
}

function latepoint_admin_reload_chat_messages(){
  var $messages_panel = jQuery('.booking-messages-wrapper');
  var data = {
    action: 'latepoint_route_call',
    route_name: $messages_panel.data('route'),
    params: {
      booking_id: $messages_panel.find('.os-booking-messages-input-w').data('booking-id'),
      viewer_user_type: 'backend_user'
    },
    return_format: 'json'
  }
  jQuery.ajax({
    type : "post",
    dataType : "json",
    url : latepoint_timestamped_ajaxurl(),
    data : data,
    success: function(response){
      if(response.status === "success"){
        $messages_panel.find('.booking-messages-list').html(response.message);
        latepoint_admin_messages_scroll_chat();
      }else{
        alert(response.message);
      }
    }
  });
}

function latepoint_admin_messages_scroll_chat(){
  if(jQuery('.booking-messages-wrapper .booking-messages-list').length) jQuery('.booking-messages-wrapper .booking-messages-list').parent().scrollTop(jQuery('.booking-messages-wrapper .booking-messages-list')[0].scrollHeight);
  if(jQuery('.os-conversations-wrapper .booking-messages-list').length) jQuery('.os-conversations-wrapper .booking-messages-list').parent().scrollTop(jQuery('.os-conversations-wrapper .booking-messages-list')[0].scrollHeight);
}

function latepoint_init_booking_messages_panel(){
  jQuery('.latepoint-admin').on('click', '.os-bm-open-quick-messages', function(){
    var $trigger_elem = jQuery(this);
    var $messages_info_w = $trigger_elem.closest('.booking-messages-info-w');
    $trigger_elem.addClass('os-loading');
    var route = $messages_info_w.data('route');
    var data = { action: 'latepoint_route_call', route_name: route, params: {booking_id: $messages_info_w.data('booking-id')}, return_format: 'json' }
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        $trigger_elem.removeClass('os-loading');
        if(response.status === "success"){
          latepoint_display_in_side_sub_panel(response.message);
          latepoint_admin_messages_scroll_chat();
          latepoint_init_booking_messages();
          jQuery('.os-booking-messages-input').trigger('focus');
        }else{
          alert(response.message, 'error');
        }
      }
    });
  });
}

// DOCUMENT READY
jQuery(document).ready(function( $ ) {

  jQuery('.os-conversations-wrapper').on('click', '.os-toggle-conversations-panel', function(){
    jQuery('.os-conversations-wrapper').toggleClass('mobile-show-conversations');
  });
  jQuery('.os-conversations-wrapper').on('click', '.os-toggle-conversation-booking-info-panel', function(){
    jQuery('.os-conversations-wrapper').toggleClass('mobile-show-booking-info');
  });

	latepoint_init_booking_messages_panel();

  if(jQuery('.os-conversations-wrapper').length){
    latepoint_admin_messages_scroll_chat();
    latepoint_init_booking_messages();
    jQuery('.os-booking-messages-input').trigger('focus');
  }

	$('.osc-search-input').on('keyup', function(e){
		if(e.which == 27){
			// esc
			$(this).val('');
    	$('.os-search-conversations-list').html('').hide();
    	$('.os-conversations-list').show();
      $this.closest('.osc-search-wrapper').removeClass('os-loading');
		}else{
			var $this = $(this);
			if(!$this.val()){
	    	$('.os-search-conversations-list').html('').hide();
	    	$('.os-conversations-list').show();
	      $this.closest('.osc-search-wrapper').removeClass('os-loading');
	    	return false;
			}
      var route = $(this).data('route');
      var params = {query: $this.val()};

      var data = { action: 'latepoint_route_call', route_name: route, params: params, layout: 'none', return_format: 'json' }
      $this.closest('.osc-search-wrapper').addClass('os-loading');
      $.ajax({
        type : "post",
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: function(data){
					if(!$this.val()){
			    	$('.os-search-conversations-list').html('').hide();
			    	$('.os-conversations-list').show();
			    	return false;
					}
          $this.closest('.osc-search-wrapper').removeClass('os-loading');
          if(data.status === "success"){
          	$('.os-search-conversations-list').html(data.message).show();
          	$('.os-conversations-list').hide();
          }
        }
      });
		}
	});
});