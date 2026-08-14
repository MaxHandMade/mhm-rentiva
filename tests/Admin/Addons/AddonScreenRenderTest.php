<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonPricingType;
use MHMRentiva\Admin\Addons\AddonScreen;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * What the screen paints.
 *
 * THE DRAG HANDLE IS CONDITIONAL, AND THAT IS THE POINT
 * -----------------------------------------------------
 * Rows can be dragged only when the list is actually sorted by position. The
 * sort criterion is a site-wide setting (`mhmrentiva_addon_display_order`:
 * menu_order | title | price_asc | price_desc | date_created), and if it says
 * "title" while the screen offers a drag handle, the operator drags a row, the
 * endpoint dutifully writes menu_order, and the list re-renders in title order
 * exactly as before. The work is accepted and silently discarded — the worst
 * kind of broken, because nothing reports an error.
 *
 * So the handle appears for menu_order and is absent otherwise.
 */
final class AddonScreenRenderTest extends WP_UnitTestCase {

	/** @var int[] */
	private array $addon_ids = array();

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->addon_ids['active'] = self::factory()->post->create(
			array(
				'post_type'    => 'mhmrentiva_addon',
				'post_title'   => 'GPS Navigation',
				'post_excerpt' => 'Device with up-to-date maps',
				'post_status'  => 'publish',
				'menu_order'   => 0,
			)
		);
		update_post_meta( $this->addon_ids['active'], 'mhmrentiva_addon_enabled', '1' );
		update_post_meta( $this->addon_ids['active'], 'mhmrentiva_addon_price', '150' );
		update_post_meta( $this->addon_ids['active'], '_mhmrentiva_addon_pricing_type', AddonPricingType::PER_DAY );

		$this->addon_ids['inactive'] = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'Snow Tyres',
				'post_status' => 'publish',
				'menu_order'  => 1,
			)
		);
		update_post_meta( $this->addon_ids['inactive'], 'mhmrentiva_addon_enabled', '0' );
		update_post_meta( $this->addon_ids['inactive'], 'mhmrentiva_addon_price', '120' );
	}

	protected function tearDown(): void {
		SettingsCore::set( 'mhmrentiva_addon_display_order', 'menu_order' );
		$this->addon_ids = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * render_page() calls wp_die() for an unauthorised user, which throws out of
	 * the buffer, so the cleanup has to be in a finally -- otherwise PHPUnit
	 * reports the test as risky for leaving a buffer open.
	 */
	private function render(): string {
		ob_start();
		try {
			AddonScreen::render_page();
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	public function test_it_renders_a_row_for_each_add_on(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'GPS Navigation', $html );
		$this->assertStringContainsString( 'Snow Tyres', $html );
		// Counted on the id attribute, not the class: `rv-addon-row--off` also
		// contains `rv-addon-row`, so a class count reads 3 for two rows.
		$this->assertSame(
			2,
			substr_count( $html, 'data-addon-id=' ),
			'One row per add-on.'
		);
	}

	public function test_a_row_carries_its_id_for_the_endpoints(): void {
		$html = $this->render();

		$this->assertStringContainsString(
			'data-addon-id="' . $this->addon_ids['active'] . '"',
			$html,
			'The toggle and reorder endpoints are driven from this attribute.'
		);
	}

	public function test_it_shows_the_description_and_price(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'Device with up-to-date maps', $html );
		$this->assertStringContainsString( '150', $html );
	}

	public function test_an_inactive_add_on_is_marked_as_such(): void {
		$html = $this->render();

		$this->assertStringContainsString(
			'rv-addon-row--off',
			$html,
			'The screen must show which services are switched off; the KPI band alone does not.'
		);
	}

	public function test_the_counter_reports_active_and_total(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression(
			'/1\D+2/u',
			wp_strip_all_tags( $html ),
			'The list header carries "{active} · {total}".'
		);
	}

	public function test_the_drag_handle_is_present_when_sorting_by_position(): void {
		SettingsCore::set( 'mhmrentiva_addon_display_order', 'menu_order' );

		$this->assertStringContainsString( 'rv-addon-drag', $this->render() );
	}

	/**
	 * The rule this class exists to protect.
	 */
	public function test_the_drag_handle_is_hidden_when_sorting_by_something_else(): void {
		SettingsCore::set( 'mhmrentiva_addon_display_order', 'title' );

		$html = $this->render();

		$this->assertStringNotContainsString(
			'rv-addon-drag',
			$html,
			'Offering a drag handle while the list sorts by title accepts the operator\'s work and discards it.'
		);
	}

	public function test_each_row_links_to_the_full_editor(): void {
		$html = $this->render();

		$this->assertStringContainsString(
			'post=' . $this->addon_ids['active'],
			$html,
			'The quick form writes four fields; the other seven live in the editor this links to.'
		);
	}

	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( \WPDieException::class );
		$this->render();
	}
}
