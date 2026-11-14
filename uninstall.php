<?php
/**
 * Uninstall routine.
 *
 * Runs only when the plugin is deleted from the Plugins screen, never on
 * deactivation — deactivating a plugin must not destroy user data.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the options this plugin creates for a single site.
 *
 * @return void
 */
function silc_wia_uninstall_site(): void {
	delete_option( 'silc_wia_api_settings' );
	delete_option( 'silc_wia_insight_history' );
	delete_option( 'silc_wia_insight_cache' );
}

if ( is_multisite() ) {
	$silc_wia_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $silc_wia_sites as $silc_wia_site_id ) {
		switch_to_blog( (int) $silc_wia_site_id );
		silc_wia_uninstall_site();
		restore_current_blog();
	}
} else {
	silc_wia_uninstall_site();
}
