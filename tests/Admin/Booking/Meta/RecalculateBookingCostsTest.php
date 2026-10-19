<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Saving a booking in wp-admin re-prices it through recalculate_booking_costs().
 * That method kept its own arithmetic instead of the calculator the booking form
 * and the manual booking screen use, and read two keys nothing writes:
 * `_mhmrentiva_additional_services_price` (the real one is `_mhmrentiva_addon_total`)
 * and `_mhmrentiva_deposit_percentage` (the real source is the vehicle's
 * `_mhmrentiva_deposit`). So an admin save dropped the add-ons from the total,
 * charged a hard-coded 20% deposit, ignored the weekend multiplier and per-day
 * add-on pricing, and re-priced transfer bookings at the vehicle's daily rate.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs
 */
final class RecalculateBookingCostsTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		update_post_meta( $this->vehicle_id, '_mhmrentiva_price_per_day', '1000' );
	}

	private function addon( string $price, string $pricing_type ): int
	{
		$addon_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_addon' ) );
		update_post_meta( $addon_id, 'mhmrentiva_addon_price', $price );
		update_post_meta( $addon_id, '_mhmrentiva_addon_pricing_type', $pricing_type );

		return $addon_id;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function booking( string $payment_type, array $meta = array() ): int
	{
		return self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_booking',
				'meta_input' => array_merge(
					array(
						'_mhmrentiva_vehicle_id'   => $this->vehicle_id,
						'_mhmrentiva_payment_type' => $payment_type,
						'_mhmrentiva_start_time'   => '10:00',
						'_mhmrentiva_end_time'     => '10:00',
					),
					$meta
				),
			)
		);
	}

	private function meta( int $booking_id, string $key ): float
	{
		return (float) get_post_meta( $booking_id, $key, true );
	}

	public function test_deposit_booking_keeps_its_addons_and_uses_the_vehicle_deposit(): void
	{
		update_post_meta( $this->vehicle_id, '_mhmrentiva_deposit', '10' );
		$addon_id   = $this->addon( '50', 'per_day' );
		$booking_id = $this->booking( 'deposit', array( '_mhmrentiva_selected_addons' => array( $addon_id ) ) );

		// Monday 10:00 -> Thursday 10:00: three weekdays, no weekend multiplier.
		BookingMeta::recalculate_booking_costs( $booking_id, '2026-11-02', '2026-11-05' );

		$this->assertSame( 3.0, $this->meta( $booking_id, '_mhmrentiva_rental_days' ) );
		$this->assertSame( 150.0, $this->meta( $booking_id, '_mhmrentiva_addon_total' ), 'A per-day add-on is charged once per rental day.' );
		$this->assertSame( 3150.0, $this->meta( $booking_id, '_mhmrentiva_total_price' ), 'The add-ons must stay in the total.' );
		$this->assertSame( 315.0, $this->meta( $booking_id, '_mhmrentiva_deposit_amount' ), "The deposit is the vehicle's 10%, not a hard-coded 20%." );
		$this->assertSame( 2835.0, $this->meta( $booking_id, '_mhmrentiva_remaining_amount' ) );
		$this->assertSame( 3150.0, $this->meta( $booking_id, '_mhmrentiva_booking_total_price' ), 'Legacy mirror follows the total.' );
	}

	public function test_vehicle_without_a_deposit_setting_falls_back_to_ten_percent(): void
	{
		$booking_id = $this->booking( 'deposit' );

		BookingMeta::recalculate_booking_costs( $booking_id, '2026-11-02', '2026-11-05' );

		$this->assertSame( 3000.0, $this->meta( $booking_id, '_mhmrentiva_total_price' ) );
		$this->assertSame( 300.0, $this->meta( $booking_id, '_mhmrentiva_deposit_amount' ) );
		$this->assertSame( 2700.0, $this->meta( $booking_id, '_mhmrentiva_remaining_amount' ) );
	}

	public function test_weekend_days_are_priced_like_the_booking_form_prices_them(): void
	{
		$booking_id = $this->booking( 'deposit' );

		// Friday 10:00 -> Monday 10:00 spans the weekend.
		BookingMeta::recalculate_booking_costs( $booking_id, '2026-11-06', '2026-11-09' );

		$parsed   = Util::parse_datetimes( '2026-11-06', '10:00', '2026-11-09', '10:00' );
		$expected = Util::total_price( $this->vehicle_id, 3, $parsed['start_ts'] );

		$this->assertGreaterThan( 3000.0, $expected, 'Fixture guard: the weekend multiplier must be in effect.' );
		$this->assertSame( round( $expected, 2 ), $this->meta( $booking_id, '_mhmrentiva_total_price' ) );
	}

	public function test_full_payment_booking_owes_nothing_after_the_save(): void
	{
		$addon_id   = $this->addon( '25', 'per_booking' );
		$booking_id = $this->booking(
			'full',
			array(
				'_mhmrentiva_selected_addons' => array( $addon_id ),
				'_mhmrentiva_deposit_amount'  => '999',
			)
		);

		BookingMeta::recalculate_booking_costs( $booking_id, '2026-11-02', '2026-11-05' );

		$this->assertSame( 3025.0, $this->meta( $booking_id, '_mhmrentiva_total_price' ) );
		$this->assertSame( 3025.0, $this->meta( $booking_id, '_mhmrentiva_deposit_amount' ), 'A full payment is the whole total.' );
		$this->assertSame( 0.0, $this->meta( $booking_id, '_mhmrentiva_remaining_amount' ) );
	}

	public function test_transfer_booking_is_not_repriced_as_a_rental(): void
	{
		$booking_id = $this->booking(
			'full',
			array(
				'_mhmrentiva_transfer_origin_id' => 7,
				'_mhmrentiva_total_price'        => '1025',
				'_mhmrentiva_deposit_amount'     => '1025',
			)
		);

		BookingMeta::recalculate_booking_costs( $booking_id, '2026-11-02', '2026-11-02' );

		$this->assertSame( 1025.0, $this->meta( $booking_id, '_mhmrentiva_total_price' ), 'A transfer is priced by route, not by the daily rate.' );
		$this->assertSame( 1025.0, $this->meta( $booking_id, '_mhmrentiva_deposit_amount' ) );
	}

	public function test_unparseable_dates_leave_the_booking_untouched(): void
	{
		$booking_id = $this->booking( 'deposit', array( '_mhmrentiva_total_price' => '4242' ) );

		BookingMeta::recalculate_booking_costs( $booking_id, '2026-11-05', '2026-11-02' );

		$this->assertSame( 4242.0, $this->meta( $booking_id, '_mhmrentiva_total_price' ) );
	}
}
