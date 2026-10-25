<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

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

	public function test_full_payment_condition_without_deposit(): void
	{
		$settings                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_rate'] = 0;
		update_option('mhmrentiva_settings', $settings);

		$this->assertSame('Full payment', $this->view()['conditions'][0]['title']);
		$this->assertNull($this->view()['booking']['breakdown']['deposit']);
	}

	public function test_highlights_follow_selected_detail_fields_and_k11_values(): void
	{
		update_post_meta($this->vehicle_id, MetaKeys::DAILY_KM_LIMIT, '300');
		$labels = array_column($this->view()['highlights'], 'value', 'label');

		$this->assertArrayHasKey('Seats', $labels);
		$this->assertSame('300 km/day', $labels['Daily km limit'] ?? '');
		$this->assertArrayNotHasKey('Minimum driver age', $labels, 'An empty K11 field is not drawn.');
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

	public function test_dealer_line_empty_without_pro_data(): void
	{
		$this->assertSame('', $this->view()['dealer_line']);
		$this->assertSame(array(), $this->view()['booking']['locations']);
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
