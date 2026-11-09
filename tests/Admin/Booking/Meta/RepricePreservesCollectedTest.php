<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use WP_UnitTestCase;

/**
 * Re-pricing a booking that has collected money keeps what was collected; the
 * difference becomes the remaining amount (function scan H2, face 2; user
 * decision 2026-11-08 "fark kalan tutar olsun").
 *
 * The offline channel's paid amount is derived (PaymentState: total −
 * remaining). A full-payment booking re-priced to a higher total had its
 * remaining written as 0, so the whole new total read as paid (measured: 2,550
 * paid, re-priced to 2,890, "refundable 2,890"). Now, while money has been
 * collected, the total is computed in the calculator's full mode (its deposit
 * mode rounds to two decimals), the difference is owed, and a full-payment
 * booking that now owes something becomes a deposit booking -- deposit = what
 * was collected -- so the existing remaining-amount surfaces show and collect it
 * (user decision 2026-11-09).
 *
 * Plan: docs/superpowers/plans/2026-11-09-h2-yuz-2-fark-kalan.md (r7-r10).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs
 */
final class RepricePreservesCollectedTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', '10');
	}

	/**
	 * An offline booking whose money arrived: payment_status paid, so
	 * PaymentState derives paid = total − remaining.
	 *
	 * @param array<string, mixed> $meta
	 */
	private function paid_offline(string $payment_type, float $total, float $remaining, array $meta = array()): int
	{
		return self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_booking',
				'meta_input' => array_merge(
					array(
						'_mhmrentiva_vehicle_id'       => $this->vehicle_id,
						'_mhmrentiva_payment_type'     => $payment_type,
						'_mhmrentiva_payment_status'   => 'paid',
						'_mhmrentiva_start_time'       => '10:00',
						'_mhmrentiva_end_time'         => '10:00',
						'_mhmrentiva_total_price'      => $total,
						'_mhmrentiva_remaining_amount' => $remaining,
						'_mhmrentiva_deposit_amount'   => 'full' === $payment_type ? $total : 300,
					),
					$meta
				),
			)
		);
	}

	private function meta(int $booking_id, string $key): string
	{
		return (string) get_post_meta($booking_id, $key, true);
	}

	public function test_a_paid_full_booking_re_priced_higher_owes_the_difference(): void
	{
		// Monday-Wednesday (2 days, 2,000) paid in full, extended to Thursday (3 days, 3,000).
		$booking = $this->paid_offline('full', 2000.0, 0.0);
		$this->assertSame(Money::toMinor(2000), PaymentState::forBooking($booking)->paid(), 'PREMISE');

		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');

		$this->assertSame(3000.0, (float) $this->meta($booking, '_mhmrentiva_total_price'));
		$this->assertSame('deposit', $this->meta($booking, '_mhmrentiva_payment_type'), 'owes something now: a deposit booking');
		$this->assertSame(2000.0, (float) $this->meta($booking, '_mhmrentiva_deposit_amount'), 'deposit = what was collected');
		$this->assertSame(1000.0, (float) $this->meta($booking, '_mhmrentiva_remaining_amount'), 'the difference is owed');
		$this->assertSame(Money::toMinor(2000), PaymentState::forBooking($booking)->paid(), 'collected unchanged');
	}

	public function test_a_paid_deposit_booking_owes_the_difference_against_everything_collected(): void
	{
		// Deposit 300 and the rest (2,700) both collected: 3,000 paid in all.
		$booking = $this->paid_offline('deposit', 3000.0, 0.0);
		$this->assertSame(Money::toMinor(3000), PaymentState::forBooking($booking)->paid(), 'PREMISE');

		// 3 days -> 4 days: 4,000.
		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-06');

		$this->assertSame('deposit', $this->meta($booking, '_mhmrentiva_payment_type'));
		$this->assertSame(300.0, (float) $this->meta($booking, '_mhmrentiva_deposit_amount'), 'the deposit stays what the order charged');
		$this->assertSame(1000.0, (float) $this->meta($booking, '_mhmrentiva_remaining_amount'), 'not total − deposit (3,700): everything collected counts');
		$this->assertSame(Money::toMinor(3000), PaymentState::forBooking($booking)->paid());
	}

	public function test_an_unchanged_total_changes_nothing_about_the_payment(): void
	{
		$booking = $this->paid_offline('full', 3000.0, 0.0);

		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');

		$this->assertSame('full', $this->meta($booking, '_mhmrentiva_payment_type'), 'nothing owed: stays a full-payment booking');
		$this->assertSame(0.0, (float) $this->meta($booking, '_mhmrentiva_remaining_amount'));
		$this->assertSame(Money::toMinor(3000), PaymentState::forBooking($booking)->paid());
	}

	public function test_a_second_call_in_the_same_update_writes_the_same_values(): void
	{
		$booking = $this->paid_offline('full', 2000.0, 0.0);

		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');
		$first = array(
			$this->meta($booking, '_mhmrentiva_total_price'),
			$this->meta($booking, '_mhmrentiva_payment_type'),
			$this->meta($booking, '_mhmrentiva_deposit_amount'),
			$this->meta($booking, '_mhmrentiva_remaining_amount'),
		);
		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');
		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');

		$this->assertSame($first, array(
			$this->meta($booking, '_mhmrentiva_total_price'),
			$this->meta($booking, '_mhmrentiva_payment_type'),
			$this->meta($booking, '_mhmrentiva_deposit_amount'),
			$this->meta($booking, '_mhmrentiva_remaining_amount'),
		));
		$this->assertSame(Money::toMinor(2000), PaymentState::forBooking($booking)->paid());
	}

	public function test_a_three_decimal_store_keeps_the_total_stable_across_calls(): void
	{
		update_option('woocommerce_price_num_decimals', 3);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '963.367');
		// Two days paid: 1,926.734.
		$booking = $this->paid_offline('full', 1926.734, 0.0);
		$collected = PaymentState::forBooking($booking)->paid();
		$this->assertSame(1926734, $collected, 'PREMISE: three decimals');

		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');
		$total_first     = $this->meta($booking, '_mhmrentiva_total_price');
		$remaining_first = $this->meta($booking, '_mhmrentiva_remaining_amount');
		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-05');

		$this->assertSame($total_first, $this->meta($booking, '_mhmrentiva_total_price'), 'the full→deposit conversion must not shift the total');
		$this->assertSame($remaining_first, $this->meta($booking, '_mhmrentiva_remaining_amount'));
		$this->assertSame(Money::toMinor($total_first) - $collected, Money::toMinor($remaining_first));
		$this->assertSame($collected, PaymentState::forBooking($booking)->paid());
		delete_option('woocommerce_price_num_decimals');
	}
}
