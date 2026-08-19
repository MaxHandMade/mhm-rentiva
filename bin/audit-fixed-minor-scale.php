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
 *   - .jsx: there are 34 tracked files under src-react/, 14 of them money
 *     surfaces (PaymentsSummary.jsx, PendingPayments.jsx, RevenueChart.jsx,
 *     StatsCards.jsx, ...). Hand-checked clean as of this writing (grepped all
 *     34 for the shape) -- but the probe itself never looks at them, so that
 *     clean bill is a human's, not this tool's.
 *   - anything not naming money the way the word list below expects: the list
 *     is an ALLOWLIST, not a hint. A line is only considered if it contains
 *     kurus|minor|amount|amt|price|total|refund|paid|deposit|revenue. $cents,
 *     $balance, $fee, $commission, $sum are invisible to it -- this is the
 *     probe's single biggest limitation.
 *   - money spelled "rate": the ratio denylist below suppresses that word to
 *     keep daily_rate/exchange_rate style ratios out of "percentage" false
 *     positives, but in this car-rental codebase "rate" is money as often as
 *     ratio. No collateral found today; the suppression is still a blind spot.
 *   - untracked files: the starting set is `git ls-files`, so a new file with
 *     a conversion that has not been `git add`-ed yet is invisible until it
 *     is.
 * The spec's own inventory started at src/ and therefore missed the member in
 * assets/js. That is the failure mode this header exists to prevent.
 *
 * Comment lines are skipped before the shape is even tested: a comment is not
 * a conversion, and commented-out code does not execute. Tasks 2-4 left
 * explanatory comments naming "*100" at the sites they fixed (to say what the
 * fix replaced), and without this rule the probe would report the sweep's own
 * account of itself as an unswept member.
 *
 * CANNOT MEASURE is a third outcome, distinct from "measured, found nothing".
 * The previous version built `git ls-files -- '*.php' '*.js'` as a shell
 * string and handed it to exec(). On Windows exec() runs through cmd.exe,
 * which does not strip single quotes, so git received the literal `'*.php'`
 * token, matched nothing, and still exited 0 -- the only guard on the next
 * line tested the exit code, not the file count, so an empty starting set
 * printed the blind-spot list and a clean bill. proc_open() with an ARRAY
 * argv (the pattern bin/check-shape-zero.php and
 * bin/check-plugin-check-parity.php already use) bypasses the shell on both
 * POSIX and Windows, so no platform's quoting rules apply; an empty starting
 * set is then checked directly and refused rather than reported as success.
 *
 * Usage: php bin/audit-fixed-minor-scale.php [--json] [--self-test]
 *
 * @package MHM_Rentiva
 */

declare( strict_types=1 );

$as_json   = in_array( '--json', $argv, true );
$self_test = in_array( '--self-test', $argv, true );
$root      = dirname( __DIR__ );

const AFMS_CANNOT_MEASURE = 2;

$started = "git ls-files -- '*.php' '*.js', minus vendor/, node_modules/, build/, tests/ and bin/ (excludes the Pro plugin, which is a separate repository)";

$blind_spots = array(
	'the Pro plugin (separate repository)',
	'conversions whose multiplier is a variable, not the literal 100',
	'.pot / .po catalogues',
	'a fixed *100 split across more than one line',
	'.jsx (34 tracked files under src-react/, 14 of them money surfaces; hand-checked clean, not probe-checked)',
	'the money word list is an allowlist, not a hint -- $cents, $balance, $fee, $commission, $sum are invisible to it',
	'the ratio denylist suppresses "rate", which is money as often as ratio in this codebase (daily_rate, exchange_rate)',
	'untracked files -- the starting set is git ls-files, so an unstaged file is invisible',
);

/**
 * Run a command without going through a shell.
 *
 * proc_open() with an ARRAY command line bypasses the shell on both POSIX and
 * Windows, so nothing here depends on quoting rules -- see the file docblock
 * for what went wrong when this ran through exec() and a shell string.
 *
 * @param string[] $argv
 * @return array{0:int,1:string,2:string} [exit code, stdout, stderr]
 */
function afms_run( array $argv, string $cwd ): array {
	$spec = array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) );
	$proc = @proc_open( $argv, $spec, $pipes, $cwd );
	if ( ! is_resource( $proc ) ) {
		return array( -1, '', 'proc_open failed for: ' . implode( ' ', $argv ) );
	}
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return array( proc_close( $proc ), $stdout, $stderr );
}

/**
 * CANNOT MEASURE must be impossible to mistake for a pass. It always writes
 * to STDERR and exits 2, in both --json and text mode -- it does not attempt
 * a JSON envelope for a number it never measured.
 */
function afms_cannot_measure( string $why, string $how = '' ): never {
	fwrite( STDERR, "CANNOT MEASURE: $why\n" );
	if ( '' !== $how ) {
		fwrite( STDERR, "  $how\n" );
	}
	fwrite( STDERR, "  This is NOT a clean bill. Exit code " . AFMS_CANNOT_MEASURE . " means the probe did not measure the tree.\n" );
	exit( AFMS_CANNOT_MEASURE );
}

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
	list( $rc, $stdout, $stderr ) = afms_run( array( 'git', 'ls-files', '--', '*.php', '*.js' ), $root );
	if ( 0 !== $rc ) {
		afms_cannot_measure(
			'git ls-files exited ' . $rc,
			'' !== trim( $stderr ) ? trim( $stderr ) : 'no stderr output'
		);
	}

	$tracked = array_values( array_filter( preg_split( '/\R/', trim( $stdout ) ), static fn( string $l ): bool => '' !== $l ) );

	if ( array() === $tracked ) {
		afms_cannot_measure(
			'git ls-files returned zero tracked *.php/*.js files',
			"This is almost certainly a broken starting set (wrong cwd, or a platform stripping the pathspec quoting) rather than a repository that truly has none -- refusing to report a number I did not measure."
		);
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

// An empty starting set must never report success, even if it slipped past
// the git-level checks above (e.g. every candidate got filtered out, or the
// tree really was scanned from the wrong root). "Scanned 0 files. No
// findings." reads as a clean bill; it is actually a probe that measured
// nothing.
//
// This refusal used to be gated on `! $self_test`, which made --self-test the
// one path it did not cover: a missing or renamed fixture left $scanned at 0
// and the guard never ran, so --self-test printed "Scanned 0 files ... No
// fixed-100 money conversions found" and exited 0 -- a confident, well
// formatted, wrong clean bill on the exact contract this tool exists to
// enforce elsewhere. There is no legitimate case where a present, readable
// self-test fixture scans to zero files, so the refusal below is now
// unconditional.
if ( 0 === $scanned ) {
	afms_cannot_measure(
		$self_test ? 'the self-test fixture was not found or not readable' : '0 files were scanned',
		$self_test
			? 'Expected: ' . $root . '/bin/fixtures/fixed-minor-scale-fixture.txt. Refusing to report a clean bill over an empty set.'
			: 'git ls-files returned tracked paths, but none of them were readable *.php/*.js files outside vendor/, node_modules/, build/, tests/, bin/. Refusing to report a clean bill over an empty set.'
	);
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
