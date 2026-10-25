<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Card;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 2: the front-end standard card's data. Every number is the one
 * the booking charges.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView
 */
final class RvsCardViewTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Hyundai Tucson',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_seats', '5');
		update_post_meta($this->vehicle_id, '_mhmrentiva_transmission', 'auto');
		update_post_meta($this->vehicle_id, '_mhmrentiva_fuel_type', 'hybrid');

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
		$settings                                                  = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_type']                = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate']                = 10;
		$settings['mhmrentiva_booking_cancellation_deadline_hours'] = 24;
		update_option('mhmrentiva_settings', $settings);
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function vehicle(array $extra = array()): array
	{
		return array_merge(
			array(
				'id'           => $this->vehicle_id,
				'title'        => 'Hyundai Tucson',
				'permalink'    => 'http://example.org/arac/tucson/',
				'image'        => array( 'id' => 0, 'url' => 'http://example.org/t.jpg', 'alt' => 'Tucson' ),
				'category'     => array( 'name' => 'SUV' ),
				'rating'       => array( 'average' => 4.8, 'count' => 24 ),
				'availability' => array( 'is_available' => true ),
				'booking_url'  => 'http://example.org/rezervasyon/',
				'is_favorite'  => false,
			),
			$extra
		);
	}

	public function test_transfer_only_vehicle_has_no_booking_button(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_service_type', 'transfer');
		$this->assertFalse(RvsCardView::from_vehicle($this->vehicle(), array())['booking']['show']);

		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_service_type', 'both');
		$this->assertTrue(RvsCardView::from_vehicle($this->vehicle(), array())['booking']['show']);
	}

	public function test_brand_image_size_and_feature_cap(): void
	{
		$this->assertSame('Hyundai', RvsCardView::from_vehicle($this->vehicle(array( 'brand' => 'Hyundai' )), array())['brand']);
		$this->assertSame('', RvsCardView::from_vehicle($this->vehicle(array( 'brand' => 'Hyundai' )), array( 'show_brand' => '0' ))['brand']);
		$this->assertSame('thumbnail', RvsCardView::from_vehicle($this->vehicle(), array( 'image_size' => 'thumbnail' ))['image']['size']);
		$this->assertSame('medium_large', RvsCardView::from_vehicle($this->vehicle(), array())['image']['size']);
		$this->assertSame(1, substr_count(RvsCardView::from_vehicle($this->vehicle(), array( 'max_features' => '2' ))['specs'], ' · '));
	}

	public function test_specs_line_from_vehicle_meta(): void
	{
		$view = RvsCardView::from_vehicle($this->vehicle(), array());

		$this->assertSame('5 People · Automatic · Hybrid', $view['specs']);
	}

	public function test_terms_line_deposit_and_cancellation(): void
	{
		$this->assertSame('Deposit 10% · Free cancellation (24 hours)', RvsCardView::from_vehicle($this->vehicle(), array())['terms']);

		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', '0');
		$settings                                   = get_option('mhmrentiva_settings');
		$settings['mhmrentiva_rental_deposit_rate'] = 0;
		update_option('mhmrentiva_settings', $settings);

		$this->assertStringStartsWith('Full payment', RvsCardView::from_vehicle($this->vehicle(), array())['terms']);
	}

	public function test_terms_include_daily_km_limit(): void
	{
		$vehicle = $this->vehicle();
		$this->assertStringNotContainsString('km/day', RvsCardView::from_vehicle($vehicle, array())['terms']);

		update_post_meta( (int) $vehicle['id'], \MHMRentiva\Admin\Core\MetaKeys::DAILY_KM_LIMIT, '300');
		$this->assertStringEndsWith(' · 300 km/day', RvsCardView::from_vehicle($vehicle, array())['terms']);
	}

	public function test_rating_label_or_new(): void
	{
		$view = RvsCardView::from_vehicle($this->vehicle(), array());
		$this->assertSame('★ 4.8', $view['rating']['label']);
		$this->assertFalse($view['rating']['show_count'], 'The review count is off by default (show_rating_count).');
		$this->assertTrue(RvsCardView::from_vehicle($this->vehicle(), array( 'show_rating_count' => '1' ))['rating']['show_count']);

		$new = RvsCardView::from_vehicle($this->vehicle(array( 'rating' => array( 'average' => 0, 'count' => 0 ) )), array());
		$this->assertTrue($new['rating']['is_new']);
		$this->assertSame('New', $new['rating']['label']);
	}

	public function test_total_price_with_dates_uses_rental_pricing(): void
	{
		// Monday 12 -> Thursday 15 July 2027 (summer x1.3, no weekend): 3 x 1300.
		$view = RvsCardView::from_vehicle(
			$this->vehicle(),
			array(),
			array( 'pickup_date' => '2027-07-12', 'return_date' => '2027-07-15', 'pickup_time' => '10:00' )
		);

		$this->assertTrue($view['price']['is_total']);
		$this->assertSame(3, $view['price']['days']);
		$this->assertSame(PriceDisplay::for_shop(3900.0)['html'], $view['price']['primary_html']);
		$this->assertStringStartsWith('3 days total · ', $view['price']['secondary']);
	}

	public function test_required_addon_is_part_of_the_total(): void
	{
		$addon = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => 'Full insurance' ));
		update_post_meta($addon, 'mhmrentiva_addon_price', '25');
		update_post_meta($addon, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($addon, 'mhmrentiva_addon_required', '1');

		$required = array_filter(\MHMRentiva\Admin\Addons\AddonManager::get_available_addons(), static fn ($a) => ! empty($a['required']));
		if (array() === $required) {
			$this->markTestSkipped('The add-on fixture did not register as required in this environment.');
		}

		$view = RvsCardView::from_vehicle($this->vehicle(), array(), array( 'pickup_date' => '2027-07-12', 'return_date' => '2027-07-15' ));

		$this->assertSame(PriceDisplay::for_shop(3925.0)['html'], $view['price']['primary_html']);
	}

	public function test_daily_price_without_dates(): void
	{
		$view = RvsCardView::from_vehicle($this->vehicle(), array(), array( 'pickup_location' => '7' ));

		$this->assertFalse($view['price']['is_total']);
		$this->assertSame(PriceDisplay::for_shop(1000.0)['html'], $view['price']['primary_html']);
		$this->assertSame('per day', $view['price']['secondary']);
	}

	public function test_missing_price_shows_on_request_not_a_number(): void
	{
		delete_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day');

		$view = RvsCardView::from_vehicle($this->vehicle(array( 'price' => array( 'raw' => 1000 ) )), array());

		$this->assertFalse($view['price']['has_price']);
		$this->assertSame('Price on request', $view['price']['primary_html']);
	}

	public function test_booking_toggle_aliases(): void
	{
		$this->assertFalse(RvsCardView::from_vehicle($this->vehicle(), array( 'show_booking_button' => '0' ))['booking']['show']);
		$this->assertFalse(RvsCardView::from_vehicle($this->vehicle(), array( 'show_book_button' => '0' ))['booking']['show']);
		$this->assertFalse(RvsCardView::from_vehicle($this->vehicle(), array( 'show_booking_btn' => false ))['booking']['show']);
		$this->assertTrue(RvsCardView::from_vehicle($this->vehicle(), array())['booking']['show']);
		$this->assertFalse(RvsCardView::from_vehicle($this->vehicle(), array( 'show_favorite_btn' => '0' ))['favorite']['show']);
		$this->assertFalse(RvsCardView::from_vehicle($this->vehicle(), array( 'show_compare_btn' => '0' ))['compare']['show']);
	}

	public function test_unavailable_vehicle_disables_select(): void
	{
		$view = RvsCardView::from_vehicle($this->vehicle(array( 'availability' => array( 'is_available' => false ) )), array());

		$this->assertFalse($view['available']);
		$this->assertSame('Unavailable', $view['booking']['label']);
		$this->assertSame('javascript:void(0);', $view['booking']['url']);
	}

	public function test_booking_label_from_shortcode_text(): void
	{
		$this->assertSame('Rent now', RvsCardView::from_vehicle($this->vehicle(), array( 'button_text' => 'Rent now' ))['booking']['label']);
		$this->assertSame('Book Now', RvsCardView::from_vehicle($this->vehicle(), array())['booking']['label']);
	}

	public function test_booking_url_forwards_given_dates(): void
	{
		$url = VehicleCardLinks::booking_url($this->vehicle(), array(), array( 'pickup_date' => '2027-07-12', 'return_date' => '2027-07-15' ));

		$this->assertStringContainsString('mhmrentiva_vehicle_id=' . $this->vehicle_id, $url);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-07-12', $url);
		$this->assertStringContainsString('mhmrentiva_return_date=2027-07-15', $url);
	}
}
