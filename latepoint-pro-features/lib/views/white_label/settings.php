<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form action="" data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'settings', 'update' ) ); ?>">
<div class="latepoint-page-with-side-nav">
<div class="latepoint-settings-w os-form-w">
	<?php wp_nonce_field( 'update_settings' ); ?>

	<!-- ======================= MASTER TOGGLE ======================= -->
	<div class="white-box section-anchor" id="stickySectionWhiteLabelToggle">
		<div class="white-box-header">
			<div class="os-form-sub-header"><h3><?php esc_html_e( 'White Label', 'latepoint-pro-features' ); ?></h3></div>
		</div>
		<div class="white-box-content no-padding">
			<div class="sub-section-row">
				<div class="sub-section-label">
					<h3><?php esc_html_e( 'Enable White Label', 'latepoint-pro-features' ); ?></h3>
				</div>
				<div class="sub-section-content">
					<div class="os-row">
						<div class="os-col-lg-12">
							<?php
							echo OsFormHelper::toggler_field(
								'settings[white_label_enabled]',
								__( 'Enable white labeling', 'latepoint-pro-features' ),
								OsSettingsHelper::is_on( 'white_label_enabled' ),
								false,
								false,
								[ 'sub_label' => __( 'When enabled, your custom brand settings below replace all LatePoint references in the admin interface.', 'latepoint-pro-features' ) ]
							);
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================= BRAND IDENTITY ======================= -->
	<div class="white-box section-anchor" id="stickySectionBrandIdentity">
		<div class="white-box-header">
			<div class="os-form-sub-header"><h3><?php esc_html_e( 'Brand Identity', 'latepoint-pro-features' ); ?></h3></div>
		</div>
		<div class="white-box-content no-padding">

			<!-- Brand Name -->
			<div class="sub-section-row">
				<div class="sub-section-label">
					<?php echo OsFeatureWhiteLabelHelper::render_section_heading( __( 'Brand Name', 'latepoint-pro-features' ), __( 'Replaces "LatePoint" in the WP admin menu, admin bar, and plugin list.', 'latepoint-pro-features' ) ); ?>
				</div>
				<div class="sub-section-content">
					<div class="os-row">
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'settings[white_label_brand_name]', __( 'Brand Name', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'white_label_brand_name' ), [ 'theme' => 'simple', 'placeholder' => 'e.g. Acme Booking' ] ); ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Brand Logo -->
			<div class="sub-section-row">
				<div class="sub-section-label">
					<?php echo OsFeatureWhiteLabelHelper::render_section_heading( __( 'Brand Logo', 'latepoint-pro-features' ), __( 'Used for the LatePoint admin sidebar logo and the WordPress top-level admin menu icon. Recommended size: 40×40 px.', 'latepoint-pro-features' ) ); ?>
				</div>
				<div class="sub-section-content">
					<?php
					echo OsFormHelper::media_uploader_field(
						'settings[white_label_brand_logo]',
						0,
						__( 'Set Logo', 'latepoint-pro-features' ),
						__( 'Remove Logo', 'latepoint-pro-features' ),
						OsSettingsHelper::get_settings_value( 'white_label_brand_logo', 0 )
					);
					?>
				</div>
			</div>

		</div>
	</div>

	<!-- ======================= PLUGIN DETAILS ======================= -->
	<div class="white-box section-anchor" id="stickySectionPluginRow">
		<div class="white-box-header">
			<div class="os-form-sub-header"><h3><?php esc_html_e( 'Plugin Details', 'latepoint-pro-features' ); ?></h3></div>
		</div>
		<div class="white-box-content no-padding">
			<div class="sub-section-row">
				<div class="sub-section-label">
					<?php echo OsFeatureWhiteLabelHelper::render_section_heading( __( 'Plugin Details', 'latepoint-pro-features' ), __( 'Override the plugin name, URL, description, author, and author URL shown in wp-admin → Plugins.', 'latepoint-pro-features' ) ); ?>
				</div>
				<div class="sub-section-content">
					<div class="os-row os-mb-3">
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'settings[white_label_plugin_row_name]', __( 'Plugin Name', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'white_label_plugin_row_name' ), [ 'theme' => 'simple', 'placeholder' => 'e.g. Acme Booking' ] ); ?>
						</div>
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'settings[white_label_plugin_row_uri]', __( 'Plugin URL', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'white_label_plugin_row_uri' ), [ 'theme' => 'simple', 'type' => 'url', 'placeholder' => 'https://your-site.com' ] ); ?>
						</div>
					</div>
					<div class="os-row os-mb-3">
						<div class="os-col-lg-12">
							<?php echo OsFormHelper::text_field( 'settings[white_label_plugin_row_description]', __( 'Plugin Description', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'white_label_plugin_row_description' ), [ 'theme' => 'simple', 'placeholder' => 'e.g. Appointment booking made simple.' ] ); ?>
						</div>
					</div>
					<div class="os-row">
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'settings[white_label_plugin_row_author]', __( 'Author', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'white_label_plugin_row_author' ), [ 'theme' => 'simple', 'placeholder' => 'e.g. Acme Corp' ] ); ?>
						</div>
						<div class="os-col-lg-6">
							<?php echo OsFormHelper::text_field( 'settings[white_label_plugin_row_author_uri]', __( 'Author URL', 'latepoint-pro-features' ), OsSettingsHelper::get_settings_value( 'white_label_plugin_row_author_uri' ), [ 'theme' => 'simple', 'type' => 'url', 'placeholder' => 'https://your-company.com' ] ); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ======================= MENU VISIBILITY ======================= -->
	<div class="white-box section-anchor" id="stickySectionMenuVisibility">
		<div class="white-box-header">
			<div class="os-form-sub-header"><h3><?php esc_html_e( 'Menu Visibility', 'latepoint-pro-features' ); ?></h3></div>
		</div>
		<div class="white-box-content no-padding">
			<div class="sub-section-row">
				<div class="sub-section-label">
					<h3><?php esc_html_e( 'Hide White Label Menu', 'latepoint-pro-features' ); ?></h3>
				</div>
				<div class="sub-section-content">
					<div class="os-row">
						<div class="os-col-lg-12">
							<?php
							echo OsFormHelper::toggler_field(
								'settings[white_label_hide_menu]',
								__( 'Hide the White Label settings menu', 'latepoint-pro-features' ),
								OsSettingsHelper::is_on( 'white_label_hide_menu' ),
								false,
								false,
								[ 'sub_label' => __( 'When enabled, the White Label menu item is removed from the admin sidebar. To restore it, deactivate and reactivate the LatePoint Pro Features plugin.', 'latepoint-pro-features' ) ]
							);
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="os-form-buttons">
		<?php echo OsFormHelper::button( 'submit', __( 'Save Settings', 'latepoint-pro-features' ), 'submit', [ 'class' => 'latepoint-btn' ] ); ?>
		<a
			href="#"
			class="latepoint-btn latepoint-btn-danger latepoint-btn-outline"
			data-os-action="<?php echo esc_attr( OsRouterHelper::build_route_name( 'white_label', 'reset' ) ); ?>"
			data-os-prompt="<?php esc_attr_e( 'This will clear all white label settings and restore LatePoint defaults. Continue?', 'latepoint-pro-features' ); ?>"
			data-os-params="<?php echo esc_attr( '_wpnonce=' . wp_create_nonce( 'update_settings' ) ); ?>"
			data-os-success-action="reload"
		><?php esc_html_e( 'Reset to Defaults', 'latepoint-pro-features' ); ?></a>
	</div>

</div>

<div class="latepoint-page-side-nav">
	<div class="side-nav-actions">
		<button type="submit" class="latepoint-btn latepoint-btn-block"><i class="latepoint-icon latepoint-icon-check"></i><span><?php esc_html_e( 'Save Changes', 'latepoint-pro-features' ); ?></span></button>
	</div>
	<div class="side-nav-body">
		<div><a href="#stickySectionWhiteLabelToggle" class="is-active"><?php esc_html_e( 'Enable', 'latepoint-pro-features' ); ?></a></div>
		<div><a href="#stickySectionBrandIdentity"><?php esc_html_e( 'Brand Identity', 'latepoint-pro-features' ); ?></a></div>
		<div><a href="#stickySectionPluginRow"><?php esc_html_e( 'Plugin Details', 'latepoint-pro-features' ); ?></a></div>
		<div><a href="#stickySectionMenuVisibility"><?php esc_html_e( 'Menu Visibility', 'latepoint-pro-features' ); ?></a></div>
	</div>
</div>

</div>
</form>
