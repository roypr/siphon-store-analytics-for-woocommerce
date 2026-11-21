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
- [ ] Rename `store-insights-for-woocommerce.php` → `siphon-store-analytics-for-woocommerce.php`.
- [ ] Update header block:
  - `Plugin Name: Siphon Store Analytics for WooCommerce`
  - `Text Domain: siphon-store-analytics-for-woocommerce`
  - `Plugin URI: https://siphon-tech.com/siphon-store-analytics-for-woocommerce` (or just your root domain)
- [ ] Update the WooCommerce-missing notice text if it still says "Store Insights for WooCommerce".

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
- [ ] `includes/class-admin.php` — change the menu page slug in `add_submenu_page()` from `store-insights-for-woocommerce` to `siphon-store-analytics-for-woocommerce`, and
update the menu title to `Store Analytics`.
- [ ] `store-insights-for-woocommerce.php` — update `plugin_action_links` URL to `admin.php?page=siphon-store-analytics-for-woocommerce`.

### 1.4 Translations / POT
- [ ] Rename `languages/store-insights-for-woocommerce.pot` → `languages/siphon-store-analytics-for-woocommerce.pot`.
- [ ] Regenerate with the new text domain (see Phase 6 for the exact command).

### 1.5 Build & metadata
- [ ] `package.json`: update `name`, `version` (bump to `3.3.0`), and the `i18n` script (new domain, POT path, `Project-Id-Version`).
- [ ] `package-lock.json`: update `name` (top of file).
- [ ] `scripts/build-dist.js`: update `SLUG` and the main-file entry in `INCLUDE`.
- [ ] `readme.txt`: title, `Stable tag`, any path references in Installation, and the `Source code:` URL (update or remove).
- [ ] `README.md`: update name references.
- [ ] `.distignore` (if present): update slug/main-file if referenced.

### 1.6 Keep as-is (do not rename)
- `SILC_WIA_*` constants, `SILC_WIA_*` classes, `silc_wia_*` option/transient names.

---

## Phase 2 — AI hybrid (WP AI Client default + BYOK fallback)

### 2.1 Make prompt builders reusable
- [ ] In `includes/class-api.php`, change `build_insight_system_prompt()` and `build_insight_user_prompt()` from `private` to `public static`. (`parse_insight_json()` is al
ready public.)

### 2.2 New core AI service
- [ ] Create `includes/class-ai-service-core.php` with `SILC_WIA_AI_Service_Core`:
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
- [ ] Verify two SDK details while implementing:
  - multi-turn message-array shape (`role`/`content`) — confirm against the `WP_AI_Client_Prompt_Builder` docs.
  - whether `using_max_tokens()` exists — if yes, set a floor (e.g. 2000) so the JSON answer isn't truncated.
- [ ] Optional: use `as_json_response( $schema )` on the first attempt for stronger JSON guarantees; keep the plain retry as fallback.

### 2.3 New router
- [ ] Create `includes/class-ai-service.php` with `SILC_WIA_AI_Service`:
  - option `silc_wia_ai_backend` (`core` | `byok`).
  - default = `core` when `SILC_WIA_AI_Service_Core::is_available()`, else `byok`.
  - `generate_insight()` dispatches to core or `SILC_WIA_API`.

### 2.4 Wire into pipeline
- [ ] `includes/class-insights.php` line ~57: replace `SILC_WIA_API::generate_insight(...)` with `SILC_WIA_AI_Service::generate_insight(...)`.
- [ ] Update the cache key to include the active backend (core vs `api_url|model`).

### 2.5 Settings & admin
- [ ] `includes/class-admin.php`:
  - register/sanitize `silc_wia_ai_backend`.
  - expose to `silcWiaData`: `aiBackend`, `coreAiAvailable`, `coreAiSupported` (`is_supported_for_text_generation()`).
  - `handle_test_api()`: branch core (tiny `generate_text()` probe) vs BYOK (`SILC_WIA_API::test_connection()`).
  - update `add_privacy_policy_content()` for both paths.
