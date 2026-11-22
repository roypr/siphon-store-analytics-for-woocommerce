=== Siphon Store Analytics for WooCommerce ===
Contributors: siphontech
Tags: woocommerce, analytics, reports, ai, charts
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 3.3.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Source code: https://github.com/roypr/siphon-store-analytics-for-woocommerce

Ask plain-English questions about your WooCommerce store and get instant charts, lists, and answers — no SQL or coding needed.

== Description ==

**Siphon Store Analytics for WooCommerce** turns natural language into data visualizations. Type a question like _"What were my best selling products last month?"_ or _"Show me pending orders"_ and get back beautiful charts, sortable lists, and precise answers — all pulled directly from your WooCommerce database.

= Three Pillars That Set It Apart =

**🗣️ Natural Language to Data Visualization**  
AI bridges the gap between asking a question and writing code. This plugin brings that power into your WooCommerce admin. No memorizing table names, no crafting SQL by hand — just ask, and the answer appears as a chart, a list, or a single highlighted number. You run the store; the plugin runs the queries.

**🔒 Your Store Data Stays in Your Database**
Only database schema metadata (table names and column names) is included in the prompt sent to the AI provider you configure, over HTTPS. The AI never receives customer names, email addresses, order totals, or any other row-level data. Every query the plugin runs executes against your own local database.

**📊 Visualization That Replaces Spreadsheet Diving**  
Stop clicking through endless admin screens and exporting CSVs. A question produces a result in one of three rich formats: an interactive Chart.js visualization (bar, line, doughnut, pie), a clickable list with direct links to orders/products/customers, or a clean numeric answer. Insights that used to take 20 minutes of digging take 5 seconds of typing.

= How It Works =

1. Choose how the plugin reaches an AI model: the **WordPress AI Client** (WordPress 7.0+, connect a provider under Settings → Connectors) or your own API key (OpenAI, Claude, OpenRouter, DeepSeek, Groq, Mistral, xAI, or any OpenAI-compatible endpoint such as Azure, Ollama, LocalAI or vLLM)
2. Ask a question in plain English
3. The AI generates a structured JSON response containing a SQL query and display configuration
4. The SQL runs against your local WooCommerce database — securely validated and restricted to SELECT-only
5. Results render instantly as a chart, list, or answer

= Features =

* **Full AI Pipeline** — Natural language → SQL → execute → visualize, all in one click
* **WordPress AI Client (WordPress 7.0+)** — The default backend on modern WordPress. Connect a provider once under Settings → Connectors and the plugin generates insights without storing any API key of its own
* **30 Built-In Library Queries** — Works out of the box without an API key. Browse pre-built insights for revenue, orders, customers, inventory, and more
* **BYOK (Bring Your Own Key)** — Kept as the fallback and as the only path on older WordPress, for self-hosted models and when AI is disabled site-wide: OpenAI, Claude (Anthropic), OpenRouter, DeepSeek, Groq, Mistral, xAI, plus Azure OpenAI, Ollama, LocalAI, vLLM and any OpenAI-compatible endpoint
* **Reasoning Model Support** — Detects and handles reasoning models (o-series, GPT-5/6, DeepSeek, Claude extended thinking) with correct parameter mapping; a Reasoning effort setting lets you choose how hard the model thinks
* **Auto-Detected Schema** — Dynamically discovers your WooCommerce tables and columns, HPOS-aware
* **Strict SQL Validation** — SELECT-only whitelist; all DML/DDL statements blocked; every table reference checked against a whitelist of known WooCommerce and WordPress tables
* **Credential Columns Never Exposed** — Password hashes, activation keys and session token meta are removed from the schema sent to the AI and are rejected by the validator
* **Cache Layer** — Configurable result caching reduces duplicate API calls
* **History Panel** — Revisit past insights without regenerating
* **Refresh Button** — Re-execute a stored query against live data without calling the AI again
* **Settings Panel** — Pick a backend and provider, then set key, model (a preference on both backends), max tokens, temperature and whether it is sent at all, reasoning effort, and cache TTL inline
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
* The API key is stored in your site database (in an option that is not autoloaded) and is never printed into the dashboard page. When the WordPress AI Client backend is selected, the plugin stores no API key at all — the connector set up under Settings → Connectors handles it
* Requests to the AI provider go to the API URL you enter and must use HTTPS; plain HTTP is only accepted for local endpoints such as `http://localhost:11434/v1`. No other external requests are made
* Generated insights keep the question, the SQL and the display settings so they can be re-run — the result rows are not stored. The rendered answer is cached for the duration set under Cache Duration
* Uninstalling removes the settings, the history, the cached results and the legacy options this plugin created

