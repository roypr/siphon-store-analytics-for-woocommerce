<?php
/**
 * WooCommerce Database Schema Mapping
 *
 * Provides a comprehensive map of WooCommerce database tables, columns,
 * relationships, post types, and meta keys. Used for AI prompt context
 * and SQL validation (whitelisting).
 *
 * Attempts dynamic schema discovery first (SHOW COLUMNS) and falls back
 * to a hardcoded reference when the table doesn't exist yet.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce schema class.
 */
class SILC_WIA_Woo_Schema {

	/**
	 * Cache group for dynamic schema.
	 */
	const CACHE_GROUP = 'silc_wia_schema';

	/**
	 * Columns that must never be exposed to the AI.
	 *
	 * These are password hashes, activation keys, and similar credential
	 * material that serve no legitimate purpose in read-only analytics
	 * queries. They are stripped from every schema payload sent to the AI
	 * (and from the admin schema endpoint), and the SQL validator rejects any
	 * query that names them.
	 */
	const SENSITIVE_COLUMNS = array(
		'user_pass',
		'user_activation_key',
		'post_password',
	);

	/**
	 * Get the WordPress table prefix.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		global $wpdb;
		return $wpdb->prefix;
	}

	// ----------------------------------------------------------------------- //
	//  CORE TABLES
	// ----------------------------------------------------------------------- //

	/**
	 * HPOS-specific table names (not present in legacy mode).
	 *
	 * @return array
	 */
	private static function get_hpos_table_names(): array {
		return array(
			'wc_orders',
			'wc_order_addresses',
			'wc_order_operational_data',
			'wc_orders_meta',
		);
	}

	/**
	 * Detect whether WooCommerce HPOS is the active order storage backend.
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}
		try {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get table names relevant for the active order storage backend.
	 *
	 * In HPOS mode, includes HPOS-specific tables. In legacy mode, excludes them
	 * so the AI only sees tables that actually exist and are authoritative.
	 *
	 * @return array
	 */
	public static function get_active_table_names(): array {
		if ( self::is_hpos_enabled() ) {
			return self::get_table_names();
		}
		return array_values( array_diff( self::get_table_names(), self::get_hpos_table_names() ) );
	}

	/**
	 * Get all known WooCommerce table names (without prefix).
	 *
	 * @return array
	 */
	public static function get_table_names(): array {
		return array(
			// Orders (HPOS / traditional).
			'wc_orders',
			'wc_order_addresses',
			'wc_order_operational_data',
			'wc_orders_meta',
			// Legacy postmeta-based orders container.
			'posts',
			'postmeta',
			// Product data.
			'wc_product_meta_lookup',
			'term_relationships',
			'term_taxonomy',
			'terms',
			'termmeta',
			// Analytics.
			'wc_order_stats',
			'wc_order_product_lookup',
			'wc_order_tax_lookup',
			'wc_order_coupon_lookup',
			'wc_customer_lookup',
			'wc_category_lookup',
			// Other WC tables.
			'wc_download_log',
			'wc_tax_rate_classes',
			'wc_reserved_stock',
			'wc_rate_limits',
			'wc_webhooks',
			'wc_product_download_directories',
			'woocommerce_order_items',
			'woocommerce_order_itemmeta',
			// Users / customers.
			'users',
			'usermeta',
		);
	}

	// ----------------------------------------------------------------------- //
	//  DYNAMIC SCHEMA DISCOVERY
	// ----------------------------------------------------------------------- //

	/**
	 * Discover column names for a table dynamically via SHOW COLUMNS.
	 *
	 * @param string $table Table name (without prefix).
	 * @return array|null Array of column names, or null if table doesn't exist.
	 */
	private static function discover_columns( string $table ): ?array {
		global $wpdb;

		$prefixed = $wpdb->prefix . $table;
		$cache_key = 'cols_' . $prefixed;
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// $table is one of the hardcoded names returned by
		// SILC_WIA_Woo_Schema::get_table_names(); %i lets $wpdb->prepare()
		// quote it as an identifier, so no string concatenation reaches SQL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Result is cached in the block below.
		$columns = $wpdb->get_results(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i', $prefixed ),
			ARRAY_A
		);

		if ( empty( $columns ) ) {
			wp_cache_set( $cache_key, null, self::CACHE_GROUP, 300 );
			return null;
		}

		$names = array_column( $columns, 'Field' );
		wp_cache_set( $cache_key, $names, self::CACHE_GROUP, 300 );
		return $names;
	}

