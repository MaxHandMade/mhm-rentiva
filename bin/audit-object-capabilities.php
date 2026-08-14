<?php
/**
 * Audit helper: find handlers that read an id out of the request and then check
 * a capability that is not about that object.
 *
 * This is the defect class that WordPress.org's T6 review surfaced and that then
 * turned up in four separate files during the fix round -- BookingMeta,
 * DepositManagementAjax, VehicleMeta, BlockedDatesMetaBox and VehicleGallery.
 * Spot checks kept missing instances, so this enumerates instead.
 *
 * ---------------------------------------------------------------------------
 * THIS IS A REPORT, NOT A GATE. Read the blind spots before trusting a "0".
 * ---------------------------------------------------------------------------
 *
 * A gate that under-reports is exactly the failure this whole exercise is about:
 * our PHPCS run said "0 ERROR" while honouring 500 of our own suppressions. So
 * this script is deliberately not wired into CI, and it prints what it could not
 * classify alongside what it flagged.
 *
 * What it CAN see:
 *   - methods wired via add_action( 'wp_ajax_*' | 'admin_post_*', array( X, 'method' ) )
 *   - methods wired via 'callback' => array( X, 'method' ) in register_rest_route
 *
 * What it CANNOT see (section 2 of the output lists these for manual review):
 *   - closures and string callables passed straight to add_action/register_rest_route
 *   - 'permission_callback' => $perm  where $perm was assigned earlier in the file
 *     (used across several REST controllers -- the callable has to be traced back
 *     to its definition by hand)
 *   - handlers reached through a router: one dispatcher checks the capability and
 *     then match()/switch()es to sub-methods that contain no check of their own.
 *     APIKeysPage::handle_request is the example. Reading the sub-method alone
 *     tells you nothing; the gate is upstream.
 *   - anything where the capability is decided by a helper (checkGranularPermission
 *     and friends) rather than a literal current_user_can() call.
 *
 * Usage: php bin/audit-object-capabilities.php [path-to-other-plugin ...]
 * Exit code is always 0 -- this reports, it does not fail a build.
 */

declare( strict_types=1 );

$roots = array_slice( $argv, 1 );
if ( ! $roots ) {
	$roots = array( __DIR__ . '/../src' );
}

// Capabilities that say nothing about which object is being acted on.
//
// Two families. The post family was here from the start. The user family was
// added after WordPress.org's T8 round flagged CustomersRestController::bulk_delete,
// which this script had reported clean: it is gated on delete_users, and
// delete_users was not on the list, so the handler was never even considered.
// These caps are blanket in exactly the sense that matters -- delete_users says
// the caller may delete users, not which user, and WordPress models the
// per-target question as the meta cap delete_user( $id ).
//
// create_users is deliberately NOT here. A create has no existing target for a
// per-object check to be about, so listing it only produced noise: the first run
// with it flagged ManualBookingMetaBox::ajax_create_booking(), which is nonce'd,
// gated on manage_options, and gates its user-creating branch on create_users
// separately. Dropping it narrows the report; the known-broken case
// (CustomersRestController::bulk_delete) still trips, which is the control that
// says the narrowing did not just quieten the script.
const BLANKET = '(edit_posts|publish_posts|read|upload_files|edit_published_posts|delete_posts|edit_others_posts'
	. '|delete_users|edit_users|list_users|promote_users|remove_users)';

$suspect     = array();
$unclassified = array();

foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) {
		fwrite( STDERR, "skip (not a directory): {$root}\n" );
		continue;
	}

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
	foreach ( $it as $file ) {
		if ( $file->getExtension() !== 'php' ) {
			continue;
		}

		$path = str_replace( '\\', '/', $file->getPathname() );
		$src  = (string) file_get_contents( $path );

		// Entry points wired by the shape we can resolve.
		preg_match_all(
			"/add_action\(\s*['\"](?:wp_ajax_(?:nopriv_)?|admin_post_(?:nopriv_)?)[a-z_]+['\"]\s*,\s*array\(\s*[^,]+,\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/",
			$src,
			$m
		);
		$hooked = array_flip( $m[1] );
		preg_match_all( "/'callback'\s*=>\s*array\(\s*[^,]+,\s*'([a-zA-Z_][a-zA-Z0-9_]*)'/", $src, $m2, PREG_OFFSET_CAPTURE );
		foreach ( $m2[1] as $hit ) {
			$hooked[ $hit[0] ] = true;
		}

		// Where the gate actually lives.
		//
		// The original script only looked for current_user_can() inside the
		// handler body. That is the rarer shape: in a REST controller the gate is
		// normally the route's permission_callback, several lines above the
		// handler and outside it. bulk_delete was caught only because it happens
		// to repeat its check in-body for defence in depth -- get_detail, same
		// controller and the same blanket-cap-plus-arbitrary-id shape, was
		// invisible because it trusts the route gate and checks nothing itself.
		// So pair each 'callback' with the 'permission_callback' that follows it
		// in the same register_rest_route() array.
		$route_blanket = array();
		foreach ( $m2[1] as $hit ) {
			$window = substr( $src, $hit[1], 600 );
			if ( preg_match( "/'permission_callback'\s*=>[^,]*current_user_can\(\s*'" . BLANKET . "'\s*\)/", $window ) ) {
				$route_blanket[ $hit[0] ] = true;
			}
		}

		// Entry points wired by a shape we cannot resolve -> manual review list.
		if ( preg_match( "/add_action\(\s*['\"](?:wp_ajax|admin_post)[a-z_]*['\"]\s*,\s*(function|fn|'|\\\$)/", $src ) ) {
			$unclassified[] = "{$path}  (closure / string / variable callable on an AJAX or admin_post hook)";
		}
		if ( preg_match( "/'(?:permission_)?callback'\s*=>\s*\\\$[a-zA-Z_]/", $src ) ) {
			$unclassified[] = "{$path}  (callback assigned through a variable -- trace it back by hand)";
		}
		if ( preg_match( '/(match|switch)\s*\(\s*\$(action|sub_action)\b/', $src ) ) {
			$unclassified[] = "{$path}  (router: the gate is on the dispatcher, not the sub-handlers)";
		}

		foreach ( split_functions( $src ) as [$name, $body] ) {
			if ( ! isset( $hooked[ $name ] ) ) {
				continue;
			}
			$gated_in_body  = (bool) preg_match( "/current_user_can\(\s*'" . BLANKET . "'\s*\)/", $body );
			$gated_at_route = isset( $route_blanket[ $name ] );
			if ( ! $gated_in_body && ! $gated_at_route ) {
				continue;
			}
			// An object-aware check anywhere in the body clears it.
			if ( preg_match( "/current_user_can\(\s*'[a-z_]+'\s*,/", $body ) ) {
				continue;
			}
			// Does it take an identifier from the request at all?
			//
			// The REST shapes below were added in the T8 round. bulk_delete reads
			// its targets with get_json_params() and then $body['ids'] -- a plural
			// array out of the JSON body, which none of the original superglobal
			// or ->int('id') patterns matched. A handler that acts on a LIST of
			// ids is the worse case, not the lesser one: the loop repeats whatever
			// the missing per-object check would have caught.
			$takes_id = "/\\\$_(POST|GET|REQUEST)\s*\[\s*'[a-z_]*(ids?|key|name|table|type)'"
				. "|->int\(\s*'[a-z_]*ids?'"
				. "|->text\(\s*'[a-z_]*(ids?|key|table)'"
				. "|get_json_params\(\s*\)"
				. "|get_param\(\s*'[a-z_]*(ids?|key)'\s*\)"
				. "|\\\$[a-z_]+\s*\[\s*'[a-z_]*ids?'\s*\]/";
			if ( ! preg_match( $takes_id, $body ) ) {
				continue;
			}
			$suspect[] = "{$path}::{$name}()";
		}
	}
}

echo "1. Handlers taking an id from the request behind a blanket capability\n";
echo str_repeat( '-', 72 ) . "\n";
foreach ( array_unique( $suspect ) as $s ) {
	echo "   {$s}\n";
}
if ( ! $suspect ) {
	echo "   (none)\n";
}

echo "\n2. Files this script cannot classify -- review these by hand\n";
echo str_repeat( '-', 72 ) . "\n";
foreach ( array_unique( $unclassified ) as $u ) {
	echo "   {$u}\n";
}
if ( ! $unclassified ) {
	echo "   (none)\n";
}

echo "\nSection 1 being empty does not mean the codebase is clean; it means the\n";
echo "shapes in section 2 still have to be read. See the header of this file.\n";

/**
 * @return array<int, array{0:string,1:string}>
 */
function split_functions( string $src ): array {
	$out = array();
	if ( ! preg_match_all( '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		return $out;
	}

	foreach ( $m[1] as $i => $hit ) {
		$name  = $hit[0];
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
		$out[] = array( $name, substr( $src, $start, $end - $start + 1 ) );
	}

	return $out;
}
