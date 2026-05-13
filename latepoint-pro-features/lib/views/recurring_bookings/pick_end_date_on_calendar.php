<?php
/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

/* @var $target_datetime OsWpDateTime */
?>
<div class="os-dates-and-times-w days-only">
	<?php
	$weekdays   = OsBookingHelper::get_weekdays_arr();
	$today_date = new OsWpDateTime( 'today' );


	?>
    <div class="os-current-month-label-w calendar-mobile-controls">
        <div class="os-current-month-label">
            <div class="current-month">
				<?php echo esc_html( OsUtilHelper::get_month_name_by_number( $target_datetime->format( 'n' ) ) ); ?>
            </div>
            <div class="current-year"><?php echo esc_html( $target_datetime->format( 'Y' ) ); ?></div>
        </div>
        <div class="os-month-control-buttons-w">
            <button type="button" class="os-month-prev-btn" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'recurring_bookings', 'load_datepicker_month' ) ); ?>">
                <i class="latepoint-icon latepoint-icon-arrow-left"></i></button>
            <button type="button" class="os-month-next-btn" data-route="<?php echo esc_attr( OsRouterHelper::build_route_name( 'recurring_bookings', 'load_datepicker_month' ) ); ?>">
                <i class="latepoint-icon latepoint-icon-arrow-right"></i></button>
        </div>
    </div>
    <div class="os-weekdays">
		<?php
		$start_of_week = OsSettingsHelper::get_start_of_week();

		// Output the divs for each weekday
		for ( $i = $start_of_week - 1; $i < $start_of_week - 1 + 7; $i ++ ) {
			// Calculate the index within the range of 0-6
			$index = $i % 7;

			// Output the div for the current weekday
			echo '<div class="weekday weekday-' . esc_attr( $index + 1 ) . '">' . esc_html( mb_substr( $weekdays[ $index ], 0, 1 ) ) . '</div>';
		}
		?>
    </div>
    <div class="os-months">
		<?php
		OsCalendarHelper::generate_monthly_calendar_days_only( $target_datetime->format( 'Y-m-d' ), false, true );
        $next_month_target_date = clone $target_datetime;
        $next_month_target_date->modify( 'first day of next month' );
        OsCalendarHelper::generate_monthly_calendar_days_only( $next_month_target_date->format( 'Y-m-d' ) );
		?>
    </div>
</div>