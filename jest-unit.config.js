/**
 * Project override of @wordpress/scripts's default jest-unit config.
 *
 * Why this file exists: jest-environment-jsdom defaults
 * `testEnvironmentOptions.customExportConditions` to `[ 'browser' ]`. Some
 * packages' `exports` maps nest their real (Node-usable) entry under a
 * `"node"` condition -- e.g. uuid@8 (a transitive dependency of
 * @wordpress/components, pinned via this package.json's `overrides` because
 * @wordpress/components' own `uuid: ^14.0.0` ships NO CommonJS build at all,
 * ESM-only, which Jest's CJS-only module loader cannot require()) resolves
 * "." to `dist/esm-browser/index.js` (ESM) under jsdom's default `browser`
 * condition alone, and only to the real `dist/index.js` (CommonJS) when a
 * `node` condition is also present. Without `node` in the set, Jest fails
 * parsing that ESM file with "Unexpected token 'export'" the moment any
 * @wordpress/components import chain reaches it.
 *
 * Adding `require`/`node` here does not remove `browser` matching for
 * packages that only publish a browser condition (jest-resolve unions this
 * list with `['require','default']` per module type -- see jest-runtime's
 * cjsConditions/esmConditions -- so `browser` still applies for jsdom-only
 * packages; this only ADDS the ability to also match a `node` condition).
 *
 * Otherwise byte-for-byte @wordpress/scripts's own config/jest-unit.config.js
 * -- wp-scripts only reads a project override wholesale (it does not merge
 * an override with its own default), so the rest is copied rather than
 * imported to keep this file self-contained.
 */
const path = require( 'path' );
const { hasBabelConfig } = require( '@wordpress/scripts/utils' );

const wpScriptsConfigDir = path.join(
	path.dirname( require.resolve( '@wordpress/scripts/package.json' ) ),
	'config'
);

const jestUnitConfig = {
	preset: '@wordpress/jest-preset-default',
	reporters: [
		'default',
		path.join( wpScriptsConfigDir, 'jest-github-actions-reporter', 'index.js' ),
	],
	testEnvironmentOptions: {
		customExportConditions: [ 'browser', 'require', 'node' ],
	},
};

if ( ! hasBabelConfig() ) {
	jestUnitConfig.transform = {
		'\\.[jt]sx?$': path.join( wpScriptsConfigDir, 'babel-transform' ),
	};
}

module.exports = jestUnitConfig;
