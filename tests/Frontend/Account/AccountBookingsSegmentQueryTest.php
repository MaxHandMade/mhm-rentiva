<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsView;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Shortcodes\Account\MyBookings;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * PR D, Task 2: the section counts and rows of "My bookings" agree (Review
 * Focus 1; r2 M1/M8; r3 Y4/y7), the old filter vars still narrow (R6), and the
 * list's settings keep their effect.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountRenderer::get_bookings_data
 */
final class AccountBookingsSegmentQueryTest extends WP_UnitTestCase
{
	private const CHAIN = array(
		'_mhmrentiva_dropoff_date',
		'_mhmrentiva_booking_dropoff_date',
		'_mhmrentiva_end_date',
		'_mhmrentiva_end_ts',
	);

	private int $user = 0;

	private int $vehicle = 0;

	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->user    = ShortcodeFixtures::customer();
		$this->vehicle = ShortcodeFixtures::vehicle();
		wp_set_current_user($this->user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		foreach (array( 'mhmrentiva_view', 'mhmrentiva_page', 'mhmrentiva_status_filter', 'mhmrentiva_search_booking' ) as $var) {
			set_query_var($var, '');
		}
		update_option('timezone_string', '');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_counts_match_rows(): void
	{
		$upcoming = array();
		for ($i = 0; $i < 12; $i++) {
			$upcoming[] = $this->booking('confirmed', 5, $i);
		}
		$past      = $this->booking('confirmed', -10, 20);
		$cancelled = $this->booking('cancelled', 5, 21);

		$this->assertSame(
			array(
				'upcoming'  => 12,
				'past'      => 1,
				'cancelled' => 1,
			),
			AccountBookingsQuery::counts($this->user)
		);

		$first  = AccountBookingsQuery::page($this->user, 'upcoming', 1, 10);
		$second = AccountBookingsQuery::page($this->user, 'upcoming', 2, 10);
		$this->assertCount(10, $first['posts']);
		$this->assertCount(2, $second['posts'], 'The 11th row is on page 2.');
		$this->assertSame(12, $first['total']);
		$this->assertSame(2, $first['pages']);
		$this->assertSame($upcoming, array_merge($this->ids($first), $this->ids($second)), 'Newest first, every upcoming booking once.');

		$this->assertSame(array( $past ), $this->ids(AccountBookingsQuery::page($this->user, 'past', 1, 10)));
		$this->assertSame(array( $cancelled ), $this->ids(AccountBookingsQuery::page($this->user, 'cancelled', 1, 10)));

		// Through the list: the default section is Upcoming, its pager has 2 pages.
		$data = AccountRenderer::get_bookings_data(array( 'limit' => '10' ))['data'];
		$this->assertSame('upcoming', $data['segment']);
		$this->assertCount(10, $data['bookings']);
		$this->assertSame(2, $data['total_pages']);
		$html = $this->shortcode('limit="10"');
		$this->assertStringContainsString('aria-current="page">' . esc_html(sprintf('Upcoming (%s)', '12')) . '<', $html);
		$this->assertStringContainsString('>Past (1)<', $html);
		$this->assertStringContainsString('>Cancelled (1)<', $html);
		$this->assertStringContainsString('mhmrentiva_view=past', $html, 'Section links carry the section.');
	}

	/**
	 * r2 M1 + r3 Y4/y7: missing, empty and fallback return days; segment_of(),
	 * counts() and the rows of page() put every booking in the same section.
	 */
	public function test_return_day_chain_counts_match_rows(): void
	{
		$yesterday = SiteTime::days_ago(1);
		$tomorrow  = SiteTime::days_ahead(1);
		$far_past  = SiteTime::days_ago(30);

		$expect = array();

		$id            = $this->booking('confirmed', 5, 0, array());
		$expect[ $id ] = 'upcoming'; // No return day at all.
		$id            = $this->booking('cancelled', 5, 1, array());
		$expect[ $id ] = 'cancelled';
		$id            = $this->booking('confirmed', 5, 2, array( '_mhmrentiva_dropoff_date' => '' ));
		$expect[ $id ] = 'upcoming'; // Empty dropoff (r3 Y4).
		$id            = $this->booking(
			'confirmed',
			5,
			3,
			array(
				'_mhmrentiva_dropoff_date'         => '',
				'_mhmrentiva_booking_dropoff_date' => $yesterday,
			)
		);
		$expect[ $id ] = 'past'; // y7: second link.
		$id            = $this->booking('confirmed', 5, 4, array( '_mhmrentiva_end_date' => $far_past ));
		$expect[ $id ] = 'past'; // Third link.
		$id            = $this->booking('confirmed', 5, 5, array( '_mhmrentiva_end_ts' => (string) ( time() - 2 * DAY_IN_SECONDS ) ));
		$expect[ $id ] = 'past'; // The end instant.
		$id            = $this->booking('confirmed', 5, 6, array( '_mhmrentiva_end_ts' => (string) ( time() + 2 * DAY_IN_SECONDS ) ));
		$expect[ $id ] = 'upcoming';
		$id            = $this->booking(
			'confirmed',
			5,
			7,
			array(
				'_mhmrentiva_dropoff_date' => $tomorrow,
				'_mhmrentiva_end_date'     => $far_past,
			)
		);
		$expect[ $id ] = 'upcoming'; // The first present link decides.
		$id            = $this->booking('confirmed', 5, 8, array( '_mhmrentiva_dropoff_date' => SiteTime::today() ));
		$expect[ $id ] = 'upcoming'; // Ends today: still upcoming.
		$id            = $this->booking('confirmed', 5, 9, array( '_mhmrentiva_dropoff_date' => $yesterday . ' 10:00' ));
		$expect[ $id ] = 'past';
		$id            = $this->booking('no_show', 5, 10, array( '_mhmrentiva_dropoff_date' => $tomorrow ));
		$expect[ $id ] = 'past';
		$id            = $this->booking('refunded', 5, 11, array( '_mhmrentiva_dropoff_date' => $yesterday ));
		$expect[ $id ] = 'cancelled';
		$id            = $this->booking('in_progress', 5, 12, array( '_mhmrentiva_dropoff_date' => SiteTime::today() ));
		$expect[ $id ] = 'upcoming';
		$id            = $this->booking('completed', 5, 13, array( '_mhmrentiva_dropoff_date' => $tomorrow ));
		$expect[ $id ] = 'past';
		$id            = $this->booking('confirmed', 5, 14, array( '_mhmrentiva_dropoff_date' => 'garbage', '_mhmrentiva_end_date' => $far_past ));
		$expect[ $id ] = 'past'; // A malformed link counts as missing.
		$id            = $this->booking('confirmed', 5, 15, array( '_mhmrentiva_dropoff_date' => '2020-13-45' ));
		$expect[ $id ] = 'upcoming'; // An impossible day is missing (checkdate), not past.
		$id            = $this->booking('confirmed', 5, 16, array( '_mhmrentiva_dropoff_date' => '2020-13-45', '_mhmrentiva_end_date' => $far_past ));
		$expect[ $id ] = 'past'; // ... so the chain reads on.

		foreach ($expect as $id => $segment) {
			$php = AccountBookingsView::segment_of((string) get_post_meta($id, '_mhmrentiva_status', true), AccountBookingsQuery::return_day($id), SiteTime::today());
			$this->assertSame($segment, $php, "PHP section of booking $id");
		}

		$counts = AccountBookingsQuery::counts($this->user);
		foreach (AccountBookingsQuery::SEGMENTS as $segment) {
			$want = array_keys(array_filter($expect, static fn(string $s): bool => $s === $segment));
			$page = AccountBookingsQuery::page($this->user, $segment, 1, 100);
			sort($want);
			$got = $this->ids($page);
			sort($got);
			$this->assertSame($want, $got, "rows of $segment");
			$this->assertSame(count($want), $counts[ $segment ], "counts() of $segment");
		}
	}

	public function test_offset_timezone_moves_no_booking(): void
	{
		update_option('timezone_string', 'Pacific/Kiritimati'); // UTC+14: the site's today is ahead of UTC.
		$today = SiteTime::today();
		$id    = $this->booking('confirmed', 5, 0, array( '_mhmrentiva_end_ts' => (string) SiteTime::instant($today, '00:30') ));
		$this->assertSame(array( 'upcoming' => 1, 'past' => 0, 'cancelled' => 0 ), AccountBookingsQuery::counts($this->user));
		$this->assertSame(array( $id ), $this->ids(AccountBookingsQuery::page($this->user, 'upcoming', 1, 10)), 'Ends today, local: upcoming.');
	}

	public function test_legacy_status_filter_still_narrows(): void
	{
		$confirmed_up   = $this->booking('confirmed', 5, 0);
		$confirmed_past = $this->booking('confirmed', -10, 1);
		$this->booking('pending', 5, 2);
		$this->booking('completed', -10, 3);

		// An old link: the filter, no section -- every section, narrowed.
		set_query_var('mhmrentiva_status_filter', 'confirmed');
		$data = AccountRenderer::get_bookings_data(array())['data'];
		$this->assertSame('', $data['segment']);
		$this->assertSame(array( $confirmed_up, $confirmed_past ), wp_list_pluck($data['bookings'], 'ID'));
		$html = $this->shortcode('');
		$this->assertStringNotContainsString('aria-current="page"', $html, 'No section is selected.');
		$this->assertStringContainsString('data-testid="bookings-sections"', $html, 'The sections stay reachable.');
		$this->assertStringNotContainsString('mhmrentiva_status_filter=', html_entity_decode($this->section_links($html)), 'A section link leaves the old filter.');

		// With a section, both hold.
		set_query_var('mhmrentiva_view', 'past');
		$this->assertSame(array( $confirmed_past ), wp_list_pluck(AccountRenderer::get_bookings_data(array())['data']['bookings'], 'ID'));

		// The search still narrows too.
		set_query_var('mhmrentiva_status_filter', '');
		set_query_var('mhmrentiva_view', '');
		set_query_var('mhmrentiva_search_booking', (string) $confirmed_past);
		$this->assertSame(array( $confirmed_past ), wp_list_pluck(AccountRenderer::get_bookings_data(array())['data']['bookings'], 'ID'));
	}

	public function test_status_attribute_hides_segment_control(): void
	{
		$up   = $this->booking('confirmed', 5, 0);
		$past = $this->booking('confirmed', -10, 1);
		$this->booking('cancelled', 5, 2);

		$html = $this->shortcode('status="confirmed"');
		$this->assertStringNotContainsString('data-testid="bookings-sections"', $html);
		$this->assertSame(array( $up, $past ), $this->row_ids($html), 'The fixed status lists every section.');

		$this->assertStringContainsString('data-testid="bookings-sections"', $this->shortcode(''), 'Control: without it the sections show.');
	}

	public function test_show_price_0_hides_amount_column(): void
	{
		$this->booking('confirmed', 5, 0, array(), '480');

		$on = $this->shortcode('');
		$this->assertStringContainsString('rvs-account-bookings__amount', $on);
		$this->assertMatchesRegularExpression('#<th[^>]*>Amount</th>#', $on);

		$off = $this->shortcode('show_price="0"');
		$this->assertStringNotContainsString('rvs-account-bookings__amount', $off);
		$this->assertDoesNotMatchRegularExpression('#<th[^>]*>Amount</th>#', $off);
		$this->assertStringContainsString('rvs-account-bookings__dates', $off, 'The other columns stay.');
	}

	public function test_section_and_general_empty_states(): void
	{
		$html = $this->shortcode('');
		$this->assertStringContainsString('data-testid="bookings-empty"', $html, 'No booking at all: the general empty state.');
		$this->assertStringContainsString('No bookings yet', $html);
		$this->assertStringNotContainsString('data-testid="bookings-sections"', $html);

		$this->booking('cancelled', 5, 0);
		$html = $this->shortcode('');
		$this->assertStringContainsString('No upcoming bookings.', $html, 'An empty section says so.');
		$this->assertStringNotContainsString('data-testid="bookings-empty"', $html);

		add_filter('mhmrentiva_vehicles_page_url', static fn(): string => 'https://example.test/cars/');
		RequestSimulator::new_request();
		wp_set_current_user(ShortcodeFixtures::customer());
		$this->assertStringContainsString('href="https://example.test/cars/"', $this->shortcode(''), 'The CTA follows mhmrentiva_vehicles_page_url.');
		remove_all_filters('mhmrentiva_vehicles_page_url');
	}

	public function test_one_shell_on_each_path(): void
	{
		$this->booking('confirmed', 5, 0);

		$shortcode = $this->shortcode('');
		$this->assertSame(1, substr_count($shortcode, 'rvs-shell'), 'The shortcode writes the only shell.');
		$this->assertStringContainsString('data-rvs-shortcode="rentiva_my_bookings"', $shortcode);

		ob_start();
		\MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration::render_bookings();
		$endpoint = (string) ob_get_clean();
		$this->assertSame(1, substr_count($endpoint, 'rvs-shell'), 'The endpoint template writes its own shell.');
		$this->assertStringContainsString('class="rvs-shell rvs-account', $endpoint);
	}

	public function test_table_carries_roles_and_labels(): void
	{
		$this->booking('confirmed', 5, 0);
		$html = $this->shortcode('');

		$this->assertStringContainsString('role="table"', $html);
		$this->assertSame(2, substr_count($html, 'role="rowgroup"'));
		$this->assertSame(6, substr_count($html, 'scope="col"'));
		$this->assertSame(6, substr_count($html, 'role="cell"'));
		$this->assertSame(6, preg_match_all('#<td[^>]*data-label="[^"]+"#', $html));
		$this->assertMatchesRegularExpression('#<span class="rvs-status rvs-status--confirmed"><svg[^>]*aria-hidden="true"#', $html, 'R7: icon + text.');
	}

	public function test_cache_key_varies_by_segment(): void
	{
		$key = new \ReflectionMethod(MyBookings::class, 'get_cache_key');
		$key->setAccessible(true);
		$atts = array( 'limit' => '10' );

		set_query_var('mhmrentiva_view', 'upcoming');
		$upcoming = $key->invoke(null, $atts);
		set_query_var('mhmrentiva_view', 'past');
		$past = $key->invoke(null, $atts);
		set_query_var('mhmrentiva_page', '2');
		$past_2 = $key->invoke(null, $atts);
		set_query_var('mhmrentiva_status_filter', 'confirmed');
		$filtered = $key->invoke(null, $atts);
		set_query_var('mhmrentiva_search_booking', '12');
		$searched = $key->invoke(null, $atts);

		$this->assertNotSame($upcoming, $past);
		$this->assertNotSame($past, $past_2);
		$this->assertNotSame($past_2, $filtered, 'The old status filter is in the key.');
		$this->assertNotSame($filtered, $searched, 'The old search is in the key.');
	}

	public function test_view_is_a_public_query_var(): void
	{
		\MHMRentiva\Admin\Frontend\Account\AccountController::register();
		$this->go_to(home_url('/?mhmrentiva_view=past'));
		$this->assertSame('past', (string) get_query_var('mhmrentiva_view'));

		wp_set_current_user($this->user);
		set_query_var('mhmrentiva_view', 'nonsense');
		$this->assertSame('upcoming', AccountRenderer::get_bookings_data(array())['data']['segment'], 'An unknown section is Upcoming.');
	}

	public function test_next_rental_candidates(): void
	{
		$up       = $this->booking('confirmed', 5, 0);
		$running  = $this->booking('in_progress', -2, 1);
		$waiting  = $this->booking('pending_payment', 1, 2);
		$this->booking('confirmed', -10, 3); // Picked up long ago.
		$this->booking('cancelled', 5, 4);
		$this->booking('completed', 5, 5);

		$ids = wp_list_pluck(AccountBookingsQuery::next_rental_candidates($this->user), 'id');
		sort($ids);
		$want = array( $up, $running, $waiting );
		sort($want);
		$this->assertSame($want, $ids);
	}

	// -- helpers -------------------------------------------------------------

	/**
	 * A booking of the test customer, $minutes_ago old (newest first order),
	 * with the return-day chain replaced by $chain when given.
	 *
	 * @param array<string,string>|null $chain
	 */
	private function booking(string $status, int $offset_days, int $minutes_ago, ?array $chain = null, string $total = '100'): int
	{
		$id   = ShortcodeFixtures::booking($this->user, $this->vehicle, $status, $total, $offset_days);
		$date = gmdate('Y-m-d H:i:s', time() - ( $minutes_ago + 1 ) * MINUTE_IN_SECONDS);
		wp_update_post(
			array(
				'ID'            => $id,
				'post_date'     => $date,
				'post_date_gmt' => $date,
			)
		);
		if (null !== $chain) {
			foreach (self::CHAIN as $key) {
				delete_post_meta($id, $key);
			}
			foreach ($chain as $key => $value) {
				update_post_meta($id, $key, $value);
			}
		}
		AccountBookingsQuery::reset();

		return $id;
	}

	/**
	 * @param array{posts: list<\WP_Post>} $page
	 * @return list<int>
	 */
	private function ids(array $page): array
	{
		return array_map(static fn(\WP_Post $p): int => (int) $p->ID, $page['posts']);
	}

	/**
	 * Booking post IDs of the rendered rows, in order (the number column shows
	 * the post ID: no order is linked).
	 *
	 * @return list<int>
	 */
	private function row_ids(string $html): array
	{
		preg_match_all('#<td role="cell" class="rvs-account-bookings__no"[^>]*>\#(\d+)</td>#', $html, $m);
		return array_map('intval', $m[1]);
	}

	private function section_links(string $html): string
	{
		preg_match('#<nav class="rvs-seg".*?</nav>#s', $html, $m);
		return $m[0] ?? '';
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_my_bookings ' . $atts . ']');
	}
}
