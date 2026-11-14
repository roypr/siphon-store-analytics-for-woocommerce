=== Store Insights for WooCommerce ===
Contributors: siphontech
Tags: woocommerce, analytics, reports, ai, charts
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 3.1.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask plain-English questions about your WooCommerce store and get instant charts, lists, and answers — no SQL or coding needed.

== Description ==

**Store Insights for WooCommerce** turns natural language into data visualizations. Type a question like _"What were my best selling products last month?"_ or _"Show me pending orders"_ and get back beautiful charts, sortable lists, and precise answers — all pulled directly from your WooCommerce database.

= Three Pillars That Set It Apart =

**🗣️ Natural Language to Data Visualization**  
AI bridges the gap between asking a question and writing code. This plugin brings that power into your WooCommerce admin. No memorizing table names, no crafting SQL by hand — just ask, and the answer appears as a chart, a list, or a single highlighted number. You run the store; the plugin runs the queries.

**🔒 Your Store Data Stays in Your Database**
Only database schema metadata (table names and column names) is included in the prompt sent to the AI provider you configure. The AI never receives customer names, email addresses, order totals, or any other row-level data. Every query the plugin runs executes against your own local database.

**📊 Visualization That Replaces Spreadsheet Diving**  
Stop clicking through endless admin screens and exporting CSVs. A question produces a result in one of three rich formats: an interactive Chart.js visualization (bar, line, doughnut, pie), a clickable list with direct links to orders/products/customers, or a clean numeric answer. Insights that used to take 20 minutes of digging take 5 seconds of typing.

= How It Works =

1. Install the plugin and add your API key (any OpenAI-compatible provider — OpenAI, Azure, Ollama, LocalAI, vLLM, etc.)
2. Ask a question in plain English
3. The AI generates a structured JSON response containing a SQL query and display configuration
4. The SQL runs against your local WooCommerce database — securely validated and restricted to SELECT-only
5. Results render instantly as a chart, list, or answer

= Features =

* **Full AI Pipeline** — Natural language → SQL → execute → visualize, all in one click
* **30 Built-In Library Queries** — Works out of the box without an API key. Browse pre-built insights for revenue, orders, customers, inventory, and more
* **BYOK (Bring Your Own Key)** — Works with OpenAI, Azure OpenAI, Ollama, LocalAI, vLLM, and any OpenAI-compatible endpoint
* **Reasoning Model Support** — Detects and handles reasoning models (o1, o3, DeepSeek-R1) with correct parameter mapping
* **Auto-Detected Schema** — Dynamically discovers your WooCommerce tables and columns, HPOS-aware
* **Strict SQL Validation** — SELECT-only whitelist; all DML/DDL statements blocked; every table reference checked against a whitelist of known WooCommerce and WordPress tables
* **Cache Layer** — Configurable result caching reduces duplicate API calls
* **History Panel** — Revisit past insights without regenerating
* **Refresh Button** — Re-execute a stored query against live data without calling the AI again
* **Settings Panel** — Configure API URL, key, model, max tokens, temperature, and cache TTL inline
* **HPOS & Legacy Compatible** — Automatically adapts table references based on active order storage backend
* **Lightweight** — React dashboard powered by WordPress core @wordpress/element and @wordpress/components; zero third-party UI frameworks

= Use Cases =

* **Store owners** — "How much did I make yesterday?" / "Which products are running low?"
* **Marketing teams** — "Top 10 customers by spending" / "Monthly revenue trend"
* **Support agents** — "Show me pending orders" / "Find refunded orders this week"
* **Accountants** — "Total tax collected this month" / "Revenue by payment method"
* **Inventory managers** — "Products never purchased" / "Out of stock products"

= Privacy & Security =

