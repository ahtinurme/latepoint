<?php
/**
 * @var $next_step string
 * @var $current_step string
 */
?>
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<form class="latepoint-lightbox-wrapper-form import-customers-form" data-route-name="<?php echo esc_attr(OsRouterHelper::build_route_name('customers', 'import_load_step')); ?>">
	<?php wp_nonce_field('import_customers_csv'); ?>
	<div class="latepoint-lightbox-heading">
		<h2><?php esc_html_e('Import Customers', 'latepoint'); ?></h2>
	</div>

    <?php echo OsFormHelper::hidden_field( 'step', 'upload_csv' ); ?>

	<div class="latepoint-lightbox-content">
        <?php include 'import_steps/step_upload_csv.php'?>
	</div>

    <div class="latepoint-lightbox-footer right-aligned">
        <button type="submit" class="latepoint-btn latepoint-csv-next-btn">
            <?php esc_html_e('Continue', 'latepoint'); ?>
        </button>
    </div>

</form>