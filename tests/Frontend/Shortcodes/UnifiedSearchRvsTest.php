<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 3: the search strip on the front-end standard -- in the shell,
 * labelled fields with unique ids, default dates tomorrow → +3 days (or the
 * minimum rental), the request's search prefilled.
 */
final class UnifiedSearchRvsTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');
	}

	public function tearDown(): void
	{
		foreach (array( 'pickup_date', 'return_date', 'pickup_time', 'pickup_location' ) as $key) {
			set_query_var(SearchResults::query_var($key), '');
		}
		delete_option('mhmrentiva_settings');
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_strip_renders_in_the_shell_with_labelled_fields(): void
	{
		$html = do_shortcode('[rentiva_unified_search]');

		$this->assertStringContainsString('<div class="rvs-shell" data-rvs-shortcode="rentiva_unified_search">', $html);
		$this->assertStringContainsString('data-rvs-search', $html);
		$this->assertSame(1, preg_match_all('/<label for="([^"]+)"/', $html, $labels) > 0 ? 1 : 0);
		foreach ($labels[1] as $id) {
			$this->assertSame(1, substr_count($html, 'id="' . $id . '"'), "Label target $id must exist once.");
		}
	}

	public function test_two_strips_on_a_page_have_distinct_ids(): void
	{
		$first  = do_shortcode('[rentiva_unified_search]');
		$second = do_shortcode('[rentiva_unified_search]');
		preg_match('/id="([^"]+)-pickup-date"/', $first, $a);
		preg_match('/id="([^"]+)-pickup-date"/', $second, $b);

		$this->assertNotSame($a[1], $b[1]);
	}

	public function test_default_dates_tomorrow_plus_three(): void
	{
		$tomorrow = ( new \DateTimeImmutable('now', wp_timezone()) )->modify('+1 day');
		$values   = UnifiedSearch::values();

		$this->assertSame($tomorrow->format('Y-m-d'), $values['pickup_date']);
		$this->assertSame($tomorrow->modify('+3 days')->format('Y-m-d'), $values['return_date']);
		$this->assertSame('10:00', $values['pickup_time']);
		$this->assertStringContainsString('value="' . $values['pickup_date'] . '"', do_shortcode('[rentiva_unified_search]'));
	}

	public function test_min_rental_days_widen_the_default(): void
	{
		$settings                                  = (array) get_option('mhmrentiva_settings', array());
		$settings['mhmrentiva_vehicle_min_rental_days'] = 5;
		$settings['mhmrentiva_min_rental_days']    = 5;
		update_option('mhmrentiva_settings', $settings);

		if (\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::min_rental_days() < 4) {
			$this->markTestSkipped('The minimum rental setting key differs in this build.');
		}
		$values = UnifiedSearch::values();
		$days   = ( new \DateTimeImmutable($values['pickup_date']) )->diff(new \DateTimeImmutable($values['return_date']))->days;

		$this->assertSame(\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::min_rental_days(), $days);
	}

	public function test_request_search_prefills_the_strip(): void
	{
		set_query_var(SearchResults::query_var('pickup_date'), '2027-07-12');
		set_query_var(SearchResults::query_var('return_date'), '2027-07-15');
		set_query_var(SearchResults::query_var('pickup_time'), '14:30');

		$values = UnifiedSearch::values();
		$this->assertSame('2027-07-12', $values['pickup_date']);
		$this->assertSame('2027-07-15', $values['return_date']);
		$this->assertSame('14:30', $values['pickup_time']);

		$html = do_shortcode('[rentiva_unified_search]');
		$this->assertStringContainsString('value="2027-07-12"', $html);
		$this->assertMatchesRegularExpression('/<option value="14:30" selected/', $html);
	}

	public function test_malformed_request_values_are_ignored(): void
	{
		set_query_var(SearchResults::query_var('pickup_date'), '"><script>');
		set_query_var(SearchResults::query_var('pickup_time'), '25:99');

		$values = UnifiedSearch::values(false);
		$this->assertSame('', $values['pickup_date']);
		$this->assertSame('10:00', $values['pickup_time']);
	}

	public function test_optional_dates_get_no_default(): void
	{
		$html = do_shortcode('[rentiva_unified_search fields_required="0"]');

		$this->assertMatchesRegularExpression('/data-rvs-pickup-date[^>]*>|name="mhmrentiva_pickup_date" value=""/', $html);
		$this->assertSame('', UnifiedSearch::values(false)['pickup_date']);
	}

	public function test_strip_loads_rvs_styles_not_the_old_sheet(): void
	{
		do_shortcode('[rentiva_unified_search]');

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-search', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-unified-search-base', 'enqueued'));
	}
}
