<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\SearchResultsWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\UnifiedSearchWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Search results and unified search settings, spec 2026-10-10 Elementor bridge
 * slice 3A Task 5.
 *
 * search-results (§2.5 E): the editors' sortBy/sortOrder (block) and orderby/order
 * (Elementor) never reached the query -- only `default_sort` does
 * (SearchResults::prepare_template_data()). They are replaced by one `default_sort`
 * control whose options are exactly the values the sort switch applies.
 *
 * unified-search: `redirect_page` becomes the form action; the block's
 * minwidth/maxwidth attributes and BlockRegistry's minWidth/maxWidth wrapper read
 * are gone (Elementor has its own width controls).
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget) of both stay
 * today's, recorded before the change (tests/fixtures/golden/search/,
 * MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class SearchSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/search';

	/** The values SearchResults' sort switch applies, in get_sorting_options() order. */
	private const SWITCH_VALUES = array( 'relevance', 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'year_desc' );

	/** @var list<int> */
	private array $catalog = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->catalog = ShortcodeFixtures::catalog();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * The block's defaultSort, the widget's default_sort and the shortcode's
	 * default_sort decide the first request's order. Catalog prices: Alpha 250,
	 * Bravo 400, Charlie 100; titles sort Alpha, Bravo, Charlie.
	 */
	public function test_default_sort_control_reaches_query(): void
	{
		$asc  = array( 'Charlie', 'Alpha', 'Bravo' );
		$desc = array( 'Bravo', 'Alpha', 'Charlie' );

		$this->assertSame($asc, $this->order($this->results_block(array( 'defaultSort' => 'price_asc' ))));
		$this->assertSame($desc, $this->order($this->results_block(array( 'defaultSort' => 'price_desc' ))));
		$this->assertSame(array( 'Charlie', 'Bravo', 'Alpha' ), $this->order($this->results_block(array( 'defaultSort' => 'name_desc' ))));

		$this->assertSame($asc, $this->order($this->results_widget(array( 'default_sort' => 'price_asc' ))));
		$this->assertSame($desc, $this->order($this->results_widget(array( 'default_sort' => 'price_desc' ))));

		$this->assertSame($desc, $this->order($this->results_shortcode('default_sort="price_desc"')));

		// Untouched instances keep today's price ascending.
		$this->assertSame($asc, $this->order($this->results_block(array())));
		$this->assertSame($asc, $this->order($this->results_widget(array())));
		$this->assertSame($asc, $this->order($this->results_shortcode('')));
	}

	/**
	 * Every place that offers a default sort offers exactly the switch's values:
	 * the block editor select, the Elementor select, the tag
	 * schema and the visitor's sort dropdown. newest/popularity/rating fell to
	 * relevance and are gone (spec §2.5 E, no new sort feature).
	 */
	public function test_default_sort_options_equal_switch_values(): void
	{
		$this->assertSame(self::SWITCH_VALUES, $this->switch_cases(), 'The sort switch changed; update the default sort options with it.');

		// No block.json enum: WP's attribute validation would turn a saved off-list value
		// (newest) into price_asc, where the shortcode and the widget keep relevance.
		// The editor select is held to the schema values by G3 instead.
		$block = SettingProbe::block_json('search-results', array());
		$this->assertArrayNotHasKey('enum', $block['attributes']['defaultSort'], 'block.json defaultSort carries no enum');
		$this->assertSame('price_asc', $block['attributes']['defaultSort']['default'] ?? null);

		$this->assertSame(self::SWITCH_VALUES, SettingProbe::editor_options('search-results')['defaultSort'] ?? null, 'block editor defaultSort select');

		$controls = SettingProbe::data_controls('SearchResultsWidget');
		$this->assertArrayHasKey('default_sort', $controls);
		$this->assertSame(self::SWITCH_VALUES, array_keys((array) ( $controls['default_sort']['options'] ?? array() )), 'Elementor default_sort select');
		$this->assertSame('price_asc', $controls['default_sort']['default'] ?? null);

		$schema = AllowlistRegistry::get_schema('rentiva_search_results');
		$this->assertSame(self::SWITCH_VALUES, $schema['default_sort']['values'] ?? null, 'schema default_sort values');
		$this->assertSame('price_asc', $schema['default_sort']['default'] ?? null);

		$this->assertSame(self::SWITCH_VALUES, array_keys(SearchResults::get_sorting_options()), 'visitor sort dropdown');
	}

	/**
	 * The old sort controls are gone from both editors, and their saved values
	 * change nothing (spec §2.4). A saved default_sort the switch never applied
	 * (newest) still falls to relevance, as today.
	 */
	public function test_saved_value_of_removed_sort_controls_is_inert(): void
	{
		$controls = SettingProbe::data_controls('SearchResultsWidget');
		foreach (array( 'orderby', 'order', 'show_availability' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}
		$attributes = SettingProbe::block_json('search-results', array())['attributes'];
		foreach (array( 'sortBy', 'sortOrder', 'showAvailability' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}

		$this->assertSame(
			RenderNormalizer::normalize($this->results_widget(array())),
			RenderNormalizer::normalize($this->results_widget(array( 'orderby' => 'title', 'order' => 'ASC', 'show_availability' => '' )))
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->results_block(array())),
			RenderNormalizer::normalize($this->results_block(array( 'sortBy' => 'newest', 'sortOrder' => 'desc', 'showAvailability' => false )))
		);
		$this->assertSame(array( 'Alpha', 'Bravo', 'Charlie' ), $this->order($this->results_widget(array( 'default_sort' => 'newest' ))));
	}

	/**
	 * A block saved with a removed sort option (newest) orders like the shortcode
	 * and the widget with the same value: the switch's relevance branch, not the
	 * block.json default price_asc (final audit, Important 1).
	 */
	public function test_block_saved_with_removed_sort_value_falls_to_relevance(): void
	{
		$relevance = $this->order($this->results_shortcode('default_sort="newest"'));
		$this->assertSame(array( 'Alpha', 'Bravo', 'Charlie' ), $relevance);
		$this->assertSame($relevance, $this->order($this->results_widget(array( 'default_sort' => 'newest' ))));
		foreach (array( 'newest', 'popularity', 'rating' ) as $removed) {
			$this->assertSame($relevance, $this->order($this->results_block(array( 'defaultSort' => $removed ))), "block defaultSort=$removed");
		}
	}

	public function test_redirect_page_sets_form_action(): void
	{
		$page     = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Zz Redirect Target',
			)
		);
		$target   = esc_url((string) get_permalink($page));
		$fallback = esc_url(ShortcodeUrlManager::get_page_url('rentiva_search_results'));
		$this->assertNotSame($fallback, $target);

		// Untouched: today's search-results page.
		$this->assertSame($fallback, $this->action($this->unified_shortcode('')));
		$this->assertSame($fallback, $this->action($this->unified_block(array())));
		$this->assertSame($fallback, $this->action($this->unified_widget(array())));

		// A page id, on every surface.
		$this->assertSame($target, $this->action($this->unified_shortcode('redirect_page="' . $page . '"')));
		$this->assertSame($target, $this->action($this->unified_block(array( 'redirect_page' => (string) $page ))));
		$this->assertSame($target, $this->action($this->unified_widget(array( 'redirect_page' => (string) $page ))));

		// A same-site URL is used as it is; another site, a missing page or a
		// draft keeps today's action.
		$same_site = home_url('/zz-results/');
		$this->assertSame(esc_url($same_site), $this->action($this->unified_shortcode('redirect_page="' . $same_site . '"')));
		$this->assertSame($fallback, $this->action($this->unified_shortcode('redirect_page="https://elsewhere.example/steal"')));
		$this->assertSame($fallback, $this->action($this->unified_shortcode('redirect_page="999999"')));
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$this->assertSame($fallback, $this->action($this->unified_shortcode('redirect_page="' . $draft . '"')));
	}

	/**
	 * T10a item 3: redirect_page="0" is not a page id. get_post(0) returns the
	 * global post, so the form used to submit to the current page; 0 now behaves
	 * like empty (global setting, else the search results page).
	 */
	public function test_redirect_page_zero_behaves_like_empty(): void
	{
		$current  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Zz Current Page',
			)
		);
		$fallback = esc_url(ShortcodeUrlManager::get_page_url('rentiva_search_results'));
		$GLOBALS['post'] = get_post($current); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- simulate a page in the loop.
		setup_postdata($GLOBALS['post']);

		$this->assertSame($fallback, $this->action($this->unified_shortcode('redirect_page="0"')));
		$this->assertSame($fallback, $this->action($this->unified_block(array( 'redirect_page' => '0' ))));
		$this->assertSame($fallback, $this->action($this->unified_widget(array( 'redirect_page' => '0' ))));

		// 0 falls through to the global setting, as empty does.
		$global   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Zz Global Results',
			)
		);
		$settings = get_option('mhmrentiva_settings', array());
		$settings = is_array($settings) ? $settings : array();
		$settings['mhmrentiva_search_results_page'] = (string) $global;
		update_option('mhmrentiva_settings', $settings);
		$this->assertSame(esc_url((string) get_permalink($global)), $this->action($this->unified_shortcode('redirect_page="0"')));

		// A global setting of 0 is not a page id either.
		$settings['mhmrentiva_search_results_page'] = '0';
		update_option('mhmrentiva_settings', $settings);
		$this->assertSame($fallback, $this->action($this->unified_shortcode('')));

		wp_reset_postdata();
	}

	public function test_wrapper_has_no_min_max_width_style(): void
	{
		$attributes = SettingProbe::block_json('unified-search', array())['attributes'];
		foreach (array( 'minwidth', 'maxwidth', 'filter_categories', 'layout' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}

		// A hand-written block comment still carrying the old keys renders as today's default.
		$html = $this->unified_block(array( 'minWidth' => '320px', 'maxWidth' => '600px', 'minwidth' => '320px', 'maxwidth' => '600px' ));
		$this->assertStringNotContainsString('min-width', $html);
		$this->assertStringNotContainsString('max-width', $html);
		$this->assertSame(
			RenderNormalizer::normalize($this->unified_block(array())),
			RenderNormalizer::normalize($html)
		);
	}

	/**
	 * The Elementor "Layout Style" select writes the live search_layout (Rule 2);
	 * the parity twin and the removed keys' saved values change nothing.
	 */
	public function test_unified_layout_control_writes_search_layout(): void
	{
		$controls = SettingProbe::data_controls('UnifiedSearchWidget');
		$this->assertArrayHasKey('search_layout', $controls);
		$this->assertSame('Layout Style', $controls['search_layout']['label'] ?? null);
		foreach (array( 'layout', 'filter_categories', 'minwidth', 'maxwidth' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}

		$this->assertStringContainsString('rv-unified-search--vertical', $this->unified_widget(array( 'search_layout' => 'vertical' )));
		$this->assertStringContainsString('rv-unified-search--vertical', $this->unified_block(array( 'search_layout' => 'vertical' )));
		$this->assertStringContainsString('rv-unified-search--vertical', $this->unified_shortcode('search_layout="vertical"'));

		$this->assertSame(
			RenderNormalizer::normalize($this->unified_widget(array())),
			RenderNormalizer::normalize($this->unified_widget(array( 'layout' => 'vertical', 'filter_categories' => '3', 'minwidth' => '300px' )))
		);
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		$html = $this->render_default_surface($name);
		$mark = 0 === strpos($name, 'results') ? 'Zz Alpha Sedan' : 'rv-unified-search__form';
		$this->assertStringContainsString($mark, $html, "$name rendered nothing to compare; the golden would pin an empty render.");

		$actual = $this->golden_form($html);
		$file   = self::GOLDEN_DIR . '/' . $name . '.html';

		if ('1' === getenv('MHMRENTIVA_RECORD_GOLDEN')) {
			if (! is_dir(self::GOLDEN_DIR)) {
				mkdir(self::GOLDEN_DIR, 0777, true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.
			}
			file_put_contents($file, $actual); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
			$this->markTestIncomplete("Recorded golden $name.");
		}

		$this->assertFileExists($file, "No golden for $name; record it on the code before the change.");
		$this->assertSame(file_get_contents($file), $actual, "The default render of $name changed (Rule 1, spec §2.2)."); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture.
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function default_surfaces(): array
	{
		$names = array( 'results-shortcode', 'results-block', 'results-widget', 'unified-shortcode', 'unified-block', 'unified-widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render_default_surface(string $name): string
	{
		switch ($name) {
			case 'results-shortcode':
				return $this->results_shortcode('');
			case 'results-block':
				return $this->results_block(array());
			case 'results-widget':
				return $this->results_widget(array());
			case 'unified-shortcode':
				return $this->unified_shortcode('');
			case 'unified-block':
				return $this->unified_block(array());
			case 'unified-widget':
				return $this->unified_widget(array());
		}
		throw new \InvalidArgumentException($name);
	}

	/**
	 * Normalized, with the run's post and attachment ids replaced by stable names.
	 */
	private function golden_form(string $html): string
	{
		$map = array();
		foreach ($this->catalog as $i => $id) {
			$map[ $id ]                               = 'VEHICLE' . $i;
			$map[ (int) get_post_thumbnail_id($id) ] = 'IMAGE' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=_id=|[?&]p=|data-vehicle-id=")(\d+)\b/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	/**
	 * The case labels of the sort switch in SearchResults::perform_search(), default last as relevance.
	 *
	 * @return list<string>
	 */
	private function switch_cases(): array
	{
		$src = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/SearchResults.php'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local source.
		$at  = strpos($src, "switch (\$params['sort'])");
		$this->assertNotFalse($at, 'Sort switch not found.');
		$end = strpos($src, 'default: // relevance', (int) $at);
		$this->assertNotFalse($end, 'Sort switch default not found.');
		preg_match_all("/case '([a-z_]+)':/", substr($src, (int) $at, (int) $end - (int) $at), $m);
		return array_merge(array( 'relevance' ), $m[1]);
	}

	/**
	 * Catalog vehicles in render order, by the second word of their title.
	 *
	 * @return list<string>
	 */
	private function order(string $html): array
	{
		$pos = array();
		foreach (array( 'Alpha', 'Bravo', 'Charlie' ) as $name) {
			$at = strpos($html, 'Zz ' . $name);
			$this->assertNotFalse($at, "Zz $name is not in the results.");
			$pos[ $name ] = $at;
		}
		asort($pos);
		return array_keys($pos);
	}

	private function action(string $html): string
	{
		$this->assertSame(1, preg_match('/<form class="rv-unified-search__form" action="([^"]*)"/', $html, $m), 'No rental search form.');
		return $m[1];
	}

	private function results_shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_search_results ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	private function results_block(array $attrs): string
	{
		return $this->block('mhm-rentiva/search-results', $attrs);
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function results_widget(array $settings): string
	{
		return $this->widget(SearchResultsWidget::class, $settings);
	}

	private function unified_shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_unified_search ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	private function unified_block(array $attrs): string
	{
		return $this->block('mhm-rentiva/unified-search', $attrs);
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function unified_widget(array $settings): string
	{
		return $this->widget(UnifiedSearchWidget::class, $settings);
	}

	/**
	 * @param class-string        $class    Widget class.
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(string $class, array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make($class, $settings);
		ob_start();
		try {
			$widget->render_content();
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	private function block(string $name, array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
