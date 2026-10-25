<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * Rules the booking form states in the browser, held on the server.
 *
 * Audit 2026-10-20 (sinif-1-istemci-tutar), F-1 / F-2 / L-2:
 *
 * - F-1: the form locks the return time to the pickup time ("Return time is
 *   automatically set to match pickup time.") but only the browser did it, and
 *   Util::rental_days() dropped the hours. Pickup 10:00, return two days later
 *   at 09:59 was charged one day while the vehicle was held for ~48 hours.
 * - F-2: an add-on the operator marked required was not required anywhere on
 *   the rental path -- the validator was hooked to a filter nothing applies.
 * - L-2: any payment_type other than deposit/full was accepted on submission.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::rental_days
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::validate_rental_duration
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_check_availability
 */
final class BookingFormServerRulesTest extends WP_Ajax_UnitTestCase
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
				'post_title'  => 'Server Rules Vehicle',
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

		wp_set_current_user(0);
	}

	public function tearDown(): void
	{
		remove_filter('pre_option_mhmrentiva_settings', array( $this, 'settings_filter' ));
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

	// ------------------------------------------------- F-1: day arithmetic

	/**
	 * Util::rental_days() keeps its whole-day count (24-hour floor; it is not
	 * changed here, daylight-saving drift included). Pricing a later return
	 * as another day would change the admin manual booking and edit flows,
	 * where staff enter different times on purpose -- a pricing-policy decision
	 * that is not part of this fix. The public form closes F-1 by forcing the
	 * return time instead (tests further down).
	 */
	public function test_days_are_whole_days_and_ignore_a_partial_one(): void
	{
		$evening = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-03', '18:00');
		$morning = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-04', '09:59');
		$this->assertIsArray($evening);
		$this->assertIsArray($morning);

		$this->assertSame(1, Util::rental_days($evening['start_ts'], $evening['end_ts']), 'A later return time does not add a day.');
		$this->assertSame(1, Util::rental_days($morning['start_ts'], $morning['end_ts']), '47h59m is one whole day, as it always was.');
	}

	public function test_whole_days_are_counted_as_before(): void
	{
		$range = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-05', '10:00');
		$this->assertIsArray($range);

		$this->assertSame(3, Util::rental_days($range['start_ts'], $range['end_ts']));

		$short = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-02', '14:00');
		$this->assertIsArray($short);
		$this->assertSame(1, Util::rental_days($short['start_ts'], $short['end_ts']), 'A same-day rental is still one day.');
	}

	public function test_the_maximum_length_counts_whole_days(): void
	{
		$this->settings['mhmrentiva_vehicle_max_rental_days'] = 2;

		$exact = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-04', '10:00');
		$late  = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-04', '23:59');
		$over  = Util::parse_datetimes('2026-11-02', '10:00', '2026-11-05', '10:00');
		$this->assertIsArray($exact);
		$this->assertIsArray($late);
		$this->assertIsArray($over);

		$this->assertTrue(Util::validate_rental_duration($exact['start_ts'], $exact['end_ts']));
		$this->assertTrue(Util::validate_rental_duration($late['start_ts'], $late['end_ts']), 'The helper still counts whole days; the public form is what stops the extra hours (see the submission test).');
		$this->assertWPError(Util::validate_rental_duration($over['start_ts'], $over['end_ts']));
	}

	// ------------------------------------- F-1: the public form's own rule

	public function test_submission_holds_the_return_time_to_the_pickup_time(): void
	{
		$response = $this->dispatch(
			'mhmrentiva_booking_form',
			array(
				'pickup_time'  => '10:00',
				'dropoff_date' => $this->day(12),
				'dropoff_time' => '09:59',
			)
		);

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$this->assertSame(2, (int) $response['data']['booking_data']['days']);
		$this->assertEqualsWithDelta(2000.0, (float) $response['data']['booking_data']['total_price'], 0.01);

		// What the vehicle will be blocked for is what the cart carries into
		// create_booking_from_data(): its dates and times. That interval must
		// be the one that was priced.
		$cart = $this->cart_booking_data();
		$this->assertSame('10:00', $cart['dropoff_time'], 'The return time follows the pickup time, whatever was posted.');

		$blocked = Util::parse_datetimes($cart['pickup_date'], $cart['pickup_time'], $cart['dropoff_date'], $cart['dropoff_time']);
		$this->assertIsArray($blocked);
		$this->assertSame((int) $cart['rental_days'], Util::rental_days($blocked['start_ts'], $blocked['end_ts']));
		$this->assertSame(2 * DAY_IN_SECONDS, $blocked['end_ts'] - $blocked['start_ts']);
	}

	/**
	 * The quote and the availability check take the return time from the
	 * pickup time too. Observable through a same-day request: with the posted
	 * 23:00 it would be a valid 13-hour window, with the forced 10:00 it is an
	 * empty one -- which the form cannot produce either.
	 *
	 * @dataProvider form_endpoints_without_a_submission
	 */
	public function test_quote_and_availability_hold_the_return_time_to_the_pickup_time(string $action): void
	{
		$response = $this->dispatch(
			$action,
			array(
				'pickup_date'  => $this->day(10),
				'pickup_time'  => '10:00',
				'dropoff_date' => $this->day(10),
				'dropoff_time' => '23:00',
			)
		);

		$this->assertFalse($response['success'] ?? true, 'A posted return time was used: ' . wp_json_encode($response));
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function form_endpoints_without_a_submission(): array
	{
		return array(
			'price quote'        => array( 'mhmrentiva_calculate_price' ),
			'availability check' => array( 'mhmrentiva_check_availability' ),
		);
	}

	public function test_submission_cannot_pass_the_maximum_with_a_late_return_time(): void
	{
		$this->settings['mhmrentiva_vehicle_max_rental_days'] = 2;

		$response = $this->dispatch(
			'mhmrentiva_booking_form',
			array(
				'dropoff_date' => $this->day(12),
				'dropoff_time' => '23:59',
			)
		);

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$cart = $this->cart_booking_data();
		$this->assertSame('10:00', $cart['dropoff_time'], 'The vehicle is not held past the two-day maximum.');
	}

	// --------------------------------------------- F-2: required add-ons

	public function test_submission_without_a_required_addon_is_refused(): void
	{
		$required = $this->addon('Mandatory insurance', '150', true);

		$response = $this->dispatch('mhmrentiva_booking_form');

		$this->assertFalse($response['success'] ?? true, wp_json_encode($response));
		$this->assertStringContainsString('Mandatory insurance', (string) ( $response['data']['message'] ?? '' ));
		$this->assertSame(array(), $this->cart_items(), 'Nothing reaches the cart.');
		unset($required);
	}

	public function test_quote_without_a_required_addon_is_refused(): void
	{
		$this->addon('Mandatory insurance', '150', true);

		$response = $this->dispatch('mhmrentiva_calculate_price');

		$this->assertFalse($response['success'] ?? true, wp_json_encode($response));
		$this->assertStringContainsString('Mandatory insurance', (string) ( $response['data']['message'] ?? '' ));
	}

	public function test_submission_and_quote_with_the_required_addon_are_accepted(): void
	{
		$required = $this->addon('Mandatory insurance', '150', true);
		$optional = $this->addon('Child seat', '50', false);

		$quote = $this->dispatch('mhmrentiva_calculate_price', array( 'addons' => array( (string) $required ) ));
		$this->assertTrue($quote['success'] ?? false, wp_json_encode($quote));
		$this->assertGreaterThan(0.0, (float) $quote['data']['addon_total']);

		$response = $this->dispatch('mhmrentiva_booking_form', array( 'addons' => array( (string) $required ) ));
		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$this->assertSame(array( $required ), array_map('intval', $response['data']['booking_data']['addons']));
		unset($optional);
	}

	/**
	 * The form's HTML is cached for anonymous visitors. A required add-on the
	 * admin adds or switches on must reach that cache at once, or the cached
	 * form lacks the box the server now insists on and refuses every booking.
	 */
	public function test_an_addon_change_drops_the_cached_booking_form(): void
	{
		\MHMRentiva\Admin\Addons\AddonManager::register_form_cache_invalidation();
		$version_key = 'mhmrentiva_rv_cache_v_rentiva_booking_form';
		$addon       = $this->addon('Mandatory insurance', '150', false);

		set_transient($version_key, 'cached', DAY_IN_SECONDS);
		update_post_meta($addon, 'mhmrentiva_addon_required', '1');
		$this->assertFalse(get_transient($version_key), 'Switching an add-on to required drops the form cache.');

		set_transient($version_key, 'cached', DAY_IN_SECONDS);
		wp_update_post(array( 'ID' => $addon, 'post_status' => 'draft' ));
		$this->assertFalse(get_transient($version_key), 'Unpublishing an add-on drops the form cache.');

		set_transient($version_key, 'cached', DAY_IN_SECONDS);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '999');
		$this->assertSame('cached', get_transient($version_key), 'Unrelated posts leave the form cache alone.');
	}

	/**
	 * Deleting the version key is not enough on its own: the key used to be
	 * rebuilt from time(), so a form cached, an add-on changed and the form read
	 * again within one second came back from the old cache entry.
	 */
	public function test_a_cached_form_shows_an_addon_made_required_in_the_same_second(): void
	{
		\MHMRentiva\Admin\Addons\AddonManager::register_form_cache_invalidation();
		wp_set_current_user(0);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');
		$addon = $this->addon('Mandatory insurance', '150', false);

		$before = do_shortcode('[rentiva_booking_form vehicle_id="' . $this->vehicle_id . '"]');
		$this->assertStringNotContainsString('rv-addon-item--required', $before, 'Fixture: nothing is required yet.');

		update_post_meta($addon, 'mhmrentiva_addon_required', '1');
		$after = do_shortcode('[rentiva_booking_form vehicle_id="' . $this->vehicle_id . '"]');

		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');
		$this->assertStringContainsString('rv-addon-item--required', $after, 'The cached form must not hide the now-required add-on.');
	}

	public function test_a_switched_off_required_addon_does_not_block_booking(): void
	{
		$required = $this->addon('Retired insurance', '150', true);
		update_post_meta($required, 'mhmrentiva_addon_enabled', '0');

		$response = $this->dispatch('mhmrentiva_booking_form');

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
	}

	public function test_form_shows_a_required_addon_selected_and_locked(): void
	{
		$required = $this->addon('Mandatory insurance', '150', true);
		$optional = $this->addon('Child seat', '50', false);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$html = do_shortcode('[rentiva_booking_form vehicle_id="' . $this->vehicle_id . '"]');

		$this->assertSame(1, preg_match('/<input[^>]*value="' . $required . '"[^>]*>/', $html, $req_input), 'Required add-on checkbox rendered.');
		$this->assertMatchesRegularExpression('/\bchecked\b/', $req_input[0]);
		$this->assertMatchesRegularExpression('/\bdisabled\b/', $req_input[0]);

		$this->assertSame(1, preg_match('/<input[^>]*value="' . $optional . '"[^>]*>/', $html, $opt_input));
		$this->assertDoesNotMatchRegularExpression('/\bchecked\b/', $opt_input[0]);
	}

	public function test_form_without_addons_still_shows_a_required_one(): void
	{
		$required = $this->addon('Mandatory insurance', '150', true);
		$this->addon('Child seat', '50', false);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$html = do_shortcode('[rentiva_booking_form vehicle_id="' . $this->vehicle_id . '" show_addons="0"]');

		$this->assertSame(1, preg_match('/<input[^>]*value="' . $required . '"[^>]*>/', $html), 'A form told to hide add-ons still carries the one it cannot be booked without.');
		$this->assertStringNotContainsString('Child seat', $html);
	}

	// ------------------------------------------------- L-2: payment type

	public function test_submission_refuses_an_unknown_payment_type(): void
	{
		$response = $this->dispatch('mhmrentiva_booking_form', array( 'payment_type' => 'later' ));

		$this->assertFalse($response['success'] ?? true, wp_json_encode($response));
		$this->assertSame('Invalid payment type.', $response['data']['message'] ?? null);
		$this->assertSame(array(), $this->cart_items());
	}

	// ------------------------------------------------------------ fixtures

	private function day(int $offset): string
	{
		return gmdate('Y-m-d', strtotime('+' . $offset . ' days'));
	}

	private function addon(string $title, string $price, bool $required): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_addon',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta($id, 'mhmrentiva_addon_price', $price);
		update_post_meta($id, 'mhmrentiva_addon_enabled', '1');
		if ($required) {
			update_post_meta($id, 'mhmrentiva_addon_required', '1');
		}

		return $id;
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
	private function dispatch(string $action, array $extra = array()): array
	{
		$_POST    = array_merge(
			array(
				'action'              => $action,
				'nonce'               => wp_create_nonce('mhmrentiva_booking_form_nonce'),
				'vehicle_id'          => (string) $this->vehicle_id,
				'pickup_date'         => $this->day(10),
				'pickup_time'         => '10:00',
				'dropoff_date'        => $this->day(12),
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

		// A harness replay may append a second JSON document; keep the first.
		$second   = strpos($raw, '{"success"', 1);
		$response = json_decode(false !== $second ? substr($raw, 0, $second) : $raw, true);
		$this->assertIsArray($response, 'AJAX response must decode: ' . $raw);

		return $response;
	}
}
