<?php
/**
 * Uninstall routine.
 *
 * Runs only when the plugin is deleted from the Plugins screen, never on
 * deactivation — deactivating a plugin must not destroy user data.
 *
 * Everything the plugin writes is removed here, including cached results,
 * which live in transients and would otherwise survive the plugin forever.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the options and cached results this plugin creates for a single site.
 *
 * @return void
 */
function silc_wia_uninstall_site(): void {
	delete_option( 'silc_wia_api_settings' );
	delete_option( 'silc_wia_ai_backend' );
	delete_option( 'silc_wia_insight_history' );
	delete_option( 'silc_wia_insight_cache' );      // Older releases.
	delete_option( 'silc_wia_query_history' );      // Older releases, unreferenced.
	delete_option( 'silc_wia_storage_version' );

	global $wpdb;

	// Cached insights are transients: the payload is a full rendered answer,
	// so both the value and its timeout row have to go.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transients have no bulk-delete API.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_silc_wia_insight_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_silc_wia_insight_' ) . '%'
		)
	);
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
