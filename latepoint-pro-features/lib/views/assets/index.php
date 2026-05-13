<?php
/**
 * @var OsAssetModel[] $assets
 * @var int $total_services
 * @var int $total_agents
 */
?>
<?php if ( ! OsSettingsHelper::is_on( 'enable_assets_functionality' ) ) { ?>
	<div class="latepoint-message latepoint-message-subtle">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %1$s and %2$s are opening and closing anchor tags */
				__( 'Assets functionality is currently disabled. You can enable it in %1$sGeneral Settings%2$s.', 'latepoint-pro-features' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=latepoint&route_name=settings__general#stickySectionRestrictions' ) ) . '">',
				'</a>'
			),
			[
				'a' => [ 'href' => [] ],
			]
		);
		?>
	</div>
<?php } else { ?>
	<div class="os-assets-list os-resources-grid">
		<?php foreach ( $assets as $asset ) { ?>
			<div class="os-asset os-resource-grid-item os-asset-status-<?php echo esc_attr( $asset->status ); ?>">
				<div class="os-asset-header">
					<h3 class="asset-name"><?php echo esc_html( $asset->name ); ?></h3>
				</div>
				<div class="os-asset-body">
					<div class="os-asset-connections">
						<div class="label"><?php esc_html_e( 'Services:', 'latepoint-pro-features' ); ?></div>
						<div class="selected-count <?php echo ( count( $asset->get_services() ) < $total_services ) ? '' : 'selected-count-all'; ?>">
							<?php
							echo ( count( $asset->get_services() ) < $total_services )
								? ( count( $asset->get_services() ) . esc_html__( ' of ', 'latepoint-pro-features' ) . $total_services )
								: esc_html__( 'All Selected', 'latepoint-pro-features' );
							?>
						</div>
					</div>
					<div class="os-asset-connections">
						<div class="label"><?php esc_html_e( 'Agents:', 'latepoint-pro-features' ); ?></div>
						<div class="selected-count <?php echo ( count( $asset->get_agents() ) < $total_agents ) ? '' : 'selected-count-all'; ?>">
							<?php
							echo ( count( $asset->get_agents() ) < $total_agents )
								? ( count( $asset->get_agents() ) . esc_html__( ' of ', 'latepoint-pro-features' ) . $total_agents )
								: esc_html__( 'All Selected', 'latepoint-pro-features' );
							?>
						</div>
					</div>
					<div class="os-asset-info">
						<div class="asset-info-row">
							<div class="label"><?php esc_html_e( 'Quantity:', 'latepoint-pro-features' ); ?></div>
							<div class="value"><strong><?php echo esc_html( $asset->quantity ); ?></strong></div>
						</div>
						<div class="asset-info-row">
							<div class="label"><?php esc_html_e( 'Status:', 'latepoint-pro-features' ); ?></div>
							<div class="value"><strong><?php echo esc_html( $asset->status ); ?></strong></div>
						</div>
					</div>
				</div>
				<div class="os-asset-foot">
					<a href="<?php echo esc_url( OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'assets', 'edit_form' ), [ 'id' => $asset->id ] ) ); ?>" class="latepoint-btn latepoint-btn-block latepoint-btn-secondary">
						<i class="latepoint-icon latepoint-icon-edit-3"></i>
						<span><?php esc_html_e( 'Edit Asset', 'latepoint-pro-features' ); ?></span>
					</a>
				</div>
			</div>
		<?php } ?>

		<?php if ( OsRolesHelper::can_user( 'asset__create' ) ) { ?>
			<?php echo OsUtilHelper::add_resource_link_html( __( 'New Asset', 'latepoint-pro-features' ), OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'assets', 'new_form' ) ) ); ?>
		<?php } ?>
	</div>
<?php } ?>
