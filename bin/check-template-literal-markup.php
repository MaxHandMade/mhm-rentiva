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
 * Returns the pure collector when required from another PHP file (so it can
 * be unit tested against synthetic snippets -- see
 * tests/Tools/TemplateLiteralMarkupCheckerTest.php); scans the shipped JS
 * surface and prints a per-file report when run directly.
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

// When required by another PHP file (e.g. a test), hand back the collector and stop.
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$is_main_script  = '' !== $script_filename && @realpath( $script_filename ) === realpath( __FILE__ );

if ( ! $is_main_script ) {
	return $collect;
}

// ---- CLI mode: sweep the shipped JS surface -------------------------------
//
// Empirically verified equivalent (2026-08-03) to
// `python bin/build-release.py --list-shipped | grep '\.js$'` (88/88, zero
// diff): every *.js file under assets/, build/admin/, src-react/, excluding
// build/zip-staging/ (a regeneratable staging copy, not itself shipped) and
// any node_modules/. Walked directly in PHP (no python dependency) so this
// same collector can run inside the PHPUnit container as a standing guard.
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
		$js_files[] = $path;
	}
}
sort( $js_files );

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

	$relative = str_replace( array( $root . DIRECTORY_SEPARATOR, '\\' ), array( '', '/' ), $path );
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

exit( $total_hits > 0 ? 1 : 0 );
