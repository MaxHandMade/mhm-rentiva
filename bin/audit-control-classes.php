<?php
/**
 * Rubric-driven control-class sweep over the whole plugin.
 *
 * WordPress.org's review reports a SAMPLE of what its scanner hit, not an
 * inventory. Auditing only the lines they cited scopes our work to their
 * sampling. `official/CONTROL-RUBRIC.md` exists so that each control class is
 * checked across every shipped file regardless of what any review mentioned;
 * this script is the machine-checkable part of that.
 *
 * It deliberately does NOT re-check what PHPCS already covers mechanically
 * (escape, sanitize, prepared SQL) -- run
 * `phpcs --standard=phpcs.xml --ignore-annotations` for those, and read the
 * result rather than the annotated one. What this covers is the set PHPCS
 * cannot see, because the defect is an ABSENCE rather than a flagged line:
 *
 *   A. state-changing entry points with no nonce verification
 *   B. state-changing entry points with no capability check
 *   C. nonce actions that are not scoped to the object being acted on
 *   D. register_setting() without a sanitize_callback
 *   E. shortcode callbacks that echo instead of returning
 *   F. allowlist comparisons that are not strict
 *   G. URLs escaped with esc_attr() where esc_url() is required
 *
 * ---------------------------------------------------------------------------
 * A REPORT, NOT A GATE. Section 0 lists what it cannot see; read that first.
 * ---------------------------------------------------------------------------
 *
 * Usage: php bin/audit-control-classes.php
 * Exit code is always 0.
 */

declare( strict_types=1 );

$root = __DIR__ . '/..';
$files = array();
foreach ( array( 'src', 'templates' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "{$root}/{$dir}" ) );
	foreach ( $it as $f ) {
		if ( $f->getExtension() === 'php' ) {
			$files[] = str_replace( '\\', '/', $f->getPathname() );
		}
	}
}
sort( $files );

$find = array( 'A' => array(), 'B' => array(), 'C' => array(), 'D' => array(), 'E' => array(), 'F' => array(), 'G' => array() );

$NONCE  = '(wp_verify_nonce|check_admin_referer|check_ajax_referer|verify_ajax_request|verify_nonce|submitted_nonce|authorize_booking_action)';
// authorize_booking_action() is DepositManagementAjax's shared entry guard: it
// resolves the booking and runs current_user_can('edit_post', $id) against it.
$CAP    = '(current_user_can|check_granular_permission|checkGranularPermission|authorize_booking_action|is_user_logged_in|WP_UNINSTALL_PLUGIN)';

foreach ( $files as $path ) {
	$src   = (string) file_get_contents( $path );
	$short = substr( $path, strlen( $root ) + 1 );

	// ---- Which methods are state-changing entry points? -------------------
	$entry = array();
	preg_match_all(
		"/add_action\(\s*['\"](wp_ajax_(?:nopriv_)?[a-z_]+|admin_post_(?:nopriv_)?[a-z_]+|save_post[a-z_]*)['\"]\s*,\s*array\(\s*[^,]+,\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/",
		$src,
		$m,
		PREG_SET_ORDER
	);
	foreach ( $m as $hit ) {
		$entry[ $hit[2] ] = $hit[1];
	}
	if ( ! $entry ) {
		continue;
	}

	foreach ( split_functions( $src ) as [$name, $body, $line] ) {
		if ( ! isset( $entry[ $name ] ) ) {
			continue;
		}
		$hook   = $entry[ $name ];
		$public = str_contains( $hook, '_nopriv_' );

		// Does it change state at all?
		$mutates = (bool) preg_match(
			'/\b(update_option|add_option|delete_option|update_post_meta|add_post_meta|delete_post_meta|update_user_meta|delete_user_meta|wp_insert_post|wp_update_post|wp_delete_post|wp_insert_user|wp_create_user|wp_update_user|wp_delete_user|\$wpdb->(insert|update|delete|replace|query)|wp_set_object_terms|wp_mail)\b/',
			$body
		);
		if ( ! $mutates ) {
			continue;
		}

		if ( ! preg_match( "/{$NONCE}\s*\(/", $body ) ) {
			$find['A'][] = "{$short}:{$line}  {$name}()  [{$hook}]";
		}
		if ( ! $public && ! preg_match( "/{$CAP}\s*\(/", $body ) ) {
			$find['B'][] = "{$short}:{$line}  {$name}()  [{$hook}]";
		}

		// C. nonce action scoped to the object?
		if ( preg_match( '/\$_(POST|GET|REQUEST)\s*\[\s*\'[a-z_]*(post_)?id\'|->int\(\s*\'[a-z_]*id\'/', $body ) ) {
			$scoped = preg_match( "/{$NONCE}\s*\(\s*[^,)]*,\s*[^,)]*\.\s*\\\$/", $body )
				|| preg_match( '/\'[a-z_-]+_\'\s*\.\s*\$/', $body );
			if ( ! $scoped ) {
				$find['C'][] = "{$short}:{$line}  {$name}()";
			}
		}
	}
}

