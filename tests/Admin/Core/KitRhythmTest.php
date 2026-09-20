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
}
