<?php
/**
 * Template-literal tag-context markup checker (T8 Görev 5 / F21).
 *
 * Headless-Chrome evidence proved that `assets/js/components/addon-booking.js`
 * built its addon-total breakdown markup from backtick template literals whose
 * tags were typed with stray whitespace -- `< div class = "total-line" >`
 * instead of `<div class="total-line">`. Per the WHATWG HTML tokenizer spec, a
 * `<` not immediately followed by `!`, `/`, an ASCII letter, or `?` aborts tag
 * -open state; the `<` (and everything up to the next real tag) is emitted as
 * literal DATA-state text. So `< div ...>` never becomes an element -- it
 * renders as visible text on the page. This script finds that shape anywhere
 * it recurs in the shipped JS tree.
 *
 * Scope is deliberately narrow, and both restrictions are load-bearing, not
 * incidental:
 *
 *   1. Only text *inside* backtick template literals is ever scanned. Plain
 *      JS statements outside any template literal -- `if (value > 0)`,
 *      `a < b` -- must never be flagged. A naive whole-file regex (tried
 *      first, per the brief) drowns in exactly this false-positive class.
 *   2. *Inside* a template literal, every `${...}` interpolation expression
 *      is excised before the tag-context regexes run. JS expression code can
 *      legitimately contain `<`/`>` comparisons directly inside a backtick
 *      block -- e.g. `` `${count > 0 ? 'x' : 'y'}` `` -- without being markup
 *      text at all. Restriction 1 alone is not sufficient for this case: the
 *      comparison is textually "inside a backtick block" but is not literal
 *      output text, so it must be excluded the same way. (Real example in
 *      this plugin: `src-react/shared/format.js:23`,
 *      `` return dec > 0 ? `${ intFormatted }...` : intFormatted; `` -- the
 *      comparison sits immediately before the template, outside it, so
 *      restriction 1 alone already clears it; restriction 2 is what protects
 *      a comparison that migrates *inside* the backticks, e.g. inside a
 *      ternary's own interpolated branch.)
 *
 * The two prescribed patterns (T8 Görev 5 brief, calibrated against the
 * false-positive class above):
 *   - `/<\s+[a-zA-Z]/`     -- an opening `<` separated from its tag name by
 *     whitespace (`< div`) -- never recognised as a tag by the tokenizer.
 *   - `/[a-zA-Z"\']\s+>/` -- a `>` separated by whitespace from a letter or
 *     quote character before it (`n >`, `" >`, `' >`). This catches the
 *     broken-closing-tag shape the same bug produces, e.g. `< / span >`:
 *     the leading `<` there fails the first pattern too (`<` is followed by
 *     a space then `/`, not a letter), so the whole fragment -- `>` included
 *     -- renders as text; this pattern is what actually flags it.
 *
 * `${...}` interpolations are collapsed by *position* to a single neutral
 * placeholder character, via a hand-rolled scanner (no JS parser is
 * available in PHP), not by a regex over `${...}`. A placeholder character
 * is used rather than deleting the interpolation outright, because deletion
 * can manufacture a whitespace-adjacency to `<`/`>` that never existed in
 * the source -- see the worked example in $consume_template's docblock
 * below (a real, shipped false positive this checker's own calibration
 * caught in assets/vendor/swiper-bundle.min.js before this fix). The
 * scanner tracks `'...'`/`"..."` strings (backslash-escape aware), `//`
 * and `/* *​/` comments, and regex literals (both needed: this codebase's own
 * HTML-escape idiom, `text.replace(/[&<>"']/g, ...)`, puts a `"` inside a
 * regex character class -- without regex-literal awareness a naive scanner
 * mistakes that `"` for the start of a string and desyncs for the rest of
 * the file; verified against every such call site in the shipped tree before
 * shipping this checker). Critically, a nested template literal *inside* an
 * interpolation is consumed as one balanced unit and its own literal text is
 * still collected as a span to scan (it is not just excised) -- this
 * codebase has exactly this shape at
 * `assets/js/frontend/availability-calendar.js:710-715`:
 * `` `<div>...${cond ? `<span>...</span>` : ''}</div>` ``. Its inner
 * backticks must never be mistaken for the outer literal's own closing
 * backtick, and the inner literal's own markup must still be checked.
 *
 * Returns `array('collect' => ..., 'shipped_js_files' => ...)` when required
 * from another PHP file -- ONE shared implementation of both the detector and
 * the shipped-file walk, reused as-is by
 * tests/Tools/TemplateLiteralMarkupCheckerTest.php (fixed in response to T8
 * Görev 5 review Important-1: an earlier version had the walk duplicated,
 * independently, inside that test file -- two copies of the same hardcoded
 * glob that could silently drift from each other). Scans the shipped JS
 * surface and prints a per-file report when run directly; `--verify-scope`
 * mechanically diffs the walk against the real `.distignore`-derived oracle
 * (see that mode's own docblock below for why it exists and where it must
 * run).
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

/**
 * Is the '/' at $code[$i] the start of a regex literal, or a division
 * operator? Approximated the standard way: look backward past whitespace for
 * the previous significant character. If it is alnum/`_`/`$`/`)`/`]`/a quote
 * character, the previous token produced a *value*, so `/` is division.
 * Otherwise (punctuation, an operator, or start-of-input) `/` starts a regex.
 * This is a heuristic, not a full tokenizer -- but every regex literal that
 * actually contains a quote character in this shipped tree is directly
 * preceded by `(` (`.replace(/[&<>"']/g, ...)`), which this heuristic
 * classifies correctly.
 *
 * @param string $code Full source being scanned.
 * @param int    $i    Index of the '/' character.
 */
