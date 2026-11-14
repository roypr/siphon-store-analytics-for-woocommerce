<?php

/**
 * Plugin Name: Store Insights with AI Lite
 * Plugin URI:  https://github.com/roypr/siphon-store-insight-lite
 * Description: Natural language to SQL query tool for WooCommerce. Uses any OpenAI-compatible API (BYOK) to convert plain English questions into SQL queries against WooCommerce data.
 * Version:     3.1.0
 * Author:      Roy Parthapratim
 * Author URI:  https://github.com/roypr
 * Text Domain: siphon-store-insight-lite
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * WC requires at least: 8.0
 * License:     GPL v2 or later
 *
 * @package SILC_WooInsight_AI
 */

// Guard against direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SILC_WIA_VERSION', '3.1.0' );
define( 'SILC_WIA_PATH', plugin_dir_path( __FILE__ ) );
define( 'SILC_WIA_URL', plugin_dir_url( __FILE__ ) );
define( 'SILC_WIA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin after WooCommerce is loaded.
 */
add_action( 'plugins_loaded', function () {
	// Ensure WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			$message = __( 'Store Insights with AI Lite requires WooCommerce to be installed and activated.', 'siphon-store-insight-lite' );
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
		} );
		return;
	}

	// Load required files.
	require_once SILC_WIA_PATH . 'includes/class-woo-schema.php';
	require_once SILC_WIA_PATH . 'includes/class-api.php';
	require_once SILC_WIA_PATH . 'includes/class-admin.php';
	require_once SILC_WIA_PATH . 'includes/class-ajax.php';
	require_once SILC_WIA_PATH . 'includes/class-insight-renderer.php';
	require_once SILC_WIA_PATH . 'includes/class-insights.php';

	// Initialize.
	SILC_WIA_Admin::init();
	SILC_WIA_Ajax::init();
} );

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function () {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Store Insights with AI Lite requires PHP 7.4 or higher.', 'siphon-store-insight-lite' ) );
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Store Insights with AI Lite requires WooCommerce to be installed.', 'siphon-store-insight-lite' ) );
	}
} );

/**
 * Add settings link on the plugins page.
 */
add_filter( 'plugin_action_links_' . SILC_WIA_BASENAME, function ( $links ) {
	$dashboard_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=siphon-store-insight-lite' ) ),
		esc_html__( 'Dashboard', 'siphon-store-insight-lite' )
	);
	array_unshift( $links, $dashboard_link );

	return $links;
} );
