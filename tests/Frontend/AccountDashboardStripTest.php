<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

/**
 * PR D, Task 3 (R14, M7): the KPI strip and the quick actions are gone from the
 * dashboard. The class keeps its name; it now pins their absence.
 */
final class AccountDashboardStripTest extends WP_UnitTestCase {

	public function test_no_kpi_strip(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'customer', 'first_name' => 'Ayse' ) );
		wp_set_current_user( $user_id );

		$html = AccountRenderer::render_dashboard( array() );

		$this->assertStringNotContainsString( 'mhm-account-kpi-strip', $html );
		$this->assertStringNotContainsString( 'mhmui-stat-card', $html );
		$this->assertStringNotContainsString( 'mhmui-front', $html );
		$this->assertStringNotContainsString( 'quick-actions', $html );
		$this->assertStringNotContainsString( 'Total Bookings', $html );
		$this->assertStringNotContainsString( 'Quick Actions', $html );
		$this->assertStringContainsString( 'Hello Ayse', $html );
	}
}
