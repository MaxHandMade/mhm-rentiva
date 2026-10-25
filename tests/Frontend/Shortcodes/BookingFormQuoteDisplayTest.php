<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * PR 0: the price quote is the charge, shown through WooCommerce.
 *
 * Same AJAX harness as BookingFormSeasonalMasterSwitchTest (see its notes on
 * the double-die quirk and the output-buffer parity).
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 */
final class BookingFormQuoteDisplayTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	/** @var int[] */
	private array $rate_ids = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		BookingForm::register();

		$this->vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
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

		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_prices_include_tax', 'no');
		$this->sandbox_option('woocommerce_tax_based_on', 'base');
		$this->sandbox_option('woocommerce_tax_display_cart', 'incl');
		$this->sandbox_option('woocommerce_tax_display_shop', 'excl');

		$this->rate_ids[] = (int) \WC_Tax::_insert_tax_rate(array(
			'tax_rate_country'  => '',
			'tax_rate'          => '20.0000',
			'tax_rate_name'     => 'VAT',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => '',
		));
	}

	public function tearDown(): void
	{
		foreach ($this->rate_ids as $rate_id) {
			\WC_Tax::_delete_tax_rate($rate_id);
		}
		delete_option('mhmrentiva_settings');
		$this->restore_sandboxed_options();
		parent::tearDown();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function calculate_price(): array
	{
		$_POST = array(
			'action'       => 'mhmrentiva_calculate_price',
			'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
			'vehicle_id'   => $this->vehicle_id,
			'pickup_date'  => '2026-07-13',
			'dropoff_date' => '2026-07-16',
			'pickup_time'  => '10:00',
			'dropoff_time' => '10:00',
			'addons'       => array(),
			'payment_type' => 'full',
		);

		$ob_level_before = ob_get_level();
		try {
			$this->_handleAjax('mhmrentiva_calculate_price');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected path for WP_Ajax_UnitTestCase.
		}
		while (ob_get_level() > $ob_level_before) {
			ob_end_clean();
		}
		while (ob_get_level() < $ob_level_before) {
			ob_start();
		}

		$raw            = (string) $this->_last_response;
		$second_blob_at = strpos($raw, '{"success"', 1);
		$json_text      = false !== $second_blob_at ? substr($raw, 0, $second_blob_at) : $raw;

		$response = json_decode($json_text, true);
		$this->assertIsArray($response, 'AJAX response must decode to an array: ' . $raw);
		$this->assertTrue($response['success'] ?? false, 'AJAX call must succeed: ' . $raw);

		return $response['data'];
	}

	public function test_quote_total_equals_deposit_calculator_total(): void
	{
		$this->ensure_booking_product('1');
		$charge = DepositCalculator::calculate_booking_deposit($this->vehicle_id, 3, 'full', array(), (int) strtotime('2026-07-13 07:00:00 UTC'));

		$data = $this->calculate_price();

		$this->assertEqualsWithDelta(3900.0, (float) $data['total_price'], 0.001);
		$this->assertEqualsWithDelta((float) $charge['total_amount'], (float) $data['total_price'], 0.001);
	}

	public function test_quote_uses_cart_display_context(): void
	{
		$this->ensure_booking_product('1');

		$data = $this->calculate_price();

		$this->assertEqualsWithDelta(4680.0, (float) $data['display_total']['amount'], 0.01);
		$this->assertArrayHasKey('display_deposit', $data);
		$this->assertArrayHasKey('display_remaining', $data);
		$this->assertArrayHasKey('price_suffix', $data);
	}

	public function test_breakdown_lines_add_up_to_the_displayed_total(): void
	{
		$this->ensure_booking_product('1');

		$data  = $this->calculate_price();
		$lines = $data['display_lines'];

		$this->assertEqualsWithDelta(1200.0, (float) $lines['vehicle_price']['amount'], 0.01, 'Daily price shown with tax (1000 x 1.2).');
		$this->assertEqualsWithDelta(
			(float) $data['display_total']['amount'],
			(float) $lines['vehicle_total']['amount'] + (float) $lines['addon_total']['amount'],
			0.01
		);
	}

	public function test_quote_payload_has_no_tax_keys(): void
	{
		$data = $this->calculate_price();

		foreach (array( 'tax_enabled', 'tax_inclusive', 'tax_rate', 'tax_rate_display', 'tax_amount', 'vehicle_subtotal' ) as $key) {
			$this->assertArrayNotHasKey($key, $data, "$key must be gone: tax is WooCommerce's job.");
		}
	}

	public function test_quote_does_not_create_booking_product(): void
	{
		$existing = (int) wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU);
		if ($existing > 0) {
			wp_delete_post($existing, true);
		}
		$this->assertSame(0, (int) wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU), 'Sanity: no booking product before the quote.');

		$data = $this->calculate_price();

		$this->assertSame(0, (int) wc_get_product_id_by_sku(WooCommerceBridge::PRODUCT_SKU), 'A quote must not write a product.');
		$this->assertEqualsWithDelta(4680.0, (float) $data['display_total']['amount'], 0.01, 'Without the product, tax is still shown: the first checkout creates a taxable product.');
	}

	public function test_non_positive_price_is_refused_like_the_submit_path(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '-50');

		$_POST = array(
			'action'       => 'mhmrentiva_calculate_price',
			'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
			'vehicle_id'   => $this->vehicle_id,
			'pickup_date'  => '2026-07-13',
			'dropoff_date' => '2026-07-16',
			'pickup_time'  => '10:00',
			'addons'       => array(),
			'payment_type' => 'full',
		);
		$ob_level_before = ob_get_level();
		try {
			$this->_handleAjax('mhmrentiva_calculate_price');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected.
		}
		while (ob_get_level() > $ob_level_before) {
			ob_end_clean();
		}
		while (ob_get_level() < $ob_level_before) {
			ob_start();
		}

		$raw      = (string) $this->_last_response;
		$cut      = strpos($raw, '{"success"', 1);
		$response = json_decode(false !== $cut ? substr($raw, 0, $cut) : $raw, true);

		$this->assertFalse($response['success'] ?? true, 'A vehicle the submit path cannot price must not be quoted: ' . $raw);
	}
}