$is_regex_start = static function ( string $code, int $i ): bool {
	$k = $i - 1;
	while ( $k >= 0 && false !== strpos( " \t\r\n", $code[ $k ] ) ) {
		--$k;
	}
	if ( $k < 0 ) {
		return true;
	}
	return false === strpos( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$)]\'"`', $code[ $k ] );
};

// Forward declarations for mutual recursion (a template literal's body can
// contain `${...}` interpolations that contain more JS code, which can
// contain more template literals, arbitrarily deep).
$consume_code     = null;
$consume_template = null;

/**
 * Scan plain JS code (i.e. NOT inside a template literal's literal-text
 * region) starting at offset $i. Skips `'...'`/`"..."` strings, `//` and
 * `/* *​/` comments, and regex literals without being confused by them.
 * Every backtick found recurses into $consume_template and appends the
 * resulting span to $spans (by reference, shared across the whole scan).
 *
 * When $stop_brace_depth is not null, this call is scanning the inside of a
 * `${...}` interpolation (entered right after the `${`): it tracks `{`/`}`
 * nesting and returns as soon as it meets the interpolation's own closing
 * `}` (i.e. depth returns to $stop_brace_depth), consuming that `}`. When
 * null, this call is the top-level, whole-file scan and only stops at EOF.
 *
 * @param string              $code             Full source.
 * @param int                 $i                Start offset.
 * @param int                 $len              strlen($code), passed in to avoid recomputing.
 * @param int                 $line             1-based line number at $i.
 * @param int|null            $stop_brace_depth See above.
 * @param list<array{line:int,text:string}> $spans By-reference collector of template-literal spans.
 * @return array{0:int,1:int} [new $i, new $line]
 */
