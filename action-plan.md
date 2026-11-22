Here's the complete work plan. It covers every issue in the review email plus a verification gate, using the hybrid AI approach we agreed on.

Working decisions (lock these before starting):

- **Display name:** `Siphon Store Analytics for WooCommerce`
- **Slug / text domain / folder / main file:** `siphon-store-analytics-for-woocommerce`
- Keep internal prefixes (`SILC_WIA_*`, `silc_wia_*` options) unchanged — they don't need to match the slug and changing them would break existing installs/data.
- AI: WP AI Client = default; BYOK = fallback (kept, not removed).

> Confirm the name with a web search first. If you pick a different distinctive prefix, keep the `… for WooCommerce` suffix.

---

## Phase 0 — Ownership & decisions (do immediately, no code)

These block the review independent of code.

1. **Verify ownership** — pick one:
   - [x] Add DNS TXT record at `siphon-tech.com` root (`@`), value exactly:
     `wordpressorg-siphontech-verification`
   - [x] Confirm propagation before replying: `dig TXT siphon-tech.com` (or `nslookup -type=TXT siphon-tech.com`).
   - *Alternative:* change WP.org account email to an `@siphon-tech.com` address.
2. **Confirm the name/slug** with a search (Google/DuckDuckGo/ChatGPT) for `Siphon Store Analytics`, `Store Insights`, and `Insights for WooCommerce`. No conflicting plugin
s → proceed.
  ## New name: Siphon Store Analytics for WooCommerce
3. **Do not resubmit under a new account.**

---

## Phase 1 — Rename (display name + slug)

### 1.1 Main file
- [x] Rename `store-insights-for-woocommerce.php` → `siphon-store-analytics-for-woocommerce.php`.
- [x] Update header block:
  - `Plugin Name: Siphon Store Analytics for WooCommerce`
  - `Text Domain: siphon-store-analytics-for-woocommerce`
  - `Plugin URI: https://siphon-tech.com/siphon-store-analytics-for-woocommerce` (or just your root domain)
- [x] Update the WooCommerce-missing notice text if it still says "Store Insights for WooCommerce".

### 1.2 Text domain (all `__()` / `esc_html__()` / `_e()` calls)
Replace `'store-insights-for-woocommerce'` → `'siphon-store-analytics-for-woocommerce'` in:

- `includes/class-api.php`
- `includes/class-admin.php`
- `includes/class-ajax.php`
- `includes/class-insights.php`
- `includes/class-insight-renderer.php`
- `includes/class-sql-validator.php`
- `includes/class-woo-schema.php`
- `includes/class-library.php` (verify; grep confirms where it appears)
- `includes/class-storage.php` (verify)
- `uninstall.php` (verify)

A careful `sed`/find-replace on `'store-insights-for-woocommerce'` is safe here — it's a literal string used only as the text domain.

### 1.3 Internal page slug
- [x] `includes/class-admin.php` — change the menu page slug in `add_submenu_page()` from `store-insights-for-woocommerce` to `siphon-store-analytics-for-woocommerce`, and
update the menu title to `Store Analytics`.
- [x] `store-insights-for-woocommerce.php` — update `plugin_action_links` URL to `admin.php?page=siphon-store-analytics-for-woocommerce`.

### 1.4 Translations / POT
- [x] Rename `languages/store-insights-for-woocommerce.pot` → `languages/siphon-store-analytics-for-woocommerce.pot`.
- [x] Regenerate with the new text domain (see Phase 6 for the exact command).

### 1.5 Build & metadata
- [x] `package.json`: update `name`, `version` (bump to `3.3.0`), and the `i18n` script (new domain, POT path, `Project-Id-Version`).
- [x] `package-lock.json`: update `name` (top of file).
- [x] `scripts/build-dist.js`: update `SLUG` and the main-file entry in `INCLUDE`.
- [x] `readme.txt`: title, `Stable tag`, any path references in Installation, and the `Source code:` URL (update or remove).
- [x] `README.md`: update name references.
- [x] `.distignore` (if present): update slug/main-file if referenced.

### 1.6 Keep as-is (do not rename)
- `SILC_WIA_*` constants, `SILC_WIA_*` classes, `silc_wia_*` option/transient names.

---

## Phase 2 — AI hybrid (WP AI Client default + BYOK fallback)

### 2.1 Make prompt builders reusable
- [x] In `includes/class-api.php`, change `build_insight_system_prompt()` and `build_insight_user_prompt()` from `private` to `public static`. (`parse_insight_json()` is al
ready public.)

