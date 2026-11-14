# WordPress.org Submission Audit — Store Insights for WooCommerce

My wordpress.org user id: siphontech
My website URL: https://siphon-tech.com

**Plugin:** Store Insights for WooCommerce
**Target slug:** `store-insights-for-woocommerce`
**Target text domain:** `store-insights-for-woocommerce`
**Version audited:** 3.0.0 (current name: "Store Insights with AI Lite", slug `siphon-store-insight-lite`)
**Audited against:** `wp-checklist.md` (13 rejection categories + 29-item pre-submission checklist)
**Status:** ❌ Not ready to submit — rename required + 4 blocking issues
**Estimated effort to ready:** 3–4 focused days

---

## TL;DR

The plugin is well-built and security-conscious, but it **would not pass review as-is**. Five items are blocking:

1. **Rename** to `Store Insights for WooCommerce` / `store-insights-for-woocommerce` — must happen *before* submission, because the slug is permanent after approval
2. The bundled `plugin-update-checker` (external update mechanism + undisclosed call to `api.github.com`)
3. Compliance guarantees in the readme (GDPR / HIPAA / PCI)
4. Misleading provider claims (Anthropic is not OpenAI-compatible)
5. Packaging — `vendor/`, `src/`, `composer.json`, `package.json`, and 8 dev `.md` files in the zip

Fix those, harden the dynamic-SQL execution path with explicit reviewer-facing justification, and it has a realistic path to approval. A single dismissible "Get Pro" notice is permitted and is specified in **Phase 2**.

---

## Scope of Audit

Files inspected:

- `siphon-store-insight-lite.php` (main plugin file)
- All 8 includes: `class-admin.php`, `class-ajax.php`, `class-api.php`, `class-insight-renderer.php`, `class-insights.php`, `class-library.php`, `class-sql-validator.php`, `class-woo-schema.php`
- `readme.txt`, `README.md`, `CHANGELOG.md`, `status.md`
- `package.json`, `composer.json`, `webpack.config.js`, `mago.toml`
- Built bundle `assets/js/dashboard.js`, `assets/js/src/*`, `assets/js/chart-renderer.js`
- `vendor/` tree (plugin-update-checker 5.7)
- `assets/lib/chart.min.js` (Chart.js 4.4.7), `LICENSE`

This audit reflects actual code, not changelog claims.

### Not verifiable from the repository — you must confirm

- Whether `silc` (`readme.txt:2`) is your own wordpress.org account username
- Whether the slug `store-insights-for-woocommerce` is unclaimed
- Whether a similarly named plugin already exists (search `store insights`, `insights for woocommerce`)

---

## Naming and Monetisation Policy

### Naming decision

| Element | Decision |
|---|---|
| Plugin name | **Store Insights for WooCommerce** |
| Slug | `store-insights-for-woocommerce` |
| Text domain | `store-insights-for-woocommerce` |
| Main plugin file | `store-insights-for-woocommerce.php` |
| Target folder in zip | `store-insights-for-woocommerce/` |

**Why:** the slug is permanent after approval, so any rename must happen before you submit. Dropping "Lite" from the *name* removes the qualifier that invites the trialware question, and `[Name] for WooCommerce` is the officially sanctioned third-party compatibility pattern — thousands of directory plugins use it. What got you flagged was different: starting the name with "Woo" (`WooInsight`), which reads as an Automattic mark. A trailing `for WooCommerce` is descriptive, not a prefix, and is clean.

"Lite" / "Pro" as a *product tier concept* is fine and honest — it just no longer belongs in the plugin name. Your Pro notice and your own site do that talking.

### What the rename touches

**Must change (text domain):**

- All `__( …, 'store-insights-for-woocommerce' )` and equivalents across the 9 PHP files
- `wp.i18n` calls in `assets/js/src/*`, then rebuild `dashboard.js`
- `readme.txt` header line 1 and every `Text Domain:`-adjacent string
- `Text Domain:` header in the main plugin file

