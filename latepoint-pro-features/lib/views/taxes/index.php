<div class="os-section-header"><h3><?php _e('Taxes', 'latepoint-pro-features'); ?></h3></div>
<div class="os-taxes-w os-form-blocks-w os-taxes-ordering-w" data-order-update-route="<?php echo OsRouterHelper::build_route_name('taxes', 'update_order'); ?>">
	<?php foreach($taxes as $tax){ ?>
		<?php include('_tax_form.php'); ?>
	<?php } ?>
</div>
<div class="os-add-box"
     data-os-after-call="latepointTaxesAddon.init_new_tax_form"
     data-os-action="<?php echo OsRouterHelper::build_route_name('taxes', 'new_form'); ?>"
     data-os-output-target-do="append"
     data-os-output-target=".os-taxes-w">
	<div class="add-box-graphic-w">
		<div class="add-box-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
	</div>
	<div class="add-box-label"><?php _e('Add Tax', 'latepoint-pro-features'); ?></div>
</div>