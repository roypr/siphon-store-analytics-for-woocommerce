<?php
/**
 * Provider Adapter
 *
 * Central registry of the AI providers the plugin can talk to, and the small
 * set of behaviours that actually differ between them.
 *
 * The plugin speaks two wire formats (OpenAI-compatible /chat/completions and
 * Anthropic's native /messages) but the OpenAI-compatible family is *not*
 * uniform. The most important divergence is the completion-limit parameter:
 *
 * - OpenAI, OpenRouter and Groq deprecated `max_tokens` in favour of
 *   `max_completion_tokens` (which also covers hidden reasoning tokens).
 * - Anthropic, DeepSeek, Mistral and xAI still document `max_tokens`.
 *
 * Guessing that parameter from the model name alone breaks as soon as a
 * provider renames it, so the decision is made per provider here and callers
 * ask for it instead of hardcoding either name. When a provider still rejects
 * the choice, SILC_WIA_API::send_request() retries once with the other name
 * (see adapt_rejected_body()).
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider adapter class.
 */
class SILC_WIA_Provider_Adapter {

	/**
	 * Completion-limit parameter names used by the supported APIs.
	 */
	const TOKEN_PARAM_MAX_TOKENS            = 'max_tokens';
	const TOKEN_PARAM_MAX_COMPLETION_TOKENS = 'max_completion_tokens';

	/**
	 * Provider slugs.
	 */
	const PROVIDER_OPENAI     = 'openai';
	const PROVIDER_ANTHROPIC  = 'anthropic';
	const PROVIDER_OPENROUTER = 'openrouter';
	const PROVIDER_DEEPSEEK   = 'deepseek';
	const PROVIDER_GROQ       = 'groq';
	const PROVIDER_MISTRAL    = 'mistral';
	const PROVIDER_XAI        = 'xai';
	const PROVIDER_CUSTOM     = 'custom';

