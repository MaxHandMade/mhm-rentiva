<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_UnitTestCase;

/**
 * The vehicle-details "Book Now" link must pre-fill the booking form: the link
 * has to carry the prefixed wire names the form reads.
 */
class VehicleDetailsBookingPrefillTest extends WP_UnitTestCase
{
	private int $vehicle_id = 0;
	private int $location   = 88;

	public function setUp(): void
	{
		parent::setUp();

		BookingForm::register();
		SearchResults::register();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');

		if (! post_type_exists('mhmrentiva_vehicle')) {
			register_post_type('mhmrentiva_vehicle', array( 'public' => true ));
		}

		update_option('mhmrentiva_settings', array( 'mhmrentiva_booking_url' => home_url('/book/') ));
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');

		$this->vehicle_id = (int) $this->factory->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Prefill Vehicle',
			'meta_input'  => array(
				'_mhmrentiva_price_per_day'  => '100',
				'_mhmrentiva_vehicle_status' => 'active',
				'_mhmrentiva_location_id'    => $this->location,
			),
		));
		wp_cache_delete($this->vehicle_id, 'post_meta');
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');
		parent::tearDown();
	}

	public function test_vehicle_details_link_prefills_booking_form(): void
	{
		$html = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '"]');

		$this->assertSame(1, preg_match('#data-rvs-vd-continue\s+href="([^"]*)"#', $html, $m), 'The Continue link must render.');

		$href  = wp_specialchars_decode($m[1]);
		$query = (string) wp_parse_url($href, PHP_URL_QUERY);
		$this->go_to('/?' . $query);

		$form = do_shortcode('[rentiva_booking_form]');

		$this->assertStringContainsString('name="vehicle_id" value="' . $this->vehicle_id . '"', $form);
		$this->assertStringContainsString('name="pickup_location_id" value="' . $this->location . '"', $form);
		$this->assertDoesNotMatchRegularExpression('#rv-checkout-vehicle\s+rv-hidden#', $form);
	}
}
