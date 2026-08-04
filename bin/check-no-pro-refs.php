<?php
/**
 * WP.org compliance oracle: Lite must ship zero edition/license/gating tokens.
 *
 * WHY THIS EXISTS
 * ----------------
 * The T4 seam-inversion work (A0-A13) deleted `Mode.php` and inverted every
 * seam so Lite no longer contains any "is this Pro?" branching, license
 * checks, or gating vocabulary. This script is the machine-checkable proof
 * of that claim -- a simple, greedy, line-based grep for the *vocabulary* of
 * edition/license gating (`isPro`, `Mode::`, `LicenseManager`, ...), not a
 * structural analyser like check-guarded-refs.php or
 * check-unguarded-pro-refs.php (which reason about specific allowlisted Pro
 * *classes*). This one asks a blunter, final question: does Lite's source
 * contain the WORDS that spell "this is a crippled/licensed edition" at all?
 *
 * Scans src/, templates/, src-react/, build/, assets/ plus the plugin
 * bootstrap and uninstall.php -- i.e. everything that ships in the WP.org
 * ZIP.
 *
 * @package MHMRentiva
 */

declare( strict_types=1 );

$base  = dirname( __DIR__ );
$roots = array( $base . '/src', $base . '/templates', $base . '/src-react', $base . '/build', $base . '/assets' );
$files = array( $base . '/mhm-rentiva.php', $base . '/uninstall.php' );

$pattern = '/isPro|is_pro|allowsSeam|pro_seam|pro_feature|pro_widget|Mode::|Licensing\\\\Mode|canUse[A-Z]|LicenseManager|LicenseAdmin|VerifyEndpoint|\bLicensing\b|MHMRentiva\\\\Pro/';

// Domain false positives that are NOT edition/license references.
// - license_plate / driver's licen(se|ce): vehicle-rental domain, not edition gating.
// - isPrototypeOf: native JS Object method (bundled Chart.js in build/); the
//   "isPro" pattern matches inside it as a plain substring, not the token "isPro".
$whitelist = '/license_plate|_mhmrentiva_license_plate|driver.?s licen|isPrototypeOf/i';

$exts = array(
	'php' => 1,
	'js'  => 1,
	'jsx' => 1,
);

$hits = array();

$scan = static function ( string $path ) use ( &$hits, $pattern, $whitelist ): void {
	$lines = file( $path );
	if ( false === $lines ) {
		return;
	}
	foreach ( $lines as $n => $line ) {
		if ( preg_match( $pattern, $line ) && ! preg_match( $whitelist, $line ) ) {
			$hits[] = $path . ':' . ( $n + 1 ) . '  ' . trim( $line );
		}
	}
};

foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( isset( $exts[ $f->getExtension() ] ) ) {
			$scan( $f->getPathname() );
		}
	}
}

foreach ( $files as $f ) {
	if ( is_file( $f ) ) {
		$scan( $f );
	}
}

if ( $hits ) {
	fwrite( STDERR, "check-no-pro-refs FAILED -- Lite contains edition/license tokens:\n" . implode( "\n", $hits ) . "\n" );
	exit( 1 );
}

echo "check-no-pro-refs: clean\n";