	// ----------------------------------------------------------------------- //
	//  HARDCODED FALLBACK SCHEMAS
	// ----------------------------------------------------------------------- //

	/**
	 * Hardcoded fallback schemas for tables that may not exist yet.
	 *
	 * These are verified against the actual WooCommerce DB schema (from wp69.sql)
	 * and act as accurate reference when dynamic discovery is unavailable.
	 *
	 * @return array<string, array>  Table name (without prefix) => columns.
	 */
	private static function get_fallback_schemas(): array {
		return array(
			// ---- ORDERS (HPOS) ---- //
			'wc_orders' => array(
				'id',
				'status',
				'currency',
				'type',
				'tax_amount',
				'total_amount',
				'customer_id',
				'billing_email',
				'date_created_gmt',
				'date_updated_gmt',
				'parent_order_id',
				'payment_method',
				'payment_method_title',
				'transaction_id',
				'ip_address',
				'user_agent',
				'customer_note',
			),
			'wc_order_addresses' => array(
				'id',
				'order_id',
				'address_type',
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country',
				'email',
				'phone',
			),
			'wc_order_operational_data' => array(
				'id',
				'order_id',
				'created_via',
				'woocommerce_version',
				'prices_include_tax',
				'coupon_usages_are_counted',
				'download_permission_granted',
				'cart_hash',
				'new_order_email_sent',
				'order_key',
				'order_stock_reduced',
				'date_paid_gmt',
				'date_completed_gmt',
				'shipping_tax_amount',
				'shipping_total_amount',
				'discount_tax_amount',
				'discount_total_amount',
				'recorded_sales',
			),
			'wc_orders_meta' => array(
				'id',
				'order_id',
				'meta_key',
				'meta_value',
			),

			// ---- WP CORE (for traditional orders / products) ---- //
			'posts' => array(
				'ID',
				'post_author',
				'post_date',
				'post_date_gmt',
				'post_content',
				'post_title',
				'post_excerpt',
				'post_status',
				'comment_status',
				'ping_status',
				'post_password',
				'post_name',
				'to_ping',
				'pinged',
				'post_modified',
				'post_modified_gmt',
				'post_content_filtered',
				'post_parent',
				'guid',
				'menu_order',
				'post_type',
				'post_mime_type',
				'comment_count',
			),
			'postmeta' => array(
				'meta_id',
				'post_id',
				'meta_key',
				'meta_value',
			),

			// ---- PRODUCT META LOOKUP ---- //
			'wc_product_meta_lookup' => array(
				'product_id',
				'sku',
				'global_unique_id',
				'virtual',
				'downloadable',
				'min_price',
				'max_price',
				'onsale',
				'stock_quantity',
				'stock_status',
				'rating_count',
				'average_rating',
				'total_sales',
				'tax_status',
				'tax_class',
			),

			// ---- TAXONOMY ---- //
			'terms' => array(
				'term_id',
				'name',
				'slug',
				'term_group',
			),
			'term_taxonomy' => array(
				'term_taxonomy_id',
				'term_id',
				'taxonomy',
				'description',
				'parent',
				'count',
			),
			'term_relationships' => array(
				'object_id',
				'term_taxonomy_id',
				'term_order',
			),
			'termmeta' => array(
				'meta_id',
				'term_id',
				'meta_key',
				'meta_value',
			),

			// ---- ANALYTICS TABLES ---- //
			'wc_order_stats' => array(
				'order_id',
				'parent_id',
				'date_created',
				'date_created_gmt',
				'date_paid',
				'date_completed',
				'num_items_sold',
				'total_sales',
				'tax_total',
				'shipping_total',
				'net_total',
				'returning_customer',
				'status',
				'customer_id',
			),
			'wc_order_product_lookup' => array(
				'order_item_id',
				'order_id',
				'product_id',
				'variation_id',
				'customer_id',
				'date_created',
				'product_qty',
				'product_net_revenue',
				'product_gross_revenue',
				'coupon_amount',
				'tax_amount',
				'shipping_amount',
				'shipping_tax_amount',
			),
			'wc_order_tax_lookup' => array(
				'order_id',
				'tax_rate_id',
				'date_created',
				'shipping_tax',
				'order_tax',
				'total_tax',
			),
			'wc_order_coupon_lookup' => array(
				'order_id',
				'coupon_id',
				'date_created',
				'discount_amount',
			),
			'wc_customer_lookup' => array(
				'customer_id',
				'user_id',
				'username',
				'first_name',
				'last_name',
				'email',
				'date_last_active',
				'date_registered',
				'country',
				'postcode',
				'city',
				'state',
			),

			// ---- LEGACY ORDER TABLES ---- //
			'woocommerce_order_items' => array(
				'order_item_id',
				'order_item_name',
				'order_item_type',
				'order_id',
			),
			'woocommerce_order_itemmeta' => array(
				'meta_id',
				'order_item_id',
				'meta_key',
				'meta_value',
			),

			// ---- USERS ---- //
			'users' => array(
				'ID',
				'user_login',
				'user_pass',
				'user_nicename',
				'user_email',
				'user_url',
				'user_registered',
				'user_activation_key',
				'user_status',
				'display_name',
			),
			'usermeta' => array(
				'umeta_id',
				'user_id',
				'meta_key',
				'meta_value',
			),
		);
	}