### 2.2 New core AI service
- [x] Create `includes/class-ai-service-core.php` with `SILC_WIA_AI_Service_Core`:
  - `is_available()` → `function_exists('wp_ai_client_prompt') && function_exists('wp_supports_ai') && wp_supports_ai()`.
  - `generate_insight( $question, $schema_context )` → builds system + user prompt, calls:
    ```php
    wp_ai_client_prompt( $user )
        ->using_system_instruction( $system )
        ->using_temperature( 0.2 )
        ->generate_text();
    ```
    then reuses `parse_insight_json()` and the existing correction-retry (multi-turn message array), returning the same `array{success, insight, error}` shape as `SILC_WIA_
API`.
- [x] Verify two SDK details while implementing:
  - multi-turn message-array shape (`role`/`content`) — confirmed, but the retry is sent as a **single** corrective prompt instead: `with_history()` needs `Message` DTO objects, and the AI Client picks the provider, so replaying an assistant turn would be provider-specific.
  - `using_max_tokens()` exists (7.0 builder) — used with a 2000-token floor.
- [x] Optional: `as_json_response()` deliberately **not** used — a schema-less JSON mode is not available everywhere, so the prompt keeps demanding JSON and the corrective retry handles deviations (same behaviour as BYOK).

### 2.3 New router
- [x] Create `includes/class-ai-service.php` with `SILC_WIA_AI_Service`:
  - option `silc_wia_ai_backend` (`core` | `byok`).
  - default = `core` when `SILC_WIA_AI_Service_Core::is_available()`, else `byok`.
  - `generate_insight()` dispatches to core or `SILC_WIA_API`.

### 2.4 Wire into pipeline
- [x] `includes/class-insights.php` line ~57: replace `SILC_WIA_API::generate_insight(...)` with `SILC_WIA_AI_Service::generate_insight(...)`.
- [x] Update the cache key to include the active backend (core vs `api_url|model`).

### 2.5 Settings & admin
- [x] `includes/class-admin.php`:
  - register/sanitize `silc_wia_ai_backend`.
  - expose to `silcWiaData`: `aiBackend`, `coreAiAvailable`, `coreAiSupported` (`is_supported_for_text_generation()`).
  - `handle_test_api()`: branch core (tiny `generate_text()` probe) vs BYOK (`SILC_WIA_API::test_connection()`).
  - update `add_privacy_policy_content()` for both paths.
- [x] `assets/js/src/settings-panel.js` (and `utils.js`/`index.js`):
  - add a "Backend" selector at the top of the AI Provider section (Automatic / WordPress AI Client / Your own API key; the raw choice is stored so saving an unrelated field cannot turn Automatic into an explicit choice).
  - WordPress AI = hide Provider/URL/Key/Model (and Max Tokens unless supported), show a note pointing to Settings → Connectors.
  - BYOK = existing fields.
  - Keep Temperature + Cache visible in both.
- [x] Rebuild `assets/js/dashboard.js` (`npm run build`).

### 2.6 Follow-up: model preference + temperature/reasoning controls
Verified against the WordPress 7.1 core SDK (`wp-includes/ai-client`, `php-ai-client`):
`using_temperature()` adds a *requirement* to model discovery, so any temperature
value removes every reasoning model (verified: 52 candidate models → 24).
`using_model_preference()` accepts a plain model id and silently falls back to
the first available model, and `ModelConfig::setCustomOption('reasoning', …)` is
the only way to request a reasoning level (all 52 models remain eligible).

- [x] **Model preference on the core path.** `SILC_WIA_AI_Service_Core::generate_text()` calls `using_model_preference()` with the stored model id. Read through the new `SILC_WIA_API::get_model_preference()` (raw option) because `get_settings()` rewrites a model that does not match the BYOK endpoint, which would drop a valid id from another connector. An empty model now stays empty for the core backend (`sanitize_settings()`), meaning "no preference"; BYOK keeps the "fill in the provider default" rule. The Model field is no longer hidden when the AI Client is selected.
- [x] **Temperature is opt-in per backend** — new `temperature_mode` setting (`auto` | `always` | `never`).
  - `auto` (default): BYOK keeps the existing per-model detection; the core path sends nothing (a value would exclude every reasoning model before the request is made).
  - `always` / `never`: explicit overrides, so no model-capability map is needed.
- [x] **Reasoning effort** — new `reasoning_effort` setting (`''` = provider default, `none`, `minimal`, `low`, `medium`, `high`, `xhigh`, `max`).
  - Core: `reasoning: { effort: … }` custom option (OpenAI Responses shape).
  - BYOK: `reasoning_effort` in the chat body; skipped for Anthropic, whose thinking budget is already handled.