== External services ==

This plugin does not send anything to the plugin author and does not use a service run by the plugin author. To turn a plain-English question into a SQL query it needs a large language model. Nothing is transmitted until you either ask a question or press **Test connection**, and if no AI is configured at all no external request is made (the built-in Library runs entirely on your own server).

= Which service is contacted =

The **Backend** setting under WooCommerce → Siphon Store Analytics → Settings decides, and the plugin preselects the first option automatically:

* **WordPress AI Client** (default on WordPress 7.0+ when a connector is set up) — WordPress itself sends the request, to whichever provider is connected under **Settings → Connectors** (OpenAI, Anthropic, Google and any other connector registered on your site). This plugin stores no API key on this path. The connector's own terms of service and privacy policy apply and are shown on that screen.
* **Your own API key (BYOK)** — the request goes directly to the endpoint saved in Settings, using the key saved in Settings. Used on WordPress versions before 7.0, for self-hosted endpoints, and whenever you prefer it.

You can switch between them at any time; the setting only affects future requests.

= What is sent, and when =

On each AI request (that is, once per question you ask), the following is sent to the AI service selected above:

* **Your question**, as plain text.
* **Database schema metadata only** — the names of your WooCommerce and WordPress tables and their column names. Password hashes, activation keys and session-token meta are removed from the schema before it is sent. No customer, order, product or payment records are included.
* **The model preference, temperature, reasoning effort and token limit** from Settings. Through the WordPress AI Client the model is only a preference (WordPress picks from the connected provider, and ignores an id that provider does not offer) and no temperature is sent unless you explicitly choose "Always"; reasoning effort is passed as the provider's own reasoning option. With your own key the model id, temperature (when sent) and reasoning effort are forwarded to your endpoint.
* **Your API key**, as a request header — only on the "your own API key" path, so that provider can authenticate the call and bill your own account.

Query results never leave your site: every generated query is executed against your own database and only the rendered chart, list or number is shown in your admin.

Pressing **Test connection** in Settings sends one short fixed prompt — `Say "ok" and nothing else.` — to the same service, so the configuration can be checked before you rely on it.

= Providers you can choose =

On the **WordPress AI Client** path, the providers available are the ones shown under Settings → Connectors on your site (WordPress ships with OpenAI, Anthropic and Google connectors). Each connector links to its own terms of service and privacy policy on that screen, and the data sent is the list above.

On the **Your own API key** path, the plugin ships with the default endpoints below. Each one is contacted only if you select that provider and save your own API key for it.

* **OpenAI** — `api.openai.com`. Terms of use: https://openai.com/policies/terms-of-use — Privacy policy: https://openai.com/policies/privacy-policy
* **Claude / Anthropic** — `api.anthropic.com` (native Messages API). Terms of service: https://www.anthropic.com/legal/consumer-terms — Privacy policy: https://www.anthropic.com/legal/privacy
* **OpenRouter** — `openrouter.ai`. Terms of service: https://openrouter.ai/terms — Privacy policy: https://openrouter.ai/privacy
* **DeepSeek** — `api.deepseek.com`. Terms of use: https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html — Privacy policy: https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html
* **Groq** — `api.groq.com`. Terms of use: https://groq.com/terms-of-use/ — Privacy policy: https://groq.com/privacy-policy/
* **Mistral** — `api.mistral.ai`. Terms of service: https://mistral.ai/terms — Privacy policy: https://legal.mistral.ai/terms/privacy-policy
* **xAI (Grok)** — `api.x.ai`. Terms of service: https://x.ai/legal/terms-of-service — Privacy policy: https://x.ai/legal/privacy-policy

