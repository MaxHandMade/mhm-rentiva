<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
use MHMRentiva\Tests\Support\SiteClock;
use WP_UnitTestCase;

/**
 * Ç6 (approved 2026-11-11): the availability calendar quotes a partly free day.
 *
 * A day a booking returns on is free from return + buffer; picked as the
 * start, the quote moves the pickup time there and says so. A day a booking
 * starts on is free until pickup - buffer; it can only end a rental, and only
 * when the return time (= the pickup time) is not later. Any other taken day
 * in the window keeps today's "include a booked day" answer.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\CalendarView::quote
 */
final class CalendarPartialQuoteTest extends WP_UnitTestCase
{
	use SiteClock;

	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		$this->pin_site_timezone('Europe/Istanbul');
		update_option('time_format', 'H:i');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');
		$this->vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'meta_input'  => array( '_mhmrentiva_price_per_day' => '100' ),
		));
		// Booked day 10 10:00 -> day 12 10:00; buffer 60 (default).
		$this->booking(SiteTime::days_ahead(10), '10:00', SiteTime::days_ahead(12), '10:00');
	}

	public function tearDown(): void
	{
		delete_option('time_format');
		parent::tearDown();
	}

	private function booking(string $from, string $from_time, string $to, string $to_time): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($id, '_mhmrentiva_vehicle_id', $this->vehicle);
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_pickup_date', $from);
		update_post_meta($id, '_mhmrentiva_pickup_time', $from_time);
		update_post_meta($id, '_mhmrentiva_dropoff_date', $to);
		update_post_meta($id, '_mhmrentiva_dropoff_time', $to_time);
		update_post_meta($id, '_mhmrentiva_start_ts', (int) SiteTime::instant($from, $from_time));
		update_post_meta($id, '_mhmrentiva_end_ts', (int) SiteTime::instant($to, $to_time));
	}

	public function test_starting_on_a_return_day_moves_the_pickup_time_and_says_so(): void
	{
		$quote = CalendarView::quote($this->vehicle, SiteTime::days_ahead(12), SiteTime::days_ahead(15));

		$this->assertTrue($quote['available'], (string) $quote['message']);
		$this->assertTrue($quote['priced']);
		$this->assertSame('Pickup and return at 11:00', $quote['time_hint']);
		$this->assertStringContainsString('11:00', $quote['partial_note']);
		$this->assertMatchesRegularExpression('/pickup_time=11(%3A|:)00/', $quote['continue_url']);
	}

	public function test_ending_on_a_pickup_day_after_its_free_time_is_refused_with_the_time(): void
	{
		$quote = CalendarView::quote($this->vehicle, SiteTime::days_ahead(7), SiteTime::days_ahead(10));

		$this->assertFalse($quote['available']);
		$this->assertStringContainsString('09:00', $quote['message']);
		$this->assertSame('', $quote['continue_url']);
	}

	public function test_a_taken_day_inside_the_window_is_refused_as_before(): void
	{
		$quote = CalendarView::quote($this->vehicle, SiteTime::days_ahead(9), SiteTime::days_ahead(13));

		$this->assertFalse($quote['available']);
		$this->assertSame('The dates you selected include a booked day.', $quote['message']);
	}

	public function test_a_day_free_only_until_a_time_cannot_start_a_rental(): void
	{
		$quote = CalendarView::quote($this->vehicle, SiteTime::days_ahead(10), SiteTime::days_ahead(11));

		$this->assertFalse($quote['available']);
		$this->assertSame('The dates you selected include a booked day.', $quote['message']);
	}

	public function test_a_free_window_has_no_note(): void
	{
		$quote = CalendarView::quote($this->vehicle, SiteTime::days_ahead(20), SiteTime::days_ahead(22));

		$this->assertTrue($quote['available'], (string) $quote['message']);
		$this->assertSame('', $quote['partial_note']);
		$this->assertSame('Pickup and return at 10:00', $quote['time_hint']);
	}
}
