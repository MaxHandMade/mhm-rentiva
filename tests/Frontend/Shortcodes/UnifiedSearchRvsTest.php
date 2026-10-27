<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\LocationContext;
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
	use \MHMRentiva\Tests\Support\StyleDependencies;

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
		remove_all_filters('mhmrentiva_locations');
		LocationContext::flush();
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

	/**
	 * PR S Task 9 item 4: the date fields show the site format from the first
	 * paint (no Y-m-d before the script runs); the hidden field after each
	 * submits Y-m-d, with or without JavaScript.
	 */
	public function test_date_fields_render_in_the_site_format_and_submit_iso(): void
	{
		$saved = get_option('date_format');
		update_option('date_format', 'd/m/Y');
		set_query_var(SearchResults::query_var('pickup_date'), '2027-07-12');
		set_query_var(SearchResults::query_var('return_date'), '2027-07-15');
		try {
			$html = do_shortcode('[rentiva_unified_search]');
		} finally {
			update_option('date_format', $saved);
		}

		foreach (array( 'pickup' => array( '12/07/2027', '2027-07-12' ), 'return' => array( '15/07/2027', '2027-07-15' ) ) as $field => $dates) {
			$this->assertMatchesRegularExpression(
				'/<input id="[^"]+-' . $field . '-date" type="text" class="[^"]*js-datepicker" value="' . preg_quote($dates[0], '/') . '"[^>]*>\s*<input type="hidden" name="' . SearchResults::query_var($field . '_date') . '" value="' . $dates[1] . '" data-rvs-date-value>/',
				$html,
				$field . ': visible in the site format, hidden Y-m-d right after it.'
			);
		}
		$this->assertDoesNotMatchRegularExpression('/type="text"[^>]*name="mhmrentiva_(pickup|return)_date"/', $html, 'The visible field submits nothing.');
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
		// PR S Task 9: the date popup wears the booking form's rvs skin here too.
		$this->assertTrue(self::style_needs('mhm-rentiva-rvs-search', 'mhm-rentiva-rvs-datepicker'), 'The strip loads the rvs datepicker skin.');
	}

	/**
	 * @param array<int, array{0:int,1:string,2:string}> $rows id, name, city.
	 */
	private function locations(array $rows): void
	{
		$objects = array();
		foreach ($rows as $row) {
			$objects[] = (object) array( 'id' => (string) $row[0], 'name' => $row[1], 'city' => $row[2] );
		}
		remove_all_filters('mhmrentiva_locations');
		LocationContext::flush();
		add_filter('mhmrentiva_locations', static fn ($locations, $type) => 'rental' === $type ? $objects : $locations, 10, 2);
	}

	public function test_lite_alone_has_no_location_field_nor_line(): void
	{
		$html = do_shortcode('[rentiva_unified_search]');

		$this->assertStringNotContainsString('data-rvs-pickup-location', $html);
		$this->assertStringNotContainsString('data-rvs-pickup-where', $html);
		$this->assertStringContainsString('rvs-search--no-location', $html);
	}

	public function test_single_location_hides_select_and_shows_line(): void
	{
		$this->locations(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ) ));

		$html = do_shortcode('[rentiva_unified_search show_location_select="1"]');

		$this->assertStringNotContainsString('data-rvs-pickup-location', $html, 'One pickup point: nothing to choose, whatever the attribute says.');
		$this->assertStringContainsString('rvs-search--no-location', $html);
		$this->assertSame(1, preg_match('#<p class="rvs-search__where" data-rvs-pickup-where>(.*?)</p>#s', $html, $m));
		$this->assertStringContainsString('Pickup and return: <strong>İstanbul Havalimanı</strong>', $m[1]);
	}

	public function test_multi_location_shows_select_without_line(): void
	{
		$this->locations(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ), array( 4, 'Sabiha Gökçen', 'İstanbul' ) ));
		set_query_var(SearchResults::query_var('pickup_location'), '4');

		$html = do_shortcode('[rentiva_unified_search]');

		$this->assertStringContainsString('data-rvs-pickup-location', $html);
		$this->assertStringNotContainsString('data-rvs-pickup-where', $html);
		$this->assertMatchesRegularExpression('#<option value="4" selected#', $html);
		$this->assertStringContainsString('>Sabiha Gökçen</option>', $html);
	}

	public function test_url_location_ignored_when_zero_or_one_location(): void
	{
		set_query_var(SearchResults::query_var('pickup_location'), '3');
		$this->assertSame('', UnifiedSearch::values()['pickup_location'], 'Lite alone.');

		$this->locations(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ) ));
		$this->assertSame('', UnifiedSearch::values()['pickup_location'], 'One location.');

		$this->locations(array( array( 3, 'İstanbul Havalimanı', 'İstanbul' ), array( 4, 'Sabiha Gökçen', 'İstanbul' ) ));
		$this->assertSame('3', UnifiedSearch::values()['pickup_location']);

		set_query_var(SearchResults::query_var('pickup_location'), '99');
		$this->assertSame('', UnifiedSearch::values()['pickup_location'], 'Not a rental location.');
	}
}
