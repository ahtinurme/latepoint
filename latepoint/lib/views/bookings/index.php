<?php
/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */
?>
<?php
/* @var $bookings OsBookingModel[] */
/* @var $showing_from int */
/* @var $showing_to int */
/* @var $total_records int */
/* @var $per_page int */
/* @var $total_pages int */
/* @var $current_page_number int */
/* @var $records_ordered_by_key string */
/* @var $records_ordered_by_direction string */
/* @var $agents_list array */
/* @var $services_list array */
/* @var $locations_list array */
/* @var $selected_columns array */
/* @var $ordered_columns array */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php if($bookings){ ?>
  <div class="table-with-pagination-w has-scrollable-table">
    <div class="os-pagination-w with-actions">
	    <div class="table-heading-w">
			  <h2 class="table-heading"><?php esc_html_e('Appointments', 'latepoint'); ?></h2>
	      <div class="pagination-info"><?php echo esc_html__('Showing', 'latepoint'). ' <span class="os-pagination-from">'. esc_html($showing_from) . '</span>-<span class="os-pagination-to">'. esc_html($showing_to) .'</span> '.esc_html__('of', 'latepoint').' <span class="os-pagination-total">'. esc_html($total_records). '</span>'; ?></div>
	    </div>
	    <div class="mobile-table-actions-trigger"><i class="latepoint-icon latepoint-icon-more-horizontal"></i></div>
      <div class="table-actions">
          <a data-os-lightbox-classes="width-700" data-os-after-call="latepoint_init_column_reordering" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('bookings', 'customize_table')); ?>" href="#" data-os-output-target="lightbox" class="latepoint-btn latepoint-btn-grey latepoint-btn-outline download-csv-with-filters"><i class="latepoint-icon latepoint-icon-sliders"></i><span><?php esc_html_e('Table Settings', 'latepoint'); ?></span></a>
          <?php if (OsSettingsHelper::can_download_records_as_csv()) { ?>
              <a href="<?php echo esc_url(OsRouterHelper::build_admin_post_link(['bookings', 'index'])); ?>" target="_blank" class="latepoint-btn latepoint-btn-grey latepoint-btn-outline download-csv-with-filters"><i class="latepoint-icon latepoint-icon-download"></i><span><?php esc_html_e('Download .csv', 'latepoint'); ?></span></a>
          <?php } ?>
      </div>
    </div>
    <div class="os-bookings-list">
      <div class="os-scrollable-table-w">
        <div class="os-table-w os-table-compact">
          <table class="os-table os-reload-on-booking-update os-scrollable-table" data-route="<?php echo esc_attr(OsRouterHelper::build_route_name('bookings', 'index')); ?>">
	          <?php echo OsFormHelper::hidden_field('filter[records_ordered_by_key]', $records_ordered_by_key, ['class' => 'records-ordered-by-key os-table-filter']); ?>
	          <?php echo OsFormHelper::hidden_field('filter[records_ordered_by_direction]', $records_ordered_by_direction, ['class' => 'records-ordered-by-direction os-table-filter']); ?>
            <thead>
              <tr>
                <?php
                foreach ( $ordered_columns as $col_key => $col_def ) {
                  if ( ! OsSettingsHelper::is_bookings_column_visible( $col_def, $selected_columns, count( $services_list ), count( $agents_list ), count( $locations_list ) ) ) continue;
                  echo OsBookingHelper::render_table_header_cell( $col_key, $col_def, $records_ordered_by_key, $records_ordered_by_direction );
                }
                ?>
              </tr>
              <tr>
                <?php
                foreach ( $ordered_columns as $col_key => $col_def ) {
                  if ( ! OsSettingsHelper::is_bookings_column_visible( $col_def, $selected_columns, count( $services_list ), count( $agents_list ), count( $locations_list ) ) ) continue;
                  echo OsBookingHelper::render_table_filter_cell( $col_key, $col_def, $services_list, $agents_list, $locations_list );
                }
                ?>
              </tr>
            </thead>
            <tbody>
              <?php include('_table_body.php'); ?>
            </tbody>
            <tfoot>
              <tr>
                <?php
                foreach ( $ordered_columns as $col_key => $col_def ) {
                  if ( ! OsSettingsHelper::is_bookings_column_visible( $col_def, $selected_columns, count( $services_list ), count( $agents_list ), count( $locations_list ) ) ) continue;
                  echo '<th>' . esc_html( $col_def['label'] ) . '</th>';
                }
                ?>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
    <div class="os-pagination-w">
      <div class="pagination-info"><?php echo esc_html('Showing', 'latepoint'). ' <span class="os-pagination-from">'. esc_html($showing_from) . '</span>-<span class="os-pagination-to">'. esc_html($showing_to) .'</span> '.esc_html__('of', 'latepoint').' <span class="os-pagination-total">'. esc_html($total_records). '</span>'; ?></div>
      <div class="pagination-page-select-w">
        <label for="tablePaginationPageSelector"><?php esc_html_e('Page:', 'latepoint'); ?></label>
        <select id="tablePaginationPageSelector" name="page" class="pagination-page-select">
          <?php
          for($i = 1; $i <= $total_pages; $i++){
            $selected = ($current_page_number == $i) ? 'selected' : '';
            echo '<option '.$selected.'>'.esc_html($i).'</option>';
          } ?>
        </select>
      </div>
    </div>
  </div>

<?php }else{ ?>
  <div class="no-results-w">
    <div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
    <h2><?php esc_html_e('No Existing Appointments Found', 'latepoint'); ?></h2>
    <a href="#" <?php echo OsOrdersHelper::quick_order_btn_html(); ?> class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus"></i><span><?php esc_html_e('Add First Appointment', 'latepoint'); ?></span></a>
  </div>
<?php } ?>
