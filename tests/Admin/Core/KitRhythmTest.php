<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * Rentiva owns the KPI strip's rhythm, not the package.
 *
 * ui-core 0.14.0 deleted `.mhmui-stats-grid { margin-top: 16px }` and replaced
 * it with a page-shell rule that 13 of Rentiva's 15 affected strips cannot
 * match (they sit on core's edit.php, in a dashboard postbox, or one level too
 * deep). The rule below targets the SAME element the package used to target,
 * one specificity step higher, so the measured spacing is identical before and
 * after the upgrade.
 */
final class KitRhythmTest extends WP_UnitTestCase {

	public function test_enqueue_kit_attaches_the_owner_rule(): void {
		$handle = AssetManager::enqueue_kit( 'admin' );
		$this->assertNotSame( '', $handle, 'The kit stylesheet must enqueue in the test environment.' );

		$inline = wp_styles()->get_data( $handle, 'after' );
		$this->assertIsArray( $inline );
		$this->assertStringContainsString(
			'.mhm-kpi-strip > .mhmui-stats-grid',
			implode( '', $inline ),
			'The owner rule must ride on the kit handle.'
		);
	}

	public function test_owner_rule_targets_the_grid_not_the_wrapper(): void {
		// Targeting the wrapper would double the spacing while ui-core 0.13.1 is
		// still installed, because the package's own grid margin is still live.
		$this->assertStringContainsString( '> .mhmui-stats-grid', AssetManager::STRIP_RHYTHM_CSS );
		$this->assertStringStartsWith( '.mhm-kpi-strip > .mhmui-stats-grid{', AssetManager::STRIP_RHYTHM_CSS );
	}

	/**
	 * @dataProvider php_strip_producers
	 */
	public function test_every_php_strip_wrapper_carries_the_product_class( string $file ): void {
		$source = file_get_contents( MHMRENTIVA_PLUGIN_PATH . $file );
		$this->assertIsString( $source );
		$this->assertMatchesRegularExpression(
			'/class="[^"]*\bmhm-kpi-strip\b[^"]*"/',
			$source,
			$file . ' renders a KPI strip, so its wrapper must carry mhm-kpi-strip.'
		);
	}

	/** @return array<string, array{0: string}> */
	public static function php_strip_producers(): array {
		// Measured 2026-09-21. This only catches a LISTED producer losing the
		// class from its source; it cannot catch a producer added with no row
		// here (silent, since the suite stays green), and the regex is a
		// presence check, not a structure check, so it cannot catch the class
		// landing on the wrong element. The direct-parent invariant is
		// enforced for real by the browser matrix (Task 6), which reads the
		// computed margin on every screen.
		return array(
			'addon list table' => array( 'src/Admin/Addons/AddonListTable.php' ),
			'addon screen'     => array( 'src/Admin/Addons/AddonScreen.php' ),
			'booking columns'  => array( 'src/Admin/Booking/ListTable/BookingColumns.php' ),
			'vehicle columns'  => array( 'src/Admin/Vehicle/ListTable/VehicleColumns.php' ),
		);
	}
}
