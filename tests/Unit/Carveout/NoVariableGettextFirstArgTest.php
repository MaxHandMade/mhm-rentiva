<?php
/**
 * WP.org T4 #9 (Task B-G1h): gettext calls must never take a variable as their
 * first argument. `__( $tpl )`, `_e( $raw )`, etc. defeat the string
 * extractor -- the msgid it would need to look up in the catalog does not
 * exist until runtime -- so the call is never actually translatable, and
 * worse, it can pipe untrusted/dynamic content through the translation
 * layer as if it were a vetted literal.
 *
 * Two real offenders shipped in src/Admin/Emails/Core/Templates.php:
 *   - `__( $tpl, 'mhm-rentiva' )` at line 157, where $tpl was either an
 *     ALREADY-translated registry subject (translated once, via a literal
 *     __() call, at its definition site in Templates::registry()) or a
 *     dynamic 'Notification: {key}' fallback -- re-wrapping either case in
 *     __() was the bug.
 *   - `__( $raw, 'mhm-rentiva' )` at line 565, where $raw is an admin-typed
 *     subject override pulled straight from a WP option -- dynamic content
 *     that was never translatable via gettext in the first place.
 *
 * Both call sites now pass their value straight through with no __() wrapper.
 *
 * This test is the tree-wide backstop: it mirrors the task brief's
 * acceptance-bar grep --
 *   grep -rnE "(__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e|_x|_n)\(\s*\$" src/
 * -- against every PHP file under src/, so any future gettext-with-variable
 * call fails a test automatically instead of depending on someone re-running
 * the grep by hand.
 *
 * Deliberately excluded (per the brief, and not matched by the regex below
 * because the character right after the opening paren is a quote, not `$`):
 *   - sprintf( __( 'literal...' ), $var ) / printf(...) -- the gettext arg is
 *     a literal; $var is sprintf's placeholder argument.
 *   - _n( 'literal singular', 'literal plural', $count ) -- both translatable
 *     strings are literals; $count is the plural-selector number, not a
 *     translatable string.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Carveout;

use PHPUnit\Framework\TestCase;

final class NoVariableGettextFirstArgTest extends TestCase {

	/**
	 * The exact gettext-family function names covered by the WP.org T4 #9
	 * acceptance-bar grep. `sprintf`/`printf` are intentionally absent: they
	 * are not gettext calls themselves, only ever consumers of an already
	 * literal `__()` result.
	 */
	private const GETTEXT_FUNCTIONS = array(
		'__',
		'_e',
		'esc_html__',
		'esc_attr__',
		'esc_html_e',
		'esc_attr_e',
		'_x',
		'_n',
	);

	/**
	 * THE NON-VACUITY PROOF: a synthetic file with a genuine
	 * variable-first-arg gettext call must be detected by the same scan the
	 * real test below runs -- otherwise a silently-broken regex would let
	 * every case through green.
	 */
	public function test_scanner_detects_variable_first_arg_in_synthetic_source(): void {
		$tmp = sys_get_temp_dir() . '/gettext_var_probe_' . uniqid() . '.php';
		file_put_contents(
			$tmp,
			"<?php\n\$tpl = 'x';\n\$out = __( \$tpl, 'mhm-rentiva' );\n"
		);

		try {
			$hits = self::scan_file( $tmp );
			$this->assertNotEmpty(
				$hits,
				'Scanner must flag a genuine __( $var ) call in a synthetic probe file.'
			);
			$this->assertStringContainsString( '__(', implode( ' ', $hits ) );
		} finally {
			unlink( $tmp );
		}
	}

	/**
	 * THE PRECISION PROOF: the two legitimate patterns the brief calls out
	 * must NOT be flagged, so the real scan below cannot be vacuously green
	 * merely because it is over-broad.
	 */
	public function test_scanner_does_not_flag_legitimate_patterns(): void {
		$tmp = sys_get_temp_dir() . '/gettext_var_probe_' . uniqid() . '.php';
		file_put_contents(
			$tmp,
			"<?php\n" .
			"\$count = 3;\n" .
			"\$a = sprintf( __( 'Found %d item(s)', 'mhm-rentiva' ), \$count );\n" .
			"\$b = _n( 'One item', 'Multiple items', \$count, 'mhm-rentiva' );\n" .
			"\$c = __( 'A literal string', 'mhm-rentiva' );\n"
		);

		try {
			$hits = self::scan_file( $tmp );
			$this->assertSame(
				array(),
				$hits,
				'Scanner must not flag sprintf(__(literal), $var), _n(literal, literal, $count), or __(literal).'
			);
		} finally {
			unlink( $tmp );
		}
	}

	/**
	 * THE REAL GATE: every PHP file under src/ must be free of
	 * variable-first-arg gettext calls.
	 */
	public function test_no_variable_first_arg_gettext_calls_anywhere_in_src(): void {
		$root = dirname( __DIR__, 3 );
		$dir  = $root . '/src';
		$hits = array();

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$file_hits = self::scan_file( $file->getPathname() );
			if ( array() === $file_hits ) {
				continue;
			}
			$relative = str_replace( '\\', '/', $file->getPathname() );
			$relative = str_replace( str_replace( '\\', '/', $root ) . '/', '', $relative );
			foreach ( $file_hits as $line_no => $snippet ) {
				$hits[] = $relative . ':' . $line_no . ' ' . $snippet;
			}
		}

		sort( $hits );

		$this->assertSame(
			array(),
			$hits,
			"Variable-first-arg gettext call(s) found under src/ (WP.org T4 #9):\n" . implode( "\n", $hits )
		);
	}

	/**
	 * Scan a single file for `<gettext-fn>( $var` occurrences. Returns a
	 * list of "line-number => matched snippet" (1-indexed line numbers to
	 * match how humans/editors report them).
	 *
	 * @return array<int, string>
	 */
	private static function scan_file( string $path ): array {
		$source = (string) file_get_contents( $path );
		$fns    = implode( '|', self::GETTEXT_FUNCTIONS );
		// \s* matches newlines too, so a call whose "(" and "$var" are split
		// across lines is still caught.
		$pattern = '/\b(?:' . $fns . ')\(\s*\$[A-Za-z_]/';

		if ( ! preg_match_all( $pattern, $source, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		$hits = array();
		foreach ( $matches[0] as $match ) {
			[ $snippet, $offset ] = $match;
			$line_no          = substr_count( $source, "\n", 0, $offset ) + 1;
			$hits[ $line_no ] = trim( $snippet );
		}

		return $hits;
	}
}