* Only database schema metadata (table and column names) is sent to the AI provider you configure — never row-level customer, order, or payment data
* SQL queries are validated against a strict whitelist of known tables
* Only SELECT statements are permitted — no INSERT, UPDATE, DELETE, DROP, or multi-statement queries
* All AJAX endpoints require the `manage_woocommerce` capability and a valid nonce
* The API key is stored in your site database and is never printed into the dashboard page
* Requests to the AI provider go to the API URL you enter; no other external requests are made

= Requirements =

* WordPress 6.2+
* WooCommerce 8.0+
* PHP 7.4+
* An API key from any OpenAI-compatible provider (optional — the built-in Library works without one)

= Bring Your Own Key (BYOK) =

The plugin is API-agnostic. Any provider exposing an `/chat/completions` endpoint works:

* **OpenAI** (api.openai.com)
* **Azure OpenAI** (your-instance.openai.azure.com)
* **Ollama** (localhost:11434)
* **LocalAI** (self-hosted)
* **vLLM** (self-hosted)
* **LM Studio** (localhost)
* Any other OpenAI-compatible proxy or gateway

= Library — Insights Without AI =

Version 2.3 introduces the **Insight Library**: 30 hand-crafted, ready-to-execute queries that work immediately with zero configuration. Browse, search (with regex!), and click to run — exactly the same output format as the AI pipeline. Perfect for demos, evaluations, and everyday quick-checks when you don't want to spend API credits.

Categories include:
* **Charts & Trends** — Best sellers, revenue trends, status distributions, category breakdowns, weekly comparisons
* **Lists & Details** — Customers by spending, recent orders, low stock, pending/on-hold/refunded orders, top rated products
* **Quick Answers** — Revenue this month/yesterday/year, order count, AOV, product count, customer count, tax totals

== Installation ==

1. Upload the `store-insights-for-woocommerce` folder to `/wp-content/plugins/` or install via the WordPress plugin directory.
2. Activate the plugin through the 'Plugins' menu.
3. Navigate to **WooCommerce → Store Insights**.
4. Open the ⚙️ **Settings** panel in the sidebar.
5. Enter your API URL and API Key (or skip — the built-in Library works without an API).
6. Start asking questions or browse the Library.

== Frequently Asked Questions ==

= Do I need an API key? =

No. The built-in Library provides 30 pre-built insights that execute directly against your database without any AI involvement. An API key unlocks the natural-language query generator where you can ask anything in plain English.

= Which AI models work best? =

GPT-4o-mini provides excellent cost/performance. GPT-4o and Claude 3.5 Sonnet produce the most accurate SQL. For self-hosted setups, Llama 3 70B and DeepSeek-V3 work well via Ollama or vLLM. Reasoning models (o1, o3, DeepSeek-R1) are fully supported.

= Is my store data sent to the AI? =

**No.** Only database schema metadata (table names and column names/types) is included in the prompt. The AI never sees actual customer names, order totals, email addresses, or any row-level data.

= What happens to failed queries? =

Invalid SQL is caught by the validator before execution. Empty results show a friendly message. Errors are displayed inline so you can refine your question.

= Does this work with HPOS? =

Yes. The plugin auto-detects whether WooCommerce High-Performance Order Storage is active and adjusts table references accordingly. Both HPOS and legacy post-based orders are fully supported.

= Can I use this on a multisite? =

Yes, with per-site settings.

= What about caching? =

Results are cached (configurable TTL, default 1 hour) to avoid redundant API calls and database queries. Cache can be set to 0 to disable.

== Changelog ==

= 3.1.0 =
* 🔒 Removed the bundled GitHub-based update checker — updates are delivered through WordPress.org only
* 🔒 The API key is no longer printed into the dashboard page source
* 🔒 Added `uninstall.php`; deactivating the plugin no longer deletes your settings or insight history
* 🔒 Added suggested privacy policy text describing exactly what is sent to the AI provider
* 🐛 The Max Tokens setting now takes effect again
* 🐛 Query results are now capped in the database instead of after loading every row into memory
* 🐛 Table alias handling in the SQL validator is now based on declared aliases instead of token length
* 🧹 Removed debug logging that wrote prompts and full API responses to the PHP error log
* 🧹 Removed dead code and aligned capability checks with the dashboard menu
* 📝 Readme wording now describes the data flow factually

