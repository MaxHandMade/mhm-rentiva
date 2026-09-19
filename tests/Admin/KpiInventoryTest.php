<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin;

use WP_UnitTestCase;

final class KpiInventoryTest extends WP_UnitTestCase {

	/**
	 * The two shared stylesheets Pro reads by URL keep their legacy blocks, so the
	 * inventory is not expected to reach zero. What must be zero is Lite's own
	 * rendering: no template, component or screen may still print a legacy card.
	 */
	public function test_no_lite_surface_prints_a_legacy_kpi_card(): void {
		$roots = array( 'src', 'src-react/admin', 'templates', 'assets/js' );

		foreach ( $roots as $relative ) {
			$hits = array();
			$dir  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( MHMRENTIVA_PLUGIN_PATH . $relative ) );
			foreach ( $dir as $file ) {
				$path = str_replace( '\\', '/', (string) $file );
				if ( ! is_file( $path ) || ! preg_match( '/\.(php|jsx?)$/', $path ) ) {
					continue;
				}
				$body = (string) file_get_contents( $path );
				if ( preg_match( '/class="[^"]*\b(mhm-stat-card|rv-cust-kpi|rv-scp-kpi|mhm-kpi-box)\b|className="[^"]*\b(mhm-stat-card|rv-cust-kpi|rv-scp-kpi|mhm-kpi-box)\b/', $body ) ) {
					$hits[] = $path;
				}
			}
			$this->assertSame( array(), $hits, $relative . ' still renders a legacy KPI card' );
		}
	}
}