$consume_code = static function ( string $code, int $i, int $len, int $line, ?int $stop_brace_depth, array &$spans ) use ( &$consume_code, &$consume_template, $is_regex_start ): array {
	$depth = 0;

	while ( $i < $len ) {
		$ch = $code[ $i ];

		if ( "\n" === $ch ) {
			++$line;
			++$i;
			continue;
		}

		if ( '/' === $ch && $i + 1 < $len && '/' === $code[ $i + 1 ] ) {
			$i += 2;
			while ( $i < $len && "\n" !== $code[ $i ] ) {
				++$i;
			}
			continue;
		}

		if ( '/' === $ch && $i + 1 < $len && '*' === $code[ $i + 1 ] ) {
			$i += 2;
			while ( $i < $len && ! ( '*' === $code[ $i ] && $i + 1 < $len && '/' === $code[ $i + 1 ] ) ) {
				if ( "\n" === $code[ $i ] ) {
					++$line;
				}
				++$i;
			}
			$i = min( $i + 2, $len );
			continue;
		}

		if ( '/' === $ch && $is_regex_start( $code, $i ) ) {
			++$i; // Past the opening '/'.
			$in_class = false;
			while ( $i < $len && "\n" !== $code[ $i ] ) {
				$rc = $code[ $i ];
				if ( '\\' === $rc ) {
					$i += 2;
					continue;
				}
				if ( '[' === $rc ) {
					$in_class = true;
					++$i;
					continue;
				}
				if ( ']' === $rc ) {
					$in_class = false;
					++$i;
					continue;
				}
				if ( '/' === $rc && ! $in_class ) {
					++$i; // Past the closing '/'.
					break;
				}
				++$i;
			}
			while ( $i < $len && false !== strpos( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $code[ $i ] ) ) {
				++$i; // Regex flags.
			}
			continue;
		}

		if ( "'" === $ch || '"' === $ch ) {
			$quote = $ch;
			++$i;
			while ( $i < $len && $code[ $i ] !== $quote ) {
				if ( '\\' === $code[ $i ] ) {
					++$i;
					if ( $i < $len ) {
						if ( "\n" === $code[ $i ] ) {
							++$line;
						}
						++$i;
					}
					continue;
				}
				if ( "\n" === $code[ $i ] ) {
					++$line; // Unterminated-string safety; valid JS never hits this.
				}
				++$i;
			}
			++$i; // Past the closing quote.
			continue;
		}

		if ( '`' === $ch ) {
			$start_line = $line;
			++$i;
			list( $i, $line, $text ) = $consume_template( $code, $i, $len, $line, $spans );
			$spans[] = array(
				'line' => $start_line,
				'text' => $text,
			);
			continue;
		}

		if ( null !== $stop_brace_depth ) {
			if ( '{' === $ch ) {
				++$depth;
				++$i;
				continue;
			}
			if ( '}' === $ch ) {
				if ( $stop_brace_depth === $depth ) {
					++$i;
					return array( $i, $line );
				}
				--$depth;
				++$i;
				continue;
			}
		}

		++$i;
	}

	return array( $i, $line );
};

/**
 * Scan a template literal's *body*, starting right after its opening
 * backtick. Accumulates literal text; every `${` starts an interpolation,
 * scanned via $consume_code (which recurses into $consume_template again for
 * any template literal nested inside the interpolation, appending it to
 * $spans directly). The interpolation's own source text is excised from the
 * returned literal text -- replaced by the same number of newlines it
 * contained, so line-number arithmetic on the returned text stays correct --
 * because JS expression code is not markup text and must never reach the
 * tag-context regexes.
 *
 * @param string $code  Full source.
 * @param int    $i     Offset right after the opening backtick.
 * @param int    $len   strlen($code).
 * @param int    $line  1-based line number at $i.
 * @param list<array{line:int,text:string}> $spans By-reference collector (for nested templates).
 * @return array{0:int,1:int,2:string} [new $i, new $line, literal text with interpolations excised]
 */
$consume_template = static function ( string $code, int $i, int $len, int $line, array &$spans ) use ( &$consume_code ): array {
	$text = '';

	while ( $i < $len ) {
		$ch = $code[ $i ];

		if ( '\\' === $ch ) {
			if ( $i + 1 < $len ) {
				$text .= $ch . $code[ $i + 1 ];
				if ( "\n" === $code[ $i + 1 ] ) {
					++$line;
				}
				$i += 2;
			} else {
				$text .= $ch;
				++$i;
			}
			continue;
		}

		if ( '`' === $ch ) {
			++$i;
			return array( $i, $line, $text );
		}

		if ( '$' === $ch && $i + 1 < $len && '{' === $code[ $i + 1 ] ) {
			$interp_start_line = $line;
			$i                += 2;
			list( $i, $line )  = $consume_code( $code, $i, $len, $line, 0, $spans );
			// Collapse the whole interpolation to ONE neutral placeholder
			// character (not zero) -- see the module docblock's "excised to
			// a single placeholder" note. Deleting it outright (replacing
			// with '') was tried first and is wrong: it manufactures a
			// whitespace-adjacency to '<'/'>' that never existed in the
			// source. Real, shipped example that caught this:
			// assets/vendor/swiper-bundle.min.js's pagination renderer,
			// `` `<${e.bulletElement} class="${e.bulletClass}"></${e.bulletElement}>` ``
			// -- the tag-name interpolation sits directly against '<' with
			// no space, and evaluates to a letter-starting string
			// ("span"), so the rendered markup is always valid
			// (`<span class="...">`). Excising to '' left '<' directly
			// followed by the literal space that originally separated
			// `${e.bulletElement}` from `class=`, i.e. `< class=...` --
			// indistinguishable from the real bug. A single placeholder
			// letter preserves adjacency in both directions: it still
			// abuts '<' with no space (`<x class=...`, no match) and still
			// reproduces a genuine `< ${tag}` source-level break
			// (`< x...`, matches, as it must -- the real render would be
			// `< span...`, equally broken).
			$text .= 'x' . str_repeat( "\n", max( 0, $line - $interp_start_line ) );
			continue;
		}

		if ( "\n" === $ch ) {
			++$line;
		}
		$text .= $ch;
		++$i;
	}

	// Unterminated template literal -- not valid JS, shouldn't happen; return
	// what was accumulated rather than looping forever.
	return array( $i, $line, $text );
};

