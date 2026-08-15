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

		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
				continue;
			}

			// Skip class methods. WP_Filesystem_Base and its subclasses define
			// file_exists(), is_file(), copy() and friends as METHODS; counting
			// those as admin-only functions made the first run of this gate
			// report 1300 names and flag every file_exists() call in the tree.
			$p = $i - 1;
			while ( $p >= 0 && is_array( $tokens[ $p ] ) && T_WHITESPACE === $tokens[ $p ][0] ) {
				$p--;
			}
			if ( $p >= 0 && is_array( $tokens[ $p ] ) && in_array(
				$tokens[ $p ][0],
				array( T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_ABSTRACT, T_FINAL ),
				true
			) ) {
				continue;
			}

			// Walk forward past whitespace and a by-reference ampersand.
			$j = $i + 1;
			while ( $j < $count && ( ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) || '&' === $tokens[ $j ] ) ) {
				$j++;
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

// Ask PHP, not a regex and not a tokenizer.
//
// The first version of this gate derived the inventory by tokenizing
// wp-admin/includes/*.php for `function <name>`. That counted CLASS METHODS as
// functions -- WP_Filesystem_Base and its subclasses define file_exists(),
// is_file(), copy() -- and reported 1300 names, so every file_exists() call in
// the tree came back as a finding. Loading WordPress the way a front-end
// request loads it, snapshotting get_defined_functions(), then loading the
// admin API and snapshotting again gives the set with no interpretation in
// between: whatever appeared is exactly what does not exist without wp-admin.
if ( ! is_readable( $core . '/wp-load.php' ) ) {
	echo "G-E: CANNOT MEASURE — {$core}/wp-load.php is not readable. Failing closed." . PHP_EOL;
	exit( 1 );
}

// wp-load.php echoes on some failure paths; keep the report clean.
ob_start();
require_once $core . '/wp-load.php';
$before = get_defined_functions()['user'];
require_once ABSPATH . 'wp-admin/includes/admin.php';
$after = get_defined_functions()['user'];
ob_end_clean();

$admin_only = array_flip( array_diff( $after, $before ) );

if ( count( $admin_only ) < 100 ) {
	printf( "G-E: CANNOT MEASURE — derived only %d admin-only functions, expected hundreds.%s", count( $admin_only ), PHP_EOL );
	echo "  Loading the admin API added almost nothing, which means it was already" . PHP_EOL;
	echo "  loaded or the core tree at {$core} is wrong. Failing closed." . PHP_EOL;
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
	$admin_hooked = array();
	if ( preg_match_all(
		"/add_action\(\s*['\"](?:admin_[a-z_]+|add_meta_boxes[a-z_]*|save_post[a-z_]*|load-[a-z_.\-]+|manage_[a-z_]+_columns)['\"][^)]*?,\s*array\(\s*[^,]+,\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/s",
		$contents,
		$hm
	) ) {
		$admin_hooked = array_flip( $hm[1] );
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

		// Is the admin API required anywhere in this file?
		if ( preg_match( '#require(_once)?\s*\(?\s*ABSPATH\s*\.\s*[\'"]wp-admin/includes/#', $contents ) ) {
			continue;
		}

		$line       = is_array( $tokens[ $i ] ) ? $tokens[ $i ][2] : 0;
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
