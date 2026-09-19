<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * AssetManager::stats_grid_html() -- the one Lite entry point every KPI strip
 * prints through, always as `echo wp_kses_post( ... )`.
 *
 * Two facts are pinned here:
 *
 *  1. wp_kses_post() is lossless for the kit's markup. The call sites escape
 *     at the echo because WP.org's Plugin Check does not read our phpcs.xml
 *     (a customEscapingFunctions entry there is invisible to the reviewer).
 *     That is only safe while core's kses keeps every attribute the kit emits
 *     -- notably the grid's `style="--mhmui-columns:N"`, which kses has allowed
 *     since WP 6.1 (custom-property assignment in safecss_filter_attr). A
 *     future core that strips it turns this test red instead of silently
 *     collapsing the grid.
 *
 *  2. When an older ui-core wins the loader and the kit renderer does not
 *     exist, the strip still prints every label and value, escaped, in the
 *     kit's class shape (it used to print nothing).
 */
final class KitStatsGridHtmlTest extends WP_UnitTestCase {

	/**
	 * Covers every branch the kit renders: icon, sub, tone, emphasis, data-*,
	 * up / down / flat deltas, with and without the hidden delta label.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function cards(): array {
		return array(
			array(
				'label'    => 'Total bookings',
				'value'    => '1,204',
				'icon'     => 'calendar-alt',
				'tone'     => 'warning',
				'emphasis' => true,
				'sub'      => 'All time',
				'data'     => array( 'stat' => 'total_bookings' ),
			),
			array(
				'label' => 'Revenue',
				'value' => '12.500,00 ₺',
				'icon'  => 'money-alt',
				'delta' => array(
					'direction' => 'up',
					'text'      => '12% this month',
					'label'     => 'rose',
				),
			),
			array(
				'label' => 'Cancelled',
				'value' => 3,
				'tone'  => 'danger',
				'delta' => array(
					'direction' => 'down',
					'text'      => '2 this month',
				),
			),
			array(
				'label' => 'Pending',
				'value' => '0',
				'tone'  => 'neutral',
				'delta' => array(
					'direction' => 'flat',
					'text'      => '0',
					'label'     => 'no change',
				),
				'data'  => array( 'stat' => 'pending' ),
			),
		);
	}

	public function test_wp_kses_post_leaves_the_kit_grid_byte_identical(): void {
		$this->assertTrue( function_exists( 'mhmuicore_stats_grid_html' ), 'Premise failed: the kit renderer is not loaded.' );

		$raw = mhmuicore_stats_grid_html( $this->cards(), 4 );

		// Premise: the markup really carries the parts kses is most likely to strip.
		$this->assertStringContainsString( 'style="--mhmui-columns:4"', $raw );
		$this->assertStringContainsString( 'data-stat="total_bookings"', $raw );
		$this->assertStringContainsString( 'data-direction="flat"', $raw );
		$this->assertStringContainsString( 'aria-hidden="true"', $raw );
		$this->assertStringContainsString( 'mhmui-stat-card__delta-sr', $raw );
		$this->assertStringContainsString( 'dashicons-calendar-alt', $raw );

		$this->assertSame( $raw, wp_kses_post( $raw ) );
	}

	public function test_the_helper_is_the_kit_renderer_when_the_kit_is_loaded(): void {
		$this->assertSame(
			mhmuicore_stats_grid_html( $this->cards(), 3 ),
			AssetManager::stats_grid_html( $this->cards(), 3 )
		);
	}

	public function test_the_fallback_prints_every_label_and_value(): void {
		$html = AssetManager::stats_grid_html( $this->cards(), 4, false );

		$this->assertNotSame( '', $html );
		foreach ( $this->cards() as $card ) {
			$this->assertStringContainsString( '<p class="mhmui-stat-card__label">' . esc_html( (string) $card['label'] ) . '</p>', $html );
			$this->assertStringContainsString( '<p class="mhmui-stat-card__value">' . esc_html( (string) $card['value'] ) . '</p>', $html );
		}
		$this->assertStringContainsString( '<p class="mhmui-stat-card__sub">All time</p>', $html );
		$this->assertStringContainsString( '12% this month', $html );
		$this->assertStringContainsString( '<span class="mhmui-stat-card__delta-sr">rose </span>', $html );
		$this->assertSame( 4, substr_count( $html, 'class="mhmui-stat-card' ) - substr_count( $html, 'class="mhmui-stat-card__' ) );
	}

	public function test_the_fallback_escapes_every_string(): void {
		$html = AssetManager::stats_grid_html(
			array(
				array(
					'label' => '<script>alert(1)</script>',
					'value' => '<b>9</b>',
					'sub'   => '"><img src=x onerror=alert(2)>',
					'data'  => array( 'stat' => '" onmouseover="x' ),
				),
				array(
					'label' => 'x',
					'value' => 'y',
					'delta' => array(
						'direction' => 'up',
						'text'      => '<i>t</i>',
						'label'     => '<u>l</u>',
					),
				),
			),
			4,
			false
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( '<i>', $html );
		$this->assertStringNotContainsString( '<u>', $html );
		$this->assertStringNotContainsString( '" onmouseover=', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}

	/**
	 * Kit class names and data-* hooks survive, so kit CSS loaded later still
	 * applies and the Add-ons screen's live counters
	 * (`[data-stat="…"] .mhmui-stat-card__value`) still find their card.
	 * No dashicon and no inline style.
	 */
	public function test_the_fallback_keeps_the_kit_shape_and_hooks_without_icons_or_styles(): void {
		$html = AssetManager::stats_grid_html( $this->cards(), 4, false );

		$this->assertStringStartsWith( '<div class="mhmui-stats-grid">', $html );
		$this->assertStringContainsString( '<div class="mhmui-stat-card mhmui-stat-card--warning mhmui-stat-card--emphasis" data-stat="total_bookings"><div class="mhmui-stat-card__body">', $html );
		$this->assertStringContainsString( 'data-stat="pending"', $html );
		$this->assertStringContainsString( 'mhmui-stat-card__delta mhmui-stat-card__delta--down', $html );
		$this->assertStringNotContainsString( 'dashicons', $html );
		$this->assertStringNotContainsString( 'style=', $html );
	}

	public function test_the_fallback_also_survives_wp_kses_post_byte_identical(): void {
		$html = AssetManager::stats_grid_html( $this->cards(), 4, false );

		$this->assertSame( $html, wp_kses_post( $html ) );
	}
}
