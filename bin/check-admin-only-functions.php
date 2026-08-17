<?php
/**
 * G-E: admin-only core functions called from paths that never load wp-admin.
 *
 * The class this gate exists for: wp_delete_user() is defined in
 * wp-admin/includes/user.php. A /wp-json/ request loads wp-load.php and stops
 * there, so on that path the function does not exist and the call is a fatal.
 * Every other gate we own was green while /customers/bulk died on its first
 * real dispatch: PHPUnit could not see it because the test bootstrap has the
 * admin API loaded, PHPCS and PHPStan only see a call to a function that does
 * exist somewhere, and Plugin Check does not model load context at all.
 *
 * The inventory is DERIVED, never listed by hand: every function defined under
 * wp-admin/includes/ and not defined under wp-includes/ is admin-only. If the
 * core tree cannot be found the gate fails closed rather than reporting clean.
 *
 * WHAT THIS GATE CANNOT SEE (state it, do not let a green imply it):
 *   - Files it does not classify as admin-free. Classification is by three
 *     signals only: a register_rest_route() call, a wp_ajax_nopriv_ hook, or a
 *     path under Frontend/. A handler reached from `init` on the front end
 *     without any of those markers is invisible here.
 *   - Indirect calls: call_user_func('wp_delete_user'), variable functions,
 *     and anything reached through a callback string.
 *   - Whether the require, when present, actually runs before the call.
 *
 * Exit 0 clean, 1 on findings or when it cannot measure.
 *
 * @package Mhm_Rentiva
 */

declare( strict_types=1 );

$plugin_dir = dirname( __DIR__ );

/**
 * Locate the WordPress core tree.
 *
 * @return string|null Absolute path to core root, or null when not found.
 */
function mhmrentiva_locate_core( string $plugin_dir ): ?string {
	$candidates = array(
		getenv( 'WP_CORE_DIR' ) ?: '',
		dirname( $plugin_dir, 3 ),          // wp-content/plugins/x -> core root.
		dirname( $plugin_dir, 2 ) . '/wp',  // host layout: <stack>/plugins/x -> <stack>/wp.
		'/var/www/html',
	);

	foreach ( $candidates as $candidate ) {
		if ( '' === $candidate ) {
			continue;
		}
		if ( is_dir( $candidate . '/wp-admin/includes' ) && is_dir( $candidate . '/wp-includes' ) ) {
			return rtrim( $candidate, '/' );
		}
	}

	return null;
}

/**
 * Collect function names defined in a directory of PHP files.
 *
 * Uses the tokenizer rather than a regex: a regex matches "function" inside
 * comments and strings, and misses nothing that matters only by accident.
 *
 * @param string $dir       Directory to scan.
 * @param bool   $recursive Whether to descend.
 * @return array<string,true>
 */
function mhmrentiva_defined_functions( string $dir, bool $recursive ): array {
	$names = array();

	$iterator = $recursive
		? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) )
		: new FilesystemIterator( $dir, FilesystemIterator::SKIP_DOTS );

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		$tokens = @token_get_all( (string) file_get_contents( $file->getPathname() ) );
		if ( ! is_array( $tokens ) ) {
			continue;
		}

		// Track brace depth so class/interface/trait/enum BODIES can be skipped.
		//
		// Only top-level functions count. WP_Filesystem_Base and its subclasses
		// define file_exists(), is_file() and copy() as METHODS; counting those
		// made an earlier version of this gate report 1300 names and flag every
		// file_exists() call in the plugin. Checking for a visibility keyword
		// before `function` is not enough either: interface methods and
		// old-style declarations carry no visibility, which is why this tracks
		// the actual body instead of guessing from one neighbouring token.
		$count        = count( $tokens );
		$depth        = 0;
		$class_depths = array();
		$pending      = false;

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ! is_array( $token ) ) {
				if ( '{' === $token ) {
					++$depth;
					if ( $pending ) {
						$class_depths[] = $depth;
						$pending        = false;
					}
				} elseif ( '}' === $token ) {
					if ( array() !== $class_depths && end( $class_depths ) === $depth ) {
						array_pop( $class_depths );
					}
					--$depth;
				}
				continue;
			}

			$type = $token[0];

			if ( T_CLASS === $type || T_INTERFACE === $type || T_TRAIT === $type
				|| ( defined( 'T_ENUM' ) && T_ENUM === $type ) ) {
				// `Foo::class` is also T_CLASS. A real declaration is never
				// preceded by ::.
				$p = $i - 1;
				while ( $p >= 0 && is_array( $tokens[ $p ] ) && T_WHITESPACE === $tokens[ $p ][0] ) {
					--$p;
				}
				if ( ! ( $p >= 0 && is_array( $tokens[ $p ] ) && T_DOUBLE_COLON === $tokens[ $p ][0] ) ) {
					$pending = true;
				}
				continue;
			}

			if ( T_FUNCTION !== $type || array() !== $class_depths ) {
				continue;
			}

			// Walk forward past whitespace and a by-reference ampersand.
			$j = $i + 1;
			while ( $j < $count && ( ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) || '&' === $tokens[ $j ] ) ) {
				++$j;
			}

			if ( $j < $count && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$names[ strtolower( $tokens[ $j ][1] ) ] = true;
			}
		}
	}

	return $names;
}

