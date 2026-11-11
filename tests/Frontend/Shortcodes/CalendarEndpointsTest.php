<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar;
use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use WP_Ajax_UnitTestCase;

/**
 * The calendar's endpoints (R10, R11, r2 m16, Codex suffix): the quote is the
 * vehicle page's own, a transfer-only vehicle is no vehicle here, the old
 * endpoint is gone.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\CalendarView
 */
final class CalendarEndpointsTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		AvailabilityCalendar::register();
		$this->vehicle_id = $this->vehicle();
		wp_set_current_user(0);
	}

	private function vehicle(string $title = 'Quote Car'): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');
		return $id;
	}

	private function day(int $ahead): string
	{
		return SiteTime::days_ahead($ahead);
	}

	/**
	 * @param array<string, string> $post
	 * @return array<string, mixed>
	 */
	private function call(string $action, array $post): array
	{
		$_POST    = $post + array( 'nonce' => wp_create_nonce('mhmrentiva_availability_nonce') );
		$_REQUEST = $_POST;
		// One test may ask twice; the response buffer accumulates.
		$this->_last_response = '';
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}
		$response = json_decode((string) $this->_last_response, true);
		$this->assertIsArray($response, 'One JSON document: ' . $this->_last_response);
		return $response;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function quote(string $start, string $end, ?int $vehicle = null): array
	{
		return $this->call(
			'mhmrentiva_calendar_quote',
			array(
				'vehicle_id' => (string) ( $vehicle ?? $this->vehicle_id ),
				'start_date' => $start,
				'end_date'   => $end,
			)
		);
	}

	public function test_quote_answers_with_the_vehicle_pages_total_days_and_link(): void
	{
		$start = $this->day(10);
		$end   = $this->day(12);

		$data = $this->quote($start, $end)['data'];

		$window  = VehicleDetailsView::window(
			array(
				'pickup_date' => $start,
				'return_date' => $end,
			)
		);
		$booking = VehicleDetailsView::booking($this->vehicle_id, array(), $window);

		$this->assertTrue($data['priced']);
		$this->assertTrue($data['available']);
		$this->assertSame(2, $data['days'], 'Twelve to fourteen is two days, as the form charges (R10).');
		$this->assertSame($booking['continue_url'], $data['continue_url'], 'r2 M2: the link is the vehicle page\'s.');
		$this->assertStringContainsString('mhmrentiva_pickup_date=' . $start, $data['continue_url']);
		$this->assertStringContainsString('mhmrentiva_return_date=' . $end, $data['continue_url']);
		$this->assertStringContainsString($booking['breakdown']['total']['html'], $data['total_html']);
		$this->assertStringContainsString('2 days', $data['range_label']);
		$this->assertNotSame('', $data['time_hint']);
		$this->assertSame('', $data['message']);
	}

	/**
	 * The dates read in the site's format, not a browser's or a PHP code.
	 */
	public function test_range_label_uses_the_site_date_format(): void
	{
		update_option('date_format', 'd/m/Y');
		$start = $this->day(10);

		$data = $this->quote($start, $this->day(12))['data'];

		$this->assertStringContainsString(gmdate('d/m/Y', (int) strtotime($start . ' 12:00:00 UTC')), $data['range_label']);
	}

	/**
	 * Codex: K7, the amount plus WooCommerce's suffix.
	 */
	public function test_total_html_carries_the_woocommerce_price_suffix(): void
	{
		if (! class_exists('WC_Product_Simple')) {
			$this->markTestSkipped('WooCommerce is not loaded.');
		}
		// WooCommerce prints a suffix only with taxes on (WC_Product::get_price_suffix()).
		update_option('woocommerce_calc_taxes', 'yes');
		update_option('woocommerce_price_display_suffix', 'incl. test VAT');

		$data = $this->quote($this->day(10), $this->day(12))['data'];

		$this->assertStringContainsString('incl. test VAT', $data['total_html']);
	}

	/**
	 * R10: a booked day inside the range: no total, no button, a message.
	 */
	public function test_range_over_a_booked_day_has_no_total_and_no_link(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', array( $this->day(11) ));

		$data = $this->quote($this->day(10), $this->day(12))['data'];

		$this->assertFalse($data['priced']);
		$this->assertFalse($data['available']);
		$this->assertSame('', $data['total_html']);
		$this->assertSame('', $data['continue_url']);
		$this->assertSame('The dates you selected include a booked day.', $data['message']);
	}

	/**
	 * R10: a single day is the form's rule, not "1 day".
	 *
	 * @dataProvider refused_dates
	 */
	public function test_windows_the_form_refuses_have_no_total(string $start, string $end): void
	{
		$data = $this->quote('' === $start ? '' : $this->resolve($start), $this->resolve($end))['data'];

		$this->assertFalse($data['priced']);
		$this->assertFalse($data['available']);
		$this->assertSame(0, $data['days']);
		$this->assertSame('', $data['total_html']);
		$this->assertSame('', $data['continue_url']);
		$this->assertNotSame('', $data['message']);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function refused_dates(): array
	{
		return array(
			'same day'      => array( '+10', '+10' ),
			'wrong order'   => array( '+12', '+10' ),
			'past'          => array( '-5', '-3' ),
			'malformed'     => array( 'tomorrow', '+3' ),
			'not a day'     => array( '2099-02-31', '+3' ),
			'empty'         => array( '', '+3' ),
		);
	}

	private function resolve(string $value): string
	{
		return 1 === preg_match('/^[+-]\d+$/', $value) ? (string) wp_date('Y-m-d', time() + (int) $value * DAY_IN_SECONDS) : $value;
	}

	/**
	 * The quote does not silently price the search strip's default window.
	 */
	public function test_malformed_dates_are_not_replaced_by_the_default_window(): void
	{
		$data = $this->quote('garbage', 'garbage')['data'];

		$this->assertFalse($data['priced']);
		$this->assertSame('', $data['range_label']);
	}

	public function test_quote_shares_the_vehicle_quote_bucket(): void
	{
		for ($i = 0; $i < 30; $i++) {
			\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit('vehicle_quote', 30, 60);
		}

		$response = $this->quote($this->day(10), $this->day(12));

		$this->assertFalse($response['success']);
		$this->assertArrayNotHasKey('total_html', (array) $response['data']);
	}

	/**
	 * R13: a closed vehicle is readable but cannot be quoted.
	 */
	public function test_quote_refuses_a_closed_vehicle(): void
	{
		update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_STATUS, 'maintenance');

		$data = $this->quote($this->day(10), $this->day(12))['data'];

		$this->assertFalse($data['priced']);
		$this->assertFalse($data['available']);
		$this->assertSame('', $data['total_html']);
		$this->assertSame('', $data['continue_url']);
		$this->assertNotSame('', $data['message']);
	}

	/**
	 * m16's other side: the vehicle page draws no months its endpoint would refuse.
	 */
	public function test_transfer_only_vehicle_page_has_no_calendar(): void
	{
		$transfer = $this->vehicle('Transfer Only');
		update_post_meta($transfer, MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');

		$page = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $transfer . '"]');
		$this->assertStringContainsString('Transfer Only', $page, 'Positive control: the page renders.');
		$this->assertStringNotContainsString('data-rvs-vd-months', $page);
		$this->assertStringNotContainsString('data-rvs-month-nav', $page);

		$this->assertStringContainsString('data-rvs-vd-months', do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '"]'), 'Positive control: a rental vehicle keeps it.');
	}

	/**
	 * Mistake 27, m16: every endpoint on the rental calendar, and the vehicle page's.
	 *
	 * @dataProvider vehicle_endpoints
	 */
	public function test_a_transfer_only_vehicle_is_not_found(string $action, string $nonce): void
	{
		$transfer = $this->vehicle('Transfer Only');
		update_post_meta($transfer, MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');

		$response = $this->call(
			$action,
			array(
				'nonce'      => wp_create_nonce($nonce),
				'vehicle_id' => (string) $transfer,
				'year'       => '2099',
				'month'      => '3',
			)
		);

		$this->assertFalse($response['success']);
		$this->assertSame('Vehicle not found.', $response['data']['message']);
		$this->assertStringNotContainsString('Transfer Only', (string) $this->_last_response);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function vehicle_endpoints(): array
	{
		return array(
			'months'       => array( 'mhmrentiva_calendar_months', 'mhmrentiva_availability_nonce' ),
			'quote'        => array( 'mhmrentiva_calendar_quote', 'mhmrentiva_availability_nonce' ),
			'vehicle info' => array( 'mhmrentiva_get_vehicle_info', 'mhmrentiva_availability_nonce' ),
			'vd months'    => array( 'mhmrentiva_vd_months', 'mhmrentiva_vd_nonce' ),
			'vd quote'     => array( 'mhmrentiva_vd_quote', 'mhmrentiva_vd_nonce' ),
		);
	}

	/**
	 * @dataProvider calendar_actions
	 */
	public function test_a_bad_nonce_answers_nothing(string $action): void
	{
		$response = $this->call(
			$action,
			array(
				'nonce'      => 'nope',
				'vehicle_id' => (string) $this->vehicle_id,
			)
		);

		$this->assertFalse($response['success']);
		$this->assertSame('Security check failed.', $response['data']['message']);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function calendar_actions(): array
	{
		return array(
			'months'       => array( 'mhmrentiva_calendar_months' ),
			'quote'        => array( 'mhmrentiva_calendar_quote' ),
			'vehicle info' => array( 'mhmrentiva_get_vehicle_info' ),
		);
	}

	/**
	 * R11: the slim shape, plain text in JSON (the script owns escaping; a translated
	 * apostrophe used to reach the screen as "&#039;").
	 */
	public function test_vehicle_info_is_the_slim_shape_in_plain_text(): void
	{
		$id = $this->vehicle("L'Auto");

		$data = $this->call('mhmrentiva_get_vehicle_info', array( 'vehicle_id' => (string) $id ))['data'];

		$this->assertSame(array( 'bookable', 'id', 'image', 'price_html', 'title' ), $this->sorted_keys($data));
		$this->assertSame($id, $data['id']);
		$this->assertSame("L'Auto", $data['title']);
		$this->assertTrue($data['bookable']);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return list<string>
	 */
	private function sorted_keys(array $data): array
	{
		$keys = array_keys($data);
		sort($keys);
		return $keys;
	}

	public function test_months_marks_a_posted_selection_and_week_start(): void
	{
		$data = $this->call(
			'mhmrentiva_calendar_months',
			array(
				'vehicle_id'     => (string) $this->vehicle_id,
				'start_month'    => '2099-03',
				'months_to_show' => '1',
				'week_start'     => '0',
				'start_date'     => '2099-03-10',
				'end_date'       => '2099-03-12',
			)
		)['data'];

		$this->assertStringContainsString('is-range-start', $data['html']);
		$this->assertStringContainsString('is-range-end', $data['html']);
		$this->assertStringContainsString('rvs-cal__btn', $data['html']);
		$this->assertSame(1, preg_match('#<th scope="col" abbr="Sunday">#', $data['html']));
		// The 1st of March 2099 is a Sunday: no leading blank when the week starts on it.
		$this->assertStringNotContainsString('<tbody><tr><td class="rvs-cal__day is-empty">', str_replace(array( "\n", "\t" ), '', $data['html']));
	}

	/**
	 * m3: live, no cached grid in front of a new booking.
	 */
	public function test_months_see_a_new_block_at_once(): void
	{
		$post = array(
			'vehicle_id'     => (string) $this->vehicle_id,
			'start_month'    => '2099-03',
			'months_to_show' => '1',
		);

		$this->assertStringNotContainsString('is-closed', $this->call('mhmrentiva_calendar_months', $post)['data']['html']);

		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', array( '2099-03-15' ));

		// Ç6: a day blocked by hand is "Closed", no longer painted as booked.
		$this->assertStringContainsString('is-closed', $this->call('mhmrentiva_calendar_months', $post)['data']['html']);
	}

	public function test_old_endpoint_and_data_paths_are_gone(): void
	{
		$this->assertFalse(has_action('wp_ajax_nopriv_mhmrentiva_availability_unified'));
		$this->assertFalse(has_action('save_post_mhmrentiva_booking', array( AvailabilityCalendar::class, 'clear_availability_cache' )));
		foreach (array( 'get_month_name', 'get_availability_data', 'calculate_calendar_pricing', 'enqueue_assets_once' ) as $method) {
			$this->assertSame(
				false,
				method_exists(AvailabilityCalendar::class, $method) && ( new \ReflectionMethod(AvailabilityCalendar::class, $method) )->getDeclaringClass()->getName() === AvailabilityCalendar::class,
				"$method is gone from the class."
			);
		}
	}

	public function test_localized_data_is_what_the_script_needs(): void
	{
		$method = new \ReflectionMethod(AvailabilityCalendar::class, 'get_localized_data');
		$method->setAccessible(true);
		$data = $method->invoke(null);

		$this->assertNotFalse(wp_verify_nonce($data['nonce'], 'mhmrentiva_availability_nonce'));
		$this->assertSame(admin_url('admin-ajax.php'), $data['ajax_url']);
		$this->assertArrayNotHasKey('showPricing', $data, 'Each calendar reads its own root.');
		$this->assertArrayNotHasKey('showBookingButton', $data);
		$this->assertArrayNotHasKey('dateFormat', $data, 'The server formats every date.');
		$this->assertArrayHasKey('pickReturn', $data['strings']);
		$this->assertArrayNotHasKey('favoriteNonce', $data);
		$this->assertSame(12, CalendarView::MAX_MONTH_SPAN);
	}
}
