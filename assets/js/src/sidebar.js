/**
 * Siphon Store Analytics for WooCommerce — Sidebar component
 *
 * @package SILC_WooInsight_AI
 */

/* global wp */

var el = wp.element.createElement;
var __ = wp.i18n.__;

/**
 * A single sidebar icon item.
 *
 * @param {Object}  props
 * @param {string}  props.icon   Dashicon class suffix.
 * @param {string}  props.label  Display label.
 * @param {string}  props.panel  Panel key.
 * @param {boolean} props.active Whether this item is active.
 * @param {Function} props.onClick Click handler.
 */
function SidebarItem(props) {
	var icon = props.icon;
	var label = props.label;
	var panel = props.panel;
	var isActive = props.activePanel === panel;
	var openPanel = props.openPanel;
	var onClick = props.onClick;
	var isDisabled = !! props.disabled;

	return el('div', {
		className: 'silc-wia-sidebar-item' + (isActive ? ' active' : ''),
		onClick: function () {
			if (isDisabled) {
				return;
			}
			// Items with an explicit handler (e.g. New chat) run it instead of
			// opening a panel.
			if (onClick) {
				onClick();
				return;
			}
			openPanel(panel);
		},
		title: label,
		style: isDisabled ? { opacity: 0.5, cursor: 'default' } : null,
	},
		el('span', { className: 'dashicons dashicons-' + icon }),
		el('span', { className: 'label' }, label)
	);
}

/**
 * Render the sidebar.
 *
 * @param {Object}   props
 * @param {boolean}  props.sidebarExpanded
 * @param {Function} props.toggleSidebar
 * @param {Function} props.openPanel
 * @param {string|null} props.activePanel
 * @param {Function} props.onNewChat
 * @param {boolean}  props.newChatDisabled
 * @return {Object} Element.
 */
export function renderSidebar(props) {
	var sidebarExpanded = props.sidebarExpanded;
	var toggleSidebar = props.toggleSidebar;

	var items = [
		{
			key: 'new-chat',
			icon: 'plus',
			label: __('New Chat', 'siphon-store-analytics-for-woocommerce'),
			onClick: props.onNewChat,
			disabled: props.newChatDisabled,
		},
		{ key: 'history', icon: 'backup', label: __('History', 'siphon-store-analytics-for-woocommerce'), panel: 'history' },
		{ key: 'library', icon: 'book-alt', label: __('Library', 'siphon-store-analytics-for-woocommerce'), panel: 'library' },
		{ key: 'suggested', icon: 'lightbulb', label: __('Suggested', 'siphon-store-analytics-for-woocommerce'), panel: 'suggested' },
		{ key: 'sql', icon: 'editor-code', label: __('SQL', 'siphon-store-analytics-for-woocommerce'), panel: 'sql' },
	];

	return el('div', {
		className: 'silc-wia-sidebar ' + (sidebarExpanded ? 'expanded' : 'collapsed'),
	},
		// Toggle button.
		el('div', {
			className: 'silc-wia-sidebar-toggle',
			onClick: toggleSidebar,
			title: sidebarExpanded
				? __('Collapse sidebar', 'siphon-store-analytics-for-woocommerce')
				: __('Expand sidebar', 'siphon-store-analytics-for-woocommerce'),
		},
			el('span', { className: 'dashicons dashicons-menu' })
		),
		// Nav items.
		items.map(function (item) {
			return el(SidebarItem, {
				key: item.key,
				icon: item.icon,
				label: item.label,
				panel: item.panel,
				onClick: item.onClick,
				disabled: item.disabled,
				activePanel: props.activePanel,
				openPanel: props.openPanel,
			});
		}),
		// Spacer.
		el('div', { className: 'silc-wia-sidebar-spacer' }),
		// Settings at bottom.
		el(SidebarItem, {
			icon: 'admin-generic',
			label: __('Settings', 'siphon-store-analytics-for-woocommerce'),
			panel: 'settings',
			activePanel: props.activePanel,
			openPanel: props.openPanel,
		})
	);
}
