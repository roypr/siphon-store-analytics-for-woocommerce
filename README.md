# Siphon Store Analytics for WooCommerce

**Natural language to data visualization for WooCommerce.**

Ask plain-English questions — _"What were my best selling products last month?"_, _"Show me pending orders"_, _"How much revenue yesterday?"_ — and get instant charts, clickable lists, and precise answers. No SQL. No exporting. No clicking through twenty admin screens.

---

## ✨ Why Siphon Store Analytics for WooCommerce?

### 🗣️ Natural Language → Data Visualization

AI bridges the gap between curiosity and code. This plugin brings that power into your WooCommerce dashboard. You run the store — the plugin writes and executes the queries. Every answer appears as a rich visualization: an interactive chart, a sortable list with clickable links, or a clean numeric answer.

### 🔒 Your Store Data Stays in Your Database

The AI **never receives your actual data**. Only database schema metadata — table names and column names — is included in the prompt. Your sales numbers, customer emails, and order details stay inside your database.

What leaves the site on a question, and nothing else:

| Sent | Not sent |
|------|----------|
| Your question, in plain text | Customer, order, product, or payment rows |
| Table and column names | Any store values returned by a query |
| Model, temperature and token limit | Any other site data |
| Your API key (authorization header) — only when you use your own key | — |

Every generated query runs against your own local database.

### 📊 Visualization Over Spreadsheets

Stop digging through admin panels and exporting CSVs. Each insight renders in one of three rich formats:

| Type | Example | Output |
|------|---------|--------|
| **Chart** | "Monthly revenue trend" | Interactive Chart.js (bar, line, pie, doughnut, horizontalBar) |
| **List** | "Top 10 customers by spending" | Clickable rows with direct links to orders, products, customers |
| **Answer** | "Total revenue yesterday" | Large formatted number with context |

---

## 🚀 Quick Start

1. Install the plugin
2. Go to **WooCommerce → Siphon Store Analytics**
3. (Optional) Set up AI in the ⚙️ Settings panel: leave the Backend on **Automatic** to use the WordPress AI Client (WordPress 7.0+ with a provider connected under Settings → Connectors), or choose **Your own API key** and paste a key
4. Start asking questions — or browse the built-in Library

**No AI configured?** The Insight Library has 30 pre-built queries that work immediately with zero configuration.

---

## 📚 Insight Library (v2.3+)

30 ready-to-execute insights — no AI, no API key, no configuration:

### 📈 Charts & Trends (10)
Best selling products · Monthly revenue trend · Order status distribution · Sales by category · Daily orders/revenue · Top products by quantity · Month vs month comparison · Weekly revenue · Revenue by day of week

### 📋 Lists & Details (12)
Top customers by spending · Recent orders · Low stock products · Pending/On-hold/Refunded orders · Out of stock products · Best selling categories · Orders with coupons · Latest customers · Never-purchased products · Top rated products

### ℹ️ Quick Answers (8)
Revenue this month/yesterday/year · Total orders · Average order value · Product count · Customer count · Tax collected

All searchable with regex. One click to execute.

---

## 🔧 Technical Details

### Architecture

```
User Question (plain English)
        │
        ▼
┌──────────────────────┐
│   AI Backend         │  ← Schema-only prompt
│   WordPress AI Client│     (table/column names)
│   or BYOK key        │
│   Returns JSON:       │
│   { sql, type,        │
│     chart_config... } │
└──────────────────────┘
        │
        ▼
┌──────────────────────┐
│   SQL Validator      │  ← SELECT-only whitelist
│   Table whitelist    │     DML/DDL blocked
│   Column blocklist   │     Multi-statement blocked
└──────────────────────┘
        │
        ▼
┌──────────────────────┐
│   wpdb Execution     │  ← Runs locally
│   Row data stays     │
│   on your server     │
└──────────────────────┘
        │
        ▼
┌──────────────────────┐
│   Insight Renderer   │  ← Chart.js / List / Answer
│   Admin links        │
│   Value formatting   │
└──────────────────────┘
```

### Security