- [x] **Recovery instead of a dead end.**
  - Core: `request_text()` retries with progressively fewer optional parameters (temperature → reasoning effort → neither) when WordPress reports `prompt_invalid_argument` or the provider returns 400/422. 401/429/5xx are never retried.
  - BYOK: `adapt_rejected_body()` also drops a rejected `reasoning_effort`; `send_request()` retries up to twice; a 400/422 that no rename explains drops `reasoning_effort` (an unsupported *level* is reported without the word "reasoning").
  - Errors that are configuration-related get an actionable tip pointing at Temperature, Reasoning effort and Model; the connection test names the setting it had to leave out and the model that answered.
- [x] `SILC_WIA_AI_Service::get_cache_signature()` includes model preference, temperature mode/value and reasoning effort.
- [x] Settings UI: "Send temperature" select + "Reasoning effort" select in Advanced (both backends); the Model field's help text explains the preference semantics per backend; the connection test now also requires unsaved sampling/model changes to be saved first.
- [x] Fixed a pre-existing save bug: `temperature` and `cache_ttl` of `0` were replaced with the default (`value || default`), so temperature 0 and "caching off" could never be stored.
- [x] Verified live against the OpenAI connector on this install: preference honoured (`gpt-5.4-nano`), `temperature` + reasoning (`prompt_invalid_argument`), and an unsupported level (`minimal`) all resolve through the retry ladder, and a full insight still returns SQL. BYOK verified against OpenRouter with `reasoning_effort` + temperature.

---

## Phase 3 — Chart.js upgrade

- [x] Download the latest stable **Chart.js 4.x** (4.6.1 is current per cdnjs as of Nov 2026; confirm on `github.com/chartjs/Chart.js/releases`).
- [x] Replace `assets/lib/chart.min.js`. (4.6.1 UMD build; checksum matched against the npm/unpkg copy.)
- [x] Update the hardcoded version `'4.4.7'` in `includes/class-admin.php` (`wp_enqueue_script`) → `'4.6.1'`.
- [x] Test every chart type the plugin produces: `bar`, `line`, `pie`, `doughnut`, `horizontalBar`, `radar`, `polarArea`. (All render with no console errors; `line` also verified end-to-end in the admin dashboard.)
- [x] Check `assets/js/chart-renderer.js` for deprecated Chart.js 4 API usage (e.g. `Chart.defaults`, scale config). Minor 4.x bumps are usually safe, but verify the horizo
ntalBar mapping.
  - `grid.drawBorder` (removed in v4) replaced with `border: { display: false }` on both scales.
  - `horizontalBar` mapping fixed: it was rendered as a vertical bar because Chart.js 3+ needs `indexAxis: 'y'`; `beginAtZero` moved to the value axis.
  - `radar` / `polarArea` no longer get stray `x`/`y` axes; they use a single styled `r` scale.

---

## Phase 4 — External services documentation (hard requirement)

### 4.1 readme.txt
- [x] Add `== External services ==` section (and mirror in `README.md`).
- [x] Document, for **each** service, what it is, what data is sent, when, and ToS + privacy links.

Content covered (both paths — done in Phase 2):

| Service | Used for | Data sent |
|---|---|---|
| WordPress AI Client (core path) | AI generation | Question + schema metadata + model settings (no plugin key) |
| OpenAI / Anthropic / Google (via core Connectors) | AI generation | Same payload, routed by WordPress; the connector's own terms apply |
| OpenAI / Anthropic / OpenRouter / DeepSeek / Groq / Mistral / xAI (BYOK) | AI generation | Question + schema metadata + model settings + API key as auth header |
| Self-hosted (Ollama/LocalAI/vLLM) | Local AI generation | Question + schema metadata to the local endpoint only |

- [x] Add ToS + privacy links for each hosted provider. Verify each URL at implementation time (these change):
  - OpenAI: `openai.com/policies/terms-of-use`, `openai.com/policies/privacy-policy` — both 200
  - Anthropic: `anthropic.com/legal/consumer-terms`, `anthropic.com/legal/privacy` — both 200
  - Google Gemini: `ai.google.dev/gemini-api/terms`, `policies.google.com/privacy` — not listed (no Google endpoint in the provider registry yet)
  - OpenRouter, DeepSeek, Groq, Mistral, xAI: their respective terms/privacy pages — all verified 200

### 4.2 Privacy policy content
- [x] Update `SILC_WIA_Admin::add_privacy_policy_content()` to describe the data flow (connection test, model settings, provider list; the core-AI branch must be added with Phase 2).

---

## Phase 5 — Admin dashboard / Guideline 11 check

Current state is already mostly fine:

- `render_analytics_notice()` is scoped to the plugin's own page — good, not a dashboard hijack.
- The WooCommerce-missing notice is the only global `admin_notices` one.

