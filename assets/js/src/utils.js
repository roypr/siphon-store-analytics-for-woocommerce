/**
 * Siphon Store Analytics for WooCommerce — Utility functions and constants
 *
 * @package SILC_WooInsight_AI
 */

/* global wp */

var __ = wp.i18n.__;

/**
 * Perform an AJAX action via POST.
 *
 * @param {string} action Action suffix (without 'silc_wia_' prefix).
 * @param {Object} extra  Extra form data fields.
 * @return {Promise<Object>} Parsed JSON response.
 */
export function doAction(action, extra) {
	var nonce = (window.silcWiaData && window.silcWiaData.nonce) || '';
	var ajaxUrl = (window.silcWiaData && window.silcWiaData.ajaxUrl) || '';
	var formData = new FormData();
	formData.append('action', 'silc_wia_' + action);
	formData.append('nonce', nonce);
	if (extra) {
		Object.keys(extra).forEach(function (key) {
			formData.append(key, extra[key]);
		});
	}
	return fetch(ajaxUrl, { method: 'POST', body: formData }).then(function (r) {
		return r.json();
	});
}

/**
 * Global data shortcut.
 */

/**
 * Global data shortcut.
 */
export var data = window.silcWiaData || {};
export var settings = data.settings || {};
export var defaults = data.defaults || {};
export var apiConfigured = data.apiConfigured || false;
export var pluginUrl = data.pluginUrl || '';
export var pluginVersion = data.pluginVersion || '2.0.0';

/**
 * AI backend selection.
 *
 * 'core' = WordPress AI Client (7.0+, provider connected under Settings →
 * Connectors); 'byok' = a key saved in this plugin. The server resolves the
 * effective backend, so these values always describe what will actually run.
 */
export var aiBackend = data.aiBackend || 'byok';
export var aiBackendSetting = typeof data.aiBackendSetting === 'string' ? data.aiBackendSetting : '';
export var coreAiAvailable = data.coreAiAvailable || false;
export var coreAiSupported = data.coreAiSupported || false;
export var connectorsUrl = data.connectorsUrl || '';

/**
 * Reasoning levels offered in Settings.
 *
 * Server-owned (SILC_WIA_API::REASONING_EFFORTS) so the accepted values and the
 * sanitised values cannot drift. This is the union of what the major providers
 * document, not a per-model map: an unsupported level is rejected by the
 * provider and the request is retried without it.
 */
export var reasoningEfforts = data.reasoningEfforts || ['none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'];

/**
 * Suggested prompts that produce different result types.
 *
 * These are translated: the chip sends its text as the question, so a user in a
 * non-English locale asks in their own language rather than in English. The
 * generated SQL is validated the same way regardless of the question language.
 */
export var SUGGESTED_PROMPTS = [
	{ text: __('Best selling products last month', 'siphon-store-analytics-for-woocommerce'), icon: '\uD83D\uDCCA', type: 'chart' },
	{ text: __('Monthly revenue trend this year', 'siphon-store-analytics-for-woocommerce'), icon: '\uD83D\uDCC8', type: 'chart' },
	{ text: __('Order status distribution', 'siphon-store-analytics-for-woocommerce'), icon: '\uD83E\uDDFE', type: 'chart' },
	{ text: __('Top 10 customers by spending', 'siphon-store-analytics-for-woocommerce'), icon: '\uD83C\uDFC6', type: 'list' },
	{ text: __('Total revenue yesterday', 'siphon-store-analytics-for-woocommerce'), icon: '\uD83D\uDCB0', type: 'answer' },
	{ text: __('Products low in stock', 'siphon-store-analytics-for-woocommerce'), icon: '\u26A0\uFE0F', type: 'list' },
];

/**
 * Pre-built library items loaded from the server.
 * Each item has the full AI-response format: id, question, sql, type,
 * title, chart_config, list_config, answer_text.
 */
export var LIBRARY_ITEMS = data.libraryItems || [];

/**
 * Human-readable label for a result type returned by the server.
 *
 * @param {string} type Result type ('chart', 'list', 'answer').
 * @return {string} Translated label, or the raw value when unknown.
 */
export function getTypeLabel(type) {
	if (type === 'chart') {
		return __('Chart', 'siphon-store-analytics-for-woocommerce');
	}
	if (type === 'list') {
		return __('List', 'siphon-store-analytics-for-woocommerce');
	}
	if (type === 'answer') {
		return __('Answer', 'siphon-store-analytics-for-woocommerce');
	}
	return type || __('Unknown', 'siphon-store-analytics-for-woocommerce');
}

/**
 * Map of error codes to user-friendly messages.
 *
 * The server already returns a translated message for most errors, so these are
 * only the fallback text for a code that arrives without one.
 */
export var ERROR_MESSAGES = {
	FORBIDDEN: __('This action is not allowed. I can only answer questions about your store data \u2014 I cannot modify or delete anything.', 'siphon-store-analytics-for-woocommerce'),
	OUT_OF_SCOPE: __('I can only help with WooCommerce store questions. Try asking about sales, products, orders, or customers.', 'siphon-store-analytics-for-woocommerce'),
	NOT_WOOCOMMERCE: __('I only work with WooCommerce store data. That request involves non-store content I cannot access.', 'siphon-store-analytics-for-woocommerce'),
	SQL_VALIDATION: __('The generated query could not be validated for safety.', 'siphon-store-analytics-for-woocommerce'),
	QUERY_ERROR: __('The query ran into a database error. Please try rephrasing your question.', 'siphon-store-analytics-for-woocommerce'),
};

/**
 * Get a user-friendly error message for an error code.
 *
 * @param {string} errorCode
 * @param {string} fallback
 * @return {string}
 */
export function getErrorMessage(errorCode, fallback) {
	if (errorCode && ERROR_MESSAGES[errorCode]) {
		return ERROR_MESSAGES[errorCode];
	}
	return fallback || __('Something went wrong.', 'siphon-store-analytics-for-woocommerce');
}
