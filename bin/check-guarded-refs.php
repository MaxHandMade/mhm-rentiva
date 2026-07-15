<?php
/**
 * Guarded-reference checker.
 *
 * PHPStan reads `class_exists( Foo::class )` as type narrowing and stays silent
 * about a dangling reference inside the guard -- carved-out code hides behind
 * exactly those guards (proven: Task 1's PHPStan gate was green while
 * src/Plugin.php still referenced `MessageListTable`, deleted two months
 * earlier). This script closes that gap: every MHMRentiva class named inside a
 * `class_exists()` / `interface_exists()` / `trait_exists()` / `is_class_available()`
 * guard must actually resolve to a real file.
 *
 * Two guard shapes are recognised, matching how this codebase actually calls them:
 *
 *   - `class_exists( Foo\Bar::class )` -- the `::class` operand is a real
 *     compile-time class-name expression. It is resolved exactly the way the
 *     PHP compiler resolves it: through the file's `use` imports first, then
 *     falling back to the file's own `namespace` declaration. Naively
 *     prefixing the raw text with "MHMRentiva\" (without reading `use`
 *     imports) is wrong whenever the short name comes from an import -- e.g.
 *     `use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;` then later
 *     `AdvancedLogger::class` must resolve to
 *     `MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger`, not
 *     `MHMRentiva\AdvancedLogger`.
 *
 *   - `class_exists( 'MHMRentiva\Foo\Bar' )` / `is_class_available( 'MHMRentiva\Foo\Bar' )`
 *     -- a string literal passed to `class_exists()` is NEVER namespace-resolved
 *     by PHP at runtime; it must already be an absolute name. So string
 *     arguments are taken verbatim (only a leading backslash is stripped) and
 *     are only checked when they already start with `MHMRentiva\`.
 *
 *     A second, narrower check catches the *inverse* mistake: a string that
 *     is clearly meant to reference one of *our own* classes (because
 *     prefixing it with `MHMRentiva\` lands on a real file in src/) but is
 *     missing that prefix. Such a guard is dead by construction -- PHP never
 *     namespace-resolves a runtime string, so it can never be true. This is
 *     only ever checked for `class_exists()` / `interface_exists()` /
 *     `trait_exists()` themselves, and for wrapper methods that are proven
 *     (by inspecting their own body) to forward their argument *directly* to
 *     `class_exists()` -- e.g. `Plugin::is_class_available()`. It is
 *     deliberately NOT checked for other `is_class_available()`-named methods
 *     that don't have that exact shape: `BookingReport::is_class_available()`
 *     uses its string argument as an internal cache key ('Core\ObjectCache'),
 *     not a class name, and flagging it would be a false positive -- one of
 *     those cache-key fragments ('Admin\Reports\BackgroundProcessor')
 *     happens to collide with a real file path under src/, which is exactly
 *     why the wrapper-shape check (not just the file-existence check) is
 *     required.
 *
 * `self::class` / `static::class` / `parent::class` are ignored -- they name
 * the enclosing class, not an external reference.
 *
 * Existence is checked the same way the plugin's own autoloader resolves a
 * class (see the `spl_autoload_register()` call in mhm-rentiva.php):
 * `MHMRentiva\Foo\Bar` -> `src/Foo/Bar.php`. This is deliberately a file-system
 * check rather than a runtime `class_exists()` check: this plugin has no
 * Composer PSR-4 autoload map, and many classes assume a live WordPress
 * bootstrap (constants, functions) to even parse-time-evaluate their class
 * bodies, so actually loading them here would be unsafe and slow.
 *
 * Returns the pure collector when required from another PHP file (so it can
 * be unit tested); scans src/, templates/, mhm-rentiva.php and uninstall.php
 * -- templates/ is intentionally scanned even though PHPStan's own `paths`
 * doesn't cover it, because that's precisely the kind of blind spot this
 * checker exists to close -- and exits non-zero on findings when run
 * directly.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

// Computed up front (not inside the `is_main_script` branch below) so the
// collector closure can capture it and resolve "does this MHMRentiva class
// really exist?" against the real src/ tree even when it's exercised from a
// unit test with synthetic in-memory PHP source.
$root = dirname( __DIR__ );

/**
 * Resolve the file path a given MHMRentiva class/interface/trait name must
 * live at, mirroring the plugin's own autoloader (see the
 * `spl_autoload_register()` call in mhm-rentiva.php): `MHMRentiva\Foo\Bar` ->
 * `src/Foo/Bar.php`.
 *
 * @param string $mhmrentiva_class Fully-qualified name, e.g. `MHMRentiva\Foo\Bar`.
 * @return string Absolute expected file path.
 */
