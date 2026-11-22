<?php
/**
 * AI backend router
 *
 * One question, two ways to answer it:
 *
 * - `core` — the WordPress AI Client (WP 7.0+). WordPress routes the request to
 *   whichever provider the site owner connected under Settings → Connectors,
 *   so the plugin stores no API key. This is the default whenever it is
 *   available, because it is the path the plugin directory review expects and
 *   it keeps credentials in one reviewed place.
 * - `byok` — the plugin's own client (SILC_WIA_API) with a key saved in the
 *   plugin settings. Kept, not deprecated: it is the only option on WordPress
 *   6.5–6.x, for self-hosted/local endpoints (Ollama, LocalAI, vLLM, LM
 *   Studio), and when `wp_supports_ai()` is false.
 *
 * Both backends build the same prompts and return the same
 * array{success, insight, error} shape, so nothing downstream (SQL validation,
 * execution, rendering, history) knows which one ran.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SILC_WIA_PATH . 'includes/class-api.php';
require_once SILC_WIA_PATH . 'includes/class-ai-service-core.php';

/**
 * Backend router.
 */
class SILC_WIA_AI_Service {

	/**
	 * Option holding the selected backend.
	 *
	 * Empty/unset means "automatic": the WordPress AI Client when available,
	 * otherwise the plugin's own client.
	 */
	const BACKEND_OPTION = 'silc_wia_ai_backend';

	/**
	 * Backend ids.
	 */
	const BACKEND_CORE = 'core';
	const BACKEND_BYOK = 'byok';

	/**
	 * Resolved backend for this request (memoised).
	 *
	 * @var string|null
	 */
	private static $backend = null;

	/**
	 * Cached "a provider is connected in WordPress" answer.
	 *
	 * @var bool|null
	 */
	private static $core_supported = null;

	/**
	 * The backend that will actually be used.
	 *
	 * A saved `core` selection degrades to `byok` when the AI Client is not
	 * available here (older WordPress, AI disabled with `WP_AI_SUPPORT`, or a
	 * filter), so insight generation keeps working instead of failing.
	 *
	 * @return string Either BACKEND_CORE or BACKEND_BYOK.
	 */
	public static function get_backend(): string {
		if ( null === self::$backend ) {
			self::$backend = self::resolve_backend( self::get_stored_backend() );
		}

		return self::$backend;
	}

	/**
	 * Work out which backend a stored choice runs as.
	 *
	 * An explicit BYOK choice always wins; "automatic" and "core" both use the
	 * AI Client when the site can serve it. Exposed so settings sanitisation can
	 * decide what "no model" means for the selected backend.
	 *
	 * @param string $stored Raw stored value ('' | BACKEND_CORE | BACKEND_BYOK).
	 * @return string Either BACKEND_CORE or BACKEND_BYOK.
	 */
	public static function resolve_backend( string $stored ): string {
		if ( self::BACKEND_BYOK === self::sanitize_backend( $stored ) ) {
			return self::BACKEND_BYOK;
		}

		return self::is_core_available() ? self::BACKEND_CORE : self::BACKEND_BYOK;
	}

	/**
	 * Whether the WordPress AI Client can be used at all on this site.
	 *
	 * @return bool
	 */
	public static function is_core_available(): bool {
		return SILC_WIA_AI_Service_Core::is_available();
	}

	/**
	 * The backend as saved in the option.
	 *
	 * Empty means "automatic". The settings screen needs the raw value so it
	 * can show what the administrator picked rather than what the site
	 * currently resolves to — saving an unrelated field must not turn an
	 * automatic choice into an explicit one.
	 *
	 * @return string '' | BACKEND_CORE | BACKEND_BYOK
	 */
	public static function get_stored_backend(): string {
		return self::sanitize_backend( get_option( self::BACKEND_OPTION, '' ) );
	}

	/**
	 * Whether a provider is connected for text generation.
	 *
	 * False while the AI Client exists but Settings → Connectors has no key,
	 * which the settings screen explains separately from "not available".
	 *
	 * @return bool
	 */
	public static function is_core_supported(): bool {
		if ( null === self::$core_supported ) {
			self::$core_supported = SILC_WIA_AI_Service_Core::is_supported();
		}

		return self::$core_supported;
	}

	/**
	 * Narrow an arbitrary value to a valid backend id.
	 *
	 * @param mixed $value Raw value.
	 * @return string Backend id, or an empty string for "automatic".
	 */
	public static function sanitize_backend( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return in_array( $value, array( self::BACKEND_CORE, self::BACKEND_BYOK ), true ) ? $value : '';
	}

	/**
	 * Generate a structured insight from a natural language question.
	 *
	 * @param string $question       The user's natural language question.
	 * @param string $schema_context The database schema context for the prompt.
	 * @return array{success: bool, insight: array|null, error: string}
	 */
	public static function generate_insight( string $question, string $schema_context ): array {
		if ( self::BACKEND_CORE === self::get_backend() && self::is_core_available() ) {
			return SILC_WIA_AI_Service_Core::generate_insight( $question, $schema_context );
		}

		return SILC_WIA_API::generate_insight( $question, $schema_context );
	}

	/**
	 * Probe the active backend.
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function test_connection(): array {
		if ( self::BACKEND_CORE === self::get_backend() && self::is_core_available() ) {
			return SILC_WIA_AI_Service_Core::test_connection();
		}

		return SILC_WIA_API::test_connection();
	}

	/**
	 * Signature of the active AI configuration, for the insight cache key.
	 *
	 * Without it, switching backend would serve answers generated by the
	 * previous one. The model preference and the sampling settings are part of
	 * the key because they change the answer: changing any of them must not
	 * keep serving the cached result of the old configuration.
	 *
	 * @return string
	 */
	public static function get_cache_signature(): string {
		$settings = SILC_WIA_API::get_settings();
		$sampling = SILC_WIA_API::get_temperature_mode( $settings )
			. '|' . (string) ( $settings['temperature'] ?? '' )
			. '|' . SILC_WIA_API::get_reasoning_effort( $settings );

		if ( self::BACKEND_CORE === self::get_backend() && self::is_core_available() ) {
			// Which model serves the request depends on the connectors, so the
			// preference is included but not the BYOK endpoint/key.
			return self::BACKEND_CORE . '|' . (string) $settings['model'] . '|' . $sampling;
		}

		return self::BACKEND_BYOK . '|' . (string) $settings['api_url'] . '|' . (string) $settings['model'] . '|' . $sampling;
	}
}
