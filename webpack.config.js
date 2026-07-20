const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Add one line per page as each faz is implemented.
		// Faz 0: dashboard entry (placeholder only — no PHP render change yet).
		//
		// Task A11a (WP.org T4 seam inversion) moved the 5 Pro-only bundles --
		// reports, messages, vendor-reports, vendor-management, export -- to
		// mhm-rentiva-pro/src-react/admin/. Lite only builds its own screens now.
		'admin/dashboard': './src-react/admin/dashboard/index.js',
		'admin/customers':  './src-react/admin/customers/index.js',
		'admin/about':           './src-react/admin/about/index.js',
		'admin/shortcode-pages': './src-react/admin/shortcode-pages/index.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