$class_file_path = static function ( string $mhmrentiva_class ) use ( $root ): string {
	$relative = str_replace( array( 'MHMRentiva\\', '\\' ), array( '', '/' ), $mhmrentiva_class ) . '.php';
	return $root . '/src/' . $relative;
};

/**
 * Collect MHMRentiva class names referenced inside availability guards.
 *
 * @param string $code PHP source.
 * @return array<int, array{line:int, class:string, kind:string}> `kind` is
 *         'dangling' (references a class that doesn't exist -- checked
 *         against the file system by the caller) or 'dead' (the guard
 *         argument is missing its 'MHMRentiva\' prefix and can therefore
 *         never resolve at runtime -- already confirmed against the file
 *         system here, since that confirmation is what rules out false
 *         positives on third-party classes and non-class-name strings).
 */
$collect = static function ( string $code ) use ( $class_file_path ): array {
	$found = array();

	$tokens = @token_get_all( $code );
	if ( ! is_array( $tokens ) ) {
		return $found;
	}
	$count = count( $tokens );

	$name_token_ids = array( T_STRING );
	if ( defined( 'T_NAME_QUALIFIED' ) ) {
		$name_token_ids[] = T_NAME_QUALIFIED;
	}
	if ( defined( 'T_NAME_FULLY_QUALIFIED' ) ) {
		$name_token_ids[] = T_NAME_FULLY_QUALIFIED;
	}
	if ( defined( 'T_NAME_RELATIVE' ) ) {
		$name_token_ids[] = T_NAME_RELATIVE;
	}

	$skip_ws = static function ( array $tokens, int $i, int $count ): int {
		while ( $i < $count ) {
			$t = $tokens[ $i ];
			if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				++$i;
				continue;
			}
			break;
		}
		return $i;
	};

	$prev_meaningful = static function ( array $tokens, int $i ) {
		--$i;
		while ( $i >= 0 ) {
			$t = $tokens[ $i ];
			if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				--$i;
				continue;
			}
			break;
		}
		return $i >= 0 ? $tokens[ $i ] : null;
	};

	// Decode a name token (T_STRING / T_NAME_QUALIFIED / T_NAME_FULLY_QUALIFIED /
	// T_NAME_RELATIVE) into [name-without-leading-backslash, is_fully_qualified, is_relative].
	$decode_name_token = static function ( array $token ): array {
		$id   = $token[0];
		$text = $token[1];

		if ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $id ) {
			return array( ltrim( $text, '\\' ), true, false );
		}
		if ( defined( 'T_NAME_RELATIVE' ) && T_NAME_RELATIVE === $id ) {
			return array( (string) preg_replace( '/^namespace\\\\/i', '', $text ), false, true );
		}
		// T_NAME_QUALIFIED (multi-segment, no leading backslash) or plain
		// T_STRING (single segment): resolved against `use` imports, then namespace.
		return array( $text, false, false );
	};

	$decode_string_literal = static function ( string $text ): string {
		$quote = $text[0];
		$inner = substr( $text, 1, -1 );
		if ( "'" === $quote ) {
			return str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), $inner );
		}
		return str_replace( '\\\\', '\\', $inner );
	};

	$last_segment = static function ( string $name ): string {
		$pos = strrpos( $name, '\\' );
		return false === $pos ? $name : substr( $name, $pos + 1 );
	};

	// ---- wrapper-body shape analysis helpers -----------------------------
	//
	// These recognise whether a wrapper's parameter reaches a
	// class_exists()/interface_exists()/trait_exists() call *directly* --
	// i.e. the call's result (or its condition, for an if/return form) is
	// the wrapper's return value with only boolean-neutral wrapping in
	// between (redundant parens, a `(bool)` cast, or a *paired* `!!`
	// negation -- a lone `!` inverts the result and must disqualify), and
	// the call's sole argument -- after stripping a redundant cast, an
	// optional PHP 8 named-argument `name:` prefix, and a trailing comma --
	// is exactly the wrapper's own parameter. Anything else (a different
	// callee, a different variable, extra statements, a cache lookup) is
	// NOT a forward and must be rejected.
	$cast_token_ids = array( T_INT_CAST, T_DOUBLE_CAST, T_STRING_CAST, T_ARRAY_CAST, T_OBJECT_CAST, T_BOOL_CAST, T_UNSET_CAST );

	// Strip a single pair of outer parens spanning the *entire* token list
	// (not just starting with '(' -- that pair must also be the one that
	// closes the list, or this isn't redundant grouping).
	$strip_outer_parens = static function ( array $tokens ): array {
		$n = count( $tokens );
		if ( $n < 2 || '(' !== $tokens[0] || ')' !== $tokens[ $n - 1 ] ) {
			return $tokens;
		}
		$paren_depth = 0;
		for ( $x = 0; $x < $n; $x++ ) {
			if ( '(' === $tokens[ $x ] ) {
				++$paren_depth;
			} elseif ( ')' === $tokens[ $x ] ) {
				--$paren_depth;
				if ( 0 === $paren_depth && $x !== $n - 1 ) {
					return $tokens; // Closes before the end -- not one wrapping pair.
				}
			}
		}
		return array_slice( $tokens, 1, $n - 2 );
	};

	// Split a token list on top-level commas (depth 0), dropping empty
	// trailing groups so a trailing comma doesn't count as a second argument.
	$split_top_level_commas = static function ( array $tokens ): array {
		$groups      = array();
		$current     = array();
		$paren_depth = 0;
		foreach ( $tokens as $t ) {
			if ( '(' === $t ) {
				++$paren_depth;
			} elseif ( ')' === $t ) {
				--$paren_depth;
			}
			if ( ',' === $t && 0 === $paren_depth ) {
				$groups[] = $current;
				$current  = array();
				continue;
			}
			$current[] = $t;
		}
		$groups[] = $current;
		return array_values(
			array_filter(
				$groups,
				static function ( $g ) {
					return array() !== $g;
				}
			)
		);
	};

	// Reduce a call argument's token list down to the plain variable name it
	// refers to (or null if it doesn't reduce to a single variable) --
	// stripping an optional named-argument `name:` prefix, any cast, and
	// redundant outer parens.
	$reduce_call_argument = static function ( array $tokens ) use ( $strip_outer_parens, $cast_token_ids ): ?string {
		// Optional PHP 8 named-argument prefix: `name: EXPR`. The name is
		// whatever identifier-like token spells the parameter ("class", for
		// class_exists()'s only argument) -- reserved words like `class`
		// tokenize as their own keyword token (T_CLASS), not T_STRING, so
		// this deliberately doesn't check the token's type, only that a
		// bare ':' immediately follows a single leading token.
		if ( count( $tokens ) >= 2 && is_array( $tokens[0] ) && ':' === $tokens[1] ) {
			$tokens = array_slice( $tokens, 2 );
		}

		$changed = true;
		while ( $changed ) {
			$changed = false;

			if ( array() !== $tokens && is_array( $tokens[0] ) && in_array( $tokens[0][0], $cast_token_ids, true ) ) {
				$tokens  = array_slice( $tokens, 1 );
				$changed = true;
				continue;
			}

			$without_parens = $strip_outer_parens( $tokens );
			if ( $without_parens !== $tokens ) {
				$tokens  = $without_parens;
				$changed = true;
			}
		}

		if ( 1 === count( $tokens ) && is_array( $tokens[0] ) && T_VARIABLE === $tokens[0][0] ) {
			return $tokens[0][1];
		}
		return null;
	};

	// Is this token list exactly `class_exists( ARG )` / `interface_exists( ARG )`
	// / `trait_exists( ARG )` -- a single argument that reduces to $param_var?
	$is_direct_guard_call = static function ( array $tokens, string $param_var ) use ( $reduce_call_argument, $split_top_level_commas ): bool {
		$n = count( $tokens );
		if (
			$n < 4
			|| ! is_array( $tokens[0] ) || T_STRING !== $tokens[0][0]
			|| ! in_array( strtolower( $tokens[0][1] ), array( 'class_exists', 'interface_exists', 'trait_exists' ), true )
			|| '(' !== $tokens[1]
			|| ')' !== $tokens[ $n - 1 ]
		) {
			return false;
		}

		$inner  = array_slice( $tokens, 2, $n - 3 );
		$groups = $split_top_level_commas( $inner );
		if ( 1 !== count( $groups ) ) {
			return false; // Zero or 2+ arguments -- not a simple single-argument forward.
		}

		return $reduce_call_argument( $groups[0] ) === $param_var;
	};

	// Peel boolean-neutral wrapping (redundant parens, `(bool)` casts, `!`
	// negations) off the front of an expression's token list, returning the
	// remaining "core" tokens plus how many `!` negations were stripped --
	// the caller must reject an odd count (it inverts the result).
	$peel_boolean_wrapping = static function ( array $tokens ) use ( $strip_outer_parens ): array {
		$negations = 0;
		$changed   = true;
		while ( $changed ) {
			$changed = false;

			if ( array() !== $tokens && '!' === $tokens[0] ) {
				$tokens = array_slice( $tokens, 1 );
				++$negations;
				$changed = true;
				continue;
			}

			if ( array() !== $tokens && is_array( $tokens[0] ) && T_BOOL_CAST === $tokens[0][0] ) {
				$tokens  = array_slice( $tokens, 1 );
				$changed = true;
				continue;
			}

			$without_parens = $strip_outer_parens( $tokens );
			if ( $without_parens !== $tokens ) {
				$tokens  = $without_parens;
				$changed = true;
			}
		}

		return array( $tokens, $negations );
	};

	// Does this expression's token list forward directly to a guard call on
	// $param_var, once boolean-neutral wrapping is peeled off?
	$is_forwarding_expression = static function ( array $tokens, string $param_var ) use ( $peel_boolean_wrapping, $is_direct_guard_call ): bool {
		list( $core, $negations ) = $peel_boolean_wrapping( $tokens );
		if ( 0 !== $negations % 2 ) {
			return false; // An odd number of `!` inverts the guard's meaning.
		}
		return $is_direct_guard_call( $core, $param_var );
	};

	// Does a (whitespace/comment-stripped) function body match one of the
	// two recognised forwarding shapes?
	//   1. `return EXPR;` -- a single return statement, EXPR forwards.
	//   2. `if ( COND ) { return true; } return false;` -- COND forwards.
	// Any other body (extra statements, a cache lookup, a different
	// control-flow shape) is rejected -- deliberately: this is what keeps
	// BookingReport::is_class_available() (a cache lookup, not a forward)
	// out of this classification without needing to special-case it by name.
	$body_is_forwarding = static function ( array $body, string $param_var ) use ( $is_forwarding_expression ): bool {
		$n = count( $body );

		if ( $n >= 4 && is_array( $body[0] ) && T_RETURN === $body[0][0] && ';' === $body[ $n - 1 ] ) {
			$expr = array_slice( $body, 1, $n - 2 );
			if ( ! in_array( ';', $expr, true ) && $is_forwarding_expression( $expr, $param_var ) ) {
				return true;
			}
		}

		if ( $n >= 3 && is_array( $body[0] ) && T_IF === $body[0][0] && '(' === $body[1] ) {
			$paren_depth = 0;
			$close       = -1;
			for ( $x = 1; $x < $n; $x++ ) {
				if ( '(' === $body[ $x ] ) {
					++$paren_depth;
				} elseif ( ')' === $body[ $x ] ) {
					--$paren_depth;
					if ( 0 === $paren_depth ) {
						$close = $x;
						break;
					}
				}
			}

			if ( -1 !== $close ) {
				$cond = array_slice( $body, 2, $close - 2 );
				$rest = array_slice( $body, $close + 1 );

				$is_true_kw  = static function ( $t ): bool {
					return is_array( $t ) && T_STRING === $t[0] && 'true' === strtolower( $t[1] );
				};
				$is_false_kw = static function ( $t ): bool {
					return is_array( $t ) && T_STRING === $t[0] && 'false' === strtolower( $t[1] );
				};

				if (
					8 === count( $rest )
					&& '{' === $rest[0]
					&& is_array( $rest[1] ) && T_RETURN === $rest[1][0]
					&& $is_true_kw( $rest[2] )
					&& ';' === $rest[3]
					&& '}' === $rest[4]
					&& is_array( $rest[5] ) && T_RETURN === $rest[5][0]
					&& $is_false_kw( $rest[6] )
					&& ';' === $rest[7]
					&& $is_forwarding_expression( $cond, $param_var )
				) {
					return true;
				}
			}
		}

		return false;
	};

	// ---- pre-pass: detect `is_class_available()`-style wrappers that
	// forward their argument *directly* to a class_exists()-family call ---
	//
	// Only a wrapper proven (via $body_is_forwarding, above) to forward its
	// sole parameter directly is safe to treat like a raw `class_exists()`
	// call for the "dead guard" check below. A wrapper with any other body
	// (e.g. one that looks up a cache keyed by its argument) must NOT be
	// treated the same way, or the checker would misread a non-class-name
	// string as a class reference. This is a purely syntactic, per-file
	// determination -- no class is actually loaded or resolved here.
	//
	// $is_class_available_non_forwarding_defs separately records every
	// *non*-forwarding definition literally named `is_class_available`
	// (regardless of why it was rejected), so that -- if this same file
	// also *relies* on that name as a real class checker (proven below by a
	// call site passing an already-'MHMRentiva\'-prefixed string, which
	// only makes sense against a genuine class_exists() proxy) -- the
	// checker can flag the mismatch loudly instead of silently disabling
	// the dead-guard check for that wrapper's call sites.
	$direct_forward_wrappers                = array();
	$is_class_available_non_forwarding_defs = array();

	for ( $fi = 0; $fi < $count; $fi++ ) {
		$ft = $tokens[ $fi ];
		if ( ! is_array( $ft ) || T_FUNCTION !== $ft[0] ) {
			continue;
		}

		$j = $skip_ws( $tokens, $fi + 1, $count );
		if ( $j < $count && '&' === $tokens[ $j ] ) {
			$j = $skip_ws( $tokens, $j + 1, $count );
		}
		if ( $j >= $count || ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) {
			continue;
		}
		$func_name      = strtolower( $tokens[ $j ][1] );
		$func_name_line = $tokens[ $j ][2] ?? 0;
		$j              = $skip_ws( $tokens, $j + 1, $count );
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// Collect top-level parameter variable names.
		$paren_depth = 1;
		++$j;
		$param_vars = array();
		while ( $j < $count && $paren_depth > 0 ) {
			$t = $tokens[ $j ];
			if ( '(' === $t ) {
				++$paren_depth;
				++$j;
				continue;
			}
			if ( ')' === $t ) {
				--$paren_depth;
				if ( 0 === $paren_depth ) {
					break;
				}
				++$j;
				continue;
			}
			if ( 1 === $paren_depth && is_array( $t ) && T_VARIABLE === $t[0] ) {
				$param_vars[] = $t[1];
			}
			++$j;
		}
		++$j; // Skip the closing ')'.

		// Skip an optional return-type declaration up to the body or a
		// semicolon (interface/abstract methods have no body).
		while ( $j < $count && '{' !== $tokens[ $j ] && ';' !== $tokens[ $j ] ) {
			++$j;
		}
		if ( $j >= $count || ';' === $tokens[ $j ] ) {
			continue;
		}

		$body_depth = 1;
		$k          = $j + 1;
		while ( $k < $count && $body_depth > 0 ) {
			if ( '{' === $tokens[ $k ] ) {
				++$body_depth;
			} elseif ( '}' === $tokens[ $k ] ) {
				--$body_depth;
				if ( 0 === $body_depth ) {
					break;
				}
			}
			++$k;
		}

		$body = array();
		for ( $m = $j + 1; $m < $k; $m++ ) {
			$t = $tokens[ $m ];
			if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$body[] = $t;
		}

		$is_forwarding = 1 === count( $param_vars ) && $body_is_forwarding( $body, $param_vars[0] );

		if ( $is_forwarding ) {
			$direct_forward_wrappers[ $func_name ] = true;
		} elseif ( 'is_class_available' === $func_name ) {
			$is_class_available_non_forwarding_defs[] = $func_name_line;
		}
	}

	$namespace   = '';
	$uses        = array(); // lowercase alias => imported FQN (no leading backslash).
	$depth       = 0;
	$guard_names = array( 'class_exists', 'interface_exists', 'trait_exists', 'is_class_available' );
	// Guard names whose bare-string argument is checked directly against
	// PHP's runtime lookup rules (no namespace resolution) -- used by the
	// "dead guard" (missing 'MHMRentiva\' prefix) check.
	$builtin_guard_names = array( 'class_exists', 'interface_exists', 'trait_exists' );
	// Proof that this file relies on `is_class_available()` as a genuine
	// class_exists() proxy: at least one call site passes it an already-
	// 'MHMRentiva\'-prefixed string, which is only ever meaningful against a
	// real forwarding wrapper (BookingReport's cache-key strings never look
	// like this). Set below, consumed after the loop.
	$is_class_available_relied_upon = false;

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if (
			'{' === $token
			|| ( is_array( $token ) && T_CURLY_OPEN === $token[0] )
			|| ( is_array( $token ) && T_DOLLAR_OPEN_CURLY_BRACES === $token[0] )
		) {
			// T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES are how the tokenizer
			// represents the opening delimiter of `"{$x}"` / `"${x}"` string
			// interpolation; PHP still closes both with a plain '}' char
			// token, so the opener must be counted too or $depth drifts.
			++$depth;
			continue;
		}
		if ( '}' === $token ) {
			--$depth;
			continue;
		}
		if ( ! is_array( $token ) ) {
			continue;
		}

		// ---- namespace declaration -----------------------------------
		if ( T_NAMESPACE === $token[0] && 0 === $depth ) {
			$j = $skip_ws( $tokens, $i + 1, $count );
			if ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], $name_token_ids, true ) ) {
				list( $name ) = $decode_name_token( $tokens[ $j ] );
				$namespace    = trim( $name, '\\' );
				$i            = $j;
			} else {
				// Global-namespace form: `namespace;` or `namespace {`.
				$namespace = '';
			}
			continue;
		}

		// ---- use-import statement (only meaningful at file scope) ----
		if ( T_USE === $token[0] && 0 === $depth ) {
			// A closure's `use ($var)` clause is also a `T_USE` token at
			// depth 0 when the closure itself is declared at file scope
			// (e.g. inside this very script). It is never preceded by
			// anything but the closure's parameter-list `)`. Treating it as
			// an import statement would scan for a terminating `;` through
			// the entire closure body -- silently swallowing any guards
			// inside it and corrupting the import map with bogus entries.
			$prev_for_use = $prev_meaningful( $tokens, $i );
			if ( ')' === $prev_for_use ) {
				continue;
			}

			$j = $skip_ws( $tokens, $i + 1, $count );

			// `use function ...;` / `use const ...;` are not class imports.
			if ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_FUNCTION, T_CONST ), true ) ) {
				while ( $j < $count && ';' !== $tokens[ $j ] ) {
					++$j;
				}
				$i = $j;
				continue;
			}

			while ( $j < $count && ';' !== $tokens[ $j ] ) {
				$j = $skip_ws( $tokens, $j, $count );
				if ( $j >= $count || ! is_array( $tokens[ $j ] ) || ! in_array( $tokens[ $j ][0], $name_token_ids, true ) ) {
					++$j; // Defensive: unexpected token shape, don't loop forever.
					continue;
				}

				list( $prefix ) = $decode_name_token( $tokens[ $j ] );
				++$j;
				$j = $skip_ws( $tokens, $j, $count );

				// Optional "\" right before a group-use "{".
				if ( $j < $count && ( '\\' === $tokens[ $j ] || ( is_array( $tokens[ $j ] ) && T_NS_SEPARATOR === $tokens[ $j ][0] ) ) ) {
					++$j;
					$j = $skip_ws( $tokens, $j, $count );
				}

				if ( $j < $count && '{' === $tokens[ $j ] ) {
					// Group use: `use Prefix\{Member (as Alias)?, ...};`
					++$j;
					while ( $j < $count && '}' !== $tokens[ $j ] ) {
						$j = $skip_ws( $tokens, $j, $count );

						if ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_FUNCTION, T_CONST ), true ) ) {
							while ( $j < $count && '}' !== $tokens[ $j ] && ',' !== $tokens[ $j ] ) {
								++$j;
							}
							if ( $j < $count && ',' === $tokens[ $j ] ) {
								++$j;
							}
							continue;
						}

						if ( $j >= $count || ! is_array( $tokens[ $j ] ) || ! in_array( $tokens[ $j ][0], $name_token_ids, true ) ) {
							++$j;
							continue;
						}

						list( $member ) = $decode_name_token( $tokens[ $j ] );
						++$j;
						$j     = $skip_ws( $tokens, $j, $count );
						$alias = null;
						if ( $j < $count && is_array( $tokens[ $j ] ) && T_AS === $tokens[ $j ][0] ) {
							$j = $skip_ws( $tokens, $j + 1, $count );
							if ( $j < $count && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
								$alias = $tokens[ $j ][1];
								++$j;
							}
						}

						$imported                     = trim( $prefix, '\\' ) . '\\' . trim( $member, '\\' );
						$alias                        = $alias ?? $last_segment( $member );
						$uses[ strtolower( $alias ) ] = $imported;

						$j = $skip_ws( $tokens, $j, $count );
						if ( $j < $count && ',' === $tokens[ $j ] ) {
							++$j;
						}
					}
					if ( $j < $count && '}' === $tokens[ $j ] ) {
						++$j;
					}
				} else {
					// Regular import: `use Prefix (as Alias)?`.
					$alias = null;
					if ( $j < $count && is_array( $tokens[ $j ] ) && T_AS === $tokens[ $j ][0] ) {
						$j = $skip_ws( $tokens, $j + 1, $count );
						if ( $j < $count && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
							$alias = $tokens[ $j ][1];
							++$j;
						}
					}

					$name                         = trim( $prefix, '\\' );
					$alias                        = $alias ?? $last_segment( $name );
					$uses[ strtolower( $alias ) ] = $name;
				}

				$j = $skip_ws( $tokens, $j, $count );
				if ( $j < $count && ',' === $tokens[ $j ] ) {
					++$j;
				}
			}

			$i = $j;
			continue;
		}

		// ---- guard call site -------------------------------------------
		if ( T_STRING !== $token[0] ) {
			continue;
		}

		$guard_name_lc = strtolower( $token[1] );
		if ( ! in_array( $guard_name_lc, $guard_names, true ) ) {
			continue;
		}

		// Skip the declaration site, e.g. `private function is_class_available(...)`.
		$prev = $prev_meaningful( $tokens, $i );
		if ( is_array( $prev ) && T_FUNCTION === $prev[0] ) {
			continue;
		}

		$call_line = $token[2] ?? 0;

		$j = $skip_ws( $tokens, $i + 1, $count );
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}
		++$j;

		// Collect the first argument's meaningful tokens.
		$paren_depth = 1;
		$meaningful  = array();
		while ( $j < $count && $paren_depth > 0 ) {
			$t = $tokens[ $j ];
			if ( '(' === $t ) {
				++$paren_depth;
				++$j;
				continue;
			}
			if ( ')' === $t ) {
				--$paren_depth;
				if ( 0 === $paren_depth ) {
					break;
				}
				++$j;
				continue;
			}
			if ( ',' === $t && 1 === $paren_depth ) {
				break;
			}
			if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				++$j;
				continue;
			}
			$meaningful[] = $t;
			++$j;
		}

		$i = $j;

		$resolved = null;

		if ( 1 === count( $meaningful ) && is_array( $meaningful[0] ) && T_CONSTANT_ENCAPSED_STRING === $meaningful[0][0] ) {
			$literal = ltrim( $decode_string_literal( $meaningful[0][1] ), '\\' );

			if ( 'is_class_available' === $guard_name_lc && 0 === strpos( $literal, 'MHMRentiva\\' ) ) {
				// Proof of reliance: this call only makes sense if
				// is_class_available() really is a class_exists() proxy.
				$is_class_available_relied_upon = true;
			}

			if ( 0 === strpos( $literal, 'MHMRentiva\\' ) ) {
				$resolved = $literal;
			} elseif (
				false !== strpos( $literal, '\\' )
				&& (
					in_array( $guard_name_lc, $builtin_guard_names, true )
					|| ( 'is_class_available' === $guard_name_lc && isset( $direct_forward_wrappers[ $guard_name_lc ] ) )
				)
			) {
				// Candidate for the "dead guard" check: a multi-segment
				// string, passed to a checkable guard, that is NOT already
				// 'MHMRentiva\'-prefixed. A missing prefix isn't the only way
				// this happens -- the prefix can also be malformed, e.g.
				// 'MHM\Rentiva\Admin\Messages\Core\MessageCache' (the
				// namespace root was typo'd apart into two segments instead
				// of the real 'MHMRentiva'). So every trailing (suffix) run
				// of the literal's segments is tried, longest first -- not
				// just the whole string -- requiring at least two segments
				// in the suffix so a single common leaf name alone (e.g.
				// 'Plugin', which would collide with this plugin's own
				// top-level src/Plugin.php) can never on its own be mistaken
				// for one of our classes. Only report when a suffix *does*
				// land on a real file -- that's the proof this was meant to
				// be one of our own classes (as opposed to a legitimate
				// third-party absolute reference like '\Elementor\Plugin',
				// which this checker has no business judging).
				$segments       = explode( '\\', $literal );
				$segment_count  = count( $segments );
				for ( $start = 0; $start <= $segment_count - 2; $start++ ) {
					$suffix    = implode( '\\', array_slice( $segments, $start ) );
					$candidate = 'MHMRentiva\\' . $suffix;
					if ( is_file( $class_file_path( $candidate ) ) ) {
						$found[] = array(
							'line'  => $call_line,
							'class' => $candidate,
							'kind'  => 'dead',
						);
						break;
					}
				}
			}
		} elseif (
			3 === count( $meaningful )
			&& is_array( $meaningful[0] ) && in_array( $meaningful[0][0], $name_token_ids, true )
			&& is_array( $meaningful[1] ) && T_DOUBLE_COLON === $meaningful[1][0]
			&& is_array( $meaningful[2] ) && T_CLASS === $meaningful[2][0]
		) {
			list( $raw, $is_fq, $is_relative ) = $decode_name_token( $meaningful[0] );

			$is_self_reference = ! $is_fq && ! $is_relative
				&& in_array( strtolower( $raw ), array( 'self', 'static', 'parent' ), true );

			if ( ! $is_self_reference ) {
				if ( $is_fq ) {
					$candidate = $raw;
				} elseif ( $is_relative ) {
					$candidate = '' !== $namespace ? $namespace . '\\' . $raw : $raw;
				} else {
					$segments = explode( '\\', $raw );
					$first    = array_shift( $segments );
					$key      = strtolower( $first );

					if ( isset( $uses[ $key ] ) ) {
						$candidate = $uses[ $key ] . ( array() !== $segments ? '\\' . implode( '\\', $segments ) : '' );
					} else {
						$candidate = '' !== $namespace ? $namespace . '\\' . $raw : $raw;
					}
				}

				if ( 0 === strpos( $candidate, 'MHMRentiva\\' ) ) {
					$resolved = $candidate;
				}
			}
		}

		if ( null !== $resolved ) {
			$found[] = array(
				'line'  => $call_line,
				'class' => $resolved,
				'kind'  => 'dangling',
			);
		}
	}

	// A non-forwarding `is_class_available()` definition in this file is
	// only worth flagging loudly when the file also *relies* on that name
	// as a real class checker -- otherwise every legitimate cache-lookup
	// wrapper (BookingReport::is_class_available(), which is never called
	// with an 'MHMRentiva\'-prefixed string) would trip this on its own.
	// A forwarding definition elsewhere in the same file (e.g. a sibling
	// class) suppresses this -- see the file-scoped-prepass caveat in the
	// module docblock.
	if (
		$is_class_available_relied_upon
		&& array() !== $is_class_available_non_forwarding_defs
		&& ! isset( $direct_forward_wrappers['is_class_available'] )
	) {
		foreach ( $is_class_available_non_forwarding_defs as $def_line ) {
			$found[] = array(
				'line'  => $def_line,
				'class' => 'is_class_available',
				'kind'  => 'unverified_wrapper',
			);
		}
	}

	return $found;
};

