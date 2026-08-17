<?php
/**
 * Inventory lock: every place in src/ that writes a WordPress user account.
 *
 * WordPress.org's T9 review flagged CustomersOptimizer::update_customer_data(),
 * a wp_update_user() call reached only from batch_update_customers(), which was
 * gated on the blanket edit_users and asked no per-target question. The T8 round
 * had already swept that defect class and fixed six surfaces -- bulk_delete,
 * get_detail, the two customer screens and three CustomerExporter sites -- and
 * still missed this one. Both gates were blind for the same structural reason:
 *
 *   - bin/audit-object-capabilities.php starts from REGISTERED HANDLERS
 *     (wp_ajax_*, admin_post_*, REST callbacks) and walks inward. A plain static
 *     service method is not reachable from any registration, so the script never
 *     considered it and reported "(none)".
 *   - the suite had two tests naming batch_update_customers() by name, and both
 *     asserted the blanket-only behaviour was CORRECT. 2013 green tests said
 *     nothing because the defect was the expectation.
 *
 * So this script starts from the opposite end: the WRITE ITSELF. Calling
 * wp_create_user/wp_update_user/wp_delete_user or $user->set_role() is a literal
 * token, and a tokenizer cannot miss one the way a call-graph walk can.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS CLAIMS, EXACTLY
 * ---------------------------------------------------------------------------
 *
 * It claims one narrow, complete thing: "the set of user-account write sites in
 * src/ is exactly the set recorded in bin/user-write-inventory.txt". It does NOT
 * claim any of them is correctly guarded -- it cannot, and a script that said so
 * would be the "0 ERROR while honouring 500 of our own suppressions" failure all
 * over again.
 *
 * That narrowness is what makes it safe to fail a build on. A new user write
 * appearing without a line in the inventory means nobody looked at it yet; a
 * stale inventory line means the write moved or went away and the justification
 * beside it is now describing nothing. Both deserve a red build. Whether the
 * justification is TRUE stays a human question, re-asked whenever this file
 * makes someone edit the inventory.
 *
 * What it cannot see, by construction. A probe that does not publish its blind
 * spots is the "0 found" failure again, so this list is part of the tool:
 *   - writes through a variable function name, call_user_func(), or a WP hook
 *     that a third party fires (user_register consumers, etc.)
 *   - direct $wpdb writes to the users/usermeta tables
 *   - user meta writes (update_user_meta) -- deliberately out of scope: they are
 *     an order of magnitude more common and mostly self-scoped preference
 *     storage. The T9 class is account writes.
 *   - the sibling Pro plugin, which shares this namespace. It is not scanned, and
 *     the class HAS a live member over there: VendorOnboardingController::suspend()
 *     and ::unsuspend() take a user id from a REST request and add/remove roles
 *     with no check of their own, behind a blanket manage_options route gate.
 *     Recorded rather than fixed here because Pro is not part of the
 *     WordPress.org package. Do not read a clean run as "the ecosystem is clean".
 *
 * Usage:  php bin/audit-user-account-writes.php [--update]
 *         --update rewrites the inventory with TODO justifications for new
 *         entries. Fill them in; the script does not read them, humans do. It
 *         preserves the file's leading comment block and the justification of
 *         every entry that still exists.
 *         NEVER wire --update into CI: it always exits 0, so the gate would
 *         never go red, and it would silently absorb the very write it exists
 *         to make somebody look at.
 *
 * Exit: 0 when the inventory matches, 1 when it does not.
 */

declare( strict_types=1 );

const WATCHED_FUNCTIONS = array( 'wp_create_user', 'wp_insert_user', 'wp_update_user', 'wp_delete_user', 'wp_set_password' );

// Removing a role is a role change as much as granting one. The live Pro instance
// of this defect class uses remove_role, so a watcher that only knew add_role
// would have walked straight past it.
const WATCHED_METHODS = array( 'set_role', 'add_role', 'remove_role', 'add_cap', 'remove_cap' );

