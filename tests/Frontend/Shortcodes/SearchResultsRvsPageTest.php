<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use WP_Ajax_UnitTestCase;

/**
 * Aşama 2 PR 3: the results page on the front-end standard and its AJAX
 * re-render. The header, chips and empty states come from the same partials
 * in both paths, so a filter change cannot make the page read differently.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults
 */
final class SearchResultsRvsPageTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	public function setUp(): void
	{
		parent::setUp();
		SearchResults::register();
		wp_set_current_user(0);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');
		delete_transient(SearchResults::FILTERS_CACHE_KEY);

		foreach (array( 'Zz Page One', 'Zz Page Two' ) as $i => $title) {
			$id = self::factory()->post->create(array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => $title,
			));
			update_post_meta($id, '_mhmrentiva_price_per_day', '1000');
			update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
			update_post_meta($id, '_mhmrentiva_fuel_type', 0 === $i ? 'diesel' : 'petrol');
		}
	}

	public function tearDown(): void
	{
		foreach (SearchResults::FILTER_PARAMS as $key) {
			set_query_var(SearchResults::query_var($key), '');
		}
		set_query_var(SearchResults::query_var('pickup_time'), '');
		set_query_var('page', '');
		remove_all_filters('mhmrentiva_locations');
		\MHMRentiva\Admin\Core\LocationContext::flush();
		parent::tearDown();
	}

	/* PR S fixtures ----------------------------------------------------------- */

	/**
	 * Rental locations the way Pro's provider gives them (DB rows: string ids).
	 *
	 * @param list<array{0:int,1:string,2:string}> $rows id, name, city.
	 */
	private function locations(array $rows): void
	{
		$out = array();
		foreach ($rows as $row) {
			$out[] = (object) array(
				'id'   => (string) $row[0],
				'name' => $row[1],
				'city' => $row[2],
			);
		}
		remove_all_filters('mhmrentiva_locations');
		\MHMRentiva\Admin\Core\LocationContext::flush();
		add_filter(
			'mhmrentiva_locations',
			static function ($locations, $type) use ($out) {
				return 'rental' === $type ? $out : $locations;
			},
			10,
			2
		);
	}

	private function vehicle(string $title, int $location_id = 0, string $fuel = 'petrol'): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => $title,
		));
		update_post_meta($id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($id, '_mhmrentiva_fuel_type', $fuel);
		if ($location_id > 0) {
			update_post_meta($id, '_mhmrentiva_location_id', (string) $location_id);
		}

		return $id;
	}

	private function vehicle_id(string $title): int
	{
		$found = get_posts(array(
			'post_type'        => 'mhmrentiva_vehicle',
			'title'            => $title,
			'fields'           => 'ids',
			'posts_per_page'   => 1,
			'suppress_filters' => true,
		));

		return (int) ( $found[0] ?? 0 );
	}

	/**
	 * A confirmed booking holding the vehicle over [start, end).
	 */
	private function book(int $vehicle_id, int $start_ts, int $end_ts): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', $start_ts));
		update_post_meta($id, '_mhmrentiva_pickup_time', wp_date('H:i', $start_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_date', wp_date('Y-m-d', $end_ts));
		update_post_meta($id, '_mhmrentiva_dropoff_time', wp_date('H:i', $end_ts));
		update_post_meta($id, '_mhmrentiva_start_ts', $start_ts);
		update_post_meta($id, '_mhmrentiva_end_ts', $end_ts);

		return $id;
	}

	/**
	 * The local date $days after today.
	 */
	private static function day(int $days): string
	{
		return ( new \DateTimeImmutable('@' . time()) )->setTimezone(wp_timezone())->setTime(12, 0)->modify("+{$days} day")->format('Y-m-d');
	}

	private static function local(string $date, string $time = '10:00'): int
	{
		return (int) \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant($date, $time);
	}

	/**
	 * Books the vehicle over the whole search window d30 .. d33 (and a day around it).
	 */
	private function book_window(int $vehicle_id): int
	{
		return $this->book($vehicle_id, self::local(self::day(29)), self::local(self::day(34)));
	}

	private function search_window(): void
	{
		set_query_var(SearchResults::query_var('pickup_date'), self::day(30));
		set_query_var(SearchResults::query_var('return_date'), self::day(33));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function window_post(): array
	{
		return array(
			'mhmrentiva_pickup_date' => self::day(30),
			'mhmrentiva_return_date' => self::day(33),
		);
	}

	/**
	 * Istanbul airport (1) and Taksim (2) share a city; Esenboga (3) does not.
	 */
	private function three_points(): void
	{
		$this->locations(array(
			array( 1, 'Airport', 'İstanbul' ),
			array( 2, 'Taksim', 'istanbul' ),
			array( 3, 'Esenboga', 'Ankara' ),
		));
	}

	/**
	 * The <section> of one kind in the html, or ''.
	 */
	private static function section(string $html, string $kind): string
	{
		return preg_match('#<section class="rvs-results__section rvs-results__section--' . $kind . '".*?</section>#s', $html, $m) ? $m[0] : '';
	}

	/* PR S tests --------------------------------------------------------------- */

	public function test_location_only_search_never_says_remove_a_filter(): void
	{
		$this->three_points();
		$this->book_window($this->vehicle('Zz Golf At Airport', 1));
		$this->vehicle('Zz Egea At Taksim', 2);
		$this->search_window();
		set_query_var(SearchResults::query_var('pickup_location'), array( '1' ));

		$html = $this->page();

		$this->assertStringNotContainsString('data-rvs-empty="filtered_out"', $html);
		$this->assertStringNotContainsString('Remove a filter', $html);
		$this->assertStringContainsString('data-rvs-empty="none_here"', $html);
		$this->assertStringContainsString('No cars are free at the Airport pickup point for', $html);
		$this->assertStringContainsString('Other pickup points in the same city have cars. The car you looked for is below, with the earliest time it is free.', $html);
		$this->assertStringContainsString('Zz Egea At Taksim', self::section($html, 'nearby'));
		$this->assertStringContainsString('Zz Golf At Airport', self::section($html, 'booked'));
		$this->assertStringContainsString('Booked for these dates at the Airport pickup point', $html);
		$this->assertStringContainsString('Other pickup points in the same city', $html);
		// The location is not a filter: no "Filters (1)".
		$this->assertMatchesRegularExpression('/<span data-rvs-filter-count>0<\/span>/', $html);

		// The same through AJAX.
		$data = $this->filter($this->window_post() + array( 'mhmrentiva_pickup_location' => array( '1' ) ));
		$this->assertSame('none_here', $data['meta']['empty_state']);
		$this->assertSame(0, $data['meta']['active_filters']);
		$this->assertStringContainsString('data-rvs-empty="none_here"', (string) $data['notice_html']);
		$this->assertStringNotContainsString('filtered_out', (string) $data['html']);
	}

	public function test_filter_with_only_a_booked_match_says_try_other_dates(): void
	{
		$this->book_window($this->vehicle_id('Zz Page One'));

		$data = $this->filter($this->window_post() + array( 'mhmrentiva_fuel_type' => array( 'diesel' ) ));

		$this->assertSame(0, $data['meta']['total']);
		$this->assertSame('no_results', $data['meta']['empty_state']);
		$this->assertStringContainsString('data-rvs-empty="no_results"', (string) $data['html']);
		$this->assertStringNotContainsString('filtered_out', (string) $data['html']);
		$this->assertStringContainsString('Zz Page One', (string) $data['booked_html']);
		$this->assertSame(1, $data['meta']['booked']);
	}

	public function test_nearby_card_carries_its_own_pickup_point(): void
	{
		$this->three_points();
		$this->vehicle('Zz Egea At Taksim', 2);
		$this->vehicle('Zz Clio At Esenboga', 3);
		$this->search_window();
		set_query_var(SearchResults::query_var('pickup_location'), array( '1' ));

		$nearby = self::section($this->page(), 'nearby');

		$this->assertStringContainsString('Zz Egea At Taksim', $nearby);
		$this->assertStringNotContainsString('Zz Clio At Esenboga', $nearby, 'Another city is not offered.');
		$this->assertMatchesRegularExpression('/Pickup: <strong>Taksim<\/strong>/', $nearby);
		// The booking link carries the car's own point, not the searched one.
		$this->assertMatchesRegularExpression('/mhmrentiva_pickup_location=2/', $nearby);
		$this->assertDoesNotMatchRegularExpression('/mhmrentiva_pickup_location=1\b/', $nearby);
		// City · the section's summary.
		$this->assertStringContainsString('İstanbul · ', $nearby);
		$this->assertMatchesRegularExpression('/<h2 class="rvs-results__section-title" id="[^"]+">Other pickup points in the same city<\/h2>/', $nearby);
		$this->assertMatchesRegularExpression('/aria-labelledby="([^"]+)".*id="\1"/s', $nearby);
	}

	/**
	 * The joins inside the section line (city names, the summary's parts)
	 * go through a translatable format, so a translation can change them.
	 */
	public function test_section_line_joins_are_translatable(): void
	{
		$this->three_points();
		$this->vehicle('Zz Egea At Taksim', 2);
		$this->search_window();
		set_query_var(SearchResults::query_var('pickup_location'), array( '1' ));
		$join = static function ($translation, $text, $context, $domain) {
			return 'mhm-rentiva' === $domain && '%1$s · %2$s' === $text && '' !== (string) $context ? '%1$s / %2$s' : $translation;
		};
		add_filter('gettext_with_context', $join, 10, 4);

		$nearby = self::section($this->page(), 'nearby');

		remove_filter('gettext_with_context', $join, 10);
		$this->assertMatchesRegularExpression('/<p class="rvs-results__section-sub">([^<]*)<\/p>/', $nearby);
		preg_match('/<p class="rvs-results__section-sub">([^<]*)<\/p>/', $nearby, $m);
		$sub = html_entity_decode($m[1]);
		$this->assertStringStartsWith('İstanbul · ', $sub, 'The outer line keeps its own format.');
		$this->assertSame(2, substr_count($sub, ' / '), 'Count / range / days: ' . $sub);
		$this->assertSame(1, substr_count($sub, ' · '), $sub);
	}

	public function test_booked_card_links_to_earliest_window(): void
	{
		$one = $this->vehicle_id('Zz Page One');
		$this->book_window($one);
		$start = self::local(self::day(30));
		$end   = self::local(self::day(33));

		$earliest = \MHMRentiva\Admin\Booking\Helpers\NextFreeWindow::earliest(array( $one ), $start, $end)[ $one ];
		$this->assertNotNull($earliest);
		$window = \MHMRentiva\Admin\Booking\Helpers\NextFreeWindow::window($earliest, 3);

		$booked = (string) $this->filter($this->window_post())['booked_html'];

		$this->assertStringContainsString('Booked for these dates', $booked);
		$this->assertStringContainsString('Earliest free: ' . wp_date(get_option('date_format') . ' ' . get_option('time_format'), $earliest), $booked);
		$this->assertStringContainsString('See from that date', $booked);
		$this->assertMatchesRegularExpression('/<article class="rvs-card rvs-card--grid is-booked"/', $booked);
		$this->assertStringContainsString('>Booked<', $booked);
		$expected = \MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks::details_url($one, array(
			'pickup_date' => wp_date('Y-m-d', $window[0]),
			'pickup_time' => wp_date('H:i', $window[0]),
			'return_date' => wp_date('Y-m-d', $window[1]),
		));
		$this->assertStringContainsString('href="' . $expected . '"', str_replace('&amp;', '&', $booked));
		// The vehicle page derives the car's own point: none is sent.
		$this->assertMatchesRegularExpression('/rvs-card__later" href="([^"]+)"/', $booked);
		preg_match('/rvs-card__later" href="([^"]+)"/', $booked, $link);
		$this->assertStringNotContainsString('pickup_location', $link[1]);
		// No price and no booking button on a booked card.
		$this->assertStringNotContainsString('vehicle-book-btn', $booked);
		$this->assertStringNotContainsString('rvs-card__price', $booked);
	}

	public function test_booked_card_without_an_earliest_window_has_no_button(): void
	{
		add_filter(
			'option_' . \MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_vehicle_advance_booking_days'] = 40;
				return $settings;
			}
		);
		$one = $this->vehicle_id('Zz Page One');
		$this->book($one, self::local(self::day(29)), self::local(self::day(80)));

		$booked = (string) $this->filter($this->window_post())['booked_html'];

		$this->assertStringContainsString('Zz Page One', $booked);
		$this->assertStringContainsString('Booked for these dates', $booked);
		$this->assertStringNotContainsString('Earliest free', $booked);
		$this->assertStringNotContainsString('See from that date', $booked);
	}

	public function test_sections_only_on_first_page_and_capped(): void
	{
		for ($i = 1; $i <= 5; $i++) {
			$this->book_window($this->vehicle('Zz Booked ' . $i));
		}

		$first = $this->filter($this->window_post() + array( 'per_page' => '1' ));
		$this->assertSame(3, substr_count( (string) $first['booked_html'], 'data-rvs-card'));
		$this->assertSame(3, $first['meta']['booked']);

		$second = $this->filter($this->window_post() + array(
			'per_page' => '1',
			'page'     => '2',
		));
		$this->assertSame('', $second['booked_html']);
		$this->assertSame('', $second['nearby_html']);

		// Same city: at most six.
		$this->three_points();
		for ($i = 1; $i <= 8; $i++) {
			$this->vehicle('Zz Taksim ' . $i, 2);
		}
		$near = $this->filter($this->window_post() + array( 'mhmrentiva_pickup_location' => array( '1' ) ));
		$this->assertSame(6, substr_count( (string) $near['nearby_html'], 'data-rvs-card'));
		$this->assertSame(6, $near['meta']['nearby']);
	}

	public function test_total_and_pages_ignore_sections(): void
	{
		$this->book_window($this->vehicle('Zz Booked A'));
		$this->book_window($this->vehicle('Zz Booked B'));

		$data = $this->filter($this->window_post() + array( 'per_page' => '1' ));

		// The two setUp cars are free; the booked ones are not counted.
		$this->assertSame(2, $data['meta']['total']);
		$this->assertSame(2, $data['meta']['max_pages']);
		$this->assertStringContainsString('2 vehicles', (string) $data['summary_html']);
		$this->assertSame(2, $data['meta']['booked']);
	}

	public function test_ajax_and_page_render_same_sections(): void
	{
		// The booking starts at d33 12:30; with the 60 min buffer it holds the
		// car from 11:30. A 14:00 return meets it, a 10:00 (default) one does not.
		$one = $this->vehicle_id('Zz Page One');
		$this->book($one, self::local(self::day(33), '12:30'), self::local(self::day(36), '12:30'));

		$this->search_window();
		set_query_var(SearchResults::query_var('pickup_time'), '14:00');
		$page = $this->page();
		$this->assertMatchesRegularExpression('/data-rv-instance="([^"]+)"/', $page);
		preg_match('/data-rv-instance="([^"]+)"/', $page, $instance);

		// The script sends the block's id prefix, so the headings' ids match the page's.
		$data = $this->filter($this->window_post() + array(
			'mhmrentiva_pickup_time' => '14:00',
			'instance'               => $instance[1],
		));

		$this->assertStringContainsString('Zz Page One', self::section($page, 'booked'), 'The page path searches with the pickup time.');
		$this->assertStringContainsString('Zz Page One', (string) $data['booked_html']);
		$this->assertStringNotContainsString('Zz Page One', (string) $data['html']);
		$this->assertSame(1, $data['meta']['total']);
		$flat = static fn(string $html): string => trim( (string) preg_replace('/\s+/', ' ', wp_kses($html, \MHMRentiva\Helpers\Html::allowed_markup())));
		$this->assertStringContainsString($flat( (string) $data['booked_html']), $flat($page));
	}

	public function test_page_two_clears_sections(): void
	{
		$this->three_points();
		$this->book_window($this->vehicle('Zz Golf At Airport', 1));
		$this->vehicle('Zz Egea At Taksim', 2);
		$this->vehicle('Zz Polo At Airport', 1);
		$this->vehicle('Zz Fiesta At Airport', 1);

		$first = $this->filter($this->window_post() + array(
			'mhmrentiva_pickup_location' => array( '1' ),
			'per_page'                   => '1',
		));
		$this->assertNotSame('', $first['nearby_html']);
		$this->assertNotSame('', $first['booked_html']);

		$second = $this->filter($this->window_post() + array(
			'mhmrentiva_pickup_location' => array( '1' ),
			'per_page'                   => '1',
			'page'                       => '2',
		));
		$this->assertSame('', $second['nearby_html']);
		$this->assertSame('', $second['booked_html']);
		$this->assertSame(2, $second['meta']['total']);
	}

	public function test_single_location_hides_location_facet(): void
	{
		$this->locations(array( array( 7, 'Airport', 'İstanbul' ) ));
		$this->vehicle('Zz At Airport', 7);
		// A URL location means nothing on a single-location site.
		set_query_var(SearchResults::query_var('pickup_location'), array( '99' ));

		$html = $this->page();

		$this->assertStringNotContainsString('name="mhmrentiva_pickup_location[]"', $html);
		$this->assertStringContainsString('Pickup and return: <strong>Airport</strong>', $html);
		$this->assertStringContainsString('Zz Page One', $html, 'The URL location must not narrow the list.');
		$this->assertStringContainsString('Zz At Airport', $html);
	}

	public function test_multi_location_facet_comes_from_location_context(): void
	{
		$this->three_points();
		// A stale facet cache naming a location that is gone must not matter.
		set_transient(SearchResults::FILTERS_CACHE_KEY, array( 'brands' => array( 'X' ), 'locations' => array( (object) array( 'id' => 55, 'name' => 'Gone' ) ) ), DAY_IN_SECONDS);

		$html = $this->page();

		$this->assertSame('mhmrentiva_search_filters_v4', SearchResults::FILTERS_CACHE_KEY);
		$this->assertStringContainsString('name="mhmrentiva_pickup_location[]" value="2"', $html);
		$this->assertStringNotContainsString('Gone', $html);
	}

	public function test_lite_alone_shows_booked_section_only(): void
	{
		$this->book_window($this->vehicle_id('Zz Page One'));
		$this->search_window();

		$html = $this->page();

		$this->assertSame('', self::section($html, 'nearby'));
		$booked = self::section($html, 'booked');
		$this->assertStringContainsString('Zz Page One', $booked);
		$this->assertMatchesRegularExpression('/<h2 class="rvs-results__section-title" id="[^"]+">Booked for these dates<\/h2>/', $booked);
		$this->assertStringContainsString('Matches your search, but not free on your dates', $booked);
		$this->assertStringNotContainsString('pickup point', $html);
		$this->assertStringNotContainsString('name="mhmrentiva_pickup_location[]"', $html);
		$this->assertStringContainsString('Zz Page Two', $html);
	}

	public function test_no_booked_ids_means_no_booked_section(): void
	{
		$runs = 0;
		$count = static function (\WP_Query $query) use (&$runs): void {
			if (! empty($query->get('mhmrentiva_search_context'))) {
				++$runs;
			}
		};
		add_action('pre_get_posts', $count);

		$data = $this->filter($this->window_post());

		remove_action('pre_get_posts', $count);
		$this->assertSame('', $data['booked_html']);
		$this->assertSame(0, $data['meta']['booked']);
		$this->assertSame(1, $runs, 'Only the main grid queries when nothing is booked.');
	}

	public function test_min_days_guard_still_empties_results(): void
	{
		add_filter(
			'option_' . \MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_vehicle_min_rental_days'] = 5;
				return $settings;
			}
		);
		$this->book_window($this->vehicle_id('Zz Page One'));

		$data = $this->filter($this->window_post());

		$this->assertSame(0, $data['meta']['total']);
		$this->assertStringNotContainsString('Zz Page Two', (string) $data['html']);
		$this->assertSame('', $data['booked_html']);
	}

	public function test_unrelated_query_untouched(): void
	{
		$this->book_window($this->vehicle_id('Zz Page One'));
		$sql    = '';
		$nested = static function (\WP_Query $query) use (&$sql): void {
			if (empty($query->get('mhmrentiva_search_context')) || '' !== $sql) {
				return;
			}
			$other = new \WP_Query(array(
				'post_type'      => 'mhmrentiva_vehicle',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			));
			$sql   = (string) $other->request;
		};
		add_action('pre_get_posts', $nested);

		$this->filter($this->window_post());

		remove_action('pre_get_posts', $nested);
		$this->assertNotSame('', $sql);
		$this->assertStringNotContainsString('.ID NOT IN (', $sql);
		$this->assertStringNotContainsString('.ID IN (', $sql);
	}

	/**
	 * A second Rentiva search started while the first one's query is being
	 * built carries the same context argument; each scoped clause must still
	 * reach only its own query. Otherwise the outer grid's NOT IN (booked)
	 * empties an inner IN (booked) query.
	 */
	public function test_nested_search_keeps_its_own_clause(): void
	{
		$booked = $this->vehicle_id('Zz Page One');
		$this->book_window($booked);
		$scoped = new \ReflectionMethod(SearchResults::class, 'scoped_query');
		$scoped->setAccessible(true);

		$inner_ids = null;
		$inner_sql = '';
		$nested    = static function (\WP_Query $query) use (&$inner_ids, &$inner_sql, $scoped, $booked): void {
			if (null !== $inner_ids || empty($query->get('mhmrentiva_search_context'))) {
				return;
			}
			$inner_ids = array();
			$inner     = $scoped->invoke(
				null,
				array(
					'post_type'      => 'mhmrentiva_vehicle',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				),
				' AND ' . $GLOBALS['wpdb']->posts . '.ID IN (' . $booked . ')'
			);
			$inner_ids = array_map('intval', $inner->posts);
			$inner_sql = (string) $inner->request;
		};
		add_action('pre_get_posts', $nested);

		$this->filter($this->window_post());

		remove_action('pre_get_posts', $nested);
		$this->assertSame(array( $booked ), $inner_ids, 'The inner search finds its own vehicle.');
		$this->assertStringNotContainsString('.ID NOT IN (', $inner_sql, "The outer search's clause stays on the outer query.");
	}

	public function test_scoped_where_is_gone_after_the_search(): void
	{
		$this->book_window($this->vehicle_id('Zz Page One'));
		global $wp_filter;
		$before = isset($wp_filter['posts_where']) ? count($wp_filter['posts_where']->callbacks, COUNT_RECURSIVE) : 0;

		$this->filter($this->window_post());

		$after = isset($wp_filter['posts_where']) ? count($wp_filter['posts_where']->callbacks, COUNT_RECURSIVE) : 0;
		$this->assertSame($before, $after, 'The search must remove its posts_where closure.');
		$later = new \WP_Query(array(
			'post_type'      => 'mhmrentiva_vehicle',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		));
		$this->assertStringNotContainsString('.ID NOT IN (', (string) $later->request);
		$this->assertContains($this->vehicle_id('Zz Page One'), array_map('intval', $later->posts));
	}

	/* Fix round 1 ------------------------------------------------------------- */

	public function test_undated_location_with_no_cars_is_not_an_empty_catalogue(): void
	{
		$this->three_points();
		$this->vehicle('Zz Egea At Taksim', 2);
		set_query_var(SearchResults::query_var('pickup_location'), array( '1' ));

		$html = $this->page();

		$this->assertStringNotContainsString('data-rvs-empty="no_vehicles"', $html);
		$this->assertStringNotContainsString('No vehicles are listed yet', $html);
		$this->assertStringContainsString('data-rvs-empty="none_yet"', $html);
		$this->assertStringContainsString('There are no cars at the Airport pickup point yet.', $html);
		$this->assertStringContainsString('Zz Egea At Taksim', self::section($html, 'nearby'));

		$data = $this->filter(array( 'mhmrentiva_pickup_location' => array( '1' ) ));
		$this->assertSame('none_yet', $data['meta']['empty_state']);
		$this->assertStringContainsString('There are no cars at the Airport pickup point yet.', (string) $data['notice_html']);
		$this->assertStringNotContainsString('no_vehicles', (string) $data['html']);
		$this->assertStringContainsString('Zz Egea At Taksim', (string) $data['nearby_html']);
		$this->assertSame(1, $data['meta']['nearby']);
	}

	public function test_undated_location_without_neighbours_still_names_the_point(): void
	{
		$this->three_points();
		$data = $this->filter(array( 'mhmrentiva_pickup_location' => array( '3' ) ));

		$this->assertSame('none_yet', $data['meta']['empty_state']);
		$this->assertStringContainsString('There are no cars at the Esenboga pickup point yet.', (string) $data['notice_html']);
		$this->assertSame('', $data['nearby_html']);
	}

	public function test_notice_sub_line_needs_both_sections(): void
	{
		$sub = 'Other pickup points in the same city have cars.';
		$this->three_points();
		$this->vehicle('Zz Egea At Taksim', 2);
		$post = $this->window_post() + array( 'mhmrentiva_pickup_location' => array( '1' ) );

		// Same city only (no booked car at the Airport): the headline alone.
		$only_near = $this->filter($post);
		$this->assertSame('none_here', $only_near['meta']['empty_state']);
		$this->assertNotSame('', $only_near['nearby_html']);
		$this->assertSame('', $only_near['booked_html']);
		$this->assertStringContainsString('No cars are free at the Airport pickup point', (string) $only_near['notice_html']);
		$this->assertStringNotContainsString($sub, (string) $only_near['notice_html']);

		// Both sections: the line pointing at them.
		$this->book_window($this->vehicle('Zz Golf At Airport', 1));
		$both = $this->filter($post);
		$this->assertNotSame('', $both['booked_html']);
		$this->assertStringContainsString($sub, (string) $both['notice_html']);
	}

	public function test_notice_sub_line_absent_with_booked_only(): void
	{
		$this->three_points();
		$this->book_window($this->vehicle('Zz Golf At Airport', 1));

		$data = $this->filter($this->window_post() + array( 'mhmrentiva_pickup_location' => array( '3', '1' ) ));

		$this->assertNotSame('', $data['booked_html']);
		$this->assertStringNotContainsString('Other pickup points in the same city have cars.', (string) $data['notice_html']);
	}

	public function test_several_picked_points_read_as_a_plural(): void
	{
		$this->three_points();
		$data = $this->filter($this->window_post() + array( 'mhmrentiva_pickup_location' => array( '1', '3' ) ));

		$this->assertSame('none_here', $data['meta']['empty_state']);
		$this->assertStringContainsString('No cars are free at the selected pickup points for', (string) $data['notice_html']);
		$this->assertStringNotContainsString('Airport, Esenboga pickup point', (string) $data['notice_html']);

		$undated = $this->filter(array( 'mhmrentiva_pickup_location' => array( '1', '3' ) ));
		$this->assertStringContainsString('There are no cars at the selected pickup points yet.', (string) $undated['notice_html']);
	}

	public function test_announcement_is_built_from_translatable_formats(): void
	{
		$this->book_window($this->vehicle_id('Zz Page One'));
		$seen      = array();
		$translate = static function ($translation, $text, $domain) use (&$seen) {
			if ('mhm-rentiva' !== $domain) {
				return $translation;
			}
			$seen[] = $text;
			$map    = array(
				'%1$s. %2$s.' => '[%1$s | %2$s]',
				'%1$s: %2$s'  => '%1$s => %2$s',
			);
			return $map[ $text ] ?? $translation;
		};
		add_filter('gettext', $translate, 10, 3);

		$data = $this->filter($this->window_post());

		remove_filter('gettext', $translate, 10);
		$this->assertSame('[1 vehicle | Booked for these dates => 1 vehicle]', $data['meta']['announce']);
	}

	/**
	 * Task 8 item 3: an empty main grid above sections that list cars read as
	 * "0 vehicles" / "Show 0 vehicles" -- "no cars" over cars.
	 */
	public function test_empty_grid_with_sections_shows_no_zero_count(): void
	{
		$this->three_points();
		$this->book_window($this->vehicle('Zz Golf At Airport', 1));
		$this->vehicle('Zz Egea At Taksim', 2);
		$this->search_window();
		set_query_var(SearchResults::query_var('pickup_location'), array( '1' ));

		$html = $this->page();

		$this->assertSame(1, preg_match('#<p class="rvs-results__summary" data-rvs-summary>(.*?)</p>#s', $html, $summary));
		$this->assertStringNotContainsString('0 vehicles', $summary[1]);
		$this->assertStringNotContainsString('<strong>', $summary[1], 'No empty count either.');
		$this->assertDoesNotMatchRegularExpression('/^\s*·/u', wp_strip_all_tags($summary[1]), 'The line does not start with a separator.');
		$this->assertStringContainsString('3 days', $summary[1]);
		$this->assertSame(1, preg_match('#<span data-rvs-apply-label>(.*?)</span>\s*</button>#s', $html, $apply));
		$this->assertSame('Show results', trim(strip_tags($apply[1])));

		$data = $this->filter($this->window_post() + array( 'mhmrentiva_pickup_location' => array( '1' ) ));
		$this->assertSame(0, $data['meta']['total']);
		$this->assertStringNotContainsString('0 vehicles', (string) $data['summary_html']);
		$this->assertStringContainsString('3 days', (string) $data['summary_html']);
		$this->assertSame('Show results', trim(strip_tags( (string) $data['apply_html'])));
		$this->assertStringNotContainsString('0 vehicles', (string) $data['meta']['announce']);
		$this->assertStringContainsString('Other pickup points in the same city: 1 vehicle', (string) $data['meta']['announce']);
	}

	public function test_a_grid_with_cars_keeps_its_count(): void
	{
		$this->search_window();

		$html = $this->page();

		$this->assertMatchesRegularExpression('#data-rvs-summary><strong>2 vehicles</strong>#', $html);
		$this->assertSame(1, preg_match('#<span data-rvs-apply-label>(.*?)</span>\s*</button>#s', $html, $apply));
		$this->assertSame('Show 2 vehicles', trim(strip_tags($apply[1])));
		$this->assertStringContainsString('<span data-rvs-apply-count>2</span>', $apply[1]);

		$data = $this->filter($this->window_post());
		$this->assertSame('Show 2 vehicles', trim(strip_tags( (string) $data['apply_html'])));
		$this->assertStringContainsString('2 vehicles', (string) $data['summary_html']);
	}

	public function test_announcement_without_sections_is_the_count(): void
	{
		$data = $this->filter($this->window_post());

		$this->assertSame('2 vehicles', $data['meta']['announce']);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function filter(array $extra = array()): array
	{
		$_POST = array_merge(
			array(
				'nonce'    => wp_create_nonce('mhmrentiva_search_results'),
				'per_page' => '12',
			),
			$extra
		);
		try {
			$this->_handleAjax('mhmrentiva_filter_results');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}
		$decoded              = json_decode( (string) $this->_last_response, true);
		$this->_last_response = '';
		$this->assertTrue($decoded['success'] ?? false);

		return $decoded['data'];
	}

	private function page(): string
	{
		return (string) do_shortcode('[rentiva_search_results]');
	}

	public function test_page_renders_in_the_shell_with_filter_column_header_and_grid(): void
	{
		$html = $this->page();

		$this->assertStringContainsString('<div class="rvs-shell" data-rvs-shortcode="rentiva_search_results">', $html);
		$this->assertStringContainsString('data-rvs-results', $html);
		$this->assertStringContainsString('data-rvs-drawer', $html);
		$this->assertStringContainsString('data-rvs-summary', $html);
		$this->assertStringContainsString('rvs-card-grid rvs-results__grid', $html);
		$this->assertStringNotContainsString('rv-search-results', $html);
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-results', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-search-results-css', 'enqueued'));
	}

	public function test_filter_groups_are_fieldsets_with_legends(): void
	{
		$html = $this->page();

		$this->assertGreaterThanOrEqual(3, substr_count($html, '<fieldset class="rvs-results__group">'));
		$this->assertSame(substr_count($html, '<fieldset class="rvs-results__group">'), substr_count($html, '<legend class="rvs-kicker">'));
	}

	public function test_not_searched_notice_without_dates_and_none_with_them(): void
	{
		$this->assertStringContainsString('data-rvs-empty="not_searched"', $this->page());

		set_query_var(SearchResults::query_var('pickup_date'), '2027-07-12');
		set_query_var(SearchResults::query_var('return_date'), '2027-07-15');
		$html = $this->page();
		$this->assertStringNotContainsString('data-rvs-empty="not_searched"', $html);
		$this->assertStringContainsString('3 days', $html);
		$this->assertStringContainsString('Total price: low to high', $html);
	}

	public function test_chips_render_for_active_filters(): void
	{
		set_query_var(SearchResults::query_var('fuel_type'), 'diesel');
		$html = $this->page();

		$this->assertMatchesRegularExpression('/<button type="button" class="rvs-chip" data-rvs-chip data-name="mhmrentiva_fuel_type\[\]" data-value="diesel">/', $html);
		$this->assertStringContainsString('Zz Page One', $html);
		$this->assertStringNotContainsString('Zz Page Two', $html);
	}

	public function test_filtered_out_empty_state_offers_clear(): void
	{
		$data = $this->filter(array( 'mhmrentiva_fuel_type' => array( 'electric' ) ));

		$this->assertStringContainsString('data-rvs-empty="filtered_out"', (string) $data['html']);
		$this->assertStringContainsString('data-rvs-clear', (string) $data['html']);
		$this->assertSame('filtered_out', $data['meta']['empty_state']);
		$this->assertSame(1, $data['meta']['active_filters']);
	}

	public function test_no_results_empty_state_offers_new_dates(): void
	{
		$data = $this->filter(array(
			'mhmrentiva_pickup_date' => '2027-07-12',
			'mhmrentiva_return_date' => '2027-07-12',
		));

		$this->assertStringContainsString('data-rvs-empty="no_results"', (string) $data['html']);
		$this->assertStringContainsString('data-rvs-change', (string) $data['html']);
	}

	public function test_ajax_payload_carries_the_same_summary_and_chips_as_the_page(): void
	{
		set_query_var(SearchResults::query_var('fuel_type'), 'diesel');
		set_query_var(SearchResults::query_var('pickup_date'), '2027-07-12');
		set_query_var(SearchResults::query_var('return_date'), '2027-07-15');
		$page = $this->page();

		$data = $this->filter(array(
			'mhmrentiva_fuel_type'   => array( 'diesel' ),
			'mhmrentiva_pickup_date' => '2027-07-12',
			'mhmrentiva_return_date' => '2027-07-15',
		));

		// The page goes out through the shortcode kses pass; the AJAX fragments
		// go through the same pass before they are compared.
		$flat = static fn(string $html): string => trim( (string) preg_replace('/\s+/', ' ', str_replace(array( "\r", "\n", "\t" ), '', wp_kses($html, \MHMRentiva\Helpers\Html::allowed_markup()))));
		$this->assertStringContainsString($flat( (string) $data['summary_html']), $flat($page));
		$this->assertStringContainsString($flat( (string) $data['chips_html']), $flat($page));
		$this->assertSame('', $data['notice_html']);
	}

	public function test_notice_region_is_a_persistent_status_region(): void
	{
		$html = $this->page();

		$this->assertMatchesRegularExpression('/<div class="rvs-results__notice" data-rvs-notice role="status">/', $html);
		$this->assertMatchesRegularExpression('/<div class="rvs-results__nearby" data-rvs-nearby><\/div>/', $html);
		$this->assertMatchesRegularExpression('/<div class="rvs-results__booked" data-rvs-booked><\/div>/', $html);
		// One live region: the summary no longer announces on its own.
		$this->assertDoesNotMatchRegularExpression('/data-rvs-summary aria-live/', $html);
	}

	public function test_ajax_meta_carries_an_announcement_of_sections_and_counts(): void
	{
		$this->book_window($this->vehicle_id('Zz Page One'));

		$data = $this->filter($this->window_post());

		$this->assertStringContainsString('1 vehicle', (string) $data['meta']['announce']);
		$this->assertStringContainsString('Booked for these dates', (string) $data['meta']['announce']);
	}

	public function test_pagination_buttons_are_44px_targets_with_page_data(): void
	{
		$html = SearchResults::render_pagination(array( 'current' => 2, 'total' => 5 ), 'http://example.org/results/');

		$this->assertStringContainsString('data-rvs-page="1"', $html);
		$this->assertStringContainsString('data-rvs-page="3"', $html);
		$this->assertStringContainsString('aria-current="page"', $html);
		$this->assertStringContainsString('aria-label="Previous page"', $html);
		$this->assertStringContainsString('href="http://example.org/results/?page=3"', $html);
		$this->assertSame('', SearchResults::render_pagination(array( 'current' => 1, 'total' => 1 )));
	}

	public function test_without_the_filter_column_the_url_filters_ride_with_the_window(): void
	{
		set_query_var(SearchResults::query_var('fuel_type'), 'diesel');
		$html = (string) do_shortcode('[rentiva_search_results show_filters="0"]');

		$this->assertStringNotContainsString('data-rvs-filters', $html);
		$this->assertMatchesRegularExpression('/data-rvs-window hidden>.*name="mhmrentiva_fuel_type\[\]" value="diesel"/s', $html);
	}

	public function test_an_empty_list_value_is_no_filter(): void
	{
		$data = $this->filter(array( 'mhmrentiva_fuel_type' => array( '' ) ));

		$this->assertSame(2, $data['meta']['total'], 'fuel_type[]= must not search for an empty fuel.');
	}

	public function test_a_vehicle_save_drops_the_facet_cache(): void
	{
		set_transient(SearchResults::FILTERS_CACHE_KEY, array( 'brands' => array( 'Stale' ) ), DAY_IN_SECONDS);
		$id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		wp_update_post(array( 'ID' => $id, 'post_title' => 'Zz Saved Again' ));

		$this->assertFalse(get_transient(SearchResults::FILTERS_CACHE_KEY));
	}

	public function test_comparison_band_is_present_and_empty_by_default(): void
	{
		$html = $this->page();

		$this->assertMatchesRegularExpression('/role="region" aria-label="Comparison" data-rvs-compare-band data-count="0" data-max="3"/', $html);
	}

	public function test_results_script_depends_on_the_shared_dialog(): void
	{
		$this->page();

		$this->assertArrayHasKey('mhm-rentiva-rvs-dialog', wp_scripts()->registered);
		$this->assertContains('mhm-rentiva-rvs-dialog', wp_scripts()->registered['mhm-rentiva-search-results-js']->deps);
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-overlay', 'enqueued'));
		$this->assertSame(array( 'mhm-rentiva-rvs-primitives' ), wp_styles()->registered['mhm-rentiva-rvs-overlay']->deps);
	}
}
