<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/* @var $ordered_columns array */
/* @var $selected_columns array */
?>
<form class="latepoint-lightbox-wrapper-form" action="" data-os-success-action="reload" data-os-action="<?php echo esc_attr(OsRouterHelper::build_route_name('settings', 'save_columns_for_bookings_table')); ?>">
<?php wp_nonce_field( 'bookings_table_columns' ); ?>
<input type="hidden" name="columns_order" id="bookings_columns_order" value="">
<div class="latepoint-lightbox-heading">
	<h2><?php esc_html_e('Table Settings', 'latepoint'); ?></h2>
</div>
<div class="latepoint-lightbox-content">
	<div class="os-column-order-w">
		<p class="os-column-order-hint"><?php esc_html_e('Drag to reorder columns. Toggle optional columns on or off.', 'latepoint'); ?></p>
		<div class="os-column-order-list">
			<?php foreach ( $ordered_columns as $col_key => $col_def ) : ?>
				<?php if ( 'id' === $col_key ) continue; ?>
				<div class="os-column-order-item" data-column-key="<?php echo esc_attr( $col_key ); ?>">
					<div class="os-column-order-drag os-column-order-drag-handle">
						<i class="latepoint-icon latepoint-icon-menu os-column-order-drag-handle"></i>
					</div>
					<div class="os-column-order-label"><?php echo esc_html( $col_def['label'] ); ?></div>
					<div class="os-column-order-toggle">
						<?php if ( 'extra' === $col_def['type'] ) : ?>
							<?php
							$extra_type = $col_def['extra_type'];
							$extra_key  = $col_def['extra_key'];
							$is_selected = isset( $selected_columns[ $extra_type ] ) && in_array( $extra_key, $selected_columns[ $extra_type ], true );
							echo OsFormHelper::toggler_field( 'selected_columns[' . $extra_type . '][' . $extra_key . ']', '', $is_selected );
							?>
						<?php elseif ( ! empty( $col_def['condition'] ) ) : ?>
							<span class="os-column-order-badge os-column-order-badge-auto"><?php esc_html_e( 'Auto', 'latepoint' ); ?></span>
						<?php else : ?>
							<span class="os-column-order-badge"><?php esc_html_e( 'Always', 'latepoint' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<div class="latepoint-lightbox-footer">
	<button type="submit" class="latepoint-btn latepoint-btn-block latepoint-btn-lg latepoint-btn-outline"><?php esc_html_e('Save Table Settings', 'latepoint'); ?></button>
</div>
</form>
