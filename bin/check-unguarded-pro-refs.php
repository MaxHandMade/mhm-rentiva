<?php
/**
 * Unguarded Pro-reference checker.
 *
 * WHY THIS EXISTS (a proven blind spot, not a hypothetical one)
 * -------------------------------------------------------------
 * During the Lite carve-out, three shipped Lite templates called
 * `Reports::get_currency_symbol()` with no guard at all. `Reports` is a Pro
 * class that Lite does not ship -- so those templates were a guaranteed fatal
 * for every Lite user who rendered them. Every gate in the repo was blind:
 *
 *   - PHPStan does not scan `templates/` at all (see phpstan.neon `paths`).
 *   - `bin/check-guarded-refs.php` only inspects class names that appear
 *     INSIDE a guard. Its question is "does the class named in this guard
 *     still exist?". A bare, unguarded call to an absent class names no
 *     guard, so that checker never sees it.
 *   - Even inside PHPStan's own scope, `is_class_available()` is a custom
 *     wrapper method; PHPStan cannot narrow types through it, so references
 *     it "protects" look unresolved-or-fine depending on config, not
 *     definitively guarded.
 *
 * This checker asks the inverse, complementary question:
 *
 *     "Is every reference to an allowlisted Pro class actually behind a guard?"
 *
 * Together the two scripts close the loop: check-guarded-refs.php proves a
 * guard names a real class; this one proves a Pro class is only ever touched
 * from behind a guard.
 *
 * RESOLUTION ALGORITHM (why this does not repeat the naive-scan false alarms)
 * --------------------------------------------------------------------------
 * A previous, naive attempt grepped for bare short names (`Reports`,
 * `PostType`, `Export`, `Message`) and drowned in false positives -- those
 * short names collide with entirely unrelated Lite classes in other
 * namespaces.
 *
 * This checker never matches a short name. It resolves EVERY class-name token
 * the way the PHP compiler does -- through the file's `use` imports (including
 * `as` aliases and group-use), then falling back to the file's own `namespace`
 * -- and only then tests the resulting fully-qualified name for membership in
 * `bin/seam-classes.txt`.
 *
 * That inverts the filter, and it is what makes a greedy scan safe: because
 * the allowlist is a closed set of exact FQNs, resolution IS the filter. A
 * bare `Reports` inside `namespace MHMRentiva\Admin\Foo` resolves to
 * `MHMRentiva\Admin\Foo\Reports`, which is not the seam FQN
 * `MHMRentiva\Admin\Reports\Reports`, and is correctly ignored. A hit can only
 * occur when the token genuinely denotes the Pro class. So we can afford to
 * collect every name in a class-name position and let the allowlist decide,
 * which is what gives coverage of all the shapes that actually bit us:
 *
 *   - `Foo::method()`, `Foo::CONST`, `Foo::class`
 *   - `new Foo`
 *   - parameter type hints, return types, typed properties
 *   - `extends Foo`, `implements Foo`
 *   - `instanceof Foo`, `catch ( Foo $e )`, attributes
 *   - same-namespace short names (no import at all)
 *
 * `use` import statements themselves are deliberately NOT references: PHP
 * never autoloads an imported name, so `use MHMRentiva\Admin\Reports\Reports;`
 * in a file that only touches it behind a guard is correct and must not be
 * reported.
 *
 * GUARD DETECTION
 * ---------------
 * Structural (token/brace matching), not a line window -- a line window both
 * misses long guarded blocks and silently green-lights code that merely sits
 * near a guard. Recognised guard calls are `class_exists()`,
 * `interface_exists()`, `trait_exists()` and this codebase's own
 * `is_class_available()` wrapper (`src/Plugin.php`, which forwards straight to
 * `class_exists()`), naming the Pro class either as a literal string or as
 * `Foo::class`.
 *
 * A reference is guarded when it falls inside a range that guard establishes:
 *
 *   - POSITIVE guard -- `if ( class_exists( X ) ) { ...here... }`: the rest of
 *     the `if` condition (so `&&` short-circuit operands are covered) plus the
 *     `if` body. An `else` branch is deliberately excluded: it is the
 *     class-is-absent branch.
 *
 *   - NEGATIVE guard -- `if ( ! class_exists( X ) ) { return; } ...here...`:
 *     this codebase's most common shape. Covers the rest of the condition (for
 *     `|| ! X::method()` short-circuit chains) plus, when the body exits via
 *     return/continue/break/throw/exit, everything after the `if` to the end of
 *     the ENCLOSING BLOCK. Scoping to the enclosing block rather than "rest of
 *     the function" keeps a `continue` guard correctly limited to its loop body.
 *
 *   - Bare expression guard -- `class_exists( X ) ? X::a() : null`: covers the
 *     remainder of that statement.
 *
 * Note the deliberate strictness: assigning a guard to a variable first
 * (`$ok = class_exists( X ); if ( $ok ) { X::a(); }`) is NOT recognised, and
 * `X::a()` is reported. That is intentional -- PHPStan cannot narrow through
 * that indirection either, and the inline form is what the codebase uses.
 *
 * Scans src/, templates/, and root-level PHP -- templates/ above all, since
 * that is exactly where the proven fatal lived and exactly what PHPStan omits.
 *
 * Returns the pure collector when required from another PHP file (so it can be
 * unit tested against synthetic input); exits non-zero on findings when run
 * directly.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

// Overridable so a unit test can point the scan at an isolated synthetic tree.
// $root otherwise always resolves from this script's own location, regardless
// of CWD -- mirroring bin/check-guarded-refs.php.
$root = dirname( __DIR__ );
$root = getenv( 'MHM_GUARD_ROOT' ) ?: $root;

// The closed set of Pro FQNs. A reference to any of these must be guarded.
$seam_allow = array();
$seam_file  = $root . '/bin/seam-classes.txt';
if ( is_file( $seam_file ) ) {
	foreach ( file( $seam_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $seam_fqn ) {
		$seam_fqn = ltrim( trim( $seam_fqn ), '\\' );
		if ( '' !== $seam_fqn ) {
			$seam_allow[ strtolower( $seam_fqn ) ] = $seam_fqn;
		}
	}
}

/**
 * Guard function names recognised as availability checks.
 *
 * `is_class_available` is this plugin's own wrapper (src/Plugin.php) which
 * forwards verbatim to class_exists(); check-guarded-refs.php independently
 * verifies that wrapper still has a forwarding shape, so trusting it here does
 * not create a hole.
 */
