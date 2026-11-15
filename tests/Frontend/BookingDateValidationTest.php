<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_Ajax_UnitTestCase;

/**
 * PR-A (customer + admin lens 2026-11-11, findings r2 #7 and admin #9):
 *
 * 1. A return before (or at) the pickup is refused as itself, not as "Invalid
 *    date/time format." -- parse_datetimes() used to swallow its own message.
 * 2. A pickup that has already passed is refused on every CUSTOMER surface
 *    (booking form submit, price, availability, search), while the operator's
 *    check still takes a past rental: an admin records rentals after the fact.
 * 3. The search names the date problem instead of "no cars are free".
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::parse_datetimes
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::check_availability_with_alternatives
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::pickup_has_passed
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_booking_form
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_calculate_price
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_check_availability
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::empty_state
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::notice_html
 */
final class BookingDateValidationTest extends WP_Ajax_UnitTestCase
{
	private const ORDER  = 'Return date must be after pickup date.';
	private const PAST   = 'The pickup time has already passed. Please choose a later date or time.';
	private const FORMAT = 'Invalid date/time format.';

	private int $vehicle_id;

	/** @var string */
	protected $_last_response;

	public function setUp(): void
	{
		parent::setUp();

		BookingForm::register();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Date Rule Sedan',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');

		wp_set_current_user(0);
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		parent::tearDown();
	}

	// ------------------------------------------------------ parse_datetimes

	public function test_a_return_before_the_pickup_is_named_not_called_a_format_error(): void
	{
		$result = Util::parse_datetimes($this->day(10), '10:00', $this->day(8), '10:00');

		$this->assertWPError($result);
		$this->assertSame(self::ORDER, $result->get_error_message());
	}

	public function test_a_return_at_the_pickup_moment_is_a_return_before_it(): void
	{
		$result = Util::parse_datetimes($this->day(10), '10:00', $this->day(10), '10:00');

		$this->assertWPError($result);
		$this->assertSame(self::ORDER, $result->get_error_message());
	}

	public function test_a_broken_date_is_still_a_format_error(): void
	{
		$result = Util::parse_datetimes('10/12/2026', '10:00', $this->day(12), '10:00');

		$this->assertWPError($result);
		$this->assertSame(self::FORMAT, $result->get_error_message());
	}

	// ------------------------------------------- customer availability check

	public function test_the_form_check_names_reversed_dates(): void
	{
		$result = Util::check_availability_with_alternatives($this->vehicle_id, $this->day(10), '10:00', $this->day(8), '10:00');

		$this->assertFalse($result['ok']);
		$this->assertSame('invalid_dates', $result['code']);
		$this->assertSame(self::ORDER, $result['message']);
	}

	public function test_the_form_check_refuses_a_pickup_that_has_passed(): void
	{
		$result = Util::check_availability_with_alternatives($this->vehicle_id, $this->day(-1), '10:00', $this->day(2), '10:00');

		$this->assertFalse($result['ok']);
		$this->assertSame('past_pickup', $result['code']);
		$this->assertSame(self::PAST, $result['message']);
	}

	public function test_the_form_check_still_takes_a_future_pickup(): void
	{
		$result = Util::check_availability_with_alternatives($this->vehicle_id, $this->day(3), '10:00', $this->day(5), '10:00');

		$this->assertTrue($result['ok'], wp_json_encode($result));
	}

	public function test_the_operator_check_still_takes_a_rental_recorded_after_the_fact(): void
	{
		$result = Util::check_availability_for_operator($this->vehicle_id, $this->day(-5), '10:00', $this->day(-2), '10:00');

		$this->assertTrue($result['ok'], wp_json_encode($result));
	}

	/** Admin lens #9: the manual booking screen's server answer named a format error too. */
	public function test_the_operator_check_names_reversed_dates_too(): void
	{
		$result = Util::check_availability_for_operator($this->vehicle_id, $this->day(10), '10:00', $this->day(8), '10:00');

		$this->assertFalse($result['ok']);
		$this->assertSame(self::ORDER, $result['message']);
	}

	public function test_a_pickup_one_minute_ago_has_passed_and_one_in_a_minute_has_not(): void
	{
		$this->assertTrue(Util::pickup_has_passed(time() - MINUTE_IN_SECONDS));
		$this->assertFalse(Util::pickup_has_passed(time() + MINUTE_IN_SECONDS));
	}

	// ---------------------------------------------------- booking form AJAX

	public function test_the_booking_form_refuses_a_pickup_that_has_passed(): void
	{
		$response = $this->dispatch('mhmrentiva_booking_form', $this->day(-1), $this->day(2), $this->customer());

		$this->assertFalse($response['success'], wp_json_encode($response));
		$this->assertSame(self::PAST, $response['data']['message']);
	}

	public function test_the_booking_form_names_reversed_dates(): void
	{
		$response = $this->dispatch('mhmrentiva_booking_form', $this->day(10), $this->day(8), $this->customer());

		$this->assertFalse($response['success'], wp_json_encode($response));
		$this->assertSame(self::ORDER, $response['data']['message']);
	}

	public function test_the_price_is_not_quoted_for_a_pickup_that_has_passed(): void
	{
		$response = $this->dispatch('mhmrentiva_calculate_price', $this->day(-1), $this->day(2));

		$this->assertFalse($response['success'], wp_json_encode($response));
		$this->assertSame(self::PAST, $response['data']['message']);
	}

	public function test_the_price_still_quotes_a_future_pickup(): void
	{
		$response = $this->dispatch('mhmrentiva_calculate_price', $this->day(3), $this->day(5));

		$this->assertTrue($response['success'], wp_json_encode($response));
	}

	public function test_the_availability_check_answers_past_pickup_with_its_own_code(): void
	{
		$response = $this->dispatch('mhmrentiva_check_availability', $this->day(-1), $this->day(2));

		$this->assertFalse($response['success'], wp_json_encode($response));
		$this->assertSame('past_pickup', $response['data']['code'] ?? '');
	}

	// --------------------------------------------------------------- search

	public function test_the_search_names_reversed_dates_instead_of_no_free_cars(): void
	{
		$params = array( 'pickup_date' => $this->day(10), 'return_date' => $this->day(8) );

		$this->assertSame('bad_dates', SearchResults::empty_state($params, 0));
		$this->assertSame('bad_dates', SearchResults::empty_state($params, 4), 'The catalogue lists, but the dates are still wrong.');
		$this->assertStringContainsString(self::ORDER, SearchResults::notice_html($params, array( 'vehicles' => array() ), 'bad_dates', '#'));
	}

	public function test_the_search_names_a_pickup_that_has_passed(): void
	{
		$params = array( 'pickup_date' => $this->day(-1), 'return_date' => $this->day(2) );

		$this->assertSame('bad_dates', SearchResults::empty_state($params, 0));
		$this->assertStringContainsString(self::PAST, SearchResults::notice_html($params, array( 'vehicles' => array() ), 'bad_dates', '#'));
	}

	public function test_the_search_keeps_its_states_for_good_dates(): void
	{
		$params = array( 'pickup_date' => $this->day(3), 'return_date' => $this->day(5) );

		$this->assertNull(SearchResults::empty_state($params, 4));
		$this->assertSame('no_results', SearchResults::empty_state($params, 0));
		$this->assertSame('not_searched', SearchResults::empty_state(array(), 4));
	}

	// -------------------------------------------------------------- helpers

	/** A date in the site timezone, $days from today. */
	private function day(int $days): string
	{
		return wp_date('Y-m-d', strtotime(( $days >= 0 ? '+' : '' ) . $days . ' days'));
	}

	/** @return array<string, string> */
	private function customer(): array
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
	private function dispatch(string $action, string $pickup, string $dropoff, array $extra = array()): array
	{
		$_POST    = array_merge(
			array(
				'action'       => $action,
				'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
				'vehicle_id'   => (string) $this->vehicle_id,
				'pickup_date'  => $pickup,
				'pickup_time'  => '10:00',
				'dropoff_date' => $dropoff,
				'dropoff_time' => '10:00',
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

		$buffer               = (string) $this->_last_response;
		$this->_last_response = '';

		$start = strpos($buffer, '{');
		if (false === $start) {
			return array( 'success' => false );
		}
		// First JSON document (the second is a harness replay).
		$depth = 0;
		for ($i = $start, $len = strlen($buffer); $i < $len; $i++) {
			if ('{' === $buffer[ $i ]) {
				$depth++;
			} elseif ('}' === $buffer[ $i ] && 0 === --$depth) {
				$decoded = json_decode(substr($buffer, $start, $i - $start + 1), true);
				return is_array($decoded) ? $decoded : array( 'success' => false );
			}
		}

		return array( 'success' => false );
	}
}
