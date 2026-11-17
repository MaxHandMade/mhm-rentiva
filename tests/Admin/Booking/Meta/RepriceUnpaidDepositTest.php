<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Re-pricing an unpaid percentage deposit keeps the booking's own rate.
 *
 * Measured on the dev site (booking #11015, manual, offline, payment pending):
 * the dates were edited, the total went 2745 -> 2575, and the deposit stayed
 * 274.50 -- 10.7% of the new total, with the remaining amount 2300.50. The
 * re-pricing kept the stored deposit because a deposit booking's deposit is
 * "what its order charged". Nothing was charged here: no money collected, no
 * WooCommerce order. Such a deposit now follows the new total at the rate the
 * booking was made with (stored deposit / stored total) -- not today's site
 * rate, which a routine save must not apply to an existing booking.
 *
 * A fixed deposit, a booking with an order and a booking without a stored total
 * keep the stored deposit as before (RecalculateBookingCostsTest).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs
 */
final class RepriceUnpaidDepositTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	/** @var callable */
	private $rate_filter;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');

		// The site rate moved to 20% after these bookings were made at 10%.
		$this->rate_filter = static fn () => array(
			'mhmrentiva_rental_deposit_type' => 'percentage',
			'mhmrentiva_rental_deposit_rate' => 20,
		);
		add_filter('pre_option_mhmrentiva_settings', $this->rate_filter);
	}

	public function tearDown(): void
	{
		remove_filter('pre_option_mhmrentiva_settings', $this->rate_filter);
		parent::tearDown();
	}

	/**
	 * Booked Mon -> Thu (3 days, 3000) with a 10% deposit, nothing paid.
	 *
	 * @param array<string, mixed> $meta
	 */
	private function unpaid_deposit_booking(array $meta = array()): int
	{
		return self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_booking',
				'meta_input' => array_merge(
					array(
						'_mhmrentiva_vehicle_id'       => $this->vehicle_id,
						'_mhmrentiva_payment_type'     => 'deposit',
						'_mhmrentiva_payment_method'   => 'offline',
						'_mhmrentiva_payment_gateway'  => 'offline',
						'_mhmrentiva_payment_status'   => 'pending',
						'_mhmrentiva_deposit_type'     => 'percentage',
						'_mhmrentiva_start_time'       => '10:00',
						'_mhmrentiva_end_time'         => '10:00',
						'_mhmrentiva_total_price'      => '3000.00',
						'_mhmrentiva_deposit_amount'   => '300',
						'_mhmrentiva_remaining_amount' => '2700',
					),
					$meta
				),
			)
		);
	}

	private function meta(int $booking_id, string $key): float
	{
		return (float) get_post_meta($booking_id, $key, true);
	}

	public function test_an_unpaid_percentage_deposit_follows_the_new_total_at_the_bookings_own_rate(): void
	{
		$booking_id = $this->unpaid_deposit_booking();

		// Mon -> Wed: two days.
		BookingMeta::recalculate_booking_costs($booking_id, '2026-11-02', '2026-11-04');

		$this->assertSame(2000.0, $this->meta($booking_id, '_mhmrentiva_total_price'));
		$this->assertSame(200.0, $this->meta($booking_id, '_mhmrentiva_deposit_amount'), "10% of the new total -- the booking's rate, not the site's 20%.");
		$this->assertSame(1800.0, $this->meta($booking_id, '_mhmrentiva_remaining_amount'));
	}

	public function test_the_bookings_rate_survives_cents(): void
	{
		// #11015's numbers: 2745 at 10% = 274.50, re-priced to 2575.
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '858.33');
		$booking_id = $this->unpaid_deposit_booking(
			array(
				'_mhmrentiva_total_price'      => '2745.00',
				'_mhmrentiva_deposit_amount'   => '274.5',
				'_mhmrentiva_remaining_amount' => '2470.5',
			)
		);

		BookingMeta::recalculate_booking_costs($booking_id, '2026-11-02', '2026-11-05');

		$total = $this->meta($booking_id, '_mhmrentiva_total_price');
		$this->assertSame(round($total * 0.1, 2), $this->meta($booking_id, '_mhmrentiva_deposit_amount'));
		$this->assertSame(round($total - round($total * 0.1, 2), 2), $this->meta($booking_id, '_mhmrentiva_remaining_amount'));
	}

	public function test_a_fixed_deposit_keeps_its_amount(): void
	{
		$booking_id = $this->unpaid_deposit_booking(array( '_mhmrentiva_deposit_type' => 'fixed' ));

		BookingMeta::recalculate_booking_costs($booking_id, '2026-11-02', '2026-11-04');

		$this->assertSame(300.0, $this->meta($booking_id, '_mhmrentiva_deposit_amount'));
		$this->assertSame(1700.0, $this->meta($booking_id, '_mhmrentiva_remaining_amount'));
	}

	public function test_a_booking_with_an_order_keeps_the_deposit_the_order_asks_for(): void
	{
		$booking_id = $this->unpaid_deposit_booking(array( '_mhmrentiva_woocommerce_order_id' => 987654 ));

		BookingMeta::recalculate_booking_costs($booking_id, '2026-11-02', '2026-11-04');

		$this->assertSame(300.0, $this->meta($booking_id, '_mhmrentiva_deposit_amount'));
		$this->assertSame(1700.0, $this->meta($booking_id, '_mhmrentiva_remaining_amount'));
	}
}
