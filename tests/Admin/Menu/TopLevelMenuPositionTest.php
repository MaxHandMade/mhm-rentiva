<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Menu;

use MHMRentiva\Admin\Utilities\Menu\Menu;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

/**
 * The top-level menu's position has moved twice: 58 -> 76 in 0cf5a089, and
 * back to 58 here. Nothing recorded the decision, so each move looked free.
 *
 * 58 is not arbitrary. WooCommerce registers at 55.5 and core's separator sits
 * at 59, so 58 is the last slot below WooCommerce and above Appearance (60) --
 * the plugin clears core's whole stack without displacing it. It is also the
 * floor of the WordPress.org guidance this plugin ships under, which asks for
 * 58 or higher so a plugin does not compete with core's own items.
 *
 * Going lower would gain a little visibility and put a published plugin below
 * that floor. Going higher is what this test exists to stop drifting back to.
 *
 * This is a behavioural test rather than the structural regex the task brief
 * offered as a fallback: add_menu_page() only ever writes into the global
 * $menu array, it does not require is_admin() or a real admin request to run,
 * so Menu::add_menu() can be called directly here and the real $menu it
 * populates read back -- the same pattern MenuNoProSubmenusTest and
 * PayoutMenuGatingTest already use in this suite for submenu assertions.
 */
final class TopLevelMenuPositionTest extends WP_UnitTestCase
{
	use UserManagementCapabilities;

	private const EXPECTED_POSITION = 58;

	protected function setUp(): void
	{
		parent::setUp();
		$actor_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$this->grant_user_management_privilege($actor_id);
		wp_set_current_user($actor_id);
	}

	protected function tearDown(): void
	{
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();
		parent::tearDown();
	}

	public function test_top_level_menu_is_registered_at_the_agreed_position(): void
	{
		Menu::add_menu();

		global $menu;
		$position = null;
		foreach ($menu as $pos => $item) {
			if (( $item[2] ?? null ) === 'mhm-rentiva') {
				$position = $pos;
				break;
			}
		}

		$this->assertSame(
			self::EXPECTED_POSITION,
			$position,
			'The top-level menu must register at position ' . self::EXPECTED_POSITION . '.'
		);
	}

	public function test_position_stays_at_or_above_the_wporg_floor(): void
	{
		$this->assertGreaterThanOrEqual(
			58,
			self::EXPECTED_POSITION,
			'WordPress.org guidance asks a plugin menu to sit at 58 or higher so it does not compete with core.'
		);
	}
}