$core = mhmrentiva_locate_core( $plugin_dir );

if ( null === $core ) {
	echo "G-E: CANNOT MEASURE — WordPress core tree not found." . PHP_EOL;
	echo "  Looked for a directory holding both wp-admin/includes and wp-includes." . PHP_EOL;
	echo "  Set WP_CORE_DIR to the core root and re-run. Failing closed." . PHP_EOL;
	exit( 1 );
}

// Read the core tree. Do NOT load WordPress.
//
// The previous version derived the inventory at runtime: load wp-load.php,
// snapshot get_defined_functions(), load the admin API, snapshot again. That
// was more accurate in principle and wrong in practice, in two ways an audit
// measured:
//
//   1. Environment poisoning. Other active plugins require wp-admin/includes/
//      files at load time, so those functions were ALREADY in the "before"
//      snapshot and fell out of the diff. On the dev stack the entire
//      wp-admin/includes/plugin.php family (is_plugin_active, get_plugins,
//      get_plugin_data) was missing from the inventory -- a REST call to any of
//      them is fatal on a reviewer's clean site and this gate could not see it.
//      The inventory was only as complete as the host was minimal.
//   2. In CI it measured NOTHING and still passed. There is no installed
//      WordPress there -- install-wp-tests.sh lays down a test suite, not a
//      configured site -- so wp-load.php failed on a missing config, the
//      output buffer swallowed it, and the gate exited 0 without printing its
//      own summary line. A gate that cannot run must not look green.
//
// Reading the files is deterministic, needs no database, no config and no
// plugins, and gives the same answer in CI and on any developer's machine.
$admin_defined = mhmrentiva_defined_functions( $core . '/wp-admin/includes', false );
$core_defined  = mhmrentiva_defined_functions( $core . '/wp-includes', true );

// Admin-only = declared under wp-admin/includes and nowhere in wp-includes.
$admin_only = array_diff_key( $admin_defined, $core_defined );

// Fail closed, and check a KNOWN member rather than only a count: a count
// alone passed while a whole family was missing.
foreach ( array( 'wp_delete_user', 'is_plugin_active', 'get_plugins', 'add_meta_box' ) as $canary ) {
	if ( ! isset( $admin_only[ $canary ] ) ) {
		printf( "G-E: CANNOT MEASURE — %s() is missing from the derived inventory.%s", $canary, PHP_EOL );
		echo "  It is declared in wp-admin/includes and nowhere in wp-includes, so its" . PHP_EOL;
		echo "  absence means the core tree at {$core} was not read correctly." . PHP_EOL;
		exit( 1 );
	}
}

if ( count( $admin_only ) < 300 ) {
	printf( "G-E: CANNOT MEASURE — derived only %d admin-only functions, expected hundreds.%s", count( $admin_only ), PHP_EOL );
	echo "  The core tree at {$core} looks wrong or unreadable. Failing closed." . PHP_EOL;
	exit( 1 );
}

/**
 * Does this file sit on a path that never loads the admin API?
 *
 * @param string $path     File path.
 * @param string $contents File contents.
 * @return string|null Reason string when admin-free, null otherwise.
 */
function mhmrentiva_admin_free_reason( string $path, string $contents ): ?string {
	$unix = str_replace( '\\', '/', $path );

	if ( str_contains( $unix, '/REST/' ) || str_ends_with( $unix, 'RestController.php' ) ) {
		return 'is a REST controller';
	}
	if ( str_contains( $unix, '/Frontend/' ) ) {
		return 'lives under Frontend/';
	}

	return null;
}

$findings = array();
$scanned  = 0;
$calls    = 0;

$src = $plugin_dir . '/src';
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );

