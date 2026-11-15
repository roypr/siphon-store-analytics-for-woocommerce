<?php
/**
 * AI Provider Client
 *
 * Handles communication with the configured AI provider for insight
 * generation. Two wire formats are supported:
 *
 * - OpenAI-compatible /chat/completions (OpenAI, OpenRouter, DeepSeek, Groq,
 *   Mistral, xAI, Ollama, LocalAI, vLLM, Azure OpenAI, …)
 * - Anthropic's native /messages (Claude)
 *
 * Provider-specific differences (endpoint, auth headers, envelope, and the
 * completion-limit parameter name) live in SILC_WIA_Provider_Adapter so the
 * rest of this class stays provider-agnostic.
 *
 * Supports both standard instruct models and reasoning models (o-series,
 * GPT-5/6, DeepSeek, Gemini thinking, Claude extended thinking, …) with
 * appropriate parameter handling for each.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SILC_WIA_PATH . 'includes/class-provider-adapter.php';

/**
 * API client class.
 */
class SILC_WIA_API {

	/**
	 * Default settings.
	 *
	 * DEFAULT_MODEL is the fallback for the default endpoint (OpenAI). Per
	 * provider defaults live in SILC_WIA_Provider_Adapter so the settings
	 * screen and the server agree on a single set of model ids.
	 */
	const DEFAULT_API_URL       = 'https://api.openai.com/v1';
	const DEFAULT_MODEL         = 'gpt-6-luna';
	const DEFAULT_MAX_TOKENS    = 1500;
	const DEFAULT_TEMPERATURE   = 0.2;
	const DEFAULT_CACHE_TTL     = 3600; // 1 hour in seconds.

	/**
	 * Previous built-in default model.
	 *
	 * Installations that never picked a model still store this value. It is
	 * treated as "unset" by get_settings() and replaced with the current
	 * provider default, so an upgrade does not keep calling a retired model.
	 */
	const LEGACY_DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * Anthropic (Claude) endpoint + required API version header.
	 *
	 * Claude's native Messages API differs from the OpenAI-compatible
	 * providers this class otherwise targets:
	 * - POST {url}/messages, not /chat/completions
	 * - x-api-key + anthropic-version headers, not Authorization: Bearer
	 * - a top-level "system" string, not a system-role message
	 * - content is an array of typed blocks, not choices[0].message.content
	 * These differences are bridged in build_anthropic_request_body() and
	 * normalize_anthropic_response() so callers never see them.
	 */
	const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1';
	const ANTHROPIC_VERSION = '2023-06-01';

	/**
	 * Extended-thinking token budget for Claude models.
	 *
	 * ANTHROPIC_THINKING_MAX_TOKENS doubles as the output ceiling for every
	 * Claude request: wp_remote_post() cannot stream, and the Messages API
	 * rejects a non-streaming request whose max_tokens exceeds ~21,333. It is
	 * kept comfortably under that point, so a request stays a plain blocking
	 * call. budget_tokens must stay below max_tokens.
	 */
	const ANTHROPIC_THINKING_BUDGET_TOKENS = 8000;
	const ANTHROPIC_THINKING_MAX_TOKENS    = 16000;

	/**
	 * Output budget used when the Max Tokens setting is unset or lower than the
	 * minimum the JSON payload needs.
	 *
	 * A truncated response cannot be parsed, so this is also the floor applied
	 * to the setting (SILC_WIA_Admin::sanitize_settings() clamps to the same
	 * value).
	 */
	const INSIGHT_MAX_TOKENS = 1500;

	/**
	 * Lowest value the Max Tokens setting may take.
	 */
	const MIN_MAX_TOKENS = 800;

	/**
	 * Max tokens for reasoning models (need extra budget for reasoning content).
	 *
	 * Reasoning tokens are billed and counted against the completion limit, so
	 * a small budget can return an empty answer once the hidden reasoning has
	 * consumed it. Claude is clamped to ANTHROPIC_THINKING_MAX_TOKENS by
	 * build_anthropic_request_body(), since the Messages API refuses larger
	 * non-streaming requests.
	 */
	const REASONING_MAX_TOKENS = 32000;

	/**
	 * Patterns that identify reasoning models.
	 *
	 * Covers current reasoning/thinking model families across providers:
	 * - OpenAI: o1/o3/o4 series, gpt-5 and gpt-6 series.
	 * - DeepSeek: R1, reasoner variants and the V4 thinking modes.
	 * - Google: Gemini 2.0 Flash Thinking and 2.5/3 thinking variants.
	 * - Anthropic: Claude 3.7 Sonnet, Claude 4/4.5/5 (extended thinking).
	 * - xAI: Grok 3 and later.
	 * - Qwen: QwQ and Qwen3 thinking variants.
	 */
	const REASONING_MODEL_PATTERNS = array(
		'/^o[134](-|$)/i',
		'/^gpt-[56]/i',
		'/deepseek/i',
		'/gemini-2\.0-flash-thinking/i',
		'/^gemini-[23].*thinking/i',
		'/^claude-opus-4/i',
		'/^claude-sonnet-4/i',
		'/^claude-haiku-4-5/i',
		'/^claude-(opus|sonnet|haiku)-5/i',
		'/^claude-3-7-sonnet/i',
		'/^(fable|mythos)-5/i',
		'/^grok-[3-9]/i',
		'/^qwq/i',
		'/^qwen3.*thinking/i',
	);