	/**
	 * Get detailed schema for WooCommerce-relevant tables.
	 *
	 * Uses dynamic discovery (SHOW COLUMNS) when the table exists, and falls
	 * back to a hardcoded reference otherwise. Results are cached.
	 *
	 * When $active_only is true, only tables relevant to the active order
	 * storage backend are returned (HPOS tables excluded in legacy mode).
	 *
	 * @param bool $active_only Whether to return only the active backend's schemas.
	 * @return array<string, array>  Table name (without prefix) => columns.
	 */
	public static function get_table_schemas( bool $active_only = false ): array {
		$fallback = self::get_fallback_schemas();
		$schemas  = array();

		foreach ( $fallback as $table => $default_cols ) {
			$dynamic = self::discover_columns( $table );
			$cols    = null !== $dynamic ? $dynamic : $default_cols;

			// Strip sensitive columns (password hashes, activation keys) so
			// they are neither advertised to the AI nor returned by the schema
			// endpoint.
			$cols = array_values( array_diff( $cols, self::SENSITIVE_COLUMNS ) );

			$schemas[ $table ] = $cols;
		}

		if ( $active_only && ! self::is_hpos_enabled() ) {
			foreach ( self::get_hpos_table_names() as $hpos_table ) {
				unset( $schemas[ $hpos_table ] );
			}
		}

		return $schemas;
	}

	/**
	 * Discover column names for a specific table, returning cached result.
	 *
	 * @param string $table Table name (without prefix).
	 * @return array|null
	 */
	public static function get_table_columns( string $table ): ?array {
		$schemas = self::get_table_schemas();
		return $schemas[ $table ] ?? null;
	}

	/**
	 * Invalidate the dynamic schema cache.
	 */
	public static function clear_cache(): void {
		global $wpdb;
		$tables = self::get_table_names();
		foreach ( $tables as $table ) {
			wp_cache_delete( 'cols_' . $wpdb->prefix . $table, self::CACHE_GROUP );
		}
	}

	// ----------------------------------------------------------------------- //
	//  POST TYPES
	// ----------------------------------------------------------------------- //

