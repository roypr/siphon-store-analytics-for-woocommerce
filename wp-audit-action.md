All changes are in and verified. Summary:

### Remaining
## What is actually left

1. **Phase 2 Pro notice — your call, not a blocker.** Needs the real Pro URL. The audit itself recommends shipping without it for the cleanest first review.
2. **Phase 4 verification gate — needs tooling/environments we don't have here:**
   - `wp plugin check` — Plugins Check isn't installed on this site (wp-cli is, so I can install and run it if you want).
   - PHPCS `WordPress-Extra` — not installed (no composer/vendor in the plugin).
   - PHP 7.4 and WP 6.2 runtimes — only PHP 8.2 / WP 7.1.4 here. I did a static scan for PHP-8-only syntax (`match`, `?->`, `str_contains`, enums, attributes, arrow fns): n
one found, but that is *not* a real 7.4 test.
   - Legacy (non-HPOS) storage, multisite, plain permalinks — this site is HPOS, single-site, pretty permalinks.
   - Full lifecycle (activate without WooCommerce → activate → deactivate → **delete**) — uninstall logic is inspection-verified only; actually deleting the plugin isn't so
mething I should do in your workspace.
   - Reviewer simulation (`'; DROP TABLE wp_options; --`) — already covered by the validator tests.

4. **Optional polish:** `Requires Plugins: woocommerce` header (WP 6.5+), a `languages/` + POT file, and screenshots uploaded to SVN `/assets` — none are rejection risks.


### Renaming completed

## What changed

**Update mechanism (Blocker 2)**
- Deleted `vendor/` (plugin-update-checker), `composer.json`, `composer.lock`
- Removed the Composer autoloader `require_once` and the whole GitHub update-check `init` hook from `siphon-store-insight-lite.php` — the plugin no longer touches `api.gith
ub.com`

**Readme / claims (Blockers 3 + 5)**
- GDPR / HIPAA / PCI framing replaced with factual data-flow wording in both `readme.txt` and `README.md` (a "Sent / Not sent" table in README)
- `Tags:` 7 → 5, `Contributors: silc` → `siphontech`, `Tested up to: 7.0.2` → `7.1` (local WP is 7.1.4), `WC tested up to:` → `11.2` (installed WC is 11.2.1), `Requires at
least:` 6.0 → 6.2 (needed for `%i`), `Stable tag:` → 3.1.0
- Removed the false "function whitelisting" claim and the `Screenshots` section (no files exist); dropped `Domain Path: /languages` (no `languages/` dir)

**Security / Phase 1**
- Deleted the `error_log()` block that dumped the system prompt, user prompt, and full API response
- Restored `$max_tokens` / `get_effective_max_tokens()` in `build_request_body()` — the Max Tokens setting works again
- `api_key` removed from `wp_localize_script`; only `hasApiKey` is exposed. JS (`settings-panel.js`) now reads `data.hasApiKey`; rebuilt `dashboard.js`
- Both dynamic queries annotated: `execute()` carries a reviewer-facing justification + `phpcs:ignore`; schema lookup now uses `$wpdb->prepare( 'SHOW COLUMNS FROM %i', … )`
- Capability checks unified on `manage_woocommerce` (menu, AJAX, settings handlers)
- New `uninstall.php` (multisite-aware); the destructive deactivation hook is gone
- `wp_add_privacy_policy_content()` added and verified in the Privacy Policy Guide
- Dead code removed: `handle_regen_insight()`, `ALLOWED_FUNCTIONS`, duplicate `return`, `assets/js/chart.asset.php`, a duplicated docblock, trailing junk after the API clas
s
- Validator hardening: the 1–4 character "alias" loophole is gone (aliases are now collected from declared `FROM`/`JOIN` aliases, minus a SQL keyword blocklist); `execute()
` appends `LIMIT 200` before running; timeout 600 → 90s

**Packaging + housekeeping**
- `.distignore`, `npm run dist` (`scripts/build-dist.js`, dependency-free), `package.json` license → `GPL-2.0-or-later`, `dist/` gitignored, ESLint config covers Node scrip
ts
- "WooInsight" removed from user-facing JS copy; version bumped to 3.1.0 (header, constant, readme, package.json, CHANGELOG)

## Verification
- `php -l` clean on all 10 PHP files; `npm run lint` clean; webpack build succeeds
- CLI harness (deleted afterwards): **30/30 library queries still validate**, 12 injection/edge cases rejected (incl. `; DROP TABLE`, `SLEEP()`, unknown tables, bare `abc`/
`logs`), 5 legitimate alias/CTE/comma-join queries accepted, `execute()` returned exactly 200 rows on an unbounded query, schema discovery works through `%i`
- Browser: dashboard renders, library query runs ("Executed in 1.01ms"), chart draws, settings panel shows "API key is saved and active" with no key in page source, setting
s save endpoint returns success, privacy policy text renders. No new PHP errors in `debug.log`; console shows only pre-existing WP core warnings
- `npm run dist` produces a single top-level `siphon-store-insight-lite/` folder with 19 shippable files

