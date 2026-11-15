=== Store Insights for WooCommerce ===
Contributors: siphontech
Tags: woocommerce, analytics, reports, ai, charts
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 3.2.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Source code: https://github.com/roypr/store-insights-for-woocommerce/tree/wp-3.2.2

Ask plain-English questions about your WooCommerce store and get instant charts, lists, and answers — no SQL or coding needed.

== Description ==

**Store Insights for WooCommerce** turns natural language into data visualizations. Type a question like _"What were my best selling products last month?"_ or _"Show me pending orders"_ and get back beautiful charts, sortable lists, and precise answers — all pulled directly from your WooCommerce database.

= Three Pillars That Set It Apart =

**🗣️ Natural Language to Data Visualization**  
AI bridges the gap between asking a question and writing code. This plugin brings that power into your WooCommerce admin. No memorizing table names, no crafting SQL by hand — just ask, and the answer appears as a chart, a list, or a single highlighted number. You run the store; the plugin runs the queries.

**🔒 Your Store Data Stays in Your Database**
Only database schema metadata (table names and column names) is included in the prompt sent to the AI provider you configure, over HTTPS. The AI never receives customer names, email addresses, order totals, or any other row-level data. Every query the plugin runs executes against your own local database.

**📊 Visualization That Replaces Spreadsheet Diving**  
Stop clicking through endless admin screens and exporting CSVs. A question produces a result in one of three rich formats: an interactive Chart.js visualization (bar, line, doughnut, pie), a clickable list with direct links to orders/products/customers, or a clean numeric answer. Insights that used to take 20 minutes of digging take 5 seconds of typing.

= How It Works =

1. Install the plugin and add your API key (OpenAI, Claude, OpenRouter, DeepSeek, Groq, Mistral, xAI, or any OpenAI-compatible endpoint such as Azure, Ollama, LocalAI or vLLM)
2. Ask a question in plain English
3. The AI generates a structured JSON response containing a SQL query and display configuration
4. The SQL runs against your local WooCommerce database — securely validated and restricted to SELECT-only
5. Results render instantly as a chart, list, or answer

= Features =

* **Full AI Pipeline** — Natural language → SQL → execute → visualize, all in one click
* **30 Built-In Library Queries** — Works out of the box without an API key. Browse pre-built insights for revenue, orders, customers, inventory, and more
* **BYOK (Bring Your Own Key)** — OpenAI, Claude (Anthropic), OpenRouter, DeepSeek, Groq, Mistral, xAI, plus Azure OpenAI, Ollama, LocalAI, vLLM and any OpenAI-compatible endpoint
* **Reasoning Model Support** — Detects and handles reasoning models (o-series, GPT-5/6, DeepSeek, Claude extended thinking) with correct parameter mapping
* **Auto-Detected Schema** — Dynamically discovers your WooCommerce tables and columns, HPOS-aware
* **Strict SQL Validation** — SELECT-only whitelist; all DML/DDL statements blocked; every table reference checked against a whitelist of known WooCommerce and WordPress tables
* **Credential Columns Never Exposed** — Password hashes, activation keys and session token meta are removed from the schema sent to the AI and are rejected by the validator
* **Cache Layer** — Configurable result caching reduces duplicate API calls
* **History Panel** — Revisit past insights without regenerating
* **Refresh Button** — Re-execute a stored query against live data without calling the AI again
* **Settings Panel** — Pick a provider, then set key, model, max tokens, temperature, and cache TTL inline
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
* Password hashes, activation keys and session tokens are not included in the schema and cannot be queried
* SQL queries are validated against a strict whitelist of known tables
* Only SELECT statements are permitted — no INSERT, UPDATE, DELETE, DROP, or multi-statement queries
* All AJAX endpoints require the `manage_woocommerce` capability and a valid nonce
* The API key is stored in your site database (in an option that is not autoloaded) and is never printed into the dashboard page
* Requests to the AI provider go to the API URL you enter and must use HTTPS; plain HTTP is only accepted for local endpoints such as `http://localhost:11434/v1`. No other external requests are made
* Generated insights keep the question, the SQL and the display settings so they can be re-run — the result rows are not stored. The rendered answer is cached for the duration set under Cache Duration
* Uninstalling removes the settings, the history, the cached results and the legacy options this plugin created

= Requirements =

