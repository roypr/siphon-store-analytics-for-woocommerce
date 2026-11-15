<?php
/**
 * Admin Page & Assets
 *
 * Registers the admin menu page and enqueues the React dashboard
 * along with WordPress dependencies (@wordpress/element, @wordpress/components, etc.).
 * Settings are managed inline within the dashboard — no separate settings page.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class SILC_WIA_Admin {

	/**
	 * Page hook suffix for the dashboard.
	 *
	 * @var string
	 */
	private static $page_hook = '';

	/**
	 * Set when sanitize_settings() dropped a stored key because the endpoint
	 * changed, so the AJAX handler can tell the user why the key is gone.
	 *
	 * @var bool
	 */
	private static $api_key_cleared = false;

	/**
	 * Option name for API settings.
	 */
	const SETTINGS_OPTION = 'silc_wia_api_settings';

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_analytics_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'wp_ajax_silc_wia_test_api', array( __CLASS__, 'handle_test_api' ) );
		add_action( 'wp_ajax_silc_wia_save_settings', array( __CLASS__, 'handle_save_settings' ) );
	}

	/**
	 * Register admin menu page under WooCommerce.
	 */
	public static function add_menu_pages(): void {
		self::$page_hook = add_submenu_page(
			'woocommerce',
			__( 'Store Insights for WooCommerce', 'store-insights-for-woocommerce' ),
			__( 'Store Insights', 'store-insights-for-woocommerce' ),
			'manage_woocommerce',
			'store-insights-for-woocommerce',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Warn on the dashboard when the Analytics data prerequisite is not met.
	 *
	 * Order-based Library items and order questions read the WooCommerce
	 * Analytics lookup tables, which are only kept current while the Analytics
	 * feature is enabled and the order import has run. This notice explains how
	 * to fix it instead of letting those features silently return nothing.
	 */
	public static function render_analytics_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || $screen->id !== self::$page_hook ) {
			return;
		}

		if ( ! class_exists( 'SILC_WIA_Woo_Schema' ) ) {
			return;
		}

		$status = SILC_WIA_Woo_Schema::get_analytics_status();

		if ( ! $status['tables_exist'] ) {
			$message = __( 'Store Insights for WooCommerce needs the WooCommerce Analytics lookup tables, but they are missing. Update or reinstall WooCommerce so its database tables are created.', 'store-insights-for-woocommerce' );
		} elseif ( ! $status['enabled'] ) {
			$features_url = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' );

			$message = sprintf(
				/* translators: %s: link to the WooCommerce features settings */
				__( 'WooCommerce Analytics is disabled, so order-based Library items are hidden and order questions may fail. Enable Analytics under %s.', 'store-insights-for-woocommerce' ),
				'<a href="' . esc_url( $features_url ) . '">' . esc_html__( 'WooCommerce > Settings > Advanced > Features', 'store-insights-for-woocommerce' ) . '</a>'
			);
		} elseif ( $status['needs_import'] ) {
			$import_url = admin_url( 'admin.php?page=wc-admin&path=%2Fanalytics%2Fsettings' );

			$message = sprintf(
				/* translators: %s: link to the WooCommerce Analytics settings */
				__( 'WooCommerce Analytics has not imported your orders yet, so order-based insights may be empty. Run the historical import under %s.', 'store-insights-for-woocommerce' ),
				'<a href="' . esc_url( $import_url ) . '">' . esc_html__( 'WooCommerce > Analytics > Settings', 'store-insights-for-woocommerce' ) . '</a>'
			);
		} else {
			return;
		}

		printf( '<div class="notice notice-warning"><p>%s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Register settings with the WordPress Settings API.
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_OPTION,
			self::SETTINGS_OPTION,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array(
					'api_url'     => SILC_WIA_API::DEFAULT_API_URL,
					'api_key'     => '',
					'model'       => SILC_WIA_API::DEFAULT_MODEL,
					'max_tokens'  => SILC_WIA_API::DEFAULT_MAX_TOKENS,
					'temperature' => SILC_WIA_API::DEFAULT_TEMPERATURE,
					'cache_ttl'   => SILC_WIA_API::DEFAULT_CACHE_TTL,
				),
			)
		);
	}

	/**
	 * Declare what data leaves the site for the WordPress privacy policy guide.
	 *
	 * The only outbound request this plugin makes is the configured AI
	 * provider call, so the suggested text describes exactly that — plus what
	 * is kept locally, since a generated answer can contain customer data.
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">'
			. esc_html__( 'Suggested text: When you ask a question, the following is sent over HTTPS to the AI provider you configured under WooCommerce &rarr; Store Insights &rarr; Settings:', 'store-insights-for-woocommerce' )
			. '</p><ul>'
			. '<li>' . esc_html__( 'Your question, in plain text.', 'store-insights-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Database schema metadata only: table names and column names.', 'store-insights-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Your API key, sent as an authorization header so the provider can bill your account.', 'store-insights-for-woocommerce' ) . '</li>'
			. '</ul><p class="privacy-policy-tutorial">'
			. esc_html__( 'No customer, order, product, or payment records are transmitted. Generated queries run against this site\'s own database.', 'store-insights-for-woocommerce' )
			. '</p><p class="privacy-policy-tutorial">'
			. esc_html__( 'Locally, the plugin keeps the question, the generated query and its display settings so past insights can be re-run; it does not keep the result rows. A generated answer is cached for the duration configured under Cache Duration so the same question does not call the AI twice. All of this data is removed when the plugin is deleted. Password hashes, activation keys and session tokens are never included in the schema sent to the provider and cannot be queried.', 'store-insights-for-woocommerce' )
			. '</p>';

		wp_add_privacy_policy_content( 'Store Insights for WooCommerce', wp_kses_post( $content ) );
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized input.
	 */
	public static function sanitize_settings( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$current = get_option( self::SETTINGS_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$output  = array();

		self::$api_key_cleared = false;

		$raw_url = trim( self::scalar_string( $input['api_url'] ?? '' ) );

		$output['api_url'] = '' !== $raw_url
			? esc_url_raw( untrailingslashit( $raw_url ) )
			: SILC_WIA_API::DEFAULT_API_URL;

		$previous_url = isset( $current['api_url'] ) ? untrailingslashit( self::scalar_string( $current['api_url'] ) ) : '';
		$url_changed  = '' !== $previous_url && $previous_url !== $output['api_url'];

		$raw_key = self::scalar_string( $input['api_key'] ?? '' );

		if ( '' !== $raw_key ) {
			$output['api_key'] = sanitize_text_field( $raw_key );

			// A pasted key usually identifies its provider ("sk-ant-…",
			// "sk-or-…", "gsk_…"). Only the plugin's built-in default endpoint
			// is ever replaced, so a deliberately selected provider or a custom
			// URL always wins over the guess.
			$output['api_url'] = untrailingslashit(
				SILC_WIA_Provider_Adapter::suggest_url_for_key( $output['api_key'], $output['api_url'] )
			);
		} elseif ( isset( $current['api_key'] ) && ! $url_changed ) {
			// An empty field means "keep the saved key" — but only while the
			// endpoint stays the same. A key is provider-specific, so keeping
			// it after a provider switch would only produce a 401 (or send a
			// credential to an endpoint it was not issued for).
			$output['api_key'] = self::scalar_string( $current['api_key'] );
		} else {
			$output['api_key'] = '';

			if ( $url_changed && ! empty( $current['api_key'] ) ) {
				self::$api_key_cleared = true;
			}
		}

		// An empty model field means "use the endpoint's default". Resolved
		// after the endpoint, because key-based routing can change it.
		$model = sanitize_text_field( trim( self::scalar_string( $input['model'] ?? '' ) ) );

		$output['model'] = '' !== $model
			? $model
			: SILC_WIA_API::get_default_model_for_url( $output['api_url'] );

		$output['max_tokens'] = isset( $input['max_tokens'] )
			? max( SILC_WIA_API::MIN_MAX_TOKENS, min( 8192, intval( self::scalar_string( $input['max_tokens'] ) ) ) )
			: SILC_WIA_API::DEFAULT_MAX_TOKENS;

		$output['temperature'] = isset( $input['temperature'] )
			? max( 0, min( 2, floatval( self::scalar_string( $input['temperature'] ) ) ) )
			: SILC_WIA_API::DEFAULT_TEMPERATURE;

		$output['cache_ttl'] = isset( $input['cache_ttl'] )
			? max( 0, min( 604800, intval( self::scalar_string( $input['cache_ttl'] ) ) ) )  // 0 to 7 days in seconds.
			: SILC_WIA_API::DEFAULT_CACHE_TTL;

		return $output;
	}

	/**
	 * Narrow an arbitrary request value to a string.
	 *
	 * Settings arrive as JSON from the dashboard, so a hand-written request can
	 * put an array or an object where a string is expected. String functions
	 * throw a TypeError on those under PHP 8, so every value is narrowed here
	 * before it reaches trim()/intval() or the settings option.
	 *
	 * @param mixed $value Raw value.
	 * @return string The scalar value as a string, or an empty string.
	 */
	private static function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Render the dashboard page.
	 */
	public static function render_page(): void {
		?>
		<div class="wrap silc-wia-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Store Insights for WooCommerce', 'store-insights-for-woocommerce' ); ?></h1>
			<div id="silc-wia-dashboard"></div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Test API connection.
	 */
	public static function handle_test_api(): void {
		if ( ! check_ajax_referer( SILC_WIA_Ajax::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		// Same capability as the menu page and every AJAX endpoint, so shop
		// managers who can open the dashboard can also use its controls.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		require_once SILC_WIA_PATH . 'includes/class-api.php';
		$result = SILC_WIA_API::test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX handler: Save settings from inline dashboard.
	 */
	public static function handle_save_settings(): void {
		if ( ! check_ajax_referer( SILC_WIA_Ajax::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		// Same capability as the menu page, so the Save button never fails for
		// a shop manager who was allowed to open the dashboard.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		// The dashboard posts the settings form as one JSON string, so a request
		// that sends an array (settings[]=...) never reaches json_decode().
		$raw_settings = '';

		if ( isset( $_POST['settings'] ) && is_string( $_POST['settings'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A JSON payload cannot be run through a text sanitizer without corrupting it; every key it carries is sanitized in sanitize_settings() below.
			$raw_settings = wp_unslash( $_POST['settings'] );
		}

		$input = '' !== $raw_settings ? json_decode( $raw_settings, true ) : null;

		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings data.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		// The API key and the prompt travel with every request, so plain HTTP
		// is refused unless the endpoint is local.
		$input_api_url = self::scalar_string( $input['api_url'] ?? '' );

		if ( '' !== $input_api_url && ! SILC_WIA_API::is_allowed_api_url( $input_api_url ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The API URL must use HTTPS. Plain HTTP is only allowed for local endpoints such as http://localhost:11434/v1.', 'store-insights-for-woocommerce' ),
				)
			);
			return;
		}

		$sanitized = self::sanitize_settings( $input );

		// The settings option holds the API key, so it is written without
		// autoload (see SILC_WIA_Storage).
		SILC_WIA_Storage::save( self::SETTINGS_OPTION, $sanitized );

		$message = __( 'Settings saved.', 'store-insights-for-woocommerce' );

		if ( self::$api_key_cleared ) {
			$message = __( 'Settings saved. The stored API key was removed because the API URL changed — enter a key for the new provider.', 'store-insights-for-woocommerce' );
		}

		wp_send_json_success( array(
			'message'         => $message,
			'api_url'         => $sanitized['api_url'],
			'model'           => $sanitized['model'],
			'has_api_key'     => ! empty( $sanitized['api_key'] ),
			'api_key_cleared' => self::$api_key_cleared,
			'is_reasoning'    => SILC_WIA_API::is_reasoning_model( $sanitized['model'] ?? '' ),
		) );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( $hook !== self::$page_hook ) {
			return;
		}

		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-hooks' );
		wp_enqueue_script( 'wp-html-entities' );

		wp_enqueue_style( 'wp-components' );

		$chartjs_file = SILC_WIA_PATH . 'assets/lib/chart.min.js';
		if ( file_exists( $chartjs_file ) ) {
			wp_enqueue_script(
				'silc-wia-chartjs',
				SILC_WIA_URL . 'assets/lib/chart.min.js',
				array(),
				'4.4.7',
				true
			);
		}

		wp_enqueue_script(
			'silc-wia-chart-renderer',
			SILC_WIA_URL . 'assets/js/chart-renderer.js',
			array( 'silc-wia-chartjs' ),
			SILC_WIA_VERSION,
			true
		);

		$asset_file = SILC_WIA_PATH . 'assets/js/dashboard.asset.php';
		$deps       = array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-hooks', 'wp-html-entities', 'silc-wia-chart-renderer' );
		$version    = SILC_WIA_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;
			if ( is_array( $asset ) ) {
				$deps    = array_merge( $deps, $asset['dependencies'] ?? array() );
				$version = $asset['version'] ?? $version;
			}
		}

		wp_enqueue_script(
			'silc-wia-dashboard',
			SILC_WIA_URL . 'assets/js/dashboard.js',
			$deps,
			$version,
			true
		);

		require_once SILC_WIA_PATH . 'includes/class-api.php';
		require_once SILC_WIA_PATH . 'includes/class-library.php';
		$api_settings    = SILC_WIA_API::get_settings();
		$has_api_key     = ! empty( $api_settings['api_key'] );
		$api_configured  = $has_api_key && ! empty( $api_settings['api_url'] ) && ! empty( $api_settings['model'] );
		$is_reason_model = SILC_WIA_API::is_reasoning_model( $api_settings['model'] ?? '' );
		$library_items   = SILC_WIA_Library::get_library_for_js();
		$api_url         = $api_settings['api_url'] ?? SILC_WIA_API::DEFAULT_API_URL;

		// Note: the API key itself is deliberately never passed to the browser.
		// Only booleans describing the saved configuration are exposed.
		wp_localize_script( 'silc-wia-dashboard', 'silcWiaData', array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( SILC_WIA_Ajax::NONCE_ACTION ),
			'siteUrl'          => site_url(),
			'pluginUrl'        => SILC_WIA_URL,
			'apiConfigured'    => $api_configured,
			'hasApiKey'        => $has_api_key,
			'isReasoningModel' => $is_reason_model,
			'pluginVersion'    => SILC_WIA_VERSION,
			'libraryItems'     => $library_items,
			// Endpoint, default model, and key hints live on the server
			// (SILC_WIA_Provider_Adapter) so the settings screen never carries
			// a second copy of the provider list.
			'providers'        => SILC_WIA_Provider_Adapter::all(),
			'settings'         => array(
				'api_url'     => $api_url,
				'model'       => $api_settings['model'] ?? SILC_WIA_API::DEFAULT_MODEL,
				'max_tokens'  => $api_settings['max_tokens'] ?? SILC_WIA_API::DEFAULT_MAX_TOKENS,
				'temperature' => $api_settings['temperature'] ?? SILC_WIA_API::DEFAULT_TEMPERATURE,
				'cache_ttl'   => $api_settings['cache_ttl'] ?? SILC_WIA_API::DEFAULT_CACHE_TTL,
			),
			'defaults' => array(
				'api_url'     => SILC_WIA_API::DEFAULT_API_URL,
				'model'       => SILC_WIA_API::DEFAULT_MODEL,
				'max_tokens'  => SILC_WIA_API::DEFAULT_MAX_TOKENS,
				'temperature' => SILC_WIA_API::DEFAULT_TEMPERATURE,
				'cache_ttl'   => SILC_WIA_API::DEFAULT_CACHE_TTL,
			),
			'l10n' => array(
				'askQuestion'        => __( 'Ask anything about your WooCommerce store...', 'store-insights-for-woocommerce' ),
				'getInsight'         => __( 'Get AI Insight', 'store-insights-for-woocommerce' ),
				'generatingInsight'  => __( 'Generating insight...', 'store-insights-for-woocommerce' ),
				'enterQuestion'      => __( 'Enter your question in plain English', 'store-insights-for-woocommerce' ),
				'results'            => __( 'Results', 'store-insights-for-woocommerce' ),
				'noResults'          => __( 'No results found.', 'store-insights-for-woocommerce' ),
				'errorOccurred'      => __( 'Something went wrong:', 'store-insights-for-woocommerce' ),
				'apiNotConfigured'   => __( 'API not configured', 'store-insights-for-woocommerce' ),
				'apiReady'           => __( 'AI Ready', 'store-insights-for-woocommerce' ),
				'tryAsking'          => __( 'Try asking:', 'store-insights-for-woocommerce' ),
				'clearHistory'       => __( 'Clear', 'store-insights-for-woocommerce' ),
				'noHistory'          => __( 'No past insights yet.', 'store-insights-for-woocommerce' ),
				'openInNewTab'       => __( 'Open in new tab', 'store-insights-for-woocommerce' ),
				'noInsightHistory'   => __( 'No insight history yet.', 'store-insights-for-woocommerce' ),
				'save'               => __( 'Save Settings', 'store-insights-for-woocommerce' ),
				'saving'             => __( 'Saving...', 'store-insights-for-woocommerce' ),
				'saved'              => __( 'Settings saved!', 'store-insights-for-woocommerce' ),
				'testConnection'     => __( 'Test Connection', 'store-insights-for-woocommerce' ),
				'testing'            => __( 'Testing...', 'store-insights-for-woocommerce' ),
				'connectionOk'       => __( 'Connection successful!', 'store-insights-for-woocommerce' ),
				'connectionFail'     => __( 'Connection failed.', 'store-insights-for-woocommerce' ),
				'settingsApiUrl'     => __( 'API URL', 'store-insights-for-woocommerce' ),
				'settingsApiKey'     => __( 'API Key', 'store-insights-for-woocommerce' ),
				'settingsModel'      => __( 'Model', 'store-insights-for-woocommerce' ),
				'settingsMaxTokens'  => __( 'Max Tokens', 'store-insights-for-woocommerce' ),
				'settingsTemp'       => __( 'Temperature', 'store-insights-for-woocommerce' ),
				'settingsCache'      => __( 'Cache Duration', 'store-insights-for-woocommerce' ),
				'sqlDetails'         => __( 'SQL & Details', 'store-insights-for-woocommerce' ),
				'history'            => __( 'History', 'store-insights-for-woocommerce' ),
				'library'            => __( 'Library', 'store-insights-for-woocommerce' ),
				'settings'           => __( 'Settings', 'store-insights-for-woocommerce' ),
				'refresh'            => __( 'Refresh', 'store-insights-for-woocommerce' ),
			),
		) );

		wp_enqueue_style(
			'silc-wia-admin',
			SILC_WIA_URL . 'assets/css/admin.css',
			array(),
			SILC_WIA_VERSION
		);
	}
}
