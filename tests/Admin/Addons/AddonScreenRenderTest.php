<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
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

	/**
	 * The KPI band is a decision, not an oversight: the design opens straight
	 * into the two columns, but average price and total value are shown on no
	 * other screen, so removing them would trade working information for a
	 * resemblance. It reuses the shared card markup instead of a second system.
	 */
	public function test_it_renders_the_kpi_band(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'mhm-stats-grid', $html );
		$this->assertSame( 4, substr_count( $html, 'mhm-stat-card__label' ), 'Four cards.' );
	}

	/**
	 * A flagless service -- the shape every add-on created before the toggle
	 * existed has -- is the ONLY case where the two definitions disagree, and
	 * the old fixture had none. Both surfaces answered "1", the assertion
	 * passed, and it measured nothing: an equality both sides supply for free
	 * is not evidence. The service is created here so the fixture can express
	 * the disagreement at all.
	 *
	 * The assertion is anchored to what the SELLING definition
	 * (AddonManager::is_sellable) produces, not merely to "the two surfaces
	 * match" -- otherwise both could be wrong together and still agree.
	 */
	public function test_the_band_and_the_list_counter_agree_on_the_active_count(): void {
		$legacy = $this->createFlaglessAddon();

		$this->assertTrue(
			AddonManager::is_sellable( $legacy ),
			'Precondition: a flagless service IS sold. That is what makes a screen calling it inactive a lie.'
		);

		$html = wp_strip_all_tags( $this->render() );

		$this->assertMatchesRegularExpression(
			'/2 active/u',
			$html,
			'Two of the three services here are sellable, so the list header must say 2 -- the KPI band already does.'
		);
		$this->assertMatchesRegularExpression(
			'/3 total/u',
			$html,
			'All three are published.'
		);
	}

	/**
	 * The badge is not cosmetic: the same flag drives data-enabled, which the
	 * toggle script reads back. Reading "0" for a service that is being sold
	 * means the operator's first click sends "enable" for something already
	 * enabled -- a no-op that looks like a change.
	 */
	public function test_a_flagless_service_is_shown_as_active(): void {
		$legacy = $this->createFlaglessAddon();

		$html = $this->render();

		$this->assertMatchesRegularExpression(
			'/<div class="rv-addon-row" data-addon-id="' . $legacy . '"/u',
			$html,
			'A sold service must not be dimmed with rv-addon-row--off.'
		);

		$row = $this->rowFor( $html, $legacy );

		$this->assertStringContainsString( 'data-enabled="1"', $row, 'The toggle must report the state the seller uses.' );
		$this->assertStringContainsString( 'aria-pressed="true"', $row );
		$this->assertStringContainsString( 'Active', $row );
	}

	/**
	 * An add-on created before the enabled flag existed carries no meta row at
	 * all -- not '1', not '0', absent. update_post_meta cannot express that, so
	 * the post is simply created without it.
	 */
	private function createFlaglessAddon(): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'Child Seat',
				'post_status' => 'publish',
				'menu_order'  => 2,
			)
		);
		update_post_meta( $id, 'mhmrentiva_addon_price', '90' );

		return (int) $id;
	}

	/** The markup of one row, so an assertion cannot be satisfied by a sibling. */
	private function rowFor( string $html, int $addon_id ): string {
		$start = strpos( $html, 'data-addon-id="' . $addon_id . '"' );
		$this->assertNotFalse( $start, 'The row for add-on ' . $addon_id . ' is not on the screen.' );

		return substr( $html, $start, 2000 );
	}

	/**
	 * The native screen let an operator click a price and type a new one. A
	 * replacement without that is a capability quietly withdrawn, so the row
	 * carries the same contract its markup did: the current value in a data
	 * attribute the editor reads back, and the id the endpoint needs.
	 */
	public function test_the_price_is_editable_in_place(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'rv-addon-price-value', $html );
		$this->assertStringContainsString( 'data-price="150"', $html );
	}

	/**
	 * Bulk selection, also carried over from the native screen.
	 */
	public function test_each_row_offers_a_selection_checkbox(): void {
		$html = $this->render();

		$this->assertSame( 2, substr_count( $html, 'rv-addon-select' ) - substr_count( $html, 'rv-addon-select-all' ) );
	}

	public function test_the_bulk_bar_is_present(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'rv-addon-bulk', $html );
	}

	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( \WPDieException::class );
		$this->render();
	}
}
