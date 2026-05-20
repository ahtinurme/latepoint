<?php
/** @var bool $can_be_imported
 *  @var array $conflicts
 * @var array $latepoint_column_mapping
 */
?>

<div class="customer-csv-step" data-customer-csv-step="confirmation" data-customer-csv-next-btn="<?php _e('Start Import', 'latepoint'); ?>">
    <div class="latepoint-message latepoint-message-subtle"><?php _e('Please review the data before importing.', 'latepoint') ?></div>
    <?php $has_duplicates = false; ?>
    <div class="customer-csv-confirmation-table-wrapper">
    <table class="customer-csv-confirmation-table">
        <tr>
            <td><?php _e('New Customers', 'latepoint') ?></td>
            <td><?php echo $can_be_imported; ?></td>
        </tr>
        <?php
        if ( ! empty( $conflicts ) ) {
            foreach ( $conflicts as $conflict_type => $conflict_data ) {
                    echo '<tr>';
                    switch($conflict_type){
                        case 'invalid':
                            echo '<td>'.__('Invalid emails', 'latepoint').'</td>';
                            echo '<td>'.count($conflict_data).'</td>';
                            break;
                        case 'duplicate':
                            $has_duplicates = true;
                            echo '<td>'.__('Existing emails', 'latepoint').'</td>';
                            echo '<td>'.count($conflict_data).'</td>';
                            break;
                    }
                    echo '</tr>';
                    echo '<tr style="display: none;"><td colspan="2">'.implode(', ', $conflict_data).'</td></tr>';
                    ?>
                <?php } ?>
            <?php
        } ?>
    </table>
    </div>
    <?php if($has_duplicates){
        echo OsFormHelper::checkbox_field('latepoint_update_customers_acknowledgement', __('Fill in missing values for existing emails', 'latepoint'), 'on', true);
    }
    ?>
    <?php echo OsFormHelper::hidden_field( 'latepoint_column_mapping', wp_json_encode($latepoint_column_mapping) ); ?>
</div>