<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\ComparisonView;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * PR E (R1, R19, R20, m6, m11, m12, Task 6): what the comparison's template prints.
 */
final class VehicleComparisonMarkupTest extends WP_UnitTestCase
{
	/** @var list<int> */
	private array $ids = array();

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->ids = array( ShortcodeFixtures::vehicle(), ShortcodeFixtures::vehicle() );
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		parent::tearDown();
	}

	private function shortcode(string $extra = '', ?array $ids = null): string
	{
		return do_shortcode('[rentiva_vehicle_comparison vehicle_ids="' . implode(',', $ids ?? $this->ids) . '" ' . $extra . ']');
	}

	/**
	 * The visitor's own list, printed directly: the results page may not exist here.
	 *
	 * @param array<string, mixed> $changes
	 */
	private function own_list(array $changes = array()): string
	{
		$view = array_merge(ComparisonView::build(array(), $this->ids, false), array( 'add_url' => 'http://example.org/results/' ), $changes);

		return Templates::render('shortcodes/vehicle-comparison', array( 'view' => $view ), true);
	}

	public function test_one_table_every_element_names_its_role(): void
	{
		$html = $this->shortcode();

		$this->assertSame(1, substr_count($html, '<table'));
		$this->assertStringNotContainsString('rv-mobile', $html);

		$expected = array(
			'table' => 'table',
			'thead' => 'rowgroup',
			'tbody' => 'rowgroup',
			'tr'    => 'row',
			'td'    => 'cell',
		);
		foreach ($expected as $tag => $role) {
			preg_match_all('/<' . $tag . '\b[^>]*>/', $html, $elements);
			$this->assertNotEmpty($elements[0], $tag);
			foreach ($elements[0] as $element) {
				$this->assertStringContainsString('role="' . $role . '"', $element);
			}
		}
		preg_match_all('/<th\b[^>]*>/', $html, $headers);
		$this->assertNotEmpty($headers[0]);
		foreach ($headers[0] as $header) {
			$this->assertMatchesRegularExpression('/role="(columnheader|rowheader)"/', $header);
		}
	}

	public function test_the_differences_box_starts_off_and_a_different_cell_has_a_text_cue(): void
	{
		$html = $this->shortcode();

		$this->assertMatchesRegularExpression('/<input type="checkbox" data-rvs-cmp-only>/', $html);

		$view = ComparisonView::build(array(), $this->ids, false);
		$rows = array();
		foreach ($view['groups'] as $group) {
			$rows = array_merge($rows, $group['rows']);
		}
		$differs = array_filter($rows, static fn (array $row): bool => ! $row['all_same']);
		$cues    = 0;
		foreach ($differs as $row) {
			$cues += count(array_filter($row['cells'], static fn (array $cell): bool => $cell['different']));
		}
		$shown = preg_match_all('/data-rvs-cmp-cue>/', $html);
		$this->assertSame($cues, $shown);
	}

	/**
	 * @dataProvider layouts
	 */
	public function test_every_toggle_works_in_every_layout(string $layout): void
	{
		$on = $this->shortcode($layout);
		$this->assertSame(2, substr_count($on, 'rvs-compare__media'));
		$this->assertSame(2, substr_count($on, 'data-rvs-cmp-remove'));
		$this->assertSame(2, substr_count($on, 'rvs-compare__select'));
		$this->assertSame(2, substr_count($on, 'rvs-compare__price'));

		$this->assertStringNotContainsString('rvs-compare__media', $this->shortcode($layout . ' show_images="0"'));
		$this->assertStringNotContainsString('data-rvs-cmp-remove', $this->shortcode($layout . ' show_remove_buttons="0"'));
		$this->assertStringNotContainsString('rvs-compare__select', $this->shortcode($layout . ' show_booking_buttons="0"'));
		$this->assertStringNotContainsString('rvs-compare__price', $this->shortcode($layout . ' show_prices="0"'));
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function layouts(): array
	{
		return array(
			'table' => array( 'layout="table"' ),
			'cards' => array( 'layout="cards"' ),
		);
	}

	public function test_the_cards_layout_uses_the_standards_cards(): void
	{
		$html = $this->shortcode('layout="cards"');

		$this->assertSame(2, substr_count($html, 'rvs-card rvs-compare__card'));
		$this->assertStringContainsString('rvs-card-grid', $html);
		$this->assertStringNotContainsString('<table', $html);
		$this->assertStringContainsString('rvs-compare--cards', $html);
	}

	public function test_a_booked_vehicle_with_no_free_window_draws_no_button(): void
	{
		$view                                  = ComparisonView::build(array(), $this->ids, true);
		$view['columns'][0]['booked']          = true;
		$view['columns'][0]['booked_note']     = '';
		$view['columns'][0]['booking_url']     = '';
		$view['columns'][0]['booking_label']   = 'See from that date';
		$view['columns'][1]['booked']          = true;
		$view['columns'][1]['booked_note']     = 'Free from 5 December';
		$view['columns'][1]['booking_url']     = 'http://example.org/vehicle/?w=1';
		$view['columns'][1]['booking_label']   = 'See from that date';

		$html = Templates::render('shortcodes/vehicle-comparison', array( 'view' => $view ), true);

		$this->assertSame(1, substr_count($html, 'rvs-compare__select'));
		$this->assertStringContainsString('rvs-btn--secondary rvs-compare__select" href="http://example.org/vehicle/?w=1"', $html);
		$this->assertStringContainsString('Free from 5 December', $html);
		$this->assertStringNotContainsString('rvs-compare__price', $html);
	}

	public function test_add_column_only_on_the_visitors_list_and_open_only_with_room(): void
	{
		$fixed = $this->shortcode();
		$this->assertStringContainsString('data-fixed="1"', $fixed);
		$this->assertStringNotContainsString('data-rvs-cmp-add', $fixed);
		$this->assertStringNotContainsString('data-rvs-cmp-filler', $fixed);

		$room = $this->own_list(array( 'can_add' => true ));
		$this->assertStringContainsString('data-fixed="0"', $room);
		$this->assertMatchesRegularExpression('/data-rvs-cmp-add>/', $room);
		$this->assertStringContainsString('href="http://example.org/results/"', $room);
		$this->assertStringContainsString('colspan="4"', $room);

		// Full: printed hidden, because a removal can make room without a reload.
		$full = $this->own_list(array( 'can_add' => false ));
		$this->assertMatchesRegularExpression('/data-rvs-cmp-add hidden>/', $full);
		$this->assertStringContainsString('colspan="3"', $full);
	}

	public function test_empty_state_has_the_search_results_destination_whatever_the_toggles(): void
	{
		$url  = 'http://example.org/results/?mhmrentiva_pickup_date=2026-12-01';
		$view = array_merge(
			ComparisonView::build(array(), array(), false),
			array( 'browse_url' => $url )
		);
		$view['show']['booking'] = false;

		$html = Templates::render('shortcodes/vehicle-comparison', array( 'view' => $view ), true);

		$this->assertStringContainsString('No vehicles to compare', $html);
		$this->assertStringContainsString('up to 3 vehicles', $html);
		$this->assertStringContainsString('href="' . esc_url($url) . '"', $html);
		$this->assertStringNotContainsString('<table', $html);
		$this->assertStringNotContainsString('data-rvs-cmp-only', $html);
		$this->assertDoesNotMatchRegularExpression('/data-rvs-cmp-empty hidden/', $html);
	}

	public function test_one_vehicle_uses_the_same_empty_state_and_says_what_is_missing(): void
	{
		$view = ComparisonView::build(array(), array( $this->ids[0] ), false);
		$html = Templates::render('shortcodes/vehicle-comparison', array( 'view' => $view ), true);

		$this->assertStringContainsString('rvs-compare__empty', $html);
		$this->assertStringContainsString('Add one more vehicle', $html);
		$this->assertStringContainsString('at least 2 vehicles', $html);
		$this->assertStringContainsString('Tick', $html);
		$this->assertStringNotContainsString('No vehicles to compare', $html);
		$this->assertStringNotContainsString('<table', $html);
	}

	/**
	 * Nothing a visitor ticks changes a list set on the page, whether it is short
	 * from the start or a removal took it there (the block is printed hidden).
	 */
	public function test_a_fixed_list_never_asks_the_visitor_to_tick_compare(): void
	{
		foreach (array( $this->shortcode('', array( $this->ids[0] )), $this->shortcode() ) as $html) {
			$this->assertStringContainsString('rvs-compare__empty', $html);
			$this->assertStringContainsString('Not enough vehicles to compare', $html);
			$this->assertStringContainsString('at least 2 vehicles', $html);
			$this->assertStringNotContainsString('Tick', $html);
			$this->assertStringNotContainsString('Add one more vehicle', $html);
		}
	}

	public function test_a_fixed_list_carries_its_empty_state_hidden_and_the_visitors_list_does_not(): void
	{
		$this->assertMatchesRegularExpression('/data-rvs-cmp-empty hidden>/', $this->shortcode());
		$this->assertStringNotContainsString('data-rvs-cmp-empty', $this->own_list());
	}

	public function test_headers_for_each_count_a_removal_can_reach(): void
	{
		$third = ShortcodeFixtures::vehicle();
		$view  = ComparisonView::build(array(), array_merge($this->ids, array( $third )), true);

		$this->assertSame(array( 2, 3 ), array_keys($view['headers']));
		$this->assertStringStartsWith('2 vehicles', $view['headers'][2]);
		$this->assertSame($view['header'], $view['headers'][3]);
	}

	public function test_assets(): void
	{
		$this->shortcode();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-comparison', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-vehicle-comparison', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-vehicle-comparison', 'enqueued'));
		$this->assertContains('mhm-rentiva-toast', wp_scripts()->registered['mhm-rentiva-vehicle-comparison']->deps);
		$this->assertContains('mhm-rentiva-vehicle-interactions', wp_scripts()->registered['mhm-rentiva-vehicle-comparison']->deps);
	}
}