// src/ holds the classes, but it is not everything that ships and runs: the
// bootstrap file, the uninstall handler and the templates are all PHP in the
// release ZIP, and an account write in any of them would count just as much.
// None has one today -- scanning them is what keeps that a measurement.
// realpath() throughout: without it the roots come back as "<plugin>/bin/../src"
// and every entry is recorded under a path nobody would ever type, which reads as
// a whole-inventory mismatch on the first run.
$roots = array_values(
	array_filter(
		array_map(
			static fn( string $path ) => realpath( $path ),
			array(
				__DIR__ . '/../src',
				__DIR__ . '/../templates',
				__DIR__ . '/../mhm-rentiva.php',
				__DIR__ . '/../uninstall.php',
			)
		)
	)
);

$plugin_root    = (string) realpath( __DIR__ . '/..' );
$inventory_file = __DIR__ . '/user-write-inventory.txt';
$update         = in_array( '--update', array_slice( $argv, 1 ), true );

if ( ! $roots ) {
	fwrite( STDERR, "nothing to scan under {$plugin_root}\n" );
	exit( 1 );
}

/**
 * Every user-account write in one file, keyed "Class::method -> call".
 *
 * @param string $path Absolute file path.
 * @param string $rel  Path as it should appear in the inventory.
 * @return array<int, string>
 */
function scan_file( string $path, string $rel ): array {
	$found  = array();
	$tokens = token_get_all( (string) file_get_contents( $path ) );

	$class    = '';
	$function = '';
	$count    = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) ) {
			continue;
		}

		// Track the enclosing class and function so a finding names a place a
		// person can open, rather than a line number that churns on every edit.
		if ( T_CLASS === $token[0] || T_INTERFACE === $token[0] || T_TRAIT === $token[0] ) {
			$name = next_name( $tokens, $i, $count );
			if ( '' !== $name ) {
				$class = $name;
			}
			continue;
		}

		if ( T_FUNCTION === $token[0] ) {
			$function = next_name( $tokens, $i, $count );
			continue;
		}

		if ( T_STRING !== $token[0] ) {
			continue;
		}

		$name = $token[1];
		$prev = prev_significant( $tokens, $i );

		// A method call ($user->set_role) versus a plain function call
		// (wp_update_user). The arrow is what separates them, and a definition
		// (`function set_role`) must never count as a call site. The nullsafe
		// arrow counts too: $user?->set_role() writes exactly as much as
		// $user->set_role(), and treating only T_OBJECT_OPERATOR as "a call"
		// would hide it.
		$arrow_tokens = array( T_OBJECT_OPERATOR );
		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
			$arrow_tokens[] = T_NULLSAFE_OBJECT_OPERATOR;
		}

		$is_arrow_call = is_array( $prev ) && in_array( $prev[0], $arrow_tokens, true );
		$is_definition = is_array( $prev ) && T_FUNCTION === $prev[0];

		if ( $is_definition ) {
			continue;
		}

		$matched = false;
		if ( in_array( $name, WATCHED_FUNCTIONS, true ) && ! $is_arrow_call ) {
			$matched = true;
		}
		if ( in_array( $name, WATCHED_METHODS, true ) && $is_arrow_call ) {
			$matched = true;
		}

		if ( ! $matched ) {
			continue;
		}

		// Only a real call has an argument list behind it; this also drops the
		// function name appearing inside a string or a docblock reference that
		// the tokenizer handed us as T_STRING for some other reason.
		if ( '(' !== next_significant_char( $tokens, $i ) ) {
			continue;
		}

		$where   = ( '' !== $class ? $class : '(file scope)' ) . '::' . ( '' !== $function ? $function : '(file scope)' );
		$found[] = sprintf( '%s | %s | %s', $rel, $where, $name );
	}

	return array_values( array_unique( $found ) );
}

/**
 * The identifier that follows a T_CLASS / T_FUNCTION token.
 *
 * @param array<int, mixed> $tokens Token stream.
 * @param int               $i      Index of the keyword.
 * @param int               $count  Token count.
 * @return string
 */
function next_name( array $tokens, int $i, int $count ): string {
	for ( $j = $i + 1; $j < $count; $j++ ) {
		if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
			return $tokens[ $j ][1];
		}
		if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		// An anonymous function or an anonymous class: `function (` / `class {`.
		return '';
	}
	return '';
}

/**
 * The previous token that is not whitespace or a comment.
 *
 * @param array<int, mixed> $tokens Token stream.
 * @param int               $i      Index to look back from.
 * @return mixed
 */
