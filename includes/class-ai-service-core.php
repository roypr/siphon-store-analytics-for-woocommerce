<?php
/**
 * WordPress AI Client backend
 *
 * Generates the insight JSON through the AI Client that ships with WordPress
 * 7.0+ (`wp_ai_client_prompt()`). This is the default backend: the site owner
 * connects a provider once under Settings → Connectors, WordPress routes the
 * request, and the plugin never stores or sends an API key of its own.
 *
 * The BYOK path in SILC_WIA_API is kept as the fallback (and remains the only
 * path on older WordPress versions, for self-hosted endpoints, and when
 * `wp_supports_ai()` is false). Both backends build their prompts with the
 * same functions in SILC_WIA_API and return the same
 * array{success, insight, error} shape, so the caller cannot tell them apart.
 *
 * The Model, Temperature and Reasoning effort settings are applied here too,
 * with one difference: WordPress picks the model, so the model id is only a
 * preference, and a temperature is never sent unless the site owner explicitly
 * asks for it. Requesting either as a hard requirement would remove models from
 * consideration before the request is made — `using_temperature()`, for one,
 * excludes every model that does not advertise support for it, which is all
 * current OpenAI reasoning models.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SILC_WIA_PATH . 'includes/class-api.php';

/**
 * AI Client (core) insight generator.
 */
class SILC_WIA_AI_Service_Core {

	/**
	 * Lowest token budget this backend will ask for.
	 *
	 * A truncated answer cannot be parsed as JSON, so the setting is raised to
	 * this floor before it reaches WP_AI_Client_Prompt_Builder::using_max_tokens().
	 */
	const MIN_MAX_TOKENS = 2000;

	/**
	 * Model id reported by the most recent successful request.
	 *
	 * The AI Client chooses the provider (and therefore the model), so the only
	 * way to know which one answered is to read it back from the result. This
	 * is populated by generate_text() and read by test_connection().
	 *
	 * @var string
	 */
	private static $last_model_id = '';

	/**
	 * Description of the settings the most recent request had to drop.
	 *
	 * @var string
	 */
	private static $last_fallback_note = '';

	/**
	 * The model id that served the most recent successful request.
	 *
	 * @return string Model id, or '' when unknown.
	 */
	public static function last_model_id(): string {
		return self::$last_model_id;
	}

	/**
	 * What the most recent request had to leave out to succeed, if anything.
	 *
	 * @return string Human readable note, or ''.
	 */
	public static function last_fallback_note(): string {
		return self::$last_fallback_note;
	}

	/**
	 * Whether the AI Client is usable in this request.
	 *
	 * Three separate conditions, because any of them can be false on a site
	 * that otherwise looks ready:
	 * - WordPress older than 7.0 has no AI Client.
	 * - `wp_supports_ai()` is false when the site owner disabled AI
	 *   (`WP_AI_SUPPORT` / the `wp_supports_ai` filter) or the environment
	 *   cannot be trusted with it.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_supports_ai' ) ) {
			return false;
		}

		return (bool) wp_supports_ai();
	}

	/**
	 * Whether a provider is actually connected for text generation.
	 *
	 * is_available() only proves the API exists; this asks the builder whether
	 * the registry can serve a text request. False means the site has no AI
	 * connector configured (Settings → Connectors), which is a different
	 * problem with a different fix.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		return (bool) wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation();
	}

	/**
	 * Generate a structured insight from a natural language question.
	 *
	 * Same contract as SILC_WIA_API::generate_insight():
	 * array{success: bool, insight: array|null, error: string}.
	 *
	 * @param string $question       The user's natural language question.
	 * @param string $schema_context The database schema context for the prompt.
	 * @return array{success: bool, insight: array|null, error: string}
	 */
	public static function generate_insight( string $question, string $schema_context ): array {
		if ( ! self::is_available() ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => __( 'The WordPress AI Client is not available on this site. Switch the AI backend to your own API key in Settings.', 'siphon-store-analytics-for-woocommerce' ),
			);
		}

