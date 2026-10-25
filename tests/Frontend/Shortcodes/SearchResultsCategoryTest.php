<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 3: the results page's query layer -- the category filter (K4),
 * the header summary, the active-filter chips, the empty-state kind and the
 * sort labels. The page and the AJAX re-render both read these methods.
 */
final class SearchResultsCategoryTest extends WP_UnitTestCase
{
	private int $suv_term;
	private int $sedan_term;
	private int $suv;
	private int $sedan;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		delete_transient('mhmrentiva_search_filters_v3');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');

		$this->suv_term   = (int) self::factory()->term->create(array( 'taxonomy' => 'mhmrentiva_vehicle_category', 'name' => 'Zz SUV' ));
		$this->sedan_term = (int) self::factory()->term->create(array( 'taxonomy' => 'mhmrentiva_vehicle_category', 'name' => 'Zz Sedan' ));

		$this->suv = ShortcodeFixtures::vehicle();
		wp_update_post(array( 'ID' => $this->suv, 'post_title' => 'Zz Category Suv' ));
		update_post_meta($this->suv, '_mhmrentiva_fuel_type', 'diesel');
		wp_set_object_terms($this->suv, array( $this->suv_term ), 'mhmrentiva_vehicle_category');

		$this->sedan = ShortcodeFixtures::vehicle();
		wp_update_post(array( 'ID' => $this->sedan, 'post_title' => 'Zz Category Sedan' ));
		update_post_meta($this->sedan, '_mhmrentiva_fuel_type', 'petrol');
		wp_set_object_terms($this->sedan, array( $this->sedan_term ), 'mhmrentiva_vehicle_category');
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		delete_transient('mhmrentiva_search_filters_v3');
		parent::tearDown();
	}

	private function render(): string
	{
		return (string) do_shortcode('[rentiva_search_results results_per_page="50"]');
	}

	public function test_category_query_var_is_registered_and_read(): void
	{
		$this->assertContains('category', SearchResults::FILTER_PARAMS);
		$this->assertContains('mhmrentiva_category', SearchResults::register_query_vars(array()));
	}

	public function test_category_filter_limits_results_by_term(): void
	{
		set_query_var(SearchResults::query_var('category'), array( (string) $this->suv_term ));
		$html = $this->render();

		$this->assertStringContainsString('Zz Category Suv', $html);
		$this->assertStringNotContainsString('Zz Category Sedan', $html);
	}

	public function test_category_filter_combines_with_meta_filters(): void
	{
		set_query_var(SearchResults::query_var('category'), array( (string) $this->suv_term, (string) $this->sedan_term ));
		set_query_var(SearchResults::query_var('fuel_type'), 'petrol');
		$html = $this->render();

		$this->assertStringContainsString('Zz Category Sedan', $html);
		$this->assertStringNotContainsString('Zz Category Suv', $html);
	}

	public function test_filter_options_list_categories_without_counts(): void
	{
		$html = $this->render();

		$this->assertMatchesRegularExpression('/name="mhmrentiva_category\[\]"\s+value="' . $this->suv_term . '"/', $html);
		$this->assertStringContainsString('Zz SUV', $html);
		$this->assertDoesNotMatchRegularExpression('/Zz SUV\s*<[^>]*>\s*\(?\d+\)?/', $html, 'No option counts (K4).');
	}

	public function test_summary_counts_days_like_the_card(): void
	{
		$summary = SearchResults::summary(array( 'pickup_date' => '2027-07-12', 'return_date' => '2027-07-15' ), 6);

		$this->assertSame(3, $summary['days']);
		$this->assertStringContainsString('6', $summary['count']);
		$this->assertNotSame('', $summary['range']);

		$none = SearchResults::summary(array(), 1);
		$this->assertSame(0, $none['days']);
		$this->assertSame('', $none['range']);
	}

	public function test_empty_state_kinds(): void
	{
		$dated = array( 'pickup_date' => '2027-07-12', 'return_date' => '2027-07-15' );

		$this->assertSame('not_searched', SearchResults::empty_state(array(), 3));
		$this->assertNull(SearchResults::empty_state($dated, 3));
		$this->assertSame('no_results', SearchResults::empty_state($dated, 0));
		$this->assertSame('filtered_out', SearchResults::empty_state($dated + array( 'fuel_type' => 'diesel' ), 0));
		$this->assertSame('filtered_out', SearchResults::empty_state(array( 'category' => array( 5 ) ), 0));
		// No window and still nothing: the catalogue is empty -- not "no cars for these dates".
		$this->assertSame('no_vehicles', SearchResults::empty_state(array(), 0));
	}

	public function test_active_chips_name_each_filter_value(): void
	{
		$chips = SearchResults::active_chips(
			array(
				'category'  => array( $this->suv_term ),
				'fuel_type' => array( 'diesel' ),
				'min_price' => 100,
			),
			array(
				'categories' => array( array( 'id' => $this->suv_term, 'name' => 'Zz SUV' ) ),
				'fuel_types' => array( 'diesel' => 'Diesel' ),
			)
		);

		$labels = wp_list_pluck($chips, 'label');
		$this->assertContains('Zz SUV', $labels);
		$this->assertContains('Diesel', $labels);
		$this->assertCount(3, $chips);
		$this->assertSame('mhmrentiva_category[]', $chips[0]['name']);
		$this->assertSame( (string) $this->suv_term, $chips[0]['value']);
	}

	public function test_sort_labels_say_total_with_dates(): void
	{
		$this->assertSame('Price: Low to High', SearchResults::get_sorting_options()['price_asc']);
		$dated = SearchResults::get_sorting_options(true);
		$this->assertSame('Total price: low to high', $dated['price_asc']);
		$this->assertSame('Total price: high to low', $dated['price_desc']);
		$this->assertSame(array_keys(SearchResults::get_sorting_options()), array_keys($dated));
	}
}