- [ ] `assets/js/src/settings-panel.js` (and `utils.js`/`index.js`):
  - add a "Backend" selector at the top of the AI Provider section.
  - WordPress AI = hide Provider/URL/Key/Model (and Max Tokens unless supported), show a note pointing to Settings → Connectors.
  - BYOK = existing fields.
  - Keep Temperature + Cache visible in both.
- [ ] Rebuild `assets/js/dashboard.js` (`npm run build`).

---

## Phase 3 — Chart.js upgrade

- [ ] Download the latest stable **Chart.js 4.x** (4.6.1 is current per cdnjs as of Nov 2026; confirm on `github.com/chartjs/Chart.js/releases`).
- [ ] Replace `assets/lib/chart.min.js`.
- [ ] Update the hardcoded version `'4.4.7'` in `includes/class-admin.php` (`wp_enqueue_script`).
- [ ] Test every chart type the plugin produces: `bar`, `line`, `pie`, `doughnut`, `horizontalBar`, `radar`, `polarArea`.
- [ ] Check `assets/js/chart-renderer.js` for deprecated Chart.js 4 API usage (e.g. `Chart.defaults`, scale config). Minor 4.x bumps are usually safe, but verify the horizo
ntalBar mapping.

---

## Phase 4 — External services documentation (hard requirement)

### 4.1 readme.txt
- [ ] Add `== External services ==` section (and mirror in `README.md`).
- [ ] Document, for **each** service, what it is, what data is sent, when, and ToS + privacy links.

Content to cover:

| Service | Used for | Data sent |
|---|---|---|
| WordPress AI Client (core path) | Routes insight prompts to the site's configured provider | Question text + DB schema metadata (tables/columns); no row-level data |
| OpenAI / Anthropic / Google (via core Connectors) | AI generation | Same as above |
| OpenAI / Anthropic / OpenRouter / DeepSeek / Groq / Mistral / xAI (BYOK) | AI generation | Question + schema metadata + API key as auth header |
| Self-hosted (Ollama/LocalAI/vLLM) | Local AI generation | Question + schema metadata to the local endpoint only |

- [ ] Add ToS + privacy links for each hosted provider. Verify each URL at implementation time (these change):
  - OpenAI: `openai.com/policies/terms-of-use`, `openai.com/policies/privacy-policy`
  - Anthropic: `anthropic.com/legal/consumer-terms`, `anthropic.com/legal/privacy`
  - Google Gemini: `ai.google.dev/gemini-api/terms`, `policies.google.com/privacy`
  - OpenRouter, DeepSeek, Groq, Mistral, xAI: their respective terms/privacy pages.

### 4.2 Privacy policy content
- [ ] Update `SILC_WIA_Admin::add_privacy_policy_content()` to describe both the core-AI and BYOK data flows.

---

## Phase 5 — Admin dashboard / Guideline 11 check

Current state is already mostly fine:

- `render_analytics_notice()` is scoped to the plugin's own page — good, not a dashboard hijack.
- The WooCommerce-missing notice is the only global `admin_notices` one.

Actions:
- [ ] Make the WooCommerce-missing notice either dismissible or scope it to the Plugins screen (dependency notices are generally acceptable, but scoping is safer).
- [ ] **Do not add** a "Pro"/upgrade notice or promotional widget — your own audit notes ship without it for the cleanest first review.
- [ ] Grep for `admin_notices`, `notice`, `upsell`, `pro` and confirm the only notices are the two legitimate ones above.

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

- [ ] Decide on `wp_users` / `wp_usermeta`: currently credential columns are blocked but the tables remain whitelisted. A reviewer may still flag this — consider restricting them further. **decision** not taking any action yet

- [ ] JS string localization: several hardcoded English strings remain in `assets/js/src/*`. Your audit treated the PHP-side `l10n` mechanism as acceptable; keep that in mind if raised.
- [ ] Remove/update stale `Source code:` GitHub URL in `readme.txt`.

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
