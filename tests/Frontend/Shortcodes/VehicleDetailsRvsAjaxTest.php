<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_Ajax_UnitTestCase;

/**
 * Aşama 2 PR B: the vehicle page's two endpoints answer with the same
 * partials the page renders (months; booking card total, breakdown, link).
 *
 * @group ajax
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails
 */
final class VehicleDetailsRvsAjaxTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Hyundai Tucson',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize(array(
			'current_active_tab'                    => 'vehicle',
			'mhmrentiva_vehicle_base_price'         => '1.0',
			'mhmrentiva_vehicle_weekend_multiplier' => '1.0',
		)));
		$settings                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_type'] = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate'] = 10;
		update_option('mhmrentiva_settings', $settings);
		delete_transient('mhmrentiva_rate_limit_vehicle_quote');
	}

	public function tearDown(): void
	{
		$_POST = array();
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function call(string $action, array $post): array
	{
		$_POST = array_merge(array( 'nonce' => wp_create_nonce('mhmrentiva_vd_nonce') ), $post);
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}
		$decoded              = json_decode( (string) $this->_last_response, true);
		$this->_last_response = '';
		return is_array($decoded) ? $decoded : array();
	}

	private function quote(array $extra = array()): array
	{
		return $this->call('mhmrentiva_vd_quote', array_merge(array(
			'vehicle_id'  => (string) $this->vehicle_id,
			'pickup_date' => '2027-07-12',
			'pickup_time' => '10:00',
			'return_date' => '2027-07-15',
		), $extra));
	}

	public function test_quote_endpoint_returns_same_breakdown_as_page(): void
	{
		$response = $this->quote();
		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));

		$view = VehicleDetailsView::build($this->vehicle_id, array(), array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '2027-07-15' ));
		$expected = (string) Templates::render('partials/rvs/breakdown', array( 'breakdown' => $view['booking']['breakdown'], 'collapsible' => true ), true);

		$this->assertSame($expected, $response['data']['breakdown_html']);
		$this->assertSame(3, $response['data']['days']);
		$this->assertTrue($response['data']['available']);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-07-12', $response['data']['continue_url']);
		$this->assertStringContainsString('data-rvs-calendar', $response['data']['months_html']);
		$this->assertSame($view['booking']['breakdown']['total']['html'], $response['data']['total_html']);
		$this->assertSame('2027-07-15', $response['data']['window']['return_date']);
	}

	/**
	 * PR S: the quote's continue link carries the car's own pickup point; a
	 * posted location that differs is not read, and the answer's window has none.
	 */
	public function test_quote_continue_url_uses_the_cars_point(): void
	{
		$filter = static fn(): array => array(
			(object) array( 'id' => 3, 'name' => 'Zz Airport', 'city' => 'Zz City' ),
			(object) array( 'id' => 6, 'name' => 'Zz Center', 'city' => 'Zz City' ),
		);
		add_filter('mhmrentiva_locations', $filter);
		update_post_meta($this->vehicle_id, '_mhmrentiva_location_id', '3');
		try {
			$data = $this->quote(array( 'pickup_location' => '6' ))['data'] ?? array();
		} finally {
			remove_filter('mhmrentiva_locations', $filter);
		}

		parse_str( (string) wp_parse_url( (string) ( $data['continue_url'] ?? '' ), PHP_URL_QUERY), $query);
		$this->assertSame('3', $query['mhmrentiva_pickup_location'] ?? null, wp_json_encode($data));
		$this->assertArrayNotHasKey('pickup_location', (array) ( $data['window'] ?? array() ));
	}

	/**
	 * Dates in the wrong order get the message and no total
	 * -- the card never shows "1 day total $X" beside "Invalid date selection".
	 */
	public function test_quote_for_dates_in_the_wrong_order_carries_no_total(): void
	{
		$data = $this->quote(array( 'pickup_date' => '2027-07-20', 'return_date' => '2027-07-15' ))['data'] ?? array();

		$this->assertFalse($data['available'], wp_json_encode($data));
		$this->assertNotSame('', $data['message']);
		$this->assertFalse($data['priced']);
		$this->assertSame('', $data['total_html']);
		$this->assertSame('', $data['breakdown_html']);
		$this->assertSame(0, $data['days']);

		$this->assertTrue($this->quote()['data']['priced'], 'An ordered window is priced.');
	}

	public function test_quote_endpoint_reports_unavailable_window(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-07-13');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-07-14');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');

		$data = $this->quote()['data'] ?? array();

		$this->assertFalse($data['available']);
		$this->assertNotSame('', $data['message']);
	}

	private function book_the_car(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-07-13');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-07-14');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');
	}

	private function free_car(string $title): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => $title,
		));
		update_post_meta($id, '_mhmrentiva_price_per_day', '900');
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($id, '_mhmrentiva_seats', '5');
		update_post_meta($id, '_mhmrentiva_transmission', 'auto');
		update_post_meta($id, '_mhmrentiva_fuel_type', 'diesel');
		return $id;
	}

	/**
	 * Owner request (2026-11-02): a taken car's quote suggests free vehicles,
	 * as the booking form does -- each with its page for the same window, the
	 * rvs card's specs line, a daily price and an image (placeholder without one).
	 */
	public function test_quote_for_a_taken_car_suggests_free_vehicles(): void
	{
		$this->book_the_car();
		$other = $this->free_car('Renault Clio');

		$data = $this->quote()['data'] ?? array();

		$this->assertFalse($data['available']);
		$this->assertNotSame('', $data['message'], 'The red message stays.');
		$this->assertIsArray($data['alternatives'] ?? null, wp_json_encode($data));
		$found = null;
		foreach ($data['alternatives'] as $alternative) {
			if ( (int) $alternative['id'] === $other) {
				$found = $alternative;
			}
		}
		$this->assertIsArray($found, 'The free vehicle is suggested.');
		$this->assertSame('Renault Clio', $found['title']);

		$link = (string) $found['permalink'];
		$this->assertStringStartsWith(get_permalink($other), $link);
		parse_str( (string) wp_parse_url($link, PHP_URL_QUERY), $query);
		$this->assertSame('2027-07-12', $query['mhmrentiva_pickup_date'] ?? null);
		$this->assertSame('2027-07-15', $query['mhmrentiva_return_date'] ?? null);
		$this->assertSame('10:00', $query['mhmrentiva_pickup_time'] ?? null);

		$expected_specs = \MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView::specs($other, \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_limited_features($other, 3));
		$this->assertNotSame('', $expected_specs);
		$this->assertSame($expected_specs, $found['specs']);

		$this->assertNotSame('', (string) $found['price_html']);
		$this->assertSame(\MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_placeholder_image_url(), $found['image'], 'No featured image: the booking form\'s placeholder.');

		// The box's markup: the page's own partial, one link per suggestion.
		$html = (string) ( $data['alternatives_html'] ?? '' );
		$this->assertSame(count($data['alternatives']), preg_match_all('/<a [^>]*class="rvs-btn rvs-btn--ghost rvs-vd__alt-link"/', $html));
		$this->assertStringContainsString('href="' . esc_url($link) . '"', $html);
		$this->assertStringContainsString('Renault Clio', $html);
	}

	/**
	 * Owner decision (2026-11-03): a taken window's quote carries no total and
	 * no breakdown, but the car's daily price -- the card's one source of
	 * the state is `taken`.
	 */
	public function test_quote_for_a_taken_car_carries_the_daily_price_not_the_total(): void
	{
		$this->book_the_car();

		$data = $this->quote()['data'] ?? array();

		$this->assertTrue($data['taken'] ?? null, wp_json_encode($data));
		$this->assertSame('', $data['total_html']);
		$this->assertSame('', $data['breakdown_html']);
		$this->assertSame(0, $data['days']);
		$expected = (string) \MHMRentiva\Admin\Booking\Pricing\PriceDisplay::for_shop(\MHMRentiva\Admin\Booking\Pricing\RentalPricing::quote_for_days($this->vehicle_id, 1, 0)->rental_amount)['html'];
		$this->assertNotSame('', $expected);
		$this->assertSame($expected, $data['daily_html'] ?? null);
	}

	public function test_quote_for_a_free_or_refused_window_is_not_taken(): void
	{
		$data = $this->quote()['data'] ?? array();
		$this->assertFalse($data['taken'] ?? null);
		$this->assertSame('', $data['daily_html'] ?? null);
		$this->assertNotSame('', $data['total_html']);

		$past = $this->quote(array( 'pickup_date' => '2020-01-10', 'return_date' => '2020-01-12' ))['data'] ?? array();
		$this->assertFalse($past['available']);
		$this->assertFalse($past['taken'] ?? null, 'A rule refusal keeps the total.');
		$this->assertNotSame('', $past['total_html']);
	}

	public function test_quote_suggests_at_most_two_vehicles(): void
	{
		$this->book_the_car();
		$this->free_car('Free A');
		$this->free_car('Free B');
		$this->free_car('Free C');

		$data = $this->quote()['data'] ?? array();

		$this->assertCount(2, $data['alternatives'] ?? array());
		$this->assertSame(2, substr_count( (string) ( $data['alternatives_html'] ?? '' ), 'class="rvs-vd__alt"'));
	}

	public function test_quote_for_a_free_car_carries_no_alternatives(): void
	{
		$this->free_car('Renault Clio');

		$data = $this->quote()['data'] ?? array();

		$this->assertTrue($data['available']);
		$this->assertSame(array(), $data['alternatives'] ?? null);
		$this->assertSame('', $data['alternatives_html'] ?? null);
	}

	public function test_quote_refused_by_a_booking_rule_carries_no_alternatives(): void
	{
		$this->free_car('Renault Clio');
		// Taken on those dates too, so only the rule keeps the suggestions out.
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2020-01-10');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2020-01-11');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');

		// A past pickup: a rule refusal, not a taken car -- other cars are no answer.
		$data = $this->quote(array(
			'pickup_date' => '2020-01-10',
			'return_date' => '2020-01-12',
		))['data'] ?? array();

		$this->assertSame(array(), $data['alternatives'] ?? null);
	}

	public function test_months_endpoint_returns_two_months_from_the_given_one(): void
	{
		$response = $this->call('mhmrentiva_vd_months', array(
			'vehicle_id' => (string) $this->vehicle_id,
			'year'       => '2027',
			'month'      => '12',
		));

		$this->assertTrue($response['success'] ?? false);
		$this->assertSame(2, substr_count($response['data']['html'], 'data-rvs-calendar'));
		$this->assertStringContainsString('data-year="2027" data-month="12"', $response['data']['html']);
		$this->assertStringContainsString('data-year="2028" data-month="1"', $response['data']['html']);
	}

	public function test_months_endpoint_rejects_unpublished_vehicle(): void
	{
		wp_update_post(array( 'ID' => $this->vehicle_id, 'post_status' => 'draft' ));

		$this->assertFalse($this->call('mhmrentiva_vd_months', array( 'vehicle_id' => (string) $this->vehicle_id, 'year' => '2027', 'month' => '7' ))['success'] ?? true);
		$this->assertFalse($this->quote()['success'] ?? true);
	}

	public function test_password_protected_vehicle_rejected(): void
	{
		wp_update_post(array( 'ID' => $this->vehicle_id, 'post_password' => 'secret' ));

		$this->assertFalse($this->call('mhmrentiva_vd_months', array( 'vehicle_id' => (string) $this->vehicle_id, 'year' => '2027', 'month' => '7' ))['success'] ?? true);
		$this->assertFalse($this->quote()['success'] ?? true);
	}

	public function test_bad_nonce_rejected(): void
	{
		$response = $this->call('mhmrentiva_vd_quote', array( 'nonce' => 'bad', 'vehicle_id' => (string) $this->vehicle_id ));
		$this->assertFalse($response['success'] ?? true);
	}
}