	/**
	 * Provider registry.
	 *
	 * Keys per provider:
	 * - label                  Display name (settings screen).
	 * - url                    Default API base URL ('' when unknown).
	 * - hosts                  Host suffixes matched against the URL host.
	 * - native                 Wire format: 'openai' or 'anthropic'.
	 * - token_param            Completion-limit parameter to send.
	 * - supports_temperature   Whether a temperature value may be sent.
	 * - default_model          Model used when the user has not picked one.
	 * - key_prefixes           Key prefixes used to route a pasted key.
	 * - key_placeholder        Settings-screen placeholder hint.
	 *
	 * Reasoning tokens are billed and counted against the completion limit on
	 * every provider below, which is why reasoning models get a larger budget
	 * in SILC_WIA_API::get_effective_max_tokens().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function registry(): array {
		return array(
			self::PROVIDER_OPENAI     => array(
				'label'                => 'OpenAI',
				'url'                  => 'https://api.openai.com/v1',
				'hosts'                => array( 'api.openai.com' ),
				'native'               => 'openai',
				// max_tokens is deprecated for the current model families.
				'token_param'          => self::TOKEN_PARAM_MAX_COMPLETION_TOKENS,
				'supports_temperature' => true,
				'default_model'        => 'gpt-6-luna',
				'key_prefixes'         => array( 'sk-proj-', 'sk-svcacct-', 'sk-' ),
				'key_placeholder'      => 'sk-...',
			),
			self::PROVIDER_ANTHROPIC  => array(
				'label'                => 'Claude (Anthropic)',
				'url'                  => 'https://api.anthropic.com/v1',
				'hosts'                => array( 'anthropic.com' ),
				'native'               => 'anthropic',
				// The Messages API requires max_tokens; there is no alias.
				'token_param'          => self::TOKEN_PARAM_MAX_TOKENS,
				// Temperature is rejected while thinking is enabled and
				// deprecated on newer models, so it is never forwarded.
				'supports_temperature' => false,
				'default_model'        => 'claude-haiku-5-5',
				'key_prefixes'         => array( 'sk-ant-' ),
				'key_placeholder'      => 'sk-ant-...',
			),
			self::PROVIDER_OPENROUTER => array(
				'label'                => 'OpenRouter',
				'url'                  => 'https://openrouter.ai/api/v1',
				'hosts'                => array( 'openrouter.ai' ),
				'native'               => 'openai',
				// Accepts both, but documents max_tokens as deprecated.
				'token_param'          => self::TOKEN_PARAM_MAX_COMPLETION_TOKENS,
				'supports_temperature' => true,
				// OpenRouter model ids are namespaced "vendor/model".
				'default_model'        => 'openai/gpt-6-luna',
				'key_prefixes'         => array( 'sk-or-' ),
				'key_placeholder'      => 'sk-or-v1-...',
			),
			self::PROVIDER_DEEPSEEK   => array(
				'label'                => 'DeepSeek',
				'url'                  => 'https://api.deepseek.com/v1',
				'hosts'                => array( 'deepseek.com' ),
				'native'               => 'openai',
				'token_param'          => self::TOKEN_PARAM_MAX_TOKENS,
				'supports_temperature' => true,
				'default_model'        => 'deepseek-flash',
				'key_prefixes'         => array( 'sk-' ),
				'key_placeholder'      => 'sk-...',
			),
			self::PROVIDER_GROQ       => array(
				'label'                => 'Groq',
				'url'                  => 'https://api.groq.com/openai/v1',
				'hosts'                => array( 'groq.com' ),
				'native'               => 'openai',
				// max_tokens is deprecated in Groq's documentation.
				'token_param'          => self::TOKEN_PARAM_MAX_COMPLETION_TOKENS,
				'supports_temperature' => true,
				'default_model'        => 'openai/gpt-oss-120b',
				'key_prefixes'         => array( 'gsk_' ),
				'key_placeholder'      => 'gsk_...',
			),
			self::PROVIDER_MISTRAL    => array(
				'label'                => 'Mistral',
				'url'                  => 'https://api.mistral.ai/v1',
				'hosts'                => array( 'mistral.ai' ),
				'native'               => 'openai',
				'token_param'          => self::TOKEN_PARAM_MAX_TOKENS,
				'supports_temperature' => true,
				// "-latest" aliases always resolve to the current model.
				'default_model'        => 'mistral-large-latest',
				'key_prefixes'         => array(),
				'key_placeholder'      => 'Mistral API key',
			),
			self::PROVIDER_XAI        => array(
				'label'                => 'xAI (Grok)',
				'url'                  => 'https://api.x.ai/v1',
				'hosts'                => array( 'x.ai' ),
				'native'               => 'openai',
				// Chat Completions uses max_tokens (only the newer Responses
				// API uses max_output_tokens).
				'token_param'          => self::TOKEN_PARAM_MAX_TOKENS,
				'supports_temperature' => true,
				'default_model'        => 'grok-4.5',
				'key_prefixes'         => array( 'xai-' ),
				'key_placeholder'      => 'xai-...',
			),
			self::PROVIDER_CUSTOM     => array(
				'label'                => 'Custom',
				'url'                  => '',
				// Any other OpenAI-compatible endpoint (Ollama, vLLM, LM
				// Studio, Azure, …). Those servers are usually older than the
				// hosted APIs, so the long-standing parameter name is the
				// safer default; send_request() retries with the new name if
				// the endpoint rejects it.
				'hosts'                => array(),
				'native'               => 'openai',
				'token_param'          => self::TOKEN_PARAM_MAX_TOKENS,
				'supports_temperature' => true,
				'default_model'        => '',
				'key_prefixes'         => array(),
				'key_placeholder'      => 'sk-...',
			),
		);
	}

	// ----------------------------------------------------------------------- //
	//  DETECTION
	// ----------------------------------------------------------------------- //

	/**
	 * Detect the provider slug from an API base URL.
	 *
	 * Matching is host-based so a URL that merely contains a provider name in
	 * its path (e.g. a proxy at /openai/v1) is not misrouted.
	 *
	 * @param string $api_url API base URL.
	 * @return string Provider slug, SILC_WIA_Provider_Adapter::PROVIDER_CUSTOM when unknown.
	 */
	public static function detect( string $api_url ): string {
		$host = self::host( $api_url );

		if ( '' === $host ) {
			return self::PROVIDER_CUSTOM;
		}

		foreach ( self::registry() as $slug => $spec ) {
			foreach ( (array) $spec['hosts'] as $needle ) {
				$needle = (string) $needle;

				if ( '' === $needle ) {
					continue;
				}

				// Suffix match on a label boundary: "api.x.ai" matches "x.ai"
				// but "box.airbnb.com" does not.
				if ( $host === $needle || substr( $host, -( strlen( $needle ) + 1 ) ) === '.' . $needle ) {
					return $slug;
				}
			}
		}

		return self::PROVIDER_CUSTOM;
	}

