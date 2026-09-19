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

	/**
	 * Task 8 deleted the `.stats-grid` rules that used to give the strip its
	 * bottom gap. The strip's wrapper must carry a Rentiva-owned class (not a
	 * rule on `.mhmui-front` itself, which is the kit's shared token scope) so
	 * the gap can be restored without leaking into every other `.mhmui-front`
	 * user on the page.
	 */
	public function test_the_kpi_strip_wrapper_carries_a_rentiva_owned_spacing_class(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		$html = AccountRenderer::render_dashboard( array() );

		$this->assertMatchesRegularExpression(
			'/class="mhmui-front mhm-account-kpi-strip"/',
			$html
		);
	}

	/**
	 * CSS can't be exercised at runtime in PHPUnit, so this is a text-level
	 * gate in the style of tests/Admin/LayoutStandardTest.php: it reads the
	 * stylesheet as text and asserts the account `p`/`li`/`label` colour rule
	 * stays scoped away from the kit's own subtree (`.mhmui-front`). Without
	 * the `:not(.mhmui-front *)` guard this blanket rule repaints every `<p>`
	 * the kit renders inside its stat cards (label, value, sub line, delta
	 * line), including colours (like the kit's muted label, or a trend's
	 * green/red delta) the blanket rule was never meant to touch.
	 */
	public function test_the_account_content_text_color_rule_excludes_the_kit_scope(): void {
		$css = (string) file_get_contents(
			MHMRENTIVA_PLUGIN_PATH . 'assets/css/frontend/my-account.css'
		);

		foreach ( array( 'p', 'li', 'label' ) as $tag ) {
			$this->assertStringContainsString(
				".woocommerce-account .woocommerce-MyAccount-content {$tag}:not(.mhmui-front *)",
				$css,
				"Expected the {$tag} colour rule to exclude .mhmui-front descendants."
			);
		}
	}
}
