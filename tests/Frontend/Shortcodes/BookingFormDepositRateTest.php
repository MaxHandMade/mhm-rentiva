<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * The booking form charges the deposit DepositCalculator::resolve_rate() settles
 * on -- the vehicle's own rate, else the global rental setting.
 *
 * The form used to read the raw vehicle field twice: an empty field forced full
 * payment (while the calculator, the card and the admin screen all said 10%),
 * and the price preview computed its own deposit from the same raw value. Now
 * an empty field follows the Payment tab: a percentage there is a deposit, and
 * "Full payment" is a full payment.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 */
final class BookingFormDepositRateTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceOptionSandbox;

	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		BookingForm::register();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Deposit Rate Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		$this->sandbox_option('woocommerce_calc_taxes', 'no');
		wp_set_current_user(0);
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		$this->restore_sandboxed_options();
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();
	}

	private function global_setting(string $type, int $rate = 10): void
	{
		update_option(
			'mhmrentiva_settings',
			array(
				'mhmrentiva_rental_deposit_type'        => $type,
				'mhmrentiva_rental_deposit_rate'        => $rate,
				'mhmrentiva_vehicle_base_price'         => 1.0,
				'mhmrentiva_vehicle_weekend_multiplier' => 1.0,
			)
		);
	}

	// ------------------------------------------------------- price preview

	public function test_price_preview_uses_the_global_percentage_for_an_empty_field(): void
	{
		$this->global_setting('percentage', 30);

		$data = $this->dispatch('mhmrentiva_calculate_price')['data'];

		$this->assertSame('deposit', $data['payment_type'] ?? null);
		$this->assertEqualsWithDelta(0.3 * (float) $data['total_price'], (float) $data['deposit_amount'], 0.01);
		$this->assertEqualsWithDelta(0.7 * (float) $data['total_price'], (float) $data['remaining_amount'], 0.01);
	}

	public function test_price_preview_charges_in_full_under_global_full_payment(): void
	{
		$this->global_setting('full_payment');

		$data = $this->dispatch('mhmrentiva_calculate_price')['data'];

		$this->assertSame('full', $data['payment_type'] ?? null);
		$this->assertEqualsWithDelta((float) $data['total_price'], (float) $data['deposit_amount'], 0.01);
		$this->assertEqualsWithDelta(0.0, (float) $data['remaining_amount'], 0.01);
	}

	public function test_price_preview_uses_the_vehicle_rate_over_global_full_payment(): void
	{
		$this->global_setting('full_payment');
		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', '15');

		$data = $this->dispatch('mhmrentiva_calculate_price')['data'];

		$this->assertSame('deposit', $data['payment_type'] ?? null);
		$this->assertEqualsWithDelta(0.15 * (float) $data['total_price'], (float) $data['deposit_amount'], 0.01);
	}

	public function test_price_preview_keeps_a_visitors_full_payment_choice(): void
	{
		$this->global_setting('percentage', 30);

		$data = $this->dispatch('mhmrentiva_calculate_price', array( 'payment_type' => 'full' ))['data'];

		$this->assertSame('full', $data['payment_type'] ?? null);
		$this->assertEqualsWithDelta((float) $data['total_price'], (float) $data['deposit_amount'], 0.01);
	}

	public function test_price_preview_and_resolver_agree_on_the_rate(): void
	{
		$this->global_setting('percentage', 25);

		$data = $this->dispatch('mhmrentiva_calculate_price')['data'];

		$this->assertEqualsWithDelta(
			DepositCalculator::resolve_rate($this->vehicle_id) / 100 * (float) $data['total_price'],
			(float) $data['deposit_amount'],
			0.01
		);
	}

	// ---------------------------------------------------------- submission

	public function test_submission_takes_a_deposit_from_the_global_percentage(): void
	{
		$this->global_setting('percentage', 30);

		$response = $this->dispatch('mhmrentiva_booking_form', $this->customer_fields());

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$booking = $response['data']['booking_data'];
		$this->assertSame('deposit', $booking['payment_type'], 'An empty vehicle field no longer forces full payment while a deposit setting applies.');
		$this->assertEqualsWithDelta(0.3 * (float) $booking['total_price'], (float) $booking['deposit_amount'], 0.01);
	}

	public function test_submission_charges_in_full_under_global_full_payment(): void
	{
		$this->global_setting('full_payment');

		$response = $this->dispatch('mhmrentiva_booking_form', $this->customer_fields());

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$booking = $response['data']['booking_data'];
		$this->assertSame('full', $booking['payment_type']);
		$this->assertEqualsWithDelta((float) $booking['total_price'], (float) $booking['deposit_amount'], 0.01);
		$this->assertEqualsWithDelta(0.0, (float) $booking['remaining_amount'], 0.01);
	}

	public function test_submission_with_a_stale_zero_follows_full_payment(): void
	{
		$this->global_setting('full_payment');
		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', '0');

		$response = $this->dispatch('mhmrentiva_booking_form', $this->customer_fields());

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$this->assertSame('full', $response['data']['booking_data']['payment_type']);
	}

	// ------------------------------------------------------------ fixtures

	/**
	 * @return array<string, string>
	 */
	private function customer_fields(): array
	{
		return array(
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Doe',
			'customer_email'      => 'jane@example.com',
			'customer_phone'      => '5551112233',
		);
	}

	/**
	 * @param array<string, string> $extra
	 * @return array<string, mixed>
	 */
	private function dispatch(string $action, array $extra = array()): array
	{
		$_POST    = array_merge(
			array(
				'action'       => $action,
				'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
				'vehicle_id'   => (string) $this->vehicle_id,
				'pickup_date'  => gmdate('Y-m-d', strtotime('+10 days')),
				'pickup_time'  => '10:00',
				'dropoff_date' => gmdate('Y-m-d', strtotime('+12 days')),
				'dropoff_time' => '10:00',
				'addons'       => array(),
				// What the form's hidden field sends.
				'payment_type' => 'deposit',
			),
			$extra
		);
		$_REQUEST = $_POST;

		$ob_level = ob_get_level();
		try {
			$this->_handleAjax($action);
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

		// A harness replay may append a second JSON document; keep the first.
		$second   = strpos($raw, '{"success"', 1);
		$response = json_decode(false !== $second ? substr($raw, 0, $second) : $raw, true);
		$this->assertIsArray($response, 'AJAX response must decode: ' . $raw);

		return $response;
	}
}
