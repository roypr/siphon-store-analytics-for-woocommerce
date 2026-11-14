/**
 * Store Insights for WooCommerce — Settings panel renderer
 *
 * @package SILC_WooInsight_AI
 */

/* global wp */

import { l10n, defaults, data } from './utils.js';

var el = wp.element.createElement;
var Button = wp.components.Button;

// Named providers. Picking one auto-fills the endpoint so the user only has to
// paste a key; "Custom" reveals the URL field for any other OpenAI-compatible
// service (Ollama, vLLM, Azure, …).
//
// The registry (endpoints, default models, key hints) is owned by the server —
// SILC_WIA_Provider_Adapter — and passed in through data.providers, so default
// model ids live in exactly one place. FALLBACK_PROVIDERS only keeps the page
// usable if that payload is missing (e.g. a stale build).
//
// Claude is detected from its URL server-side (SILC_WIA_Provider_Adapter) and
// routed to Anthropic's native Messages API, so no extra setting is needed.
var FALLBACK_PROVIDERS = {
	openai: {
		label: 'OpenAI',
		url: 'https://api.openai.com/v1',
		model: 'gpt-6-luna',
		keyPlaceholder: 'sk-...',
	},
	anthropic: {
		label: 'Claude (Anthropic)',
		url: 'https://api.anthropic.com/v1',
		model: 'claude-haiku-5-5',
		keyPlaceholder: 'sk-ant-...',
	},
	openrouter: {
		label: 'OpenRouter',
		url: 'https://openrouter.ai/api/v1',
		model: 'openai/gpt-6-luna',
		keyPlaceholder: 'sk-or-v1-...',
	},
	deepseek: {
		label: 'DeepSeek',
		url: 'https://api.deepseek.com/v1',
		model: 'deepseek-flash',
		keyPlaceholder: 'sk-...',
	},
	groq: {
		label: 'Groq',
		url: 'https://api.groq.com/openai/v1',
		model: 'openai/gpt-oss-120b',
		keyPlaceholder: 'gsk_...',
	},
	mistral: {
		label: 'Mistral',
		url: 'https://api.mistral.ai/v1',
		model: 'mistral-large-latest',
		keyPlaceholder: 'Mistral API key',
	},
	xai: {
		label: 'xAI (Grok)',
		url: 'https://api.x.ai/v1',
		model: 'grok-4.5',
		keyPlaceholder: 'xai-...',
	},
	custom: {
		label: 'Custom',
		url: '',
		model: '',
		keyPlaceholder: 'sk-...',
	},
};

var SERVER_PROVIDERS = data.providers || {};

var PROVIDERS = Object.keys(FALLBACK_PROVIDERS).reduce(function (acc, slug) {
	acc[slug] = Object.assign({}, FALLBACK_PROVIDERS[slug], SERVER_PROVIDERS[slug] || {});
	return acc;
}, {});

/**
 * Normalize an endpoint for comparison (same rule as the server's
 * untrailingslashit).
 *
 * @param {string} url API base URL.
 * @return {string} URL without a trailing slash.
 */
function normalizeUrl(url) {
	return String(url || '').replace(/\/+$/, '');
}

/**
 * Match a stored endpoint back to a known provider so the select reflects the
 * saved configuration. An unrecognised or blank endpoint is treated as Custom
 * (keeping the URL field usable).
 *
 * @param {string} url API base URL.
 * @return {string} Provider slug.
 */
function detectProvider(url) {
	var normalized = normalizeUrl(url);
	var slugs = Object.keys(PROVIDERS);

	for (var i = 0; i < slugs.length; i++) {
		var def = PROVIDERS[slugs[i]];
		if (def.url && normalizeUrl(def.url) === normalized) {
			return slugs[i];
		}
	}

	return 'custom';
}

/**
 * Apply a provider choice to the settings form.
 *
 * @param {Function} updateSetting Settings setter from the parent.
 * @param {Object}   formSettings  Current form values.
 * @param {Event}    event         Change event of the provider select.
 */
