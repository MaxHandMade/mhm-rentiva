<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Pricing;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Booking\Pricing\RentalPricing;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * PR 0 invariant: what the booking form shows is what the checkout charges.
 *
 * The form quotes DepositCalculator's amount through PriceDisplay::for_cart()
 * (WooCommerce's 'cart' tax display); the submit path puts the same
 * DepositCalculator amount in the cart. With the same input and the same tax
 * location (store base), the two numbers must agree -- for a full payment
 * and for a deposit, with one or two tax rates, tax-exclusive or -inclusive.
 *
 * Fixture: 1000/day, Monday -> Thursday in July, summer multiplier 1.3 on
 * = 3 x 1300 = 3900 in the store's price-entry convention.
 *
 * @covers \MHMRentiva\Admin\Booking\Pricing\PriceDisplay
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::calculate_booking_deposit
 */
final class QuoteEqualsCheckoutTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	private int $vehicle_id;

	private int $start_ts;

	/** @var int[] */
	private array $rate_ids = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Fiat Egea',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');

		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize(array(
			'current_active_tab'                    => 'vehicle',
			'mhmrentiva_vehicle_base_price'         => '1.0',
			'mhmrentiva_vehicle_weekend_multiplier' => '1.2',
			'mhmrentiva_vehicle_seasonal_pricing'   => '1',
			'vehicle_pricing'                       => array(
				'seasonal_multipliers' => array(
					'summer' => array( 'multiplier' => '1.3' ),
				),
			),
		)));
		$this->set_deposit_rate(10);

		$this->start_ts = (int) strtotime('2026-07-13 07:00:00 UTC');

		$this->ensure_booking_product('1');

		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_prices_include_tax', 'no');
		$this->sandbox_option('woocommerce_tax_based_on', 'base');
		$this->sandbox_option('woocommerce_default_customer_address', 'base');
		$this->sandbox_option('woocommerce_tax_display_cart', 'incl');
		$this->sandbox_option('woocommerce_tax_display_shop', 'excl');
		$this->sandbox_option('woocommerce_price_display_suffix', '');

		$this->rate_ids[] = $this->insert_rate('20.0000', 1);

		wc_load_cart();
		WC()->cart->empty_cart();
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		foreach ($this->rate_ids as $rate_id) {
			\WC_Tax::_delete_tax_rate($rate_id);
		}
		delete_option('mhmrentiva_settings');
		$this->restore_sandboxed_options();
		parent::tearDown();
	}

	private function set_deposit_rate(int $rate): void
	{
		$settings                                   = get_option('mhmrentiva_settings', array());
		$settings['mhmrentiva_rental_deposit_type'] = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate'] = $rate;
		update_option('mhmrentiva_settings', $settings);
	}

	private function insert_rate(string $rate, int $priority): int
	{
		return (int) \WC_Tax::_insert_tax_rate(array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => $rate,
			'tax_rate_name'     => 'VAT ' . $priority,
			'tax_rate_priority' => $priority,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => $priority,
			'tax_rate_class'    => '',
		));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function quote(string $payment_type): array
	{
		return DepositCalculator::calculate_booking_deposit($this->vehicle_id, 3, $payment_type, array(), $this->start_ts);
	}

	/**
	 * The submit path: the amount due now goes into the cart.
	 *
	 * @param array<string, mixed> $quote
	 */
	private function checkout_total(array $quote): float
	{
		$added = WooCommerceBridge::add_booking_data_to_cart(
			array(
				'vehicle_id'   => $this->vehicle_id,
				'pickup_date'  => '2026-07-13',
				'pickup_time'  => '10:00',
				'dropoff_date' => '2026-07-16',
				'dropoff_time' => '10:00',
				'payment_type' => $quote['payment_type'],
				'total_price'  => $quote['total_amount'],
			),
			(float) $quote['deposit_amount']
		);
		$this->assertTrue($added, 'The booking could not be added to the cart.');

		WC()->cart->calculate_totals();

		return (float) WC()->cart->get_total('edit');
	}

	public function test_full_payment_quote_equals_checkout_total(): void
	{
		$quote = $this->quote('full');

		$this->assertEqualsWithDelta(3900.0, (float) $quote['total_amount'], 0.001, 'Season is charged, not only shown.');
		$shown = PriceDisplay::for_cart((float) $quote['total_amount'])['amount'];

		$this->assertEqualsWithDelta(4680.0, $shown, 0.01);
		$this->assertEqualsWithDelta($shown, $this->checkout_total($quote), 0.01);
	}

	public function test_full_payment_with_two_rates(): void
	{
		$this->rate_ids[] = $this->insert_rate('5.0000', 2);

		$quote = $this->quote('full');
		$shown = PriceDisplay::for_cart((float) $quote['total_amount'])['amount'];

		$this->assertEqualsWithDelta(4875.0, $shown, 0.01, 'Both rates apply, not only the first.');
		$this->assertEqualsWithDelta($shown, $this->checkout_total($quote), 0.01);
	}

	public function test_deposit_quote_equals_checkout_deposit(): void
	{
		$quote = $this->quote('deposit');

		$this->assertSame('deposit', $quote['payment_type']);
		$this->assertEqualsWithDelta(390.0, (float) $quote['deposit_amount'], 0.001);

		$shown = PriceDisplay::for_cart((float) $quote['deposit_amount'])['amount'];

		$this->assertEqualsWithDelta(468.0, $shown, 0.01);
		$this->assertEqualsWithDelta($shown, $this->checkout_total($quote), 0.01);
	}

	public function test_tax_inclusive_store_total_unchanged(): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', 'yes');

		$quote = $this->quote('full');
		$shown = PriceDisplay::for_cart((float) $quote['total_amount'])['amount'];

		$this->assertEqualsWithDelta(3900.0, $shown, 0.01);
		$this->assertEqualsWithDelta(3900.0, $this->checkout_total($quote), 0.01);
	}

	public function test_exclusive_cart_display_matches_cart_subtotal_and_says_so(): void
	{
		$this->sandbox_option('woocommerce_tax_display_cart', 'excl');

		$quote   = $this->quote('full');
		$display = PriceDisplay::for_cart((float) $quote['total_amount']);

		$this->checkout_total($quote);

		$this->assertEqualsWithDelta((float) WC()->cart->get_subtotal(), $display['amount'], 0.01);
		$this->assertStringContainsString('tax_label', $display['suffix'], 'A tax-exclusive figure must be labelled as such.');
	}

	public function test_quote_uses_cart_display_context_not_shop(): void
	{
		$this->assertEqualsWithDelta(3900.0, PriceDisplay::for_shop(3900.0)['amount'], 0.01);
		$this->assertEqualsWithDelta(4680.0, PriceDisplay::for_cart(3900.0)['amount'], 0.01);
	}

	public function test_stored_total_equals_full_amount(): void
	{
		$quote = $this->quote('deposit');
		$full  = RentalPricing::quote_for_days($this->vehicle_id, 3, $this->start_ts)->full_amount;

		$this->assertEqualsWithDelta($full, (float) $quote['total_amount'], 0.001);
		$this->assertEqualsWithDelta($full, Util::total_price($this->vehicle_id, 3, $this->start_ts), 0.001);
	}

	public function test_legacy_rentiva_tax_settings_are_ignored(): void
	{
		$this->sandbox_option('woocommerce_calc_taxes', 'no');
		$settings                                     = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_vehicle_tax_rate']      = 18;
		$settings['mhmrentiva_vehicle_tax_inclusive'] = '0';
		update_option('mhmrentiva_settings', $settings);

		$quote = $this->quote('full');

		$this->assertEqualsWithDelta(3900.0, PriceDisplay::for_cart((float) $quote['total_amount'])['amount'], 0.01);
		$this->assertEqualsWithDelta(3900.0, $this->checkout_total($quote), 0.01);
	}
}