Actions:
- [x] Make the WooCommerce-missing notice either dismissible or scope it to the Plugins screen (dependency notices are generally acceptable, but scoping is safer). → scoped to `plugins.php`.
- [x] **Do not add** a "Pro"/upgrade notice or promotional widget — your own audit notes ship without it for the cleanest first review.
- [x] Grep for `admin_notices`, `notice`, `upsell`, `pro` and confirm the only notices are the two legitimate ones above.

---

## Phase 6 — Verification gate

Run all of these before packaging:

- [ ] `php -l` on every PHP file (clean).
- [ ] `npm run lint` (clean).
- [ ] `npm run build` (webpack succeeds; `dashboard.js` regenerated).
- [ ] Regenerate POT:
  ```bash
  wp i18n make-pot . languages/siphon-store-analytics-for-woocommerce.pot \
    --domain=siphon-store-analytics-for-woocommerce \
    --exclude='node_modules,vendor,build,dist,.git,.github,docs' \
    --headers='{"Project-Id-Version":"Siphon Store Analytics for WooCommerce 3.3.0",...}'
  ```
  (match your exact existing script flags).
- [ ] `wp plugin check .` (install Plugins Check if needed) — fix errors/warnings.
- [ ] PHPCS `WordPress-Extra` — fix violations.
- [ ] Test in **WordPress Playground** (the email explicitly asks for this).
- [ ] Test on a **PHP 7.4** environment (your code declares 7.4; the review will check it).
- [ ] Test activation **without WooCommerce** (no fatal), then activate, deactivate, delete.

### AI test matrix (from the hybrid design)

| Scenario | Expected |
|---|---|
| WP 7.0+, connector set, backend = WordPress AI | Insight generated via core; no plugin key stored |
| WP 7.0+, no connector | Clear "no AI provider configured" error |
| WP 7.0+, backend = BYOK | Existing direct path works |
| WP 6.5–6.7 | Core hidden; BYOK only; no fatal |
| `WP_AI_SUPPORT` false | Core unavailable; fallback to BYOK |
| Ollama / custom endpoint | BYOK only |
| Invalid JSON first attempt | Retry succeeds or returns parse error |
| Activation on WP 6.5 | No fatal (guard all core calls) |

---

## Phase 7 — Package & submit

- [ ] Bump version to `3.3.0` consistently: main header, `SILC_WIA_VERSION`, `readme.txt` Stable tag, `package.json`, `CHANGELOG.md`.
- [ ] Add a `3.3.0` changelog entry: rename, WP AI Client support, Chart.js upgrade, external-services docs.
- [ ] Run `npm run dist`; confirm the output folder is `dist/siphon-store-analytics-for-woocommerce/` and contains only shippable files.
- [ ] Zip it and upload via **Add your plugin** (logged in as `siphontech`).
- [ ] **Reply to the same email thread** — short, no change list (see draft below).

---

## Optional hardening (recommended before resubmit, not in the email)

These come from your own prior audit and could surface next round:

- [ ] Decide on `wp_users` / `wp_usermeta`: currently credential columns are blocked but the tables remain whitelisted. A reviewer may still flag this — consider restricting them further. 
**decision** not taking any action yet

- [ ] JS string localization: several hardcoded English strings remain in `assets/js/src/*`. Your audit treated the PHP-side `l10n` mechanism as acceptable; keep that in mind if raised.
- [x] Remove/update stale `Source code:` GitHub URL in `readme.txt`. → now points at `github.com/roypr/siphon-store-analytics-for-woocommerce` (the old `/tree/wp-3.3.0` URL returned 404).
- [x] Also fixed for consistency: Installation folder/menu names in `readme.txt`, the FAQ notice reference, and the `README.md` GitHub link (`WordPress 6.2+` → `6.5+`).

---

## Reply email draft (keep it brief, per their instruction)

> Subject: (reply inline to the review thread)
>
> Hi,
>
> Please reserve the slug `siphon-store-analytics-for-woocommerce` (display name: Siphon Store Analytics for WooCommerce).
>
> For ownership: I've added the DNS TXT record `wordpressorg-siphontech-verification` at `siphon-tech.com`.
>
> The AI features now use the WordPress AI Client by default on WP 7.0+; a bring-your-own-key path remains only for older WordPress versions and self-hosted/OpenAI-compatible endpoints. External services are documented in the readme. Updated version is uploaded.
>
> Thanks.

---

**Critical path / order of operations:** do Phase 0 first (ownership is the biggest rejection risk), then Phase 1 (rename), then Phase 2 (AI), Phases 3–5 (quick fixes), the
n Phase 6–7 (verify + submit). If time-constrained, Phase 0 + 1 + 4 are non-negotiable; Phase 2 is strongly recommended; Phase 3 is trivial.