- **SELECT-only** — No INSERT, UPDATE, DELETE, DROP, ALTER, or multi-statement queries
- **Table whitelist** — Only known WooCommerce and WordPress core tables allowed
- **Credential columns blocked** — `user_pass`, `user_activation_key`, `post_password` and session-token meta are stripped from the schema and rejected by the validator
- **Capability check** — `manage_woocommerce` required for all AJAX endpoints
- **Nonce verification** — All requests validated with WordPress nonces
- **HTTPS required** — The API key and prompt are only sent over HTTPS; plain HTTP is accepted only for local endpoints (localhost, private ranges, `.local`/`.test`, single-label hosts)
- **No result rows stored** — History keeps the question, SQL and display config, and re-runs the query when an entry is opened; the rendered answer is cached for the configured TTL only
- **Secrets not autoloaded** — The settings option (API key) and the history option are excluded from the autoloaded options blob
- **Clean uninstall** — Settings, history, cached results and legacy options are removed when the plugin is deleted

### AI backends — WordPress AI Client and BYOK

Two ways to reach a model, chosen by the **Backend** setting:

- **WordPress AI Client** (default on WordPress 7.0+, `includes/class-ai-service-core.php`) — `wp_ai_client_prompt()` builds the same prompt, WordPress routes it to the provider connected under Settings → Connectors, and the plugin stores no API key. Guarded with `function_exists()` + `wp_supports_ai()`, so WordPress 6.5–6.x and sites with `WP_AI_SUPPORT` disabled simply fall through.
- **Your own API key** (`includes/class-api.php`) — direct calls to a provider you configure. Kept as a first-class fallback: it is the only option before WordPress 7.0, for self-hosted models, and when AI is disabled site-wide.

`includes/class-ai-service.php` routes `generate_insight()` and `test_connection()` to the active backend and adds the backend, model preference, temperature mode and reasoning effort to the cache key, so both paths share the SQL validation, execution, rendering and history code unchanged.

Both backends use the same Model, Temperature and Reasoning effort settings, with one difference in meaning:

- **Model** — BYOK sends it as `model`; the AI Client passes it to `using_model_preference()`. A preference, not a requirement: an id the connected provider does not offer is ignored in favour of one of its own models, and an empty value means "no preference" on that path. Read with `SILC_WIA_API::get_model_preference()` so the BYOK endpoint normalisation cannot discard a valid id from another connector.
- **Temperature** — `temperature_mode` decides whether the value is sent at all: `Automatic` (BYOK: only to models known to accept it; AI Client: never, because requesting it excludes every reasoning model from discovery), `Always`, `Never`.
- **Reasoning effort** — `Provider default` or one of `none / minimal / low / medium / high / xhigh / max`. Sent as `reasoning: { effort: … }` through the AI Client (OpenAI Responses shape) and as `reasoning_effort` on the BYOK path, never to Claude endpoints.

Because provider support varies per model, the plugin does not attempt to map capabilities: a rejected temperature or reasoning level is dropped and the request retried (twice at most), and the connection test names the setting it had to leave out.

### BYOK — Bring Your Own Key

Ships with an endpoint registry for the common providers, plus a Custom option for
any other OpenAI-compatible endpoint:

- **OpenAI** — `api.openai.com` (`max_completion_tokens`)
- **Claude / Anthropic** — `api.anthropic.com`, native Messages API (`/v1/messages`, `x-api-key`)
- **OpenRouter** — `openrouter.ai/api/v1`
- **DeepSeek** — `api.deepseek.com`
- **Groq** — `api.groq.com/openai/v1`
- **Mistral** — `api.mistral.ai`
- **xAI / Grok** — `api.x.ai`
- **Azure OpenAI / Ollama / LocalAI / vLLM / LM Studio** — Custom endpoint

The completion-limit parameter is chosen per provider (`max_tokens` vs
`max_completion_tokens`); if an endpoint rejects the choice, the request is
retried once with the other name.

### Reasoning Model Support

Auto-detects reasoning models (o-series, GPT-5/6, DeepSeek, Gemini thinking, Claude extended thinking) and adjusts parameters: omits temperature, keeps Claude's thinking budget inside the non-streaming ceiling, and allocates an appropriate token budget. You can also ask for a specific **Reasoning effort** level; if the model does not offer it, the error names the levels it does and the request is retried without the parameter.

### HPOS & Legacy Compatible

Dynamically discovers your active WooCommerce order storage backend. Adjusts table references automatically. Both HPOS and legacy post-based orders are fully supported.

---

## 🔌 External Services

This plugin sends nothing to the plugin author and does not use a service run by the plugin author. To turn a plain-English question into a SQL query it needs a large language model. Nothing is transmitted until you ask a question or press **Test connection**, and with no AI configured no external request is ever made — the built-in Library runs entirely on your own server.

**Which service receives the request** is decided by the **Backend** setting under WooCommerce → Siphon Store Analytics → Settings:

