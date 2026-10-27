<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * PR D, R2: plain-text counters on Lite's "My bookings" (upcoming) and
 * "My favorites" menu items; nothing for 0; other items untouched.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration::add_menu_counts
 */
final class AccountNavCountsTest extends WP_UnitTestCase
{
	private int $user = 0;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(class_exists('WooCommerce'), 'These cases need WooCommerce.');
		WooCommerceIntegration::clear_slug_cache();
		$this->user = ShortcodeFixtures::customer();
		wp_set_current_user($this->user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_appends_upcoming_count(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		ShortcodeFixtures::booking($this->user, $vehicle, 'confirmed', '100', 5);
		ShortcodeFixtures::booking($this->user, $vehicle, 'pending', '100', 9);
		ShortcodeFixtures::booking($this->user, $vehicle, 'completed', '100', -10); // Past: not counted.
		ShortcodeFixtures::booking($this->user, $vehicle, 'cancelled', '100', 5); // Cancelled: not counted.
		update_user_meta($this->user, 'mhmrentiva_favorites', array( 11, 12, 13, 14, 15 ));

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
		$vehicle = ShortcodeFixtures::vehicle();
		ShortcodeFixtures::booking($this->user, $vehicle, 'confirmed', '100', 5);
		update_user_meta($this->user, 'mhmrentiva_favorites', array( 11 ));

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

	public function test_hooked_after_the_items_are_added(): void
	{
		$this->assertSame(20, has_filter('woocommerce_account_menu_items', array( WooCommerceIntegration::class, 'add_menu_items' )));
		$this->assertSame(21, has_filter('woocommerce_account_menu_items', array( WooCommerceIntegration::class, 'add_menu_counts' )));
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