## Deliberately not done
- **Rename** (folder, main file, plugin name, slug, text domain) — per your instruction; leaves everything internally consistent rather than half-renamed. `npm run dist` an
d `Installation` in readme still say `siphon-store-insight-lite`
- **All Claude/Anthropic references** — untouched, as instructed. Note the audit's misleading-provider finding therefore remains (Anthropic preset + "Claude 3.5 Sonnet" FAQ
 claim)
- **JS string localization** — documented as a known limitation in `README.md` (the audit permits this alternative); the PHP-side `l10n` mechanism covers 32 keys
- **Pro notice (Phase 2)** and **`wp_users`/`wp_usermeta` whitelist decision** — both need your input (real Pro URL; whether shop managers may query user tables)
- Plugin Check / PHPCS were **not** run — no `wp-cli` or PHPCS in this environment; that's the Phase 4 gate.

---

# Round 2 — schema/SQL hardening + provider architecture (3.2.0)

No new user-facing feature; this closes the two findings the audit left open
(credential columns on the whitelist, misleading provider support) and replaces
the single hardcoded OpenAI wire format with a provider registry.

## Schema and SQL validation

**`class-woo-schema.php`**
- New `SENSITIVE_COLUMNS` (`user_pass`, `user_activation_key`, `post_password`), stripped from
  `get_table_schemas()` so neither the AI prompt nor the schema payload can advertise them
  (dynamic `SHOW COLUMNS` results included). Same mechanism the Pro build uses.
- `get_schema_context()` now states which columns are omitted and that `usermeta`
  session/credential meta must not be queried, plus a matching rule line. Also fixed a
  duplicated sentence in its docblock.

**`class-sql-validator.php`**
- Forbidden-keyword scan now runs on a literal-stripped copy of the query (escape-aware), so
  `CONCAT('Coupon #', id)` or a `--` inside a value no longer trips the comment/keyword
  blocklist. Comment markers outside literals, stacked statements, hex and `SLEEP()` are still
  rejected.
- New `PROTECTED_IDENTIFIERS` check rejects any query naming `user_pass`,
  `user_activation_key`, `post_password` or `session_tokens`. Unlike the keyword scan this runs
  against the raw query, because `wp_usermeta.meta_key = 'session_tokens'` reaches session data
  through a *value*. Cost: a literal that merely mentions one of those words is rejected too —
  accepted trade for credential data.
- Kept the Round 1 alias/CTE handling (declared aliases only), which is stricter than Pro's
  1–4-character heuristic.

## AI providers and models

