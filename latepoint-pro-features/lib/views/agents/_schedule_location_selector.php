<?php
/**
 * Location selector dropdown for agent schedule sections.
 *
 * @var string $schedule_location_selector_id Unique ID for the select element.
 * @var array  $connected_locations           Array of location models connected to the agent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="os-form-sub-header-actions">
	<label class="schedule-location-label" for="<?php echo esc_attr( $schedule_location_selector_id ); ?>"><?php _e( 'Location:', 'latepoint-pro-features' ); ?></label>
	<select id="<?php echo esc_attr( $schedule_location_selector_id ); ?>" name="<?php echo esc_attr( $schedule_location_selector_id ); ?>" class="schedule-location-selector">
		<option value="general"><?php _e( 'General', 'latepoint-pro-features' ); ?></option>
		<?php foreach ( $connected_locations as $loc ) : ?>
			<option value="<?php echo esc_attr( $loc->id ); ?>">
				<?php echo esc_html( $loc->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
