<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $target_datetime OsWpDateTime */
?>

<?php OsCalendarHelper::generate_monthly_calendar_days_only( $target_datetime->format( 'Y-m-d' ), false, true ); ?>