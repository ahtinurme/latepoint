<?php
/** @var $coupons OsCouponModel[] */
?>
<?php if($coupons){ ?>
  <div class="table-with-pagination-w has-scrollable-table no-overflow">
    <div class="os-pagination-w with-actions">
	    <div class="table-heading-w">
			  <h2 class="table-heading"><?php _e('Coupons', 'latepoint-pro-features'); ?></h2>
	      <div class="pagination-info"><?php echo __('Showing', 'latepoint-pro-features'). ' <span class="os-pagination-from">'. $showing_from . '</span>-<span class="os-pagination-to">'. $showing_to .'</span> '.__('of', 'latepoint-pro-features').' <span class="os-pagination-total">'. $total_coupons. '</span>'; ?></div>
	    </div>
	    <div class="mobile-table-actions-trigger"><i class="latepoint-icon latepoint-icon-more-horizontal"></i></div>
      <div class="table-actions">
	    <?php if (OsSettingsHelper::can_download_records_as_csv()) { ?>
            <a href="<?php echo OsRouterHelper::build_admin_post_link(OsRouterHelper::build_route_name('coupons', 'index') ) ?>" target="_blank" class="latepoint-btn latepoint-btn-outline latepoint-btn-grey download-csv-with-filters"><i class="latepoint-icon latepoint-icon-download"></i><span><?php _e('Download .csv', 'latepoint-pro-features'); ?></span></a>
        <?php } ?>
        <a href="#" <?php echo OsCouponsHelper::quick_coupon_btn_html(); ?> class="latepoint-btn latepoint-btn-outline latepoint-btn-grey"><i class="latepoint-icon latepoint-icon-plus"></i><span><?php _e('New Coupon', 'latepoint-pro-features'); ?></span></a>
      </div>
    </div>
	    <div class="os-scrollable-table-w">
      <div class="os-table-w os-table-compact">
        <table class="os-table os-reload-on-coupon-update os-scrollable-table" data-route="<?php echo OsRouterHelper::build_route_name('coupons', 'index'); ?>">
          <thead>
            <tr>
              <th><?php esc_html_e('ID', 'latepoint-pro-features'); ?></th>
              <th class="text-left"><?php esc_html_e('Name', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Code', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Discount', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Status', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Created On', 'latepoint-pro-features'); ?></th>
            </tr>
            <tr>
              <th><?php echo OsFormHelper::text_field('filter[id]', false, '', ['style' => 'width: 40px;', 'class' => 'os-table-filter', 'placeholder' => __('ID', 'latepoint-pro-features')]); ?></th>
              <th><?php echo OsFormHelper::text_field('filter[name]', false, '', ['class' => 'os-table-filter', 'placeholder' => __('Search by Name', 'latepoint-pro-features')]); ?></th>
              <th><?php echo OsFormHelper::text_field('filter[code]', false, '', ['class' => 'os-table-filter', 'placeholder' => __('Code...', 'latepoint-pro-features')]); ?></th>
              <th></th>
              <th><?php echo OsFormHelper::select_field('filter[status]', false, OsCouponsHelper::get_statuses_list(), '', ['placeholder' => __('Show All', 'latepoint-pro-features'), 'class' => 'os-table-filter']); ?></th>
              <th>
                <div class="os-form-group">
                  <div class="os-date-range-picker os-table-filter-datepicker" data-can-be-cleared="yes" data-no-value-label="<?php esc_attr_e('Filter By Date', 'latepoint-pro-features'); ?>" data-clear-btn-label="<?php esc_attr_e('Reset Date Filtering', 'latepoint-pro-features'); ?>">
                    <span class="range-picker-value"><?php esc_html_e('Filter By Date', 'latepoint-pro-features'); ?></span>
                    <i class="latepoint-icon latepoint-icon-chevron-down"></i>
                    <input type="hidden" class="os-table-filter os-datepicker-date-from" name="filter[created_at_from]" value=""/>
                    <input type="hidden" class="os-table-filter os-datepicker-date-to" name="filter[created_at_to]" value=""/>
                  </div>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php include('_table_body.php'); ?>
          </tbody>
          <tfoot>
            <tr>
              <th><?php esc_html_e('ID', 'latepoint-pro-features'); ?></th>
              <th class="text-left"><?php esc_html_e('Name', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Code', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Discount', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Status', 'latepoint-pro-features'); ?></th>
              <th><?php esc_html_e('Created On', 'latepoint-pro-features'); ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
	    </div>
    <div class="os-pagination-w">
      <div class="pagination-info"><?php echo esc_html__('Showing', 'latepoint-pro-features'). ' <span class="os-pagination-from">'. $showing_from . '</span>-<span class="os-pagination-to">'. $showing_to .'</span> '.__('of', 'latepoint-pro-features').' <span class="os-pagination-total">'. $total_coupons. '</span>'; ?></div>
      <div class="pagination-page-select-w">
        <label for="tablePaginationPageSelector"><?php esc_html_e('Page:', 'latepoint-pro-features'); ?></label>
        <select id="tablePaginationPageSelector" name="page" class="pagination-page-select">
          <?php
          for($i = 1; $i <= $total_pages; $i++){
            $selected = ($current_page_number == $i) ? 'selected' : '';
            echo '<option '.$selected.'>'.$i.'</option>';
          } ?>
        </select>
      </div>
    </div>
    </div>
<?php }else{ ?>
  <div class="no-results-w">
    <div class="icon-w"><i class="latepoint-icon latepoint-icon-tag"></i></div>
    <h2><?php esc_html_e('No Coupons Found', 'latepoint-pro-features'); ?></h2>
    <a href="#" <?php echo OsCouponsHelper::quick_coupon_btn_html(); ?> class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus"></i><span><?php esc_html_e('Add Coupon', 'latepoint-pro-features'); ?></span></a>
  </div>
<?php } ?>