		$system_prompt = SILC_WIA_API::build_insight_system_prompt( $schema_context );
		$user_prompt   = SILC_WIA_API::build_insight_user_prompt( $question );
		$max_tokens    = max( self::MIN_MAX_TOKENS, SILC_WIA_API::get_insight_max_tokens() );

		$content = self::request_text( $system_prompt, $user_prompt, $max_tokens );

		if ( is_wp_error( $content ) ) {
			return array(
				'success' => false,
				'insight' => null,
				/* translators: %s: error message returned by the WordPress AI Client */
				'error'   => sprintf( __( 'WordPress AI Client: %s', 'siphon-store-analytics-for-woocommerce' ), self::describe_error( $content ) ),
			);
		}

		if ( '' === $content ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => __( 'The WordPress AI Client returned an empty response.', 'siphon-store-analytics-for-woocommerce' ),
			);
		}

		$insight = SILC_WIA_API::parse_insight_json( $content );

		if ( null === $insight ) {
			// One correction attempt, mirroring the BYOK retry: show the model
			// its own output and ask for JSON only. It is a single fresh prompt
			// rather than a replayed conversation because the AI Client picks
			// the provider (and therefore the message format) for us, so there
			// is no assistant turn this plugin can safely construct.
			$retry_prompt = $user_prompt
				. "\n\n---\n"
				. "Your previous response was not valid JSON:\n"
				. $content
				. "\n\nReturn ONLY valid JSON with no markdown, no explanation, just the JSON object.";

			$retry_content = self::request_text( $system_prompt, $retry_prompt, $max_tokens );

			if ( is_string( $retry_content ) && '' !== $retry_content ) {
				$insight = SILC_WIA_API::parse_insight_json( $retry_content );
			}
		}

		if ( null === $insight ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => __( 'Could not parse insight JSON from the AI response.', 'siphon-store-analytics-for-woocommerce' ),
			);
		}

		return array(
			'success' => true,
			'insight' => $insight,
			'error'   => '',
		);
	}

	/**
	 * Probe the AI Client with a tiny request.
	 *
	 * Separate from generate_insight() on purpose: a connection check must not
	 * spend a full insight budget just to prove a provider is reachable. The
	 * model, temperature and reasoning settings are applied, so the answer
	 * names the model that actually served the request (which is how a site
	 * owner can tell whether their Model preference was honoured).
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function test_connection(): array {
		if ( ! self::is_available() ) {
			return array(
				'success' => false,
				'message' => __( 'The WordPress AI Client is not available on this site.', 'siphon-store-analytics-for-woocommerce' ),
			);
		}

		if ( ! self::is_supported() ) {
			return array(
				'success' => false,
				'message' => __( 'No AI provider is connected. Add one under Settings → Connectors.', 'siphon-store-analytics-for-woocommerce' ),
			);
		}

		// The retry ladder is enabled so a green result means an insight will be
		// produced, not just that the connector answers. When a setting had to be
		// dropped, last_fallback_note() says which one, which is the actionable
		// part of a "why is my temperature ignored" question.
		$result = self::request_text( '', 'Say "ok" and nothing else.', 20 );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				/* translators: %s: error message returned by the WordPress AI Client */
				'message' => sprintf( __( 'WordPress AI Client: %s', 'siphon-store-analytics-for-woocommerce' ), self::describe_error( $result ) ),
			);
		}

		$model = self::last_model_id();
		$note  = self::last_fallback_note();

		if ( '' === $model ) {
			$message = __( 'Connection successful! A provider is connected through the WordPress AI Client.', 'siphon-store-analytics-for-woocommerce' );
		} else {
			$message = sprintf(
				/* translators: %s: model id chosen by the WordPress AI Client */
				__( 'Connection successful! The WordPress AI Client used the model %s.', 'siphon-store-analytics-for-woocommerce' ),
				$model
			);
		}

		return array(
			'success' => true,
			'message' => '' !== $note ? $message . ' ' . $note : $message,
		);
	}

	/**
	 * Run one text prompt, retrying once without the optional parameters.
	 *
	 * The AI Client refuses models whose metadata does not advertise a parameter
	 * that was requested (a temperature value removes every reasoning model from
	 * consideration) and providers reject values they do not offer. Rather than
	 * mapping those capabilities here, the settings are applied as configured and
	 * the request is retried with progressively fewer optional parameters so a
	 * stale or over-eager setting degrades instead of breaking the insight.
	 *
	 * @param string $system_prompt System instruction.
	 * @param string $user_prompt   User prompt.
	 * @param int    $max_tokens    Output token budget.
	 * @param bool   $allow_retry   Whether a simpler configuration may be tried.
	 * @return string|WP_Error Generated text, or the builder's error.
	 */
	private static function request_text( string $system_prompt, string $user_prompt, int $max_tokens, bool $allow_retry = true ) {
		$settings = SILC_WIA_API::get_settings();
		$effort   = SILC_WIA_API::get_reasoning_effort( $settings );
		$temp     = null;

		self::$last_fallback_note = '';

		// 'auto' never sends a temperature on this backend: the AI Client picks
		// the model, and a requested value excludes every model that does not
		// advertise support for it — including all current reasoning models.
		// 'always' is the explicit opt-in for a model known to accept it.
		if ( SILC_WIA_API::TEMPERATURE_MODE_ALWAYS === SILC_WIA_API::get_temperature_mode( $settings ) ) {
			$temp = (float) ( $settings['temperature'] ?? SILC_WIA_API::DEFAULT_TEMPERATURE );
		}

		$attempts = array( array( $temp, $effort ) );

		if ( $allow_retry ) {
			if ( null !== $temp ) {
				$attempts[] = array( null, $effort );
			}

			if ( null !== $temp || '' !== $effort ) {
				$attempts[] = array( null, '' );
			}
		}

		$result = '';
		$last   = count( $attempts ) - 1;

		foreach ( $attempts as $index => $attempt ) {
			$result = self::generate_text( $system_prompt, $user_prompt, $max_tokens, $attempt[0], $attempt[1] );

			if ( ! is_wp_error( $result ) ) {
				if ( $index > 0 ) {
					self::$last_fallback_note = self::describe_fallback( $attempts[0], $attempt );
				}

				return $result;
			}

			if ( $index === $last || ! self::is_configuration_error( $result ) ) {
				return $result;
			}
		}

		return $result;
	}

	/**
	 * Name the settings a retry had to drop, for the connection test message.
	 *
	 * @param array $requested Configuration of the first attempt.
	 * @param array $used      Configuration that succeeded.
	 * @return string Human readable note, or '' when nothing was dropped.
	 */
	private static function describe_fallback( array $requested, array $used ): string {
		$dropped = array();

		if ( null !== $requested[0] && null === $used[0] ) {
			$dropped[] = __( 'temperature', 'siphon-store-analytics-for-woocommerce' );
		}

		if ( '' !== $requested[1] && '' === $used[1] ) {
			$dropped[] = __( 'reasoning effort', 'siphon-store-analytics-for-woocommerce' );
		}

		if ( empty( $dropped ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: comma separated list of settings, e.g. "temperature, reasoning effort" */
			__( 'Your %s setting was not accepted by this model, so it was left out — insights still work.', 'siphon-store-analytics-for-woocommerce' ),
			implode( ', ', $dropped )
		);
	}

	/**
	 * Run one text prompt with an explicit configuration.
	 *
	 * @param string     $system_prompt System instruction.
	 * @param string     $user_prompt   User prompt.
	 * @param int        $max_tokens    Output token budget.
	 * @param float|null $temperature   Temperature to send, or null to omit the parameter.
	 * @param string     $effort        Reasoning effort to request, or '' to omit it.
	 * @return string|WP_Error Generated text, or the builder's error.
	 */
	private static function generate_text( string $system_prompt, string $user_prompt, int $max_tokens, ?float $temperature, string $effort ) {
		$builder = wp_ai_client_prompt( $user_prompt );

		if ( '' !== $system_prompt ) {
			$builder = $builder->using_system_instruction( $system_prompt );
		}

		// A model *preference*, not a requirement: WordPress falls back to the
		// first model the connected providers offer when the id is not
		// available there, so a model id from another provider cannot break
		// the request. Read as stored (see get_model_preference()), because the
		// BYOK normalisation in get_settings() would discard an id that belongs
		// to a different connector.
		$model = SILC_WIA_API::get_model_preference();

		if ( '' !== $model ) {
			$builder = $builder->using_model_preference( $model );
		}

		// using_max_tokens() is part of the 7.0 builder. The setting only caps
		// the generated answer; a provider's own ceiling still wins, so the
		// floor here cannot force an oversized request.
		$builder = $builder->using_max_tokens( $max_tokens );

		if ( null !== $temperature ) {
			$builder = $builder->using_temperature( $temperature );
		}

		$reasoning = self::reasoning_config( $effort );

		if ( null !== $reasoning ) {
			$builder = $builder->using_model_config( $reasoning );
		}

		$result = $builder->generate_text_result();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::$last_model_id = self::read_model_id( $result );

		$text = is_object( $result ) && method_exists( $result, 'toText' ) ? (string) $result->toText() : '';

		return trim( $text );
	}

	/**
	 * Build the model configuration that requests a reasoning effort.
	 *
	 * The AI Client has no dedicated reasoning method, so the level travels as
	 * the provider-specific `reasoning` custom option — the shape OpenAI's
	 * Responses API expects (`reasoning: { effort: "low" }`). Providers that do
	 * not know the option reject the request, which request_text() retries
	 * without.
	 *
	 * @param string $effort Reasoning effort, or '' for none.
	 * @return object|null Model configuration, or null when not available.
	 */
	private static function reasoning_config( string $effort ) {
		if ( '' === $effort || ! class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelConfig' ) ) {
			return null;
		}

		$config = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		$config->setCustomOption( 'reasoning', array( 'effort' => $effort ) );

		return $config;
	}

	/**
	 * Read the model id out of a generative result.
	 *
	 * @param mixed $result Result returned by the AI Client.
	 * @return string Model id, or '' when the result does not expose one.
	 */
	private static function read_model_id( $result ): string {
		if ( ! is_object( $result ) || ! method_exists( $result, 'getModelMetadata' ) ) {
			return '';
		}

		$metadata = $result->getModelMetadata();

		return is_object( $metadata ) && method_exists( $metadata, 'getId' ) ? (string) $metadata->getId() : '';
	}

	/**
	 * Whether an error was caused by the requested parameters rather than by
	 * the provider, the network or the credentials.
	 *
	 * Retrying without the optional parameters only helps when the request was
	 * rejected because of them: WordPress reports a capability mismatch as
	 * `prompt_invalid_argument` (no HTTP request is made), and a provider
	 * reports an unsupported value as a 400/422. Auth, quota and rate-limit
	 * errors are left alone so a retry cannot burn them twice.
	 *
	 * @param WP_Error $error Error returned by the AI Client.
	 * @return bool
	 */
	private static function is_configuration_error( WP_Error $error ): bool {
		if ( 'prompt_invalid_argument' === $error->get_error_code() ) {
			return true;
		}

		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		return in_array( $status, array( 400, 422 ), true );
	}

	/**
	 * Turn a WP_Error into an actionable message.
	 *
	 * @param WP_Error $error Error returned by the AI Client.
	 * @return string Error message, plus a hint when the settings caused it.
	 */
	private static function describe_error( WP_Error $error ): string {
		$message = $error->get_error_message();

		if ( ! self::is_configuration_error( $error ) ) {
			return $message;
		}

		return $message . ' ' . __( 'Tip: open Settings and set Temperature to "Automatic" and Reasoning effort to "Provider default". If the message names a model, enter a model id that the provider connected under Settings → Connectors offers.', 'siphon-store-analytics-for-woocommerce' );
	}
}
