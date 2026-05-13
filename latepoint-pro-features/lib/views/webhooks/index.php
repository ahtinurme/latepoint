<div class="os-webhooks-w">
	<?php if($webhooks){ ?>
		<?php foreach($webhooks as $webhook){ ?>
			<?php include('_webhook_form.php'); ?>
		<?php } ?>
	<?php } ?>
</div>
<div class="os-add-box add-webhook-box add-webhook-trigger" data-os-action="<?php echo OsRouterHelper::build_route_name('webhooks', 'new_form'); ?>" data-os-output-target-do="append" data-os-output-target=".os-webhooks-w">
	<div class="add-box-graphic-w">
		<div class="add-box-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
	</div>
	<div class="add-box-label"><?php _e('Add Webhook', 'latepoint-pro-features'); ?></div>
</div>