	/**
	 * Detect the provider a pasted API key belongs to.
	 *
	 * Only used to auto-select an endpoint when the user has not changed the
	 * default one; it never overrides a custom URL.
	 *
	 * @param string $api_key API key.
	 * @return string Provider slug, or '' when the shape is not recognised.
	 */
	public static function detect_from_key( string $api_key ): string {
		$api_key = trim( $api_key );

		if ( '' === $api_key ) {
			return '';
		}

		// Prefixes overlap ("sk-" is also the start of "sk-ant-" and "sk-or-"),
		// so the longest matching prefix wins rather than the first one.
		$best_provider = '';
		$best_length   = 0;

		foreach ( self::registry() as $slug => $spec ) {
			foreach ( (array) $spec['key_prefixes'] as $prefix ) {
				$length = strlen( (string) $prefix );

				if ( $length > $best_length && 0 === strpos( $api_key, (string) $prefix ) ) {
					$best_provider = $slug;
					$best_length   = $length;
				}
			}
		}

		return $best_provider;
	}

	/**
	 * Suggest the endpoint to use for a pasted API key.
	 *
	 * Auto-routing only applies while the endpoint is unset or is still the
	 * plugin's built-in default (OpenAI). Once the user has selected a provider
	 * or typed a custom endpoint, that choice is authoritative: key prefixes
	 * overlap ("sk-…" is used by OpenAI and DeepSeek alike), so guessing from
	 * the key would otherwise silently move a deliberately selected provider's
	 * request back to OpenAI.
	 *
	 * @param string $api_key     API key.
	 * @param string $current_url Currently configured API base URL.
	 * @return string URL to use.
	 */
	public static function suggest_url_for_key( string $api_key, string $current_url ): string {
		$provider = self::detect_from_key( $api_key );

		if ( '' === $provider ) {
			return $current_url;
		}

		$suggested = self::get_default_url( $provider );

		if ( '' === $suggested ) {
			return $current_url;
		}

		$current_url = untrailingslashit( $current_url );

		if ( $current_url === $suggested ) {
			return $current_url;
		}

		// Only the built-in default may be replaced. Any other endpoint is an
		// explicit choice (a selected provider or a custom URL) and wins over
		// the guessed key shape.
		$builtin_default = self::get_default_url( self::PROVIDER_OPENAI );

		if ( '' !== $current_url && $builtin_default !== $current_url ) {
			return $current_url;
		}

		return $suggested;
	}

	// ----------------------------------------------------------------------- //
	//  SPEC LOOKUPS
	// ----------------------------------------------------------------------- //

	/**
	 * Get the spec for a provider slug.
	 *
	 * @param string $provider Provider slug.
	 * @return array<string, mixed>
	 */
	public static function get_spec( string $provider ): array {
		$registry = self::registry();

		return isset( $registry[ $provider ] ) ? $registry[ $provider ] : $registry[ self::PROVIDER_CUSTOM ];
	}

	/**
	 * Get the spec for the provider behind an API URL.
	 *
	 * @param string $api_url API base URL.
	 * @return array<string, mixed>
	 */
	public static function get( string $api_url ): array {
		return self::get_spec( self::detect( $api_url ) );
	}

	/**
	 * Get a provider's default API base URL.
	 *
	 * @param string $provider Provider slug.
	 * @return string URL, or '' when the provider has none.
	 */
	public static function get_default_url( string $provider ): string {
		$spec = self::get_spec( $provider );

		return untrailingslashit( (string) $spec['url'] );
	}

	/**
	 * Get the default model for the provider behind an API URL.
	 *
	 * @param string $api_url API base URL.
	 * @return string Model id, or '' when unknown.
	 */
	public static function get_default_model( string $api_url ): string {
		$spec = self::get( $api_url );

		return (string) $spec['default_model'];
	}

	/**
	 * Whether the provider uses Anthropic's native Messages API.
	 *
	 * @param string $api_url API base URL.
	 * @return bool
	 */
	public static function is_anthropic( string $api_url ): bool {
		$spec = self::get( $api_url );

		return 'anthropic' === $spec['native'];
	}

