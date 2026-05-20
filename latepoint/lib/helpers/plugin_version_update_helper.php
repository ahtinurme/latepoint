<?php

class OsPluginVersionUpdateHelper {

	/**
	 * Run version-specific updates when the plugin version changes.
	 *
	 * @return void
	 */
	public static function init() {

		$saved_version = get_option( 'latepoint_plugin_version', '0' );

		do_action( 'latepoint_update_init', $saved_version );

		if ( version_compare( $saved_version, LATEPOINT_VERSION, '=' ) ) {
			return;
		}

		do_action( 'latepoint_update_before', $saved_version );

		update_option( 'latepoint_plugin_version', LATEPOINT_VERSION, false );

		do_action( 'latepoint_update_after', $saved_version );
	}
}
