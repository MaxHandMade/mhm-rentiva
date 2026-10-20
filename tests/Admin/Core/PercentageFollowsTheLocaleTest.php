<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * A percentage is written through the catalogue, never as `rate . '%'`.
 *
 * The booking form's price summary read "Vergi (dahil) (20%)" on a Turkish
 * site, where the sign comes first ("%20"); the deposit hint beside a vehicle's
 * deposit field had the opposite defect, a hard-coded "%10" on every site.
 *
 * @covers \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::format_deposit_display
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 */
final class PercentageFollowsTheLocaleTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceOptionSandbox;

	/** @var string */
	protected $_last_response;

	/** The catalogue's Turkish translation of the format. */
	private const TR_FORMAT = '%%%s';

	public function tearDown(): void
	{
		remove_all_filters('gettext_with_context');
		delete_option('mhmrentiva_settings');
		$this->restore_sandboxed_options();
		parent::tearDown();
	}

	private function turkish(): void
	{
		add_filter(
			'gettext_with_context',
			static function ($translation, $text, $context, $domain) {
				return 'mhm-rentiva' === $domain && '%s%%' === $text && str_starts_with((string) $context, 'A percentage')
					? self::TR_FORMAT
					: $translation;
			},
			10,
			4
		);
	}

	public function test_english_puts_the_sign_after_the_number(): void
	{
		$this->assertSame('20%', CurrencyHelper::format_percentage(20.0));
	}

	public function test_turkish_puts_the_sign_before_the_number(): void
	{
		$this->turkish();

		$this->assertSame('%20', CurrencyHelper::format_percentage(20.0));
	}

	public function test_a_fraction_keeps_its_digits_in_the_stores_decimal_format(): void
	{
		$this->sandbox_option('woocommerce_price_decimal_sep', ',');

		$this->assertSame('12,5%', CurrencyHelper::format_percentage(12.5));
		$this->assertSame('8,25%', CurrencyHelper::format_percentage(8.25));
	}

	public function test_the_deposit_hint_follows_the_locale(): void
	{
		$this->assertSame('10%', DepositCalculator::format_deposit_display('10'));

		$this->turkish();
		$this->assertSame('%10', DepositCalculator::format_deposit_display('10'));
	}

	public function test_the_price_quote_carries_the_tax_rate_as_written_in_the_locale(): void
	{
		if (! class_exists('WC_Tax')) {
			$this->markTestSkipped('WooCommerce is absent from this test environment.');
		}

		BookingForm::register();
		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_prices_include_tax', 'yes');
		$rate_id = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'KDV',
				'tax_rate_priority' => '1',
				'tax_rate_compound' => '0',
				'tax_rate_shipping' => '1',
				'tax_rate_order'    => '1',
				'tax_rate_class'    => '',
			)
		);

		$vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Tax Label Vehicle',
			)
		);
		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		wp_set_current_user(0);

		$this->turkish();

		try {
			$data = $this->quote($vehicle_id);
		} finally {
			\WC_Tax::_delete_tax_rate($rate_id);
		}

		$this->assertTrue((bool) ( $data['tax_enabled'] ?? false ), wp_json_encode($data));
		$this->assertSame('%20', $data['tax_rate_display'] ?? null);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function quote(int $vehicle_id): array
	{
		$_POST    = array(
			'action'       => 'mhmrentiva_calculate_price',
			'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
			'vehicle_id'   => (string) $vehicle_id,
			'pickup_date'  => gmdate('Y-m-d', strtotime('+10 days')),
			'pickup_time'  => '10:00',
			'dropoff_date' => gmdate('Y-m-d', strtotime('+12 days')),
			'dropoff_time' => '10:00',
			'addons'       => array(),
			'payment_type' => 'deposit',
		);
		$_REQUEST = $_POST;

		$ob_level = ob_get_level();
		try {
			$this->_handleAjax('mhmrentiva_calculate_price');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The payload, not the die, is what is under test.
			unset($e);
		} finally {
			while (ob_get_level() > $ob_level) {
				ob_end_clean();
			}
			while (ob_get_level() < $ob_level) {
				ob_start();
			}
		}

		$raw                  = (string) $this->_last_response;
		$this->_last_response = '';
		$second               = strpos($raw, '{"success"', 1);
		$response             = json_decode(false !== $second ? substr($raw, 0, $second) : $raw, true);
		$this->assertIsArray($response, 'AJAX response must decode: ' . $raw);
		$this->assertTrue((bool) ( $response['success'] ?? false ), $raw);

		return (array) $response['data'];
	}
}
