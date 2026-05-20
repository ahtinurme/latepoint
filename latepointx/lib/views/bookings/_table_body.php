<?php
/* @var $bookings OsBookingModel[] */
/* @var $services_list array */
/* @var $locations_list array */
/* @var $agents_list array */
/* @var $selected_columns array */
/* @var $ordered_columns array */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php
if($bookings){
  foreach ($bookings as $booking): ?>
    <tr class="os-clickable-row" <?php echo OsBookingHelper::quick_booking_btn_html($booking->id); ?>>
      <?php
      foreach ( $ordered_columns as $col_key => $col_def ) {
        if ( ! OsSettingsHelper::is_bookings_column_visible( $col_def, $selected_columns, count( $services_list ), count( $agents_list ), count( $locations_list ) ) ) continue;
        echo OsBookingHelper::render_table_body_cell( $col_key, $col_def, $booking );
      }
      ?>
    </tr>
    <?php
  endforeach;
}?>
