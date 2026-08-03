<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;

/**
 * T8 Görev 5 (F21): the checker is a standalone script
 * (bin/check-template-literal-markup.php); these tests exercise its pure
 * collector by requiring the file (mirrors GuardedRefsCheckerTest, its
 * neighbour in this directory), plus keep the whole shipped JS surface
 * under permanent regression coverage.
 *
 * Background: headless-Chrome evidence proved
 * assets/js/components/addon-booking.js built its addon-total breakdown from
 * template literals typed with stray whitespace around tag delimiters --
 * `< div class = "total-line" >` instead of `<div class="total-line">`. Per
 * the WHATWG tokenizer spec, `<` not immediately followed by `!`, `/`, a
 * letter, or `?` aborts tag-open state, so the whole fragment renders as
 * visible page text instead of building DOM nodes. This is a source scan on
 * purpose, mirroring tests/Tools/NoProMentionInLiteTest.php and
 * tests/Unit/Build/NoProFeatureAssetsTest.php: it must catch the shape even
 * on a surface nobody thought to render in a browser test.
 *
 * The first six test methods below ARE the brief's prescribed calibration,
 * made permanent: a naive whole-file regex for this shape (tried first, per
 * the brief) matches ordinary comparisons like `value > 0` throughout the
 * codebase. The checker must never do that, on ANY of the several ways a
 * comparison can sit near a template literal without being markup text.
 *
 * @package MHMRentiva\Tests\Tools
 */
final class TemplateLiteralMarkupCheckerTest extends TestCase {

	/**
	 * The bin script's require() contract: `array('collect' => ...,
	 * 'shipped_js_files' => ...)`. Required exactly once per call site (PHP
	 * caches nothing between calls, but each require() re-runs the whole
	 * script; that cost is negligible here and keeps this test hermetic
	 * rather than caching state on the instance).
	 *
	 * Fixed in response to T8 Görev 5 review Important-1: this test used to
	 * have its OWN private `shipped_js_files()`, independently re-walking
	 * the same three hardcoded roots the bin script's CLI mode also walks --
	 * two copies of the same glob that could silently drift from each other.
	 * Both `collector()` and `shipped_js_files_walker()` below now pull from
	 * this single require(), so there is exactly one implementation of the
	 * walk in the codebase (see bin/check-template-literal-markup.php's
	 * `$find_shipped_js_files` docblock for the remaining, narrower risk this
	 * does NOT eliminate -- drift from `.distignore` itself -- and how that
	 * one is guarded instead, via `--verify-scope`).
	 *
	 * @return array{collect: callable(string): list<array{line:int,pattern:string,excerpt:string}>, shipped_js_files: callable(): list<string>}
	 */
	private function bin_script(): array {
		return require dirname( __DIR__, 2 ) . '/bin/check-template-literal-markup.php';
	}

	/**
	 * @return callable(string): list<array{line:int,pattern:string,excerpt:string}>
	 */
	private function collector(): callable {
		return $this->bin_script()['collect'];
	}

	private function plugin_root(): string {
		return rtrim( dirname( __DIR__, 2 ), '/\\' ) . '/';
	}

	// ---- calibration: the exact false-positive class the brief names ------

	/**
	 * The brief's own example. A naive whole-file regex catches this; scoping
	 * to backtick-literal text alone (this code has no template literals at
	 * all) already clears it.
	 */
	public function test_ignores_ordinary_comparison_outside_template_literals(): void {
		$code = <<<'JS'
function f( value, a, b ) {
	if ( value > 0 ) {
		return 1;
	}
	return a < b;
}
JS;

		$this->assertSame( array(), ( $this->collector() )( $code ) );
	}

	/**
	 * The harder half of the brief's requirement: a comparison that sits
	 * INSIDE a backtick block, but inside a `${...}` interpolation -- JS
	 * expression code, not literal output text. Scoping to "inside
	 * backticks" alone is not sufficient for this shape; interpolations must
	 * be excised too.
	 */
	public function test_ignores_comparison_inside_interpolation_expression(): void {
		$code = <<<'JS'
const s = `total: ${a < b ? 1 : 2}, other: ${x > 0 ? 3 : 4}`;
JS;

		$this->assertSame( array(), ( $this->collector() )( $code ) );
	}

	/**
	 * Real, shipped example of the same principle (found during calibration,
	 * src-react/shared/format.js:23): the comparison sits immediately before
	 * the template literal, not inside it -- covered by the "outside
	 * backticks" restriction alone, but worth pinning verbatim since it is
	 * the plugin's own naturally-occurring instance of the brief's example.
	 */
	public function test_ignores_comparison_immediately_preceding_a_template_literal(): void {
		$code = <<<'JS'
return dec > 0 ? `${ intFormatted }${ decimalSep }${ decPart }` : intFormatted;
JS;

		$this->assertSame( array(), ( $this->collector() )( $code ) );
	}

