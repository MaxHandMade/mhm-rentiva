<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * What the booking form shows before the visitor has picked any dates.
 *
 * The payment summary opened with "Daily Price: -" although the vehicle (and so
 * its daily price) was already known; the Broadsheet artboard
 * (claude.ai/design Rezervasyon-Formu) leads the summary with the daily price.
 * The vehicle summary's rating printed "3.0" through number_format() while the
 * card prints the site locale's "3,0".
 */
class BookingFormSummaryInitialStateTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
	}

	private function daily_price_cell(string $html): string
	{
		$this->assertSame(1, preg_match('/class="rv-price-value rv-daily-price"[^>]*>(.*?)<\/span>/s', $html, $m), 'Daily price cell not found.');
		return trim(wp_strip_all_tags($m[1]));
	}

	public function test_preselected_vehicle_shows_its_daily_price_at_once(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$html    = do_shortcode('[rentiva_booking_form vehicle_id="' . $vehicle . '"]');

		$cell = $this->daily_price_cell($html);
		$this->assertNotSame('-', $cell);
		$this->assertStringContainsString('100', $cell);
	}

	public function test_without_a_vehicle_the_daily_price_stays_a_dash(): void
	{
		$html = do_shortcode('[rentiva_booking_form]');

		$this->assertSame('-', $this->daily_price_cell($html));
	}

	private function rated_vehicle(): int
	{
		$vehicle = ShortcodeFixtures::vehicle();
		update_post_meta($vehicle, '_mhmrentiva_rating_average', '4.5');
		update_post_meta($vehicle, '_mhmrentiva_rating_count', '3');
		return $vehicle;
	}

	/** Same rule as the vehicle card: an unrated vehicle shows no rating, not "★ 0.0". */
	public function test_unrated_vehicle_summary_shows_no_rating(): void
	{
		$html = do_shortcode('[rentiva_booking_form vehicle_id="' . ShortcodeFixtures::vehicle() . '"]');

		$this->assertStringNotContainsString('rv-sv__rating-value', $html);
	}

	public function test_vehicle_summary_rating_uses_the_site_number_format(): void
	{
		$vehicle = $this->rated_vehicle();
		$marker  = static function ($formatted) {
			return 'ZZ' . $formatted;
		};
		add_filter('number_format_i18n', $marker);
		try {
			$html = do_shortcode('[rentiva_booking_form vehicle_id="' . $vehicle . '"]');
		} finally {
			remove_filter('number_format_i18n', $marker);
		}

		$this->assertSame(1, preg_match('/class="rv-sv__rating-value">(.*?)<\/span>/s', $html, $m), 'Rating value not rendered.');
		$this->assertStringStartsWith('ZZ', trim($m[1]));
	}
}
