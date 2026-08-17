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
 * What it cannot see, by construction:
 *   - writes through a variable function name, call_user_func(), or a WP hook
 *     that a third party fires (user_register consumers, etc.)
 *   - direct $wpdb writes to the users/usermeta tables
 *   - user meta writes (update_user_meta) -- deliberately out of scope: they are
 *     an order of magnitude more common and mostly self-scoped preference
 *     storage. The T9 class is account writes.
 *
 * Usage:  php bin/audit-user-account-writes.php [--update]
 *         --update rewrites the inventory with TODO justifications for new
 *         entries. Fill them in; the script does not read them, humans do.
 *
 * Exit: 0 when the inventory matches, 1 when it does not.
 */

declare( strict_types=1 );

const WATCHED_FUNCTIONS = array( 'wp_create_user', 'wp_insert_user', 'wp_update_user', 'wp_delete_user' );
const WATCHED_METHODS   = array( 'set_role', 'add_role', 'add_cap' );

$src           = __DIR__ . '/../src';
$inventory_file = __DIR__ . '/user-write-inventory.txt';
$update         = in_array( '--update', array_slice( $argv, 1 ), true );

if ( ! is_dir( $src ) ) {
	fwrite( STDERR, "src/ not found at {$src}\n" );
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
		// (`function set_role`) must never count as a call site.
		$is_arrow_call = is_array( $prev ) && T_OBJECT_OPERATOR === $prev[0];
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

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
$actual   = array();

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$rel    = 'src/' . str_replace( '\\', '/', substr( $file->getPathname(), strlen( $src ) + 1 ) );
	$actual = array_merge( $actual, scan_file( $file->getPathname(), $rel ) );
}

sort( $actual );

$recorded = array();
if ( is_file( $inventory_file ) ) {
	foreach ( file( $inventory_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		if ( '#' === substr( ltrim( $line ), 0, 1 ) ) {
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
	$out = array(
		'# User-account write sites in src/ -- generated by bin/audit-user-account-writes.php.',
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

printf( "User-account write sites found in src/: %d\n", count( $actual ) );
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