	/**
	 * Get WooCommerce post type mappings.
	 *
	 * When $active_only is true, excludes the legacy 'shop_order' post type
	 * when HPOS is active, since orders are no longer stored as posts.
	 *
	 * @param bool $active_only Whether to return only the active backend's post types.
	 * @return array<string, string>  post_type => description
	 */
	public static function get_post_types( bool $active_only = false ): array {
		$types = array(
			'product'          => __( 'Simple or variable product', 'store-insights-for-woocommerce' ),
			'product_variation' => __( 'Product variation', 'store-insights-for-woocommerce' ),
			'shop_order'       => __( 'Order (legacy post type)', 'store-insights-for-woocommerce' ),
			'shop_order_refund' => __( 'Order refund', 'store-insights-for-woocommerce' ),
			'shop_coupon'      => __( 'Coupon / discount code', 'store-insights-for-woocommerce' ),
			'shop_subscription' => __( 'WooCommerce subscription', 'store-insights-for-woocommerce' ),
		);

		if ( $active_only && self::is_hpos_enabled() ) {
			unset( $types['shop_order'] );
		}

		return $types;
	}

	// ----------------------------------------------------------------------- //
	//  PRODUCT TYPES
	// ----------------------------------------------------------------------- //

	/**
	 * Get WooCommerce product type slugs.
	 *
	 * @return array<string, string>
	 */
	public static function get_product_types(): array {
		return array(
			'simple'   => __( 'Simple physical or digital product', 'store-insights-for-woocommerce' ),
			'grouped'  => __( 'Grouped product (collection of simples)', 'store-insights-for-woocommerce' ),
			'external' => __( 'External / affiliate product', 'store-insights-for-woocommerce' ),
			'variable' => __( 'Variable product (has variations)', 'store-insights-for-woocommerce' ),
		);
	}

	// ----------------------------------------------------------------------- //
	//  IMPORTANT META KEYS
	// ----------------------------------------------------------------------- //