// When required by another PHP file (e.g. a test), hand back the collector and stop.
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$is_main_script  = '' !== $script_filename && @realpath( $script_filename ) === realpath( __FILE__ );

if ( ! $is_main_script ) {
	return $collect;
}

$dangling            = array();
$dead_guards         = array();
$unverified_wrappers = array();

// The exact paths phpstan.neon analyses, PLUS templates/ -- deliberately
// wider than PHPStan's own `paths`, since this checker exists to cover
// PHPStan's blind spot within its own scanned scope, not to inherit it.
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

foreach ( $php_files as $path ) {
	$code = (string) file_get_contents( $path );

	foreach ( $collect( $code ) as $hit ) {
		$relative_source = str_replace( $root . DIRECTORY_SEPARATOR, '', $path );
		$relative_source = str_replace( '\\', '/', $relative_source );

		if ( 'dead' === $hit['kind'] ) {
			// File existence was already confirmed inside $collect(); no
			// further check needed here.
			$dead_guards[] = sprintf( '%s:%d  %s', $relative_source, $hit['line'], $hit['class'] );
			continue;
		}

		if ( 'unverified_wrapper' === $hit['kind'] ) {
			$unverified_wrappers[] = sprintf( '%s:%d  %s()', $relative_source, $hit['line'], $hit['class'] );
			continue;
		}

		if ( ! is_file( $class_file_path( $hit['class'] ) ) ) {
			$dangling[] = sprintf( '%s:%d  %s', $relative_source, $hit['line'], $hit['class'] );
		}
	}
}