function prev_significant( array $tokens, int $i ) {
	for ( $j = $i - 1; $j >= 0; $j-- ) {
		if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		return $tokens[ $j ];
	}
	return null;
}

/**
 * The next non-whitespace character, so a call can be told from a mention.
 *
 * @param array<int, mixed> $tokens Token stream.
 * @param int               $i      Index to look forward from.
 * @return string
 */
function next_significant_char( array $tokens, int $i ): string {
	$count = count( $tokens );
	for ( $j = $i + 1; $j < $count; $j++ ) {
		if ( is_array( $tokens[ $j ] ) ) {
			if ( in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return '';
		}
		return $tokens[ $j ];
	}
	return '';
}

/**
 * Path as the inventory spells it: relative to the plugin root, forward slashes.
 *
 * @param string $absolute    Absolute file path.
 * @param string $plugin_root Absolute plugin root.
 * @return string
 */
function relative_path( string $absolute, string $plugin_root ): string {
	$normalised = str_replace( '\\', '/', $absolute );
	$root       = str_replace( '\\', '/', $plugin_root ) . '/';

	return str_starts_with( $normalised, $root ) ? substr( $normalised, strlen( $root ) ) : $normalised;
}

$actual = array();

foreach ( $roots as $root ) {
	if ( is_file( $root ) ) {
		$actual = array_merge( $actual, scan_file( $root, relative_path( $root, $plugin_root ) ) );
		continue;
	}

	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$actual = array_merge( $actual, scan_file( $file->getPathname(), relative_path( $file->getPathname(), $plugin_root ) ) );
	}
}

$actual = array_values( array_unique( $actual ) );
sort( $actual );

$recorded = array();
$header   = array();

if ( is_file( $inventory_file ) ) {
	foreach ( file( $inventory_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		if ( '#' === substr( ltrim( $line ), 0, 1 ) ) {
			// The header is where the CREATE-vs-WRITE taxonomy and the date the
			// justifications were last read against the code live. --update used
			// to overwrite it with four generated lines, which threw away the
			// most considered part of the file.
			$header[] = $line;
			continue;
		}
		// "src/... | Class::method | wp_update_user  -- justification"
		$parts      = explode( '--', $line, 2 );
		$recorded[ trim( $parts[0] ) ] = isset( $parts[1] ) ? trim( $parts[1] ) : '';
	}
}

$new     = array_values( array_diff( $actual, array_keys( $recorded ) ) );
$stale   = array_values( array_diff( array_keys( $recorded ), $actual ) );

if ( $update ) {
	$out = $header ? array_merge( $header, array( '' ) ) : array(
		'# User-account write sites -- checked by bin/audit-user-account-writes.php.',
		'# One line per call site. The text after "--" is for humans: say WHY this',
		'# surface may write a user account and WHICH per-target check stands beside',
		'# the write. The script does not read it; the next person to touch this file does.',
		'',
	);
	foreach ( $actual as $entry ) {
		$note  = $recorded[ $entry ] ?? 'TODO: justify this write and name the per-target check beside it.';
		$out[] = $entry . '  -- ' . $note;
	}
	file_put_contents( $inventory_file, implode( "\n", $out ) . "\n" );
	printf( "Inventory rewritten: %d site(s).\n", count( $actual ) );
	exit( 0 );
}

printf( "User-account write sites found (src, templates, bootstrap, uninstall): %d\n", count( $actual ) );
printf( "Recorded in %s: %d\n\n", basename( $inventory_file ), count( $recorded ) );

if ( ! $new && ! $stale ) {
	echo "Inventory matches. (This says the SET is known, not that any guard is correct.)\n";
	exit( 0 );
}

if ( $new ) {
	echo "NEW -- a user account is written here and nobody has justified it yet:\n";
	foreach ( $new as $entry ) {
		echo "  {$entry}\n";
	}
	echo "\n";
}

if ( $stale ) {
	echo "STALE -- recorded, but no longer present. The justification beside it describes nothing:\n";
	foreach ( $stale as $entry ) {
		echo "  {$entry}\n";
	}
	echo "\n";
}

echo "Run with --update to rewrite the inventory, then fill in the justifications.\n";
exit( 1 );
