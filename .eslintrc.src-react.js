/**
 * Gate G-F -- house style and accessibility over the maintained React surface.
 *
 * WHY A SEPARATE FILE, AND WHY IT IS NEVER DISCOVERED
 * --------------------------------------------------
 * This config is passed explicitly (`--no-eslintrc --config`) by the `lint:js`
 * script and by nothing else. It is deliberately NOT named `.eslintrc.js`:
 * that name outranks the committed `.eslintrc.json` in ESLint's discovery
 * order, and `.eslintrc.json` is the project's single declaration of `env`
 * (browser, jquery) and of ~60 project globals. A discovered `.eslintrc.js`
 * would silently shadow all of it for editors and for any future tool that
 * relies on discovery — while gate G-E, which reads `.eslintrc.json` by path,
 * kept working and would not have reported the loss. So the file extends
 * `.eslintrc.json` instead of competing with it.
 *
 * WHAT IT ADDS, AND WHAT ALREADY EXISTED
 * --------------------------------------
 * G-E (`composer check-js-correctness`) already enforces `eslint:recommended`
 * — the "these are probably bugs" set — over the whole shipped JS surface, and
 * deliberately does not enforce style. That decision is right for the legacy
 * `assets/` tree (74 files, ~14k findings). It is not right for `src-react/`,
 * which is maintained code: this gate adds the `@wordpress/eslint-plugin`
 * house rules and `jsx-a11y` there, and only there.
 *
 * Measured 2026-09-01 before the gate existed: `npm run lint:js` was never
 * called by any CI job, and lint-js with no path argument lints `.`, reporting
 * 18661 problems across 115 files — unusable as a gate. Scoped to `src-react/`
 * it was 2116, of which 2082 were `prettier/prettier` and 34 substantive.
 * Those 34 were fixed; this file records why three of them were answered by
 * configuration rather than by editing code.
 *
 * SCOPE DECISIONS
 * ---------------
 * 1. `prettier/prettier` is OFF. Enabling it reformats 35 files in one
 *    unreviewed diff; formatting is its own round, not a correctness gate.
 * 2. `eqeqeq` allows `== null` / `!= null`. That idiom is CORRECT here and the
 *    naive "fix" would have shipped a defect: `b.total_price != null` catches
 *    both null and undefined, so rewriting it to `!==` would render `0,00`
 *    instead of an em dash whenever the field is absent.
 * 3. `no-alert` is OFF. `window.confirm` guards the bulk-delete flow in
 *    CustomersPage; it is a native, keyboard-accessible primitive. Replacing
 *    it with the in-app ConfirmModal changes what the operator sees and needs
 *    visual approval — not a decision a lint rule makes. Open UX item; if the
 *    modal is adopted, delete this line rather than leaving it standing.
 * 4. `jsdoc/require-param` is off for JSX. It demands `@param root0` docs for
 *    destructured props, which is noise, not information.
 *
 * The remaining rules are enforced, and a probe file carrying `curly`,
 * `no-nested-ternary`, `eqeqeq` (non-null) and `no-undef` violations was run
 * through this gate to confirm each one still fails it.
 */

module.exports = {
	root: true,
	extends: [ './.eslintrc.json' ],
	rules: {
		'prettier/prettier': 'off',
		'no-alert': 'off',
		eqeqeq: [ 'error', 'always', { null: 'ignore' } ],
		// `properties: 'never'` alone is not enough: payload keys arrive by
		// destructuring, which creates identifiers, so `ignoreDestructuring`
		// is the half that applies. Renaming at the destructuring boundary
		// (`page_url: pageUrl`) is the fix used in the code itself.
		camelcase: [
			'error',
			{ properties: 'never', ignoreDestructuring: true },
		],
	},
	overrides: [
		{
			files: [ '**/*.jsx' ],
			rules: {
				'jsdoc/require-param': 'off',
			},
		},
	],
};
