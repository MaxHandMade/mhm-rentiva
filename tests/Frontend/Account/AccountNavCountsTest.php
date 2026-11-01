<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * PR D, R2: plain-text counters on Lite's "My bookings" (upcoming) and
 * "My favorites" menu items; nothing for 0; other items untouched; only on
 * the front-end My Account page (fix round 1: the Menus metabox and the
 * Customizer save the label into a menu item, and the Customer account
 * block reads the items on every page).
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration::add_menu_counts
 */
final class AccountNavCountsTest extends WP_UnitTestCase
{
	private int $user = 0;

	private int $account_page = 0;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(class_exists('WooCommerce'), 'These cases need WooCommerce.');
		WooCommerceIntegration::clear_slug_cache();
		$this->user = ShortcodeFixtures::customer();
		wp_set_current_user($this->user);
		$this->account_page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Zz Account',
				'post_content' => '[woocommerce_my_account]',
			)
		);
		update_option('woocommerce_myaccount_page_id', $this->account_page);
		$this->go_to((string) get_permalink($this->account_page));
		$this->assertTrue(is_account_page(), 'Premise: on the account page.');
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		update_option('woocommerce_myaccount_page_id', 0);
		set_current_screen('front');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_appends_upcoming_count(): void
	{
		$this->fixture();

		$items = $this->menu();

		$this->assertSame('My bookings (2)', $items[ WooCommerceIntegration::get_endpoint_slug('bookings') ]);
		$this->assertSame('My favorites (5)', $items[ WooCommerceIntegration::get_endpoint_slug('favorites') ]);
		$this->assertSame('Payment history', $items[ WooCommerceIntegration::get_endpoint_slug('payment_history') ]);
	}

	public function test_zero_count_leaves_label(): void
	{
		$items = $this->menu();

		$this->assertSame('My bookings', $items[ WooCommerceIntegration::get_endpoint_slug('bookings') ]);
		$this->assertSame('My favorites', $items[ WooCommerceIntegration::get_endpoint_slug('favorites') ]);

		wp_set_current_user(0);
		$guest = WooCommerceIntegration::add_menu_counts(array( 'x' => 'X' ));
		$this->assertSame(array( 'x' => 'X' ), $guest, 'No user, nothing to count.');
	}

	public function test_pro_items_untouched(): void
	{
		$this->fixture();

		$items = WooCommerceIntegration::add_menu_counts(
			array(
				'dashboard'       => 'Dashboard',
				'messages'        => 'Messages (3)',
				'vendor-panel'    => 'Vendor panel',
				'orders'          => 'Orders',
				'customer-logout' => 'Log out',
			)
		);

		$this->assertSame(
			array(
				'dashboard'       => 'Dashboard',
				'messages'        => 'Messages (3)',
				'vendor-panel'    => 'Vendor panel',
				'orders'          => 'Orders',
				'customer-logout' => 'Log out',
			),
			$items
		);
	}

	/**
	 * wp-admin (Appearance > Menus metabox, which saves the label into a menu
	 * item): no counts.
	 */
	public function test_admin_context_leaves_labels(): void
	{
		$this->fixture();
		set_current_screen('nav-menus');
		$this->assertTrue(is_admin(), 'Premise: admin context.');

		$items = $this->menu();

		$this->assertSame('My bookings', $items[ WooCommerceIntegration::get_endpoint_slug('bookings') ]);
		$this->assertSame('My favorites', $items[ WooCommerceIntegration::get_endpoint_slug('favorites') ]);
	}

	/**
	 * Any other front page (the Customer account block's dropdown): no counts.
	 */
	public function test_non_account_front_page_leaves_labels(): void
	{
		$this->fixture();
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Plain',
			)
		);
		$this->go_to((string) get_permalink($page));
		$this->assertFalse(is_account_page(), 'Premise: not the account page.');

		$items = $this->menu();

		$this->assertSame('My bookings', $items[ WooCommerceIntegration::get_endpoint_slug('bookings') ]);
		$this->assertSame('My favorites', $items[ WooCommerceIntegration::get_endpoint_slug('favorites') ]);
	}

	public function test_hooked_after_the_items_are_added(): void
	{
		$this->assertSame(20, has_filter('woocommerce_account_menu_items', array( WooCommerceIntegration::class, 'add_menu_items' )));
		$this->assertSame(21, has_filter('woocommerce_account_menu_items', array( WooCommerceIntegration::class, 'add_menu_counts' )));
	}

	/**
	 * Two upcoming, one past, one cancelled booking; five favourites.
	 */
	private function fixture(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		ShortcodeFixtures::booking($this->user, $vehicle, 'confirmed', '100', 5);
		ShortcodeFixtures::booking($this->user, $vehicle, 'pending', '100', 9);
		ShortcodeFixtures::booking($this->user, $vehicle, 'completed', '100', -10); // Past: not counted.
		ShortcodeFixtures::booking($this->user, $vehicle, 'cancelled', '100', 5); // Cancelled: not counted.
		update_user_meta($this->user, 'mhmrentiva_favorites', array( 11, 12, 13, 14, 15 ));
	}

	/**
	 * WooCommerce's menu items with Lite's and the counters applied, the way
	 * wc_get_account_menu_items() runs the filter.
	 *
	 * @return array<string,string>
	 */
	private function menu(): array
	{
		return (array) apply_filters(
			'woocommerce_account_menu_items',
			array(
				'dashboard'       => 'Dashboard',
				'orders'          => 'Orders',
				'customer-logout' => 'Log out',
			),
			array()
		);
	}
}
