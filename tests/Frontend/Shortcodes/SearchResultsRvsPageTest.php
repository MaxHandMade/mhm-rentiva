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
		delete_transient('mhmrentiva_search_filters_v3');

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
		set_query_var('page', '');
		parent::tearDown();
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

	public function test_comparison_band_is_present_and_empty_by_default(): void
	{
		$html = $this->page();

		$this->assertMatchesRegularExpression('/role="region" aria-label="Comparison" data-rvs-compare-band data-count="0" data-max="3"/', $html);
	}
}
