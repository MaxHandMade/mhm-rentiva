<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView;
use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Aşama 2 PR B: the vehicle page's data -- one view model the template only
 * prints. Every amount is the one the booking charges; nothing is invented.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails
 */
final class VehicleDetailsViewTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	/** Monday 12 -> Thursday 15 July 2027: three weekdays at 1000. */
	private const WINDOW = array(
		'pickup_date'     => '2027-07-12',
		'pickup_time'     => '10:00',
		'return_date'     => '2027-07-15',
		'pickup_location' => '',
	);

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Hyundai Tucson',
			'post_name'   => 'hyundai-tucson',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_seats', '5');
		update_post_meta($this->vehicle_id, '_mhmrentiva_transmission', 'auto');
		update_post_meta($this->vehicle_id, '_mhmrentiva_fuel_type', 'hybrid');

		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize(array(
			'current_active_tab'                    => 'vehicle',
			'mhmrentiva_vehicle_base_price'         => '1.0',
			'mhmrentiva_vehicle_weekend_multiplier' => '1.0',
		)));
		$settings                                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_type']                 = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate']                 = 10;
		$settings['mhmrentiva_booking_cancellation_deadline_hours'] = 24;
		update_option('mhmrentiva_settings', $settings);
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		set_query_var('mhmrentiva_vehicle_slug', '');
		parent::tearDown();
	}

	private function booking(string $from, string $to, string $status = 'confirmed'): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($id, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($id, '_mhmrentiva_pickup_date', $from);
		update_post_meta($id, '_mhmrentiva_dropoff_date', $to);
		update_post_meta($id, '_mhmrentiva_status', $status);
	}

	private function view(array $window = self::WINDOW, array $atts = array()): array
	{
		return VehicleDetailsView::build($this->vehicle_id, $atts, $window);
	}

	public function test_cache_key_differs_per_vehicle_slug(): void
	{
		$factors = new \ReflectionMethod(VehicleDetails::class, 'cache_request_factors');
		$factors->setAccessible(true);

		set_query_var('mhmrentiva_vehicle_slug', 'hyundai-tucson');
		$a = $factors->invoke(null);
		set_query_var('mhmrentiva_vehicle_slug', 'fiat-egea');
		$b = $factors->invoke(null);

		$this->assertNotSame($a, $b);
		$this->assertSame('fiat-egea', $b['vehicle_slug']);
	}

	public function test_default_window_matches_search_strip(): void
	{
		$view     = $this->view(array());
		$defaults = UnifiedSearch::values();

		$this->assertSame($defaults['pickup_date'], $view['booking']['window']['pickup_date']);
		$this->assertSame($defaults['return_date'], $view['booking']['window']['return_date']);
		$this->assertSame('10:00', $view['booking']['window']['pickup_time']);
	}

	public function test_conditions_from_real_data_only(): void
	{
		$titles = array_column($this->view()['conditions'], 'title');
		$this->assertSame(array( 'Deposit 10%', 'Free cancellation' ), $titles);

		$settings                                             = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_booking_fuel_policy_note']      = 'Full to full.';
		$settings['mhmrentiva_booking_security_deposit_note'] = '5,000 TL card hold at pickup.';
		update_option('mhmrentiva_settings', $settings);

		$conditions = $this->view()['conditions'];
		$this->assertSame(array( 'Deposit 10%', 'Free cancellation', 'Security deposit', 'Fuel policy' ), array_column($conditions, 'title'));
		$this->assertSame('Full to full.', $conditions[3]['text']);
	}

	public function test_card_says_deposit_or_full_payment_is_chosen_at_checkout(): void
	{
		$booking = $this->view()['booking'];

		$this->assertSame('Choose deposit or full payment at the payment step', $booking['deposit_text']);
		$this->assertSame('Deposit (10%)', $booking['breakdown']['deposit']['label']);
	}

	public function test_full_payment_condition_without_deposit(): void
	{
		$settings                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_rate'] = 0;
		update_option('mhmrentiva_settings', $settings);

		$this->assertSame('Full payment', $this->view()['conditions'][0]['title']);
		$this->assertNull($this->view()['booking']['breakdown']['deposit']);
	}

	/**
	 * Owner decision (PR B review): the highlights are exactly the admin's
	 * "Vehicle Detail Highlighted Features" selection -- nothing is appended.
	 */
	public function test_highlights_are_only_the_admin_selection(): void
	{
		update_post_meta($this->vehicle_id, MetaKeys::DAILY_KM_LIMIT, '300');
		update_post_meta($this->vehicle_id, '_mhmrentiva_features', array( 'bluetooth' ));
		$this->select_detail_fields(array( array( 'detail', 'seats' ) ));

		$this->assertSame(array( 'seats' ), array_column($this->view()['highlights'], 'key'), 'A filled K11 field or an active feature is not added unasked.');

		$this->select_detail_fields(array( array( 'detail', 'seats' ), array( 'detail', 'daily_km_limit' ), array( 'detail', 'min_driver_age' ) ));
		$labels = array_column($this->view()['highlights'], 'value', 'label');
		$this->assertSame('300 km/day', $labels['Daily km limit'] ?? '');
		$this->assertArrayNotHasKey('Minimum driver age', $labels, 'A selected but empty field is not drawn.');
	}

	/**
	 * @param list<array{0: string, 1: string}> $fields type, key
	 */
	private function select_detail_fields(array $fields): void
	{
		$settings = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_vehicle_detail_fields'] = array_map(static fn(array $f): array => array( 'type' => $f[0], 'key' => $f[1] ), $fields);
		update_option('mhmrentiva_settings', $settings);
	}

	public function test_booked_dates_cover_pickup_to_return(): void
	{
		$this->booking('2027-07-01', '2027-07-03');
		$this->booking('2027-07-20', '2027-07-21', 'cancelled');

		$this->assertSame(array( '2027-07-01', '2027-07-02', '2027-07-03' ), VehicleDetails::booked_dates($this->vehicle_id, '2027-07-01', '2027-08-31'));
		$this->assertSame(array(), VehicleDetails::booked_dates($this->vehicle_id, '2027-08-01', '2027-08-31'));
	}

	public function test_months_start_at_the_window_and_mark_it(): void
	{
		$this->booking('2027-07-20', '2027-07-21');
		$months = $this->view()['months'];

		$this->assertCount(2, $months);
		$this->assertSame(array( 7, 8 ), array( $months[0]['month'], $months[1]['month'] ));
		$days = array();
		foreach ($months[0]['weeks'] as $week) {
			foreach (array_filter($week) as $cell) {
				$days[ $cell['date'] ] = $cell['state'];
			}
		}
		$this->assertSame('selected', $days['2027-07-13']);
		$this->assertSame('booked', $days['2027-07-20']);
	}

	public function test_booking_continue_url_carries_window(): void
	{
		$url = $this->view()['booking']['continue_url'];

		$this->assertStringContainsString('mhmrentiva_vehicle_id=' . $this->vehicle_id, $url);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-07-12', $url);
		$this->assertStringContainsString('mhmrentiva_return_date=2027-07-15', $url);
		$this->assertStringContainsString('mhmrentiva_pickup_time=10', $url);
	}

	public function test_total_and_deposit_match_rental_pricing(): void
	{
		$booking = $this->view()['booking'];

		$this->assertSame(3, $booking['days']);
		$this->assertSame(3000.0, $booking['breakdown']['total']['amount']);
		$this->assertSame(PriceDisplay::for_cart(3000.0)['html'], $booking['breakdown']['total']['html']);
		$this->assertSame(300.0, $booking['breakdown']['deposit']['amount']);
		$this->assertTrue($booking['available']);
	}

	public function test_unavailable_window_is_reported(): void
	{
		$this->booking('2027-07-13', '2027-07-14');
		$booking = $this->view()['booking'];

		$this->assertFalse($booking['available']);
		$this->assertNotSame('', $booking['message']);
		$this->assertSame(3000.0, $booking['breakdown']['total']['amount'], 'The total stays visible.');
	}

	public function test_window_outside_rental_rules_is_not_bookable(): void
	{
		$settings                                     = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_vehicle_max_rental_days'] = 30;
		update_option('mhmrentiva_settings', $settings);

		$booking = $this->view(array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '2027-08-21' ))['booking'];

		$this->assertFalse($booking['available']);
		$this->assertStringContainsString('30', $booking['message']);
	}

	public function test_far_return_date_does_not_loop_for_years(): void
	{
		// Out of any sane range: the search strip's default window instead.
		$started = microtime(true);
		$view    = $this->view(array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '9999-12-31' ));
		$this->assertLessThan(2.0, microtime(true) - $started);
		$this->assertSame(UnifiedSearch::values()['return_date'], $view['booking']['window']['return_date']);

		// In range but years long: quoted up to the cap, reported as not bookable.
		$view = $this->view(array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '2035-01-01' ));
		$this->assertLessThanOrEqual(366, $view['booking']['days']);
		$this->assertFalse($view['booking']['available']);
	}

	public function test_edited_window_with_return_before_pickup_is_reported_not_replaced(): void
	{
		$booking = $this->view(array( 'pickup_date' => '2027-07-15', 'pickup_time' => '10:00', 'return_date' => '2027-07-12' ))['booking'];

		$this->assertSame('2027-07-15', $booking['window']['pickup_date'], 'The visitor\'s dates are kept, not swapped for the default window.');
		$this->assertSame('2027-07-12', $booking['window']['return_date']);
		$this->assertFalse($booking['available']);
		$this->assertNotSame('', $booking['message']);
	}

	public function test_time_outside_the_half_hour_options_falls_back(): void
	{
		$window = VehicleDetailsView::window(array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:15', 'return_date' => '2027-07-15' ));

		$this->assertSame(UnifiedSearch::values()['pickup_time'], $window['pickup_time'], 'The time select only offers :00 and :30.');
	}

	public function test_pickup_beyond_the_advance_window_is_not_bookable(): void
	{
		$settings                                          = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_vehicle_advance_booking_days'] = 30;
		update_option('mhmrentiva_settings', $settings);
		$pickup = gmdate('Y-m-d', strtotime('+60 days'));
		$return = gmdate('Y-m-d', strtotime('+63 days'));

		$booking = $this->view(array( 'pickup_date' => $pickup, 'pickup_time' => '10:00', 'return_date' => $return ))['booking'];

		$this->assertFalse($booking['available']);
		$this->assertStringContainsString('30', $booking['message']);
	}

	/**
	 * The last bookable day is today plus N calendar days in the site's
	 * timezone -- the same helper "Earliest free" uses for its horizon.
	 */
	public function test_advance_limit_is_n_calendar_days_from_today(): void
	{
		$settings                                            = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_vehicle_advance_booking_days'] = 30;
		update_option('mhmrentiva_settings', $settings);
		$last  = SiteTime::days_ahead(30);
		$after = SiteTime::days_ahead(31);

		$on_last = $this->view(array( 'pickup_date' => $last, 'pickup_time' => '10:00', 'return_date' => SiteTime::days_ahead(33) ))['booking'];
		$beyond  = $this->view(array( 'pickup_date' => $after, 'pickup_time' => '10:00', 'return_date' => SiteTime::days_ahead(34) ))['booking'];

		$this->assertStringNotContainsString('days ahead', (string) $on_last['message']);
		$this->assertFalse($beyond['available']);
		$this->assertStringContainsString('30 days ahead', $beyond['message']);
	}

	public function test_past_pickup_is_not_bookable(): void
	{
		$pickup  = gmdate('Y-m-d', strtotime('-3 days'));
		$return  = gmdate('Y-m-d', strtotime('+1 day'));
		$booking = $this->view(array( 'pickup_date' => $pickup, 'pickup_time' => '10:00', 'return_date' => $return ))['booking'];

		$this->assertFalse($booking['available']);
	}

	public function test_continue_url_falls_back_to_vendor_then_global_location(): void
	{
		$this->locations(array( 7 => 'Zz Vendor Point', 9 => 'Zz Default Point' ));
		$vendor = (int) self::factory()->user->create();
		wp_update_post(array( 'ID' => $this->vehicle_id, 'post_author' => $vendor ));
		update_user_meta($vendor, MetaKeys::VENDOR_LOCATION_ID, '7');
		$this->assertStringContainsString('mhmrentiva_pickup_location=7', $this->view()['booking']['continue_url']);

		delete_user_meta($vendor, MetaKeys::VENDOR_LOCATION_ID);
		$settings                                       = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_default_rental_location'] = 9;
		update_option('mhmrentiva_settings', $settings);
		$this->assertStringContainsString('mhmrentiva_pickup_location=9', $this->view()['booking']['continue_url']);
	}

	public function test_block_json_points_at_the_rvs_stylesheet(): void
	{
		$json = json_decode( (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/blocks/vehicle-details/block.json'), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped file.
		$this->assertSame(array( 'mhm-rentiva-rvs-vehicle' ), $json['style']);
	}

	public function test_dealer_line_empty_without_pro_data(): void
	{
		$this->assertSame('', $this->view()['dealer_line']);
		$this->assertArrayNotHasKey('locations', $this->view()['booking']);
	}

	/**
	 * Rental locations: id => name, all in "Zz City".
	 *
	 * @param array<int, string> $names
	 */
	private function locations(array $names): void
	{
		add_filter(
			'mhmrentiva_locations',
			static function () use ($names): array {
				$out = array();
				foreach ($names as $id => $name) {
					$out[] = (object) array(
						'id'   => $id,
						'name' => $name,
						'city' => 'Zz City',
					);
				}
				return $out;
			}
		);
	}

	private function card(array $view): string
	{
		return (string) \MHMRentiva\Admin\Core\Utilities\Templates::render(
			'partials/vehicle-details/booking-card',
			array(
				'view'       => $view,
				'atts'       => array(),
				'show_price' => true,
			),
			true
		);
	}

	/**
	 * PR S: the card names the car's own pickup point instead of a select of
	 * every branch, and a location in the URL does not change it.
	 */
	public function test_vehicle_page_shows_its_own_pickup_point_not_a_select(): void
	{
		$this->locations(array( 3 => 'Zz Airport', 6 => 'Zz Center' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_location_id', '3');

		$view = $this->view(array_merge(self::WINDOW, array( 'pickup_location' => '6' )));
		$html = $this->card($view);

		$this->assertSame('Zz Airport', $view['booking']['pickup_return']);
		$this->assertArrayNotHasKey('locations', $view['booking']);
		$this->assertArrayNotHasKey('pickup_location', $view['booking']['window'], 'The window carries no location: the car has one.');
		$this->assertStringNotContainsString('name="pickup_location"', $html);
		$this->assertStringNotContainsString('Zz Center', $html);
		$this->assertMatchesRegularExpression('#data-rvs-vd-where[^>]*>.*Pickup and return: <strong>Zz Airport</strong>#s', $html);
	}

	/**
	 * The page's continue link carries the car's point, not the URL's (the
	 * quote endpoint's half is in VehicleDetailsRvsAjaxTest).
	 */
	public function test_continue_url_uses_the_cars_point_on_the_page(): void
	{
		$this->locations(array( 3 => 'Zz Airport', 6 => 'Zz Center' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_location_id', '3');

		$url = $this->view(array_merge(self::WINDOW, array( 'pickup_location' => '6' )))['booking']['continue_url'];

		parse_str( (string) wp_parse_url($url, PHP_URL_QUERY), $query);
		$this->assertSame('3', $query['mhmrentiva_pickup_location'] ?? null);
	}

	/**
	 * A car whose own point is not a rental location: no line and no point in
	 * the link, rather than a branch the customer cannot pick up from.
	 */
	public function test_car_without_a_rental_point_has_no_line_on_a_multi_location_site(): void
	{
		$this->locations(array( 3 => 'Zz Airport', 6 => 'Zz Center' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_location_id', '44');

		$view = $this->view(array_merge(self::WINDOW, array( 'pickup_location' => '6' )));

		$this->assertSame('', $view['booking']['pickup_return']);
		$this->assertStringNotContainsString('pickup_location', $view['booking']['continue_url']);
		$this->assertStringNotContainsString('data-rvs-vd-where', $this->card($view));
	}

	/**
	 * Single-location site: a car with no point of its own is picked up at
	 * the one there is.
	 */
	public function test_single_location_site_names_the_one_point(): void
	{
		$this->locations(array( 5 => 'Zz Only' ));

		$booking = $this->view()['booking'];

		$this->assertSame('Zz Only', $booking['pickup_return']);
		$this->assertStringContainsString('mhmrentiva_pickup_location=5', $booking['continue_url']);
	}

	/**
	 * Lite alone (no rental location): no line, no error, and a stale
	 * location meta or URL value does not travel.
	 */
	public function test_lite_alone_has_no_location_line(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_location_id', '3');

		$view = $this->view(array_merge(self::WINDOW, array( 'pickup_location' => '6' )));

		$this->assertSame('', $view['booking']['pickup_return']);
		$this->assertStringNotContainsString('pickup_location', $view['booking']['continue_url']);
		$this->assertStringNotContainsString('data-rvs-vd-where', $this->card($view));
	}

	public function test_reviews_distribution_percentages(): void
	{
		foreach (array( 5, 5, 5, 4 ) as $rating) {
			$comment = self::factory()->comment->create(array( 'comment_post_ID' => $this->vehicle_id, 'comment_type' => 'review', 'comment_approved' => '1' ));
			update_comment_meta($comment, 'mhmrentiva_rating', (string) $rating);
		}
		\MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::recalculate_and_save($this->vehicle_id);

		$reviews = $this->view()['reviews'];
		$this->assertSame(4, $reviews['count']);
		$rows = array_column($reviews['distribution'], null, 'star');
		$this->assertSame(3, $rows[5]['count']);
		$this->assertSame(75, $rows[5]['pct']);
		$this->assertSame(0, $rows[1]['pct']);
	}

	public function test_gallery_starts_with_featured_image(): void
	{
		$image = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $this->vehicle_id);
		set_post_thumbnail($this->vehicle_id, $image);

		$gallery = $this->view()['gallery'];
		$this->assertNotEmpty($gallery);
		$this->assertSame((int) $image, $gallery[0]['id']);
		$this->assertNotSame('', $gallery[0]['large']);
	}
}
