<?php

/**
 * Plugin Name: Store Insights for WooCommerce
 * Plugin URI:  https://github.com/roypr/store-insights-for-woocommerce
 * Description: Natural language to SQL query tool for WooCommerce. Uses your own AI provider key (OpenAI, Claude, OpenRouter, DeepSeek, Groq, Mistral, xAI, or any OpenAI-compatible endpoint) to convert plain English questions into SQL queries against WooCommerce data.
 * Version:     3.2.1
 * Author:      Roy Parthapratim
 * Author URI:  https://github.com/roypr
 * Text Domain: store-insights-for-woocommerce
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
define( 'SILC_WIA_VERSION', '3.2.1' );
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
			$message = __( 'Store Insights for WooCommerce requires WooCommerce to be installed and activated.', 'store-insights-for-woocommerce' );
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
		} );
		return;
	}

	// Load required files.
	require_once SILC_WIA_PATH . 'includes/class-woo-schema.php';
	require_once SILC_WIA_PATH . 'includes/class-api.php';
	require_once SILC_WIA_PATH . 'includes/class-storage.php';
	require_once SILC_WIA_PATH . 'includes/class-admin.php';
	require_once SILC_WIA_PATH . 'includes/class-ajax.php';
	require_once SILC_WIA_PATH . 'includes/class-insight-renderer.php';
	require_once SILC_WIA_PATH . 'includes/class-insights.php';

	// One-time storage layout upgrade (no-op after the first request).
	SILC_WIA_Storage::maybe_migrate();

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
		wp_die( esc_html__( 'Store Insights for WooCommerce requires PHP 7.4 or higher.', 'store-insights-for-woocommerce' ) );
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Store Insights for WooCommerce requires WooCommerce to be installed.', 'store-insights-for-woocommerce' ) );
	}
} );

/**
 * Add settings link on the plugins page.
 */
add_filter( 'plugin_action_links_' . SILC_WIA_BASENAME, function ( $links ) {
	$dashboard_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=store-insights-for-woocommerce' ) ),
		esc_html__( 'Dashboard', 'store-insights-for-woocommerce' )
	);
	array_unshift( $links, $dashboard_link );

	return $links;
} );
