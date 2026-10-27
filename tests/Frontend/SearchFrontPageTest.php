<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use WP_UnitTestCase;

/**
 * PR S Task 9 (D4): the results page's "Change" goes to the page holding the
 * search strip with the search in the query (`/?mhmrentiva_pickup_date=...`).
 * When that page is the static front page, the Rentiva search vars -- public
 * query vars -- made WordPress treat the request as the blog index (WP_Query
 * shows page_on_front only for a request with no query vars), so the customer
 * landed on a post list with no strip. A home request that carries nothing
 * but Rentiva search vars is routed to the front page.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::route_front_page
 */
final class SearchFrontPageTest extends WP_UnitTestCase
{
	private int $front = 0;

	/** @var array<string, mixed> */
	private array $saved = array();

	public function setUp(): void
	{
		parent::setUp();
		foreach (array( 'show_on_front', 'page_on_front', 'page_for_posts' ) as $option) {
			$this->saved[ $option ] = get_option($option);
		}
		$this->front = (int) self::factory()->post->create(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Front',
			'post_content' => '[rentiva_unified_search]',
		));
		self::factory()->post->create(array( 'post_status' => 'publish', 'post_title' => 'A blog post' ));
		update_option('show_on_front', 'page');
		update_option('page_on_front', $this->front);

		add_filter('query_vars', array( SearchResults::class, 'register_query_vars' ));
		add_filter('query_vars', array( BookingForm::class, 'register_query_vars' ));
		add_filter('request', array( SearchResults::class, 'route_front_page' ));
	}

	public function tearDown(): void
	{
		remove_filter('query_vars', array( SearchResults::class, 'register_query_vars' ));
		remove_filter('query_vars', array( BookingForm::class, 'register_query_vars' ));
		remove_filter('request', array( SearchResults::class, 'route_front_page' ));
		foreach ($this->saved as $option => $value) {
			update_option($option, $value);
		}
		parent::tearDown();
	}

	public function test_change_link_on_the_front_page_shows_the_front_page(): void
	{
		$this->go_to(home_url('/?mhmrentiva_pickup_date=2027-07-12&mhmrentiva_return_date=2027-07-15&mhmrentiva_pickup_time=11%3A00&mhmrentiva_pickup_location=3'));

		$this->assertTrue(is_front_page(), 'The front page, not the blog index.');
		$this->assertFalse(is_home());
		$this->assertSame($this->front, get_queried_object_id());
		// The strip reads the search back from the query.
		$values = UnifiedSearch::values(false);
		$this->assertSame('2027-07-12', $values['pickup_date']);
		$this->assertSame('2027-07-15', $values['return_date']);
		$this->assertSame('11:00', $values['pickup_time']);
	}

	public function test_bare_front_page_is_untouched(): void
	{
		$this->go_to(home_url('/'));

		$this->assertTrue(is_front_page());
		$this->assertSame($this->front, get_queried_object_id());
	}

	public function test_other_query_vars_keep_their_meaning(): void
	{
		// A WordPress search on the home URL stays a search.
		$this->go_to(home_url('/?s=car&mhmrentiva_pickup_date=2027-07-12'));
		$this->assertTrue(is_search());
		$this->assertFalse(is_front_page());

		// A page asked for by id stays that page.
		$other = (int) self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish' ));
		$this->go_to(home_url('/?page_id=' . $other . '&mhmrentiva_pickup_date=2027-07-12'));
		$this->assertSame($other, get_queried_object_id());
	}

	public function test_a_site_whose_front_page_lists_posts_is_untouched(): void
	{
		update_option('show_on_front', 'posts');
		$this->go_to(home_url('/?mhmrentiva_pickup_date=2027-07-12'));

		$this->assertTrue(is_home());
		$this->assertFalse(is_page());
	}
}
