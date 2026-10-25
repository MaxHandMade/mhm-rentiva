<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Addons;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Addons\AddonPricingCalculator;
use MHMRentiva\Admin\Addons\AddonSelection;
use MHMRentiva\Admin\Booking\Pricing\RentalPricing;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR G (K12): add-on quantities. One value object carries the
 * selection (ids + id => quantity); the quantity enters the price at one
 * place (AddonPricingCalculator's context), so the quote, the deposit and the
 * detail rows agree.
 *
 * @covers \MHMRentiva\Admin\Addons\AddonSelection
 * @covers \MHMRentiva\Admin\Addons\AddonPricingCalculator
 */
final class AddonSelectionTest extends WP_UnitTestCase
{
	private function addon(float $price, string $type = 'per_booking', int $max = 1, bool $required = false, string $title = 'Child seat'): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => $title ));
		update_post_meta($id, 'mhmrentiva_addon_price', (string) $price);
		update_post_meta($id, '_mhmrentiva_addon_pricing_type', $type);
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($id, 'mhmrentiva_addon_required', $required ? '1' : '0');
		if ($max > 1) {
			update_post_meta($id, MetaKeys::ADDON_MAX_QUANTITY, (string) $max);
		}
		return $id;
	}

	public function test_meta_keys_are_named(): void
	{
		$this->assertSame('_mhmrentiva_addon_quantities', MetaKeys::BOOKING_ADDON_QUANTITIES);
		$this->assertSame('mhmrentiva_addon_max_quantity', MetaKeys::ADDON_MAX_QUANTITY);
	}

	public function test_from_input_clamps_and_drops(): void
	{
		$seat     = $this->addon(10.0, 'per_booking', 3);
		$gps      = $this->addon(20.0, 'per_booking', 1, false, 'GPS');
		$required = $this->addon(5.0, 'per_booking', 2, true, 'Insurance');
		$unknown  = 999999;

		$selection = AddonSelection::from_input(
			array( $seat, $gps, $required, $unknown, $seat ),
			array( $seat => '5', $gps => '4', $required => '0', $unknown => '2' )
		);

		$this->assertSame(array( $seat, $gps, $required ), $selection->ids());
		$this->assertSame(3, $selection->quantity($seat), 'Clamped to the add-on\'s maximum.');
		$this->assertSame(1, $selection->quantity($gps), 'A maximum of 1 keeps the old checkbox meaning.');
		$this->assertSame(1, $selection->quantity($required), 'A selected required add-on never goes below 1.');
		$this->assertSame(0, $selection->quantity($unknown));
		$this->assertSame(array( $seat => 3 ), $selection->quantities());

		$dropped = AddonSelection::from_input(array( $seat ), array( $seat => '0' ));
		$this->assertSame(array(), $dropped->ids(), 'Quantity 0 removes an optional add-on.');

		$odd = AddonSelection::from_input(array( $seat, $gps ), array( $seat => 'abc', $gps => array( 3 ) ));
		$this->assertSame(1, $odd->quantity($seat));
		$this->assertSame(1, $odd->quantity($gps));
		$this->assertSame(array(), array_diff(array_keys($odd->quantities()), $odd->ids()));
	}

	public function test_for_booking_defaults_to_one_without_quantities_meta(): void
	{
		$seat    = $this->addon(10.0, 'per_booking', 3);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		update_post_meta($booking, '_mhmrentiva_selected_addons', array( $seat ));

		$selection = AddonSelection::for_booking($booking);
		$this->assertSame(array( $seat ), $selection->ids());
		$this->assertSame(1, $selection->quantity($seat));
	}

	public function test_store_round_trips_and_drops_ones(): void
	{
		$seat    = $this->addon(10.0, 'per_booking', 3);
		$gps     = $this->addon(20.0, 'per_booking', 1, false, 'GPS');
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

		AddonSelection::of(array( $seat, $gps ), array( $seat => 2 ))->store($booking);
		$this->assertSame(array( $seat, $gps ), get_post_meta($booking, '_mhmrentiva_selected_addons', true), 'The list stays a list (Pro reads it).');
		$this->assertSame(array( $seat => 2 ), get_post_meta($booking, MetaKeys::BOOKING_ADDON_QUANTITIES, true));
		$this->assertSame(2, AddonSelection::for_booking($booking)->quantity($seat));

		AddonSelection::of(array( $seat ))->store($booking);
		$this->assertFalse(metadata_exists('post', $booking, MetaKeys::BOOKING_ADDON_QUANTITIES), 'All ones: no quantities row.');
	}

	public function test_quantity_multiplies_every_pricing_type(): void
	{
		$booking   = $this->addon(100.0, 'per_booking', 3);
		$daily     = $this->addon(50.0, 'per_day', 3);
		$passenger = $this->addon(30.0, 'per_passenger', 3);

		$this->assertSame(200.0, AddonPricingCalculator::calculate($booking, array( 'rental_days' => 3, 'quantity' => 2 )));
		$this->assertSame(300.0, AddonPricingCalculator::calculate($daily, array( 'rental_days' => 3, 'quantity' => 2 )));
		$this->assertSame(60.0, AddonPricingCalculator::calculate($passenger, array( 'rental_days' => 3, 'quantity' => 2 )));
		$this->assertSame(100.0, AddonPricingCalculator::calculate($booking, array( 'rental_days' => 3 )), 'No quantity = 1.');
	}

	public function test_quote_with_quantities_equals_details_sum(): void
	{
		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', '1000');
		$seat  = $this->addon(10.0, 'per_day', 3);
		$fee   = $this->addon(25.0, 'per_booking', 1, false, 'Cleaning');
		$quote = RentalPricing::quote_for_days($vehicle, 3, 0, array( $seat, $fee ), array( $seat => 2 ));

		$this->assertSame(85.0, $quote->addon_total, '10 x 3 days x 2 + 25.');

		$details = AddonManager::build_addon_details(array( $seat, $fee ), array( 'rental_days' => 3, 'addon_quantities' => array( $seat => 2 ) ));
		$this->assertSame($quote->addon_total, (float) array_sum(array_column($details, 'line_total')));
		$this->assertSame(2, $details[0]['quantity']);
		$this->assertSame(1, $details[1]['quantity']);
		$this->assertSame('Child seat × 2', AddonManager::line_label($details[0]));
		$this->assertSame('Cleaning', AddonManager::line_label($details[1]));

		$this->assertSame(
			$quote->addon_total,
			RentalPricing::quote($vehicle, (int) strtotime('2027-07-12 10:00'), (int) strtotime('2027-07-15 10:00'), array( $seat, $fee ), array( $seat => 2 ))->addon_total
		);
	}

	public function test_deposit_follows_quantities(): void
	{
		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize(array( 'current_active_tab' => 'vehicle', 'mhmrentiva_vehicle_base_price' => '1.0', 'mhmrentiva_vehicle_weekend_multiplier' => '1.0' )));
		$settings                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_type'] = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate'] = 10;
		update_option('mhmrentiva_settings', $settings);

		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', '1000');
		$seat = $this->addon(100.0, 'per_booking', 3);

		$deposit = DepositCalculator::calculate_booking_deposit($vehicle, 3, 'deposit', array( $seat ), 0, array( $seat => 3 ));
		$this->assertSame(3300.0, (float) $deposit['total_amount']);
		$this->assertSame(330.0, (float) $deposit['deposit_amount']);

		delete_option('mhmrentiva_settings');
	}

	public function test_max_quantity_is_read_as_1_to_20(): void
	{
		$seat = $this->addon(10.0);
		$this->assertSame(1, AddonSelection::max_quantity($seat));
		update_post_meta($seat, MetaKeys::ADDON_MAX_QUANTITY, '7');
		$this->assertSame(7, AddonSelection::max_quantity($seat));
		update_post_meta($seat, MetaKeys::ADDON_MAX_QUANTITY, '99');
		$this->assertSame(20, AddonSelection::max_quantity($seat));
		update_post_meta($seat, MetaKeys::ADDON_MAX_QUANTITY, '-3');
		$this->assertSame(1, AddonSelection::max_quantity($seat));

		$fields = ( new \ReflectionMethod(\MHMRentiva\Admin\Addons\AddonMeta::class, 'get_fields') );
		$fields->setAccessible(true);
		$all = $fields->invoke(null);
		$this->assertArrayHasKey(MetaKeys::ADDON_MAX_QUANTITY, $all['addon_details']['fields']);
		$this->assertSame('20', (string) $all['addon_details']['fields'][ MetaKeys::ADDON_MAX_QUANTITY ]['max']);
	}
}
