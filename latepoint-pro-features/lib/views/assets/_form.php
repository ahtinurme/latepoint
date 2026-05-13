<?php
/**
 * @var OsAssetModel $asset
 * @var OsServiceModel[] $services
 * @var OsAgentModel[] $agents
 */
?>
<div class="os-form-w">
	<form action=""
		data-os-success-action="redirect"
		data-os-redirect-to="<?php echo esc_url( OsRouterHelper::build_link( [ 'assets', 'index' ] ) ); ?>"
		data-os-action="<?php echo esc_attr( $asset->is_new_record() ? OsRouterHelper::build_route_name( 'assets', 'create' ) : OsRouterHelper::build_route_name( 'assets', 'update' ) ); ?>">

		<?php wp_nonce_field( $asset->is_new_record() ? 'new_asset' : 'edit_asset_' . $asset->id ); ?>

		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php esc_html_e( 'Basic Information', 'latepoint-pro-features' ); ?></h3>
					<?php if ( ! $asset->is_new_record() ) { ?>
						<div class="os-form-sub-header-actions"><?php echo esc_html__( 'Asset ID:', 'latepoint-pro-features' ) . esc_html( $asset->id ); ?></div>
					<?php } ?>
				</div>
			</div>
			<div class="white-box-content">
				<?php echo OsFormHelper::text_field( 'asset[name]', __( 'Asset Name', 'latepoint-pro-features' ), $asset->name ); ?>
				<div class="os-row">
					<div class="os-col-lg-6">
						<?php echo OsFormHelper::text_field( 'asset[quantity]', __( 'Quantity (Capacity)', 'latepoint-pro-features' ), $asset->quantity, [ 'type' => 'number', 'min' => '1' ] ); ?>
					</div>
					<div class="os-col-lg-6">
						<?php
						echo OsFormHelper::select_field(
							'asset[status]',
							__( 'Status', 'latepoint-pro-features' ),
							[
								LATEPOINT_ASSET_STATUS_ACTIVE   => __( 'Active', 'latepoint-pro-features' ),
								LATEPOINT_ASSET_STATUS_DISABLED => __( 'Disabled', 'latepoint-pro-features' ),
							],
							$asset->status
						);
						?>
					</div>
				</div>
				<?php echo OsFormHelper::textarea_field( 'asset[short_description]', __( 'Short Description', 'latepoint-pro-features' ), $asset->short_description, [ 'rows' => 3 ] ); ?>
			</div>
		</div>

		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php esc_html_e( 'Connected Services', 'latepoint-pro-features' ); ?></h3>
					<div class="os-form-sub-header-actions">
						<?php echo OsFormHelper::checkbox_field( 'select_all_services', __( 'Select All', 'latepoint-pro-features' ), 'off', false, [ 'class' => 'os-select-all-toggler' ] ); ?>
					</div>
				</div>
			</div>
			<div class="white-box-content">
				<div class="os-complex-connections-selector">
					<?php
					if ( $services ) {
						foreach ( $services as $service ) {
							$is_active_service       = $asset->is_new_record() ? true : $asset->has_service( $service->id );
							$is_active_service_value = $is_active_service ? 'yes' : 'no';
							$active_class            = $is_active_service ? 'active' : '';
							?>
							<div class="connection <?php echo esc_attr( $active_class ); ?>">
								<div class="connection-i selector-trigger">
									<div class="connection-avatar"><img src="<?php echo esc_url( $service->get_selection_image_url() ); ?>"/></div>
									<h3 class="connection-name"><?php echo esc_html( $service->name ); ?></h3>
									<?php echo OsFormHelper::hidden_field( 'asset[services][service_' . $service->id . '][connected]', $is_active_service_value, [ 'class' => 'connection-child-is-connected' ] ); ?>
								</div>
							</div>
							<?php
						}
					}
					?>
				</div>
			</div>
		</div>

		<div class="white-box">
			<div class="white-box-header">
				<div class="os-form-sub-header">
					<h3><?php esc_html_e( 'Connected Agents', 'latepoint-pro-features' ); ?></h3>
					<div class="os-form-sub-header-actions">
						<?php echo OsFormHelper::checkbox_field( 'select_all_agents', __( 'Select All', 'latepoint-pro-features' ), 'off', false, [ 'class' => 'os-select-all-toggler' ] ); ?>
					</div>
				</div>
			</div>
			<div class="white-box-content">
				<div class="os-complex-connections-selector">
					<?php
					if ( $agents ) {
						foreach ( $agents as $agent ) {
							$is_active_agent       = $asset->is_new_record() ? true : $asset->has_agent( $agent->id );
							$is_active_agent_value = $is_active_agent ? 'yes' : 'no';
							$active_class          = $is_active_agent ? 'active' : '';
							?>
							<div class="connection <?php echo esc_attr( $active_class ); ?>">
								<div class="connection-i selector-trigger">
									<div class="connection-avatar"><img src="<?php echo esc_url( $agent->get_avatar_url() ); ?>"/></div>
									<h3 class="connection-name"><?php echo esc_html( $agent->full_name ); ?></h3>
									<?php echo OsFormHelper::hidden_field( 'asset[agents][agent_' . $agent->id . '][connected]', $is_active_agent_value, [ 'class' => 'connection-child-is-connected' ] ); ?>
								</div>
							</div>
							<?php
						}
					}
					?>
				</div>
			</div>
		</div>

		<div class="os-form-buttons os-flex">
			<?php
			if ( $asset->is_new_record() ) {
				echo OsFormHelper::button( 'submit', __( 'Add Asset', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] );
			} else {
				echo OsFormHelper::hidden_field( 'asset[id]', $asset->id );
				echo '<a href="#" class="latepoint-btn latepoint-btn-danger remove-service-btn" style="margin-left: auto;"
						data-os-prompt="' . esc_attr__( 'Are you sure you want to remove this asset? You can also change status to disabled if you want to temporarily disable it instead.', 'latepoint-pro-features' ) . '"
						data-os-redirect-to="' . esc_url( OsRouterHelper::build_link( [ 'assets', 'index' ] ) ) . '"
						data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $asset->id ], 'destroy_asset_' . $asset->id ) . '"
						data-os-success-action="redirect"
						data-os-action="' . esc_attr( OsRouterHelper::build_route_name( 'assets', 'destroy' ) ) . '">' . esc_html__( 'Delete Asset', 'latepoint-pro-features' ) . '</a>';
				echo OsFormHelper::button( 'submit', __( 'Save Changes', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] );
			}
			?>
		</div>
	</form>
</div>
