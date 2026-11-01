<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use WP_UnitTestCase;

/**
 * PR D (r2, Task 1 ekleri): on the booking detail the "My bookings" menu item
 * is the current one. WooCommerce marks nothing there (the detail is not a menu
 * item), so Lite adds `is-active` through `woocommerce_account_menu_item_classes`.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration::menu_item_classes
 */
final class AccountNavActiveItemTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(class_exists('WooCommerce'), 'These cases need WooCommerce.');
		WooCommerceIntegration::clear_slug_cache();
	}

	public function tearDown(): void
	{
		global $wp;
		$wp->query_vars = array();
		parent::tearDown();
	}

	private function on(string $key): void
	{
		global $wp;
		$wp->query_vars = array( WooCommerceIntegration::get_endpoint_slug($key) => '42' );
	}

	public function test_bookings_item_active_on_booking_detail(): void
	{
		$this->on('view_booking');
		$bookings = WooCommerceIntegration::get_endpoint_slug('bookings');

		$classes = wc_get_account_menu_item_classes($bookings);

		$this->assertStringContainsString('is-active', $classes);
		$this->assertSame(1, substr_count($classes, 'is-active'));
	}

	public function test_other_items_untouched_on_booking_detail(): void
	{
		$this->on('view_booking');

		$this->assertStringNotContainsString('is-active', wc_get_account_menu_item_classes(WooCommerceIntegration::get_endpoint_slug('favorites')));
		$this->assertStringNotContainsString('is-active', wc_get_account_menu_item_classes('orders'));
	}

	public function test_bookings_item_not_forced_elsewhere(): void
	{
		$this->on('favorites');

		$this->assertStringNotContainsString('is-active', wc_get_account_menu_item_classes(WooCommerceIntegration::get_endpoint_slug('bookings')));
	}

	public function test_filter_is_registered(): void
	{
		$this->assertSame(10, has_filter('woocommerce_account_menu_item_classes', array( WooCommerceIntegration::class, 'menu_item_classes' )));
	}
}