The data each of these providers receives is exactly the list above, and their own terms and privacy policies govern what they do with it.

= Self-hosted and custom endpoints =

You can also choose **Custom** and enter the URL of any OpenAI-compatible endpoint — Azure OpenAI, Ollama, LocalAI, vLLM, LM Studio, a gateway or your own proxy. In that case the question and the schema metadata go to that endpoint and nowhere else. If the endpoint runs on your own machine or network (for example `http://localhost:11434/v1`), the data does not leave your infrastructure. Plain HTTP is accepted only for such local endpoints; every other endpoint must use HTTPS.

== Requirements ==

* WordPress 6.5+
* WooCommerce 8.0+
* WooCommerce Analytics enabled for order-based insights, with its order lookup tables populated. The Library and order-level insights read `wc_order_stats`, `wc_order_product_lookup`, `wc_order_tax_lookup`, `wc_order_coupon_lookup` and `wc_customer_lookup`, which WooCommerce maintains in both HPOS and legacy order storage. If Analytics is disabled or its lookup tables are missing, order-based Library items are hidden; if the historical import has not run yet, they remain visible but may return no results.
* PHP 7.4+
* An AI backend (optional — the built-in Library works without one): the WordPress AI Client with a connector set up (WordPress 7.0+), or an API key from any supported provider

= Bring Your Own Key (BYOK) =

Select **Your own API key** as the Backend when you want the plugin to call a provider directly. This is the only path on WordPress versions before 7.0, the only path for self-hosted models, and the fallback when AI is disabled site-wide with `WP_AI_SUPPORT` (or the `wp_supports_ai` filter). The plugin supports the providers below, and any other OpenAI-compatible endpoint:

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

1. Upload the `siphon-store-analytics-for-woocommerce` folder to `/wp-content/plugins/` or install via the WordPress plugin directory.
2. Activate the plugin through the 'Plugins' menu.
3. Navigate to **WooCommerce → Siphon Store Analytics**.
4. Open the ⚙️ **Settings** panel in the sidebar.
5. Leave the Backend on **Automatic** to use the WordPress AI Client (WordPress 7.0+ with a provider connected under Settings → Connectors), or pick **Your own API key** and enter your API URL and key. Skipping this is fine too — the built-in Library works without any AI.
6. Start asking questions or browse the Library.

== Frequently Asked Questions ==

= Do I need an API key? =

No. There are two ways to give the plugin an AI model, and the built-in Library provides 30 pre-built insights that need neither.

* **WordPress 7.0+**: connect a provider once under **Settings → Connectors** and leave the Backend setting on Automatic. The plugin stores no API key of its own.
* **Any WordPress version**: select **Your own API key** as the Backend and paste a key from OpenAI, Claude, OpenRouter, DeepSeek, Groq, Mistral, xAI or any OpenAI-compatible endpoint.

= Why is the WordPress AI Client still greyed out or failing? =

The WordPress AI Client exists only on WordPress 7.0 and later, needs at least one provider connected under **Settings → Connectors**, and is skipped when the site disables AI (`define( 'WP_AI_SUPPORT', false );` or the `wp_supports_ai` filter). In any of those cases the plugin falls back to your own API key — make sure one is saved and the Backend is set to Automatic or "Your own API key". The connection test in Settings reports which backend answered.

= Which AI models work best? =

Any current chat model works. Each provider's default model is preselected in Settings and can be replaced with any model id the provider lists. Smaller/faster models are cheapest; larger models produce more accurate SQL for complex questions. Reasoning models (o-series, GPT-5/6, DeepSeek, Claude extended thinking) are supported with the correct parameter set for each provider.

= Is my store data sent to the AI? =

**No.** Only database schema metadata (table names and column names/types) is included in the prompt. The AI never sees actual customer names, order totals, email addresses, or any row-level data.

= What happens to failed queries? =