/**
 * Full collector: scan $code and return every tag-context-breakage offender
 * found inside its backtick template literals.
 *
 * @param string $code Full JS source.
 * @return list<array{line:int,pattern:string,excerpt:string}> `pattern` is
 *         'open' (`<\s+[a-zA-Z]`) or 'close' (`[a-zA-Z"\']\s+>`).
 */
$collect = static function ( string $code ) use ( &$consume_code ): array {
	$len   = strlen( $code );
	$spans = array();
	$consume_code( $code, 0, $len, 1, null, $spans );

	$offenders = array();

	foreach ( $spans as $span ) {
		$text = $span['text'];

		foreach (
			array(
				'open'  => '/<\s+[a-zA-Z]/',
				'close' => '/[a-zA-Z"\']\s+>/',
			) as $kind => $pattern
		) {
			if ( ! preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			foreach ( $matches[0] as $match ) {
				list( $matched, $offset ) = $match;
				$line_in_span             = substr_count( substr( $text, 0, $offset ), "\n" );
				$window_start             = max( 0, $offset - 20 );
				$window                   = substr( $text, $window_start, 60 );
				$excerpt                  = trim( (string) preg_replace( '/\s+/', ' ', $window ) );

				$offenders[] = array(
					'line'    => $span['line'] + $line_in_span,
					'pattern' => $kind,
					'excerpt' => $excerpt,
					'match'   => $matched,
				);
			}
		}
	}

	usort(
		$offenders,
		static function ( $a, $b ) {
			return $a['line'] <=> $b['line'];
		}
	);

	return $offenders;
};

/**
 * Walk the shipped JS surface: every *.js file under assets/, build/admin/,
 * src-react/, excluding build/zip-staging/ (a regeneratable staging copy of
 * the ENTIRE plugin tree left over from prior release builds -- not itself
 * part of the shipped surface) and any node_modules/.
 *
 * This is a hardcoded restatement of what `.distignore` actually ships (read,
 * for the real release ZIP, by `list_shipped()` in
 * `bin/build-release.py:227-262`) -- empirically verified equivalent at task
 * time (2026-08-03: 88/88 files, zero set difference against `python
 * bin/build-release.py --list-shipped | grep '\.js$'`), NOT a structural
 * derivation from `.distignore` itself. That distinction matters: if
 * `.distignore` later changes which directories ship, this walk will not
 * automatically follow it. Re-deriving `.distignore`'s gitignore-style
 * pattern matching (negation, `**`, directory-only patterns) in PHP would
 * duplicate genuinely complex logic and risks a second, independently-wrong
 * implementation -- so instead of that, two narrower things guard against
 * drift going unnoticed:
 *
 *   1. This is the ONLY walk in the codebase -- both the default CLI sweep
 *      below and the PHPUnit guard
 *      (tests/Tools/TemplateLiteralMarkupCheckerTest.php) require() this
 *      file and call this same closure, so there is nothing left to drift
 *      from EACH OTHER (T8 Görev 5 review Important-1's duplication half).
 *   2. `--verify-scope` (below) mechanically diffs this walk's output
 *      against the real oracle, `python bin/build-release.py --list-shipped`
 *      -- meant to be run after any `.distignore` change (Important-1's
 *      drift-from-truth half). It cannot run inside the PHPUnit container
 *      (no python3 there), so it is a separate, host-run mode, not part of
 *      the automated suite -- see its own comment below.
 *
 * This same "restating .distignore drifts silently, so read the real thing
 * instead" lesson already burned this codebase once:
 * `bin/check-plugin-check-parity.php`'s Gate G-D used to hardcode its own
 * scope and silently missed 4 shipped paths as a result (see that file's
 * header, "WRONG SCOPE"). This checker does not repeat that -- `--verify-scope`
 * is the same shape as G-D's own fix, cross-checking against
 * `--list-shipped` rather than trying to become a second `.distignore`
 * parser.
 *
 * Returned paths are absolute and ALWAYS forward-slash-normalized, even on
 * Windows -- `RecursiveDirectoryIterator::getPathname()` was found (T8
 * Görev 5 review Important-1 fix round) to mix separators on Windows
 * (forward slashes up through whatever literal was concatenated into
 * `$scan_roots`, native backslashes for every segment SPL itself appends
 * beneath that), which silently broke a root-prefix strip done downstream
 * by string-prefix comparison. Normalizing once, here, at the source, means
 * every consumer (the CLI sweep below, `--verify-scope`, and the PHPUnit
 * test) gets a predictable format instead of each needing its own
 * separator-mixing workaround.
 *
 * @return list<string> Absolute, forward-slash-normalized paths.
 */
$find_shipped_js_files = static function (): array {
	$root = dirname( __DIR__ );

	$scan_roots = array(
		$root . '/assets',
		$root . '/build/admin',
		$root . '/src-react',
	);

	$js_files = array();
	foreach ( $scan_roots as $target ) {
		if ( ! is_dir( $target ) ) {
			continue;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $target, RecursiveDirectoryIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( 'js' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			$path = $file->getPathname();
			if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'zip-staging' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			$js_files[] = str_replace( '\\', '/', $path );
		}
	}
	sort( $js_files );

	return $js_files;
};

// When required by another PHP file (e.g. a test), hand back BOTH closures
// and stop -- see $find_shipped_js_files's docblock above for why there is
// exactly one implementation of the walk, shared, rather than two.
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$is_main_script  = '' !== $script_filename && @realpath( $script_filename ) === realpath( __FILE__ );

if ( ! $is_main_script ) {
	return array(
		'collect'          => $collect,
		'shipped_js_files' => $find_shipped_js_files,
	);
}

// ---- CLI mode ---------------------------------------------------------

const EXIT_CLEAN           = 0;
const EXIT_FINDINGS        = 1;
const EXIT_CANNOT_MEASURE  = 2;

/**
 * Run a command without going through a shell. Mirrors
 * bin/check-plugin-check-parity.php's own run_cmd(): proc_open() with an
 * ARRAY command line bypasses the shell on both POSIX and Windows, so
 * nothing here depends on quoting rules.
 *
 * @param list<string> $argv_cmd
 * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
 */
$run_cmd = static function ( array $argv_cmd ): array {
	$spec = array(
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$proc = @proc_open( $argv_cmd, $spec, $pipes );
	if ( ! is_resource( $proc ) ) {
		return array( -1, '', 'proc_open failed for: ' . implode( ' ', $argv_cmd ) );
	}
	$stdout = (string) stream_get_contents( $pipes[1] );
	$stderr = (string) stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $proc );

	return array( $code, $stdout, $stderr );
};

if ( in_array( '--verify-scope', $argv, true ) ) {
	// ---- --verify-scope: mechanical drift detector (T8 Görev 5 review I1) -
	//
	// WHERE THIS RUNS: the host (or any environment with a `python3` /
	// `python` / `py` on PATH) -- NOT the `rentiva-dev-wpcli-1` Docker
	// container PHPUnit runs in, which has no python3 (progress.md's
	// standing note). This mode is therefore never invoked by the PHPUnit
	// guard itself; it is a separate, manually/CI-run check -- the same
	// "documented host-only, not pretended-away" shape as
	// bin/check-plugin-check-parity.php's own "WHERE IT RUNS".
	//
	// Diffs $find_shipped_js_files()'s output against the real oracle,
	// `python bin/build-release.py --list-shipped` filtered to `.js` -- the
	// same list `list_shipped()` derives from `.distignore` for the actual
	// release ZIP. Exit 0 only on an exact set match. Exit 1 on any drift:
	// both directions are reported, since a file the walk would MISS is the
	// dangerous case (a shipped file silently un-guarded by this checker),
	// while a file the walk scans that is NOT actually shipped is reported
	// too (harmless, but means the walk is testing something that never
	// ships -- also worth knowing). Exit 2 if no python binary is available
	// at all -- cannot measure, never conflated with a clean 0.
	$root   = dirname( __DIR__ );
	$oracle = null;
	foreach ( array( 'python3', 'python', 'py' ) as $py ) {
		list( $rc, $out ) = $run_cmd( array( $py, $root . '/bin/build-release.py', '--list-shipped' ) );
		if ( 0 === $rc && '' !== trim( $out ) ) {
			$oracle = preg_split( '/\R/', trim( $out ) );
			break;
		}
	}

	if ( null === $oracle ) {
		fwrite( STDERR, "VERIFY-SCOPE: cannot measure -- no working python3/python/py found to run bin/build-release.py --list-shipped\n" );
		exit( EXIT_CANNOT_MEASURE );
	}

	$oracle_js = array();
	foreach ( $oracle as $rel ) {
		if ( '.js' === strtolower( substr( $rel, -3 ) ) ) {
			$oracle_js[] = $rel;
		}
	}
	sort( $oracle_js );

	// $find_shipped_js_files() already returns forward-slash-normalized
	// absolute paths (see its own docblock); only $root itself -- built here
	// from dirname(__DIR__), which is native-separator (backslash on
	// Windows) -- needs normalizing before the prefix strip.
	$root_fs   = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
	$walked_js = array();
	foreach ( $find_shipped_js_files() as $abs ) {
		$walked_js[] = 0 === strpos( $abs, $root_fs ) ? substr( $abs, strlen( $root_fs ) ) : $abs;
	}
	sort( $walked_js );

	$missing_from_walker = array_values( array_diff( $oracle_js, $walked_js ) );
	$extra_in_walker     = array_values( array_diff( $walked_js, $oracle_js ) );

	if ( array() !== $missing_from_walker ) {
		echo "Shipped by .distignore but NOT scanned by the walk (dangerous -- these ship un-guarded):\n";
		echo implode( "\n", $missing_from_walker ) . "\n\n";
	}
	if ( array() !== $extra_in_walker ) {
		echo "Scanned by the walk but NOT actually shipped by .distignore (noisy, not dangerous):\n";
		echo implode( "\n", $extra_in_walker ) . "\n\n";
	}

	printf(
		"VERIFY-SCOPE: oracle=%d walker=%d missing_from_walker=%d extra_in_walker=%d\n",
		count( $oracle_js ),
		count( $walked_js ),
		count( $missing_from_walker ),
		count( $extra_in_walker )
	);

	exit( ( array() === $missing_from_walker && array() === $extra_in_walker ) ? EXIT_CLEAN : EXIT_FINDINGS );
}

// ---- default mode: sweep the shipped JS surface ------------------------
// $find_shipped_js_files() returns forward-slash-normalized absolute paths;
// only $root (native-separator) needs normalizing before the prefix strip.
$root     = rtrim( str_replace( '\\', '/', dirname( __DIR__ ) ), '/' ) . '/';
$js_files = $find_shipped_js_files();

$files_with_hits = 0;
$total_hits      = 0;

foreach ( $js_files as $path ) {
	$code      = (string) file_get_contents( $path );
	$offenders = $collect( $code );

	if ( array() === $offenders ) {
		continue;
	}

	++$files_with_hits;
	$total_hits += count( $offenders );

	$relative = 0 === strpos( $path, $root ) ? substr( $path, strlen( $root ) ) : $path;
	printf( "%s: %d hit(s)\n", $relative, count( $offenders ) );
	foreach ( $offenders as $o ) {
		printf( "    :%d [%s] %s\n", $o['line'], $o['pattern'], $o['excerpt'] );
	}
}

printf(
	"\nTEMPLATE LITERAL MARKUP SCAN: files_scanned=%d files_with_hits=%d total_hits=%d\n",
	count( $js_files ),
	$files_with_hits,
	$total_hits
);

exit( $total_hits > 0 ? EXIT_FINDINGS : EXIT_CLEAN );
