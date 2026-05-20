<?php

class OsShortLinksSystemsHelper {


	public static function is_external_short_links_system_enabled( string $external_short_links_system_code ): bool {
		return OsSettingsHelper::is_on( 'enable_' . $external_short_links_system_code );
	}

	public static function get_list_of_external_short_links_systems( $enabled_only = false ) {
		$external_short_links_systems = [];
		/**
		 * Returns an array of external short links systems
		 *
		 * @since 5.1.94
		 * @hook latepoint_list_of_external_short_links_systems
		 *
		 * @param {array} array of short links systems
		 * @param {bool} filter to return only short links systems that are enabled
		 *
		 * @returns {array} The array of external short links systems
		 *
		 */
		return apply_filters( 'latepoint_list_of_external_short_links_systems', $external_short_links_systems, $enabled_only );
	}
}
