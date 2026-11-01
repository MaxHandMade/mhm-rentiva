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
	 * Regression guard: the calendar's link comes from the server (PR E, r2 M2) and
	 * carries the raw base, not one escaped for HTML.
	 */
	public function test_calendar_quote_link_carries_raw_base(): void
	{
		$this->set_booking_setting('https://example.test/book?a=1&b=2');
		$id = $this->vehicle(array( '_mhmrentiva_price_per_day' => '100' ));

		$quote = \MHMRentiva\Admin\Frontend\Shortcodes\CalendarView::quote(
			$id,
			\MHMRentiva\Admin\Booking\Helpers\SiteTime::days_ahead(10),
			\MHMRentiva\Admin\Booking\Helpers\SiteTime::days_ahead(12)
		);

		$this->assertStringContainsString('&b=2', $quote['continue_url']);
		$this->assertStringNotContainsString('&#038;', $quote['continue_url']);
	}
}