**New `includes/class-provider-adapter.php`** (adapted from Pro's `WIA_Provider_Adapter`)
- Registry: OpenAI, Claude (Anthropic), OpenRouter, DeepSeek, Groq, Mistral, xAI, Custom —
  endpoint, wire format, completion-limit parameter, temperature support, default model,
  key prefixes/placeholder.
- Host-based detection (no path false positives), key-shape detection with longest-prefix
  routing, `suggest_url_for_key()`, `model_matches_provider()`.

**`class-api.php`**
- Completion limit is now per provider, not guessed from the model name: OpenAI/OpenRouter/Groq
  send `max_completion_tokens`; Anthropic/DeepSeek/Mistral/xAI send `max_tokens`. A rejection
  that blames the parameter (or `temperature`) is retried once with the body repaired.
- Real Claude support: `/v1/messages`, `x-api-key` + `anthropic-version`, top-level `system`,
  typed content blocks both ways, thinking requested only where the model accepts it
  (adaptive families omit it), `max_tokens` clamped under the non-streaming ceiling.
- Default models refreshed to the Pro set; a stored model that cannot belong to the configured
  endpoint is replaced with that provider's default; `gpt-4o-mini` keeps working as "unset".
- Test Connection goes through the same provider-aware dispatch path (no thinking budget spent).
- Fixed the JSON retry silently dropping the assistant turn it built: the retry now replays it,
  which also stops two consecutive `user` turns reaching Claude (a 400).

**`class-admin.php`**
- Provider list passed to the dashboard from the adapter (single source of truth for endpoints and
  default models).
- Saving a different API URL without entering a key clears the stored key (a key is
  provider-specific) and the response explains it; an empty model falls back to the provider
  default; a pasted key whose prefix identifies a provider replaces only the built-in default
  endpoint; the AJAX response echoes the stored URL/model so the form mirrors it.

**Settings panel (`assets/js/src`)**
- Provider select (server-driven) that fills the endpoint and default model; Custom keeps the
  free-text URL for Azure/Ollama/LocalAI/vLLM/LM Studio; model help names the provider default;
  the "key is saved" hint only appears while the saved endpoint is still selected.

## Docs
- `readme.txt` / `README.md`: provider list, "Claude supported natively", privacy bullets for the
  blocked credential columns; removed the false "Function whitelist" claim and the stale
  "Claude 3.5 Sonnet" model advice.
- `CHANGELOG.md` 3.2.0 entry; version bumped in header, constant, readme, package.json.

## Verification
- `php -l` clean on all 11 PHP files; `npm run lint` clean; webpack build succeeds.
- 75-assertion CLI harness (deleted afterwards): all 30 library queries still validate against
  the live prefix; 13 injection/edge cases rejected; alias/CTE/comma-join cases accepted;
  protected columns blocked (including the `meta_key` form); literal-only false positives gone;
  provider detection, token-parameter selection, drift retry, Claude envelope, thinking and
  clamp verified; settings key lifecycle verified.
- 25 assertions through `wp eval-file` on the active install (WP 7.1.4 / WC 11.2.1 / PHP 8.2.4):
  dynamic schema hides `user_pass`/`user_activation_key`/`post_password`; the sample query
  executes with the row cap; registry and body building behave as expected. A follow-up
  `wp eval` check confirmed key-shape routing replaces only the built-in default endpoint,
  preserves a custom URL, and leaves the stored option untouched.
- Browser: dashboard loads, settings panel lists all eight providers, detects the saved
  OpenRouter endpoint, keeps the stored custom model id, fills Claude's endpoint/default model on
  switch, and hides the saved-key hint once the endpoint changes. Library query ran end-to-end
  through AJAX (`success: true`, validator + execution). No new console errors.

## Still open (unchanged from Round 1)
- Phase 2 Pro notice (needs the real Pro URL) — not added.
- No `wp_users`/`wp_usermeta` removal from the whitelist; the credential columns are blocked
  instead, which is what the audit's residual-risk note asked for.
- Plugin Check / PHPCS remain the Phase 4 gate.

---

# Round 3 — data-at-rest and transport hardening (3.2.1)

Same scope rule: no new feature, only what the security review flagged for this build.

## Result rows are no longer stored

`handle_generate_insight()` used to persist the whole rendered answer (`list_data`, chart
datasets, formatted answer) into `silc_wia_insight_history`. That put customer names, emails and
addresses into `wp_options` indefinitely — measured at 3,328 bytes and autoloaded on this install.

- New `includes/class-storage.php` (`SILC_WIA_Storage`) owns the history option: `get_history()`,
  `append_history()` (keeps the newest 10), `clear_history()`.
- `class-insights.php` now returns `render_config` — the *pre-render* type/title/chart/list/answer
  config, before rendering fills it with data. History stores that, not the rendered payload.
- History entries are `id, question, title, type, sql, sql_time_ms, rows_returned, time` plus the
  pre-render config. Opening an entry re-executes its SQL through the Library path
  (`handleLoadHistory` → `handleLibraryItem`), so the numbers are current and no rows are kept.
- Entries written by earlier versions are reduced to metadata on upgrade (`maybe_migrate()`).
  Their post-render config cannot be replayed, so they fall back to default rendering.
- `re_execute_sql()` also returns `render_config`, and `handleRefresh()` now uses it — this fixes a
  latent bug where refreshing a chart lost the AI's chart type and transform.

## Options are never autoloaded

`SILC_WIA_Storage::save()` creates options with `add_option( …, 'no' )` and re-asserts the flag
after each write (`wp_set_option_autoload_values()` on WP 6.4+, a single `$wpdb` update below that).
`maybe_migrate()` applies it to the existing settings/history options and removes the legacy
`silc_wia_query_history` option. Measured after the change: both options `autoload=off`, history
down from 3,328 to 2,027 bytes.

## HTTPS required

`SILC_WIA_API::is_allowed_api_url()` accepts `https`, plus plain HTTP only for local endpoints
(loopback, private/reserved ranges, `*.localhost`, `.local`, `.test`, single-label host names) so
Ollama/LocalAI/vLLM keep working. Enforced in `handle_save_settings()` (with a readable error) and
again in `dispatch_request()`, so a URL stored by an older version cannot send the key in clear text.
The settings screen states the rule under the URL field.

## Uninstall and cache

- `uninstall.php` also deletes the cached-answer transients (`_transient_…` and
  `_transient_timeout_…` for `silc_wia_insight_*`), the legacy `silc_wia_query_history` option and
  the storage-version marker.
- The insight cache key now includes endpoint + model, so switching provider cannot serve an answer
  generated by the previous configuration.

## Verification
- `php -l` clean on all 12 PHP files; `npm run lint` clean; webpack build succeeds.
- Live install: after one page load both options report `autoload=off`, the legacy option is gone,
  the storage marker reads `2`, and the existing history entries were stripped to metadata.
- URL policy checked against 13 cases (https, http public, localhost, 127.0.0.1, RFC1918, link-local,
  single-label host, `.local`, public host, ftp, empty) — all as intended.
- Re-saving the current settings through `sanitize_settings()` + `SILC_WIA_Storage::save()` left the
  values byte-identical and `autoload=off`; a non-local `http://` endpoint was refused by
  `test_connection()` with the HTTPS message, while `http://localhost:11434/v1` reached the
  transport layer.
- Browser: a synthetic history entry (chart + transform, no rows) replayed through
  `silc_wia_execute_sql` — 9 rows, chart rendered, and the response carried `render_config` back so
  Refresh keeps the original chart config. Test entry and backup option removed afterwards; the
  user's four history entries were restored.
