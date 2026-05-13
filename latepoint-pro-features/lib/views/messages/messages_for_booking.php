<?php
if($messages){
  foreach($messages as $message){
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
  }
}else{
  echo '<div class="os-bm-no-messages">'.__('No Messages Exist.', 'latepoint-pro-features').'</div>';
} ?>