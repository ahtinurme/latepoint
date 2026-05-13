<?php 
$triggers = ['new_booking' => __('New Booking', 'latepoint-pro-features'),
							'updated_booking' => __('Updated Booking', 'latepoint-pro-features'),
						 'new_customer' => __('New Customer', 'latepoint-pro-features'),
	'new_transaction' => __('New Transaction', 'latepoint-pro-features')]; ?>
<form data-os-webhook-id="<?php echo $webhook['id']; ?>" 
			data-os-action="<?php echo OsRouterHelper::build_route_name('webhooks', 'save'); ?>" 
			data-os-after-call="latepoint_webhook_updated" 
			class="os-webhook-form os-webhook-status-<?php echo esc_attr($webhook['status']); ?> <?php if(empty($webhook['url'])) echo 'os-is-editing'; ?>">
	<div class="os-webhook-form-i">
		<div class="os-webhook-form-info">
			<div class="os-webhook-name"><?php echo !empty($webhook['name']) ? $webhook['name'] : __('New Webhook', 'latepoint-pro-features'); ?></div>
			<div class="os-webhook-trigger">
				<i class="latepoint-icon latepoint-icon-zap"></i>
				<span><?php echo $triggers[$webhook['trigger']]; ?></span>
			</div>
			<div class="os-webhook-url">
				<i class="latepoint-icon latepoint-icon-link-2"></i>
				<span><?php echo $webhook['url']; ?></span>
			</div>
			<div class="os-webhook-edit-btn"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
		</div>
		<div class="os-webhook-form-params">
			<div class="os-row">
				<div class="os-col-8">
					<?php echo OsFormHelper::text_field('webhooks['.$webhook['id'].'][url]', __('Webhook URL', 'latepoint-pro-features'), $webhook['url'],['placeholder' => __('Webhook URL (https://example.com)', 'latepoint-pro-features')]); ?>
				</div>
				<div class="os-col-4">
					<?php echo OsFormHelper::text_field('webhooks['.$webhook['id'].'][name]', __('Name (For Internal Use)', 'latepoint-pro-features'), $webhook['name']); ?>
				</div>
				<div class="os-col-8">
				  <?php echo OsFormHelper::select_field('webhooks['.$webhook['id'].'][trigger]', 
				  																				__('Event that will trigger this hook', 'latepoint-pro-features'),
				  																				$triggers, 
													  											$webhook['trigger']); ?>
				</div>
				<div class="os-col-4">
				  <?php echo OsFormHelper::select_field('webhooks['.$webhook['id'].'][status]', 
				  																			__('Hook Status', 'latepoint-pro-features'),
				  																			array( 'active' => __('Active', 'latepoint-pro-features'),
																												'disabled' => __('Disabled', 'latepoint-pro-features')),
																								$webhook['status']); ?>
				</div>
			</div>
		  <button type="submit" class="os-webhook-save-btn latepoint-btn latepoint-btn-primary"><span><?php _e('Save', 'latepoint-pro-features'); ?></span></button>
		</div>
	</div>
	<?php echo OsFormHelper::hidden_field('webhooks['.$webhook['id'].'][id]', $webhook['id'], ['class' => 'os-webhook-id']); ?>
	<?php wp_nonce_field('latepoint_save_webhooks', '_wpnonce', false); ?>
	<a href="#" data-os-prompt="<?php _e('Are you sure you want to delete this webhook?', 'latepoint-pro-features'); ?>"  data-os-after-call="latepoint_webhook_removed" data-os-pass-this="yes" data-os-action="<?php echo OsRouterHelper::build_route_name('webhooks', 'destroy'); ?>" data-os-params="<?php echo OsUtilHelper::build_os_params(['id' => $webhook['id']], 'latepoint_destroy_webhook_' . $webhook['id']) ?>" class="os-remove-webhook"><i class="latepoint-icon latepoint-icon-cross"></i></a>
</form>