**Must NOT change (already correct and releasable):**

- Class prefix `SILC_WIA_` and constants `SILC_WIA_VERSION` / `PATH` / `URL` / `BASENAME`
- Option names `silc_wia_api_settings`, `silc_wia_insight_history`, `silc_wia_insight_cache`
- AJAX action names `silc_wia_*`, nonce action `silc-wia-nonce`
- `localStorage` key `silc_wia_walkthrough_done`

Prefixes only need to be unique, not slug-matched. Leave them alone — churning them adds risk for zero review benefit.

**Optional consistency cleanup** (do it if it's cheap, skip it otherwise): the admin page slug `'siphon-store-insight-lite'` in `add_submenu_page()` (`class-admin.php:53`) and the corresponding `admin.php?page=…` in the plugin action link (`siphon-store-insight-lite.php:116`). Not a review issue; purely cosmetic.

### What is permitted for monetisation

Allowed (checklist §3, "Permitted in principle"):

- A separate Pro add-on plugin distributed outside WordPress.org — unchanged
- A limited, contextual, dismissible promotion of that Pro plugin inside your own admin screen
- The Pro plugin having its **own distinct slug**, never shared with this one

Not permitted:

- Any code in this plugin that installs, downloads, or updates the Pro plugin
- Any license-key field or validation in this plugin, even one that "unlocks nothing"
- Any promotion that describes a capability already present in the submitted code
- Review solicitation bundled into the same notice

### The WooCommerce trademark lines

- ✅ `[Name] for WooCommerce` — sanctioned compatibility pattern
- ✅ Tag `woocommerce` in `Tags:`
- ✅ Registering the menu under `add_submenu_page( 'woocommerce', … )`
- ❌ Any name **starting** with `Woo` (`WooInsight`, `WooReports`, `Woo Analytics`)
- ❌ "Official", "by Woo", "WooCommerce Approved", or anything implying endorsement
- ❌ Logo or icon derived from WooCommerce brand assets

---

## Blocking Issues

### 1. Rename before submission

See *Naming and Monetisation Policy* above. The slug cannot be changed after approval without losing the listing entirely. Do this first, verify the plugin still activates and the dashboard still loads, then proceed.

---

### 2. `plugin-update-checker` must be removed — rejection on its own

**Evidence:**

- `siphon-store-insight-lite.php:32-37` — loads `vendor/autoload.php`
- `siphon-store-insight-lite.php:72-85` — builds a PUC instance against `https://github.com/roypr/siphon-store-insight-lite` with `enableReleaseAssets()`
- `composer.json:6-8` — dependency declared
- `vendor/yahnis-elsts/plugin-update-checker/` — ~50 files shipped

**Why it is rejected:** this is an external update mechanism delivering executable plugin code from outside WordPress.org (guideline 8). It is exactly why WooCommerce had to split its update manager into a separate, non-directory plugin (`Woo.com Update Manager`, March 2024). It also makes the plugin call `api.github.com` on admin load — *undisclosed external communication* (checklist §4 and §5). Both the reviewer and the automated scanner catch this.

**Action:** remove `vendor/`, `composer.json`, `composer.lock`, the autoloader `require_once`, and the entire `init` hook at `:72-85`.

**Consequence:** updates will come from your SVN repository only — normal for directory-hosted plugins. If you still want GitHub self-updates for the non-directory build, ship that as a separate build, never from this plugin.

---

### 3. Readme makes regulatory-compliance guarantees

**Evidence:**

- `readme.txt:68-70` — "GDPR-friendly", "HIPAA-adjacent safe", "PCI-aware"
- `README.md:19-22` — same claims, expanded

**Why it is rejected:** `wp-checklist.md` §8 — "claims that a plugin guarantees legal compliance or can automatically ensure regulatory compliance are specifically problematic."

**Action:** rewrite as factual data-flow statements. Example replacement:

> Only database schema metadata (table names and column names) is included in the prompt sent to the AI provider you configure. No row-level customer, order, or payment data is transmitted. All queries execute against your local database.

Delete the compliance framing entirely.

---

### 4. Misleading provider support

**Evidence:**

- `assets/js/src/settings-panel.js:145` — presets `https://api.anthropic.com/v1`
- `assets/js/src/settings-panel.js:205` — links to Anthropic API-key docs
- `readme.txt:117` — "Claude 3.5 Sonnet produce the most accurate SQL"
- `class-api.php:50` — `'/claude-3-opus/i'` in `REASONING_MODEL_PATTERNS`

**Why it is a problem:** Anthropic's API is `/v1/messages` with an `x-api-key` header, not OpenAI-compatible `/chat/completions` with `Authorization: Bearer`. Any user who clicks that preset gets a failure. Checklist §8 — "misleading claims about what the plugin can accomplish."

**Action:** remove Anthropic from the presets, the key-links block, the FAQ, and the reasoning-model patterns. Keep OpenAI / Azure OpenAI / Ollama / LocalAI / vLLM — those are genuinely OpenAI-compatible. A real multi-provider abstraction layer is out of scope for shipping.

---

### 5. Packaging — dev files in the zip

**Files that must not ship:**

| Category | Files |
|---|---|
| Bundled update library | `vendor/` (~50 files) |
| Dev sources | `assets/js/src/index.js`, `panels.js`, `results.js`, `settings-panel.js`, `sidebar.js`, `utils.js`, `walkthrough.js` |
| Build tooling | `package.json`, `package-lock.json`, `composer.json`, `composer.lock`, `webpack.config.js`, `eslint.config.mjs`, `mago.toml` |
| Docs / planning | `README.md`, `CHANGELOG.md`, `status.md`, `plan-review.md`, `v2-plan.md`, `example.md`, `wp-checklist.md`, this audit file |

Checklist §10 — "development-only files included unnecessarily." See **Phase 3** for the build pipeline.

---

## Audit Against the 29-Item Checklist

### Security (8 items)

| Item | Verdict | Evidence / Notes |
|---|---|---|
| Sanitize and validate every input | ⚠️ Partial | `class-ajax.php:94` question is sanitized correctly. `:309` (`result_json`) and `:337` (`sql`) accepted raw by design — see *Architectural Decision* |
| Escape every dynamic output | ✅ Pass | `class-admin.php:124-126`, main file `:47,:93,:116`. React escapes all rendering; no `dangerouslySetInnerHTML` anywhere in the bundle |
| Nonce verification for state changes | ✅ Pass | All AJAX handlers route through `SILC_WIA_Ajax::verify()` (`class-ajax.php:45-57`) |
| Capability checks | ⚠️ Inconsistent | Dashboard `manage_woocommerce` (`class-ajax.php:51`); settings `manage_options` (`class-admin.php:139,:163`); menu page registered with `manage_woocommerce` (`:52`). Shop managers see a dashboard whose Save button always fails |
| Prepared SQL queries | ❌ Fails scanner | `class-sql-validator.php:316` `$wpdb->get_results( $sql )`; `class-woo-schema.php:152` `SHOW COLUMNS FROM \`{$prefixed}\`` |
| Secure AJAX and REST endpoints | ✅ Pass | `wp_ajax_` only — no `nopriv`, no REST routes |
| Safe file handling and uploads | ✅ Pass | No uploads. `include`/`file_exists` limited to plugin-local paths |
| No direct execution of PHP files | ✅ Pass | `ABSPATH` guard present in all 9 PHP files |

### Directory compliance (8 items)

| Item | Verdict | Evidence / Notes |
|---|---|---|
| GPL-compatible code and assets | ✅ Pass | GPLv2 header + `LICENSE`; Chart.js 4.4.7 is MIT, correctly bundled locally with banner intact; PUC is GPL. **Fix** `package.json:12` — declares `"license": "ISC"` |
| No trialware or locally locked features | ✅ Pass | Nothing gated behind payment. Preserve this when adding the Pro notice — see Phase 2 |
| No unauthorized tracking or telemetry | ❌ Fails | PUC phones `api.github.com` on admin load |
| No external executable code downloads | ❌ Fails | Same |
| No forced frontend branding | ✅ Pass | Zero frontend output — admin-only plugin |
| No intrusive admin notices or advertising | ⚠️ Conditional | Currently clean (one conditional WooCommerce-missing notice). Adding a Pro notice is permitted **only** within the Phase 2 spec |
| No trademark or slug conflicts | ⚠️ Risk | WooCommerce prefix removed from the name in 3.0.0 ✅. Rename resolves the rest. **Still must fix:** the settings UI reads "WooInsight works with any OpenAI-compatible API" and "WooInsight never sends your data" (`assets/js/src/settings-panel.js`, in `dashboard.js`) — that is a WooCommerce mark in user-facing copy |
| No copied or misleading functionality | ⚠️ Risk | Blockers 3 + 4. Also `readme.txt:45` claims "table and function whitelisting" — function whitelisting does not exist; `ALLOWED_FUNCTIONS` (`class-sql-validator.php:24-38`) is declared and never referenced |

### WordPress development (6 items) — strongest area

| Item | Verdict | Evidence / Notes |
|---|---|---|
| Unique prefixes for global identifiers | ✅ Pass | `SILC_WIA_` classes/constants, `silc_wia_` options and hooks throughout. Prefixes need not match the slug — leave them |
| Use WordPress-provided libraries | ✅ Pass | Uses `wp.element` / `wp.components`. Chart.js is correctly bundled (not in core) |
| Correct enqueueing | ✅ Pass | `class-admin.php:190-193` gates on `self::$page_hook` |
| Correct text domain and translations | ⚠️ Partial | Rename makes text domain match the new slug ✅, but ~40 hardcoded English strings in JS remain untranslatable (walkthrough steps, settings help text, "Ask anything about your WooCommerce store") |
| Compatible PHP and WordPress implementation | ⚠️ Risk | `Requires PHP: 7.4` but `mago.toml:5` targets 8.2. A real 7.4 test matrix is required |
| Use WordPress APIs appropriately | ⚠️ Partial | `delete_option` on **deactivation** (main file `:105-108`) instead of `uninstall.php`; no `wp_add_privacy_policy_content()` |

### Submission (7 items)

| Item | Verdict | Evidence / Notes |
|---|---|---|
| Complete and production-ready ZIP | ⚠️ Partial | No `uninstall.php`; no `languages/` directory despite `Domain Path: /languages` |
| ZIP under 10 MB | ✅ Pass | Comfortably under once `vendor/` and `src/` are excluded |
| Correct plugin headers and readme.txt | ❌ Fails | **`Tags:` has 7 entries** (`readme.txt:3`) — hard limit is 5. `Tested up to: 7.0.2` must be an actually released WordPress version. `Contributors: silc` must be your real wp.org username or author attribution drops silently. Header name must match `readme.txt` line 1 exactly after the rename |
| No development tools or unnecessary files | ❌ Fails | Blocker 5 |
| All dependencies and assets included | ✅ Pass | `dashboard.js` build present and current with `src/` |
| Clear description and honest feature claims | ❌ Fails | Blockers 3 + 4 |
| Working demo or documentation | ⚠️ Partial | `Screenshots` section (`readme.txt:139-145`) lists 5 images; no `screenshot-*.png` files exist. They belong in SVN `/assets`, not the plugin zip |

### Score

- ✅ Pass: 13
- ⚠️ Partial / risk: 11
- ❌ Fail: 5

---

## Architectural Decision: Arbitrary SQL Execution

The plugin's entire purpose is executing AI-generated SQL. That is inherently at odds with the `$wpdb->prepare()` expectation, and it is the single thing a reviewer will spend the most time on.

| Option | Assessment |
|---|---|
| **A. Keep free-form SQL, harden + document** | **Recommended.** Defensible: admin capability + nonce + SELECT-only + table whitelist. The reasoning must be visible in the code so a reviewer doesn't have to reverse-engineer it |
| B. Replace with a fixed query catalog | Kills the product's core value |
| C. Drop to `manage_options` and accept the scrutiny | Weakens shop-manager UX and does not remove the finding |

### Validator tightening required before a reviewer tests it

1. **`class-sql-validator.php:228`** — `preg_match( '/^[a-z]{1,4}$/i', $tbl )` marks **any** 1–4 character token as an allowed "alias". A real table named e.g. `logs` would pass the whitelist. Remove the heuristic; collect aliases properly from `FROM/JOIN x AS y` patterns instead.
2. **`class-sql-validator.php:312-343`** — `execute()` fetches the **entire** result set and only slices to 200 rows *after* the fact. `SELECT * FROM wp_posts` on a large store will exhaust memory. Append `LIMIT n` when no `LIMIT` is present, then slice. (Skip for `WITH` queries — a CTE cannot be wrapped in a MySQL subquery.)
3. **`class-api.php:227`** — `'timeout' => 600` will hit `max_execution_time` on many shared hosts. 90 seconds is sufficient.

### Recommended annotation form

```php
public static function execute( string $sql ): array {
    global $wpdb;

    // $sql is an AI-generated, validator-approved SELECT. It contains no bound
    // parameters, so $wpdb->prepare() cannot be applied. The injected identifier
    // surface is closed by SILC_WIA_SQL_Validator::validate(), which enforces:
    //   - SELECT / WITH only (no DML, DDL, or multi-statement)
    //   - a hard table whitelist built from known WooCommerce + core tables
    //   - a forbidden-keyword and suspicious-pattern blocklist
    //   - backtick/string-literal stripping before table extraction
    // Callers must pass a query through validate() first.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $results = $wpdb->get_results( $sql, ARRAY_A );
```

---

## Implementation Plan

### Phase 0 — Rename and blocking policy fixes (1 day)

**Rename**

- [ ] Rename folder and main file to `store-insights-for-woocommerce/` and `store-insights-for-woocommerce.php`
- [ ] Update `Plugin Name:` to `Store Insights for WooCommerce`
- [ ] Update `Text Domain:` to `store-insights-for-woocommerce`
- [ ] Replace the text domain string in every `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, `_x()` across the 9 PHP files
- [ ] Replace text domain in `wp.i18n` calls in `assets/js/src/*`, then rebuild `dashboard.js`
- [ ] Update `readme.txt` line 1 to `=== Store Insights for WooCommerce ===`
- [ ] Verify the plugin still activates and the dashboard renders after the rename
- [ ] Confirm slug `store-insights-for-woocommerce` is still available at submission time

**Policy fixes**

- [ ] Remove PUC: `vendor/`, `composer.json`, `composer.lock`, autoloader require (`:32-37`), `init` hook (`:72-85`)
- [ ] Strip GDPR / HIPAA / PCI claims from `readme.txt:68-70` and `README.md:19-22`; replace with factual data-flow wording
- [ ] Remove Anthropic from provider presets, key links, FAQ (`readme.txt:117`), and reasoning-model patterns (`class-api.php:50`)
- [ ] Replace all remaining "WooInsight" strings with "this plugin" and rebuild
- [ ] `readme.txt:3` — reduce `Tags:` to 5
- [ ] `readme.txt:5` — set `Tested up to:` to an actually released WordPress version
- [ ] `readme.txt:2` — set `Contributors:` to your real wp.org username

### Phase 1 — Security and technical review magnets (1–1.5 days)

1. [ ] Delete the debug block at `class-api.php:318-323` — it `error_log()`s the full system prompt, user prompt, and full API response
2. [ ] Fix `class-api.php:196-198` — `max_tokens` hardcoded to `16000` with the real variable commented out. The user's Max Tokens setting currently does nothing, and `REASONING_MAX_TOKENS` / `get_effective_max_tokens()` are dead code. Restore `$effective_max` / `$max_tokens`
3. [ ] **Remove `api_key` from `wp_localize_script`** (`class-admin.php:261`). It lands in page source, and the JavaScript never reads it — `dashboard.js` initialises `api_key: ""` unconditionally. Pass the already-computed `has_api_key` boolean (`:246`) instead. Zero-cost secret leak fix
4. [ ] Annotate the two dynamic queries with `phpcs:ignore` plus a justification comment (form above). Use `$wpdb->prepare( 'SHOW COLUMNS FROM %i', $prefixed )` for the schema query and bump `Requires at least` to 6.2 — cheap in 2026 and removes one finding outright
5. [ ] Make capability handling consistent — `manage_woocommerce` throughout, or settings at `manage_options` **and** the menu page too
6. [ ] Add `uninstall.php` containing the `delete_option` calls; remove them from the deactivation hook (`:105-108`). Deactivation must not destroy user data
7. [ ] Add `wp_add_privacy_policy_content()` describing exactly what leaves the site: the user's question, the schema context string, and the API key header
8. [ ] Delete dead code: `handle_regen_insight()` (never registered in `init()`), `ALLOWED_FUNCTIONS`, duplicate `return $config;` at `class-insights.php:378-379`, unused `assets/js/chart.asset.php`
9. [ ] Apply the three validator/timing tightenings from *Architectural Decision* above
10. [ ] Extract or localize the ~40 hardcoded JS strings, or at minimum document them as a known limitation

### Phase 2 — Pro notice (2–3 hours)

Build exactly one notice, to this spec. Every line matters:

| Requirement | Rule |
|---|---|
| Placement | Only on your own page (`admin.php?page=…`). Never `admin_notices` on other screens |
| Dismissal | Stored per user in `get_user_meta()` — permanent, never reset by an update |
| Frequency | Shown once until dismissed. No modal, no pointer overlay, no popup |
| Content | One sentence + one link. No countdown, no fake urgency, no review solicitation |
| Accuracy | Describes **only** capabilities absent from this codebase |
| No tracking | Plain link, `rel="noopener noreferrer"`. No third-party referral parameters |

- [ ] Add `render_upgrade_notice()` called from `render_page()`, before `<div id="silc-wia-dashboard">`
- [ ] Add a dismiss AJAX handler with the standard nonce + capability check
- [ ] Grep the entire codebase for the Pro capability names you reference in the notice — confirm zero matches
- [ ] Confirm no license-key field, no remote install/download code, no Pro update checks exist anywhere

```php
/**
 * Render a single, permanently dismissible upgrade notice.
 *
 * Shown only on this plugin's own admin screen — never on other admin pages —
 * and dismissed per user, permanently. Dismissal is never reset by an update.
 */
public static function render_upgrade_notice(): void {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( get_user_meta( get_current_user_id(), 'silc_wia_pro_notice_dismissed', true ) ) {
        return;
    }
    ?>
    <div class="notice notice-info is-dismissible silc-wia-pro-notice">
        <p>
            <?php
            esc_html_e(
                'Scheduled email reports and multi-store dashboards are available in the separate Pro add-on.',
                'store-insights-for-woocommerce'
            );

            echo ' ';

            printf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url( 'https://example.com/pro/' ),
                esc_html__( 'Learn more', 'store-insights-for-woocommerce' )
            );
            ?>
        </p>
    </div>
    <?php
}
```

Dismissal handler, registered in `SILC_WIA_Admin::init()`:

```php
public static function handle_dismiss_pro_notice(): void {
    check_ajax_referer( SILC_WIA_Ajax::NONCE_ACTION, 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'store-insights-for-woocommerce' ) ) );
    }

    update_user_meta( get_current_user_id(), 'silc_wia_pro_notice_dismissed', 1 );
    wp_send_json_success();
}
```

The `is-dismissible` class plus a small JS handler that posts to `silc_wia_dismiss_pro_notice` handles the click. Do not use `admin_notices` — this renders inside your own page markup.

### Phase 3 — Build and packaging (½ day)

- [ ] Add `.distignore` excluding: `src/`, `node_modules/`, `vendor/`, `composer.*`, `package*.json`, `webpack.config.js`, `eslint.config.mjs`, `mago.toml`, `*.md`
- [ ] Add a `build` script: run webpack, then `wp dist-archive .` (or a plain zip of the allowlist)
- [ ] `package.json:12` — change `"license": "ISC"` to `"GPL-2.0-or-later"`
- [ ] Verify the zip contains exactly one top-level folder named `store-insights-for-woocommerce/`
- [ ] Upload screenshots to SVN `/assets` or delete the `Screenshots` section from `readme.txt`
- [ ] Add a `languages/` directory (or remove `Domain Path: /languages` from the header)

### Phase 4 — Verification gate (½ day)

- [ ] `wp plugin check store-insights-for-woocommerce` — treat every ERROR as a blocker, every WARNING as needing a written justification
- [ ] PHPCS with the `WordPress-Extra` standard
- [ ] Test matrix: PHP 7.4 **and** 8.2
- [ ] Test matrix: WP 6.0 **and** current stable
- [ ] WooCommerce with HPOS **enabled** and **legacy** order storage
- [ ] Plain permalinks
- [ ] Multisite
- [ ] Manual lifecycle: activate without WooCommerce → activate with WooCommerce → deactivate → delete. Confirm no PHP notices and no orphaned options
- [ ] Confirm the Pro notice appears once, dismisses permanently, and does not return on reactivation or update
- [ ] Simulate a reviewer: try `$_POST['question']` with `'; DROP TABLE wp_options; --` and confirm the validator blocks it

