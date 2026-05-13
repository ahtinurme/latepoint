<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="addons-info-holder" data-route="<?php echo esc_attr(OsRouterHelper::build_route_name('addons', 'load_addons_list')); ?>">
	<span class="loading"><?php esc_html_e('Loading Addons Information', 'latepoint-pro-features'); ?></span>
</div>