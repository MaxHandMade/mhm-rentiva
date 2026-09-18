<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use WP_UnitTestCase;

final class AccountDashboardStripTest extends WP_UnitTestCase {

	public function test_the_account_strip_is_kit_markup_without_icons(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		$html = AccountRenderer::render_dashboard( array() );

		$this->assertStringContainsString( 'mhmui-front', $html );
		$this->assertStringContainsString( 'mhmui-stat-card', $html );
		$this->assertStringNotContainsString( 'class="stat-card', $html );
		$this->assertStringNotContainsString( 'dashicons', $html );
		$this->assertStringNotContainsString( '<svg', $html );
	}
}