function handleProviderChange(updateSetting, formSettings, event) {
	var next = event.target.value;
	var def = PROVIDERS[next] || {};

	// Named providers own their endpoint; Custom keeps the URL editable so a
	// previous provider's endpoint cannot be saved by accident.
	if (def.url) {
		updateSetting('api_url', def.url);
	} else if (detectProvider(formSettings.api_url) !== 'custom') {
		updateSetting('api_url', '');
	}

	// A typed key belongs to the provider it was entered for.
	if (formSettings.api_key) {
		updateSetting('api_key', '');
	}

	// A model id is provider-specific. Use the new provider's default whenever
	// it has one: keeping the previous provider's model (e.g. a GPT id on
	// Anthropic) would make every request fail.
	if (def.model) {
		updateSetting('model', def.model);
	}
}

/**
 * Helper: a labelled input row.
 *
 * Defined outside renderSettingsPanel so React recognises it as the
 * same component type across renders, preventing unnecessary
 * unmount/remount of input elements (which caused focus loss).
 *
 * @param {Object} sfProps
 * @param {string} sfProps.label
 * @param {Object} sfProps.children
 * @param {string} sfProps.help
 * @return {Object} Element.
 */
function SettingsField(sfProps) {
	return el('div', { className: 'silc-wia-settings-field' },
		el('label', null, sfProps.label),
		sfProps.children,
		sfProps.help ? el('div', { className: 'help' }, sfProps.help) : null
	);
}

/**
 * Number input with minus / plus buttons.
 *
 * @param {Object}   np        Props.
 * @param {number}   np.value  Current value.
 * @param {Function} np.onChange
 * @param {number}   np.min
 * @param {number}   np.max
 * @param {number}   np.step
 * @param {string}   [np.placeholder]
 * @return {Object} Element.
 */
function NumberField(np) {
	var dec = function () {
		var v = parseFloat(np.value) || 0;
		var s = parseFloat(np.step) || 1;
		np.onChange(Math.max(np.min, +(v - s).toFixed(2)));
	};
	var inc = function () {
		var v = parseFloat(np.value) || 0;
		var s = parseFloat(np.step) || 1;
		np.onChange(Math.min(np.max, +(v + s).toFixed(2)));
	};

	return el('div', { className: 'silc-wia-number-field' },
		el('button', {
			type: 'button',
			className: 'silc-wia-number-btn silc-wia-number-minus',
			onClick: dec,
			disabled: (parseFloat(np.value) || 0) <= np.min,
			'aria-label': 'Decrease',
		}, '\u2212'),
		el('input', {
			type: 'number',
			value: np.value,
			placeholder: np.placeholder,
			min: np.min, max: np.max, step: np.step,
			onChange: function (e) { np.onChange(e.target.value); },
		}),
		el('button', {
			type: 'button',
			className: 'silc-wia-number-btn silc-wia-number-plus',
			onClick: inc,
			disabled: (parseFloat(np.value) || 0) >= np.max,
			'aria-label': 'Increase',
		}, '+')
	);
}

/**
 * Render the Settings panel.
 *
 * @param {Object}   props
 * @param {Object}   props.formSettings
 * @param {Function} props.updateSetting
 * @param {Function} props.handleSaveSettings
 * @param {Function} props.handleTestConnection
 * @param {boolean}  props.settingsDirty
 * @param {boolean}  props.saving
 * @param {boolean}  props.testing
 * @param {Object|null} props.saveMsg
 * @param {Object|null} props.testResult
 * @return {Object} Element.
 */
