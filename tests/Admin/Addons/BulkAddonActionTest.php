<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
use WP_Ajax_UnitTestCase;

/**
 * Found by the whole-tree sweep for the class round 4 turned up in
 * BookingDepositMetaBox: an add_action() naming a method that does not exist.
 *
 * Here the registration says `handle_bulk_action` and the method is
 * `handle_bulk_actions`. addon-list.js really does call
 * wp_ajax_mhmrentiva_bulk_addon_action, so bulk enable/disable/delete on the
 * Additional Services list has never worked -- WordPress finds no callable and
 * the request dies with 0.
 *
 * @covers \MHMRentiva\Admin\Addons\AddonManager::handle_bulk_actions
 */
final class BulkAddonActionTest extends WP_Ajax_UnitTestCase
{
	private int $addon_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->addon_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', '1' );

		wp_set_current_user(
			self::factory()->user->create( array( 'role' => 'administrator' ) )
		);

		AddonManager::register();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * The registered callback has to exist, or WordPress never reaches the code.
	 */
	public function test_the_registered_callback_exists(): void
	{
		$this->assertNotFalse(
			has_action( 'wp_ajax_mhmrentiva_bulk_addon_action' ),
			'The bulk-action endpoint should be registered.'
		);

		$callbacks = $GLOBALS['wp_filter']['wp_ajax_mhmrentiva_bulk_addon_action']->callbacks ?? array();
		$found     = false;
		foreach ( $callbacks as $priority ) {
			foreach ( $priority as $cb ) {
				$fn = $cb['function'];
				$this->assertTrue(
					is_callable( $fn ),
					'The bulk-action endpoint is registered to a callback that does not exist: '
						. ( is_array( $fn ) ? ( is_string( $fn[0] ) ? $fn[0] : get_class( $fn[0] ) ) . '::' . $fn[1] : 'closure' )
				);
				$found = true;
			}
		}

		$this->assertTrue( $found, 'No callback registered for the bulk-action endpoint.' );
	}

	/**
	 * And it has to actually do the work when called.
	 */
	public function test_bulk_disable_turns_the_addon_off(): void
	{
		$_POST = array(
			'nonce'       => wp_create_nonce( 'mhmrentiva_addon_list_nonce' ),
			'bulk_action' => 'disable_addons',
			'addon_ids'   => array( $this->addon_id ),
		);

		try {
			$this->_handleAjax( 'mhmrentiva_bulk_addon_action' );
		} catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
			// wp_send_json_* terminates; the meta assertion below is the check.
		}

		$this->assertSame(
			'0',
			(string) get_post_meta( $this->addon_id, 'mhmrentiva_addon_enabled', true ),
			'A bulk disable should have switched the add-on off. Response: ' . $this->_last_response
		);
	}
}
