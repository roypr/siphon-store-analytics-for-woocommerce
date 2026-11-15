<?php
/**
 * AJAX Handler
 *
 * Handles AJAX requests from the React dashboard for insight generation
 * and schema retrieval.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SILC_WIA_PATH . 'includes/class-woo-schema.php';

/**
 * AJAX handler class.
 */
class SILC_WIA_Ajax {

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'silc-wia-nonce';

	/**
	 * Register all AJAX handlers.
	 */
	public static function init(): void {
		// Schema endpoint (used by insight pipeline).
		add_action( 'wp_ajax_silc_wia_get_schema', array( __CLASS__, 'handle_get_schema' ) );
		// Insight endpoints.
		add_action( 'wp_ajax_silc_wia_generate_insight', array( __CLASS__, 'handle_generate_insight' ) );
		add_action( 'wp_ajax_silc_wia_get_insight_history', array( __CLASS__, 'handle_get_insight_history' ) );
		add_action( 'wp_ajax_silc_wia_clear_insight_history', array( __CLASS__, 'handle_clear_insight_history' ) );
		// Execute a stored SQL query without re-invoking AI.
		add_action( 'wp_ajax_silc_wia_execute_sql', array( __CLASS__, 'handle_execute_sql' ) );
	}
	/**
	 * Verify nonce and user capabilities.
	 */
	private static function verify(): bool {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'store-insights-for-woocommerce' ) ) );
			return false;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'store-insights-for-woocommerce' ) ) );
			return false;
		}

		return true;
	}

	/**
	 * Handle: Get the full database schema context for AI prompt.
	 */
	public static function handle_get_schema(): void {
		if ( ! self::verify() ) {
			return;
		}

		$context = SILC_WIA_Woo_Schema::get_schema_context();

		wp_send_json_success( array(
			'schema'  => SILC_WIA_Woo_Schema::get_table_schemas(),
			'context' => $context,
			'prefix'  => SILC_WIA_Woo_Schema::prefix(),
			'tables'  => SILC_WIA_Woo_Schema::get_table_names(),
			'meta_keys' => SILC_WIA_Woo_Schema::get_meta_keys(),
		) );
	}

	// ----------------------------------------------------------------------- //
	//  INSIGHT ENDPOINTS
	// ----------------------------------------------------------------------- //

	/**
	 * Handle: Generate a full insight (AI → SQL → execute → render).
	 *
	 * POST params: question (string)
	 * Response: { success, data: { type, chart_config, list_data, answer_text, ... } }
	 */
	public static function handle_generate_insight(): void {
		if ( ! self::verify() ) {
			return;
		}

		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';
		$question = trim( $question );

		if ( empty( $question ) ) {
			wp_send_json_error( array( 'message' => __( 'No question provided.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		require_once SILC_WIA_PATH . 'includes/class-insights.php';

		$insight = SILC_WIA_Insights::generate_insight($question);
		
		if ( ! $insight['success'] ) {
			wp_send_json_error( array(
				'message'    => $insight['error'] ?? __( 'Failed to generate insight.', 'store-insights-for-woocommerce' ),
				'error_code' => $insight['error_code'] ?? '',
				'question'   => $question,
			) );
			return;
		}

		// Save to insight history. Only the question, the query and the
		// presentation config are kept: the result rows may contain customer
		// names, emails and addresses, and replaying the entry re-executes the
		// SQL (see SILC_WIA_Storage).
		$title  = ! empty( $insight['title'] ) ? $insight['title'] : self::derive_title( $question, $insight );
		$render = is_array( $insight['render_config'] ?? null ) ? $insight['render_config'] : array();

		SILC_WIA_Storage::append_history(
			array(
				'id'            => uniqid( 'i_' ),
				'question'      => $question,
				'title'         => $title,
				'type'          => $render['type'] ?? $insight['type'],
				'sql'           => $insight['sql'] ?? '',
				'sql_time_ms'   => $insight['sql_time_ms'] ?? 0,
				'rows_returned' => $insight['rows_returned'] ?? 0,
				'time'          => current_time( 'mysql' ),
				'chart_config'  => $render['chart_config'] ?? null,
				'list_config'   => $render['list_config'] ?? null,
				'answer_text'   => $render['answer_text'] ?? null,
			)
		);

		// Include title in response.
		$insight['title'] = $title;

		wp_send_json_success( $insight );
	}

	/**
	 * Derive a display title from the question or insight data.
	 *
	 * @param string $question The original question.
	 * @param array  $insight  The generated insight data.
	 * @return string
	 */
	private static function derive_title( string $question, array $insight ): string {
		// Use chart title if available. The chart config comes from the AI
		// response, so only a scalar can be used as the stored title.
		if ( isset( $insight['chart_config']['title'] ) && is_scalar( $insight['chart_config']['title'] ) ) {
			$chart_title = trim( (string) $insight['chart_config']['title'] );

			if ( '' !== $chart_title ) {
				return $chart_title;
			}
		}
		// Use answer_text prefix (first ~50 chars) for answer type.
		if ( isset( $insight['answer_text'] ) && is_scalar( $insight['answer_text'] ) && '' !== (string) $insight['answer_text'] ) {
			$answer_text = (string) $insight['answer_text'];
			$short       = substr( $answer_text, 0, 50 );

			return rtrim( $short, ' .' ) . ( strlen( $answer_text ) > 50 ? '...' : '' );
		}
		// Fallback: use first 60 chars of the question.
		return mb_substr( $question, 0, 60 ) . ( mb_strlen( $question ) > 60 ? '...' : '' );
	}

	/**
	 * Handle: Get insight history.
	 */
	public static function handle_get_insight_history(): void {
		if ( ! self::verify() ) {
			return;
		}

		wp_send_json_success( array(
			'history' => array_reverse( SILC_WIA_Storage::get_history() ),
		) );
	}

	/**
	 * Handle: Clear insight history.
	 */
	public static function handle_clear_insight_history(): void {
		if ( ! self::verify() ) {
			return;
		}

		SILC_WIA_Storage::clear_history();

		wp_send_json_success( array(
			'message' => __( 'Insight history cleared.', 'store-insights-for-woocommerce' ),
		) );
	}

	/**
	 * Handle: Re-execute a stored SQL query without calling AI.
	 *
	 * POST params: result_json (JSON string of full stored result) OR
	 *              legacy individual fields: sql, type, chart_config (JSON), list_config (JSON), answer_text
	 * Response: Same structure as handle_generate_insight.
	 */
	public static function handle_execute_sql(): void {
		if ( ! self::verify() ) {
			return;
		}

		// Every value here is external input: a non-string field (result_json[],
		// sql[], chart_config[]) must be rejected before it reaches a string
		// function, which would throw a TypeError under PHP 8.
		$result_json = isset( $_POST['result_json'] ) && is_string( $_POST['result_json'] )
			? wp_unslash( $_POST['result_json'] )
			: '';

		if ( '' !== $result_json ) {
			// New path: full stored result sent as JSON.
			$stored_result = json_decode( $result_json, true );

			if ( empty( $stored_result ) || ! is_array( $stored_result ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid result data.', 'store-insights-for-woocommerce' ) ) );
				return;
			}

			require_once SILC_WIA_PATH . 'includes/class-insights.php';

			$result = SILC_WIA_Insights::re_execute_sql( $stored_result );

			if ( ! $result['success'] ) {
				wp_send_json_error( array(
					'message' => $result['error'] ?? __( 'Failed to execute SQL.', 'store-insights-for-woocommerce' ),
					'sql'     => $result['sql'] ?? '',
				) );
				return;
			}

			wp_send_json_success( $result );
			return;
		}

		// Legacy fallback: individual fields. The SQL itself stays raw — it is
		// untrusted by design and gated by SILC_WIA_SQL_Validator::validate() —
		// but it must be a string to get that far.
		$sql = isset( $_POST['sql'] ) && is_string( $_POST['sql'] ) ? wp_unslash( $_POST['sql'] ) : '';
		$sql = trim( $sql );

		if ( empty( $sql ) ) {
			wp_send_json_error( array( 'message' => __( 'No SQL query provided.', 'store-insights-for-woocommerce' ) ) );
			return;
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'answer';
		$type = in_array( $type, array( 'chart', 'list', 'answer' ), true ) ? $type : 'answer';

		$insight_data = array();

		$chart_config = isset( $_POST['chart_config'] ) && is_string( $_POST['chart_config'] )
			? json_decode( wp_unslash( $_POST['chart_config'] ), true )
			: null;

		// Only a decoded object is a usable chart config; a scalar or a broken
		// JSON string falls back to the renderer's auto-detection.
		if ( is_array( $chart_config ) ) {
			$insight_data['chart_config'] = $chart_config;
		}

		$list_config = isset( $_POST['list_config'] ) && is_string( $_POST['list_config'] )
			? json_decode( wp_unslash( $_POST['list_config'] ), true )
			: null;

		if ( is_array( $list_config ) ) {
			$insight_data['list_config'] = $list_config;
		}

		$answer_text = isset( $_POST['answer_text'] ) ? sanitize_text_field( wp_unslash( $_POST['answer_text'] ) ) : '';
		if ( ! empty( $answer_text ) ) {
			$insight_data['answer_text'] = $answer_text;
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! empty( $title ) ) {
			$insight_data['title'] = $title;
		}

		require_once SILC_WIA_PATH . 'includes/class-insights.php';

		$result = SILC_WIA_Insights::re_execute_sql( $sql, $type, $insight_data );

		if ( ! $result['success'] ) {
			wp_send_json_error( array(
				'message' => $result['error'] ?? __( 'Failed to execute SQL.', 'store-insights-for-woocommerce' ),
				'sql'     => $sql,
			) );
			return;
		}

		// Ensure title is present in the response.
		if ( empty( $result['title'] ) && ! empty( $title ) ) {
			$result['title'] = $title;
		}

		wp_send_json_success( $result );
	}
}
