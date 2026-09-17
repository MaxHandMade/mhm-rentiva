<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonScreen;
use WP_UnitTestCase;

/**
 * Task 7 of the KPI kit migration: the Add-ons management screen's stats
 * band moves onto the ui-core kit and keeps the `data-stat` hook the live
 * counter script (addons-screen.js) relies on after every toggle/price edit.
 */
final class AddonScreenStripTest extends WP_UnitTestCase {

	public function test_every_card_keeps_its_data_stat_hook_on_kit_markup(): void {
		ob_start();
		AddonScreen::render_stats_band();
		$html = (string) ob_get_clean();

		foreach ( array( 'total_addons', 'active_addons', 'avg_price', 'total_value' ) as $key ) {
			$this->assertStringContainsString( 'data-stat="' . $key . '"', $html );
		}
		$this->assertStringContainsString( 'mhmui-stat-card', $html );
		$this->assertStringNotContainsString( 'class="mhm-stat-card', $html );
	}

	public function test_the_live_counter_script_targets_the_classes_the_band_now_prints(): void {
		$js = (string) file_get_contents( MHMRENTIVA_PLUGIN_PATH . 'assets/js/admin/addons-screen.js' );

		$this->assertStringContainsString( '.mhmui-stat-card__value', $js );
		$this->assertStringNotContainsString( '.mhm-stat-card__value', $js );
	}
}
