<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $booking OsBookingModel */
/* @var $target_datetime OsWpDateTime */
?>

<?php echo OsCalendarHelper::generate_dates_and_times_picker($booking, $target_datetime, false, [ 'timezone_name' => OsTimeHelper::get_timezone_name_from_session(), 'consider_cart_items' => true, 'highlight_target_date' => true ]); ?>
