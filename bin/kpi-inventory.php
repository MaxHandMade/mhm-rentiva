<?php
/**
 * Counts the legacy KPI class names still present in the shipped tree.
 *
 * A report, not a gate: a class name can be assembled at runtime, and this tool
 * cannot see that. It answers "what is left", and the browser answers "what renders".
 */

declare( strict_types = 1 );

$root  = dirname( __DIR__ );
$needles = array( 'mhm-stat-card', 'mhm-stats-grid', 'mhm-kpi-box', 'mhm-kpi-row', 'rv-cust-kpi', 'rv-scp-kpi', 'stat-card', 'stats-grid', 'mhm-rentiva-dashboard__kpi' );
$skip    = array( '/vendor/', '/node_modules/', '/build/', '/.git/' );

$totals = array_fill_keys( $needles, 0 );
$files  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );

foreach ( $files as $file ) {
	$path = str_replace( '\\', '/', (string) $file );
	if ( ! is_file( $path ) || ! preg_match( '/\.(php|jsx?|css)$/', $path ) ) {
		continue;
	}
	foreach ( $skip as $fragment ) {
		if ( str_contains( $path, $fragment ) ) {
			continue 2;
		}
	}
	$body = (string) file_get_contents( $path );
	foreach ( $needles as $needle ) {
		$totals[ $needle ] += substr_count( $body, $needle );
	}
}

foreach ( $totals as $needle => $count ) {
	printf( "%-32s %d\n", $needle, $count );
}