	/**
	 * Whether a model id plausibly belongs to the provider behind an API URL.
	 *
	 * A stored model survives a provider switch unless something corrects it,
	 * which leaves requests like "gpt-6-luna" pointed at Anthropic. Only model
	 * families that are unambiguous are checked; an unknown id is left alone so
	 * custom model names are never second-guessed. Custom endpoints and
	 * OpenRouter accept models from many families, so they always match.
	 *
	 * @param string $model   Model id.
	 * @param string $api_url API base URL.
	 * @return bool
	 */
	public static function model_matches_provider( string $model, string $api_url ): bool {
		if ( '' === $model ) {
			return true;
		}

		$provider = self::detect( $api_url );

		if ( self::PROVIDER_CUSTOM === $provider || self::PROVIDER_OPENROUTER === $provider ) {
			return true;
		}

		$family = self::model_family( $model );

		return '' === $family || $family === $provider;
	}

	/**
	 * Identify the provider family a model id clearly belongs to.
	 *
	 * @param string $model Model id.
	 * @return string Provider slug, or '' when the family is not recognised.
	 */
	private static function model_family( string $model ): string {
		$model = strtolower( trim( $model ) );

		if ( preg_match( '/^(claude-|fable-|mythos-)/', $model ) ) {
			return self::PROVIDER_ANTHROPIC;
		}

		if ( preg_match( '/^(gpt-|o[1-9](-|$)|chatgpt-|ft:)/', $model ) ) {
			return self::PROVIDER_OPENAI;
		}

		if ( 0 === strpos( $model, 'deepseek' ) ) {
			return self::PROVIDER_DEEPSEEK;
		}

		if ( 0 === strpos( $model, 'grok-' ) ) {
			return self::PROVIDER_XAI;
		}

		if ( preg_match( '/^(mistral-|codestral-|pixtral-|ministral-|open-mistral-|open-mixtral-)/', $model ) ) {
			return self::PROVIDER_MISTRAL;
		}

		// Groq hosts open-weight models with many unrelated names; do not guess.
		return '';
	}

	// ----------------------------------------------------------------------- //
	//  REQUEST PARAMETERS
	// ----------------------------------------------------------------------- //

	/**
	 * The completion-limit parameter name to send for this endpoint.
	 *
	 * @param string $api_url API base URL.
	 * @param string $model   Optional model id (reserved for future model-level
	 *                        overrides; the choice is currently per provider).
	 * @return string Parameter name.
	 */
	public static function get_token_param( string $api_url, string $model = '' ): string {
		$spec = self::get( $api_url );

		/**
		 * Filter the completion-limit parameter for an endpoint.
		 *
		 * Lets a site override the choice for a gateway whose behaviour does
		 * not match its upstream provider's documentation.
		 *
		 * @param string $token_param Parameter name (max_tokens|max_completion_tokens).
		 * @param string $api_url     API base URL.
		 * @param string $model       Model id.
		 */
		$token_param = (string) apply_filters( 'silc_wia_completion_token_param', (string) $spec['token_param'], $api_url, $model );

		if ( self::TOKEN_PARAM_MAX_COMPLETION_TOKENS !== $token_param ) {
			return self::TOKEN_PARAM_MAX_TOKENS;
		}

		return self::TOKEN_PARAM_MAX_COMPLETION_TOKENS;
	}

	/**
	 * The other completion-limit parameter name.
	 *
	 * @param string $token_param Parameter name.
	 * @return string The alternate name.
	 */
	public static function alternate_token_param( string $token_param ): string {
		return ( self::TOKEN_PARAM_MAX_COMPLETION_TOKENS === $token_param )
			? self::TOKEN_PARAM_MAX_TOKENS
			: self::TOKEN_PARAM_MAX_COMPLETION_TOKENS;
	}

	/**
	 * Whether a `temperature` value may be sent to this endpoint/model.
	 *
	 * Reasoning models across all providers only accept the default value, so
	 * sending one turns the whole request into a 400.
	 *
	 * @param string $api_url API base URL.
	 * @param string $model   Model id.
	 * @return bool
	 */
	public static function supports_temperature( string $api_url, string $model = '' ): bool {
		$spec = self::get( $api_url );

		if ( empty( $spec['supports_temperature'] ) ) {
			return false;
		}

		if ( '' !== $model && SILC_WIA_API::is_reasoning_model( $model ) ) {
			return false;
		}

		return true;
	}

