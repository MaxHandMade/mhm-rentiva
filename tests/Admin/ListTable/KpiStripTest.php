<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use MHMRentiva\Admin\Addons\AddonListTable;
use WP_UnitTestCase;

/**
 * Task 6 of the KPI kit migration: the three server-rendered list screens
 * (Bookings, Vehicles, Add-ons) move their hand-written .mhm-stat-card
 * strips onto the ui-core kit's mhmuicore_stats_grid_html()/stat_card_html().
 */
final class KpiStripTest extends WP_UnitTestCase {

	/** @var mixed */
	private $prev_pagenow;

	/** @var mixed */
	private $prev_post_type;

	protected function setUp(): void {
		parent::setUp();
		$this->prev_pagenow  = $GLOBALS['pagenow'] ?? null;
		$this->prev_post_type = $GLOBALS['post_type'] ?? null;
	}

	protected function tearDown(): void {
		$GLOBALS['pagenow']   = $this->prev_pagenow;
		$GLOBALS['post_type'] = $this->prev_post_type;
		parent::tearDown();
	}

	/**
	 * The strips guard on the current screen, so the test puts the globals the
	 * renderers read into the state edit.php would have.
	 */
	private function render_strip( callable $renderer, string $post_type ): string {
		$GLOBALS['pagenow']   = 'edit.php';
		$GLOBALS['post_type'] = $post_type;

		ob_start();
		$renderer();
		return (string) ob_get_clean();
	}

	public function test_booking_strip_is_kit_markup_wrapped_in_the_admin_scope(): void {
		$html = $this->render_strip(
			static fn () => BookingColumns::add_booking_stats_cards(),
			'mhmrentiva_booking'
		);

		$this->assertStringContainsString( '<div class="mhmui-admin">', $html );
		$this->assertStringContainsString( 'mhmui-stats-grid', $html );
		preg_match_all( '/class="mhmui-stat-card[ "]/', $html, $matches );
		$this->assertSame( 4, count( $matches[0] ) );
		$this->assertStringNotContainsString( 'class="mhm-stat-card', $html );
	}

	public function test_pending_is_the_only_toned_card_and_its_label_carries_the_meaning(): void {
		$html = $this->render_strip(
			static fn () => BookingColumns::add_booking_stats_cards(),
			'mhmrentiva_booking'
		);

		$this->assertSame( 1, substr_count( $html, 'mhmui-stat-card--warning' ) );
		$this->assertStringNotContainsString( 'mhmui-stat-card--success', $html );
	}

	public function test_vehicle_and_addon_strips_are_kit_markup_with_an_icon_per_card(): void {
		foreach ( array(
			array( static fn () => VehicleColumns::add_vehicle_stats_cards(), 'mhmrentiva_vehicle' ),
			array( static fn () => AddonListTable::add_addon_stats_cards(), 'mhmrentiva_addon' ),
		) as [ $renderer, $post_type ] ) {
			$html = $this->render_strip( $renderer, $post_type );

			$this->assertStringContainsString( 'mhmui-stats-grid', $html );
			$this->assertSame( 4, substr_count( $html, 'dashicons dashicons-' ), $post_type );
			$this->assertStringNotContainsString( 'class="mhm-stat-card', $html );
		}
	}
}
