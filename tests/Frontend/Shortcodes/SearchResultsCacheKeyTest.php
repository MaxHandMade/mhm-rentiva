<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * The guest HTML cache must not hand one request's results to another: every
 * filter var, the page and the guest's comparison list (a cookie the cards and
 * the comparison band render) are part of the key.
 */
final class SearchResultsCacheKeyTest extends WP_UnitTestCase
{
	private int $fired = 0;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		ShortcodeFixtures::vehicle();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');
		add_filter('mhmrentiva_shortcodes_rentiva_search_results_html', array( $this, 'count_render' ));
	}

	public function tearDown(): void
	{
		unset($_COOKIE['mhmrentiva_compare']);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @param string $html Shortcode markup.
	 */
	public function count_render(string $html): string
	{
		++$this->fired;
		return $html;
	}

	private function flush_compare(): void
	{
		$prop = new \ReflectionProperty(\MHMRentiva\Admin\Services\CompareService::class, 'cached_list');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	private function render(): void
	{
		// The results loop leaves the global post on its last vehicle when there
		// is no main query to restore (tests); a page always has one.
		$GLOBALS['post'] = null;
		do_shortcode('[rentiva_search_results]');
		RequestSimulator::new_request();
	}

	public function test_filter_vars_split_the_cache(): void
	{
		set_query_var(SearchResults::query_var('fuel_type'), 'diesel');
		$this->render();
		set_query_var(SearchResults::query_var('fuel_type'), '');
		$this->render();

		$this->assertSame(2, $this->fired, 'A guest without the filter got the filtered render from the cache.');
	}

	public function test_page_splits_the_cache(): void
	{
		set_query_var('page', 2);
		$this->render();
		set_query_var('page', 1);
		$this->render();

		$this->assertSame(2, $this->fired);
	}

	public function test_compare_list_splits_the_cache(): void
	{
		$_COOKIE['mhmrentiva_compare'] = '[42]';
		$this->flush_compare();
		$this->render();
		unset($_COOKIE['mhmrentiva_compare']);
		$this->flush_compare();
		$this->render();

		$this->assertSame(2, $this->fired, 'One guest\'s comparison list was served to another.');
	}

	public function test_same_request_still_hits_the_cache(): void
	{
		$this->render();
		$this->render();

		$this->assertSame(1, $this->fired);
	}
}