	/**
	 * Claude models that use ADAPTIVE thinking.
	 *
	 * On these generations thinking is controlled by the API automatically
	 * and the legacy `thinking` object is NOT accepted — sending
	 * `{"type":"enabled","budget_tokens":N}` (or `{"type":"disabled"}` where
	 * disallowed) returns HTTP 400. The field must simply be omitted.
	 */
	const CLAUDE_ADAPTIVE_THINKING_PATTERNS = array(
		'/^claude-(opus|sonnet|haiku)-5/i',
		'/^claude-opus-4[.\-][78]/i',
		'/^(fable|mythos)-5(\.|$|-)/i',
	);

	/**
	 * Claude models that use the LEGACY extended-thinking form.
	 *
	 * These accept `{"type":"enabled","budget_tokens":N}`:
	 * - Opus 4.5, Sonnet 4.5, Haiku 4.5: `enabled` + `budget_tokens` only.
	 * - Opus 4.6, Sonnet 4.6: adaptive, or `enabled` + `budget_tokens`
	 *   (deprecated but still accepted).
	 * - Claude 3.7 Sonnet: `enabled` + `budget_tokens`.
	 *
	 * The minor version is written with either a dot ("Claude Sonnet 4.5") or
	 * a hyphen (the API model id, e.g. `claude-sonnet-4-5-20250929`), so both
	 * separators must match.
	 */
	const CLAUDE_BUDGET_THINKING_PATTERNS = array(
		'/^claude-(opus|sonnet)-4[.\-][56]/i',
		'/^claude-haiku-4-5/i',
		'/^claude-3-7-sonnet/i',
	);

	// ----------------------------------------------------------------------- //
	//  SETTINGS
	// ----------------------------------------------------------------------- //

