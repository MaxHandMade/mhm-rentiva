<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Addons\AddonScreen;
use MHMRentiva\Admin\Addons\AddonStats;
use WP_UnitTestCase;

/**
 * The inline price editor's endpoint.
 *
 * WHY THIS FILE EXISTS AT ALL
 * ---------------------------
 * handle_update_price() called wp_send_json_success() directly, and that ends
 * in wp_die(). A test could reach the method but never its payload, so four
 * guards -- nonce, manage_options, a real add-on id, a non-negative price --
 * shipped with no assertion on any of them. The write is now a pure method and
 * the AJAX wrapper is the only part that dies, which is the same seam
 * ajax_toggle_enabled/toggle_enabled already used.
 *
 * WHY IT ASSERTS ON STATS
 * -----------------------
 * The KPI band shows Average Price and Total Value. Every other mutation on
 * this screen reloads the page (create, delete, bulk, reorder) so the band is
 * rebuilt for free; the inline price editor deliberately does not, because the
 * point of editing in place is not reloading. That left it as the one path
 * that changed a figure the band displays without telling the band -- the
 * operator typed 90, saw the row say 90, and the Total Value above it kept
 * yesterday's number until something else forced a reload.
 */
final class AddonPriceEndpointTest extends WP_UnitTestCase {

	private int $addon_id;
	private int $admin_id;

	protected function setUp(): void {
		parent::setUp();

		// The plugin's own hooks are NOT registered in the test environment --
		// measured, not assumed: has_action('wp_ajax_mhmrentiva_addon_toggle_enabled')
		// is false here while the screen works in the browser. Every other test
		// calls the pure methods directly and so never noticed. This one must
		// not: the freshness it asserts is produced by the meta hook, so
		// leaving it unregistered would test a chain production does not run
		// and report a defect that does not exist.
		AddonScreen::register();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		$this->addon_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'GPS Navigation',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_price', '100' );
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', '1' );
	}

	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** @return array<string,mixed> */
	private function request( array $overrides = array() ): array {
		return array_merge(
			array(
				'nonce'    => wp_create_nonce( 'mhmrentiva_addon_list_nonce' ),
				'addon_id' => (string) $this->addon_id,
				'price'    => '250',
			),
			$overrides
		);
	}

	private function current_price(): string {
		return (string) get_post_meta( $this->addon_id, 'mhmrentiva_addon_price', true );
	}

	public function test_it_writes_the_price(): void {
		$result = AddonManager::update_price( $this->request() );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '250', $this->current_price() );
	}

	/**
	 * The reason this endpoint was touched. Anchored to a computed figure, not
	 * merely to "a stats key is present": with one add-on priced 250 the total
	 * IS 250, so a stale payload carrying 100 fails here.
	 */
	public function test_it_returns_stats_that_already_include_the_new_price(): void {
		$before = AddonStats::get();
		$this->assertStringContainsString( '100', (string) $before['total_value'], 'Precondition: the band starts at the old price.' );

		$result = AddonManager::update_price( $this->request() );

		$this->assertArrayHasKey( 'stats', $result, 'The band cannot refresh from a payload that omits it.' );

		// Asserted on the rendered figure rather than a cast: total_value is a
		// formatted string ('100,00 $'), and (float) on a thousands-separated
		// one would silently read 1.0 for '1.250,00 $'.
		$this->assertStringContainsString(
			'250',
			(string) $result['stats']['total_value'],
			'The figures must be recomputed after the write, not read from the cache the write invalidated.'
		);
		$this->assertNotSame(
			$before['total_value'],
			$result['stats']['total_value'],
			'A payload carrying the pre-write figures leaves the band showing the operator their old number.'
		);
	}

	public function test_it_refuses_a_bad_nonce(): void {
		$result = AddonManager::update_price( $this->request( array( 'nonce' => 'not-a-nonce' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '100', $this->current_price(), 'A rejected request must not have written.' );
	}

	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = AddonManager::update_price( $this->request() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '100', $this->current_price() );
	}

	public function test_it_refuses_a_post_that_is_not_an_add_on(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = AddonManager::update_price( $this->request( array( 'addon_id' => (string) $page ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '', (string) get_post_meta( $page, 'mhmrentiva_addon_price', true ) );
	}

	public function test_it_refuses_a_negative_price(): void {
		$result = AddonManager::update_price( $this->request( array( 'price' => '-5' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '100', $this->current_price() );
	}
}
