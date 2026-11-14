# Changelog

All notable changes to Store Insights for WooCommerce will be documented in this file.

## [3.2.0] — Nov 2026

### Security
- Credential columns are no longer exposed: `user_pass`, `user_activation_key`, `post_password` and session-token meta (`session_tokens`) are stripped from the schema sent to the AI, from the schema REST payload, and from the prompt, and the SQL validator now rejects any query that names them — including `wp_usermeta` lookups that target the session meta key as a value.
- The SQL validator now strips string literals with escape handling before the forbidden-keyword scan, so a legitimate value such as `CONCAT('Coupon #', id)` is no longer mistaken for a comment marker or keyword, while stacked statements and comment/hex payloads are still rejected.
- Changing the API URL no longer silently reuses the previously stored API key. A key is provider-specific, so saving a different endpoint without entering a new key clears it and the settings screen explains why.

### Changed
- New `SILC_WIA_Provider_Adapter` owns the provider registry (endpoint, default model, wire format, completion-limit parameter, key hints). The settings screen reads it from the server instead of carrying its own copy.
- The settings screen now selects a provider (OpenAI, Claude, OpenRouter, DeepSeek, Groq, Mistral, xAI, Custom) and fills the endpoint; Custom keeps the free-text URL field for Azure, Ollama, LocalAI, vLLM, LM Studio and any other compatible gateway.
- The completion-limit parameter is chosen per provider instead of guessed from the model name: OpenAI, OpenRouter and Groq send `max_completion_tokens`; Anthropic, DeepSeek, Mistral and xAI send `max_tokens`. If an endpoint rejects the choice, the request is retried once with the other name (temperature is dropped when the error blames it).
- Claude is supported natively: requests go to `/v1/messages` with `x-api-key` and `anthropic-version`, the system prompt is hoisted out of `messages`, response content blocks are mapped back to the OpenAI envelope, and extended thinking is requested only where the model accepts it.
- Default model ids refreshed per provider, and a stored model that cannot belong to the configured endpoint (for example a GPT id after switching to Claude) is replaced with that provider's default instead of being sent.
- The Test Connection button now goes through the same provider-aware request path as a real insight.

### Removed
- The "Function whitelist" claim in `README.md`, which did not match the validator (it blocklists, it does not whitelist functions).

## [3.1.0] — Nov 2026

### Security
- Removed the bundled `plugin-update-checker` library, the Composer autoloader, and the GitHub update-check hook. Updates now come from WordPress.org only, and the plugin makes no request to `api.github.com`.
- The API key is no longer passed to the browser through `wp_localize_script`. Only a `hasApiKey` boolean is exposed.
- Added `uninstall.php`; deactivating the plugin no longer deletes the settings or the insight history.
- Added suggested privacy policy text via `wp_add_privacy_policy_content()`.
- SQL validator: table aliases are now collected from declared `FROM`/`JOIN` aliases instead of accepting any 1–4 character token as a table name.
- SQL execution: a `LIMIT 200` clause is appended before the query runs, so unbounded queries no longer load every row into memory. The schema lookup now uses `$wpdb->prepare( 'SHOW COLUMNS FROM %i', … )`.
- Capability checks are consistent (`manage_woocommerce`) across the menu page, the AJAX endpoints, and the settings handlers.

### Fixed
- The Max Tokens setting takes effect again (`build_request_body()` had a hardcoded `16000`).
- API request timeout reduced from 600s to 90s so it no longer exceeds `max_execution_time` on shared hosts.

### Removed
- Debug `error_log()` calls that wrote the system prompt, user prompt, and the full API response to the PHP error log.
- Dead code: `handle_regen_insight()`, `ALLOWED_FUNCTIONS`, a duplicated `return`, and the unused `assets/js/chart.asset.php`.
- Regulatory compliance claims from `readme.txt` and `README.md`, replaced with factual data-flow wording.
- Bundled `vendor/`, `composer.json`, `composer.lock`, and the `Screenshots` section of the readme.

### Added
- `.distignore` and `npm run dist` for building the WordPress.org distribution folder.

## [3.1.0] - Nov 2026
- **Identity change** — Plugin identity changed from Store Insights with AI Lite to Store Insights for WooCommerce. Change made to comply with woocommerce and wp branding guideline.

## [3.0.0] — Nov 2026
- **Identity change** — Plugin identity changed from WooInsight AI to Store Insights with AI Lite. Change made to comply with woocommerce branding guideline.

## [2.5.1] — 2026