const MHM_GUARD_FUNCTIONS = array( 'class_exists', 'interface_exists', 'trait_exists', 'is_class_available' );

/** Names that are never class references, even though the tokenizer emits T_STRING. */
const MHM_NON_CLASS_NAMES = array(
	'string', 'int', 'integer', 'bool', 'boolean', 'float', 'double', 'array', 'void',
	'null', 'false', 'true', 'mixed', 'object', 'callable', 'iterable', 'never',
	'static', 'self', 'parent', 'this',
);

/**
 * Collect unguarded references to allowlisted Pro classes in one PHP source.
 *
 * @param string                $code       PHP source.
 * @param array<string, string> $seam_allow Map of lowercased FQN => canonical FQN.
 * @return array<int, array{line:int, class:string}>
 */
$collect = static function ( string $code, array $seam_allow ): array {
	$raw = token_get_all( $code );

	// Normalise to a uniform shape and build the "significant" token stream
	// (no whitespace / comments), which is what all navigation below walks.
	$sig = array();
	foreach ( $raw as $token ) {
		if ( is_array( $token ) ) {
			if ( T_WHITESPACE === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$sig[] = array(
				'id'   => $token[0],
				'text' => $token[1],
				'line' => $token[2],
			);
			continue;
		}
		$prev_line = array() === $sig ? 1 : $sig[ count( $sig ) - 1 ]['line'];
		$sig[]     = array(
			'id'   => $token,
			'text' => $token,
			'line' => $prev_line,
		);
	}

	$count = count( $sig );

	/** Is the token at $p the literal single-character token $char? */
	$is_char = static function ( int $p, string $char ) use ( $sig, $count ): bool {
		return $p >= 0 && $p < $count && $sig[ $p ]['id'] === $char;
	};

	/** Match a bracket forward from an opening bracket at $p. Returns index of its closer, or -1. */
	$match_forward = static function ( int $p, string $open, string $close ) use ( $sig, $count ): int {
		$depth = 0;
		for ( $i = $p; $i < $count; $i++ ) {
			if ( $sig[ $i ]['id'] === $open ) {
				++$depth;
			} elseif ( $sig[ $i ]['id'] === $close ) {
				--$depth;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}
		return -1;
	};

	// ---------------------------------------------------------------
	// Pass 1: forward, stateful. Track namespace + imports, and collect
	// candidate references and guard occurrences (both need resolution,
	// so both are gathered while the import state is live).
	// ---------------------------------------------------------------
	$namespace = '';
	$imports   = array(); // lowercased alias => FQN.

	/** Resolve a name token's text to an FQN, exactly as the PHP compiler would. */
	$resolve = static function ( string $text ) use ( &$namespace, &$imports ): string {
		if ( '' === $text ) {
			return '';
		}
		// Fully qualified: never touched by imports or the current namespace.
		if ( '\\' === $text[0] ) {
			return ltrim( $text, '\\' );
		}
		// `namespace\Foo` is relative to the current namespace only.
		if ( 0 === stripos( $text, 'namespace\\' ) ) {
			$rest = substr( $text, 10 );
			return '' === $namespace ? $rest : $namespace . '\\' . $rest;
		}
		$parts = explode( '\\', $text );
		$first = strtolower( $parts[0] );
		if ( isset( $imports[ $first ] ) ) {
			$parts[0] = $imports[ $first ];
			return implode( '\\', $parts );
		}
		return '' === $namespace ? $text : $namespace . '\\' . $text;
	};

	$name_ids = array( T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE );

	$refs   = array(); // [{pos, line, fqn}]
	$guards = array(); // [{pos, fqn, negated}]

	$brace_depth = 0;

	for ( $p = 0; $p < $count; $p++ ) {
		$id = $sig[ $p ]['id'];

		if ( '{' === $id ) {
			++$brace_depth;
			continue;
		}
		if ( '}' === $id ) {
			--$brace_depth;
			continue;
		}

		// --- namespace declaration ---
		if ( T_NAMESPACE === $id && $p + 1 < $count && in_array( $sig[ $p + 1 ]['id'], $name_ids, true ) ) {
			$namespace = ltrim( $sig[ $p + 1 ]['text'], '\\' );
			$imports   = array();
			$p        += 1;
			continue;
		}

		// --- use imports (class imports only) ---
		// A trait `use` sits inside a class body (brace_depth > 0); a closure's
		// `use ( $var )` clause is followed by '('. Neither is an import.
		if ( T_USE === $id && 0 === $brace_depth && ! $is_char( $p + 1, '(' ) ) {
			$q = $p + 1;
			// `use function ...` / `use const ...` import symbols, not classes.
			if ( $q < $count && ( T_FUNCTION === $sig[ $q ]['id'] || T_CONST === $sig[ $q ]['id'] ) ) {
				while ( $q < $count && ! $is_char( $q, ';' ) ) {
					++$q;
				}
				$p = $q;
				continue;
			}

			// Parse a comma-separated list of `Name [as Alias]`, and the
			// group-use form `Prefix\{ A, B as C }`.
			$prefix = '';
			while ( $q < $count && ! $is_char( $q, ';' ) ) {
				if ( in_array( $sig[ $q ]['id'], $name_ids, true ) ) {
					$name = ltrim( $sig[ $q ]['text'], '\\' );

					// Group use: `use A\B\{ C, D as E };`. PHP tokenises the
					// prefix as T_NAME_QUALIFIED('A\B') + T_NS_SEPARATOR + '{'
					// -- the trailing separator is its OWN token and is not part
					// of the name. Missing that reads `A\B` as a plain import
					// and binds the alias `B` to the namespace, which silently
					// mis-resolves every later short name (it made a real
					// unguarded ref look clean, so this branch is load-bearing).
					$is_group = $is_char( $q + 1, '{' );
					if ( $q + 2 < $count && T_NS_SEPARATOR === $sig[ $q + 1 ]['id'] && $is_char( $q + 2, '{' ) ) {
						$is_group = true;
					}
					if ( $is_group ) {
						$prefix = rtrim( $name, '\\' ) . '\\';
						// Advance past the '{' regardless of which shape matched.
						$q = $is_char( $q + 1, '{' ) ? $q + 2 : $q + 3;
						continue;
					}

					$fqn   = $prefix . $name;
					$alias = $name;
					if ( $q + 1 < $count && T_AS === $sig[ $q + 1 ]['id'] && $q + 2 < $count ) {
						$alias = $sig[ $q + 2 ]['text'];
						$q    += 2;
					} else {
						$segments = explode( '\\', $name );
						$alias    = end( $segments );
					}
					$imports[ strtolower( $alias ) ] = $fqn;
				}
				++$q;
			}
			$p = $q;
			continue;
		}

		if ( ! in_array( $id, $name_ids, true ) ) {
			continue;
		}

		$text = $sig[ $p ]['text'];

		// --- guard call? (checked before the reference filter, since
		// `$this->is_class_available(...)` would otherwise be skipped as a
		// method name) ---
		$bare = strtolower( ltrim( $text, '\\' ) );
		if ( in_array( $bare, MHM_GUARD_FUNCTIONS, true ) && $is_char( $p + 1, '(' ) ) {
			$close = $match_forward( $p + 1, '(', ')' );
			if ( $close > 0 ) {
				$guard_fqn = '';
				// First argument only.
				$a = $p + 2;
				if ( $a < $close && T_CONSTANT_ENCAPSED_STRING === $sig[ $a ]['id'] ) {
					$literal = $sig[ $a ]['text'];
					$quote   = $literal[0];
					$inner   = substr( $literal, 1, -1 );
					// Unescape the two sequences PHP honours in both quote styles.
					$inner     = str_replace( array( '\\\\', '\\' . $quote ), array( '\\', $quote ), $inner );
					$guard_fqn = ltrim( $inner, '\\' );
				} elseif ( $a < $close && in_array( $sig[ $a ]['id'], $name_ids, true )
					&& $a + 2 <= $close && T_DOUBLE_COLON === $sig[ $a + 1 ]['id']
					&& 'class' === strtolower( $sig[ $a + 2 ]['text'] )
				) {
					$guard_fqn = $resolve( $sig[ $a ]['text'] );
				}

				if ( '' !== $guard_fqn ) {
					// Negated? Walk back over any '(' to find a '!'.
					$b = $p - 1;
					while ( $b >= 0 && $is_char( $b, '(' ) ) {
						--$b;
					}
					$guards[] = array(
						'pos'     => $p,
						'fqn'     => strtolower( $guard_fqn ),
						'negated' => $b >= 0 && '!' === $sig[ $b ]['id'],
					);
				}
			}
			continue;
		}

		if ( in_array( $bare, MHM_NON_CLASS_NAMES, true ) ) {
			continue;
		}

		// --- reference filter ---
		$prev_id = $p > 0 ? $sig[ $p - 1 ]['id'] : null;
		// Not a class reference: a member/method name, a declaration name, or
		// an alias.
		$skip_after = array(
			T_OBJECT_OPERATOR,
			T_NULLSAFE_OBJECT_OPERATOR,
			T_DOUBLE_COLON,
			T_FUNCTION,
			T_CLASS,
			T_INTERFACE,
			T_TRAIT,
			T_CONST,
			T_GOTO,
			T_NAMESPACE,
			T_AS,
		);
		if ( defined( 'T_ENUM' ) ) {
			$skip_after[] = T_ENUM;
		}
		if ( null !== $prev_id && in_array( $prev_id, $skip_after, true ) ) {
			continue;
		}
		// A plain function call `foo(...)` -- but keep `new Foo(...)`,
		// `Foo::bar()` (that `Foo` is followed by `::`, not `(`).
		if ( $is_char( $p + 1, '(' ) && T_NEW !== $prev_id ) {
			continue;
		}

		$fqn = $resolve( $text );
		if ( isset( $seam_allow[ strtolower( $fqn ) ] ) ) {
			$refs[] = array(
				'pos'  => $p,
				'line' => $sig[ $p ]['line'],
				'fqn'  => $seam_allow[ strtolower( $fqn ) ],
			);
		}
	}

	if ( array() === $refs ) {
		return array();
	}

	// ---------------------------------------------------------------
	// Pass 2: turn each guard into the token ranges it protects.
	// ---------------------------------------------------------------
	$ranges = array(); // lowercased FQN => list of [start, end]

	foreach ( $guards as $guard ) {
		$g = $guard['pos'];

		// Find an enclosing `if (` / `elseif (` whose condition contains $g.
		$if_open = -1;
		$depth   = 0;
		for ( $p = $g - 1; $p >= 0; $p-- ) {
			$id = $sig[ $p ]['id'];
			if ( ')' === $id ) {
				++$depth;
			} elseif ( '(' === $id ) {
				if ( 0 === $depth ) {
					// An unmatched opener: some enclosing call or condition.
					$before = $p - 1;
					if ( $before >= 0 && ( T_IF === $sig[ $before ]['id'] || T_ELSEIF === $sig[ $before ]['id'] ) ) {
						$if_open = $p;
					}
					break;
				}
				--$depth;
			} elseif ( 0 === $depth && ( '{' === $id || '}' === $id || ';' === $id ) ) {
				// Statement boundary reached without an enclosing condition.
				break;
			}
		}

		if ( -1 === $if_open ) {
			// Bare expression guard (e.g. a ternary): protect the rest of the
			// statement.
			$end = $g;
			for ( $p = $g; $p < $count; $p++ ) {
				if ( ';' === $sig[ $p ]['id'] ) {
					$end = $p;
					break;
				}
				$end = $p;
			}
			$ranges[ $guard['fqn'] ][] = array( $g, $end );
			continue;
		}

		$cond_close = $match_forward( $if_open, '(', ')' );
		if ( -1 === $cond_close ) {
			continue;
		}

		// The rest of the condition is always protected: `&&` (positive) and
		// `||` (negative) both short-circuit away from the operands that
		// follow once the guard has decided.
		$ranges[ $guard['fqn'] ][] = array( $g, $cond_close );

		// Locate the if body.
		$body_start = $cond_close + 1;
		$body_end   = $body_start;
		if ( $is_char( $body_start, '{' ) ) {
			$matched = $match_forward( $body_start, '{', '}' );
			if ( -1 === $matched ) {
				continue;
			}
			$body_end = $matched;
		} else {
			// Single-statement if, no braces.
			for ( $p = $body_start; $p < $count; $p++ ) {
				$body_end = $p;
				if ( ';' === $sig[ $p ]['id'] ) {
					break;
				}
			}
		}

		if ( ! $guard['negated'] ) {
			// Positive guard: the body is the class-is-present branch. Any
			// `else` deliberately falls outside this range.
			$ranges[ $guard['fqn'] ][] = array( $body_start, $body_end );
			continue;
		}

		// Negative guard: the body is the bail-out branch. It only protects
		// the code that follows if it actually leaves.
		$exits = false;
		for ( $p = $body_start; $p <= $body_end; $p++ ) {
			$id = $sig[ $p ]['id'];
			if ( T_RETURN === $id || T_CONTINUE === $id || T_BREAK === $id || T_THROW === $id || T_EXIT === $id ) {
				$exits = true;
				break;
			}
		}
		if ( ! $exits ) {
			continue;
		}

		// Protect from the end of the if to the end of the ENCLOSING block --
		// i.e. up to the '}' that closes the block our `if` lives in. For a
		// top-of-function early return that is the rest of the function; for a
		// `continue` it is correctly limited to the rest of the loop body. At
		// file top level there is no closing brace, so it runs to EOF.
		$depth = 0;
		$end   = $count - 1;
		for ( $p = $body_end + 1; $p < $count; $p++ ) {
			$id = $sig[ $p ]['id'];
			if ( '{' === $id ) {
				++$depth;
			} elseif ( '}' === $id ) {
				if ( 0 === $depth ) {
					$end = $p;
					break;
				}
				--$depth;
			}
		}
		$ranges[ $guard['fqn'] ][] = array( $body_end + 1, $end );
	}

	// ---------------------------------------------------------------
	// Pass 3: every reference must fall inside a range for its own class.
	// ---------------------------------------------------------------
	$found = array();
	foreach ( $refs as $ref ) {
		$key      = strtolower( $ref['fqn'] );
		$guarded  = false;
		foreach ( $ranges[ $key ] ?? array() as $range ) {
			if ( $ref['pos'] >= $range[0] && $ref['pos'] <= $range[1] ) {
				$guarded = true;
				break;
			}
		}
		if ( ! $guarded ) {
			$found[] = array(
				'line'  => $ref['line'],
				'class' => $ref['fqn'],
			);
		}
	}

	return $found;
};

// When required by another PHP file (e.g. a test), hand back the collector.
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$is_main_script  = '' !== $script_filename && @realpath( $script_filename ) === realpath( __FILE__ );

if ( ! $is_main_script ) {
	return $collect;
}

// src/ + templates/ + root PHP. templates/ is the whole point: PHPStan omits
// it, and that is where the proven Reports:: fatal shipped.
$scan_targets = array(
	$root . '/src',
	$root . '/templates',
	$root . '/mhm-rentiva.php',
	$root . '/uninstall.php',
);

$php_files = array();
foreach ( $scan_targets as $target ) {
	if ( is_file( $target ) ) {
		$php_files[] = $target;
		continue;
	}
	if ( ! is_dir( $target ) ) {
		continue;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $target, RecursiveDirectoryIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$php_files[] = $file->getPathname();
		}
	}
}

$unguarded = array();
foreach ( $php_files as $path ) {
	$code = (string) file_get_contents( $path );
	foreach ( $collect( $code, $seam_allow ) as $hit ) {
		// Normalise BOTH sides to forward slashes before stripping the root.
		// The iterator yields mixed separators on Windows (the scan target is
		// built with '/', the walked children with '\'), so a
		// DIRECTORY_SEPARATOR-based strip silently fails and prints an
		// absolute path. Mirrors bin/check-manifest.php.
		$relative = str_replace( '\\', '/', $path );
		$base     = str_replace( '\\', '/', $root ) . '/';
		if ( 0 === strpos( $relative, $base ) ) {
			$relative = substr( $relative, strlen( $base ) );
		}
		$unguarded[] = sprintf( '%s:%d  %s', $relative, $hit['line'], $hit['class'] );
	}
}

if ( array() !== $unguarded ) {
	sort( $unguarded );
	echo "Unguarded references to carved-out Pro classes (these fatal in Lite -- the class is not shipped):\n\n";
	echo implode( "\n", $unguarded ) . "\n\n";
	echo count( $unguarded ) . " found.\n";
	echo "Fix by guarding the call (class_exists()/is_class_available()), re-pointing it at a Lite class, or deleting the Pro-only content.\n";
	exit( 1 );
}

echo "[OK] Every reference to an allowlisted Pro class is guarded.\n";
exit( 0 );