	/**
	 * Key WooCommerce meta keys with descriptions.
	 *
	 * @return array<string, string>
	 */
	public static function get_meta_keys(): array {
		return array(
			// Product meta.
			'_price'              => __( 'Current product price', 'store-insights-for-woocommerce' ),
			'_regular_price'      => __( 'Regular product price', 'store-insights-for-woocommerce' ),
			'_sale_price'         => __( 'Sale price', 'store-insights-for-woocommerce' ),
			'_sku'                => __( 'Stock keeping unit', 'store-insights-for-woocommerce' ),
			'_stock'              => __( 'Stock quantity', 'store-insights-for-woocommerce' ),
			'_stock_status'       => __( 'Stock status (instock/outofstock)', 'store-insights-for-woocommerce' ),
			'_manage_stock'       => __( 'Whether stock is managed', 'store-insights-for-woocommerce' ),
			'_weight'             => __( 'Product weight', 'store-insights-for-woocommerce' ),
			'_length'             => __( 'Product length', 'store-insights-for-woocommerce' ),
			'_width'              => __( 'Product width', 'store-insights-for-woocommerce' ),
			'_height'             => __( 'Product height', 'store-insights-for-woocommerce' ),
			'_tax_status'         => __( 'Tax status', 'store-insights-for-woocommerce' ),
			'_tax_class'          => __( 'Tax class', 'store-insights-for-woocommerce' ),
			'_featured'           => __( 'Featured product flag', 'store-insights-for-woocommerce' ),
			'_visibility'         => __( 'Catalog visibility', 'store-insights-for-woocommerce' ),
			'_thumbnail_id'       => __( 'Featured image attachment ID', 'store-insights-for-woocommerce' ),
			'_product_attributes' => __( 'Serialized product attributes', 'store-insights-for-woocommerce' ),
			'_product_version'    => __( 'Product version', 'store-insights-for-woocommerce' ),
			'_virtual'            => __( 'Virtual product flag', 'store-insights-for-woocommerce' ),
			'_downloadable'       => __( 'Downloadable product flag', 'store-insights-for-woocommerce' ),
			'_download_limit'     => __( 'Download limit', 'store-insights-for-woocommerce' ),
			'_download_expiry'    => __( 'Download expiry days', 'store-insights-for-woocommerce' ),
			'_sale_price_dates_from' => __( 'Sale start date', 'store-insights-for-woocommerce' ),
			'_sale_price_dates_to'   => __( 'Sale end date', 'store-insights-for-woocommerce' ),
			'_wc_average_rating'  => __( 'Average rating', 'store-insights-for-woocommerce' ),
			'_wc_rating_count'    => __( 'Rating count', 'store-insights-for-woocommerce' ),
			'_wc_review_count'    => __( 'Review count', 'store-insights-for-woocommerce' ),

			// Order meta.
			'_order_total'        => __( 'Order total', 'store-insights-for-woocommerce' ),
			'_order_tax'          => __( 'Order tax total', 'store-insights-for-woocommerce' ),
			'_order_shipping'     => __( 'Order shipping total', 'store-insights-for-woocommerce' ),
			'_order_discount'     => __( 'Order discount total', 'store-insights-for-woocommerce' ),
			'_order_currency'     => __( 'Order currency', 'store-insights-for-woocommerce' ),
			'_payment_method'     => __( 'Payment method slug', 'store-insights-for-woocommerce' ),
			'_payment_method_title' => __( 'Payment method title', 'store-insights-for-woocommerce' ),
			'_customer_user'      => __( 'Customer user ID', 'store-insights-for-woocommerce' ),
			'_billing_first_name' => __( 'Billing first name', 'store-insights-for-woocommerce' ),
			'_billing_last_name'  => __( 'Billing last name', 'store-insights-for-woocommerce' ),
			'_billing_company'    => __( 'Billing company', 'store-insights-for-woocommerce' ),
			'_billing_address_1'  => __( 'Billing address line 1', 'store-insights-for-woocommerce' ),
			'_billing_address_2'  => __( 'Billing address line 2', 'store-insights-for-woocommerce' ),
			'_billing_city'       => __( 'Billing city', 'store-insights-for-woocommerce' ),
			'_billing_state'      => __( 'Billing state', 'store-insights-for-woocommerce' ),
			'_billing_postcode'   => __( 'Billing postcode', 'store-insights-for-woocommerce' ),
			'_billing_country'    => __( 'Billing country', 'store-insights-for-woocommerce' ),
			'_billing_email'      => __( 'Billing email', 'store-insights-for-woocommerce' ),
			'_billing_phone'      => __( 'Billing phone', 'store-insights-for-woocommerce' ),
			'_shipping_first_name' => __( 'Shipping first name', 'store-insights-for-woocommerce' ),
			'_shipping_last_name'  => __( 'Shipping last name', 'store-insights-for-woocommerce' ),
			'_shipping_company'    => __( 'Shipping company', 'store-insights-for-woocommerce' ),
			'_shipping_address_1'  => __( 'Shipping address line 1', 'store-insights-for-woocommerce' ),
			'_shipping_address_2'  => __( 'Shipping address line 2', 'store-insights-for-woocommerce' ),
			'_shipping_city'       => __( 'Shipping city', 'store-insights-for-woocommerce' ),
			'_shipping_state'      => __( 'Shipping state', 'store-insights-for-woocommerce' ),
			'_shipping_postcode'   => __( 'Shipping postcode', 'store-insights-for-woocommerce' ),
			'_shipping_country'    => __( 'Shipping country', 'store-insights-for-woocommerce' ),

			// Coupon meta.
			'discount_type'       => __( 'Coupon discount type', 'store-insights-for-woocommerce' ),
			'coupon_amount'       => __( 'Coupon amount', 'store-insights-for-woocommerce' ),
			'minimum_amount'      => __( 'Coupon minimum spend', 'store-insights-for-woocommerce' ),
			'maximum_amount'      => __( 'Coupon maximum spend', 'store-insights-for-woocommerce' ),
			'usage_count'         => __( 'Coupon usage count', 'store-insights-for-woocommerce' ),
			'individual_use'      => __( 'Individual use flag', 'store-insights-for-woocommerce' ),
			'free_shipping'       => __( 'Free shipping flag', 'store-insights-for-woocommerce' ),
			'expiry_date'         => __( 'Coupon expiry date', 'store-insights-for-woocommerce' ),
			'usage_limit'         => __( 'Coupon usage limit', 'store-insights-for-woocommerce' ),
			'usage_limit_per_user' => __( 'Coupon usage limit per user', 'store-insights-for-woocommerce' ),
		);
	}