// ---- D. register_setting without sanitize_callback -----------------------
foreach ( $files as $path ) {
	$src   = (string) file_get_contents( $path );
	$short = substr( $path, strlen( $root ) + 1 );
	// Match the CALL, not the substring: `mhm_rentiva_register_settings_providers`
	// contains "register_setting" and would otherwise be reported four times.
	if ( preg_match_all( '/\bregister_setting\s*\(/', $src, $mm, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $mm[0] as $hit ) {
			$seg = substr( $src, $hit[1], 900 );
			if ( ! str_contains( $seg, 'sanitize_callback' ) ) {
				$find['D'][] = $short . ':' . ( substr_count( $src, "\n", 0, $hit[1] ) + 1 );
			}
		}
	}

	// ---- E. shortcode callback echoing --------------------------------------
	foreach ( array_keys_of( $src, 'add_shortcode' ) as $off ) {
		$seg = substr( $src, $off, 300 );
		if ( preg_match( "/add_shortcode\(\s*[^,]+,\s*(?:function|fn)\s*\([^)]*\)\s*(?:=>)?\s*\{?(.{0,400})/s", $seg, $mm ) ) {
			if ( preg_match( '/\becho\b/', $mm[1] ) && ! str_contains( $mm[1], 'ob_start' ) ) {
				$find['E'][] = $short . ':' . ( substr_count( $src, "\n", 0, $off ) + 1 );
			}
		}
	}

	// ---- F. non-strict allowlist comparison ---------------------------------
	foreach ( preg_split( "/\n/", $src ) as $i => $line ) {
		if ( preg_match( '/\bin_array\s*\(/', $line ) && ! str_contains( $line, 'true' ) ) {
			$find['F'][] = $short . ':' . ( $i + 1 ) . '  ' . trim( substr( $line, 0, 90 ) );
		}
		// ---- G. URL through esc_attr ----------------------------------------
		if ( preg_match( '/esc_attr\(\s*\$?[a-zA-Z_>\-\[\]\'"]*(url|link|href|permalink)/i', $line ) ) {
			$find['G'][] = $short . ':' . ( $i + 1 ) . '  ' . trim( substr( $line, 0, 90 ) );
		}
	}
}

$titles = array(
	'A' => 'State-changing entry point with NO nonce verification',
	'B' => 'State-changing entry point with NO capability check (nopriv excluded)',
	'C' => 'Nonce action not scoped to the object the handler acts on',
	'D' => 'register_setting() without sanitize_callback',
	'E' => 'Shortcode callback that echoes instead of returning',
	'F' => 'in_array() without strict comparison',
	'G' => 'URL escaped with esc_attr() instead of esc_url()',
);

echo "0. What this cannot see -- check these by hand\n";
echo str_repeat( '-', 74 ) . "\n";
echo "   closures and string callables on hooks; handlers reached through a\n";
echo "   router (the gate is on the dispatcher); capability decided by a helper\n";
echo "   this script does not know the name of; REST routes (see\n";
echo "   audit-object-capabilities.php); anything PHPCS covers -- run it with\n";
echo "   --ignore-annotations and read THAT number, not the annotated one.\n\n";

foreach ( $titles as $k => $title ) {
	$rows = array_unique( $find[ $k ] );
	printf( "%s. %s: %d\n", $k, $title, count( $rows ) );
	echo str_repeat( '-', 74 ) . "\n";
	foreach ( $rows as $r ) {
		echo "   {$r}\n";
	}
	if ( ! $rows ) {
		echo "   (none)\n";
	}
	echo "\n";
}

/**
 * @return array<int, int>
 */
function array_keys_of( string $haystack, string $needle ): array {
	$out = array();
	$off = 0;
	while ( ( $off = strpos( $haystack, $needle, $off ) ) !== false ) {
		$out[] = $off;
		$off  += strlen( $needle );
	}
	return $out;
}

/**
 * @return array<int, array{0:string,1:string,2:int}>
 */
function split_functions( string $src ): array {
	$out = array();
	if ( ! preg_match_all( '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		return $out;
	}
	foreach ( $m[1] as $i => $hit ) {
		$start = strpos( $src, '{', $m[0][ $i ][1] );
		if ( false === $start ) {
			continue;
		}
		$depth = 0;
		$end   = $start;
		$len   = strlen( $src );
		for ( $j = $start; $j < $len; $j++ ) {
			if ( '{' === $src[ $j ] ) {
				++$depth;
			} elseif ( '}' === $src[ $j ] ) {
				--$depth;
				if ( 0 === $depth ) {
					$end = $j;
					break;
				}
			}
		}
		$out[] = array( $hit[0], substr( $src, $start, $end - $start + 1 ), substr_count( $src, "\n", 0, $hit[1] ) + 1 );
	}
	return $out;
}
