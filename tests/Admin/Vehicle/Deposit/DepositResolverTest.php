<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Deposit;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use WP_UnitTestCase;

/**
 * One deposit rate per vehicle and flow.
 *
 * Before this resolver the rental deposit came from three places that did not
 * agree: DepositCalculator turned an empty or '0' vehicle field into a
 * hard-coded 10%, the booking form forced full payment for the same empty
 * field, and there was no site-wide rental deposit setting at all. The model
 * the product owner approved (2026-10-20):
 *
 * - the vehicle's own `_mhmrentiva_deposit` (0 < x <= 100) overrides, in both
 *   the rental and the transfer flow;
 * - otherwise the flow's global setting applies (rental: the new Payment tab
 *   fields; transfer: `mhmrentiva_transfer_deposit_*`);
 * - "Full payment" (or rate 0) means no deposit: the whole total is charged.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator
 */
final class DepositResolverTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function settings(array $settings): void
	{
		update_option('mhmrentiva_settings', $settings);
	}

	private function vehicle_rate(string $value): void
	{
		update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_DEPOSIT, $value);
	}

	// ------------------------------------------------------------ resolver

	public function test_empty_vehicle_field_uses_the_rental_default_of_ten_percent(): void
	{
		$this->assertSame(10.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_empty_vehicle_field_follows_the_global_rental_rate(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$this->assertSame(30.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_global_full_payment_resolves_to_zero(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'full_payment',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$this->assertSame(0.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_vehicle_rate_overrides_global_full_payment(): void
	{
		$this->settings(array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));
		$this->vehicle_rate('15');

		$this->assertSame(15.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_vehicle_rate_overrides_global_percentage(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);
		$this->vehicle_rate('15');

		$this->assertSame(15.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function not_an_override(): array
	{
		return array(
			'empty'        => array( '' ),
			'zero'         => array( '0' ),
			'non-numeric'  => array( 'abc' ),
			'negative'     => array( '-5' ),
			'above 100'    => array( '150' ),
		);
	}

	/**
	 * @dataProvider not_an_override
	 */
	public function test_a_value_outside_one_to_hundred_is_not_an_override(string $stored): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);
		$this->vehicle_rate($stored);

		$this->assertSame(30.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_a_fractional_vehicle_rate_is_kept(): void
	{
		$this->vehicle_rate('12.5');

		$this->assertSame(12.5, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_global_rate_above_hundred_is_clamped(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 150,
			)
		);

		$this->assertSame(100.0, DepositCalculator::resolve_rate($this->vehicle_id));
	}

	public function test_transfer_context_defaults_to_full_payment(): void
	{
		$this->assertSame(0.0, DepositCalculator::resolve_rate($this->vehicle_id, 'transfer'));
	}

	public function test_transfer_context_reads_the_transfer_settings_not_the_rental_ones(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type'   => 'percentage',
				'mhmrentiva_rental_deposit_rate'   => 30,
				'mhmrentiva_transfer_deposit_type' => 'percentage',
				'mhmrentiva_transfer_deposit_rate' => 25,
			)
		);

		$this->assertSame(25.0, DepositCalculator::resolve_rate($this->vehicle_id, 'transfer'));
		$this->assertSame(30.0, DepositCalculator::resolve_rate($this->vehicle_id, 'rental'));
	}

	public function test_transfer_percentage_without_a_stored_rate_uses_twenty(): void
	{
		$this->settings(array( 'mhmrentiva_transfer_deposit_type' => 'percentage' ));

		$this->assertSame(20.0, DepositCalculator::resolve_rate($this->vehicle_id, 'transfer'));
	}

	public function test_vehicle_rate_overrides_the_transfer_setting_too(): void
	{
		$this->vehicle_rate('15');

		$this->assertSame(15.0, DepositCalculator::resolve_rate($this->vehicle_id, 'transfer'));
	}

	public function test_unknown_context_charges_in_full(): void
	{
		$this->assertSame(0.0, DepositCalculator::resolve_rate($this->vehicle_id, 'shuttle'));
	}

	// ------------------------------------------- readers through the resolver

	public function test_vehicle_deposit_value_reports_the_resolved_rate(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$this->assertSame('30', DepositCalculator::get_vehicle_deposit_value($this->vehicle_id));
	}

	public function test_vehicle_deposit_value_is_empty_when_full_payment_applies(): void
	{
		$this->settings(array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));
		$this->vehicle_rate('0');

		$this->assertSame('', DepositCalculator::get_vehicle_deposit_value($this->vehicle_id));
	}

	public function test_vehicle_deposit_follows_the_global_rate(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$result = DepositCalculator::calculate_vehicle_deposit($this->vehicle_id, 2);

		$this->assertEqualsWithDelta(600.0, (float) $result['deposit_amount'], 0.001);
	}

	public function test_vehicle_deposit_is_zero_under_full_payment(): void
	{
		$this->settings(array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));

		$result = DepositCalculator::calculate_vehicle_deposit($this->vehicle_id, 2);

		$this->assertEqualsWithDelta(0.0, (float) $result['deposit_amount'], 0.001);
	}

	public function test_booking_deposit_uses_the_global_rate(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$result = DepositCalculator::calculate_booking_deposit($this->vehicle_id, 2, 'deposit');

		$this->assertSame('deposit', $result['payment_type']);
		$this->assertEqualsWithDelta(600.0, (float) $result['deposit_amount'], 0.001);
		$this->assertEqualsWithDelta(1400.0, (float) $result['remaining_amount'], 0.001);
	}

	public function test_booking_deposit_with_rate_zero_is_charged_in_full(): void
	{
		$this->settings(array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));

		$result = DepositCalculator::calculate_booking_deposit($this->vehicle_id, 2, 'deposit');

		$this->assertSame('full', $result['payment_type'], 'A 0-rate "deposit" booking must never charge 0.');
		$this->assertEqualsWithDelta(2000.0, (float) $result['deposit_amount'], 0.001);
		$this->assertEqualsWithDelta(0.0, (float) $result['remaining_amount'], 0.001);
	}

	public function test_booking_deposit_keeps_full_payment_when_a_rate_applies(): void
	{
		$this->vehicle_rate('15');

		$result = DepositCalculator::calculate_booking_deposit($this->vehicle_id, 2, 'full');

		$this->assertSame('full', $result['payment_type']);
		$this->assertEqualsWithDelta(2000.0, (float) $result['deposit_amount'], 0.001);
	}

	// ------------------------------------------------------ card / detail

	public function test_card_deposit_follows_the_global_rate_for_an_empty_field(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
				'mhmrentiva_vehicle_card_fields' => array(
					array(
						'type' => 'detail',
						'key'  => 'deposit',
					),
				),
			)
		);
		update_option('mhmrentiva_selected_details', array( 'deposit' ));
		$this->reset_field_map();

		$items = VehicleFeatureHelper::collect_items($this->vehicle_id);
		$this->reset_field_map();

		$texts = array_column($items, 'text', 'key');
		$this->assertArrayHasKey('deposit', $texts, 'An empty field under a 30% global setting still has a deposit to show.');
		$this->assertStringContainsString('300', $texts['deposit']);
	}

	public function test_card_hides_the_deposit_under_full_payment_even_with_a_stale_zero(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'full_payment',
				'mhmrentiva_vehicle_card_fields' => array(
					array(
						'type' => 'detail',
						'key'  => 'deposit',
					),
				),
			)
		);
		update_option('mhmrentiva_selected_details', array( 'deposit' ));
		$this->vehicle_rate('0');
		$this->reset_field_map();

		$items = VehicleFeatureHelper::collect_items($this->vehicle_id);
		$this->reset_field_map();

		$this->assertNotContains('deposit', array_column($items, 'key'));
	}

	public function test_comparison_row_shows_the_resolved_rate_for_an_empty_field(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
				'comparison_fields'              => array( 'details' => array( 'deposit' ) ),
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		$method = new \ReflectionMethod(\MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison::class, 'get_vehicle_data');
		$method->setAccessible(true);
		$data = $method->invoke(null, $this->vehicle_id);

		$this->assertIsArray($data);
		$this->assertSame('30', $data['features']['deposit'] ?? null, 'The comparison table read the raw field and showed nothing for an empty one.');
	}

	private function reset_field_map(): void
	{
		$ref = new \ReflectionProperty(VehicleFeatureHelper::class, 'fields_map_cache');
		$ref->setAccessible(true);
		$ref->setValue(null, null);
	}
}
