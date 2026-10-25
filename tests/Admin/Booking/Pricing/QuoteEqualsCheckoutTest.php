<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Pricing;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
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

	/**
	 * Cent-level parity with WC_Cart_Totals across the settings that change
	 * its rounding. 1002.53 inclusive at 20% + 5% with line rounding is the
	 * case wc_get_price_to_display() gets a cent wrong (1002.53 vs 1002.52).
	 *
	 * @return array<string, array{0: float, 1: string, 2: bool, 3: string}>
	 */
	public static function rounding_matrix(): array
	{
		$rows = array();
		foreach (array( 1002.53, 99.99, 1234.57, 3900.0 ) as $amount) {
			foreach (array( 'yes', 'no' ) as $includes) {
				foreach (array( false, true ) as $two_rates) {
					foreach (array( 'no', 'yes' ) as $round_at_subtotal) {
						$rows[ sprintf('%s incl=%s two=%d sub=%s', $amount, $includes, (int) $two_rates, $round_at_subtotal) ] = array( $amount, $includes, $two_rates, $round_at_subtotal );
					}
				}
			}
		}
		return $rows;
	}

	/**
	 * @dataProvider rounding_matrix
	 */
	public function test_cart_display_equals_checkout_to_the_cent(float $amount, string $includes, bool $two_rates, string $round_at_subtotal): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', $includes);
		$this->sandbox_option('woocommerce_tax_round_at_subtotal', $round_at_subtotal);
		if ($two_rates) {
			$this->rate_ids[] = $this->insert_rate('5.0000', 2);
		}

		$shown = PriceDisplay::for_cart($amount)['amount'];

		$this->assertEqualsWithDelta(
			$this->checkout_total(array(
				'payment_type'   => 'full',
				'total_amount'   => $amount,
				'deposit_amount' => $amount,
			)),
			$shown,
			0.0001
		);
	}

	/**
	 * Store base TR 20%, customer in DE 19%, prices entered with tax: WooCommerce
	 * re-bases the price for the customer's rate (non-base location adjustment).
	 */
	public function test_non_base_location_matches_checkout(): void
	{
		foreach ($this->rate_ids as $rate_id) {
			\WC_Tax::_delete_tax_rate($rate_id);
		}
		$this->rate_ids   = array();
		$this->rate_ids[] = $this->insert_country_rate('TR', '20.0000');
		$this->rate_ids[] = $this->insert_country_rate('DE', '19.0000');
		$this->sandbox_option('woocommerce_default_country', 'TR');
		$this->sandbox_option('woocommerce_prices_include_tax', 'yes');
		$this->sandbox_option('woocommerce_tax_based_on', 'billing');
		WC()->customer->set_billing_country('DE');
		WC()->customer->set_shipping_country('DE');

		try {
			$shown    = PriceDisplay::for_cart(1002.53)['amount'];
			$checkout = $this->checkout_total(array( 'payment_type' => 'full', 'total_amount' => 1002.53, 'deposit_amount' => 1002.53 ));
		} finally {
			WC()->customer->set_billing_country('TR');
			WC()->customer->set_shipping_country('TR');
		}

		$this->assertNotEqualsWithDelta(1002.53, $checkout, 0.001, 'Sanity: the adjustment must change the price in this fixture.');
		$this->assertEqualsWithDelta($checkout, $shown, 0.0001);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function entry_conventions(): array
	{
		return array(
			'prices with tax'    => array( 'yes' ),
			'prices without tax' => array( 'no' ),
		);
	}

	/**
	 * @dataProvider entry_conventions
	 */
	public function test_vat_exempt_customer_matches_checkout(string $includes): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', $includes);
		WC()->customer->set_is_vat_exempt(true);

		try {
			$shown    = PriceDisplay::for_cart(1002.53)['amount'];
			$checkout = $this->checkout_total(array( 'payment_type' => 'full', 'total_amount' => 1002.53, 'deposit_amount' => 1002.53 ));
		} finally {
			WC()->customer->set_is_vat_exempt(false);
		}

		$this->assertEqualsWithDelta($checkout, $shown, 0.0001);
	}

	public function test_suffix_keeps_wc_price_bdi_markup(): void
	{
		$suffix = static fn() => ' <small class="woocommerce-price-suffix"><bdi>1.200,00</bdi></small>';
		add_filter('woocommerce_get_price_suffix', $suffix);

		$out = PriceDisplay::for_cart(1000.0)['suffix'];

		remove_filter('woocommerce_get_price_suffix', $suffix);

		$this->assertStringContainsString('<bdi>1.200,00</bdi>', $out);
	}

	public function test_exclusive_deposit_carries_the_tax_label(): void
	{
		$this->sandbox_option('woocommerce_tax_display_cart', 'excl');

		$this->assertStringContainsString('tax_label', PriceDisplay::for_cart(390.0)['tax_label']);

		$this->sandbox_option('woocommerce_tax_display_cart', 'incl');
		$this->assertSame('', PriceDisplay::for_cart(390.0)['tax_label']);
	}

	private function insert_country_rate(string $country, string $rate): int
	{
		return (int) \WC_Tax::_insert_tax_rate(array(
			'tax_rate_country'  => $country,
			'tax_rate_state'    => '',
			'tax_rate'          => $rate,
			'tax_rate_name'     => 'VAT ' . $country,
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => '',
		));
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

	public function test_suffix_from_filters_is_sanitised(): void
	{
		$this->sandbox_option('woocommerce_tax_display_cart', 'excl');
		$label  = static fn() => '<img src=x onerror="alert(1)">VAT';
		$suffix = static fn() => '<small>incl.</small><script>alert(2)</script>';
		add_filter('woocommerce_countries_ex_tax_or_vat', $label);
		add_filter('woocommerce_get_price_suffix', $suffix);

		$out = PriceDisplay::for_cart(100.0)['suffix'];

		remove_filter('woocommerce_countries_ex_tax_or_vat', $label);
		remove_filter('woocommerce_get_price_suffix', $suffix);

		$this->assertStringNotContainsString('onerror', $out);
		$this->assertStringNotContainsString('<script', $out);
		$this->assertStringContainsString('<small>incl.</small>', $out);
	}

	public function test_quote_uses_cart_display_context_not_shop(): void
	{
		$this->assertEqualsWithDelta(3900.0, PriceDisplay::for_shop(3900.0)['amount'], 0.01);
		$this->assertEqualsWithDelta(4680.0, PriceDisplay::for_cart(3900.0)['amount'], 0.01);
	}

	public function test_stored_total_equals_full_amount(): void
	{
		// Re-pricing a booking from wp-admin writes the stored total from the
		// same computation the form quoted: season included.
		$booking_id = self::factory()->post->create(array(
			'post_type'  => 'mhmrentiva_booking',
			'meta_input' => array(
				'_mhmrentiva_vehicle_id'   => $this->vehicle_id,
				'_mhmrentiva_payment_type' => 'deposit',
				'_mhmrentiva_start_time'   => '10:00',
				'_mhmrentiva_end_time'     => '10:00',
			),
		));

		BookingMeta::recalculate_booking_costs($booking_id, '2026-07-13', '2026-07-16');

		$full = RentalPricing::quote_for_days($this->vehicle_id, 3, $this->start_ts)->full_amount;
		$this->assertEqualsWithDelta(3900.0, $full, 0.001);
		$this->assertEqualsWithDelta($full, (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true), 0.001);
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
