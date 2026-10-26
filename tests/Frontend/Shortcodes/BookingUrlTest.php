<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\BookingUrl;
use WP_UnitTestCase;

/**
 * BookingUrl is the single place the booking-page address and the
 * "book this vehicle" link are built. The base URL is RAW (no HTML entities),
 * so JavaScript can append its own query args; the vehicle link carries the
 * prefixed wire names the booking form reads.
 */
class BookingUrlTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');
		delete_option('mhmrentiva_settings');
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');

		if (! post_type_exists('mhmrentiva_vehicle')) {
			register_post_type('mhmrentiva_vehicle', array( 'public' => true ));
		}
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');
		parent::tearDown();
	}

	private function set_booking_setting(string $value): void
	{
		update_option('mhmrentiva_settings', array( 'mhmrentiva_booking_url' => $value ));
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');
	}

	private function vehicle(array $meta = array(), int $author = 0): int
	{
		$args = array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'BookingUrl Vehicle',
			'meta_input'  => $meta,
		);
		if ($author > 0) {
			$args['post_author'] = $author;
		}
		return (int) $this->factory->post->create($args);
	}

	public function test_base_url_is_raw(): void
	{
		$this->set_booking_setting('https://example.test/book?a=1&b=2');

		$this->assertSame('https://example.test/book?a=1&b=2', BookingUrl::base_url());
	}

	public function test_base_url_physical_page_branch(): void
	{
		$page_id = $this->factory->post->create(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Book',
			'post_content' => '[rentiva_booking_form]',
		));
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');

		$this->assertSame(get_permalink($page_id), BookingUrl::base_url());
	}

	public function test_base_url_filter_branch(): void
	{
		add_filter('mhmrentiva_shortcode_url', static fn() => 'https://x.test/b?a=1&b=2');
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');

		$url = BookingUrl::base_url();
		remove_all_filters('mhmrentiva_shortcode_url');

		$this->assertStringContainsString('&b=2', $url);
		$this->assertStringNotContainsString('&#038;', $url);
	}

	public function test_base_url_home_fallback_when_setting_rejected(): void
	{
		$this->set_booking_setting('javascript:alert(1)');

		$this->assertSame(home_url('/'), BookingUrl::base_url());
	}

	public function test_for_vehicle_uses_prefixed_keys(): void
	{
		$this->set_booking_setting('https://example.test/book');
		$location = 77;
		$id       = $this->vehicle(array( '_mhmrentiva_location_id' => $location ));

		parse_str((string) wp_parse_url(BookingUrl::for_vehicle($id), PHP_URL_QUERY), $q);

		$this->assertSame((string) $id, $q['mhmrentiva_vehicle_id'] ?? null);
		// No pickup location: the booking form takes it from the car.
		$this->assertArrayNotHasKey('mhmrentiva_pickup_location', $q);
		$this->assertArrayNotHasKey('vehicle_id', $q);
		$this->assertArrayNotHasKey('pickup_location', $q);
	}

	public function test_location_for_hierarchy(): void
	{
		$this->set_booking_setting('https://example.test/book');

		// Vehicle level.
		$own = $this->vehicle(array( '_mhmrentiva_location_id' => 77 ));
		$this->assertSame(77, BookingUrl::location_for($own));

		// Vendor level.
		$vendor = (int) $this->factory->user->create();
		update_user_meta($vendor, '_mhmrentiva_vendor_location_id', 55);
		$id = $this->vehicle(array(), $vendor);
		$this->assertSame(55, BookingUrl::location_for($id));

		// Global default level.
		$no_loc_vendor = (int) $this->factory->user->create();
		update_option('mhmrentiva_settings', array(
			'mhmrentiva_booking_url'             => 'https://example.test/book',
			'mhmrentiva_default_rental_location' => 33,
		));
		ShortcodeUrlManager::clear_cache('rentiva_booking_form');
		$id2 = $this->vehicle(array(), $no_loc_vendor);
		$this->assertSame(33, BookingUrl::location_for($id2));

		// None.
		$this->set_booking_setting('https://example.test/book');
		$this->assertSame(0, BookingUrl::location_for($id2));
	}

	/**
	 * Regression guard (green before and after): the calendar hands JS the raw base.
	 */
	public function test_calendar_localize_carries_raw_base(): void
	{
		$this->set_booking_setting('https://example.test/book?a=1&b=2');
		$id = $this->vehicle(array( '_mhmrentiva_price_per_day' => '100' ));

		// Earlier tests may have enqueued the calendar assets already; start clean.
		$previous = $GLOBALS['wp_scripts'] ?? null;
		AbstractShortcode::reset_enqueued_assets_for_tests();
		$GLOBALS['wp_scripts'] = new \WP_Scripts();

		$data = '';
		try {
			do_shortcode('[rentiva_availability_calendar vehicle_id="' . $id . '"]');

			foreach (array_keys(wp_scripts()->registered) as $handle) {
				$d = wp_scripts()->get_data($handle, 'data');
				if (is_string($d) && false !== strpos($d, 'mhmRentivaAvailability')) {
					$data = $d;
					break;
				}
			}
		} finally {
			$GLOBALS['wp_scripts'] = $previous;
			AbstractShortcode::reset_enqueued_assets_for_tests();
		}
		$this->assertNotSame('', $data, 'Calendar must localize mhmRentivaAvailability.');

		$json = rtrim(trim(substr($data, (int) strpos($data, '=') + 1)), ';');
		$obj  = json_decode($json, true);

		$this->assertIsArray($obj);
		$this->assertStringContainsString('&b=2', (string) ( $obj['bookingPageUrl'] ?? '' ));
		$this->assertStringNotContainsString('&#038;', (string) ( $obj['bookingPageUrl'] ?? '' ));
	}
}
