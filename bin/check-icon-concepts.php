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
 * 🔴 WHAT A CLEAN RUN DOES NOT MEAN. The scanner matches per FILE: a file that
 * mentions an anchor has all of its `icon` values read, including ones that
 * belong to the other two vocabularies. Those land in the `unknown` bucket,
 * which does not fail — so a clean exit means "no call site wrote a suffix
 * that has a concept", not "no raw suffix exists". The package's own docblock
 * lists the rest of its blind spots (a value in a variable, one built with
 * sprintf, a dynamic JSX prop).
 *
 * Exit codes: 0 = converged, 1 = a call site wrote a suffix with a concept,
 * 2 = the run measured nothing (an empty gate is a broken gate).
 *
 * @package Mhm_Rentiva
 */

declare(strict_types=1);

$root    = dirname( __DIR__ );
$package = $root . '/vendor/mhm/ui-core/src/Kit';

foreach ( array( $package . '/Icons.php', $package . '/IconConceptScanner.php' ) as $file ) {
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "MEASURE-FAILED: the package's scanner is not installed: {$file}\n" );
		exit( 2 );
	}
	require_once $file;
}

require_once $root . '/src/Admin/Core/IconConcepts.php';

\MHMUiCore\Kit\Icons::register( \MHMRentiva\Admin\Core\IconConcepts::MAP );

// The product wrappers every kit call site goes through, plus the JSX
// component name. `mhmuicore_stats_grid_html` is deliberately NOT here: it is
// reached only from inside those wrappers, and anchoring on it finds nothing.
$anchors = array( 'stats_grid_html', 'ProKit::grid', 'StatsGrid' );

$paths = array( $root . '/src', $root . '/src-react' );

$scanner = new \MHMUiCore\Kit\IconConceptScanner( $anchors );
$result  = $scanner->scan( $paths );

if ( array() !== ( $result['failed'] ?? array() ) ) {
	foreach ( $result['failed'] as $failure ) {
		fwrite( STDERR, 'MEASURE-FAILED: ' . ( is_array( $failure ) ? implode( ' ', $failure ) : (string) $failure ) . "\n" );
	}
	exit( 2 );
}

$measured = $result['concepts'] + count( $result['raw'] ) + count( $result['unknown'] );

if ( 0 === $measured ) {
	fwrite( STDERR, "MEASURE-FAILED: no anchored file carried a readable icon value; the anchors or the paths are wrong.\n" );
	exit( 2 );
}

foreach ( $result['unknown'] as $hit ) {
	printf( "unknown  %s:%d  '%s'%s", $hit['file'], $hit['line'], $hit['value'], PHP_EOL );
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

printf(
	'icon-concepts: %d concept call site(s), %d raw, %d unknown%s',
	$result['concepts'],
	count( $result['raw'] ),
	count( $result['unknown'] ),
	PHP_EOL
);

exit( array() === $result['raw'] ? 0 : 1 );