---

## Residual Risks

- **Free-form SQL will still draw comments.** Even hardened, expect a reviewer question. Answer it in one paragraph — capability gate, SELECT-only rule, whitelist location. Document, don't argue.
- **You permanently give up the GitHub update channel** for the directory build. That is the price of listing. Confirm you are comfortable maintaining SVN.
- **The AI feature requires a paid third-party key.** That is permitted serviceware, but the Library (30 queries, no key needed) is what makes this a legitimate standalone plugin. Lead with the Library in the description — it is the strongest argument against any trialware suspicion.
- **The Pro notice is the highest-risk optional addition.** It is permitted, but it is also the single thing most likely to convert a clean approval into review feedback if the spec above is loosened. If you want maximum approval odds on the first pass, ship without it and add it in version 3.1.
- **`wp_users` and `wp_usermeta` are on the table whitelist.** Password hashes and session tokens are readable via the AI path by a shop manager. It is admin-only and arguably in scope for customer analytics, but a reviewer may flag it. Consider excluding `usermeta` and restricting `users` to the columns you actually chart.

---

## Pre-Submission Checklist Status

| Area | Ready? |
|---|---|
| Naming | ❌ Rename required — must precede submission |
| Security | ❌ Blocked by unprepared-SQL findings and the API-key leak |
| Directory compliance | ❌ Blocked by PUC and the compliance claims |
| Monetisation | ⚠️ Permitted; proceed only within the Phase 2 spec |
| WordPress development | ⚠️ Minor fixes only — the strongest area |
| Submission packaging | ❌ Blocked by dev files, tag count, and invalid `Tested up to` |
| Overall | ❌ **Do not submit yet** |

---

*This audit file must be excluded from the distribution zip — it is covered by the `*.md` rule in the Phase 3 `.distignore`.*
