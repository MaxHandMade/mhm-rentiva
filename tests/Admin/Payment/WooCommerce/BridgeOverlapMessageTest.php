<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\SiteClock;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR T -- the cart's "no longer available" refusal names the dates the
 * customer picked.
 *
 * The bridge now reads the pickup as a real instant (site timezone). Printing
 * that instant through date_i18n() -- which expects a timestamp with the
 * offset already added -- shows the UTC date: an Istanbul pickup at 01:00 on
 * 2 November would be announced as 1 November. wp_date() prints it locally.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_checkout_availability
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_cart_availability
 */
final class BridgeOverlapMessageTest extends WP_UnitTestCase
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
		add_filter('pre_option_date_format', static fn() => 'd.m.Y');

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Overlap Sedan',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		// A confirmed booking holding the vehicle 1-5 November, local noon.
		$this->confirmed_booking((int) SiteTime::instant('2026-11-01', '12:00'), (int) SiteTime::instant('2026-11-05', '12:00'));
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

	public function test_overlap_refusal_message_prints_local_date(): void
	{
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->booking_data(), 100.0));

		WooCommerceBridge::validate_checkout_availability();

		$this->assertSame(1, wc_notice_count('error'), 'Precondition: the overlap is refused.');
		$message = $this->first_error();
		$this->assertStringContainsString('02.11.2026', $message);
		$this->assertStringContainsString('04.11.2026', $message);
	}

	public function test_cart_refusal_message_prints_local_date(): void
	{
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->booking_data(), 100.0));

		WooCommerceBridge::validate_cart_availability();

		$this->assertSame(1, wc_notice_count('error'), 'Precondition: the overlap is refused.');
		$message = $this->first_error();
		$this->assertStringContainsString('02.11.2026', $message);
		$this->assertStringContainsString('04.11.2026', $message);
	}

	private function first_error(): string
	{
		$notices = wc_get_notices('error');
		$first   = $notices[0] ?? array();

		return (string) ( is_array($first) ? ( $first['notice'] ?? '' ) : $first );
	}

	private function confirmed_booking(int $start_ts, int $end_ts): void
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($booking_id, '_mhmrentiva_vehicle_id', $this->vehicle_id);
		update_post_meta($booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $start_ts));
		update_post_meta($booking_id, '_mhmrentiva_pickup_time', wp_date('H:i', $start_ts));
		update_post_meta($booking_id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $end_ts));
		update_post_meta($booking_id, '_mhmrentiva_dropoff_time', wp_date('H:i', $end_ts));
		update_post_meta($booking_id, '_mhmrentiva_start_ts', $start_ts);
		update_post_meta($booking_id, '_mhmrentiva_end_ts', $end_ts);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function booking_data(): array
	{
		return array(
			'vehicle_id'          => $this->vehicle_id,
			'pickup_date'         => '2026-11-02',
			'pickup_time'         => '01:00',
			'dropoff_date'        => '2026-11-04',
			'dropoff_time'        => '01:00',
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
