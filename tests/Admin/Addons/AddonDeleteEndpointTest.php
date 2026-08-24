<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonScreen;
use WP_UnitTestCase;

/**
 * Deleting a service from the list.
 *
 * WHY THIS GOES TO THE TRASH AND NOT TO wp_delete_post( $id, true )
 * -----------------------------------------------------------------
 * The native screen this replaced deletes through WordPress's own row action,
 * which trashes. Trashed add-ons can be restored from the native list, and
 * bookings that reference a deleted service keep a resolvable post ID. Forcing
 * a permanent delete here would make the new screen more destructive than the
 * one it replaced, for the same click -- a feature regression disguised as a
 * feature.
 *
 * AddonManager::handle_bulk_actions is the cautionary tale in this same feature:
 * it called wp_delete_post( $id, true ) with no post-type check and could
 * permanently destroy any post on the site until T8. The guards below are the
 * ones it was missing, and the trash is the blast radius it should have had.
 */
final class AddonDeleteEndpointTest extends WP_UnitTestCase {

	private int $addon_id;

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->addon_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_title'  => 'Snow Tyres',
				'post_status' => 'publish',
			)
		);
	}

	protected function tearDown(): void {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $overrides
	 * @return array<string,string>
	 */
	private function build_request( array $overrides = array() ): array {
		return array_merge(
			array(
				'nonce'    => wp_create_nonce( AddonScreen::NONCE_ACTION ),
				'addon_id' => (string) $this->addon_id,
			),
			$overrides
		);
	}

	public function test_it_trashes_the_service(): void {
		$result = AddonScreen::delete_addon( $this->build_request() );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'trash', get_post_status( $this->addon_id ) );
	}

	/**
	 * The blast-radius rule, stated as an assertion: the row is recoverable
	 * after the click, exactly as it is on the native screen.
	 */
	public function test_the_service_is_recoverable_afterwards(): void {
		AddonScreen::delete_addon( $this->build_request() );

		wp_untrash_post( $this->addon_id );
		wp_update_post( array( 'ID' => $this->addon_id, 'post_status' => 'publish' ) );

		$this->assertSame( 'publish', get_post_status( $this->addon_id ) );
	}

	/** Guard 1 in isolation. */
	public function test_it_refuses_a_bad_nonce(): void {
		$result = AddonScreen::delete_addon( $this->build_request( array( 'nonce' => 'nope' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'publish', get_post_status( $this->addon_id ) );
	}

	/** Guard 2 in isolation -- user switched before the nonce is minted. */
	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = AddonScreen::delete_addon( $this->build_request() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'publish', get_post_status( $this->addon_id ) );
	}

	/**
	 * Guard 3 in isolation. This is the one handle_bulk_actions shipped without,
	 * and on a delete endpoint it is the difference between removing a service
	 * and removing a page.
	 */
	public function test_it_refuses_a_target_that_is_not_an_add_on(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Checkout',
				'post_status' => 'publish',
			)
		);

		$result = AddonScreen::delete_addon( $this->build_request( array( 'addon_id' => (string) $page_id ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'publish', get_post_status( $page_id ), 'A page must survive this endpoint untouched.' );
	}

	public function test_it_refuses_a_missing_post(): void {
		$result = AddonScreen::delete_addon( $this->build_request( array( 'addon_id' => '99999999' ) ) );

		$this->assertFalse( $result['success'] );
	}

	public function test_the_endpoint_is_registered_on_admin_init(): void {
		\MHMRentiva\Admin\Addons\AddonManager::admin_init();

		$this->assertSame(
			10,
			has_action( 'wp_ajax_mhmrentiva_addon_delete', array( AddonScreen::class, 'ajax_delete_addon' ) )
		);
	}
}
