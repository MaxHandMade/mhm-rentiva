<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\SiteClock;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR S -- the cart and the checkout refuse a window that reaches a day the
 * vehicle is closed, with the message they give for any taken window.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_cart_availability
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_checkout_availability
 */
final class BridgeBlockedDayTest extends WP_UnitTestCase
{
	use SiteClock;
	use WooCommerceFixtures;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		$this->ensure_booking_product();
		WC()->cart->empty_cart();
		wc_clear_notices();

		$this->pin_site_timezone('Europe/Istanbul');

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Quiet Sedan',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', '["2026-11-03"]');
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}
		$this->release_site_clock();
		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function validators(): array
	{
		return array(
			'cart'     => array( 'validate_cart_availability' ),
			'checkout' => array( 'validate_checkout_availability' ),
		);
	}

	/**
	 * @dataProvider validators
	 */
	public function test_customer_paths_refuse_blocked_day(string $validator): void
	{
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->booking_data('2026-11-02', '2026-11-04'), 100.0));

		WooCommerceBridge::$validator();

		$this->assertSame(1, wc_notice_count('error'));
		$this->assertStringNotContainsStringIgnoringCase('closed', $this->first_error(), 'The customer is not told why.');
	}

	/**
	 * @dataProvider validators
	 */
	public function test_a_window_clear_of_the_closed_day_passes(string $validator): void
	{
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->booking_data('2026-11-04', '2026-11-06'), 100.0));

		WooCommerceBridge::$validator();

		$this->assertSame(0, wc_notice_count('error'));
	}

	private function first_error(): string
	{
		$notices = wc_get_notices('error');
		$first   = $notices[0] ?? array();

		return (string) ( is_array($first) ? ( $first['notice'] ?? '' ) : $first );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function booking_data(string $pickup, string $dropoff): array
	{
		return array(
			'vehicle_id'          => $this->vehicle_id,
			'pickup_date'         => $pickup,
			'pickup_time'         => '10:00',
			'dropoff_date'        => $dropoff,
			'dropoff_time'        => '10:00',
			'guests'              => 1,
			'customer_user_id'    => 0,
			'customer_name'       => 'Jane Customer',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Customer',
			'customer_email'      => 'jane@example.com',
			'customer_phone'      => '5551234567',
			'payment_type'        => 'full',
			'payment_method'      => 'woocommerce',
			'total_price'         => 100,
			'rental_days'         => 2,
			'selected_addons'     => array(),
		);
	}
}
