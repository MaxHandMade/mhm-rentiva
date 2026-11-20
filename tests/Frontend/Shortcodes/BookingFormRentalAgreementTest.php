<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Settings\Groups\BookingSettings;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use ReflectionMethod;
use WP_Ajax_UnitTestCase;

/**
 * The rental agreement on the booking form.
 *
 * Off (the default): the form and the submission are as before. On: the form
 * shows the agreement and a consent box, the server refuses a submission
 * without consent whatever the browser did, and the booking records when the
 * customer accepted and which text (sha256 of the text shown).
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::create_booking_from_data
 */
final class BookingFormRentalAgreementTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceOptionSandbox;

	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	/** @var array<string, mixed> */
	private array $settings = array();

	public function setUp(): void
	{
		parent::setUp();
		BookingForm::register();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Agreement Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		$this->sandbox_option('woocommerce_calc_taxes', 'no');

		$this->settings = array(
			'mhmrentiva_rental_deposit_type'        => 'percentage',
			'mhmrentiva_rental_deposit_rate'        => 20,
			'mhmrentiva_vehicle_base_price'         => 1.0,
			'mhmrentiva_vehicle_weekend_multiplier' => 1.0,
			'mhmrentiva_vehicle_min_rental_days'    => 1,
			'mhmrentiva_vehicle_max_rental_days'    => 30,
		);
		add_filter('pre_option_mhmrentiva_settings', array( $this, 'settings_filter' ));
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		wp_set_current_user(0);
	}

	public function tearDown(): void
	{
		remove_filter('pre_option_mhmrentiva_settings', array( $this, 'settings_filter' ));
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->restore_sandboxed_options();
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function settings_filter(): array
	{
		return $this->settings;
	}

	private function enable(string $text = ''): void
	{
		$this->settings['mhmrentiva_rental_agreement_enabled'] = '1';
		$this->settings['mhmrentiva_rental_agreement_text']    = $text;
	}

	private function form(): string
	{
		return do_shortcode('[rentiva_booking_form vehicle_id="' . $this->vehicle_id . '"]');
	}

	// ------------------------------------------------------------- off

	public function test_off_the_form_has_no_agreement(): void
	{
		$html = $this->form();

		$this->assertStringNotContainsString('data-rvs-agreement', $html);
		$this->assertStringNotContainsString('name="terms_accepted"', $html);
	}

	public function test_off_a_submission_without_consent_goes_through_and_carries_no_acceptance(): void
	{
		$response = $this->dispatch();

		$this->assertTrue($response['success'], wp_json_encode($response));
		$this->assertArrayNotHasKey('terms_accepted_at', $this->cart_booking_data());
		$this->assertArrayNotHasKey('terms_version', $this->cart_booking_data());
	}

	// -------------------------------------------------------------- on

	public function test_on_the_form_shows_the_agreement_and_a_consent_box(): void
	{
		$this->enable("## Our terms\n- Drive **carefully**");

		$html = $this->form();

		$this->assertStringContainsString('data-rvs-agreement', $html);
		$this->assertStringContainsString('<h4>Our terms</h4>', $html);
		$this->assertStringContainsString('<strong>carefully</strong>', $html);
		$this->assertSame(1, \MHMRentiva\Tests\Support\CanonicalHtml::count_tags($html, '<input type="checkbox" name="terms_accepted" value="1">'));
		$this->assertStringContainsString('I have read and accept the rental agreement.', $html);
	}

	public function test_on_an_empty_text_shows_the_template(): void
	{
		$this->enable('');

		$this->assertStringContainsString('1. Driver and documents', $this->form());
	}

	public function test_on_a_submission_without_consent_is_refused_and_nothing_reaches_the_cart(): void
	{
		$this->enable();

		$response = $this->dispatch();

		$this->assertFalse($response['success']);
		$this->assertSame('To complete your booking, accept the rental agreement.', $response['data']['message'] ?? null);
		$this->assertSame(array(), $this->cart_items());
	}

	public function test_on_a_submission_with_consent_carries_the_time_and_the_version_of_the_text_shown(): void
	{
		$this->enable("## Terms v1\n- one");
		$before = gmdate('Y-m-d H:i:s');

		$response = $this->dispatch(array(
			'terms_accepted' => '1',
			'terms_version'  => hash('sha256', "## Terms v1\n- one"),
		));

		$this->assertTrue($response['success'], wp_json_encode($response));
		$data = $this->cart_booking_data();
		$this->assertSame(hash('sha256', "## Terms v1\n- one"), $data['terms_version']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['terms_accepted_at']);
		$this->assertGreaterThanOrEqual($before, $data['terms_accepted_at']);
	}

	public function test_the_template_version_is_the_hash_of_the_template(): void
	{
		$this->enable('');

		$version = hash('sha256', BookingSettings::rental_agreement_template());
		$this->dispatch(array(
			'terms_accepted' => '1',
			'terms_version'  => $version,
		));

		$this->assertSame($version, $this->cart_booking_data()['terms_version']);
	}

	public function test_the_form_posts_back_the_version_of_the_text_it_shows(): void
	{
		$this->enable("## Terms v1\n- one");

		$this->assertMatchesRegularExpression(
			'/<input[^>]*name="terms_version"[^>]*value="' . hash('sha256', "## Terms v1\n- one") . '"/',
			$this->form()
		);
	}

	public function test_a_submission_from_a_page_showing_another_text_is_refused(): void
	{
		// A cached or open page showed v1; the admin has since saved v2.
		$this->enable("## Terms v2\n- two");

		$response = $this->dispatch(array(
			'terms_accepted' => '1',
			'terms_version'  => hash('sha256', "## Terms v1\n- one"),
		));

		$this->assertFalse($response['success']);
		$this->assertSame('The rental agreement has changed. Reload the page and review it before booking.', $response['data']['message'] ?? null);
		$this->assertSame(array(), $this->cart_items());
	}

	// ---------------------------------------------------------- record

	public function test_the_booking_records_the_acceptance_it_was_handed(): void
	{
		$booking_id = $this->create_booking(
			array(
				'terms_accepted_at' => '2026-11-18 11:32:00',
				'terms_version'     => str_repeat('a', 64),
			)
		);

		$this->assertSame('2026-11-18 11:32:00', get_post_meta($booking_id, '_mhmrentiva_terms_accepted_at', true));
		$this->assertSame(str_repeat('a', 64), get_post_meta($booking_id, '_mhmrentiva_terms_version', true));
		$this->assertFalse(metadata_exists('post', $booking_id, '_mhmrentiva_terms_text'), 'No text was handed over.');
	}

	public function test_the_booking_keeps_the_accepted_text_itself(): void
	{
		// What the customer accepted must survive the admin editing the setting.
		$text       = "## Terms\n- Pay fines\n- Path C:\\cars\\keys";
		$booking_id = $this->create_booking(
			array(
				'terms_accepted_at' => '2026-11-18 11:32:00',
				'terms_version'     => hash('sha256', $text),
				'terms_text'        => $text,
			)
		);

		$this->assertSame($text, get_post_meta($booking_id, '_mhmrentiva_terms_text', true), 'Backslashes and line breaks kept.');
	}

	public function test_a_text_that_does_not_match_its_version_is_not_kept(): void
	{
		$booking_id = $this->create_booking(
			array(
				'terms_accepted_at' => '2026-11-18 11:32:00',
				'terms_version'     => hash('sha256', 'the text shown'),
				'terms_text'        => 'another text',
			)
		);

		$this->assertFalse(metadata_exists('post', $booking_id, '_mhmrentiva_terms_text'));
	}

	public function test_the_cart_carries_the_text_shown(): void
	{
		$this->enable("## Terms v1\n- one");

		$this->dispatch(array(
			'terms_accepted' => '1',
			'terms_version'  => hash('sha256', "## Terms v1\n- one"),
		));

		$this->assertSame("## Terms v1\n- one", $this->cart_booking_data()['terms_text']);
	}

	public function test_a_booking_without_acceptance_writes_no_acceptance_keys(): void
	{
		// A cart from before the switch was turned on, or the switch is off.
		$booking_id = $this->create_booking(array());

		$this->assertFalse(metadata_exists('post', $booking_id, '_mhmrentiva_terms_accepted_at'));
		$this->assertFalse(metadata_exists('post', $booking_id, '_mhmrentiva_terms_version'));
	}

	// ---------------------------------------------------------- helpers

	/**
	 * @param array<string, string> $extra
	 */
	private function create_booking(array $extra): int
	{
		$data = array_merge(
			array(
				'vehicle_id'          => $this->vehicle_id,
				'pickup_date'         => gmdate('Y-m-d', strtotime('+20 days')),
				'pickup_time'         => '10:00',
				'dropoff_date'        => gmdate('Y-m-d', strtotime('+22 days')),
				'dropoff_time'        => '10:00',
				'guests'              => 1,
				'customer_user_id'    => 0,
				'customer_name'       => 'Jane Doe',
				'customer_first_name' => 'Jane',
				'customer_last_name'  => 'Doe',
				'customer_email'      => 'jane@example.com',
				'customer_phone'      => '5551112233',
				'payment_type'        => 'deposit',
				'payment_method'      => 'woocommerce',
				'payment_gateway'     => 'woocommerce',
				'deposit_amount'      => 400,
				'remaining_amount'    => 1600,
				'deposit_type'        => 'percentage',
				'payment_display'     => 'Deposit',
				'total_price'         => 2000,
				'rental_days'         => 2,
				'selected_addons'     => array(),
				'cancellation_policy' => '24_hours',
				'payment_deadline'    => gmdate('Y-m-d H:i:s', strtotime('+1 day')),
			),
			$extra
		);

		$method = new ReflectionMethod(WooCommerceBridge::class, 'create_booking_from_data');
		$method->setAccessible(true);
		$booking_id = (int) $method->invoke(null, $data, 999);
		$this->assertGreaterThan(0, $booking_id);

		return $booking_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cart_items(): array
	{
		return ( function_exists('WC') && isset(WC()->cart) ) ? WC()->cart->get_cart() : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cart_booking_data(): array
	{
		foreach ($this->cart_items() as $item) {
			if (isset($item['mhmrentiva_booking_data'])) {
				return $item['mhmrentiva_booking_data'];
			}
		}
		$this->fail('No booking reached the cart.');
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function dispatch(array $extra = array()): array
	{
		$action   = 'mhmrentiva_booking_form';
		$_POST    = array_merge(
			array(
				'action'              => $action,
				'nonce'               => wp_create_nonce('mhmrentiva_booking_form_nonce'),
				'vehicle_id'          => (string) $this->vehicle_id,
				'pickup_date'         => gmdate('Y-m-d', strtotime('+10 days')),
				'pickup_time'         => '10:00',
				'dropoff_date'        => gmdate('Y-m-d', strtotime('+12 days')),
				'dropoff_time'        => '10:00',
				'payment_type'        => 'deposit',
				'customer_first_name' => 'Jane',
				'customer_last_name'  => 'Doe',
				'customer_email'      => 'jane@example.com',
				'customer_phone'      => '5551112233',
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
		$second               = strpos($raw, '{"success"', 1);
		$response             = json_decode(false !== $second ? substr($raw, 0, $second) : $raw, true);
		$this->assertIsArray($response, 'AJAX response must decode: ' . $raw);

		return $response;
	}
}
