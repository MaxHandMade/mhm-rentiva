<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\MyFavoritesWidget;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * My favourites settings, spec 2026-10-10 Elementor bridge slice 3A Task 7b.
 *
 * APPLY: orderby -- added (the saved favourites order, today's), name, price.
 * The list is the user's saved ids, so it is sorted in PHP (plan ruling); a
 * favourite without a price is kept and goes last (Review Focus 1). layout reaches
 * the card (it was a literal 'grid'), no_results_text is the empty state.
 * show_availability_status is off by default (Rule 1; Task 2 pulled the block
 * default) and now reaches the card from shortcode text too.
 * REMOVE: show_badges (spec §2.1), show_added_date (no per-favourite timestamp),
 * show_remove_button (twin of the live show_favorite_button: the block's "Show
 * Remove Button" control now writes it, Rule 2; Elementor already has one).
 *
 * G2: the default card renders are pinned by VehicleCardSettingsTest's
 * favorites-* goldens.
 */
final class MyFavoritesSettingsTest extends WP_UnitTestCase
{
	/** @var list<int> Alpha (250, 1 day old), Bravo (400, 3 days), Charlie (100, 2 days). */
	private array $catalog = array();

	private int $user = 0;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->catalog = ShortcodeFixtures::catalog();
		$this->user    = ShortcodeFixtures::customer();
		wp_set_current_user($this->user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * 'added' is the order the user saved them in -- today's shortcode and widget
	 * order -- whatever `order` says. name sorts by title both ways. The legacy
	 * Elementor values 'date' and 'title' keep meaning added and name.
	 */
	public function test_orderby_added_keeps_saved_order(): void
	{
		list( $alpha, $bravo, $charlie ) = $this->catalog;
		$this->save(array( $bravo, $charlie, $alpha ));
		$saved = array( $bravo, $charlie, $alpha );

		$this->assertSame($saved, $this->cards($this->shortcode('')), 'shortcode default.');
		$this->assertSame($saved, $this->cards($this->widget(array())), 'widget default.');
		$this->assertSame($saved, $this->cards($this->block(array())), 'block default (sortBy added).');
		foreach (array( 'asc', 'desc' ) as $order) {
			$this->assertSame($saved, $this->cards($this->shortcode('orderby="added" order="' . $order . '"')), "added $order.");
			$this->assertSame(
				$saved,
				$this->cards(
					$this->block(
						array(
							'sortBy'    => 'added',
							'sortOrder' => $order,
						)
					)
				),
				"block added $order."
			);
		}
		$this->assertSame($saved, $this->cards($this->widget(array( 'orderby' => 'date' ))), 'A saved Elementor "date" is added.');

		$by_name = array( $alpha, $bravo, $charlie );
		$renders = array(
			'shortcode asc'  => array( $this->shortcode('orderby="name" order="ASC"'), $by_name ),
			'shortcode desc' => array( $this->shortcode('orderby="name" order="DESC"'), array_reverse($by_name) ),
			'block asc'      => array(
				$this->block(
					array(
						'sortBy'    => 'name',
						'sortOrder' => 'asc',
					)
				),
				$by_name,
			),
			'widget desc'    => array(
				$this->widget(
					array(
						'orderby' => 'name',
						'order'   => 'DESC',
					)
				),
				array_reverse($by_name),
			),
			'legacy title'   => array(
				$this->widget(
					array(
						'orderby' => 'title',
						'order'   => 'ASC',
					)
				),
				$by_name,
			),
		);
		foreach ($renders as $what => $pair) {
			$this->assertSame($pair[1], $this->cards($pair[0]), $what);
		}

		// One list of options on every editor, all schema values (G3).
		$values   = AllowlistRegistry::get_schema('rentiva_my_favorites')['orderby']['values'];
		$block    = SettingProbe::block_json('my-favorites', array())['attributes']['sortBy'];
		$controls = SettingProbe::data_controls('MyFavoritesWidget');
		$this->assertSame(array( 'added', 'name', 'price' ), $block['enum']);
		$this->assertSame('added', $block['default']);
		$this->assertSame(array( 'added', 'name', 'price' ), array_keys($controls['orderby']['options']));
		$this->assertSame('added', $controls['orderby']['default']);
		foreach ($block['enum'] as $option) {
			$this->assertContains($option, $values);
		}
	}

	/**
	 * Review Focus 1: sorted by price, a favourite without a price is not dropped
	 * (the user saved it); it goes last in both orders, in saved order. `limit`
	 * cuts after the sort.
	 */
	public function test_price_sort_keeps_favourites_without_price(): void
	{
		list( $alpha, $bravo, $charlie ) = $this->catalog;
		$no_meta = ShortcodeFixtures::vehicle();
		delete_post_meta($no_meta, '_mhmrentiva_price_per_day');
		$no_meta_2 = ShortcodeFixtures::vehicle();
		delete_post_meta($no_meta_2, '_mhmrentiva_price_per_day');
		// A price saved empty is stored as 0 (the meta is registered as a number).
		// 0 is no price to the house (VehicleDataHelper::get_price_per_day(); the
		// card shows its default instead), so it goes last with the unpriced ones.
		$zero = ShortcodeFixtures::vehicle();
		update_post_meta($zero, '_mhmrentiva_price_per_day', '');
		$this->assertSame('0', get_post_meta($zero, '_mhmrentiva_price_per_day', true));
		$this->save(array( $bravo, $no_meta, $charlie, $zero, $no_meta_2, $alpha ));

		$asc  = array( $charlie, $alpha, $bravo, $no_meta, $zero, $no_meta_2 );
		$desc = array( $bravo, $alpha, $charlie, $no_meta, $zero, $no_meta_2 );

		$renders = array(
			'shortcode asc'  => array( $this->shortcode('orderby="price" order="ASC"'), $asc ),
			'shortcode desc' => array( $this->shortcode('orderby="price" order="DESC"'), $desc ),
			'block asc'      => array(
				$this->block(
					array(
						'sortBy'    => 'price',
						'sortOrder' => 'asc',
					)
				),
				$asc,
			),
			'block desc'     => array(
				$this->block(
					array(
						'sortBy'    => 'price',
						'sortOrder' => 'desc',
					)
				),
				$desc,
			),
			'widget asc'     => array(
				$this->widget(
					array(
						'orderby' => 'price',
						'order'   => 'ASC',
					)
				),
				$asc,
			),
			'widget desc'    => array(
				$this->widget(
					array(
						'orderby' => 'price',
						'order'   => 'DESC',
					)
				),
				$desc,
			),
		);
		foreach ($renders as $what => $pair) {
			$this->assertSame($pair[1], $this->cards($pair[0]), "$what: all six, unpriced last.");
		}

		$this->assertSame(array( $charlie, $alpha ), $this->cards($this->shortcode('orderby="price" order="ASC" limit="2"')), 'limit cuts the sorted list.');
		$this->assertSame(array( $bravo, $no_meta ), $this->cards($this->shortcode('limit="2"')), 'limit cuts the saved order.');
	}

	/**
	 * The card gets the chosen layout (it was a literal 'grid'); the shared grid
	 * template keeps grid cards for the vehicles grid.
	 */
	public function test_layout_reaches_card(): void
	{
		$this->save($this->catalog);

		foreach (array( 'shortcode' => $this->shortcode(''), 'block' => $this->block(array()), 'widget' => $this->widget(array()) ) as $surface => $html) {
			$this->assertSame(array( 'grid', 'grid', 'grid' ), $this->layouts($html), "$surface default: grid cards.");
		}
		$list = array(
			'shortcode' => $this->shortcode('layout="list"'),
			'block'     => $this->block(array( 'layout' => 'list' )),
			'widget'    => $this->widget(array( 'layout' => 'list' )),
		);
		foreach ($list as $surface => $html) {
			$this->assertSame(array( 'list', 'list', 'list' ), $this->layouts($html), "$surface: list cards.");
		}
		$this->assertSame(array( 'grid', 'grid', 'grid' ), $this->layouts($this->shortcode('layout="zz-unknown"')), 'An unknown layout is grid.');

		$this->assertNotContains('list', $this->layouts(do_shortcode('[rentiva_vehicles_grid]')), 'The vehicles grid keeps grid cards.');
	}

	/**
	 * T10a item 4: only the favourites list variant is pinned to one column at
	 * every width; a hand-typed columns="1" vehicles grid follows the tablet /
	 * mobile rules like any other column count.
	 */
	public function test_one_column_pin_is_scoped_to_favourites_list(): void
	{
		$this->save($this->catalog);

		foreach (array( 'shortcode' => $this->shortcode('layout="list"'), 'block' => $this->block(array( 'layout' => 'list' )), 'widget' => $this->widget(array( 'layout' => 'list' )) ) as $surface => $html) {
			// The front-end standard's single-column list (Aşama 2 PR 2).
			$this->assertStringContainsString('<div class="rvs-card-list"', $html, "$surface: list variant.");
		}
		$this->assertStringNotContainsString('rvs-card-list', $this->shortcode(''));
		$this->assertStringNotContainsString('rvs-card-list', do_shortcode('[rentiva_vehicles_grid columns="1"]'));

		$css = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/css/rvs/rvs-card.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
		$this->assertMatchesRegularExpression('/\.rvs-card-list\s*\{[^}]*flex-direction:\s*column/', $css);
	}

	/**
	 * no_results_text is the empty state; empty (the default) is today's text.
	 */
	public function test_no_results_text_is_used(): void
	{
		$today = 'No vehicles found yet.';
		foreach (array( 'shortcode' => $this->shortcode(''), 'block' => $this->block(array()), 'widget' => $this->widget(array()) ) as $surface => $html) {
			$this->assertStringContainsString($today, $html, "$surface default: today's empty state.");
		}

		$custom = array(
			'shortcode' => $this->shortcode('no_results_text="Zz Nothing saved yet"'),
			'block'     => $this->block(array( 'noResultsText' => 'Zz Nothing saved yet' )),
			'widget'    => $this->widget(array( 'no_results_text' => 'Zz Nothing saved yet' )),
		);
		foreach ($custom as $surface => $html) {
			$this->assertStringContainsString('Zz Nothing saved yet', $html, "$surface: the configured text.");
			$this->assertStringNotContainsString($today, $html, "$surface: instead of the generic one.");
		}

		$this->save(array( $this->catalog[0] ));
		$this->assertStringNotContainsString('Zz Nothing saved yet', $this->shortcode('no_results_text="Zz Nothing saved yet"'), 'Not shown when there are favourites.');
	}

	/**
	 * Off by default on every surface (Rule 1); on shows the card's status, from
	 * shortcode text too.
	 */
	public function test_availability_status_default_off(): void
	{
		$this->save($this->catalog);

		foreach (array( 'shortcode' => $this->shortcode(''), 'block' => $this->block(array()), 'widget' => $this->widget(array()) ) as $surface => $html) {
			$this->assertCount(3, $this->cards($html), "$surface rendered the cards.");
			$this->assertStringNotContainsString('rvs-card__status', $html, "$surface default: no availability status.");
		}
		$on = array(
			'shortcode' => $this->shortcode('show_availability_status="1"'),
			'block'     => $this->block(array( 'showAvailabilityStatus' => true )),
			'widget'    => $this->widget(array( 'show_availability_status' => 'yes' )),
		);
		foreach ($on as $surface => $html) {
			$this->assertSame(3, substr_count($html, 'rvs-card__status'), "$surface: on shows it.");
		}
		$this->assertFalse(SettingProbe::block_json('my-favorites', array())['attributes']['showAvailabilityStatus']['default']);
	}

	/**
	 * show_badges, show_added_date and show_remove_button are gone; the block's
	 * "Show Remove Button" control writes the live show_favorite_button (Rule 2).
	 * Saved values of the removed keys change nothing (Review Focus 4).
	 */
	public function test_removed_settings_are_inert(): void
	{
		$this->save($this->catalog);

		$attributes = SettingProbe::block_json('my-favorites', array())['attributes'];
		foreach (array( 'showBadges', 'showAddedDate', 'showRemoveButton' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		$controls = SettingProbe::data_controls('MyFavoritesWidget');
		foreach (array( 'show_badges', 'show_added_date', 'show_remove_button' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}
		$this->assertArrayHasKey('show_favorite_button', $controls, 'Elementor keeps its one control for the button.');
		$schema = AllowlistRegistry::get_schema('rentiva_my_favorites');
		foreach (array( 'show_badges', 'show_added_date', 'show_remove_button' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $schema, "schema $gone");
		}

		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/my-favorites/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringNotContainsString('showRemoveButton', $js);
		$this->assertStringNotContainsString('showAddedDate', $js);
		$this->assertSame(1, substr_count($js, 'setAttributes({ showFavoriteButton: val })'), 'One editor control writes showFavoriteButton.');
		$this->assertStringContainsString("__('Show Remove Button', 'mhm-rentiva')", $js, 'The control keeps its label.');
		$this->assertStringNotContainsString('mhm-card-favorite', $this->block(array( 'showFavoriteButton' => false )), 'The button it writes hides the heart.');

		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array())),
			RenderNormalizer::normalize(
				$this->widget(
					array(
						'show_badges'        => '',
						'show_added_date'    => 'yes',
						'show_remove_button' => '',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->block(array())),
			RenderNormalizer::normalize(
				$this->block(
					array(
						'showBadges'       => false,
						'showAddedDate'    => true,
						'showRemoveButton' => false,
					)
				)
			)
		);
	}

	/**
	 * @param list<int> $vehicles In the order the user saves them.
	 */
	private function save(array $vehicles): void
	{
		foreach ($vehicles as $vehicle) {
			FavoritesService::add($this->user, $vehicle);
		}
	}

	/**
	 * Vehicle ids of the rendered cards, in order.
	 *
	 * @return list<int>
	 */
	private function cards(string $html): array
	{
		preg_match_all('#class="rvs-card rvs-card--\w+[^"]*" data-rvs-card data-testid="vehicle-card" data-id="(\d+)"#', $html, $m);
		return array_map('intval', $m[1]);
	}

	/**
	 * @return list<string>
	 */
	private function layouts(string $html): array
	{
		preg_match_all('#class="rvs-card rvs-card--(\w+)[^"]*"#', $html, $m);
		return $m[1];
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_my_favorites ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(MyFavoritesWidget::class, $settings);
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
	private function block(array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => 'mhm-rentiva/my-favorites',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