### Added
- **CTE support in SQL validation** — queries that use common table expressions (`WITH name AS (...) SELECT ...`) are now accepted. Previously these valid queries were rejected by the validator; now they pass through just like ordinary SELECT queries.

## [2.5.0] — 2026

### Added
- **WordPress 7.0.2 compatibility** — tested up to the latest WordPress release
- **WooCommerce 10.9.4 compatibility** — tested up to the latest WooCommerce release

### Changed
- **Better AI decision making** — improved schema guidance and prompt handling for more accurate queries, chart types, and answers

### Fixed
- **WooCommerce legacy compatibility** — fixed issues when running with legacy (non-HPOS) order storage
- General bug fixes across the dashboard and query pipeline

## [2.4.0] — 2025

### Added
- **Plus/minus buttons** on number fields in settings (Max Tokens, Temperature, Cache Duration) for easier adjustment
- **Hidden heading** before the React root so WordPress admin notices have an anchor point and display correctly

### Fixed
- **WP admin notices** inside the plugin screen were invisible because there was no heading element for them to anchor after — added a screen-reader-text `<h1>` before `#silc-wia-dashboard`

---

## [2.3.1] — 2025

### Fixed
- **UI/UX improvements** across the dashboard for better visual consistency
- **Bug fixes** in chart rendering and layout behavior
- Minor styling refinements for sidebar, panels, and responsive layout

---

## [2.3.0] — 2026

### Added
- **Insight Library** — 30 built-in, ready-to-execute queries that work without an AI API key
  - 10 chart insights (best sellers, trends, distributions, comparisons)
  - 12 list insights (customers, orders, inventory, categories)
  - 8 answer insights (revenue, counts, averages, tax)
  - Regex search across all library items
- `SILC_WIA_Library` PHP class for library management
- Library CSS styles (`.silc-wia-library-*`)
- Library data passed to JS via `wp_localize_script` (`libraryItems`)

### Changed
- **Guides panel renamed to Library** (sidebar icon, panel key, all labels)
- Library items execute directly via `execute_sql` AJAX — same pipeline as the Refresh button
- `renderGuidesPanel` → `renderLibraryPanel` in JS
- `GUIDE_SECTIONS` → `LIBRARY_ITEMS` in JS utils
- Sidebar icon for Library: `book` → `book-alt`
- l10n key `guides` → `library`

### Removed
- Legacy `GUIDE_SECTIONS` data structure from `utils.js`
- `renderGuidesPanel` import from `index.js`

---

## [2.0.0] — 2026

### Added
- Complete React dashboard built on `@wordpress/element` and `@wordpress/components`
- OpenAI-compatible AI pipeline with structured JSON output (SQL + type + config)
- HPOS-aware schema discovery with dynamic `SHOW COLUMNS` and hardcoded fallback
- Strict SQL validator: SELECT-only, table whitelist, function whitelist, multi-statement blocking
- Chart.js visualization: bar, line, pie, doughnut, horizontalBar, polarArea, radar
- Transform-based chart data preparation (`group_split`, `columns_to_datasets`)
- List rendering with type-specific templates (order, product, customer, coupon, generic)
- Admin link generation for orders, products, customers, coupons (HPOS-aware)
- Answer type for single numeric results with `{{column}}` template substitution
- History panel with instant replay of past insights (no re-generation)
- Inline settings panel: API URL, key, model, max tokens, temperature, cache TTL
- Refresh button to re-execute stored queries without AI call
- Sidebar navigation: SQL Details, History, Guides, Suggested Prompts, Settings
- Connection test utility
- Suggested prompts on empty state
- Collapsible sidebar with panel drawer
- Result caching with configurable TTL (transients)
- Reasoning model detection and auto-configuration (o1, o3, DeepSeek-R1, Claude Opus, Gemini Flash Thinking)
- `max_completion_tokens` support for OpenAI reasoning models
- Multi-strategy JSON parsing (direct decode, strip markdown, regex extraction)
- AJAX retry with assistant message context for reasoning models
- Plugin update checker integration (yahnis-elsts/plugin-update-checker)
- Webpack build pipeline with Babel and React JSX transform
- ESLint configuration
- Debug logging for API requests and responses

### Changed
- Plugin architecture completely rewritten from v1
- AI prompt now includes full schema context with table relationships
- Chart config uses `transform` objects instead of pre-computed data (AI never sees actual data)

---

## [1.0.0] — 2026

### Added
- Initial private release
- Basic AI-to-SQL pipeline via OpenAI-compatible API
- Chart.js integration for basic chart types
- API settings management (URL, key, model)
- SELECT-only SQL validation
- Simple result rendering (chart / text)
- Core plugin architecture and documentation
