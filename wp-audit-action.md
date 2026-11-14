All changes are in and verified. Summary:

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