/**
 * Build the WordPress.org distribution folder.
 *
 * Copies only the files that belong in the released plugin into
 * dist/<plugin-slug>/, mirroring the rules in .distignore. Deliberately
 * dependency-free so it runs anywhere Node does — zip the output folder, or
 * point wp-cli's `wp dist-archive` at .distignore instead.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SLUG = 'store-insights-for-woocommerce';
const OUT = path.join(ROOT, 'dist', SLUG);

// Everything not listed here is development-only and must not ship.
const INCLUDE = [
	'store-insights-for-woocommerce.php',
	'uninstall.php',
	'readme.txt',
	'LICENSE',
	'includes',
	'assets/css',
	'assets/img',
	'assets/js/chart-renderer.js',
	'assets/js/dashboard.js',
	'assets/js/dashboard.asset.php',
	'assets/lib/chart.min.js',
];

function copy(relative) {
	const from = path.join(ROOT, relative);
	const to = path.join(OUT, relative);

	if (!fs.existsSync(from)) {
		throw new Error('Missing required file: ' + relative);
	}

	fs.cpSync(from, to, { recursive: true });
	console.log('  + ' + relative);
}

fs.rmSync(path.join(ROOT, 'dist'), { recursive: true, force: true });
fs.mkdirSync(OUT, { recursive: true });

console.log('Building dist/' + SLUG + '/');
INCLUDE.forEach(copy);
console.log('Done. Zip the folder above to produce the submission archive.');
