<?php
/**
 * @var $bundle OsBundleModel
 * @var $services OsServiceModel[]
 */
?>
<div class="os-form-w">
	<form action="" data-os-success-action="redirect"
	      data-os-redirect-to="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('bundles', 'index')); ?>"
	      data-os-action="<?php echo $bundle->is_new_record() ? OsRouterHelper::build_route_name('bundles', 'create') : OsRouterHelper::build_route_name('bundles', 'update'); ?>">

		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php _e('General Information', 'latepoint-pro-features'); ?></h3>
					<?php if (!$bundle->is_new_record()) { ?>
						<div class="os-form-sub-header-actions"><?php echo __('Bundle ID:', 'latepoint-pro-features') . $bundle->id; ?></div>
					<?php } ?>
				</div>
			</div>
			<div class="white-box-content">
				<div class="os-row">
					<div class="os-col-lg-6">
						<?php echo OsFormHelper::text_field('bundle[name]', __('Bundle Name', 'latepoint-pro-features'), $bundle->name, ['theme' => 'simple']); ?>
					</div>
					<div class="os-col-lg-3">
						<?php echo OsFormHelper::select_field('bundle[visibility]', __('Visibility', 'latepoint-pro-features'), array(LATEPOINT_BUNDLE_VISIBILITY_VISIBLE => __('Visible to everyone', 'latepoint-pro-features'), LATEPOINT_BUNDLE_VISIBILITY_HIDDEN => __('Visible only to admins and agents', 'latepoint-pro-features')), $bundle->visibility); ?>
					</div>
					<div class="os-col-lg-3">
						<?php echo OsFormHelper::select_field('bundle[status]', __('Status', 'latepoint-pro-features'), array(LATEPOINT_BUNDLE_STATUS_ACTIVE => __('Active', 'latepoint-pro-features'), LATEPOINT_BUNDLE_STATUS_DISABLED => __('Disabled', 'latepoint-pro-features')), $bundle->status); ?>
					</div>
				</div>
				<div class="os-row">
					<div class="os-col-lg-6">
						<?php echo OsFormHelper::textarea_field('bundle[short_description]', __('Short Description', 'latepoint-pro-features'), $bundle->short_description, array('rows' => 1, 'theme' => 'simple')); ?>
					</div>
				</div>
			</div>
		</div>
		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header"><h3><?php _e('Price', 'latepoint-pro-features'); ?></h3></div>
			</div>
			<div class="white-box-content">
				<div class="bundle-duration-box">
					<div class="os-row">
						<div class="os-col-lg-3">
							<?php echo OsFormHelper::money_field('bundle[charge_amount]', __('Charge Amount', 'latepoint-pro-features'), $bundle->charge_amount, ['theme' => 'simple']); ?>
						</div>
						<div class="os-col-lg-3">
							<?php echo OsFormHelper::money_field('bundle[deposit_amount]', __('Deposit Amount', 'latepoint-pro-features'), $bundle->deposit_amount, ['theme' => 'simple']); ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header"><h3><?php esc_html_e( 'Display Price', 'latepoint-pro-features' ); ?></h3></div>
			</div>
			<div class="white-box-content">
				<div class="latepoint-message latepoint-message-subtle"><?php esc_html_e( 'This price is for display purposes only, it is not the price that the customer will be charged. The Charge Amount field above controls the amount that customer will be charged for. Setting both minimum and maximum price, will show a price range on the bundle selection step.', 'latepoint-pro-features' ); ?></div>
				<div class="os-row">
					<div class="os-col-lg-3">
						<?php echo OsFormHelper::money_field( 'bundle[price_min]', __( 'Minimum Price', 'latepoint-pro-features' ), $bundle->price_min, array( 'theme' => 'simple' ) ); ?>
					</div>
					<div class="os-col-lg-3">
						<?php echo OsFormHelper::money_field( 'bundle[price_max]', __( 'Maximum Price', 'latepoint-pro-features' ), $bundle->price_max, array( 'theme' => 'simple' ) ); ?>
					</div>
				</div>
			</div>
		</div>

		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header"><h3><?php _e('Included Services', 'latepoint-pro-features'); ?></h3></div>
			</div>
			<div class="white-box-content">
				<div class="os-complex-connections-selector">
					<?php if ($services) {
						foreach ($services as $service) {
							$is_connected = $bundle->is_new_record() ? false : $bundle->has_service($service->id);
							$is_connected_value = $is_connected ? 'yes' : 'no';
							?>
							<div class="connection with-quantity <?php echo $is_connected ? 'active' : ''; ?>">
								<div class="connection-i selector-trigger">
									<h3 class="connection-name"><?php echo $service->name; ?></h3>
									<?php echo OsFormHelper::hidden_field('bundle[services][service_' . $service->id . '][connected]', $is_connected_value, array('class' => 'connection-child-is-connected')); ?>
								</div>
								<?php
								$service_durations = $service->get_all_durations_arr();
								if(count($service_durations) > 1){ ?>
								<div class="complex-connection-set">
								<div class="os-service-durations">
									<div class="os-form-group os-form-select-group os-form-group-transparent">
										<label for=""><?php _e('Duration:', 'latepoint-pro-features'); ?></label>
										<select class="os-form-control" name="bundle[services][service_<?php echo $service->id; ?>][duration]" id="">
											<?php
												foreach ($service->get_all_durations_arr() as $duration) {
													$selected = ($duration['duration'] == $bundle->duration_for_service($service->id)) ? 'selected' : '';
													$custom_name = empty($duration['name']) ? sprintf(__('%d minutes', 'latepoint-pro-features'), $duration['duration']) : $duration['name'];
													echo '<option value="' . $duration['duration'] . '" ' . $selected . '>' . $custom_name . '</option>';
												}?>
										</select>
									</div>
								</div>
								</div>
								<?php }else{
									echo OsFormHelper::hidden_field('bundle[services][service_' . $service->id . '][duration]', $service->duration);
								}?>
								<?php if($service->capacity_max > 1){ ?>
									<div class="complex-connection-set">
										<?php
								    $capacity_min = empty($service->capacity_min) ? 1 : $service->capacity_min;
								    $capacity_max = empty($service->capacity_max) ? 1 : $service->capacity_max;
								    $capacity_options = [];
								    for($i = $capacity_min; $i <= $capacity_max; $i++){
								      $capacity_options[] = $i;
								    }
								    echo '<div class="booking-total-attendees-selector-w">';
								          echo OsFormHelper::select_field('bundle[services][service_' . $service->id . '][total_attendees]', __('Attendees', 'latepoint-pro-features'), $capacity_options, $bundle->total_attendees_for_service($service->id));
								    echo '</div>';
										?>
									</div>
								<?php }else{
									echo OsFormHelper::hidden_field('bundle[services][service_' . $service->id . '][total_attendees]', 1);
								}?>
								<div class="complex-connection-set">
									<label for=""><?php _e('Bundle Quantity:', 'latepoint-pro-features'); ?></label>
									<div class="os-connection-quantity-wrapper">
										<?php echo OsFormHelper::quantity_field('bundle[services][service_' . $service->id . '][quantity]', __('qty', 'latepoint-pro-features'), ($is_connected_value ? $bundle->quantity_for_service($service->id) : 0)); ?>
									</div>
								</div>
							</div>
							<?php
						}
					} else { ?>
						<div class="no-results-w">
							<div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
							<h2><?php _e('No Existing Services Found', 'latepoint-pro-features'); ?></h2>
							<a href="<?php echo OsRouterHelper::build_link(['services', 'new_form']) ?>" class="latepoint-btn"><i
									class="latepoint-icon latepoint-icon-plus"></i><span><?php _e('Add First Service', 'latepoint-pro-features'); ?></span></a>
						</div> <?php
					}
					?>
				</div>
			</div>
		</div>
		<?php do_action('latepoint_bundle_form_after', $bundle); ?>
		<div class="os-form-buttons os-flex">
			<?php
			if ($bundle->is_new_record()) {
				echo OsFormHelper::hidden_field('bundle[id]', '');
				wp_nonce_field('latepoint_new_bundle', '_wpnonce', false);
				echo OsFormHelper::button('submit', __('Save Bundle', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']);
			} else {
				echo OsFormHelper::hidden_field('bundle[id]', $bundle->id);
				wp_nonce_field('latepoint_edit_bundle_' . $bundle->id, '_wpnonce', false);
				if (OsRolesHelper::can_user('bundle__delete')) {
					echo '<a href="#" class="latepoint-btn latepoint-btn-danger remove-bundle-btn" style="margin-left: auto;"
                data-os-prompt="' . __('Are you sure you want to remove this bundle? It will remove all appointments associated with it. You can also change status to disabled if you want to temporary disable it instead.', 'latepoint-pro-features') . '"
                data-os-redirect-to="' . OsRouterHelper::build_link(OsRouterHelper::build_route_name('bundles', 'index')) . '"
                data-os-params="' . OsUtilHelper::build_os_params(['id' => $bundle->id], 'latepoint_destroy_bundle_' . $bundle->id) . '"
                data-os-success-action="redirect"
                data-os-action="' . OsRouterHelper::build_route_name('bundles', 'destroy') . '">' . __('Delete Bundle', 'latepoint-pro-features') . '</a>';
				}
				if (OsRolesHelper::can_user('bundle__edit')) {
					echo OsFormHelper::button('submit', __('Save Bundle', 'latepoint-pro-features'), 'submit', ['class' => 'latepoint-btn']);
				}
			}

			?>
		</div>
	</form>
</div>