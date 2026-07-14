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
 *     are only checked when they already start with `MHMRentiva\`. This also
 *     protects against false positives from call sites where the string isn't
 *     a class name at all -- e.g. `BookingReport::is_class_available()` uses
 *     its argument as an internal cache key ('Core\ObjectCache'), and a few
 *     `Plugin::is_class_available()` call sites pass a namespace-relative
 *     fragment ('Admin\REST\ErrorHandler') that is not a valid runtime class
 *     name either. Both are correctly ignored by requiring the `MHMRentiva\`
 *     prefix to already be present in the literal.
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
 * be unit tested); scans src/, mhm-rentiva.php and uninstall.php -- the exact
 * paths phpstan.neon analyses -- and exits non-zero on findings when run
 * directly.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

/**
 * Collect MHMRentiva class names referenced inside availability guards.
 *
 * @param string $code PHP source.
 * @return array<int, array{line:int, class:string}>
 */
$collect = static function ( string $code ): array {
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

	$namespace   = '';
	$uses        = array(); // lowercase alias => imported FQN (no leading backslash).
	$depth       = 0;
	$guard_names = array( 'class_exists', 'interface_exists', 'trait_exists', 'is_class_available' );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( '{' === $token ) {
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

		if ( ! in_array( strtolower( $token[1] ), $guard_names, true ) ) {
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
			if ( 0 === strpos( $literal, 'MHMRentiva\\' ) ) {
				$resolved = $literal;
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

$root     = dirname( __DIR__ );
$dangling = array();

// The exact paths phpstan.neon analyses -- this checker exists to cover the
// blind spot PHPStan has within that same scope.
$scan_targets = array(
	$root . '/src',
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
		// Mirrors the plugin's own PSR-4-ish autoloader (mhm-rentiva.php):
		// MHMRentiva\Foo\Bar -> src/Foo/Bar.php.
		$relative      = str_replace( array( 'MHMRentiva\\', '\\' ), array( '', '/' ), $hit['class'] ) . '.php';
		$expected_path = $root . '/src/' . $relative;

		if ( ! is_file( $expected_path ) ) {
			$relative_source = str_replace( $root . DIRECTORY_SEPARATOR, '', $path );
			$relative_source = str_replace( '\\', '/', $relative_source );
			$dangling[]      = sprintf( '%s:%d  %s', $relative_source, $hit['line'], $hit['class'] );
		}
	}
}

if ( array() !== $dangling ) {
	echo "Dangling guarded references (the class named in the guard does not exist):\n\n";
	echo implode( "\n", $dangling ) . "\n\n";
	echo count( $dangling ) . " found.\n";
	exit( 1 );
}

echo "[OK] Every guarded MHMRentiva class reference resolves.\n";
exit( 0 );
