<?php
/**
 * Insight Library
 *
 * Provides a collection of pre-built, ready-to-execute insight items.
 * Each item has the same JSON format the AI would return — sql, type,
 * chart_config, list_config, answer_text, title — so it works without
 * an active AI API connection.
 *
 * All SQL queries use only tables and columns verified against the
 * actual WooCommerce schema. Compatible with both HPOS and legacy modes.
 *
 * Items whose query is valid on both order-storage backends declare a single
 * `sql` string. An item that must read the order storage itself (wc_orders vs.
 * posts) can additionally declare `sql_hpos` and/or `sql_legacy`; get_library()
 * resolves the active backend and returns exactly one `sql` value per item, so
 * no inapplicable statement reaches the dashboard or the execute endpoint.
 *
 * @package SILC_WooInsight_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Library class.
 */
class SILC_WIA_Library {

	/**
	 * Get the full library of pre-built insight items.
	 *
	 * Each item contains:
	 *   id           — unique identifier
	 *   question     — natural language description (searchable)
	 *   sql          — the SQL SELECT query (with {prefix} placeholder)
	 *   sql_hpos     — (optional) overrides `sql` when HPOS is active
	 *   sql_legacy   — (optional) overrides `sql` when legacy storage is active
	 *   backends     — (optional) array of backends the item applies to:
	 *                  'hpos' and/or 'legacy'. Defaults to both.
	 *   type         — chart | list | answer
	 *   title        — short display title
	 *   chart_config — (chart type only) Chart.js transform config
	 *   list_config  — (list type only) column/link config
	 *   answer_text  — (answer type only) template with {{column}} placeholders
	 *
	 * `sql_hpos`, `sql_legacy` and `backends` are resolution metadata: they are
	 * consumed here and never returned, so every caller receives a single,
	 * backend-applicable `sql` statement per item.
	 *
	 * @return array
	 */
	public static function get_library(): array {
		global $wpdb;
		$p = $wpdb->prefix;

		// Backend note: every statement below queries storage-agnostic tables
		// (wc_order_stats, wc_order_product_lookup, wc_product_meta_lookup,
		// wc_customer_lookup, posts, terms, users), which WooCommerce creates
		// and populates in both HPOS and legacy mode. They therefore need only
		// a single `sql` string. Add `sql_hpos` / `sql_legacy` when a query has
		// to read the order storage itself (wc_orders vs. posts + postmeta).
		$items = array(

			// ================================================================
			// CHARTS & TRENDS (10 items)
			// ================================================================

			array(
				'id'       => 'lib_001',
				'question' => 'Best selling products by revenue this month',
				'sql'      => self::sql(
					"SELECT p.post_title AS product, SUM(opl.product_gross_revenue) AS revenue
					FROM {prefix}wc_order_product_lookup opl
					JOIN {prefix}wc_order_stats os ON os.order_id = opl.order_id
					JOIN {prefix}posts p ON p.ID = opl.product_id
					WHERE os.date_created >= DATE_FORMAT(NOW(), '%Y-%m-01')
					  AND os.status IN ('wc-completed','wc-processing')
					GROUP BY opl.product_id, p.post_title
					ORDER BY revenue DESC
					LIMIT 15"
				),
				'type'     => 'chart',
				'title'    => 'Best Selling Products This Month',
				'chart_config' => array(
					'chart_type' => 'bar',
					'title'      => 'Best Selling Products by Revenue — This Month',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'product',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			array(
				'id'       => 'lib_002',
				'question' => 'Monthly revenue trend this year',
				'sql'      => self::sql(
					"SELECT DATE_FORMAT(date_created, '%Y-%m') AS month,
					       SUM(total_sales) - SUM(tax_total) AS revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_FORMAT(NOW(), '%Y-01-01')
					  AND status IN ('wc-completed','wc-processing')
					GROUP BY DATE_FORMAT(date_created, '%Y-%m')
					ORDER BY month ASC"
				),
				'type'     => 'chart',
				'title'    => 'Monthly Revenue Trend',
				'chart_config' => array(
					'chart_type' => 'line',
					'title'      => 'Monthly Revenue — This Year',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'month',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			array(
				'id'       => 'lib_003',
				'question' => 'Order status distribution',
				'sql'      => self::sql(
					"SELECT status, COUNT(*) AS count
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
					GROUP BY status
					ORDER BY count DESC"
				),
				'type'     => 'chart',
				'title'    => 'Order Status Distribution',
				'chart_config' => array(
					'chart_type' => 'doughnut',
					'title'      => 'Order Status Distribution (Last 30 Days)',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'status',
						'value_columns' => array( 'count' ),
					),
				),
			),

			array(
				'id'       => 'lib_004',
				'question' => 'Sales by product category',
				'sql'      => self::sql(
					"SELECT t.name AS category, SUM(opl.product_gross_revenue) AS revenue
					FROM {prefix}wc_order_product_lookup opl
					JOIN {prefix}wc_order_stats os ON os.order_id = opl.order_id
					JOIN {prefix}term_relationships tr ON tr.object_id = opl.product_id
					JOIN {prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
					JOIN {prefix}terms t ON t.term_id = tt.term_id
					WHERE os.date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
					  AND os.status IN ('wc-completed','wc-processing')
					GROUP BY t.term_id, t.name
					ORDER BY revenue DESC
					LIMIT 10"
				),
				'type'     => 'chart',
				'title'    => 'Sales by Product Category',
				'chart_config' => array(
					'chart_type' => 'bar',
					'title'      => 'Sales by Category (Last 30 Days)',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'category',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			array(
				'id'       => 'lib_005',
				'question' => 'Daily orders for the past 7 days',
				'sql'      => self::sql(
					"SELECT DATE(date_created) AS day, COUNT(*) AS orders
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
					GROUP BY DATE(date_created)
					ORDER BY day ASC"
				),
				'type'     => 'chart',
				'title'    => 'Daily Orders (7 Days)',
				'chart_config' => array(
					'chart_type' => 'bar',
					'title'      => 'Daily Orders — Past 7 Days',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'day',
						'value_columns' => array( 'orders' ),
					),
				),
			),

			array(
				'id'       => 'lib_006',
				'question' => 'Top 5 products by quantity sold',
				'sql'      => self::sql(
					"SELECT p.post_title AS product, SUM(opl.product_qty) AS qty_sold
					FROM {prefix}wc_order_product_lookup opl
					JOIN {prefix}wc_order_stats os ON os.order_id = opl.order_id
					JOIN {prefix}posts p ON p.ID = opl.product_id
					WHERE os.date_created >= DATE_FORMAT(NOW(), '%Y-%m-01')
					  AND os.status IN ('wc-completed','wc-processing')
					GROUP BY opl.product_id, p.post_title
					ORDER BY qty_sold DESC
					LIMIT 5"
				),
				'type'     => 'chart',
				'title'    => 'Top 5 Products by Quantity',
				'chart_config' => array(
					'chart_type' => 'horizontalBar',
					'title'      => 'Top 5 Products by Quantity Sold — This Month',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'product',
						'value_columns' => array( 'qty_sold' ),
					),
				),
			),

			array(
				'id'       => 'lib_007',
				'question' => 'Daily revenue for the past 30 days',
				'sql'      => self::sql(
					"SELECT DATE(date_created) AS day,
					       SUM(total_sales) - SUM(tax_total) AS revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
					  AND status IN ('wc-completed','wc-processing')
					GROUP BY DATE(date_created)
					ORDER BY day ASC"
				),
				'type'     => 'chart',
				'title'    => 'Daily Revenue (30 Days)',
				'chart_config' => array(
					'chart_type' => 'line',
					'title'      => 'Daily Revenue — Past 30 Days',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'day',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			array(
				'id'       => 'lib_008',
				'question' => 'Compare this month vs last month revenue',
				'sql'      => self::sql(
					"SELECT
					  CASE
					    WHEN date_created >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 'This Month'
					    ELSE 'Last Month'
					  END AS period,
					  SUM(total_sales) - SUM(tax_total) AS revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')
					  AND status IN ('wc-completed','wc-processing')
					GROUP BY period
					ORDER BY period DESC"
				),
				'type'     => 'chart',
				'title'    => 'This Month vs Last Month',
				'chart_config' => array(
					'chart_type' => 'bar',
					'title'      => 'Revenue — This Month vs Last Month',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'period',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			array(
				'id'       => 'lib_009',
				'question' => 'Weekly revenue for the past 4 weeks',
				'sql'      => self::sql(
					"SELECT DATE_FORMAT(
					    DATE_SUB(date_created, INTERVAL WEEKDAY(date_created) DAY), '%Y-%m-%d'
					  ) AS week_start,
					  SUM(total_sales) - SUM(tax_total) AS revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
					  AND status IN ('wc-completed','wc-processing')
					GROUP BY YEARWEEK(date_created,1)
					ORDER BY YEARWEEK(date_created,1) ASC"
				),
				'type'     => 'chart',
				'title'    => 'Weekly Revenue (4 Weeks)',
				'chart_config' => array(
					'chart_type' => 'bar',
					'title'      => 'Revenue — Past 4 Weeks',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'week_start',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			array(
				'id'       => 'lib_010',
				'question' => 'Revenue by day of week',
				'sql'      => self::sql(
					"SELECT DAYNAME(date_created) AS weekday,
					       SUM(total_sales) - SUM(tax_total) AS revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_SUB(NOW(), INTERVAL 90 DAY)
					  AND status IN ('wc-completed','wc-processing')
					GROUP BY DAYOFWEEK(date_created), DAYNAME(date_created)
					ORDER BY DAYOFWEEK(date_created) ASC"
				),
				'type'     => 'chart',
				'title'    => 'Revenue by Day of Week',
				'chart_config' => array(
					'chart_type' => 'bar',
					'title'      => 'Revenue by Day of Week (Last 90 Days)',
					'transform'  => array(
						'type'         => 'columns_to_datasets',
						'label_column' => 'weekday',
						'value_columns' => array( 'revenue' ),
					),
				),
			),

			// ================================================================
			// LISTS & DETAILS (12 items)
			// ================================================================

			array(
				'id'       => 'lib_011',
				'question' => 'Top 10 customers by total spending',
				'sql'      => self::sql(
					"SELECT u.display_name AS customer, u.user_email AS email,
					       SUM(os.total_sales) AS total_spent,
					       COUNT(DISTINCT os.order_id) AS orders,
					       u.ID AS user_id
					FROM {prefix}wc_order_stats os
					JOIN {prefix}users u ON u.ID = os.customer_id
					WHERE os.status IN ('wc-completed','wc-processing')
					  AND os.customer_id > 0
					GROUP BY os.customer_id, u.ID, u.display_name, u.user_email
					ORDER BY total_spent DESC
					LIMIT 10"
				),
				'type'     => 'list',
				'title'    => 'Top 10 Customers by Spending',
				'list_config' => array(
					'title_column'    => 'customer',
					'display_columns' => array( 'customer', 'email', 'total_spent', 'orders' ),
					'link_columns'    => array( 'user_id' => 'user' ),
					'value_formats'   => array( 'total_spent' => 'currency' ),
				),
			),

			array(
				'id'       => 'lib_012',
				'question' => 'Recent orders with totals',
				'sql'      => self::sql(
					"SELECT os.order_id, os.date_created, os.status,
					       os.total_sales, os.net_total
					FROM {prefix}wc_order_stats os
					ORDER BY os.date_created DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Recent Orders',
				'list_config' => array(
					'title_column'    => 'order_id',
					'display_columns' => array( 'order_id', 'date_created', 'status', 'total_sales' ),
					'link_columns'    => array( 'order_id' => 'order' ),
					'value_formats'   => array( 'total_sales' => 'currency', 'net_total' => 'currency' ),
				),
			),

			array(
				'id'       => 'lib_013',
				'question' => 'Products low in stock',
				'sql'      => self::sql(
					"SELECT p.ID AS product_id, p.post_title AS product,
					       pml.stock_quantity, pml.stock_status
					FROM {prefix}wc_product_meta_lookup pml
					JOIN {prefix}posts p ON p.ID = pml.product_id
					WHERE p.post_status = 'publish'
					  AND pml.stock_quantity <= 5
					  AND pml.stock_quantity > 0
					ORDER BY pml.stock_quantity ASC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Low Stock Products',
				'list_config' => array(
					'title_column'    => 'product',
					'display_columns' => array( 'product', 'stock_quantity', 'stock_status' ),
					'link_columns'    => array( 'product_id' => 'product' ),
					'value_formats'   => array( 'stock_quantity' => 'number' ),
				),
			),

			array(
				'id'       => 'lib_014',
				'question' => 'Pending orders',
				'sql'      => self::sql(
					"SELECT os.order_id, os.date_created, os.total_sales
					FROM {prefix}wc_order_stats os
					WHERE os.status = 'wc-pending'
					ORDER BY os.date_created DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Pending Orders',
				'list_config' => array(
					'title_column'    => 'order_id',
					'display_columns' => array( 'order_id', 'date_created', 'total_sales' ),
					'link_columns'    => array( 'order_id' => 'order' ),
					'value_formats'   => array( 'total_sales' => 'currency' ),
				),
			),

			array(
				'id'       => 'lib_015',
				'question' => 'Out of stock products',
				'sql'      => self::sql(
					"SELECT p.ID AS product_id, p.post_title AS product,
					       pml.stock_status
					FROM {prefix}wc_product_meta_lookup pml
					JOIN {prefix}posts p ON p.ID = pml.product_id
					WHERE p.post_status = 'publish'
					  AND pml.stock_status = 'outofstock'
					ORDER BY p.post_title ASC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Out of Stock Products',
				'list_config' => array(
					'title_column'    => 'product',
					'display_columns' => array( 'product', 'stock_status' ),
					'link_columns'    => array( 'product_id' => 'product' ),
				),
			),

			array(
				'id'       => 'lib_016',
				'question' => 'Orders on hold',
				'sql'      => self::sql(
					"SELECT os.order_id, os.date_created, os.total_sales
					FROM {prefix}wc_order_stats os
					WHERE os.status = 'wc-on-hold'
					ORDER BY os.date_created DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Orders On Hold',
				'list_config' => array(
					'title_column'    => 'order_id',
					'display_columns' => array( 'order_id', 'date_created', 'total_sales' ),
					'link_columns'    => array( 'order_id' => 'order' ),
					'value_formats'   => array( 'total_sales' => 'currency' ),
				),
			),

			array(
				'id'       => 'lib_017',
				'question' => 'Best selling categories',
				'sql'      => self::sql(
					"SELECT t.name AS category, COUNT(DISTINCT opl.order_id) AS orders,
					       SUM(opl.product_qty) AS items_sold
					FROM {prefix}wc_order_product_lookup opl
					JOIN {prefix}term_relationships tr ON tr.object_id = opl.product_id
					JOIN {prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
					JOIN {prefix}terms t ON t.term_id = tt.term_id
					GROUP BY t.term_id, t.name
					ORDER BY items_sold DESC
					LIMIT 15"
				),
				'type'     => 'list',
				'title'    => 'Best Selling Categories',
				'list_config' => array(
					'title_column'    => 'category',
					'display_columns' => array( 'category', 'orders', 'items_sold' ),
					'value_formats'   => array( 'orders' => 'number', 'items_sold' => 'number' ),
				),
			),

			array(
				'id'       => 'lib_018',
				'question' => 'Orders using coupons',
				'sql'      => self::sql(
					"SELECT os.order_id, os.date_created, os.total_sales,
					       SUM(opl.coupon_amount) AS coupon_discount
					FROM {prefix}wc_order_stats os
					JOIN {prefix}wc_order_product_lookup opl ON opl.order_id = os.order_id
					WHERE opl.coupon_amount > 0
					GROUP BY os.order_id, os.date_created, os.total_sales
					ORDER BY os.date_created DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Orders with Coupons',
				'list_config' => array(
					'title_column'    => 'order_id',
					'display_columns' => array( 'order_id', 'date_created', 'total_sales', 'coupon_discount' ),
					'link_columns'    => array( 'order_id' => 'order' ),
					'value_formats'   => array( 'total_sales' => 'currency', 'coupon_discount' => 'currency' ),
				),
			),

			array(
				'id'       => 'lib_019',
				'question' => 'Latest customers by registration date',
				'sql'      => self::sql(
					"SELECT u.ID AS user_id, u.display_name, u.user_email,
					       u.user_registered
					FROM {prefix}users u
					ORDER BY u.user_registered DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Latest Customers',
				'list_config' => array(
					'title_column'    => 'display_name',
					'display_columns' => array( 'display_name', 'user_email', 'user_registered' ),
					'link_columns'    => array( 'user_id' => 'user' ),
				),
			),

			array(
				'id'       => 'lib_020',
				'question' => 'Products never purchased',
				'sql'      => self::sql(
					"SELECT p.ID AS product_id, p.post_title AS product, p.post_date
					FROM {prefix}posts p
					LEFT JOIN {prefix}wc_order_product_lookup opl ON opl.product_id = p.ID
					WHERE p.post_type = 'product'
					  AND p.post_status = 'publish'
					  AND opl.product_id IS NULL
					ORDER BY p.post_date DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Products Never Purchased',
				'list_config' => array(
					'title_column'    => 'product',
					'display_columns' => array( 'product', 'post_date' ),
					'link_columns'    => array( 'product_id' => 'product' ),
				),
			),

			array(
				'id'       => 'lib_021',
				'question' => 'Top products by average rating',
				'sql'      => self::sql(
					"SELECT p.ID AS product_id, p.post_title AS product,
					       pml.average_rating, pml.rating_count
					FROM {prefix}wc_product_meta_lookup pml
					JOIN {prefix}posts p ON p.ID = pml.product_id
					WHERE p.post_status = 'publish'
					  AND pml.average_rating > 0
					ORDER BY pml.average_rating DESC, pml.rating_count DESC
					LIMIT 15"
				),
				'type'     => 'list',
				'title'    => 'Top Rated Products',
				'list_config' => array(
					'title_column'    => 'product',
					'display_columns' => array( 'product', 'average_rating', 'rating_count' ),
					'link_columns'    => array( 'product_id' => 'product' ),
					'value_formats'   => array( 'average_rating' => 'number', 'rating_count' => 'number' ),
				),
			),

			array(
				'id'       => 'lib_022',
				'question' => 'Refunded orders',
				'sql'      => self::sql(
					"SELECT os.order_id, os.date_created, os.total_sales
					FROM {prefix}wc_order_stats os
					WHERE os.status = 'wc-refunded'
					ORDER BY os.date_created DESC
					LIMIT 20"
				),
				'type'     => 'list',
				'title'    => 'Refunded Orders',
				'list_config' => array(
					'title_column'    => 'order_id',
					'display_columns' => array( 'order_id', 'date_created', 'total_sales' ),
					'link_columns'    => array( 'order_id' => 'order' ),
					'value_formats'   => array( 'total_sales' => 'currency' ),
				),
			),

			// ================================================================
			// QUICK ANSWERS (8 items)
			// ================================================================

			array(
				'id'       => 'lib_023',
				'question' => 'Total revenue this month',
				'sql'      => self::sql(
					"SELECT SUM(total_sales) - SUM(tax_total) AS total_revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_FORMAT(NOW(), '%Y-%m-01')
					  AND status IN ('wc-completed','wc-processing')"
				),
				'type'     => 'answer',
				'title'    => 'Revenue This Month',
				'answer_text' => 'Total revenue this month: {{total_revenue}}',
			),

			array(
				'id'       => 'lib_024',
				'question' => 'Total revenue yesterday',
				'sql'      => self::sql(
					"SELECT SUM(total_sales) - SUM(tax_total) AS total_revenue
					FROM {prefix}wc_order_stats
					WHERE DATE(date_created) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
					  AND status IN ('wc-completed','wc-processing')"
				),
				'type'     => 'answer',
				'title'    => 'Revenue Yesterday',
				'answer_text' => 'Total revenue yesterday: {{total_revenue}}',
			),

			array(
				'id'       => 'lib_025',
				'question' => 'Total orders this month',
				'sql'      => self::sql(
					"SELECT COUNT(*) AS total_orders
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_FORMAT(NOW(), '%Y-%m-01')"
				),
				'type'     => 'answer',
				'title'    => 'Orders This Month',
				'answer_text' => 'Total orders this month: {{total_orders}}',
			),

			array(
				'id'       => 'lib_026',
				'question' => 'Average order value',
				'sql'      => self::sql(
					"SELECT ROUND(AVG(total_sales), 2) AS avg_order_value
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
					  AND status IN ('wc-completed','wc-processing')"
				),
				'type'     => 'answer',
				'title'    => 'Average Order Value',
				'answer_text' => 'Average order value (last 30 days): {{avg_order_value}}',
			),

			array(
				'id'       => 'lib_027',
				'question' => 'How many products do I have?',
				'sql'      => self::sql(
					"SELECT COUNT(*) AS product_count
					FROM {prefix}posts
					WHERE post_type = 'product'
					  AND post_status = 'publish'"
				),
				'type'     => 'answer',
				'title'    => 'Product Count',
				'answer_text' => 'Total published products: {{product_count}}',
			),

			array(
				'id'       => 'lib_028',
				'question' => 'Total customers count',
				'sql'      => self::sql(
					"SELECT COUNT(*) AS customer_count
					FROM {prefix}wc_customer_lookup"
				),
				'type'     => 'answer',
				'title'    => 'Customer Count',
				'answer_text' => 'Total customers: {{customer_count}}',
			),

			array(
				'id'       => 'lib_029',
				'question' => 'Total revenue this year',
				'sql'      => self::sql(
					"SELECT SUM(total_sales) - SUM(tax_total) AS total_revenue
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_FORMAT(NOW(), '%Y-01-01')
					  AND status IN ('wc-completed','wc-processing')"
				),
				'type'     => 'answer',
				'title'    => 'Revenue This Year',
				'answer_text' => 'Total revenue this year: {{total_revenue}}',
			),

			array(
				'id'       => 'lib_030',
				'question' => 'Total tax collected this month',
				'sql'      => self::sql(
					"SELECT SUM(tax_total) AS total_tax
					FROM {prefix}wc_order_stats
					WHERE date_created >= DATE_FORMAT(NOW(), '%Y-%m-01')
					  AND status IN ('wc-completed','wc-processing')"
				),
				'type'     => 'answer',
				'title'    => 'Tax Collected This Month',
				'answer_text' => 'Total tax collected this month: {{total_tax}}',
			),

		);

		return self::resolve_backend_items( $items );
	}

	/**
	 * Load the schema class when this class is used outside the plugin bootstrap.
	 *
	 * @return bool True when SILC_WIA_Woo_Schema is available.
	 */
	private static function load_schema(): bool {
		if ( class_exists( 'SILC_WIA_Woo_Schema' ) ) {
			return true;
		}

		$schema_file = defined( 'SILC_WIA_PATH' )
			? SILC_WIA_PATH . 'includes/class-woo-schema.php'
			: __DIR__ . '/class-woo-schema.php';

		if ( file_exists( $schema_file ) ) {
			require_once $schema_file;
		}

		return class_exists( 'SILC_WIA_Woo_Schema' );
	}

	/**
	 * Detect whether HPOS is the active order storage backend.
	 *
	 * The schema class is the single source of truth for backend detection.
	 *
	 * @return bool
	 */
	private static function is_hpos_enabled(): bool {
		return self::load_schema() && SILC_WIA_Woo_Schema::is_hpos_enabled();
	}

	/**
	 * Detect whether WooCommerce Analytics data is available.
	 *
	 * The Library's order-based items read the Analytics lookup tables, so they
	 * are withheld when the feature is disabled or the tables are missing. The
	 * product/user-only items remain available.
	 *
	 * @return bool
	 */
	private static function is_analytics_available(): bool {
		return self::load_schema() && SILC_WIA_Woo_Schema::is_analytics_available();
	}

	/**
	 * Whether a resolved statement reads one of the Analytics lookup tables.
	 *
	 * @param string $sql Resolved SQL (real table prefix).
	 * @return bool
	 */
	private static function references_analytics_tables( string $sql ): bool {
		if ( ! self::load_schema() ) {
			return false;
		}

		$prefix = SILC_WIA_Woo_Schema::prefix();

		foreach ( SILC_WIA_Woo_Schema::get_analytics_table_names() as $table ) {
			if ( preg_match( '/\b' . preg_quote( $prefix . $table, '/' ) . '\b/', $sql ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an item declares a non-empty SQL statement under a given key.
	 *
	 * @param array  $item Item array.
	 * @param string $key  Key to inspect (`sql`, `sql_hpos`, or `sql_legacy`).
	 * @return bool
	 */
	private static function has_statement( array $item, string $key ): bool {
		return array_key_exists( $key, $item ) && is_string( $item[ $key ] ) && '' !== $item[ $key ];
	}

	/**
	 * Resolve each library item to the SQL for the active storage backend.
	 *
	 * Per-item resolution:
	 *   - `sql_hpos` / `sql_legacy` override the shared `sql` statement.
	 *   - `backends` restricts the item to one or more backends; an item that
	 *     does not apply to the active backend is omitted entirely. When it is
	 *     omitted, an item with a shared `sql` applies to both backends, and an
	 *     item with only variant statements applies to the backends that have
	 *     a variant.
	 *   - Items whose statement reads an Analytics lookup table are omitted
	 *     when WooCommerce Analytics is disabled or its tables are missing.
	 *
	 * The resolution metadata is removed from the returned item, so callers
	 * (and the localized JS payload) only ever receive one `sql` string that
	 * matches the active schema.
	 *
	 * @param array $items Library items as declared in get_library().
	 * @return array Items applicable to the active backend.
	 */
	private static function resolve_backend_items( array $items ): array {
		$backend             = self::is_hpos_enabled() ? 'hpos' : 'legacy';
		$variant             = 'hpos' === $backend ? 'sql_hpos' : 'sql_legacy';
		$analytics_available = self::is_analytics_available();
		$resolved            = array();

		foreach ( $items as $item ) {
			$backends = is_array( $item['backends'] ?? null ) ? $item['backends'] : null;

			if ( null === $backends ) {
				if ( self::has_statement( $item, 'sql' ) ) {
					// A shared statement runs on either backend.
					$backends = array( 'hpos', 'legacy' );
				} else {
					// No shared statement: derive support from the variants present.
					$backends = array();
					if ( self::has_statement( $item, 'sql_hpos' ) ) {
						$backends[] = 'hpos';
					}
					if ( self::has_statement( $item, 'sql_legacy' ) ) {
						$backends[] = 'legacy';
					}
				}
			}

			if ( ! in_array( $backend, $backends, true ) ) {
				continue;
			}

			// Prefer the active backend's variant over the shared statement.
			$sql = self::has_statement( $item, $variant ) ? $item[ $variant ] : ( $item['sql'] ?? '' );

			unset( $item['backends'], $item['sql_hpos'], $item['sql_legacy'] );

			// No usable statement for the active backend means the item cannot
			// run here, so it must not be sent downstream.
			if ( ! is_string( $sql ) || '' === $sql ) {
				continue;
			}

			// Order-based items need the Analytics lookup tables. Withhold them
			// when Analytics is disabled or its tables are missing; the admin
			// notice explains why and how to enable it.
			if ( ! $analytics_available && self::references_analytics_tables( $sql ) ) {
				continue;
			}

			$item['sql'] = $sql;
			$resolved[]  = $item;
		}

		return $resolved;
	}

	/**
	 * Replace {prefix} placeholder with the actual WordPress table prefix.
	 *
	 * @param string $sql SQL with {prefix} placeholder.
	 * @return string SQL with real prefix.
	 */
	public static function sql( string $sql ): string {
		global $wpdb;
		return str_replace( '{prefix}', $wpdb->prefix, trim( $sql ) );
	}

	/**
	 * Get library items ready for JS consumption.
	 *
	 * get_library() already resolves `sql_hpos` / `sql_legacy` against the
	 * active order storage backend, so the returned items carry exactly one
	 * applicable `sql` statement each.
	 *
	 * @return array
	 */
	public static function get_library_for_js(): array {
		return self::get_library();
	}
}
