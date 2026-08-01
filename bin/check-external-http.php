<?php
/**
 * Third-party host gate.
 *
 * Lite must make no third-party HTTP calls. Two shipped call sites sent the
 * VISITOR'S IP ADDRESS to ip-api.com over plain HTTP -- no consent, no
 * disclosure, no encryption -- and a third made every front-end page load fetch
 * a webfont from fonts.googleapis.com, which leaks the visitor's IP to Google
 * just as effectively. All are removed; this gate keeps them from coming back.
 *
 * WHY A DENY-LIST (when the carve-out is otherwise a whitelist)
 * ------------------------------------------------------------
 * A whitelist of allowed hosts would have to enumerate every inert URL the
 * plugin merely PRINTS -- support links, doc links, schema.org namespaces,
 * licence URLs -- and would fail closed on each new one, so it would be edited
 * into uselessness. The risk here is narrow and nameable: a specific set of
 * third-party services this plugin must never talk to. So the deny-list names
 * them.
 *
 * The trade-off is honest and worth stating: this gate cannot catch a NEW
 * third-party host nobody has thought of. It is a regression fence, not a
 * discovery tool. Discovery is the sweep documented alongside it:
 *
 *   grep -rnE "wp_remote_(get|post)|curl_exec|file_get_contents\s*\(\s*['\"]https?://|fsockopen" src/ templates/ *.php
 *
 * Note that sweep is PHP-egress only, and would NOT have found the Google Fonts
 * leak, which was a wp_register_style() URL -- browser-side egress the plugin
 * causes just as deliberately. That is why this gate matches hosts anywhere in
 * the source, not just inside HTTP-call expressions.
 *
 * WHAT IS DELIBERATELY NOT DENIED
 * -------------------------------
 * WordPress.org and the site's own host are part of the expected WordPress
 * platform surface. Inert links that are only ever displayed to an admin
 * (wpalemi.com support pages, the maxhandmade.github.io docs site, the YouTube
 * channel) cause no request unless a human clicks them, and are not egress.
 *
 * Gravatar is NOT exempt (it used to be, on the theory that WordPress core
 * itself builds Gravatar URLs via get_avatar(), so it was "expected platform
 * surface"). Task B-G1b (WP.org T4 #3) removed every get_avatar()/
 * get_avatar_url()/gravatar.com call site from Lite in favour of local
 * initials avatars, specifically so the readme's "no third-party requests"
 * claim would be literally true. 'gravatar.com' is now in the deny-list
 * below as a regression fence: it must never come back, from core or
 * otherwise.
 *
 * Exit codes: 0 = clean, 1 = a denied host is referenced.
 *
 * @package MHM_Rentiva
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
// Overridable so a unit test can prove this gate goes red against a synthetic
// tree, mirroring bin/check-guarded-refs.php and bin/check-unguarded-pro-refs.php.
$root = getenv( 'MHM_GUARD_ROOT' ) ?: $root;

/**
 * Hosts Lite must never contact, and why each is banned.
 *
 * Keys are matched case-insensitively as substrings of the source, so a bare
 * host name catches http://, https://, protocol-relative and interpolated forms
 * alike.
 */
$denied_hosts = array(
	'ip-api.com'          => 'geolocation lookup -- sent the visitor IP to a third party over plain HTTP (removed in Task 8)',
	'ipapi.co'            => 'geolocation lookup -- same class of visitor-PII egress as ip-api.com',
	'ipinfo.io'           => 'geolocation lookup -- same class of visitor-PII egress as ip-api.com',
	'geobytes.com'        => 'geolocation lookup -- named in the original ip-api call site comment',
	'freegeoip.net'       => 'geolocation lookup -- same class of visitor-PII egress',
	'ip-api.io'           => 'geolocation lookup -- same class of visitor-PII egress',
	'fonts.googleapis.com' => 'CDN webfont -- leaks the visitor IP to Google on every page load; bundle the font locally instead',
	'fonts.gstatic.com'   => 'CDN webfont payload host -- same leak as fonts.googleapis.com',
	'ajax.googleapis.com' => 'script CDN -- WordPress.org requires bundled assets, not remote ones',
	'cdn.jsdelivr.net'    => 'asset CDN -- WordPress.org requires bundled assets, not remote ones',
	'cdnjs.cloudflare.com' => 'asset CDN -- WordPress.org requires bundled assets, not remote ones',
	'unpkg.com'           => 'asset CDN -- WordPress.org requires bundled assets, not remote ones',
	'google-analytics.com' => 'analytics -- undisclosed visitor tracking',
	'googletagmanager.com' => 'analytics -- undisclosed visitor tracking',
	'gravatar.com'        => 'avatar CDN -- get_avatar()/get_avatar_url() and hand-built gravatar.com URLs all leak the visitor/reviewer email hash to a third party; Task B-G1b replaced every avatar with a local initials placeholder (matches www.gravatar.com and secure.gravatar.com too, substring match)',
);

/**
 * Files allowed to name a denied host, with the reason.
 *
 * Kept deliberately tiny. A file-level exemption is a real hole -- genuine
 * egress could hide in an exempted file -- so it is only justified for a file
 * whose PURPOSE is to name hostile hosts.
 *
 * Repo-relative, forward slashes.
 */
$exempt_files = array();

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

$hits = array();
foreach ( $php_files as $path ) {
	$source = file_get_contents( $path );
	if ( false === $source ) {
		continue;
	}

	// Normalise BOTH sides to forward slashes before stripping the root: the
	// iterator yields mixed separators on Windows. Mirrors check-manifest.php.
	$relative = str_replace( '\\', '/', $path );
	$base     = str_replace( '\\', '/', $root ) . '/';
	if ( 0 === strpos( $relative, $base ) ) {
		$relative = substr( $relative, strlen( $base ) );
	}

	if ( isset( $exempt_files[ $relative ] ) ) {
		continue;
	}

	// Match CODE only, never comments. A comment naming a host issues no
	// request, and flagging prose would make the gate cry wolf -- including at
	// the very call sites where a comment explains why the host was removed.
	// Documentation must stay free to name what it is warning about.
	$code_by_line = array();
	foreach ( token_get_all( $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			continue; // Single-char tokens cannot contain a host.
		}
		if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
			continue;
		}
		// A multi-line token (e.g. a heredoc) is attributed to its opening
		// line, so a reported line number may point at the start of such a
		// construct rather than the exact URL. Precise enough to act on.
		$line                  = $token[2];
		$code_by_line[ $line ] = ( $code_by_line[ $line ] ?? '' ) . $token[1];
	}

	foreach ( $code_by_line as $line => $text ) {
		foreach ( $denied_hosts as $host => $reason ) {
			if ( false !== stripos( $text, $host ) ) {
				$hits[] = sprintf( '%s:%d  %s -- %s', $relative, $line, $host, $reason );
			}
		}
	}
}

if ( array() !== $hits ) {
	sort( $hits );
	echo "Third-party host referenced in Lite (Lite must make no third-party HTTP calls):\n\n";
	echo implode( "\n", $hits ) . "\n\n";
	echo count( $hits ) . " found.\n";
	echo "Remove the call, or serve the resource from the plugin itself. Do not substitute another third-party service.\n";
	exit( 1 );
}

echo "[OK] No third-party host referenced in Lite.\n";
exit( 0 );
