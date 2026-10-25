<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Pricing;

use MHMRentiva\Admin\Booking\Pricing\RentalPricing;
use MHMRentiva\Admin\Booking\Pricing\RentalQuote;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * PR 0 -- the one rental pricing computation.
 *
 * 2026-07-13 is a Monday; 2026-07-18 a Saturday. Both fall in the default
 * summer season (June-August).
 *
 * @covers \MHMRentiva\Admin\Booking\Pricing\RentalPricing
 */
final class RentalPricingTest extends WP_UnitTestCase
{
	private const MONDAY   = '2026-07-13 07:00:00';
	private const THURSDAY = '2026-07-16 07:00:00';

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		$this->settings();
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function settings(array $overrides = array()): void
	{
		$input = array_merge(
			array(
				'current_active_tab'                    => 'vehicle',
				'mhmrentiva_vehicle_base_price'         => '1.0',
				'mhmrentiva_vehicle_weekend_multiplier' => '1.2',
				'vehicle_pricing'                       => array(
					'seasonal_multipliers' => array(
						'summer' => array( 'multiplier' => '1.3' ),
					),
				),
			),
			$overrides
		);
		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize($input));
	}

	private static function ts(string $utc): int
	{
		return (int) strtotime($utc . ' UTC');
	}

	private function addon(string $price, string $type): int
	{
		$id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_addon',
			'post_status' => 'publish',
		));
		update_post_meta($id, 'mhmrentiva_addon_price', $price);
		update_post_meta($id, '_mhmrentiva_addon_pricing_type', $type);
		return $id;
	}

	public function test_weekday_rental_is_daily_times_days(): void
	{
		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY));

		$this->assertSame(3, $quote->days);
		$this->assertEqualsWithDelta(3000.0, $quote->rental_amount, 0.001);
		$this->assertSame(0, $quote->weekend_days);
	}

	public function test_weekend_day_uses_multiplier_after_season(): void
	{
		$this->settings(array( 'mhmrentiva_vehicle_seasonal_pricing' => '1' ));

		// Friday -> Sunday: Friday 1300, Saturday 1300 x 1.2 = 1560.
		$quote = RentalPricing::quote_for_days($this->vehicle_id, 2, self::ts('2026-07-17 07:00:00'));

		$this->assertEqualsWithDelta(2860.0, $quote->rental_amount, 0.001);
		$this->assertSame(1, $quote->weekend_days);
		$this->assertEqualsWithDelta(260.0, $quote->weekend_surcharge, 0.001);
		$this->assertSame('2026-07-18', $quote->day_lines[1]['date']);
	}

	public function test_base_multiplier_applies_when_positive(): void
	{
		$this->settings(array( 'mhmrentiva_vehicle_base_price' => '1.5' ));

		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY));

		$this->assertEqualsWithDelta(4500.0, $quote->rental_amount, 0.001);
		$this->assertEqualsWithDelta(1500.0, $quote->daily_price, 0.001);
	}

	public function test_non_positive_stored_base_multiplier_is_ignored(): void
	{
		// The sanitizer clamps to >= 0.1; a raw 0 can only come from an older
		// stored option. It must not zero every price.
		$settings                                  = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_vehicle_base_price'] = 0;
		update_option('mhmrentiva_settings', $settings);

		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY));

		$this->assertEqualsWithDelta(3000.0, $quote->rental_amount, 0.001);
	}

	public function test_season_off_ignores_season_multiplier(): void
	{
		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY));

		$this->assertEqualsWithDelta(3000.0, $quote->rental_amount, 0.001);
	}

	public function test_season_on_applies_season_multiplier(): void
	{
		$this->settings(array( 'mhmrentiva_vehicle_seasonal_pricing' => '1' ));

		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY));

		$this->assertEqualsWithDelta(3900.0, $quote->rental_amount, 0.001);
	}

	public function test_no_start_date_charges_every_day_at_base_rate(): void
	{
		$this->settings(array( 'mhmrentiva_vehicle_seasonal_pricing' => '1' ));

		$quote = RentalPricing::quote_for_days($this->vehicle_id, 3, 0);

		$this->assertEqualsWithDelta(3000.0, $quote->rental_amount, 0.001);
		$this->assertSame(0, $quote->weekend_days);
	}

	public function test_addons_and_full_amount(): void
	{
		$per_day     = $this->addon('50', 'per_day');
		$per_booking = $this->addon('120', 'per_booking');

		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY), array( $per_day, (string) $per_booking ));

		$this->assertEqualsWithDelta(270.0, $quote->addon_total, 0.001);
		$this->assertEqualsWithDelta(3270.0, $quote->full_amount, 0.001);
	}

	public function test_non_positive_price_yields_zero_rental(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '-50');

		$quote = RentalPricing::quote($this->vehicle_id, self::ts(self::MONDAY), self::ts(self::THURSDAY));

		$this->assertSame(0.0, $quote->rental_amount);
	}

	public function test_quote_carries_no_tax_field(): void
	{
		$this->assertFalse(property_exists(RentalQuote::class, 'tax'));
		$this->assertFalse(property_exists(RentalQuote::class, 'tax_amount'));
	}
}
