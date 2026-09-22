<?php
/**
 * Icon-concept convergence gate: a kit call site must name a CONCEPT, not the
 * Dashicon suffix that concept resolves to.
 *
 * WHY THIS EXISTS
 *
 * ui-core 0.14 gave the kit's `icon` prop a vocabulary: `revenue` resolves to
 * `money-alt`, `time` to `calendar-alt`. Writing the suffix still works — the
 * package passes an unknown value through unchanged — so nothing fails when a
 * call site drifts back to the raw form. What is lost is silent: two cards
 * meaning the same thing can end up drawn with two different glyphs, which is
 * exactly what this tree looked like before the convergence (a "Total
 * Customers" card drawn with `groups` on one screen and `admin-users` on
 * another).
 *
 * WHY THE ENGINE IS THE PACKAGE'S AND THE VOCABULARY IS OURS
 *
 * The scanner ships inside vendor/mhm/ui-core precisely so a consumer's CI can
 * require it; bin/ does not ship, so it cannot come from there. But the
 * package only knows its own twelve seed concepts. A run that does not first
 * register Rentiva's concepts reports every one of them as an unknown suffix
 * and advises registering what is already registered — measured, which is why
 * IconConcepts::MAP is read here rather than restated.
 *
 * WHY THE ANCHORS ARE PRODUCT NAMES
 *
 * The scanner finds call sites by anchor, not by the word `icon`, because this
 * tree writes `'icon' =>` in three unrelated vocabularies: kit cards, the
 * product's inline-SVG feature icons, and a dashicons-prefixed button helper.
 * The package's own function names find zero call sites here — every one goes
 * through a product wrapper — so the anchors are those wrappers.
 *
 * 🔴 WHY THE `unknown` BUCKET FAILS UNLESS IT IS NAMED BELOW. The scanner
 * matches per FILE: a file that mentions an anchor has all of its `icon`
 * values read, including ones that belong to the other two vocabularies.
 * Those land in `unknown`. So does a misspelled concept: `'revenu'` is
 * neither a concept nor a suffix with one, and it reaches the browser as
 * `dashicons-revenu`, which draws nothing -- under every kit version, with no
 * error. The first version of this gate let that bucket pass, so a typo
 * produced exactly the failure the vocabulary exists to prevent while the
 * gate said "converged" (an independent audit found it, 2026-09-22). Every
 * value that is legitimately not a concept is therefore named in
 * $accepted_raw with its reason and how many call sites write it; anything
 * else in `unknown` fails, and so does an entry written more or fewer times
 * than recorded, so the list cannot rot into a blanket pass. The package's own docblock lists the scanner's remaining
 * blind spots (a value in a variable, one built with sprintf, a dynamic JSX
 * prop).
 *
 * Exit codes: 0 = converged, 1 = a call site wrote a suffix with a concept,
 * a value that is neither a concept nor accepted, or an accepted value
 * written a different number of times than recorded; 2 = the run measured nothing (an empty gate is a broken
 * gate).
 *
 * @package Mhm_Rentiva
 */

declare(strict_types=1);

$root    = dirname( __DIR__ );
$package = $root . '/vendor/mhm/ui-core/src/Kit';

// 🔴 A RUN THAT NEVER REACHES THE VERDICT IS NOT A PASS. An `exit` anywhere
// below -- a direct-access guard in a required file was the measured case --
// ends the process with status 0 and no output, which CI reads as "converged".
// Shutdown functions still run after such an exit, and an exit() inside one
// replaces the status, so the run is failed here unless the verdict line set
// the flag.
$verdict_reached = false;
register_shutdown_function(
	static function () use ( &$verdict_reached ): void {
		if ( ! $verdict_reached ) {
			fwrite( STDERR, "MEASURE-FAILED: the gate stopped before its verdict; nothing was measured.\n" );
			exit( 2 );
		}
	}
);

// Every deliberate "measured nothing" exit goes through here, so it reports
// once and the shutdown check above does not report it a second time.
$measure_failed = static function ( string $why ) use ( &$verdict_reached ): void {
	$verdict_reached = true;
	fwrite( STDERR, 'MEASURE-FAILED: ' . $why . "\n" );
	exit( 2 );
};

foreach ( array( $package . '/Icons.php', $package . '/IconConceptScanner.php' ) as $file ) {
	if ( ! is_file( $file ) ) {
		$measure_failed( "the package's scanner is not installed: {$file}" );
	}
	require_once $file;
}

// The vocabulary file carries the direct-access guard every shipped class file
// carries. Without this define, requiring it outside WordPress would `exit`
// inside the require -- which the shutdown check above turns into a failure
// rather than a silent pass, but the gate would still measure nothing.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

require_once $root . '/src/Admin/Core/IconConcepts.php';

// Values an anchored file writes as `icon` that are deliberately NOT concepts:
// value => array( how many call sites write it, why ). A value without a
// reason is a typo until proven otherwise, and a count that moves is a new
// use nobody decided on.
$accepted_raw = array(
	// BookingColumns: the "Completed" card. The seed's `active` draws yes-alt
	// (the circled tick); this card has always drawn the plain tick.
	'yes'        => array( 1, 'plain tick on the completed-bookings card; no concept draws it' ),
	// shortcode-pages/StatsBar.jsx: page-status counters with no product noun.
	'admin-page' => array( 1, 'shortcode pages: Total' ),
	'warning'    => array( 1, 'shortcode pages: Missing' ),
);

