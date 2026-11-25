/**
 * Siphon Store Analytics for WooCommerce — Settings panel renderer
 *
 * @package SILC_WooInsight_AI
 */

/* global wp */

import { defaults, data, coreAiAvailable, coreAiSupported, connectorsUrl, reasoningEfforts } from './utils.js';

var el = wp.element.createElement;
var Button = wp.components.Button;
var __ = wp.i18n.__;
var sprintf = wp.i18n.sprintf;

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
//
// The labels here are provider names, which are trademarks and stay untranslated
// ("Custom" is the only generic word and is translated at render time).
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
			'aria-label': __('Decrease', 'siphon-store-analytics-for-woocommerce'),
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
			'aria-label': __('Increase', 'siphon-store-analytics-for-woocommerce'),
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

	// Test Connection hits the server, which uses the *saved* backend and — for
	// the BYOK path — the saved endpoint and key. While any of those differ
	// from what is on screen, or a new key has been typed but not saved, a
	// result would describe a configuration that is not shown here.
	var savedSettings = data.settings || {};
	var backendChanged = (formSettings.ai_backend || '') !== (data.aiBackendSetting || '');
	// The connection test uses the saved model, temperature mode, temperature
	// and reasoning effort, so any unsaved change to them makes a result
	// describe a configuration that is not on screen.
	var samplingChanged =
		String(formSettings.model || '') !== String(savedSettings.model || '') ||
		String(formSettings.temperature_mode || 'auto') !== String(savedSettings.temperature_mode || 'auto') ||
		String(formSettings.reasoning_effort || '') !== String(savedSettings.reasoning_effort || '') ||
		String(formSettings.temperature) !== String(savedSettings.temperature);
	var connectionDirty = backendChanged || samplingChanged || !keyApplies || !!formSettings.api_key;
	var keyHelp;

	if (!!data.hasApiKey && !keyApplies) {
		keyHelp = __('A key is saved for a different API URL. Enter a key for the selected provider \u2014 saving removes the old one.', 'siphon-store-analytics-for-woocommerce');
	} else if (hasKey && !formSettings.api_key) {
		keyHelp = __('A key is already saved. Type a new one to replace it, or leave blank to keep the current key.', 'siphon-store-analytics-for-woocommerce');
	} else {
		keyHelp = __('Paste your API key here. Need one? Sign up at your AI provider and create a key from their dashboard.', 'siphon-store-analytics-for-woocommerce');
	}

	// Which backend will actually run. "Automatic" follows what the site can
	// do: the WordPress AI Client when it is available, otherwise this plugin's
	// own client. The server resolves the same rule, and reports the result as
	// data.aiBackend.
	var backendChoice = formSettings.ai_backend || '';
	var usesCore = backendChoice === 'core' || (backendChoice === '' && data.aiBackend === 'core');

	// Backend selector. "WordPress AI Client" is only offered where it exists,
	// so the screen never promises a path the site cannot take.
	var backendOptions = [
		el('option', { key: 'auto', value: '' },
			coreAiAvailable
				? __('Automatic \u2014 use the WordPress AI Client', 'siphon-store-analytics-for-woocommerce')
				: __('Automatic \u2014 use your own API key (WordPress AI Client needs WordPress 7.0+)', 'siphon-store-analytics-for-woocommerce')
		),
	];
	if (coreAiAvailable) {
		backendOptions.push(el('option', { key: 'core', value: 'core' }, __('WordPress AI Client', 'siphon-store-analytics-for-woocommerce')));
	}
	backendOptions.push(el('option', { key: 'byok', value: 'byok' }, __('Your own API key', 'siphon-store-analytics-for-woocommerce')));

	// Explains what the chosen Temperature behaviour does for this backend.
	// "Automatic" is the only option whose result depends on the backend, so
	// the text spells out what actually happens rather than just naming it.
	var tempMode = formSettings.temperature_mode || 'auto';
	var tempModeHelp;

	if (tempMode === 'always') {
		tempModeHelp = usesCore
			? __('The temperature above is always sent. Only useful for a model you know accepts it: current OpenAI reasoning models reject any value, so a rejected request is retried once without it (Test Connection tells you when that happened).', 'siphon-store-analytics-for-woocommerce')
			: __('The temperature above is always sent, even for models the plugin does not recognise. If the provider rejects it, the request is retried once without it.', 'siphon-store-analytics-for-woocommerce');
	} else if (tempMode === 'never') {
		tempModeHelp = __('No temperature is ever sent and the provider\u2019s own default is used. The safe choice for reasoning models and for providers that do not document support.', 'siphon-store-analytics-for-woocommerce');
	} else {
		tempModeHelp = usesCore
			? __('Not sent through the WordPress AI Client: requesting a temperature removes every model that does not advertise support for it, which includes all current reasoning models. Choose "Always" only for a model known to accept it.', 'siphon-store-analytics-for-woocommerce')
			: __('Sent only to models known to accept it. Reasoning models (o-series, GPT-5/6, DeepSeek, Claude thinking) get the provider default instead.', 'siphon-store-analytics-for-woocommerce');
	}

	var backendHelp;
	if (!usesCore) {
		backendHelp = __('Sends requests straight to the provider configured below. Required before WordPress 7.0, for self-hosted endpoints such as Ollama or vLLM, and when AI is disabled with WP_AI_SUPPORT.', 'siphon-store-analytics-for-woocommerce');
	} else if (coreAiSupported) {
		backendHelp = __('WordPress routes each request to the provider connected under Settings \u2192 Connectors. This plugin stores no API key of its own on this path.', 'siphon-store-analytics-for-woocommerce');
	} else {
		// Inline link: the sentence is split so the anchor stays a separate node.
		backendHelp = [
			__('No AI provider is connected yet. Add one under Settings \u2192 Connectors', 'siphon-store-analytics-for-woocommerce'),
			connectorsUrl
				? el('a', { key: 'connectors', href: connectorsUrl }, __(' (open Connectors)', 'siphon-store-analytics-for-woocommerce'))
				: null,
			__(' \u2014 until then, asking a question returns an error. Or switch to your own API key below.', 'siphon-store-analytics-for-woocommerce'),
		];
	}

	return el('div', { className: 'silc-wia-settings-panel' },

		// ---------------------------------------------------------------
		// SECTION 1 — AI Provider
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\uD83E\uDD16 ',
				__('AI Provider', 'siphon-store-analytics-for-woocommerce')
			),
			el('div', { className: 'silc-wia-settings-section-desc' },
				__('Choose which AI service powers your insights. OpenAI-compatible providers and Claude (Anthropic) are supported.', 'siphon-store-analytics-for-woocommerce')
			),

			// Backend: WordPress AI Client vs. a key saved in this plugin.
			el(SettingsField, {
				label: __('Backend', 'siphon-store-analytics-for-woocommerce'),
				help: backendHelp,
			},
				el('select', {
					value: backendChoice,
					onChange: function (e) { updateSetting('ai_backend', e.target.value); },
				}, backendOptions)
			),

			// Provider
			usesCore ? null : el(SettingsField, {
				label: __('Provider', 'siphon-store-analytics-for-woocommerce'),
				help: __('Pick a provider to fill in its API URL and default model, or choose Custom to enter any other endpoint.', 'siphon-store-analytics-for-woocommerce'),
			},
				el('select', {
					value: providerSlug,
					onChange: function (e) { handleProviderChange(updateSetting, formSettings, e); },
				},
					Object.keys(PROVIDERS).map(function (slug) {
						// Provider names are trademarks; only "Custom" is translated.
						return el('option', { key: slug, value: slug },
							slug === 'custom'
								? __('Custom', 'siphon-store-analytics-for-woocommerce')
								: PROVIDERS[slug].label
						);
					})
				)
			),

			// API URL
			usesCore ? null : el(SettingsField, {
				label: __('API URL', 'siphon-store-analytics-for-woocommerce'),
				help: (providerDef.url
					? __('Filled in from the provider you selected. Edit it only if you use a proxy or gateway.', 'siphon-store-analytics-for-woocommerce')
					: __('The address of your AI provider\u2019s API, for example http://localhost:11434/v1 for Ollama.', 'siphon-store-analytics-for-woocommerce'))
					+ ' ' + __('HTTPS is required unless the endpoint is on this machine or your local network.', 'siphon-store-analytics-for-woocommerce'),
			},
				el('input', {
					type: 'url',
					value: formSettings.api_url,
					placeholder: providerDef.url || 'https://api.example.com/v1',
					onChange: function (e) { updateSetting('api_url', e.target.value); },
				})
			),

			// Model. On both backends this is a preference, never a hard
			// requirement, so it can be left empty without breaking a request.
			el(SettingsField, {
				label: __('Model', 'siphon-store-analytics-for-woocommerce'),
				help: usesCore
					? __('Preferred model id. WordPress uses it when the provider connected under Settings \u2192 Connectors offers that model, and otherwise picks one itself \u2014 so a wrong id never breaks a request. Leave it empty to always let WordPress choose.', 'siphon-store-analytics-for-woocommerce')
					: __('The model id exactly as your provider lists it.', 'siphon-store-analytics-for-woocommerce')
						+ (providerDef.model
							? ' ' + (
								/* translators: 1: provider (brand) name, 2: default model id. */
								sprintf(__('The default for %1$s is %2$s.', 'siphon-store-analytics-for-woocommerce'), providerDef.label, providerDef.model)
							)
							: ''),
			},
				el('input', {
					type: 'text',
					value: formSettings.model,
					placeholder: usesCore ? __('Leave empty to let WordPress choose', 'siphon-store-analytics-for-woocommerce') : providerDef.model,
					onChange: function (e) { updateSetting('model', e.target.value); },
				})
			),
		),

		// ---------------------------------------------------------------
		// SECTION 2 — Authentication (own API key only)
		// ---------------------------------------------------------------
		usesCore ? null : el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\uDDD1\uFE0F\u200D\uD83D\uDD12 ',
				__('Authentication', 'siphon-store-analytics-for-woocommerce')
			),
			el('div', { className: 'silc-wia-settings-section-desc' },
				__('Your API key is stored securely in your WordPress database and is only sent to the API URL above. This plugin makes no other external requests.', 'siphon-store-analytics-for-woocommerce')
			),

			// API Key
			el(SettingsField, {
				label: __('API Key', 'siphon-store-analytics-for-woocommerce'),
				help: keyHelp,
			},
				el('input', {
					type: 'password',
					value: formSettings.api_key,
					placeholder: hasKey
						? __('API key is saved \u2014 enter new one to replace', 'siphon-store-analytics-for-woocommerce')
						: (providerDef.keyPlaceholder || 'sk-...'),
					onChange: function (e) { updateSetting('api_key', e.target.value); },
					autoComplete: 'off',
				}),
				hasKey && !formSettings.api_key
					? el('div', { className: 'has-key-indicator' }, '\u2713 ', __('API key is saved and active.', 'siphon-store-analytics-for-woocommerce'))
					: null,
				// Link to get a key
				el('div', { className: 'silc-wia-settings-key-links' },
					el('a', {
						href: 'https://platform.openai.com/api-keys',
						target: '_blank',
						rel: 'noopener noreferrer',
					}, __('Get an OpenAI API key \u2197', 'siphon-store-analytics-for-woocommerce')),
					' \u00B7 ',
					el('a', {
						href: 'https://docs.anthropic.com/en/docs/api-keys',
						target: '_blank',
						rel: 'noopener noreferrer',
					}, __('Anthropic \u2197', 'siphon-store-analytics-for-woocommerce')),
					' \u00B7 ',
					el('a', {
						href: 'https://platform.deepseek.com/api_keys',
						target: '_blank',
						rel: 'noopener noreferrer',
					}, __('DeepSeek \u2197', 'siphon-store-analytics-for-woocommerce')),
				)
			),
		),

		// ---------------------------------------------------------------
		// SECTION 3 — Advanced
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\u2699\uFE0F ',
				__('Advanced', 'siphon-store-analytics-for-woocommerce')
			),

			// Max Tokens
			el(SettingsField, {
				label: __('Max Tokens', 'siphon-store-analytics-for-woocommerce'),
				help: __('Maximum length of the AI\u2019s response, in tokens. Higher values allow longer answers and chart descriptions. The minimum is 800 so the JSON reply is not cut off mid-object.', 'siphon-store-analytics-for-woocommerce')
					+ (usesCore ? ' ' + __('With the WordPress AI Client at least 2000 tokens are requested, as far as the connected provider allows.', 'siphon-store-analytics-for-woocommerce') : ''),
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
				label: __('Temperature', 'siphon-store-analytics-for-woocommerce'),
				help: __('Controls how creative the AI is. Lower values (0.1\u20130.3) produce more consistent SQL. Higher values (0.7+) can be more creative but less reliable. Only used when a temperature is sent (see below).', 'siphon-store-analytics-for-woocommerce'),
			},
				el(NumberField, {
					value: formSettings.temperature,
					placeholder: defaults.temperature,
					min: 0, max: 2, step: 0.1,
					onChange: function (v) { updateSetting('temperature', v); },
				})
			),

			// Whether a temperature is sent at all. Recent OpenAI reasoning
			// models reject any value, so this is a choice rather than a
			// capability map the plugin has to keep up to date.
			el(SettingsField, {
				label: __('Send temperature', 'siphon-store-analytics-for-woocommerce'),
				help: tempModeHelp,
			},
				el('select', {
					value: formSettings.temperature_mode || 'auto',
					onChange: function (e) { updateSetting('temperature_mode', e.target.value); },
				},
					el('option', { key: 'auto', value: 'auto' }, __('Automatic (recommended)', 'siphon-store-analytics-for-woocommerce')),
					el('option', { key: 'always', value: 'always' }, __('Always', 'siphon-store-analytics-for-woocommerce')),
					el('option', { key: 'never', value: 'never' }, __('Never', 'siphon-store-analytics-for-woocommerce'))
				)
			),

			// Reasoning effort. Sent as the provider's own reasoning option;
			// an unsupported level is reported by the provider and the request
			// is retried without it.
			el(SettingsField, {
				label: __('Reasoning effort', 'siphon-store-analytics-for-woocommerce'),
				help: __('How much the model should think before answering. "Provider default" sends nothing at all, which always works. A level your model does not offer returns an error naming the levels it does support \u2014 the plugin then retries without it, and you can pick a supported level here or leave the default.', 'siphon-store-analytics-for-woocommerce'),
			},
				el('select', {
					value: formSettings.reasoning_effort || '',
					onChange: function (e) { updateSetting('reasoning_effort', e.target.value); },
				},
					el('option', { key: 'default', value: '' }, __('Provider default (do not send)', 'siphon-store-analytics-for-woocommerce')),
					reasoningEfforts.map(function (effort) {
						// Level ids are API values, shown capitalised as-is.
						return el('option', { key: effort, value: effort }, effort.charAt(0).toUpperCase() + effort.slice(1));
					})
				)
			),
		),

		// ---------------------------------------------------------------
		// SECTION 4 — Caching
		// ---------------------------------------------------------------
		el('div', { className: 'silc-wia-settings-section' },
			el('div', { className: 'silc-wia-settings-section-title' },
				'\uD83D\uDCBE ',
				__('Caching', 'siphon-store-analytics-for-woocommerce')
			),
			el('div', { className: 'silc-wia-settings-section-desc' },
				__('Results are cached so asking the same question again loads instantly. Set the duration below, or set to 0 to disable caching.', 'siphon-store-analytics-for-woocommerce')
			),

			// Cache Duration
			el(SettingsField, {
				label: __('Cache Duration', 'siphon-store-analytics-for-woocommerce'),
				help: __('How long to remember results for the same question. Recommended: 1\u20136 hours. Set to 0 to disable caching entirely.', 'siphon-store-analytics-for-woocommerce'),
			},
				el('div', { className: 'silc-wia-cache-row' },
					el(NumberField, {
						// 0 is a valid value ("caching off"), so it must not fall
						// back to the default here.
						value: Math.round((parseInt(formSettings.cache_ttl, 10) || 0) / 60),
						min: 0, max: 10080, step: 15,
						onChange: function (v) {
							var minutes = parseInt(v, 10) || 0;
							updateSetting('cache_ttl', Math.max(0, Math.min(10080, minutes)) * 60);
						},
					}),
					el('span', { className: 'silc-wia-cache-unit' }, __('minutes', 'siphon-store-analytics-for-woocommerce')),
				),
				// Quick presets
				el('div', { className: 'silc-wia-cache-presets' },
					el('span', { className: 'label' }, __('Quick:', 'siphon-store-analytics-for-woocommerce')),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 0); } }, __('Off', 'siphon-store-analytics-for-woocommerce')),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 30 * 60); } }, __('30 min', 'siphon-store-analytics-for-woocommerce')),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 3600); } }, __('1 hour', 'siphon-store-analytics-for-woocommerce')),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 6 * 3600); } }, __('6 hours', 'siphon-store-analytics-for-woocommerce')),
					el('span', { className: 'preset', onClick: function () { updateSetting('cache_ttl', 86400); } }, __('24 hours', 'siphon-store-analytics-for-woocommerce')),
				)
			),
		),

		// ACTIONS
		el('div', { className: 'silc-wia-settings-actions' },
			el(Button, {
				isPrimary: true,
				onClick: handleSaveSettings,
				disabled: saving || !settingsDirty,
			}, saving
				? __('Saving\u2026', 'siphon-store-analytics-for-woocommerce')
				: __('Save Settings', 'siphon-store-analytics-for-woocommerce')),
			el(Button, {
				isSecondary: true,
				onClick: handleTestConnection,
				disabled: testing || connectionDirty,
				title: connectionDirty
					? __('Save your changes first \u2014 the connection test uses the saved settings.', 'siphon-store-analytics-for-woocommerce')
					: undefined,
			}, testing
				? __('Testing\u2026', 'siphon-store-analytics-for-woocommerce')
				: __('Test Connection', 'siphon-store-analytics-for-woocommerce')),
			connectionDirty
				? el('span', { className: 'silc-wia-test-hint' },
					__('Save your changes to test this configuration.', 'siphon-store-analytics-for-woocommerce'))
				: null,
			testResult
				? el('span', {
					className: 'silc-wia-test-result ' + (testResult.type === 'success' ? 'success' : 'fail'),
					key: testResult.text,
				}, (testResult.type === 'success' ? '\u2713 ' : '\u2717 ') + testResult.text)
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
