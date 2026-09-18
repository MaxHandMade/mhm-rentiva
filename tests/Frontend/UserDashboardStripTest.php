<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Core\Dashboard\CustomerDashboard;
use WP_UnitTestCase;

/**
 * The [rentiva_user_dashboard] KPI strip renders through the ui-core kit
 * (KPI kit migration, Task 9): kit cards, no icons on the front end (K4), no
 * count-up animation, and the root is the kit's front page shell.
 */
final class UserDashboardStripTest extends WP_UnitTestCase {

	/** @return array<string, mixed> */
	private function payload(): array {
		return array(
			'context'    => 'customer',
			'active_tab' => 'overview',
			'user'       => wp_get_current_user(),
			'kpis'       => \MHMRentiva\Core\Dashboard\DashboardConfig::get_kpis( 'customer' ),
			'kpi_data'   => array(
				'total_bookings' => array(
					'total'     => 4,
					'trend'     => 25,
					'direction' => 'up',
				),
			),
		);
	}

	public function test_strip_is_kit_markup_inside_the_front_page_shell(): void {
		wp_set_current_user( self::factory()->user->create() );

		$html = CustomerDashboard::render( $this->payload() );

		$this->assertStringContainsString( 'mhmui-front mhmui-front-page', $html );
		$this->assertStringContainsString( 'mhmui-stat-card', $html );
		$this->assertStringNotContainsString( 'mhm-rentiva-dashboard__kpi-card', $html );
		$this->assertStringNotContainsString( 'data-count', $html );
	}

	public function test_the_strip_carries_no_icon(): void {
		wp_set_current_user( self::factory()->user->create() );

		$html = CustomerDashboard::render( $this->payload() );

		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertStringNotContainsString( 'dashicons', $html );
	}

	/**
	 * The old `__kpis` wrapper gave the strip its bottom gap. Its replacement
	 * is a Rentiva-owned class, never a rule on `.mhmui-front`, which is the
	 * kit's shared token scope.
	 */
	public function test_the_strip_sits_in_a_rentiva_owned_spacing_wrapper(): void {
		wp_set_current_user( self::factory()->user->create() );

		$html = CustomerDashboard::render( $this->payload() );

		$this->assertMatchesRegularExpression( '/<div class="mhm-rentiva-dashboard__strip">\s*<div class="mhmui-stats-grid/', $html );
	}

	public function test_a_trend_becomes_a_kit_delta_line(): void {
		wp_set_current_user( self::factory()->user->create() );

		$html = CustomerDashboard::render( $this->payload() );

		$this->assertStringContainsString( 'mhmui-stat-card__delta--up', $html );
		$this->assertStringContainsString( '25', $html );
		// Accessible name for the direction (kit 0.13.0 delta.label): the arrow is aria-hidden.
		$this->assertStringContainsString( 'mhmui-stat-card__delta-sr', $html );
	}

	public function test_the_count_up_script_is_gone(): void {
		$this->assertFileDoesNotExist( MHMRENTIVA_PLUGIN_PATH . 'assets/js/frontend/user-dashboard.js' );
	}
}