	/**
	 * Regression guard for a false positive this checker's own calibration
	 * found live in assets/vendor/swiper-bundle.min.js: a tag-name
	 * interpolation sitting directly against '<' with no space
	 * (`` `<${tag} class="${cls}">` ``) always evaluates to a letter-starting
	 * string, so the rendered markup is valid. An earlier version of this
	 * checker excised `${...}` to an EMPTY string, which deleted the
	 * interpolation outright and left '<' directly followed by the literal
	 * space that used to separate it from `class=` -- indistinguishable from
	 * the real bug. Interpolations must collapse to a single placeholder
	 * character, not nothing.
	 */
	public function test_ignores_interpolated_tag_name_touching_angle_bracket(): void {
		$code = <<<'JS'
const h = `<${tag} class="${cls}"></${tag}>`;
JS;

		$this->assertSame( array(), ( $this->collector() )( $code ) );
	}

	/**
	 * Regression guard for the OTHER real desync this checker's calibration
	 * found: this plugin's own HTML-escape idiom,
	 * `text.replace(/[&<>"']/g, ...)` (used in addon-booking.js itself,
	 * plus 4 sibling files), puts a bare '"' inside a regex character class.
	 * A scanner that does not recognise regex literals mistakes that '"' for
	 * the start of a string, and desyncs its string-tracking for the rest of
	 * the file -- silently corrupting every template-literal boundary found
	 * afterwards (a false NEGATIVE, the dangerous direction for a safety
	 * guard). This regex is placed directly before a template literal with
	 * genuinely broken markup, so a desync here would also swallow the
	 * broken tag below into the (mis-tracked) "string" and hide it.
	 */
	public function test_html_escape_regex_with_quoted_character_class_does_not_desync_the_scanner(): void {
		$code = <<<'JS'
function esc( s ) {
	return s.replace( /[&<>"']/g, function ( m ) {
		return { '&': '&amp;', '"': '&quot;', "'": '&#039;' }[ m ];
	} );
}
el.append( `< div >broken</ div >` );
JS;

		$found = ( $this->collector() )( $code );

		$this->assertNotSame( array(), $found, 'The broken tag after the regex must still be found -- a desync would hide it.' );
		foreach ( $found as $hit ) {
			$this->assertSame( 6, $hit['line'], 'The only template literal in this fixture is on line 6.' );
		}
	}

	/**
	 * Well-formed markup inside a template literal, including a NESTED
	 * template literal inside a ternary's interpolated branch -- the exact
	 * shape live at assets/js/frontend/availability-calendar.js:710-715,
	 * `` `<div>...${cond ? `<span>...</span>` : ''}</div>` ``. The inner
	 * backticks must never be mistaken for the outer literal's own closing
	 * backtick, and the inner literal's own text must still be scanned (and
	 * found clean here).
	 */
	public function test_ignores_well_formed_markup_including_nested_template_in_interpolation(): void {
		$code = <<<'JS'
el.append(
	`<div class="price">
		<span class="amount">${amount}</span>
		${hasDiscount ? `<span class="badge">${pct}%</span>` : ''}
	</div>`
);
JS;

		$this->assertSame( array(), ( $this->collector() )( $code ) );
	}

	// ---- calibration: the checker must actually fire on the real shape ----

	/**
	 * Mutation/positive control: on a 3-line fixture simple enough to
	 * hand-verify, both prescribed patterns must fire, on the exact line the
	 * broken tag is on (line 2) -- proving the detector isn't merely
	 * satisfied by "some hit somewhere" but tracks position correctly across
	 * the excision arithmetic.
	 */
	public function test_flags_broken_open_and_close_tag_with_correct_line_number(): void {
		$code = "const s = `\n< div >\n`;\n";

		$found = ( $this->collector() )( $code );

		$this->assertCount( 2, $found );
		$this->assertSame( array( 'open', 'close' ), array_column( $found, 'pattern' ) );
		$this->assertSame( array( 2, 2 ), array_column( $found, 'line' ) );
	}

	/**
	 * The open-tag pattern (`<` then whitespace then a letter) must fire
	 * independently of the close-tag pattern.
	 */
	public function test_flags_broken_open_tag_alone(): void {
		$code = 'el.append(`< div class="x"></div>`);';

		$found = ( $this->collector() )( $code );

		$this->assertSame( array( 'open' ), array_column( $found, 'pattern' ) );
	}

	/**
	 * The close-tag pattern (a letter/quote then whitespace then `>`) must
	 * fire independently of the open-tag pattern.
	 */
	public function test_flags_broken_close_tag_alone(): void {
		$code = 'el.append(`<div class="x"></div >`);';

		$found = ( $this->collector() )( $code );

		$this->assertSame( array( 'close' ), array_column( $found, 'pattern' ) );
	}

