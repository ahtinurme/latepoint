<?php /**
 * @var int $skipped_records
 * @var int $updated_records
 */
?>
<div class="customer-csv-step" data-customer-csv-step="done" data-hide-next-btn="true">
    <div class="customer-import-result">

        <div class="customer-import-result-success">
            <div class="icon-w a-rotate-scale">
                <i class="latepoint-icon latepoint-icon-check"></i>
            </div>
            <div><?php _e('Import Complete', 'latepoint'); ?></div>
        </div>

        <div class="customer-csv-confirmation-table-wrapper">
        <table class="customer-csv-confirmation-table">
            <tr>
                <td><?php echo esc_html__('Created/Updated Customers', 'latepoint'); ?></td>
                <td><?php echo $updated_records; ?></td>
            </tr>
            <tr>
                <td><?php echo esc_html__('Skipped Records', 'latepoint'); ?></td>
                <td><?php echo $skipped_records; ?></td>
            </tr>
        </table>

    </div>
	<div>

    </div>
    <p></p>
</div>