	/**
	 * Get API settings.
	 *
	 * A stored model id can outlive the endpoint it belongs to: the built-in
	 * default changed over time, and a provider switch leaves the previous
	 * provider's model behind. Both cases are corrected here so no request is
	 * sent with a model the endpoint cannot serve.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = array(
			'api_url'     => self::DEFAULT_API_URL,
			'api_key'     => '',
			'model'       => self::DEFAULT_MODEL,
			'max_tokens'  => self::DEFAULT_MAX_TOKENS,
			'temperature' => self::DEFAULT_TEMPERATURE,
			'cache_ttl'   => self::DEFAULT_CACHE_TTL,
		);

		$saved = get_option( 'silc_wia_api_settings', array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		// A stored value that is still the old built-in default means the user
		// never chose a model — follow the current provider default instead of
		// calling a model the provider has retired.
		if ( self::LEGACY_DEFAULT_MODEL === $settings['model'] ) {
			$settings['model'] = self::get_default_model_for_url( (string) $settings['api_url'] );
		}

		// A model id is provider-specific. If the saved model clearly belongs
		// to a different provider (e.g. a GPT id left behind after switching
		// to Claude), use the endpoint's default instead of sending a request
		// that cannot succeed.
		if ( ! SILC_WIA_Provider_Adapter::model_matches_provider( (string) $settings['model'], (string) $settings['api_url'] ) ) {
			$settings['model'] = self::get_default_model_for_url( (string) $settings['api_url'] );
		}

		return $settings;
	}

	/**
	 * Resolve the default model for an API URL.
	 *
	 * @param string $api_url API base URL.
	 * @return string Model id.
	 */
	public static function get_default_model_for_url( string $api_url ): string {
		$model = SILC_WIA_Provider_Adapter::get_default_model( $api_url );

		return '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Get the cache TTL in seconds from settings, with a sensible default.
	 *
	 * @return int Cache TTL in seconds.
	 */
	public static function get_cache_ttl(): int {
		$settings = self::get_settings();
		$ttl      = intval( $settings['cache_ttl'] ?? self::DEFAULT_CACHE_TTL );
		return max( 0, min( 86400 * 7, $ttl ) ); // Between 0 and 7 days.
	}

	/**
	 * Whether an API URL points at Anthropic's native API.
	 *
	 * Single switch that decides which wire format (OpenAI-compatible vs
	 * Claude Messages API) the rest of the class uses. Delegates to the
	 * provider adapter so detection lives in one place.
	 *
	 * @param string $api_url API base URL.
	 * @return bool
	 */
	public static function is_anthropic( string $api_url ): bool {
		return SILC_WIA_Provider_Adapter::is_anthropic( $api_url );
	}

	/**
	 * Whether an API URL may be used without exposing the key.
	 *
	 * The API key and the prompt travel in the request body/headers, so plain
	 * HTTP is only acceptable when the request never leaves the machine or the
	 * local network (Ollama, LocalAI, vLLM, LM Studio). Everything else must be
	 * HTTPS.
	 *
	 * @param string $url API base URL.
	 * @return bool
	 */
	public static function is_allowed_api_url( string $url ): bool {
		$url = trim( $url );

		if ( '' === $url ) {
			return false;
		}

		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

		if ( 'https' === $scheme ) {
			return true;
		}

		return self::is_local_host( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Whether a host is local (loopback, private network or a mDNS-style name).
	 *
	 * @param string $host Host name or IP.
	 * @return bool
	 */
	public static function is_local_host( string $host ): bool {
		$host = strtolower( trim( $host, '[]' ) );

		if ( '' === $host ) {
			return false;
		}

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '0.0.0.0', '::1' ), true ) ) {
			return true;
		}

		if ( '.localhost' === substr( $host, -10 ) ) {
			return true;
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			// Private (RFC 1918) and reserved (loopback, link-local) ranges.
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		if ( '.local' === substr( $host, -6 ) || '.test' === substr( $host, -5 ) ) {
			return true;
		}

		// A bare host name (docker service, "ollama") has no dot and can only
		// resolve inside the local network.
		return false === strpos( $host, '.' );
	}

	/**
	 * Check if the configured model is a reasoning model.
	 *
	 * Reasoning models (o-series, GPT-5/6, DeepSeek, Claude extended thinking,
	 * etc.) require different parameter handling:
	 * - No temperature parameter
	 * - Higher completion budget (reasoning content consumes tokens)
	 *
	 * @param string $model Model name.
	 * @return bool
	 */
	public static function is_reasoning_model( string $model ): bool {
		foreach ( self::REASONING_MODEL_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $model ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect which extended-thinking mechanism a Claude model uses.
	 *
	 * Returns one of:
	 * - 'adaptive' — thinking is managed by the API; the request must omit
	 *   the `thinking` field entirely (sending `enabled`/`budget_tokens` or
	 *   `disabled` returns HTTP 400 on these models).
	 * - 'budget'   — legacy form: `{"type":"enabled","budget_tokens":N}`.
	 * - 'none'     — not a Claude thinking model (or not a Claude model).
	 *
	 * Adaptive models are checked first because some generations (Opus 4.6 /
	 * Sonnet 4.6) appear in both lists — the deprecated budget form still
	 * works there, but omitting `thinking` is always safe, so 'adaptive'
	 * wins.
	 *
	 * @param string $model Model name.
	 * @return string 'adaptive' | 'budget' | 'none'.
	 */
	public static function get_claude_thinking_mode( string $model ): string {
		foreach ( self::CLAUDE_ADAPTIVE_THINKING_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $model ) ) {
				return 'adaptive';
			}
		}
		foreach ( self::CLAUDE_BUDGET_THINKING_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $model ) ) {
				return 'budget';
			}
		}
		return 'none';
	}

	/**
	 * Get appropriate max_tokens for a model.
	 *
	 * Reasoning models need a larger budget because they output reasoning
	 * content before the final visible answer. The visible output plus
	 * reasoning must fit within max_tokens.
	 *
	 * @param string $model      Model name.
	 * @param int    $base_tokens Base token limit for instruct models.
	 * @return int
	 */
	public static function get_effective_max_tokens( string $model, int $base_tokens ): int {
		if ( self::is_reasoning_model( $model ) ) {
			return max( $base_tokens, self::REASONING_MAX_TOKENS );
		}
		return $base_tokens;
	}

	/**
	 * Extract the assistant message content from an API response.
	 *
	 * Handles both shapes:
	 * - Claude Messages API: `content` is an array of typed blocks; only
	 *   `type: "text"` blocks are visible output (a `thinking` block is
	 *   skipped).
	 * - OpenAI-compatible: `choices[0].message.content` is a plain string.
	 *
	 * @param array $data Decoded API response.
	 * @return string The visible content text.
	 */
	private static function extract_content( array $data ): string {
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$text = '';
			foreach ( $data['content'] as $block ) {
				if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && isset( $block['text'] ) && is_string( $block['text'] ) ) {
					$text .= $block['text'];
				}
			}
			if ( '' !== $text ) {
				return $text;
			}
		}

		if ( isset( $data['choices'][0]['message']['content'] ) && is_string( $data['choices'][0]['message']['content'] ) ) {
			return $data['choices'][0]['message']['content'];
		}
		return '';
	}

	/**
	 * Build the full assistant message (including reasoning content) for retry.
	 *
	 * For reasoning models, the API may include a `reasoning_content` field
	 * alongside `content`. When retrying, we need to send both back so the
	 * model has full context of its previous response.
	 *
	 * @param array $data Decoded API response from the first attempt.
	 * @return array Assistant message.
	 */
	private static function build_assistant_retry_message( array $data ): array {
		$message = array(
			'role'    => 'assistant',
			'content' => self::extract_content( $data ),
		);

		// Include reasoning_content if the API returned it (reasoning models).
		if ( isset( $data['choices'][0]['message']['reasoning_content'] ) ) {
			$message['reasoning_content'] = $data['choices'][0]['message']['reasoning_content'];
		}

		return $message;
	}

	// ----------------------------------------------------------------------- //
	//  BODY BUILDER
	// ----------------------------------------------------------------------- //

	/**
	 * Build an OpenAI-shaped chat request body for the configured provider.
	 *
	 * Single place that decides:
	 * - which completion-limit parameter to send (provider dependent);
	 * - whether a temperature value is allowed (reasoning models reject any
	 *   non-default value, Anthropic deprecates it).
	 *
	 * The body is always built in the OpenAI wire format; send_request()
	 * translates it to the Messages API when the endpoint is Anthropic.
	 *
	 * @param string     $model       Model name.
	 * @param array      $messages    Chat messages (OpenAI shape).
	 * @param int        $max_tokens  Completion token limit.
	 * @param float|null $temperature Optional sampling temperature.
	 * @param string     $api_url     API base URL (empty for the configured one).
	 * @return array Request body.
	 */
	private static function build_chat_body( string $model, array $messages, int $max_tokens, ?float $temperature = null, string $api_url = '' ): array {
		if ( '' === $api_url ) {
			$settings = self::get_settings();
			$api_url  = (string) $settings['api_url'];
		}

		$token_param = SILC_WIA_Provider_Adapter::get_token_param( $api_url, $model );

		$body = array(
			'model'      => $model,
			'messages'   => $messages,
			$token_param => $max_tokens,
		);

		if ( null !== $temperature && SILC_WIA_Provider_Adapter::supports_temperature( $api_url, $model ) ) {
			$body['temperature'] = $temperature;
		}

		return $body;
	}

	/**
	 * Translate an OpenAI-shaped request body into Claude's Messages format.
	 *
	 * Callers build bodies for the OpenAI wire format (model/messages plus
	 * max_tokens or max_completion_tokens). This bridges the gaps so they do
	 * not need to know about the provider:
	 * - the system-role turn is moved into a top-level `system` string, since
	 *   Claude has no `system` role inside `messages`;
	 * - `max_completion_tokens` is mapped to `max_tokens`, and the value is
	 *   clamped to the non-streaming ceiling;
	 * - `temperature` is never sent: Claude rejects it while thinking is
	 *   enabled, and newer Claude models deprecate it outright.
	 *
	 * @param array $body OpenAI-shaped request body.
	 * @return array Claude Messages request body.
	 */
	private static function build_anthropic_request_body( array $body ): array {
		$model    = isset( $body['model'] ) ? (string) $body['model'] : self::DEFAULT_MODEL;
		$messages = ( isset( $body['messages'] ) && is_array( $body['messages'] ) ) ? $body['messages'] : array();

		$system             = '';
		$turns              = array();
		$has_assistant_turn = false;

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = isset( $message['role'] ) ? (string) $message['role'] : '';

			if ( 'system' === $role ) {
				$piece  = isset( $message['content'] ) && is_string( $message['content'] ) ? $message['content'] : '';
				$system = ( '' === $system ) ? $piece : $system . "\n\n" . $piece;
				continue;
			}

			if ( 'assistant' === $role ) {
				$has_assistant_turn = true;
			}

			$turns[] = $message;
		}

		$max_tokens = 0;
		if ( isset( $body['max_tokens'] ) ) {
			$max_tokens = (int) $body['max_tokens'];
		} elseif ( isset( $body['max_completion_tokens'] ) ) {
			$max_tokens = (int) $body['max_completion_tokens'];
		}
		if ( $max_tokens <= 0 ) {
			$max_tokens = self::DEFAULT_MAX_TOKENS;
		}

		// The Messages API refuses a non-streaming request whose max_tokens
		// exceeds ~21,333, and wp_remote_post() cannot stream. Clamp to the
		// safe ceiling.
		$max_tokens = min( $max_tokens, self::ANTHROPIC_THINKING_MAX_TOKENS );

		$anthropic = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => $turns,
		);

		if ( '' !== $system ) {
			$anthropic['system'] = $system;
		}

		// Extended thinking is only requested on a fresh, single-turn call.
		// The JSON-retry flow replays the previous assistant turn as plain
		// text (without its thinking block), which the Messages API rejects
		// while thinking is enabled — so thinking is skipped whenever the
		// conversation already contains an assistant turn.
		if ( ! $has_assistant_turn && self::is_reasoning_model( $model ) ) {
			$thinking_mode = self::get_claude_thinking_mode( $model );

			if ( 'budget' === $thinking_mode ) {
				// Legacy extended-thinking models (4.5/4.6, 3.7 Sonnet):
				// explicit `enabled` + budget_tokens. budget_tokens must stay
				// below max_tokens, and the total must stay under the
				// ~21,333-token point where the Messages API requires
				// streaming (wp_remote_post() cannot stream).
				$anthropic['max_tokens'] = max( $max_tokens, self::ANTHROPIC_THINKING_MAX_TOKENS );
				$anthropic['thinking']   = array(
					'type'          => 'enabled',
					'budget_tokens' => self::ANTHROPIC_THINKING_BUDGET_TOKENS,
				);
			}
			// 'adaptive' (Opus 5+, Sonnet 5, Opus 4.7/4.8, Fable/Mythos 5.x):
			// omit the `thinking` field entirely — the API manages thinking
			// itself and rejects the legacy object with HTTP 400.
			// 'none' (non-Claude reasoning model on an Anthropic URL, or an
			// unrecognized Claude name): omit as well, the safest default.
		}

		return $anthropic;
	}

	/**
	 * Normalize a Claude response into the OpenAI envelope callers expect.
	 *
	 * Callers read `data.choices[0].message.content`, so the visible text is
	 * projected onto that path. The original Claude fields are left intact.
	 *
	 * @param array $data Decoded Claude response.
	 * @return array OpenAI-shaped response.
	 */
	private static function normalize_anthropic_response( array $data ): array {
		$data['choices'] = array(
			array(
				'message' => array(
					'role'    => 'assistant',
					'content' => self::extract_content( $data ),
				),
			),
		);

		return $data;
	}

	/**
	 * Send a chat completion request and return the parsed response.
	 *
	 * The caller always supplies an OpenAI-shaped body. When the configured
	 * API URL points at Anthropic, the body is translated to the Messages API
	 * format and the response is normalized back to the OpenAI envelope, so
	 * callers stay provider-agnostic.
	 *
	 * If an OpenAI-compatible endpoint rejects the completion-limit parameter
	 * (or a temperature value) the request is retried once with the parameter
	 * renamed/removed — see SILC_WIA_Provider_Adapter::adapt_rejected_body().
	 *
	 * @param array $body Request body.
	 * @return array{success: bool, data: array|null, error: string}
	 */
	private static function send_request( array $body ): array {
		$settings = self::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'API key is not configured. Please go to Settings and add your API key.', 'store-insights-for-woocommerce' ),
			);
		}

		$api_url   = untrailingslashit( $settings['api_url'] );
		$is_claude = self::is_anthropic( $api_url );

		$result = self::dispatch_request( $body, $api_url, (string) $settings['api_key'], $is_claude );

		// Provider drift guard: the OpenAI-compatible ecosystem does not agree
		// on the completion-limit parameter name, and a routing layer such as
		// OpenRouter may forward to an upstream that lags its own docs.
		if ( ! $result['success'] && ! $is_claude ) {
			$adapted = SILC_WIA_Provider_Adapter::adapt_rejected_body( $body, (string) $result['error'] );

			if ( null !== $adapted ) {
				$result = self::dispatch_request( $adapted, $api_url, (string) $settings['api_key'], $is_claude );
			}
		}

		return $result;
	}

	/**
	 * Perform one HTTP request against the configured endpoint.
	 *
	 * @param array  $body      OpenAI-shaped request body.
	 * @param string $api_url   API base URL (no trailing slash).
	 * @param string $api_key   API key.
	 * @param bool   $is_claude Whether the endpoint is Anthropic's.
	 * @param int    $timeout   Request timeout in seconds.
	 * @return array{success: bool, data: array|null, error: string}
	 */
	private static function dispatch_request( array $body, string $api_url, string $api_key, bool $is_claude, int $timeout = 90 ): array {
		// Last line of defence: a plain-HTTP URL that was stored before this
		// rule existed must not send the API key and prompt in clear text.
		if ( ! self::is_allowed_api_url( $api_url ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'The API URL must use HTTPS. Plain HTTP is only allowed for local endpoints such as http://localhost:11434/v1.', 'store-insights-for-woocommerce' ),
			);
		}

		if ( $is_claude ) {
			$body     = self::build_anthropic_request_body( $body );
			$endpoint = $api_url . '/messages';
			$headers  = array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
			);
		} else {
			$endpoint = $api_url . '/chat/completions';
			$headers  = array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			);
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				// 90s is generous for a single completion and stays well inside
				// the default max_execution_time on most shared hosts.
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => sprintf(
					/* translators: %s: error message */
					__( 'API request failed: %s', 'store-insights-for-woocommerce' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_raw    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body_raw, true );

		// Claude's error envelope is {"type":"error","error":{"type":...,"message":...}}
		// — the same $data['error']['message'] lookup used for OpenAI-compatible
		// errors works unchanged here.
		if ( $status_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf(
				/* translators: %d: HTTP status code */
				__( 'API returned HTTP %d', 'store-insights-for-woocommerce' ),
				$status_code
			);
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $error_msg,
			);
		}

		if ( $is_claude && is_array( $data ) ) {
			$data = self::normalize_anthropic_response( $data );
		}

		return array(
			'success' => true,
			'data'    => $data,
			'error'   => '',
		);
	}

	// ----------------------------------------------------------------------- //
	//  INSIGHT GENERATION
	// ----------------------------------------------------------------------- //

	/**
	 * Generate a structured insight from a natural language question.
	 *
	 * Uses a dedicated prompt that asks the AI to return valid JSON with
	 * fields: sql, type, chart_config, list_config, answer_text.
	 *
	 * Uses get_schema_context() from Woo_Schema.
	 *
	 * @param string $question       The user's natural language question.
	 * @param string $schema_context The database schema context for the prompt.
	 * @return array{success: bool, insight: array|null, error: string}
	 */
	public static function generate_insight( string $question, string $schema_context ): array {
		$settings = self::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => __( 'API key is not configured. Please go to Settings and add your API key.', 'store-insights-for-woocommerce' ),
			);
		}

		// Build prompts.
		$system_prompt = self::build_insight_system_prompt();
		$system_prompt .= "\nHere is the WooCommerce database schema (table prefix included):";
		$system_prompt .= "\n" . $schema_context;

		$user_prompt   = self::build_insight_user_prompt( $question );

		$model = (string) $settings['model'];

		// The Max Tokens setting is the output budget for this call. The
		// minimum exists because a response cut off mid-JSON cannot be parsed,
		// so anything below it is raised (the settings screen clamps to the
		// same range). Reasoning models still get the larger budget from
		// get_effective_max_tokens().
		$base_tokens = (int) ( $settings['max_tokens'] ?? 0 );

		if ( $base_tokens < self::MIN_MAX_TOKENS ) {
			$base_tokens = max( self::INSIGHT_MAX_TOKENS, self::MIN_MAX_TOKENS );
		}

		$max_tokens = self::get_effective_max_tokens( $model, $base_tokens );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
			array(
				'role'    => 'user',
				'content' => $user_prompt,
			),
		);

		$body = self::build_chat_body( $model, $messages, $max_tokens, 0.2, (string) $settings['api_url'] );

		$result = self::send_request( $body );

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => $result['error'],
			);
		}

		$content = self::extract_content( $result['data'] );

		if ( empty( $content ) ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => __( 'API returned an empty response.', 'store-insights-for-woocommerce' ),
			);
		}

		// Parse the JSON with multi-strategy fallback.
		$insight = self::parse_insight_json( $content );

		if ( null === $insight ) {
			// Retry: send the previous assistant response back as context,
			// including reasoning_content if the API returned it, then ask for
			// pure JSON.
			//
			// The assistant turn must be replayed, not skipped: Claude's
			// Messages API rejects two consecutive user turns, and the model
			// can only correct its own output if it can see it.
			$retry_body = $body;
			$assistant  = self::build_assistant_retry_message( $result['data'] );

			if ( '' !== (string) $assistant['content'] ) {
				$retry_body['messages'][] = $assistant;
			}

			$retry_body['messages'][] = array(
				'role'    => 'user',
				'content' => 'Your previous response was not valid JSON. Return ONLY valid JSON with no markdown, no explanation, just the JSON object.',
			);
			$retry = self::send_request( $retry_body );
			if ( $retry['success'] ) {
				$retry_content = self::extract_content( $retry['data'] );
				if ( ! empty( $retry_content ) ) {
					$insight = self::parse_insight_json( $retry_content );
				}
			}
		}

		if ( null === $insight ) {
			return array(
				'success' => false,
				'insight' => null,
				'error'   => __( 'Could not parse insight JSON from the API response.', 'store-insights-for-woocommerce' ),
			);
		}

		return array(
			'success' => true,
			'insight' => $insight,
			'error'   => '',
		);
	}

	/**
	 * Build the system prompt for insight generation (structured JSON output).
	 *
	 * @return string
	 */
	private static function build_insight_system_prompt(): string {
		return 'You are a senior WooCommerce Analytics Engineer. Your job is to translate a business question about a WooCommerce store into ONE correct, executable, read-only SELECT query, plus the presentation metadata that describes its output.'
			. ' Analytical correctness is your PRIMARY responsibility. SQL is the final step of your reasoning, never the first.'
			. "\n\n"
			. "SCOPE & SAFETY — hard rules:"
			. "\n"
			. "\n1. WOOCOMMERCE ONLY: orders, products, customers, coupons, categories, revenue, stock, taxes, shipping, order statuses, product ratings."
			. "\n2. {$GLOBALS['wpdb']->prefix}posts IS ALLOWED for WooCommerce post types only: product, product_variation, shop_coupon. Always filter by post_type."
			. "\n3. SELECT ONLY: never INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE/RENAME. No DDL, no multi-statement, no forbidden functions (SLEEP, BENCHMARK, OUTFILE, etc.)."
			. "\n4. PII PROTECTION: limit PII columns (email, phone, address) to 50 rows. For bulk PII extraction, use PII_EXCEEDED error."
			. "\n5. NO SELECT *: always enumerate columns."
			. "\n"
			. "\nREASONING PIPELINE — think in this order before writing SQL:"
			. "\n  1. BUSINESS QUESTION — restate what the user wants in one line."
			. "\n  2. BUSINESS INTENT — KPI / list / ranking / trend / comparison / distribution / breakdown / cohort."
			. "\n  3. METRIC — revenue, orders, customers, AOV, stock, discount. Pick the correct column (beware homonyms: {$GLOBALS['wpdb']->prefix}wc_product_meta_lookup.total_sales = units sold, NOT money)."
			. "\n  4. DIMENSIONS — month, year, category, customer, country, payment method, product, status."
			. "\n  5. FILTERS — order status, date range, product type. Always include parent_id = 0 on {$GLOBALS['wpdb']->prefix}wc_order_stats unless the question is about refunds."
			. "\n  6. TIME PERIOD — resolve to concrete literal dates. Never use CURDATE(), NOW(), or non-deterministic functions."
			. "\n  7. ENTITIES & TABLES — prefer analytical tables ({$GLOBALS['wpdb']->prefix}wc_order_stats, {$GLOBALS['wpdb']->prefix}wc_order_product_lookup, {$GLOBALS['wpdb']->prefix}wc_customer_lookup). Use transactional tables only when necessary."
			. "\n  8. JOINS — join only what is required. Verify no fan-out. LEFT JOIN for meta."
			. "\n  9. CALCULATIONS — aggregations, GROUP BY, ORDER BY, aliases."
			. "\n 10. SQL — construct the ENTIRE query from the completed plan. Use sargable date ranges (>= ''YYYY-MM-DD'' AND < ''YYYY-MM-DD''), never YEAR()/MONTH() in WHERE."
			. "\n 11. VALIDATION — run the checklist below. If anything fails, discard SQL, fix plan, regenerate."
			. "\n 12. PRESENTATION — choose output type + config from the SQL output shape. Presentation must NEVER influence the SQL."
			. "\n"
			. "\nINTERPRETATION RULES — determine business meaning before translating to SQL:"
			. "\n- \"Revenue\" / \"Sales\" → SUM(total_sales) WHERE parent_id = 0 (on {$GLOBALS['wpdb']->prefix}wc_order_stats)."
			. "\n- \"Net sales\" → SUM(net_total) WHERE parent_id = 0."
			. "\n- \"Net revenue after refunds\" → SUM(total_sales) without parent_id filter (refund rows are negative)."
			. "\n- \"Top products\" → rank by quantity sold OR revenue per context."
			. "\n- \"Repeat/returning customers\" → customers with multiple qualifying orders. Do NOT use returning_customer column."
			. "\n- \"Trend\" → time-series aggregation. \"Distribution\" → grouped counts or percentages."
			. "\n- \"Top/Highest/Best/Most\" → DESC. \"Bottom/Lowest/Least/Worst\" → ASC."
			. "\n"
			. "\nTIME PERIOD CONVENTIONS — compute literal date boundaries from injected current date:"
			. "\n- Use sargable ranges: >= ''YYYY-MM-DD'' AND < ''YYYY-MM-DD''. Never YEAR()/MONTH() in WHERE. Never BETWEEN for datetimes."
			. "\n- \"This year\" / \"Last year\" → current / previous calendar year."
			. "\n- \"Last N months/days\" → rolling window from current date."
			. "\n- When comparing incomplete periods (e.g., this year vs last year asked in July), truncate the prior period to the same elapsed window."
			. "\n"
			. "\nVALIDATION CHECKLIST — every item must pass before output:"
			. "\n- Business: Does the SQL answer the user's ACTUAL question?"
			. "\n- Metric: Correct column? Check homonyms (total_sales = money on {$GLOBALS['wpdb']->prefix}wc_order_stats, but = units sold on {$GLOBALS['wpdb']->prefix}wc_product_meta_lookup)."
			. "\n- Refunds: parent_id = 0 filter on {$GLOBALS['wpdb']->prefix}wc_order_stats (unless question is about refunds)?"
			. "\n- Grain: No fan-out? order_item_type filtered when joining {$GLOBALS['wpdb']->prefix}woocommerce_order_items?"
			. "\n- Schema: Every table/column exists? No phantom columns (e.g., type on {$GLOBALS['wpdb']->prefix}wc_order_stats)?"
			. "\n- Aggregation: GROUP BY correct? Every non-aggregate column in GROUP BY?"
			. "\n- Dates: Sargable? Literal dates (no CURDATE/NOW)? Incomplete periods truncated for comparison?"
			. "\n- Ordering: Time/month series ordered by number, not name?"
			. "\n- Limits: LIMIT present for non-aggregated result sets? PII limited to 50 rows?"
			. "\n"
			. "\nERROR CODES — when a request is forbidden or out of scope, return ONLY this JSON (no other text, no markdown):"
			. "\n"
			. "\n{"
			. "\n  \"error_code\": \"FORBIDDEN\","
			. "\n  \"error_message\": \"Short user-friendly explanation\""
			. "\n}"
			. "\n"
			. "\nAvailable error_code values and when to use each:"
			. "\n  FORBIDDEN — User asks to modify/delete/insert/update/drop/alter/truncate data. Even if they say delete or remove,"
			. "\n    respond with FORBIDDEN. Do NOT write a SELECT for a destructive action."
			. "\n  OUT_OF_SCOPE — Question is not about WooCommerce store data (e.g., WordPress posts, user management, general knowledge, chit-chat, weather, jokes)."
			. "\n  NOT_WOOCOMMERCE — Question asks about non-WooCommerce post types or tables (posts, pages, comments, attachments, custom post types)."
			. "\n"
			. "\nCRITICAL: If the user says delete product X, remove order #123, update all prices, insert coupon,"
			. "\ndrop table, change status of..., or ANYTHING that implies modifying data — respond with error_code FORBIDDEN."
			. "\nDo NOT try to be clever and write SELECT * FROM products WHERE... when user asked to delete them."
			. "\nThe error_message should be a short friendly sentence like:"
			. "\n  FORBIDDEN → I can only answer questions about your store data. I cannot modify or delete anything."
			. "\n  OUT_OF_SCOPE → I can only help with WooCommerce store questions. Try asking about sales, products, orders, or customers."
			. "\n  NOT_WOOCOMMERCE → I only work with WooCommerce store data. That request involves non-store content."
			. "\n"
			. "\nWhen the request IS a valid WooCommerce question, you MUST return ONLY valid JSON (no other text, no markdown) with this structure:"
			. "\n\n"
			. '{'
			. "\n  \"sql\": \"The SQL SELECT query to execute\","
			. "\n  \"title\": \"Short descriptive title (2-6 words). Examples: Best Selling Products, Monthly Revenue Trend, Order Status Distribution.\","
			. "\n  \"type\": \"chart\" | \"list\" | \"answer\","
			. "\n  \"chart_config\": {"
			. "\n    \"chart_type\": \"bar\" | \"line\" | \"pie\" | \"horizontalBar\" | \"doughnut\" | \"radar\" | \"polarArea\","
			. "\n    \"title\": \"Chart title\","
			. "\n    \"transform\": {"
			. "\n      \"type\": \"group_split\" | \"columns_to_datasets\","
			. "\n      \"group_by\": \"column name to split datasets by (for group_split)\","
			. "\n      \"x_axis\": \"column name for x-axis labels (for group_split)\","
			. "\n      \"value_column\": \"column name for y-axis values (for group_split)\","
			. "\n      \"x_labels\": [\"optional ordered label strings, e.g. Jan,Feb..\"],"
			. "\n      \"label_column\": \"column name for x-axis labels (for columns_to_datasets)\","
			. "\n      \"value_columns\": [\"array of numeric column names to chart (for columns_to_datasets)\"]"
			. "\n    },"
			. "\n    \"x_label\": \"X-axis label (optional)\","
			. "\n    \"y_label\": \"Y-axis label (optional)\""
			. "\n  },"
			. "\n  \"answer_text\": \"Template for the answer text. Use {{column_name}} placeholders, e.g. Total orders last week: {{total_orders}}\","
			. "\n  \"list_config\": {"
			. "\n    \"title_column\": \"Column name to use as the primary display text\","
			. "\n    \"link_columns\": {"
			. "\n      \"column_name\": \"link_type\""
			. "\n    },"
			. "\n    \"display_columns\": [\"col1\", \"col2\"],"
			. "\n    \"value_formats\": {"
			. "\n      \"total_sales\": \"currency\","
			. "\n      \"order_total\": \"currency\","
			. "\n      \"count\": \"number\""
			. "\n    }"
			. "\n  }"
			. "\n}"
			. "\n\nCRITICAL — You have NO access to actual data:"
			. "\n- You only know column names and types from the schema. You do NOT know what values exist in the database."
			. "\n- NEVER include labels or datasets arrays in chart_config — you cannot know the actual values."
			. "\n- For charts, ALWAYS use the transform object to tell the server how to reshape flat SQL results into Chart.js format."
			. "\n- The SQL you write WILL be executed against the real database. The server will use the real result rows."
			. "\n"
			. "\nChart transform examples (use these, not pre-computed values):"
			. "\n  group_split: SQL returns columns like year, month, revenue. Transform tells server: group_by=year,"
			. "\n    x_axis=month, value_column=revenue, x_labels=[Jan,Feb,...]. The server splits rows by year"
			. "\n    into separate datasets, using month as x-axis labels."
			. "\n  columns_to_datasets: SQL returns columns like month, total_sales, refunds. Transform tells server:"
			. "\n    label_column=month, value_columns=[total_sales,refunds]. Each value column becomes a dataset."
			. "\n"
			. "\nType selection rules:"
			. "\n- type=chart when question asks for comparison, trend, distribution, or visualization"
			. "\n- type=list when question asks for list, show me, who are, which customers, pending orders, top products"
			. "\n- type=answer when question asks for count, total, average, or a single numeric answer"
			. "\n"
			. "\nOther rules:"
			. "\n- Use COALESCE for potentially NULL numeric columns"
			. "\n- Use proper MySQL date functions for time-based queries"
			. "\n- Format currency as plain numbers (frontend adds currency symbol)"
			. "\n- For list type, include link_columns mapping (order_id->order, product_id->product, customer_id/user_id->user, coupon_id->coupon)"
			. "\n- DO NOT include markdown code fences around the JSON"
			. "\n- For answer type, provide answer_text with {{column_name}} placeholders";
	}

	/**
	 * Build the user prompt for insight generation.
	 *
	 * @param string $question       The user's question.
	 * @return string
	 */
	private static function build_insight_user_prompt( string $question ): string {
		return "User question: " . $question
			. "

Remember: If this question asks to modify, delete, insert, update, drop, or alter data in any way,"
			. ' respond with {"error_code": "FORBIDDEN", "error_message": "..."}.'
			. "
If it is not about WooCommerce store data, respond with the appropriate error_code."
			. "
Otherwise, return ONLY valid JSON with the SQL query, output type, and configuration as specified.";
	}

	/**
	 * Parse insight JSON from the API response with multi-strategy fallback.
	 *
	 * Strategy chain:
	 * 1. Direct JSON decode
	 * 2. Strip markdown fences, then decode
	 * 3. Regex extraction of JSON block
	 *
	 * @param string $raw_response The raw API response text.
	 * @return array|null Parsed insight data or null on failure.
	 */
	public static function parse_insight_json( string $raw_response ): ?array {
		// Strategy 1: Direct JSON decode.
		$data = json_decode( $raw_response, true );
		if ( self::is_valid_insight_json( $data ) ) {
			return $data;
		}

		// Strategy 2: Strip markdown code fences and decode.
		$cleaned = preg_replace( '/```(?:json)?\s*
?/i', '', $raw_response );
		$data    = json_decode( trim( $cleaned ), true );
		if ( self::is_valid_insight_json( $data ) ) {
			return $data;
		}

		// Strategy 3: Regex extract JSON block.
		preg_match( '/\{.*\}/s', $raw_response, $matches );
		if ( ! empty( $matches[0] ) ) {
			$data = json_decode( $matches[0], true );
			if ( self::is_valid_insight_json( $data ) ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Validate that parsed insight data has the required fields.
	 *
	 * Accepts either:
	 * - A normal insight response (sql + type)
	 * - An error_code response (error_code + error_message)
	 *
	 * @param mixed $data The parsed data to validate.
	 * @return bool True if valid.
	 */
	private static function is_valid_insight_json( $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		// Accept error_code responses.
		if ( isset( $data['error_code'] ) && is_string( $data['error_code'] ) ) {
			$valid_codes = array( 'FORBIDDEN', 'OUT_OF_SCOPE', 'NOT_WOOCOMMERCE' );
			if ( ! in_array( $data['error_code'], $valid_codes, true ) ) {
				return false;
			}
			return true;
		}

		// Normal insight response must have sql and type.
		if ( ! isset( $data['sql'], $data['type'] ) ) {
			return false;
		}
		if ( ! in_array( $data['type'], array( 'chart', 'list', 'answer' ), true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Test the API connection with a simple request.
	 *
	 * Goes through the same dispatch path as a real call, so a provider whose
	 * endpoint, headers, or parameter names differ (Claude, for instance) is
	 * verified exactly as it will be used later.
	 *
	 * @return array{success: bool, message: string}
	 */
	public static function test_connection(): array {
		$settings = self::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No API key configured.', 'store-insights-for-woocommerce' ),
			);
		}

		$api_url   = untrailingslashit( (string) $settings['api_url'] );
		$model     = (string) $settings['model'];
		$is_claude = self::is_anthropic( $api_url );
		$messages  = array(
			array( 'role' => 'user', 'content' => 'Say "ok" and nothing else.' ),
		);

		if ( $is_claude ) {
			// Built directly rather than through build_anthropic_request_body():
			// a connection check must not enable extended thinking (it would
			// burn the thinking budget just to prove the key works).
			$body = array(
				'model'      => $model,
				'max_tokens' => 10,
				'messages'   => $messages,
			);
		} else {
			// 20 tokens so reasoning models have room to answer "ok".
			$body = self::build_chat_body( $model, $messages, 20, null, $api_url );
		}

		$result = self::dispatch_request( $body, $api_url, (string) $settings['api_key'], $is_claude, 30 );

		if ( ! $result['success'] ) {
			$spec = SILC_WIA_Provider_Adapter::get( $api_url );

			return array(
				'success' => false,
				/* translators: 1: provider name, 2: API error message */
				'message' => sprintf( __( '%1$s: %2$s', 'store-insights-for-woocommerce' ), (string) $spec['label'], (string) $result['error'] ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connection successful!', 'store-insights-for-woocommerce' ),
		);
	}
}