	// ----------------------------------------------------------------------- //
	//  ORDER STATUSES
	// ----------------------------------------------------------------------- //

	/**
	 * WooCommerce order statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_order_statuses(): array {
		return array(
			'wc-pending'    => __( 'Pending payment', 'store-insights-for-woocommerce' ),
			'wc-processing' => __( 'Processing', 'store-insights-for-woocommerce' ),
			'wc-on-hold'    => __( 'On hold', 'store-insights-for-woocommerce' ),
			'wc-completed'  => __( 'Completed', 'store-insights-for-woocommerce' ),
			'wc-cancelled'  => __( 'Cancelled', 'store-insights-for-woocommerce' ),
			'wc-refunded'   => __( 'Refunded', 'store-insights-for-woocommerce' ),
			'wc-failed'     => __( 'Failed', 'store-insights-for-woocommerce' ),
			'wc-checkout-draft' => __( 'Draft / abandoned', 'store-insights-for-woocommerce' ),
		);
	}

	// ----------------------------------------------------------------------- //
	//  TAXONOMIES
	// ----------------------------------------------------------------------- //

	/**
	 * WooCommerce taxonomies.
	 *
	 * @return array<string, string>
	 */
	public static function get_taxonomies(): array {
		return array(
			'product_cat' => __( 'Product category', 'store-insights-for-woocommerce' ),
			'product_tag' => __( 'Product tag', 'store-insights-for-woocommerce' ),
			'product_type' => __( 'Product type', 'store-insights-for-woocommerce' ),
			'product_visibility' => __( 'Product visibility', 'store-insights-for-woocommerce' ),
			'product_brand' => __( 'Product brand (if WooCommerce Brands active)', 'store-insights-for-woocommerce' ),
		);
	}

	// ----------------------------------------------------------------------- //
	//  UTILITY HELPERS
	// ----------------------------------------------------------------------- //

