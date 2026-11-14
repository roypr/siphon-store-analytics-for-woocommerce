# Store Insights for WooCommerce

**Natural language to data visualization for WooCommerce.**

Ask plain-English questions — _"What were my best selling products last month?"_, _"Show me pending orders"_, _"How much revenue yesterday?"_ — and get instant charts, clickable lists, and precise answers. No SQL. No exporting. No clicking through twenty admin screens.

---

## ✨ Why Store Insights for WooCommerce?

### 🗣️ Natural Language → Data Visualization

AI bridges the gap between curiosity and code. This plugin brings that power into your WooCommerce dashboard. You run the store — the plugin writes and executes the queries. Every answer appears as a rich visualization: an interactive chart, a sortable list with clickable links, or a clean numeric answer.

### 🔒 Your Store Data Stays in Your Database

The AI **never receives your actual data**. Only database schema metadata — table names and column names — is included in the prompt. Your sales numbers, customer emails, and order details stay inside your database.

What leaves the site on a question, and nothing else:

| Sent | Not sent |
|------|----------|
| Your question, in plain text | Customer, order, product, or payment rows |
| Table and column names | Any store values returned by a query |
| Your API key (authorization header) | Any other site data |

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
2. Go to **WooCommerce → Store Insights**
3. (Optional) Add your API key in the ⚙️ Settings panel
4. Start asking questions — or browse the built-in Library

**No API key?** The Insight Library has 30 pre-built queries that work immediately with zero configuration.

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
│   AI API (BYOK)      │  ← Schema-only prompt
│   Returns JSON:       │     (table/column names)
│   { sql, type,        │
│     chart_config... } │
└──────────────────────┘
        │
        ▼
┌──────────────────────┐
│   SQL Validator      │  ← SELECT-only whitelist
│   Table whitelist    │     DML/DDL blocked
│   Function whitelist │     Multi-statement blocked
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

Auto-detects reasoning models (o-series, GPT-5/6, DeepSeek, Gemini thinking, Claude extended thinking) and adjusts parameters: omits temperature, keeps Claude's thinking budget inside the non-streaming ceiling, and allocates an appropriate token budget.

### HPOS & Legacy Compatible

Dynamically discovers your active WooCommerce order storage backend. Adjusts table references automatically. Both HPOS and legacy post-based orders are fully supported.

---

## 📦 Requirements

| Component | Minimum |
|-----------|---------|
| WordPress | 6.2+ |
| WooCommerce | 8.0+ |
| PHP | 7.4+ |
| API Key | Optional (Library works without) |

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

- **Plugin URI**: [GitHub](https://github.com/roypr/store-insights-for-woocommerce)
- **Chart.js**: [chartjs.org](https://www.chartjs.org/) (MIT)