foreach ( $it as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$path     = $file->getPathname();
	$contents = (string) file_get_contents( $path );
	$reason   = mhmrentiva_admin_free_reason( $path, $contents );

	if ( null === $reason ) {
		continue;
	}

	$scanned++;

	$tokens = @token_get_all( $contents );
	if ( ! is_array( $tokens ) ) {
		continue;
	}

	// Methods this file wires to a hook that only ever fires inside wp-admin.
	// A file can sit under Frontend/ and still contribute an admin screen:
	// ContactMessagePostType registers add_details_box() on
	// add_meta_boxes_<type>, and that method calls add_meta_box(), which is
	// admin-only and correct. Without this the gate reported it as a defect.
	//
	// The list is explicit, NOT `admin_[a-z_]+`. That pattern also matched
	// admin_bar_menu, which fires on the FRONT END -- an audit mutation put an
	// unguarded wp_delete_user() in an admin_bar_menu handler and this gate
	// passed it. "Starts with admin_" is not the same claim as "only fires
	// inside wp-admin", and only the second one is safe to exempt.
	$admin_hooked = array();
	if ( preg_match_all(
		"/add_action\(\s*['\"](?:admin_init|admin_menu|admin_notices|admin_enqueue_scripts|admin_head[a-z_-]*|admin_footer[a-z_-]*|admin_post_[a-z_]+|add_meta_boxes[a-z_]*|save_post[a-z_]*|load-[a-z_.\-]+|manage_[a-z_]+_columns|wp_ajax_[a-z_]+)['\"][^)]*?,\s*array\(\s*[^,]+,\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/s",
		$contents,
		$hm
	) ) {
		$admin_hooked = array_flip( $hm[1] );
	}

	// Real require statements, taken from TOKENS and remembered by line.
	//
	// The first version asked preg_match() whether the file text contained a
	// require of wp-admin/includes. It does not distinguish code from comments:
	// an audit mutation commented the require out, left the fatal call in place,
	// and the gate stayed green. A require that is not code cannot load
	// anything, and one that appears after the call cannot help it either.
	$require_lines = array();
	for ( $t = 0; $t < count( $tokens ); $t++ ) {
		if ( ! is_array( $tokens[ $t ] ) ) {
			continue;
		}
		if ( ! in_array( $tokens[ $t ][0], array( T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE ), true ) ) {
			continue;
		}

		$window = '';
		$stop   = min( $t + 14, count( $tokens ) );
		for ( $w = $t; $w < $stop; $w++ ) {
			$window .= is_array( $tokens[ $w ] ) ? $tokens[ $w ][1] : $tokens[ $w ];
		}

		if ( str_contains( $window, 'wp-admin/includes/' ) ) {
			$require_lines[] = $tokens[ $t ][2];
		}
	}

	$current_method = '';
	$count          = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( is_array( $tokens[ $i ] ) && T_FUNCTION === $tokens[ $i ][0] ) {
			$n = $i + 1;
			while ( $n < $count && is_array( $tokens[ $n ] ) && T_WHITESPACE === $tokens[ $n ][0] ) {
				$n++;
			}
			$current_method = ( $n < $count && is_array( $tokens[ $n ] ) && T_STRING === $tokens[ $n ][0] )
				? $tokens[ $n ][1]
				: '';
		}

		if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] ) {
			continue;
		}

		if ( '' !== $current_method && isset( $admin_hooked[ $current_method ] ) ) {
			continue;
		}

		$name = strtolower( $tokens[ $i ][1] );
		if ( ! isset( $admin_only[ $name ] ) ) {
			continue;
		}

		// Must be a call: next non-whitespace token is '('.
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( $j >= $count || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// Must not be a method call or a definition: previous non-whitespace
		// token cannot be ->, ::, or the `function` keyword.
		$k = $i - 1;
		while ( $k >= 0 && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
			$k--;
		}
		if ( $k >= 0 && is_array( $tokens[ $k ] )
			&& in_array( $tokens[ $k ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}

		$calls++;

		$line = is_array( $tokens[ $i ] ) ? $tokens[ $i ][2] : 0;

		// Cleared only by a real require that lexically precedes the call.
		// Still file-scoped, which is coarser than it should be: a require in
		// one method clears a call in another, and a require of image.php
		// clears a call into user.php. Both are stated in the header.
		$guarded = false;
		foreach ( $require_lines as $require_line ) {
			if ( $require_line < $line ) {
				$guarded = true;
				break;
			}
		}
		if ( $guarded ) {
			continue;
		}

		$rel        = str_replace( str_replace( '\\', '/', $plugin_dir ) . '/', '', str_replace( '\\', '/', $path ) );
		$findings[] = sprintf( '%s:%d  %s() — file %s, no wp-admin/includes require', $rel, $line, $name, $reason );
	}
}

printf(
	'G-E: admin-only inventory=%d, admin-free files scanned=%d, admin-only calls found=%d, unguarded=%d%s',
	count( $admin_only ),
	$scanned,
	$calls,
	count( $findings ),
	PHP_EOL
);

foreach ( $findings as $finding ) {
	echo '  ' . $finding . PHP_EOL;
}

exit( count( $findings ) > 0 ? 1 : 0 );
