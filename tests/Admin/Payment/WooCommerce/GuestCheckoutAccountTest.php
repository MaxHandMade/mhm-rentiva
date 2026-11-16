<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR-C -- a guest paying for a rental is offered an account at checkout.
 *
 * Customer walkthrough (11-11 #10): a guest could book, but the checkout had
 * no "Create an account?" box, so the guest had no way back to the booking
 * (My Account asks for a login). Product decision (11-11): show WooCommerce's
 * own option while a rental is in the cart. The store setting is not
 * touched; other carts follow it. The booking then follows the order to the
 * new account (WooCommerceBridge, the order's customer id).
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::offer_account_for_rentals
 */
final class GuestCheckoutAccountTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		$this->ensure_booking_product();
		WC()->cart->empty_cart();
		add_filter('pre_option_woocommerce_enable_signup_and_login_from_checkout', static fn() => 'no');
		add_filter('woocommerce_checkout_registration_enabled', array( WooCommerceBridge::class, 'offer_account_for_rentals' ));
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();
	}

	public function test_a_rental_in_the_cart_offers_an_account(): void
	{
		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart(array(
			'vehicle_id'   => $vehicle,
			'pickup_date'  => '2027-04-02',
			'pickup_time'  => '10:00',
			'dropoff_date' => '2027-04-04',
			'dropoff_time' => '10:00',
		), 100.0));

		$this->assertTrue(WC()->checkout()->is_registration_enabled());
	}

	public function test_a_cart_without_a_rental_follows_the_store_setting(): void
	{
		$this->assertFalse(WC()->checkout()->is_registration_enabled(), 'An empty cart: the store said no.');

		$other = new \WC_Product_Simple();
		$other->set_regular_price('5');
		$other->save();
		WC()->cart->add_to_cart($other->get_id());

		$this->assertFalse(WC()->checkout()->is_registration_enabled(), 'A shop item: the store said no.');
	}

	public function test_a_store_that_already_offers_accounts_keeps_offering_them(): void
	{
		add_filter('pre_option_woocommerce_enable_signup_and_login_from_checkout', static fn() => 'yes', 20);

		$this->assertTrue(WC()->checkout()->is_registration_enabled());
	}
}