// 🔴 WHAT THE COUNT DOES NOT SEE: a relocation. Converting the `yes` card and
// giving `yes` to a new card in the same change keeps the count at 1 and
// passes. Closing that means counting per file; it was left as a recorded
// debt (third independent audit, 2026-09-22) because it needs two opposite
// edits landing together, and a reviewer sees both in one diff.

// The list's own shape is an input: an entry left as `value => 'reason'` (the
// first version's shape, a plausible merge artefact) would be compared against
// the reason's first character and blame the call site instead of the list.
foreach ( $accepted_raw as $value => $entry ) {
	if ( ! is_array( $entry ) || ! is_int( $entry[0] ?? null ) || ! is_string( $entry[1] ?? null ) ) {
		$measure_failed( "malformed \$accepted_raw entry '{$value}': expected array( count, reason )" );
	}
}

\MHMUiCore\Kit\Icons::register( \MHMRentiva\Admin\Core\IconConcepts::MAP );

// The product wrappers every kit call site goes through, plus the JSX
// component name. `mhmuicore_stats_grid_html` is deliberately NOT here: it is
// reached only from inside those wrappers, and anchoring on it finds nothing.
$anchors = array( 'stats_grid_html', 'ProKit::grid', 'StatsGrid' );

// 🔴 templates/ IS IN THIS LIST BECAUSE LEAVING IT OUT WAS THIS TREE'S
// RECURRING BLIND SPOT -- and this gate had it. templates/account/dashboard.php
// hands cards to the kit and the first version of this file could not see it.
// It passes no icons today, so nothing was being missed yet; the gate said
// "converged" about a directory it had never opened. During the 0.14 upgrade
// the count of kit call sites was written wrong four times running, and every
// wrong count came from a scan that read src/ and src-react/ and stopped.
$paths = array_values( array_filter( array( $root . '/src', $root . '/src-react', $root . '/templates' ), 'is_dir' ) );

$scanner = new \MHMUiCore\Kit\IconConceptScanner( $anchors );
$result  = $scanner->scan( $paths );

if ( array() !== ( $result['failed'] ?? array() ) ) {
	$reasons = array();
	foreach ( $result['failed'] as $failure ) {
		$reasons[] = is_array( $failure ) ? implode( ' ', $failure ) : (string) $failure;
	}
	$measure_failed( implode( "\nMEASURE-FAILED: ", $reasons ) );
}

$measured = $result['concepts'] + count( $result['raw'] ) + count( $result['unknown'] );

if ( 0 === $measured ) {
	$measure_failed( 'no anchored file carried a readable icon value; the anchors or the paths are wrong.' );
}

$unaccepted = array();
$seen       = array();

foreach ( $result['unknown'] as $hit ) {
	if ( isset( $accepted_raw[ $hit['value'] ] ) ) {
		$seen[ $hit['value'] ] = ( $seen[ $hit['value'] ] ?? 0 ) + 1;
		printf( "accepted %s:%d  '%s'%s", $hit['file'], $hit['line'], $hit['value'], PHP_EOL );
		continue;
	}
	$unaccepted[] = $hit;
	printf( "UNKNOWN  %s:%d  '%s' -- not a concept and not accepted; a typo draws nothing%s", $hit['file'], $hit['line'], $hit['value'], PHP_EOL );
}

// An accepted value is accepted a known number of times, not anywhere. A
// value-only key let a third card quietly adopt `groups`, or a half-converted
// pair pass, and still read as accepted -- the second independent audit's
// finding. Counting keeps line numbers out of this file and fails both.
$miscounted = array();

foreach ( $accepted_raw as $value => $entry ) {
	$found = $seen[ $value ] ?? 0;
	if ( $found === $entry[0] ) {
		continue;
	}
	$miscounted[] = $value;
	if ( 0 === $found ) {
		printf( "STALE    '%s' is accepted but no call site writes it -- remove it from \$accepted_raw%s", $value, PHP_EOL );
	} else {
		printf( "COUNT    '%s' is accepted %d time(s) but written %d -- a new use needs a concept or a reason (%s)%s", $value, $entry[0], $found, $entry[1], PHP_EOL );
	}
}

foreach ( $result['raw'] as $hit ) {
	printf(
		"RAW      %s:%d  '%s' -- write '%s'%s",
		$hit['file'],
		$hit['line'],
		$hit['value'],
		implode( "' or '", $hit['concepts'] ),
		PHP_EOL
	);
}

// The file count is printed because it is the number this tree kept getting
// wrong (see the templates/ note above): a verdict without it cannot be
// checked against the directories it claims to have read.
printf(
	'icon-concepts: %d file(s), %d concept call site(s), %d raw, %d accepted, %d unknown, %d miscounted%s',
	$result['files'],
	$result['concepts'],
	count( $result['raw'] ),
	count( $result['unknown'] ) - count( $unaccepted ),
	count( $unaccepted ),
	count( $miscounted ),
	PHP_EOL
);

$verdict_reached = true;

exit( array() === $result['raw'] && array() === $unaccepted && array() === $miscounted ? 0 : 1 );