| Backend | Who sends it | Where the key lives |
|---------|--------------|---------------------|
| WordPress AI Client (default on WP 7.0+ when a connector exists) | WordPress core, via `wp_ai_client_prompt()` | Settings → Connectors — the plugin stores no key |
| Your own API key (BYOK) | This plugin, direct HTTPS call | This plugin's settings (option is not autoloaded) |

**Sent on every AI request:**

| Sent | Detail |
|------|--------|
| Your question | Plain text, exactly as typed |
| Schema metadata | Table names and column names only (credentials stripped) |
| Model settings | Model preference, temperature (only when sent), reasoning effort, token limit — through the WordPress AI Client the model is a preference and WordPress may pick a different one |
| Your API key | Request header — BYOK path only, so the provider bills your own account |

**Never sent:** customer, order, product or payment rows — and no query result ever leaves the site. Pressing **Test connection** sends a single fixed prompt (`Say "ok" and nothing else.`) to the same service, whichever backend is active.

The default endpoint per provider (BYOK path), and each provider's own terms. On the WordPress AI Client path the providers are the connectors on your Settings → Connectors screen, which link to their own policies:

| Provider | Endpoint | Terms | Privacy |
|----------|----------|-------|---------|
| OpenAI | `api.openai.com` | [Terms](https://openai.com/policies/terms-of-use) | [Privacy](https://openai.com/policies/privacy-policy) |
| Claude / Anthropic | `api.anthropic.com` (Messages API) | [Terms](https://www.anthropic.com/legal/consumer-terms) | [Privacy](https://www.anthropic.com/legal/privacy) |
| OpenRouter | `openrouter.ai` | [Terms](https://openrouter.ai/terms) | [Privacy](https://openrouter.ai/privacy) |
| DeepSeek | `api.deepseek.com` | [Terms](https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html) | [Privacy](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html) |
| Groq | `api.groq.com` | [Terms](https://groq.com/terms-of-use/) | [Privacy](https://groq.com/privacy-policy/) |
| Mistral | `api.mistral.ai` | [Terms](https://mistral.ai/terms) | [Privacy](https://legal.mistral.ai/terms/privacy-policy) |
| xAI (Grok) | `api.x.ai` | [Terms](https://x.ai/legal/terms-of-service) | [Privacy](https://x.ai/legal/privacy-policy) |

Choosing **Custom** points the plugin at any OpenAI-compatible endpoint — Azure OpenAI, Ollama, LocalAI, vLLM, LM Studio, a gateway or your own proxy. The question and schema metadata then go to that endpoint and nowhere else; with a local endpoint the data stays inside your own infrastructure. Plain HTTP is accepted only for local endpoints, everything else must use HTTPS.

---

## 📦 Requirements

| Component | Minimum |
|-----------|---------|
| WordPress | 6.5+ |
| WooCommerce | 8.0+ |
| PHP | 7.4+ |
| AI backend | Optional (Library works without). WordPress AI Client on WP 7.0+ with a connector, or your own API key |

---

## 🏗️ Development

```bash
# Install dependencies
npm install

# Build JS bundle
npm run build

# Lint
npm run lint

# Build the WordPress.org distribution folder (dist/)
npm run dist
```

The React dashboard is built with `@wordpress/element` and `@wordpress/components` — zero third-party UI frameworks. Chart.js is bundled as a minimal asset (MIT license).

---

## ⚠️ Known limitations

- **JS strings:** strings that come from PHP are translatable through the `silcWiaData.l10n` object (~32 keys). Prose that originates in the JS bundle itself — walkthrough steps, settings help text, empty-state copy — is still hardcoded English. Moving it to `wp.i18n` is a follow-up.
- **PHP 7.4:** `Requires PHP: 7.4` is declared, but the development environment runs PHP 8.2. A real 7.4 run is still required before submitting.
- **Free-form SQL:** the plugin's core feature is executing AI-generated SQL. The validator (`includes/class-sql-validator.php`) enforces SELECT-only, a table whitelist, and a keyword blocklist, and `execute()` documents why `$wpdb->prepare()` cannot apply. Expect a reviewer question and keep that explanation intact.

---

## 📄 License

GPL v2 or later. See [LICENSE](./LICENSE) for full text.

---

## 🔗 Links

- **Plugin URI**: [GitHub](https://github.com/roypr/siphon-store-analytics-for-woocommerce)
- **Chart.js**: [chartjs.org](https://www.chartjs.org/) (MIT)