if ( array() !== $dangling || array() !== $dead_guards || array() !== $unverified_wrappers ) {
	if ( array() !== $dangling ) {
		echo "Dangling guarded references (the class named in the guard does not exist):\n\n";
		echo implode( "\n", $dangling ) . "\n\n";
	}

	if ( array() !== $dead_guards ) {
		echo "Dead guards (bare string is missing -- or has a malformed -- the 'MHMRentiva\\' prefix required by class_exists()/is_class_available() -- the guard can never be true):\n\n";
		echo implode( "\n", $dead_guards ) . "\n\n";
	}

	if ( array() !== $unverified_wrappers ) {
		echo "Unverified guard wrappers (this method is relied on elsewhere in the same file as a class_exists()-style checker -- called with an already 'MHMRentiva\\'-prefixed string -- but its body no longer matches a recognised forwarding shape, so the dead-guard check cannot verify any of its call sites in this file. Either restore a recognisable forward, or confirm by hand that every call site is still correctly prefixed):\n\n";
		echo implode( "\n", $unverified_wrappers ) . "\n\n";
	}

	echo ( count( $dangling ) + count( $dead_guards ) + count( $unverified_wrappers ) ) . " found.\n";
	exit( 1 );
}

echo "[OK] Every guarded MHMRentiva class reference resolves.\n";
exit( 0 );