export function renderSettingsPanel(props) {
	var formSettings = props.formSettings;
	var updateSetting = props.updateSetting;
	var handleSaveSettings = props.handleSaveSettings;
	var handleTestConnection = props.handleTestConnection;
	var settingsDirty = props.settingsDirty;
	var saving = props.saving;
	var testing = props.testing;
	var saveMsg = props.saveMsg;
	var testResult = props.testResult;

	// Derived from the endpoint, so it always reflects what would actually be
	// saved. A blank or unknown endpoint reads as "Custom".
	var providerSlug = detectProvider(formSettings.api_url);
	var providerDef = PROVIDERS[providerSlug] || PROVIDERS.custom;

	// The API key itself is never exposed to the browser; the server only
	// reports whether one is saved. A stored key belongs to the endpoint it
	// was saved with, so the "key is saved" hint only applies while that
	// endpoint is still selected — the server drops the key otherwise.
	var savedUrl = normalizeUrl((data.settings || {}).api_url);
	var keyApplies = normalizeUrl(formSettings.api_url) === savedUrl;
	var hasKey = !!data.hasApiKey && keyApplies;
	var keyHelp;

	if (!!data.hasApiKey && !keyApplies) {
		keyHelp = 'A key is saved for a different API URL. Enter a key for the selected provider \u2014 saving removes the old one.';
	} else if (hasKey && !formSettings.api_key) {
		keyHelp = 'A key is already saved. Type a new one to replace it, or leave blank to keep the current key.';
	} else {
		keyHelp = 'Paste your API key here. Need one? Sign up at your AI provider and create a key from their dashboard.';
	}

	return el('div', { className: 'silc-wia-settings-panel' },

		// ---------------------------------------------------------------
		// SECTION 1 — AI Provider
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\uD83E\uDD16 AI Provider'
			),
			el('div', { className: 'silc-wia-settings-section-desc' },
				'Choose which AI service powers your insights. OpenAI-compatible providers and Claude (Anthropic) are supported.'
			),

			// Provider
			el(SettingsField, {
				label: 'Provider',
				help: 'Pick a provider to fill in its API URL and default model, or choose Custom to enter any other endpoint.',
			},
				el('select', {
					value: providerSlug,
					onChange: function (e) { handleProviderChange(updateSetting, formSettings, e); },
				},
					Object.keys(PROVIDERS).map(function (slug) {
						return el('option', { key: slug, value: slug }, PROVIDERS[slug].label);
					})
				)
			),

			// API URL
			el(SettingsField, {
				label: l10n.settingsApiUrl || 'API URL',
				help: (providerDef.url
					? 'Filled in from the provider you selected. Edit it only if you use a proxy or gateway.'
					: 'The address of your AI provider\u2019s API, for example http://localhost:11434/v1 for Ollama.')
					+ ' HTTPS is required unless the endpoint is on this machine or your local network.',
			},
				el('input', {
					type: 'url',
					value: formSettings.api_url,
					placeholder: providerDef.url || 'https://api.example.com/v1',
					onChange: function (e) { updateSetting('api_url', e.target.value); },
				})
			),

			// Model
			el(SettingsField, {
				label: l10n.settingsModel || 'Model',
				help: 'The model id exactly as your provider lists it.'
					+ (providerDef.model ? ' The default for ' + providerDef.label + ' is ' + providerDef.model + '.' : ''),
			},
				el('input', {
					type: 'text',
					value: formSettings.model,
					placeholder: providerDef.model,
					onChange: function (e) { updateSetting('model', e.target.value); },
				})
			),
		),

		// ---------------------------------------------------------------
		// SECTION 2 — Authentication
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\uDDD1\uFE0F\u200D\uD83D\uDD12 Authentication'
			),
			el('div', { className: 'silc-wia-settings-section-desc' },
				'Your API key is stored securely in your WordPress database and is only sent to the API URL above. This plugin makes no other external requests.'
			),

			// API Key
			el(SettingsField, {
				label: l10n.settingsApiKey || 'API Key',
				help: keyHelp,
			},
				el('input', {
					type: 'password',
					value: formSettings.api_key,
					placeholder: hasKey ? 'API key is saved \u2014 enter new one to replace' : (providerDef.keyPlaceholder || 'sk-...'),
					onChange: function (e) { updateSetting('api_key', e.target.value); },
					autoComplete: 'off',
				}),
				hasKey && !formSettings.api_key
					? el('div', { className: 'has-key-indicator' }, '\u2713 API key is saved and active.')
					: null,
				// Link to get a key
				el('div', { className: 'silc-wia-settings-key-links' },
					el('a', {
						href: 'https://platform.openai.com/api-keys',
						target: '_blank',
						rel: 'noopener noreferrer',
					}, 'Get an OpenAI API key \u2197'),
					' \u00B7 ',
					el('a', {
						href: 'https://docs.anthropic.com/en/docs/api-keys',
						target: '_blank',
						rel: 'noopener noreferrer',
					}, 'Anthropic \u2197'),
					' \u00B7 ',
					el('a', {
						href: 'https://platform.deepseek.com/api_keys',
						target: '_blank',
						rel: 'noopener noreferrer',
					}, 'DeepSeek \u2197'),
				)
			),
		),

		// ---------------------------------------------------------------
		// SECTION 3 — Advanced
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\u2699\uFE0F Advanced'
			),

			// Max Tokens
			el(SettingsField, {
				label: l10n.settingsMaxTokens || 'Max Tokens',
				help: 'Maximum length of the AI\u2019s response, in tokens. Higher values allow longer answers and chart descriptions. The minimum is 800 so the JSON reply is not cut off mid-object.',
			},
				el(NumberField, {
					value: formSettings.max_tokens,
					placeholder: defaults.max_tokens,
					min: 800, max: 8192, step: 100,
					onChange: function (v) { updateSetting('max_tokens', v); },
				})
			),

			// Temperature
			el(SettingsField, {
				label: l10n.settingsTemp || 'Temperature',
				help: 'Controls how creative the AI is. Lower values (0.1\u20130.3) produce more consistent SQL. Higher values (0.7+) can be more creative but less reliable.',
			},
				el(NumberField, {
					value: formSettings.temperature,
					placeholder: defaults.temperature,
					min: 0, max: 2, step: 0.1,
					onChange: function (v) { updateSetting('temperature', v); },
				})
			),
		),

		// ---------------------------------------------------------------
		// SECTION 4 — Caching
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\uD83D\uDCBE Caching'
			),
			el('div', { className: 'silc-wia-settings-section-desc' },
				'Results are cached so asking the same question again loads instantly. Set the duration below, or set to 0 to disable caching.'
			),

			// Cache Duration
			el(SettingsField, {
				label: l10n.settingsCache || 'Cache Duration',
				help: 'How long to remember results for the same question. Recommended: 1\u20136 hours. Set to 0 to disable caching entirely.',
			},
				el('div', { className: 'silc-wia-cache-row' },
					el(NumberField, {
						value: Math.round(parseInt(formSettings.cache_ttl, 10) / 60) || 60,
						min: 0, max: 10080, step: 15,
						onChange: function (v) {
							var minutes = parseInt(v, 10) || 0;
							updateSetting('cache_ttl', Math.max(0, Math.min(10080, minutes)) * 60);
						},
					}),
					el('span', { className: 'silc-wia-cache-unit' }, 'minutes'),
				),
				// Quick presets
				el('div', { className: 'silc-wia-cache-presets' },
					el('span', { className: 'label' }, 'Quick:'),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 0); } }, 'Off'),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 30 * 60); } }, '30 min'),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 3600); } }, '1 hour'),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 6 * 3600); } }, '6 hours'),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 86400); } }, '24 hours'),
				)
			),
		),

		// ACTIONS
		el('div', { className: 'silc-wia-settings-actions' },
			el(Button, {
				isPrimary: true,
				onClick: handleSaveSettings,
				disabled: saving || !settingsDirty,
			}, saving ? (l10n.saving || 'Saving...') : (l10n.save || 'Save Settings')),
			el(Button, {
				isSecondary: true,
				onClick: handleTestConnection,
				disabled: testing,
			}, testing ? (l10n.testing || 'Testing...') : (l10n.testConnection || 'Test Connection')),
			testResult
				? el('span', {
					className: 'silc-wia-test-result ' + (testResult.type === 'success' ? 'success' : 'fail'),
					key: testResult.text,
				}, (testResult.type === 'success' ? '✓ ' : '✗ ') + testResult.text)
				: null,
		),

		// Save message.
		saveMsg
			? el('p', {
				style: {
					margin: '12px 0 0 0',
					fontSize: '12px',
					fontWeight: 500,
					color: saveMsg.type === 'success' ? '#1a7a2e' : '#b32d2e',
				},
			}, saveMsg.text)
			: null
	);
}
