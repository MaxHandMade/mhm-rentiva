<?php
/**
 * Reports fixed-100 minor-unit conversions that the M-02 sweep did not reach.
 *
 * REPORTS. It is deliberately not a CI gate: this is an absence-of-defect
 * tool, and a false positive that fails a build teaches people to silence the
 * tool rather than read it.
 *
 * WHERE IT STARTS -- read this before believing a "(none)":
 *   git ls-files -- '*.php' '*.js', minus vendor/, node_modules/, build/,
 *   tests/ and bin/.
 * WHAT IT CANNOT SEE:
 *   - the Pro plugin (separate repository)
 *   - any conversion whose multiplier is a variable rather than the literal 100
 *   - .pot / .po catalogues
 *   - a fixed *100 hidden inside a multi-line expression (the probe reads one
 *     line at a time; it cannot see a literal that lands on the line after the
 *     money-naming identifier)
 * The spec's own inventory started at src/ and therefore missed the member in
 * assets/js. That is the failure mode this header exists to prevent.
 *
 * Comment lines are skipped before the shape is even tested: a comment is not
 * a conversion, and commented-out code does not execute. Tasks 2-4 left
 * explanatory comments naming "*100" at the sites they fixed (to say what the
 * fix replaced), and without this rule the probe would report the sweep's own
 * account of itself as an unswept member.
 *
 * Usage: php bin/audit-fixed-minor-scale.php [--json] [--self-test]
 *
 * @package MHM_Rentiva
 */

declare( strict_types=1 );

$as_json   = in_array( '--json', $argv, true );
$self_test = in_array( '--self-test', $argv, true );
$root      = dirname( __DIR__ );

$started = "git ls-files -- '*.php' '*.js', minus vendor/, node_modules/, build/, tests/ and bin/ (excludes the Pro plugin, which is a separate repository)";

$blind_spots = array(
	'the Pro plugin (separate repository)',
	'conversions whose multiplier is a variable, not the literal 100',
	'.pot / .po catalogues',
	'a fixed *100 split across more than one line',
);

// The shape: a literal 100 (or 100.0) multiplying or dividing something, on a
// line whose identifiers name money.
//
// The word lists below use a hand-rolled boundary -- (?<![A-Za-z0-9]) /
// (?![A-Za-z0-9]) -- instead of \b. \b treats underscore as a word character,
// so \brefund\b never matches inside $refund_amount_kurus, and snake_case is
// the naming convention this whole codebase uses. A boundary that cannot see
// through the plugin's own naming style is not blunt, it is blind; measured
// against the fixture's own planted member before trusting it.
$shape = '/(?:\*|\/)\s*100(?:\.0+)?\b/';
$money = '/(?<![A-Za-z0-9])(?:kurus|minor|amount|amt|price|total|refund|paid|deposit|revenue)(?![A-Za-z0-9])/i';
// Ratios share the literal and none of the defect.
$ratio = '/(?<![A-Za-z0-9])(?:percent|percentage|pct|rate|trend|occupancy|progress|discount|hits|cache)(?![A-Za-z0-9])/i';
// A comment is not a conversion; commented-out code does not execute.
$comment = '#^\s*(?://|/\*|\*|\#)#';

if ( $self_test ) {
	$files = array( $root . '/bin/fixtures/fixed-minor-scale-fixture.txt' );
} else {
	exec( 'git -C ' . escapeshellarg( $root ) . " ls-files -- '*.php' '*.js'", $tracked, $code );
	if ( 0 !== $code ) {
		fwrite( STDERR, "git ls-files failed; refusing to report a number I did not measure.\n" );
		exit( 2 );
	}
	$files = array();
	foreach ( $tracked as $rel ) {
		if ( preg_match( '#^(vendor|node_modules|build|tests|bin)/#', $rel ) ) {
			continue;
		}
		$files[] = $root . '/' . $rel;
	}
}

$findings = array();
$scanned  = 0;

foreach ( $files as $path ) {
	if ( ! is_readable( $path ) ) {
		continue;
	}
	++$scanned;

	foreach ( file( $path ) as $i => $line ) {
		if ( preg_match( $comment, $line ) ) {
			continue;
		}
		if ( ! preg_match( $shape, $line ) ) {
			continue;
		}
		if ( preg_match( $ratio, $line ) || ! preg_match( $money, $line ) ) {
			continue;
		}
		$findings[] = array(
			'file' => ltrim( str_replace( $root, '', $path ), '/\\' ),
			'line' => $i + 1,
			'code' => trim( $line ),
		);
	}
}

if ( $as_json ) {
	echo (string) json_encode(
		array(
			'scanned'     => $scanned,
			'findings'    => $findings,
			'started'     => $started,
			'blind_spots' => $blind_spots,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	), "\n";
	exit( 0 );
}

printf( "Started from: %s\n", $started );
printf( "Scanned %d files.\n", $scanned );
printf( "Blind spots: %s\n", implode( '; ', $blind_spots ) );
if ( empty( $findings ) ) {
	echo "No fixed-100 money conversions found in the scanned set.\n";
	exit( 0 );
}
printf( "%d unswept member(s):\n", count( $findings ) );
foreach ( $findings as $f ) {
	printf( "  %s:%d  %s\n", $f['file'], $f['line'], $f['code'] );
}
exit( 0 );