* WordPress 6.5+
* WooCommerce 8.0+
* WooCommerce Analytics enabled for order-based insights, with its order lookup tables populated. The Library and order-level insights read `wc_order_stats`, `wc_order_product_lookup`, `wc_order_tax_lookup`, `wc_order_coupon_lookup` and `wc_customer_lookup`, which WooCommerce maintains in both HPOS and legacy order storage. If Analytics is disabled or its lookup tables are missing, order-based Library items are hidden; if the historical import has not run yet, they remain visible but may return no results.
* PHP 7.4+
* An API key from any supported provider (optional — the built-in Library works without one)

= Bring Your Own Key (BYOK) =

The plugin supports the providers below, and any other OpenAI-compatible endpoint:

* **OpenAI** (api.openai.com)
* **Claude / Anthropic** (api.anthropic.com) — native Messages API, not an OpenAI-compatible shim
* **OpenRouter** (openrouter.ai)
* **DeepSeek** (api.deepseek.com)
* **Groq** (api.groq.com)
* **Mistral** (api.mistral.ai)
* **xAI / Grok** (api.x.ai)
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

Any current chat model works. Each provider's default model is preselected in Settings and can be replaced with any model id the provider lists. Smaller/faster models are cheapest; larger models produce more accurate SQL for complex questions. Reasoning models (o-series, GPT-5/6, DeepSeek, Claude extended thinking) are supported with the correct parameter set for each provider.

= Is my store data sent to the AI? =

**No.** Only database schema metadata (table names and column names/types) is included in the prompt. The AI never sees actual customer names, order totals, email addresses, or any row-level data.

= What happens to failed queries? =

Invalid SQL is caught by the validator before execution. Empty results show a friendly message. Errors are displayed inline so you can refine your question.

= Does this work with HPOS? =

Yes. Order-level insights read the WooCommerce Analytics lookup tables (`wc_order_stats`, `wc_order_product_lookup`, `wc_customer_lookup`), which WooCommerce populates in both HPOS and legacy post-based order storage. The plugin also detects the active order storage backend when building the schema it sends to the AI, and can supply backend-specific SQL for any item that needs to read the order storage directly.

= Why are some Library items missing? =

The Library hides items that need the WooCommerce Analytics lookup tables when Analytics is disabled or when those tables are missing. Enable WooCommerce Analytics under WooCommerce > Settings > Advanced > Features. If Analytics is enabled but the tables have no order data yet, run the historical import under WooCommerce > Analytics > Settings. The dashboard shows a notice on the Store Insights page when this applies.

= Can I use this on a multisite? =

Yes, with per-site settings.

= What about caching? =

Results are cached (configurable TTL, default 1 hour) to avoid redundant API calls and database queries. Cache can be set to 0 to disable.

== Changelog ==

= 3.2.2 =
* 🔎 WooCommerce Analytics is now a documented data prerequisite with runtime readiness checks and an admin notice
* 🧩 Order-based Library items are hidden when Analytics is disabled or its lookup tables are missing; product/user/post items remain
* 💬 Missing Analytics table errors now show an actionable message instead of a raw database error
* 📝 Readme lists the Analytics requirement, explains missing Library items, and requires WordPress 6.5
* 🔧 Translation template regenerated without build output

= 3.2.1 =
* 🔒 Insight history no longer keeps result rows — opening a past insight re-runs its query
* 🔒 The settings and history options are no longer autoloaded on every request
* 🔒 Plain-HTTP API URLs are refused (HTTPS required, except for local endpoints)
* 🔒 Uninstalling now also removes cached results and a legacy option
* 🐛 The Max Tokens setting now controls the request budget (800–8192, default 1500)
* 🐛 Refreshing a chart keeps the original chart type and transform
* 📝 Privacy text now lists what is stored locally and the HTTPS requirement

= 3.2.0 =
* 🔒 Password hashes, activation keys and session tokens are removed from the schema sent to the AI and rejected by the SQL validator
* 🔒 Changing the API URL no longer reuses the previously saved API key
* 🔌 Pick a provider (OpenAI, Claude, OpenRouter, DeepSeek, Groq, Mistral, xAI or Custom) and the endpoint is filled in for you
* 🔌 Claude is now supported natively through Anthropic's Messages API
* 🔌 The completion-limit parameter is chosen per provider (`max_tokens` vs `max_completion_tokens`) and retried if an endpoint rejects it
* 🐛 The JSON retry now sends the model's previous answer back, so it can correct its output
* 📝 Provider list and privacy wording updated

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