	// ----------------------------------------------------------------------- //
	//  ERROR RECOVERY
	// ----------------------------------------------------------------------- //

	/**
	 * Repair a request body that a provider rejected over a parameter name.
	 *
	 * Covers the two drifts that break integrations in practice:
	 * - the endpoint wants the other completion-limit parameter name;
	 * - the endpoint rejects a non-default `temperature` (reasoning models).
	 *
	 * Only "this parameter is not accepted" style errors are rewritten: a value
	 * error (e.g. "max_tokens must be <= 8192") is a real problem that a retry
	 * with another parameter name cannot fix.
	 *
	 * @param array  $body  Rejected OpenAI-shaped request body.
	 * @param string $error Error message returned by the API.
	 * @return array|null Corrected body, or null when nothing can be fixed.
	 */
	public static function adapt_rejected_body( array $body, string $error ): ?array {
		if ( ! self::is_unsupported_param_error( $error ) ) {
			return null;
		}

		$changed = false;

		$blamed = self::blamed_token_param( $error );

		if ( null !== $blamed && array_key_exists( $blamed, $body ) ) {
			$alternate = self::alternate_token_param( $blamed );

			$body[ $alternate ] = $body[ $blamed ];
			unset( $body[ $blamed ] );

			$changed = true;
		}

		if ( isset( $body['temperature'] ) && false !== stripos( $error, 'temperature' ) ) {
			unset( $body['temperature'] );
			$changed = true;
		}

		return $changed ? $body : null;
	}

	/**
	 * Whether an error message says a parameter is not accepted at all.
	 *
	 * @param string $error Error message.
	 * @return bool
	 */
	private static function is_unsupported_param_error( string $error ): bool {
		$signals = array(
			'unsupported',
			'not supported',
			'unknown parameter',
			'unrecognized',
			'unrecognised',
			'unexpected',
			'extra fields',
			'extra inputs',
			'not allowed',
			'deprecated',
			'no longer supported',
		);

		foreach ( $signals as $signal ) {
			if ( false !== stripos( $error, $signal ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Work out which completion-limit parameter an error message blames.
	 *
	 * The first mention wins: providers phrase these errors as
	 * "Unsupported parameter: 'max_tokens' … use 'max_completion_tokens'".
	 *
	 * @param string $error Error message.
	 * @return string|null Blamed parameter name, or null.
	 */
	private static function blamed_token_param( string $error ): ?string {
		$pos_completion = stripos( $error, self::TOKEN_PARAM_MAX_COMPLETION_TOKENS );
		$pos_legacy     = stripos( $error, self::TOKEN_PARAM_MAX_TOKENS );

		if ( false !== $pos_completion && ( false === $pos_legacy || $pos_completion < $pos_legacy ) ) {
			return self::TOKEN_PARAM_MAX_COMPLETION_TOKENS;
		}

		if ( false !== $pos_legacy ) {
			return self::TOKEN_PARAM_MAX_TOKENS;
		}

		return null;
	}

	// ----------------------------------------------------------------------- //
	//  PRESENTATION
	// ----------------------------------------------------------------------- //

	/**
	 * Provider metadata for the settings screen.
	 *
	 * Exposed through silcWiaData so the UI does not carry a second copy of
	 * the default model ids.
	 *
	 * @return array<string, array{label: string, url: string, model: string, keyPlaceholder: string}>
	 */
	public static function all(): array {
		$out = array();

		foreach ( self::registry() as $slug => $spec ) {
			$out[ $slug ] = array(
				'label'          => (string) $spec['label'],
				'url'            => (string) $spec['url'],
				'model'          => (string) $spec['default_model'],
				'keyPlaceholder' => (string) $spec['key_placeholder'],
			);
		}

		return $out;
	}

	// ----------------------------------------------------------------------- //
	//  HELPERS
	// ----------------------------------------------------------------------- //

	/**
	 * Extract the host from a URL without relying on WordPress helpers.
	 *
	 * @param string $api_url API base URL.
	 * @return string Lowercased host, or '' when the URL has none.
	 */
	private static function host( string $api_url ): string {
		$api_url = trim( $api_url );

		if ( '' === $api_url ) {
			return '';
		}

		$host = wp_parse_url( $api_url, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}
}