	/**
	 * Build a schema context string for the AI prompt, scoped to the active
	 * order storage backend (HPOS or legacy). This prevents the AI from seeing
	 * tables and rules that don't apply to the current site.
	 *
	 * generate_insight() in class-api.php uses this method, which keeps the
	 * schema context in one place.
	 *
	 * @return string
	 */
	public static function get_schema_context(): string {
		$prefix  = self::prefix();
		$is_hpos = self::is_hpos_enabled();
		$lines   = array();
		$lines[] = 'Database table prefix: "' . $prefix . '"';
		$lines[] = '';
		$lines[] = '=== TABLES AND COLUMNS ===';

		foreach ( self::get_table_schemas( true ) as $table => $cols ) {
			$lines[] = $prefix . $table . ' ( ' . implode( ', ', $cols ) . ' )';
		}

		$lines[] = '';
		$lines[] = 'Sensitive columns are intentionally omitted from this schema and must never be queried:';
		$lines[] = '- ' . $prefix . 'users: user_pass and user_activation_key (password hashes / reset keys)';
		$lines[] = '- ' . $prefix . 'posts: post_password';
		$lines[] = '- ' . $prefix . 'usermeta: session_tokens and other credential meta values';

		$lines[] = '';
		$lines[] = '=== WOOCOMMERCE POST TYPES ===';
		foreach ( self::get_post_types( true ) as $pt => $desc ) {
			$lines[] = "- {$pt}: {$desc}";
		}

		$lines[] = '';
		$lines[] = '=== PRODUCT TYPES (stored in wp_term_relationships + wp_term_taxonomy with taxonomy "product_type") ===';
		foreach ( self::get_product_types() as $pt => $desc ) {
			$lines[] = "- {$pt}: {$desc}";
		}

		$lines[] = '';
		$lines[] = '=== IMPORTANT META KEYS (stored in wp_postmeta, can be joined via post_id) ===';
		foreach ( self::get_meta_keys() as $key => $desc ) {
			$lines[] = "- '{$key}': {$desc}";
		}

		$lines[] = '';
		$lines[] = '=== ORDER STATUSES ===';
		foreach ( self::get_order_statuses() as $status => $label ) {
			$lines[] = "- {$status}: {$label}";
		}

		$lines[] = '';
		$lines[] = '=== TAXONOMIES ===';
		foreach ( self::get_taxonomies() as $tax => $desc ) {
			$lines[] = "- {$tax}: {$desc}";
		}

		$lines[] = '';
		$lines[] = '=== RELATIONSHIPS ===';

		if ( $is_hpos ) {
			$lines[] = '- Products (post_type="product") are stored in wp_posts. Their meta is in wp_postmeta.';
			$lines[] = '- Orders are stored in wp_wc_orders. Addresses in wp_wc_order_addresses, operational data in wp_wc_order_operational_data.';
			$lines[] = '- Order metadata is in wp_wc_orders_meta.';
			$lines[] = '- Order line items are in wp_woocommerce_order_items and wp_woocommerce_order_itemmeta.';
			$lines[] = '- Order analytics use wp_wc_order_stats, wp_wc_order_product_lookup, wp_wc_order_tax_lookup, wp_wc_order_coupon_lookup.';
			$lines[] = '- Product-category mapping is through wp_term_relationships + wp_term_taxonomy (taxonomy="product_cat").';
			$lines[] = '- Product meta lookup is in wp_wc_product_meta_lookup (fast aggregated product data).';
			$lines[] = '- Customer data is in wp_wc_customer_lookup (for analytics).';
			$lines[] = '- Users table stores registered customers (wp_users).';
		} else {
			$lines[] = '- Products (post_type="product") are stored in wp_posts. Their meta is in wp_postmeta.';
			$lines[] = '- Orders (post_type="shop_order") are stored in wp_posts. Their meta is in wp_postmeta.';
			$lines[] = '- Legacy order items are stored in wp_woocommerce_order_items and wp_woocommerce_order_itemmeta.';
			$lines[] = '- Product-category mapping is through wp_term_relationships + wp_term_taxonomy (taxonomy="product_cat").';
			$lines[] = '- Product meta lookup is in wp_wc_product_meta_lookup (fast aggregated product data).';
			$lines[] = '- Customer data is in wp_wc_customer_lookup (for analytics).';
			$lines[] = '- Users table stores registered customers (wp_users).';
		}

		$lines[] = '';
		$lines[] = '=== RULES ===';
		$lines[] = '- Always use the table prefix in queries.';
		if ( $is_hpos ) {
			$lines[] = '- Use aliases to keep queries readable (e.g., FROM wp_wc_orders AS o).';
			$lines[] = '- For orders, filter by o.type = "shop_order".';
		} else {
			$lines[] = '- Use aliases to keep queries readable (e.g., FROM wp_posts AS p).';
			$lines[] = '- For orders, filter by p.post_type = "shop_order".';
		}
		$lines[] = '- For products, filter by p.post_type = "product".';
		$lines[] = '- Order status column value is "wc-pending", "wc-processing", "wc-completed" etc.';
		$lines[] = '- Use COALESCE for meta values that may be NULL.';
		$lines[] = '- When joining postmeta, use LEFT JOIN since meta may not exist.';
		$lines[] = '- Use aggregate functions (COUNT, SUM, AVG) for reports.';
		$lines[] = '- Use GROUP BY when aggregating with non-aggregated columns.';
		$lines[] = '- ORDER BY and LIMIT are recommended for top-N queries.';
		$lines[] = '- Use DATE() function for date comparisons.';
		$lines[] = '- For date ranges, compare against date_created_gmt or post_date.';
		$lines[] = '- Prefer wp_wc_order_stats for high-level order analytics.';
		$lines[] = '- Never select password hashes, activation keys, or session/auth meta values; those columns are not part of this schema.';

		return implode( "\n", $lines );
	}
}
