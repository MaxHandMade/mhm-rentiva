<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Menu;

use MHMRentiva\Admin\Utilities\Menu\Menu;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

/**
 * The top-level menu's position is a recorded decision, not a free choice.
 *
 * History: 6 -> 58 after a WordPress.org review note that a plugin menu should
 * not compete with core's top-level items; 58 -> 76 (0cf5a089) -> 58 in 6.1.4;
 * 58 -> 55.4 in 6.1.5 at the product owner's request (2026-09-17). At 58 the
 * menu sat under WooCommerce, Products, Payments and Analytics, and with Pro's
 * submenus its list ran well below the screen.
 *
 * 55.4 puts the menu directly above WooCommerce (55.5) and still below core's
 * content block (Comments, 25). Core registers nothing between 25 and its
 * separator at 59, so relative to core's own items the menu sits where 58 did.
 * The earlier "58 or higher" floor was our own reading of the review note --
 * which was about position 6 -- not a published rule.
 *
 * Behavioural, not structural: add_menu_page() only writes into the global
 * $menu array and needs no real admin request, so Menu::add_menu() is called
 * directly and the $menu it populates is read back -- the pattern
 * MenuNoProSubmenusTest and PayoutMenuGatingTest already use.
 */
final class TopLevelMenuPositionTest extends WP_UnitTestCase
{
	use UserManagementCapabilities;

	/** Core casts a float position to a string key. */
	private const EXPECTED_POSITION = '55.4';

	/** WooCommerce's own add_menu_page() position (class-wc-admin-menus.php). */
	private const WOOCOMMERCE_POSITION = 55.5;

	/** Core's Comments menu, the last item of core's content block. */
	private const CORE_COMMENTS_POSITION = 25;

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

	private function registered_position(): ?string
	{
		Menu::add_menu();

		global $menu;
		foreach ($menu as $pos => $item) {
			if (( $item[2] ?? null ) === 'mhm-rentiva') {
				return (string) $pos;
			}
		}

		return null;
	}

	public function test_top_level_menu_is_registered_at_the_agreed_position(): void
	{
		$this->assertSame(
			self::EXPECTED_POSITION,
			$this->registered_position(),
			'The top-level menu must register at position ' . self::EXPECTED_POSITION . '.'
		);
	}

	public function test_menu_sits_above_woocommerce_and_below_core_content_items(): void
	{
		$position = (float) $this->registered_position();

		$this->assertLessThan(self::WOOCOMMERCE_POSITION, $position, 'The menu must sit directly above WooCommerce.');
		$this->assertGreaterThan(self::CORE_COMMENTS_POSITION, $position, 'The menu must not climb into core\'s content block.');
	}
}
