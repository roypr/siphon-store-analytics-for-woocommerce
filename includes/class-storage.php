<?php
/**
 * Option storage for insight history.
 *
 * Two rules drive this class:
 *
 * 1. Nothing we store is autoloaded. The settings option holds an API key and
 *    the history option holds SQL questions; autoloading either of them puts
 *    them in memory on every front-end, REST and cron request, and in every
 *    options cache and backup dump.
 * 2. History keeps the query and its presentation config, never the result
 *    rows. A stored answer can contain customer names, emails and addresses;
 *    replaying it re-executes the SQL instead (same path as a Library item).
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Insight history storage.
 */
class SILC_WIA_Storage {

	/**
	 * Option holding the insight history.
	 */
	const HISTORY_OPTION = 'silc_wia_insight_history';

	/**
	 * Option recording which storage layout has been applied.
	 */
	const VERSION_OPTION = 'silc_wia_storage_version';

	/**
	 * Current storage layout version.
	 */
	const STORAGE_VERSION = '2';

	/**
	 * How many history entries to keep.
	 */
	const HISTORY_LIMIT = 10;

	/**
	 * Options already checked for an autoload fix during this request.
	 *
	 * @var array<string, bool>
	 */
	private static $autoload_checked = array();

	// ----------------------------------------------------------------------- //
	//  HISTORY
	// ----------------------------------------------------------------------- //

	/**
	 * Get the stored insight history.
	 *
	 * @return array
	 */
	public static function get_history(): array {
		$history = get_option( self::HISTORY_OPTION, array() );

		return is_array( $history ) ? $history : array();
	}

	/**
	 * Append one entry to the history and persist it.
	 *
	 * The caller supplies the entry; only the newest HISTORY_LIMIT entries are
	 * kept.
	 *
	 * @param array $entry History entry (no result rows).
	 * @return void
	 */
	public static function append_history( array $entry ): void {
		$history   = self::get_history();
		$history[] = $entry;

		if ( count( $history ) > self::HISTORY_LIMIT ) {
			$history = array_slice( $history, -self::HISTORY_LIMIT );
		}

		self::save( self::HISTORY_OPTION, $history );
	}

	/**
	 * Delete the stored history.
	 *
	 * @return void
	 */
	public static function clear_history(): void {
		delete_option( self::HISTORY_OPTION );
	}

	// ----------------------------------------------------------------------- //
	//  OPTION WRITES
	// ----------------------------------------------------------------------- //

	/**
	 * Write an option without ever autoloading it.
	 *
	 * add_option() fixes the autoload flag when the option is created; an
	 * existing option can flip back to autoloaded on WordPress 6.6+ (where
	 * update_option() re-evaluates autoload heuristically), so the flag is
	 * re-asserted after each write.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public static function save( string $option, $value ): void {
		if ( ! add_option( $option, $value, '', 'no' ) ) {
			update_option( $option, $value );
		}

		self::disable_autoload( $option );
	}

	/**
	 * Force an option to stay out of the autoloaded options blob.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function disable_autoload( string $option ): void {
		if ( isset( self::$autoload_checked[ $option ] ) ) {
			return;
		}

		self::$autoload_checked[ $option ] = true;

		if ( function_exists( 'wp_set_option_autoload_values' ) ) {
			// WordPress 6.4+.
			wp_set_option_autoload_values( array( $option => 'off' ) );
			return;
		}

		global $wpdb;

		// 'no' is the legacy spelling of "not autoloaded" and is still
		// understood by every supported version.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No option API exists for this before WP 6.4.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET autoload = 'no' WHERE option_name = %s AND autoload <> 'no'",
				$option
			)
		);
	}

	// ----------------------------------------------------------------------- //
	//  UPGRADE
	// ----------------------------------------------------------------------- //

	/**
	 * Apply storage-rule changes once per site.
	 *
	 * Runs on plugins_loaded and exits after a single autoloaded read, so the
	 * cost when there is nothing to do is one option lookup.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( self::STORAGE_VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		// 1. Drop result rows that older versions wrote into the history.
		$history = self::get_history();
		$clean   = array();

		foreach ( $history as $entry ) {
			if ( is_array( $entry ) ) {
				$clean[] = self::strip_stored_rows( $entry );
			}
		}

		self::save( self::HISTORY_OPTION, $clean );

		// 2. Keep our options out of the autoloaded options blob.
		self::disable_autoload( SILC_WIA_Admin::SETTINGS_OPTION );

		// 3. Remove an option that no code path reads any more.
		delete_option( 'silc_wia_query_history' );

		update_option( self::VERSION_OPTION, self::STORAGE_VERSION );
	}

	/**
	 * Reduce a history entry stored by an older version to metadata only.
	 *
	 * Those entries carried the rendered result payload (rows, chart data,
	 * formatted answer). The presentation config they kept was already
	 * post-render, so it cannot be replayed; the entry falls back to the
	 * default rendering of its result type instead.
	 *
	 * @param array $entry Stored history entry.
	 * @return array
	 */
	private static function strip_stored_rows( array $entry ): array {
		$keep = array( 'id', 'question', 'title', 'type', 'sql', 'sql_time_ms', 'rows_returned', 'time' );
		$out  = array();

		foreach ( $keep as $key ) {
			if ( array_key_exists( $key, $entry ) ) {
				$out[ $key ] = $entry[ $key ];
			}
		}

		return $out;
	}
}
