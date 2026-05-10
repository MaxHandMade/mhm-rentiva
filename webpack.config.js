const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Add one line per page as each faz is implemented.
		// Faz 0: dashboard entry (placeholder only — no PHP render change yet).
		'admin/dashboard': './src-react/admin/dashboard/index.js',
		'admin/reports':    './src-react/admin/reports/index.js',
		'admin/customers':  './src-react/admin/customers/index.js',
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