	// ---- calibration against real shipped files (brief-prescribed step) ---

	/**
	 * The brief's "known-clean file" calibration step, pinned against the
	 * real file: substantial real-world template-literal usage (multiple
	 * `<div>`/`<span>`/`<button>` blocks) including the nested-template-in-
	 * interpolation shape, all well-formed. Chosen because it is the richest
	 * real HTML-in-template-literals user in the shipped JS tree.
	 */
	public function test_known_clean_file_availability_calendar_has_zero_offenders(): void {
		$path = $this->plugin_root() . 'assets/js/frontend/availability-calendar.js';
		$this->assertFileExists( $path );

		$found = ( $this->collector() )( (string) file_get_contents( $path ) );

		$this->assertSame( array(), $found );
	}

	/**
	 * Second real-file calibration target: contains the brief's own
	 * `value > 0`-shaped comparison verbatim (line 23, `dec > 0 ? ... `),
	 * immediately beside (not inside) a template literal.
	 */
	public function test_known_clean_file_format_js_has_zero_offenders(): void {
		$path = $this->plugin_root() . 'src-react/shared/format.js';
		$this->assertFileExists( $path );

		$found = ( $this->collector() )( (string) file_get_contents( $path ) );

		$this->assertSame( array(), $found );
	}

	/**
	 * The fixed file itself. Before this task's fix, this file had 27
	 * offenders across 3 broken blocks (lines 177-180, 196-199, 205-208 at
	 * the pre-fix HEAD b1568525) -- captured in task-5-report.md's RED
	 * transcript. This assertion is what keeps it fixed.
	 */
	public function test_addon_booking_js_template_literals_are_well_formed(): void {
		$path = $this->plugin_root() . 'assets/js/components/addon-booking.js';
		$this->assertFileExists( $path );

		$found = ( $this->collector() )( (string) file_get_contents( $path ) );

		$this->assertSame( array(), $found );
	}

	// ---- KANUN 0: the whole shipped JS surface, kept clean going forward --

	/**
	 * Every *.js file this plugin ships -- delegates to the bin script's
	 * OWN `$find_shipped_js_files` (via `bin_script()` above), not a
	 * second, independent walk. Empirically verified (2026-08-03) to
	 * exactly reproduce `python bin/build-release.py --list-shipped`
	 * filtered to `.js` (88/88, zero diff); see that closure's docblock in
	 * bin/check-template-literal-markup.php for the full reasoning and the
	 * `--verify-scope` mechanism that guards against it drifting from
	 * `.distignore` over time.
	 *
	 * @return list<string> Absolute paths.
	 */
	private function shipped_js_files_walker(): array {
		return ( $this->bin_script()['shipped_js_files'] )();
	}

	/**
	 * Guards the scan itself: a glob/root mismatch that silently matched
	 * nothing would make the assertion below pass while scanning no code.
	 * 88 shipped .js files were measured at task time; a generous floor
	 * (80) tolerates ordinary future file churn without being fragile.
	 */
	public function test_the_scan_actually_reads_a_plausible_number_of_shipped_js_files(): void {
		$this->assertGreaterThanOrEqual( 80, count( $this->shipped_js_files_walker() ), 'The scan found implausibly few shipped JS files.' );
	}

	/**
	 * KANUN 0: the brief's sweep, kept permanent. Every shipped JS file --
	 * not just addon-booking.js -- must have zero tag-context breakage
	 * inside its template literals. At task time (2026-08-03) this scanned
	 * 88 files and found offenders in exactly one (addon-booking.js, 27,
	 * fixed by this same task); this assertion is what stops a 28th offender
	 * -- in any of these 88 files, or a 89th added later -- from shipping
	 * silently.
	 */
	public function test_no_shipped_js_file_has_template_literal_markup_offenders(): void {
		// shipped_js_files_walker() returns forward-slash-normalized absolute
		// paths (bin/check-template-literal-markup.php's
		// $find_shipped_js_files guarantee); only plugin_root() itself
		// (native-separator on Windows) needs normalizing before the prefix
		// strip -- this is purely for readable failure messages below, since
		// $offenders is asserted empty either way.
		$root      = str_replace( '\\', '/', $this->plugin_root() );
		$collect   = $this->collector();
		$offenders = array();

		foreach ( $this->shipped_js_files_walker() as $path ) {
			$code = (string) file_get_contents( $path );
			$hits = $collect( $code );
			if ( array() === $hits ) {
				continue;
			}

			$relative = 0 === strpos( $path, $root ) ? substr( $path, strlen( $root ) ) : $path;
			foreach ( $hits as $hit ) {
				$offenders[] = sprintf( '%s:%d [%s] %s', $relative, $hit['line'], $hit['pattern'], $hit['excerpt'] );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			"These shipped JS files have tag-context breakage inside a template literal:\n" . implode( "\n", $offenders )
		);
	}
}
