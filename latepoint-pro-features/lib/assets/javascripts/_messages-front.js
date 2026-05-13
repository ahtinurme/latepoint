( function( $ ) {
  "use strict";

  function latepoint_init_booking_messages_file_upload(){

    // UPLOAD/REMOVE IMAGE LINK LOGIC
    $('.latepoint-chat-box-w').on( 'click', '.os-bm-upload-file-btn', function( event ){
      var frame;
      var $input = $(this);
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
        $('.booking-messages-list').append('<div class="os-booking-message-attachment-w os-bm-customer"><div class="os-booking-message-attachment"><i class="latepoint-icon latepoint-icon-paperclip"></i><span>' + attachment.filename + '</span></div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>').scrollTop($('.booking-messages-list')[0].scrollHeight);


        var params = { message: {
                          content: attachment.id, 
                          content_type: 'attachment',
                          author_type: $wrapper.data('author-type'),
                          booking_id: $wrapper.data('booking-id') 
                        }
                      };
        var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 

        $.ajax({
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
      var params = { message: {
                      content: message_content, 
                      author_type: $wrapper.data('author-type'),
                      booking_id: $wrapper.data('booking-id') }
                    };
      var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 
      $wrapper.find('.latepoint-btn').addClass('os-loading');
      $('.booking-messages-list').find('.os-bm-no-messages').remove();
      var avatar_url = $wrapper.data('avatar-url');
      $('.booking-messages-list').append('<div class="os-booking-message-w os-bm-customer"><div class="os-booking-message">' + message_content + '</div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>');
      latepoint_messages_scroll_chat();

      $input.val('');
      $.ajax({
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

  function latepoint_messages_scroll_chat(){
    jQuery('.booking-messages-list').scrollTop(jQuery('.booking-messages-list')[0].scrollHeight);
  }

  function latepoint_reload_chat_messages(booking_id, show_loading){
    var $chatbox = $('.latepoint-chat-box-w');
    if(!$chatbox.length) return false;
    if(show_loading) $chatbox.addClass('os-loading');
    var data = {
      action: 'latepoint_route_call',
      route_name: $chatbox.data('route'),
      params: {
        booking_id: booking_id,
        viewer_user_type: 'customer'
      },
      return_format: 'json'
    }
    $.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        if(show_loading) $chatbox.removeClass('os-loading');
        if(response.status === "success"){
          $chatbox.find('.booking-messages-list').html(response.message);
          latepoint_messages_scroll_chat();
          $('.os-booking-messages-input-w').data('booking-id', booking_id);
        }else{
          alert(response.message);
        }
      }
    });
  }

  function latepoint_init_booking_messages_chat_box(){
    $('.lc-conversation').on('click', function(){
      var booking_id = $(this).data('booking-id');
      $('.lc-conversation.lc-selected').removeClass('lc-selected');
      $(this).addClass('lc-selected');
      latepoint_reload_chat_messages(booking_id, true);
      return false;
    });

    clearInterval(latepoint_helper.latepoint_message_refresh_timer);
    if($('.latepoint-chat-box-w').length && $('.lc-conversation').length){
      latepoint_helper.latepoint_message_refresh_timer = setInterval(function(){
        if (!document.hidden) {
          var route = $('.latepoint-chat-box-w').data('check-unread-route');
          var data = { action: 'latepoint_route_call', route_name: route, params: {booking_id: $('.lc-conversation.lc-selected').data('booking-id'), viewer_user_type: 'customer'}, return_format: 'json' } 
          $.ajax({
            type : "post",
            dataType : "json",
            url : latepoint_timestamped_ajaxurl(),
            data : data,
            success: function(response){
              if(response.status === "success"){
                if(response.message == 'yes'){
                  latepoint_reload_chat_messages($('.lc-conversation.lc-selected').data('booking-id'), false);
                }
              }
            }
          });
        }
      }, 3000);
    }

    $('.os-bm-send-btn').on('click', function(event){
      var $wrapper = $(this).closest('.os-booking-messages-input-w');
      latepoint_send_booking_message($wrapper);
      return false;
    });
    // INPUT TEXT BOX
    $('.os-booking-messages-input').on('keyup', function(event){
      var $input = $(this);
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
  }



  // DOCUMENT READY
  $( function() {
    latepoint_init_booking_messages_chat_box();
    $('.latepoint-trigger-messages-tab').on('click', function(){
      latepoint_reload_chat_messages($('.lc-conversation.lc-selected').data('booking-id'), false);
    });
  });


} )( jQuery );