<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonScreen;
use WP_UnitTestCase;

/**
 * The per-row Aktif/Pasif toggle.
 *
 * WHY THE GUARDS GET ONE TEST EACH
 * --------------------------------
 * Three separate things have to be true before this endpoint writes: a valid
 * nonce, `manage_options`, and a target that really is an add-on. A single
 * "unauthorised request is rejected" test would pass with any one of them in
 * place and would keep passing if the other two were deleted, which is exactly
 * how AddonManager::handle_bulk_actions shipped without a post-type check and
 * could wp_delete_post() any post on the site until T8 caught it.
 *
 * So each guard is removed in isolation by the test below -- one request that
 * carries everything except a nonce, one that carries everything except the
 * capability, one that carries everything except a real add-on ID -- and each
 * has to be refused on its own.
 *
 * The pattern being copied is handle_update_price(), which is the one endpoint
 * in this file that already had all three.
 */
final class AddonToggleEndpointTest extends WP_UnitTestCase {

	private int $addon_id;
	private int $admin_id;

	protected function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->addon_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'GPS Navigation',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', '1' );

		wp_set_current_user( $this->admin_id );
	}

	protected function tearDown(): void {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $overrides
	 */
	private function build_request( array $overrides = array() ): void {
		$_POST = array_merge(
			array(
				'nonce'    => wp_create_nonce( 'mhmrentiva_addon_list_nonce' ),
				'addon_id' => (string) $this->addon_id,
				'enabled'  => '0',
			),
			$overrides
		);
	}

	private function current_enabled(): string {
		return (string) get_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', true );
	}

	public function test_it_flips_the_enabled_flag(): void {
		$this->build_request();

		$result = AddonScreen::toggle_enabled( $_POST );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '0', $this->current_enabled() );
		$this->assertFalse( $result['enabled'], 'The response reports the state the row should now render.' );
	}

	public function test_it_turns_the_flag_back_on(): void {
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', '0' );
		$this->build_request( array( 'enabled' => '1' ) );

		$result = AddonScreen::toggle_enabled( $_POST );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '1', $this->current_enabled() );
		$this->assertTrue( $result['enabled'] );
	}

	/** Guard 1 in isolation: everything valid except the nonce. */
	public function test_it_refuses_a_request_with_a_bad_nonce(): void {
		$this->build_request( array( 'nonce' => 'not-a-nonce' ) );

		$result = AddonScreen::toggle_enabled( $_POST );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '1', $this->current_enabled(), 'A rejected request must not have written.' );
	}

	/**
	 * Guard 2 in isolation: valid nonce, real add-on, but no capability.
	 *
	 * The switch to the subscriber happens BEFORE the nonce is minted, and the
	 * order is the whole test. Minting first and switching after produces a
	 * nonce tied to the administrator, which the subscriber's request then
	 * fails on — so the assertion below passed while measuring the nonce guard
	 * instead of this one. Caught by mutation: deleting the capability check
	 * left this test green.
	 */
	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->build_request();

		$result = AddonScreen::toggle_enabled( $_POST );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '1', $this->current_enabled() );
	}

	/**
	 * Guard 3 in isolation: valid nonce, full capability, but the target is an
	 * ordinary page. This is the guard handle_bulk_actions was missing.
	 */
	public function test_it_refuses_a_target_that_is_not_an_add_on(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->build_request( array( 'addon_id' => (string) $page_id ) );

		$result = AddonScreen::toggle_enabled( $_POST );

		$this->assertFalse( $result['success'] );
		$this->assertSame(
			'',
			(string) get_post_meta( $page_id, 'mhmrentiva_addon_enabled', true ),
			'A page must not come out of this endpoint carrying add-on meta.'
		);
	}

	/** A deleted or never-existing ID is refused the same way. */
	public function test_it_refuses_a_missing_post(): void {
		$this->build_request( array( 'addon_id' => '99999999' ) );

		$result = AddonScreen::toggle_enabled( $_POST );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Every test above calls toggle_enabled() directly, which proves the
	 * decision logic and proves nothing about whether a browser can ever reach
	 * it. A correct endpoint that no `wp_ajax_` action points at is dead code,
	 * and no gate in this repo would notice: PHPCS, PHPStan and the suite are
	 * all satisfied by a method that is never wired.
	 *
	 * So this asserts the wiring itself, from the class that owns the screen's
	 * admin bootstrap.
	 */
	public function test_the_endpoint_is_actually_registered_on_admin_init(): void {
		\MHMRentiva\Admin\Addons\AddonManager::admin_init();

		$this->assertSame(
			10,
			has_action( 'wp_ajax_mhmrentiva_addon_toggle_enabled', array( AddonScreen::class, 'ajax_toggle_enabled' ) ),
			'The toggle endpoint must be reachable over admin-ajax, not just callable from a test.'
		);
	}
}
