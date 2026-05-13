<?php

class OsFeatureServiceCategoriesHelper {

	public static function generate_service_categories_list( $parent_id = false ) {
		$service_categories = new OsServiceCategoryModel();
		$args               = array();
		$args['parent_id']  = $parent_id ? $parent_id : 'IS NULL';
		$service_categories = $service_categories->where( $args )->order_by( 'order_number asc' )->get_results_as_models();
		if ( ! is_array( $service_categories ) ) {
			return;
		}
		if ( $service_categories ) {
			foreach ( $service_categories as $service_category ) { ?>
				<div class="os-category-parent-w" data-id="<?php echo $service_category->id; ?>">
					<div class="os-category-w">
						<div class="os-category-head">
							<div class="os-category-drag"></div>
							<div class="os-category-name"><?php echo $service_category->name; ?></div>
							<div class="os-category-items-meta"><?php _e( 'ID: ', 'latepoint-pro-features' ); ?><span><?php echo $service_category->id; ?></span></div>
							<div class="os-category-items-count"><span><?php echo $service_category->count_services(); ?></span> <?php _e( 'Services Linked', 'latepoint-pro-features' ); ?></div>
							<button class="os-category-edit-btn"><i class="latepoint-icon latepoint-icon-edit-3"></i></button>
						</div>
						<div class="os-category-body">
							<?php include plugin_dir_path( __FILE__ ) . '../views/service_categories/_form.php'; ?>
						</div>
					</div>
					<div class="os-category-children">
						<?php
						$services = $service_category->get_services( true );
						if ( is_array( $services ) ) {
							foreach ( $services as $service ) {
								echo '<div class="item-in-category-w status-' . esc_attr( $service->status ) . '" data-id="' . $service->id . '"><div class="os-category-item-drag"></div><div class="os-category-item-name">' . esc_html( $service->name ) . '</div><div class="os-category-item-meta">ID: ' . $service->id . '</div></div>';
							}
						} ?>
						<?php OsFeatureServiceCategoriesHelper::generate_service_categories_list( $service_category->id ); ?>
					</div>
				</div>
				<?php
			}
		}
	}
}