= 3.1.0 =
* Identity change — Plugin identity changed from Store Insights with AI Lite to Store Insights for WooCommerce. Change made to comply with woocommerce and wp branding guideline.

= 3.0.0 =
* Identity change — Plugin identity changed from WooInsight AI to Store Insights with AI Lite. Change made to comply with woocommerce branding guideline.

= 2.5.1 =
* 🧩 SQL validation now accepts common table expressions (CTEs) — queries using `WITH ... AS (...)` are no longer rejected

= 2.5.0 =
* 🐛 Bug fixes across the dashboard and query pipeline
* 🧩 Improved WooCommerce legacy (non-HPOS) compatibility
* 🧠 Better AI decision making for more accurate queries and visualizations
* ✅ Tested with WordPress 7.0.2 and WooCommerce 10.9.4

= 2.3.1 =
* 🐛 Bug fixes across chart rendering, SQL execution display, and responsive layout
* 🎨 Visual improvements to sidebar navigation, panel headers, and mobile breakpoints
* ⚡ Minor performance optimizations and UI polish

= 2.3.0 =
* ✨ **Insight Library** — 30 built-in queries (10 charts, 12 lists, 8 answers) that work without an API key
* 🏷️ Guides panel renamed to Library
* 🔍 Library supports regex search
* 🖱️ Library items execute directly via the refresh pipeline — no AI call needed
* 🎨 New library-specific CSS styles
* 🧹 Removed legacy GUIDE_SECTIONS data structure
* 📚 PHP class `SILC_WIA_Library` added for library management

= 2.0.0 =
* 🚀 Complete rewrite with React dashboard (@wordpress/element, @wordpress/components)
* 🧠 OpenAI-compatible AI pipeline with structured JSON output
* 🗄️ HPOS-aware schema discovery with hardcoded fallbacks
* 🔒 Strict SQL validator: SELECT-only, table whitelist, multi-statement blocking
* 🎨 Chart.js visualization: bar, line, pie, doughnut, horizontalBar, polarArea, radar
* 📋 List rendering with admin links for orders, products, customers, coupons
* 💬 Answer type for single numeric results
* 💾 History panel with instant replay of past insights
* ⚙️ Inline settings panel (API URL, key, model, tokens, temperature, cache)
* 🔄 Refresh button to re-execute stored queries without AI
* 🧩 Sidebar navigation: SQL, History, Guides, Suggested, Settings
* 🧪 Connection test utility
* 💰 Suggested prompts on empty state
* 📱 Responsive layout with collapsible sidebar

= 1.0.0 =
* Initial private release
* Basic AI-to-SQL pipeline
* Chart.js integration
* API settings management
* Documentation and core architecture

== Upgrade Notice ==

= 2.5.1 =
SQL validation now accepts queries that use common table expressions (CTEs, written as `WITH ... AS (...)`). No breaking changes — all settings, history, and library items are preserved. Recommended update.

= 2.5.0 =
Bug fixes, improved legacy WooCommerce compatibility, and smarter AI decision making. No breaking changes — all settings, history, and library items are preserved. Recommended update.

= 2.3.1 =
Bug fixes and visual improvements. No breaking changes — all existing settings, history, and library items are preserved. Recommended update for a smoother experience.

= 2.3.0 =
The Guides panel has been replaced with the new Insight Library. All functionality is backward-compatible; existing API settings and history are preserved. The Library adds 30 pre-built queries that work without an API key — ideal for demonstration and evaluation.

== Credits ==

Built by the SILC team. Uses Chart.js for visualizations (MIT license). WordPress admin UI built on @wordpress/element and @wordpress/components.