Invalid SQL is caught by the validator before execution. Empty results show a friendly message. Errors are displayed inline so you can refine your question.

= Does this work with HPOS? =

Yes. Order-level insights read the WooCommerce Analytics lookup tables (`wc_order_stats`, `wc_order_product_lookup`, `wc_customer_lookup`), which WooCommerce populates in both HPOS and legacy post-based order storage. The plugin also detects the active order storage backend when building the schema it sends to the AI, and can supply backend-specific SQL for any item that needs to read the order storage directly.

= Why are some Library items missing? =

The Library hides items that need the WooCommerce Analytics lookup tables when Analytics is disabled or when those tables are missing. Enable WooCommerce Analytics under WooCommerce > Settings > Advanced > Features. If Analytics is enabled but the tables have no order data yet, run the historical import under WooCommerce > Analytics > Settings. The dashboard shows a notice on the Siphon Store Analytics page when this applies.

= Can I use this on a multisite? =

Yes, with per-site settings.

= What about caching? =

Results are cached (configurable TTL, default 1 hour) to avoid redundant API calls and database queries. Cache can be set to 0 to disable.

== Changelog ==

= 3.3.0 =
* Identity change — Plugin identity changed from Store Insights for WooCommerce to Siphon Store Analytics for WooCommerce
* 🤖 AI now uses the WordPress AI Client by default on WordPress 7.0+ — connect a provider under Settings → Connectors and no API key is stored by the plugin
* 🔑 Bring-your-own-key is kept as a fallback, not removed: still the only path on older WordPress, for self-hosted models (Ollama, LocalAI, vLLM, LM Studio) and when AI is disabled with `WP_AI_SUPPORT`
* 🎛️ New **Backend** selector in Settings (Automatic / WordPress AI Client / Your own API key); the connection test now probes whichever backend is active
* 🧠 Both backends send the same prompt and reuse the same JSON parser, including the one-shot "return only JSON" correction retry
* 🗄️ The insight cache key now includes the active backend, so switching backend, provider or model can never serve an answer generated by the old one
* 📊 Chart.js upgraded to 4.6.1; every chart type re-tested (bar, line, pie, doughnut, horizontalBar, radar, polarArea)
* 🐛 Horizontal bar charts render horizontally again — Chart.js 3+ draws them as a bar chart with the category axis flipped, so the "Top 5 products by quantity sold" Library item was showing vertical bars
* 🐛 Radar and polar area charts no longer draw two stray cartesian axes behind the chart
* 🔌 "External services" section now covers both backends — the WordPress AI Client/Connectors path and the direct-provider path — with what is sent, when, and where each provider's terms and privacy policy are published
* 📝 Suggested privacy policy text now describes the connection test, the settings that are sent, the provider list and the WordPress AI Client path
* 📝 Installation steps, FAQ entries, backend troubleshooting, menu name and the source code link updated
* 🧹 The "WooCommerce is required" notice now only appears on the Plugins screen, not on every admin page
* 🧹 Uninstalling also removes the saved backend choice

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
* Identity change — Plugin identity changed from Store Insights with AI Lite to Store Insights for WooCommerce. Change made to comply with woocommerce and wp branding guideline.
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

= 3.0.0 =
* Identity change — Plugin identity changed from WooInsight AI to Store Insights with AI Lite. Change made to comply with woocommerce branding guideline.

= 2.5.1 =
* 🧩 SQL validation now accepts common table expressions (CTEs) — queries using `WITH ... AS (...)` are no longer rejected

= 2.5.0 =
* 🐛 Bug fixes across the dashboard and query pipeline
* 🧩 Improved WooCommerce legacy (non-HPOS) compatibility
* 🧠 Better AI decision making for more accurate queries and visualizations
* ✅ Tested with WordPress 7.0.2 and WooCommerce 10.9.4

= 2.4.0 =
* ✨ Plus/minus buttons on the number fields in Settings (Max Tokens, Temperature, Cache Duration)
* 🐛 WordPress admin notices on the plugin screen are now visible — a screen-reader heading gives them an anchor point

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
