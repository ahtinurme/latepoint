<div class="customer-csv-step" data-customer-csv-step="mapping" data-customer-csv-next-btn="<?php echo esc_html__('Next', 'latepoint') ?>">
    <div class="latepoint-message latepoint-message-subtle"><?php _e('Please match CSV columns to customer properties.', 'latepoint') ?></div>
<?php
    $available_fields = OsCustomerImportHelper::get_import_fields();

    if(!empty($csv_data)){
        $columns = $csv_data[0];
        ?>
            <div class="customer-csv-mapping-wrapper">

            <div class="os-row os-row-align-center customer-csv-mapping-header">
                <div class="os-col-md-6">
                    <?php _e('CSV Column', 'latepoint'); ?>
                </div>
                <div class="os-col-md-6">
                    <?php _e('LatePoint Property', 'latepoint'); ?>
                </div>
            </div>

        <?php
        foreach ($columns as $index => $column) {
            $column = trim($column); ?>
            <div class="os-row os-row-align-center customer-csv-mapping-row">
                <div class="os-col-md-6">
                    <div class="label-desc"><?php echo esc_html($column); ?></div>
                </div>
                <div class="os-col-md-6">
                    <?php echo OsFormHelper::select_field('latepoint_column_mapping[' . $index . ']', '', $available_fields, $column); ?>
                </div>
            </div>
        <?php } ?>
        </div>
    <?php
    } ?>
</div>
