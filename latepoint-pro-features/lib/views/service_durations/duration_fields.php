<?php
/**
 * @var $duration array
 **/
?>
<div class="duration-box service-duration-box extra-duration">
  <div class="os-row">
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::text_field('service[durations]['.$duration['id'].'][name]', __('Duration Name (optional)', 'latepoint-pro-features'), $duration['name'] ?? '',['theme' => 'simple']); ?>
    </div>
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::text_field('service[durations]['.$duration['id'].'][duration]', __('Duration', 'latepoint-pro-features'), $duration['duration'],['theme' => 'simple', 'class' => 'os-mask-minutes']); ?>
    </div>
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::money_field('service[durations]['.$duration['id'].'][charge_amount]', __('Charge Amount', 'latepoint-pro-features'), $duration['charge_amount'],['theme' => 'simple']); ?>
    </div>
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::money_field('service[durations]['.$duration['id'].'][deposit_amount]', __('Deposit Amount', 'latepoint-pro-features'), $duration['deposit_amount'],['theme' => 'simple']); ?>
    </div>
  </div>
  <a href="#" class="os-remove-duration"><i class="latepoint-icon latepoint-icon-cross"></i></a>
</div>