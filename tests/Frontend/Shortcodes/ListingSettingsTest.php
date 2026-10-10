<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\FeaturedVehiclesWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesGridWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesListWidget;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Vehicles list, vehicles grid and featured vehicles settings, spec 2026-10-10
 * Elementor bridge slice 3A Task 3.
 *
 * APPLY: list `columns` (a styled columns class, not the unstyled data-columns),
 * `layout` (the vehicle card widget's list/grid card), `custom_css_class` and the
 * "Button Text" control (rebound to the canonical `button_text`, ruling T2-1);
 * grid `columns_tablet` / `columns_mobile` (classes that set the custom properties
 * vehicles-grid.css reads in its breakpoints).
 *
 * G2 (Rule 1): the default renders of the three shortcodes stay today's. The
 * shortcode text, list block, list widget, grid shortcode and featured shortcode
 * renders are pinned by VehicleCardSettingsTest; this test pins the four that are
 * not (grid block/widget, featured block/widget), recorded before the change
 * (tests/fixtures/golden/listing/, MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class ListingSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/listing';

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

	public function test_list_columns_class_follows_setting(): void
	{
		$ids = implode(',', $this->catalog);

		$this->assertSame(array( 'rv-vehicles-list--columns-1' ), $this->list_columns_classes(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '"]')));
		$this->assertSame(array( 'rv-vehicles-list--columns-2' ), $this->list_columns_classes(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '" columns="2"]')));
		$this->assertSame(array( 'rv-vehicles-list--columns-3' ), $this->list_columns_classes($this->block('vehicles-list', array( 'ids' => $ids, 'columns' => '3' ))));
		$this->assertSame(array( 'rv-vehicles-list--columns-4' ), $this->list_columns_classes($this->widget(VehiclesListWidget::class, array( 'columns' => '4' ))));
		// Out of range falls back to one column.
		$this->assertSame(array( 'rv-vehicles-list--columns-1' ), $this->list_columns_classes(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '" columns="9"]')));

		// Rule 1: the widget's default is today's single column (it was '3', unstyled).
		$this->assertSame(array( 'rv-vehicles-list--columns-1' ), $this->list_columns_classes($this->widget(VehiclesListWidget::class, array())));

		// The class is styled outside Elementor too (the trap: data-columns alone is unstyled).
		$css = $this->asset('assets/css/frontend/vehicles-list.css');
		foreach (array( 2, 3, 4 ) as $n) {
			$this->assertMatchesRegularExpression(
				'/\.rv-vehicles-list__wrapper\.rv-vehicles-list--columns-' . $n . '\s*\{[^}]*grid-template-columns:\s*repeat\(' . $n . ',/',
				$css,
				"vehicles-list.css must lay out --columns-$n."
			);
		}
	}

	public function test_list_layout_reaches_card(): void
	{
		$vehicle = (string) $this->catalog[0];

		$this->assertStringContainsString('mhm-card--list', $this->widget(VehicleCardWidget::class, array( 'vehicle_id' => $vehicle )));
		$grid = $this->widget(
			VehicleCardWidget::class,
			array(
				'vehicle_id' => $vehicle,
				'layout'     => 'grid',
			)
		);
		$this->assertStringContainsString('mhm-card--grid', $grid);
		$this->assertStringNotContainsString('mhm-card--list', $grid);

		$this->assertStringContainsString('mhm-card--grid', do_shortcode('[rentiva_vehicles_list ids="' . $vehicle . '" layout="grid"]'));
		$this->assertStringContainsString('mhm-card--list', do_shortcode('[rentiva_vehicles_list ids="' . $vehicle . '" layout="zz-unknown"]'));
	}

	public function test_list_custom_css_class_on_wrapper(): void
	{
		$ids = implode(',', $this->catalog);

		$this->assertContains('zz-text', $this->container_classes(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '" custom_css_class="zz-text"]')));
		$this->assertContains('zz-block', $this->container_classes($this->block('vehicles-list', array( 'ids' => $ids, 'customCssClass' => 'zz-block' ))));
		$this->assertContains('zz-widget', $this->container_classes($this->widget(VehiclesListWidget::class, array( 'custom_css_class' => 'zz-widget' ))));

		// Several classes keep their separation; a hostile value cannot break out.
		$classes = $this->container_classes(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '" custom_css_class="zz-one zz-two"]'));
		$this->assertContains('zz-one', $classes);
		$this->assertContains('zz-two', $classes);

		// The card widget maps the same control to `class` too: printed once.
		$card = $this->container_classes(
			$this->widget(
				VehicleCardWidget::class,
				array(
					'vehicle_id'       => (string) $this->catalog[0],
					'custom_css_class' => 'zz-card',
				)
			)
		);
		$this->assertSame(1, count(array_keys($card, 'zz-card', true)));
	}

	public function test_list_booking_btn_text_reaches_card(): void
	{
		$controls = WidgetFactory::make(VehiclesListWidget::class)->get_controls();
		$this->assertArrayHasKey('button_text', $controls, 'The "Button Text" control writes the canonical key (rule 2).');
		$this->assertArrayNotHasKey('booking_btn_text', $controls, 'booking_btn_text is an internal carrier, never an editor control.');
		$this->assertSame('', $controls['button_text']['default']);

		$this->assertSame('Zz Kirala', $this->button_label($this->widget(VehiclesListWidget::class, array( 'button_text' => 'Zz Kirala' ))));
		$this->assertSame('Book Now', $this->button_label($this->widget(VehiclesListWidget::class, array())));

		// Left untouched, the global "Book Now" text reaches the list widget's cards.
		$option = 'option_' . \MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME;
		$set    = static function ($settings) {
			$settings                             = is_array($settings) ? $settings : array();
			$settings['mhmrentiva_text_book_now'] = 'Zz Global';
			return $settings;
		};
		add_filter($option, $set);
		try {
			$this->assertSame('Zz Global', $this->button_label($this->widget(VehiclesListWidget::class, array())));
			$this->assertSame('Zz Mine', $this->button_label($this->widget(VehiclesListWidget::class, array( 'button_text' => 'Zz Mine' ))));
		} finally {
			remove_filter($option, $set);
		}
	}

	public function test_grid_tablet_and_mobile_columns_emit_vars(): void
	{
		// Defaults (today's 2 / 1) add nothing: the stylesheet's fallbacks are today's values.
		$this->assertSame(array(), $this->grid_breakpoint_classes(do_shortcode('[rentiva_vehicles_grid]')));
		$this->assertSame(array(), $this->grid_breakpoint_classes(do_shortcode('[rentiva_vehicles_grid columns_tablet="2" columns_mobile="1"]')));
		$this->assertSame(array(), $this->grid_breakpoint_classes($this->block('vehicles-grid', array())));
		$this->assertSame(array(), $this->grid_breakpoint_classes($this->widget(VehiclesGridWidget::class, array())));

		$this->assertSame(
			array( 'rv-vehicles-grid--tablet-3', 'rv-vehicles-grid--mobile-2' ),
			$this->grid_breakpoint_classes(do_shortcode('[rentiva_vehicles_grid columns_tablet="3" columns_mobile="2"]'))
		);
		$this->assertSame(
			array( 'rv-vehicles-grid--tablet-1' ),
			$this->grid_breakpoint_classes($this->block('vehicles-grid', array( 'columns_tablet' => '1' )))
		);
		$this->assertSame(
			array( 'rv-vehicles-grid--mobile-2' ),
			$this->grid_breakpoint_classes($this->widget(VehiclesGridWidget::class, array( 'columns_mobile' => '2' )))
		);
		// Out of range is ignored.
		$this->assertSame(array(), $this->grid_breakpoint_classes(do_shortcode('[rentiva_vehicles_grid columns_tablet="7" columns_mobile="0"]')));

		// The stylesheet reads them: each class sets its custom property, and the
		// breakpoints lay the grid out from the property with today's fallback.
		$css = $this->asset('assets/css/frontend/vehicles-grid.css');
		foreach (array( 1, 2, 3, 4 ) as $n) {
			$this->assertMatchesRegularExpression('/\.rv-vehicles-grid--tablet-' . $n . '\s*\{\s*--rv-grid-columns-tablet:\s*' . $n . ';/', $css);
			$this->assertMatchesRegularExpression('/\.rv-vehicles-grid--mobile-' . $n . '\s*\{\s*--rv-grid-columns-mobile:\s*' . $n . ';/', $css);
		}
		$this->assertMatchesRegularExpression('/@media \(max-width: 1024px\)\s*\{[^@]*repeat\(var\(--rv-grid-columns-tablet, 2\), 1fr\)/', $css);
		$this->assertMatchesRegularExpression('/@media \(max-width: 782px\)\s*\{[^@]*repeat\(var\(--rv-grid-columns-mobile, 1\), 1fr\)/', $css);
	}

	public function test_featured_book_button_control_writes_the_live_key(): void
	{
		$controls = WidgetFactory::make(FeaturedVehiclesWidget::class)->get_controls();
		$this->assertArrayHasKey('show_booking_button', $controls);
		$this->assertArrayNotHasKey('show_book_button', $controls);

		$button = 'data-testid="vehicle-book-btn"';
		$this->assertGreaterThan(0, substr_count($this->widget(FeaturedVehiclesWidget::class, array()), $button));
		$off = $this->widget(FeaturedVehiclesWidget::class, array( 'show_booking_button' => '' ));
		$this->assertStringContainsString('mhm-vehicle-card', $off);
		$this->assertSame(0, substr_count($off, $button));

		// Shortcode text: the canonical key now works, like on every other tag.
		$this->assertGreaterThan(0, substr_count(do_shortcode('[rentiva_featured_vehicles]'), $button));
		$this->assertSame(0, substr_count(do_shortcode('[rentiva_featured_vehicles show_booking_button="0"]'), $button));
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		$html = $this->render_default_surface($name);
		$this->assertStringContainsString('mhm-vehicle-card', $html, "$name rendered no card; the golden would pin an empty render.");

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
		$names = array( 'grid-block', 'grid-widget', 'featured-block', 'featured-widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render_default_surface(string $name): string
	{
		switch ($name) {
			case 'grid-block':
				return $this->block('vehicles-grid', array());
			case 'grid-widget':
				return $this->widget(VehiclesGridWidget::class, array());
			case 'featured-block':
				return $this->block('featured-vehicles', array());
			case 'featured-widget':
				return $this->widget(FeaturedVehiclesWidget::class, array());
		}
		throw new \InvalidArgumentException($name);
	}

	/**
	 * Normalized, with the run's post and attachment ids replaced by stable names
	 * (same form as VehicleCardSettingsTest's goldens).
	 */
	private function golden_form(string $html): string
	{
		$map = array();
		foreach ($this->catalog as $i => $id) {
			$map[ $id ]                               = 'VEHICLE' . $i;
			$map[ (int) get_post_thumbnail_id($id) ] = 'IMAGE' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=_id=|[?&]p=)(\d+)\b/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	/**
	 * @return list<string>
	 */
	private function list_columns_classes(string $html): array
	{
		$this->assertSame(1, preg_match('/class="rv-vehicles-list__wrapper ([^"]*)"/', $html, $m), 'No list wrapper in the render.');
		return array_values(array_filter(preg_split('/\s+/', $m[1]), static fn(string $c): bool => str_starts_with($c, 'rv-vehicles-list--columns-')));
	}

	/**
	 * @return list<string>
	 */
	private function grid_breakpoint_classes(string $html): array
	{
		$this->assertSame(1, preg_match('/<div class="rv-vehicles-grid ([^"]*)"/', $html, $m), 'No grid in the render.');
		return array_values(array_filter(preg_split('/\s+/', $m[1]), static fn(string $c): bool => (bool) preg_match('/^rv-vehicles-grid--(tablet|mobile)-/', $c)));
	}

	/**
	 * @return list<string>
	 */
	private function container_classes(string $html): array
	{
		$this->assertSame(1, preg_match('/<div class="rv-vehicles-list-container ([^"]*)"/', $html, $m), 'No list container in the render.');
		return array_values(array_filter(preg_split('/\s+/', trim($m[1]))));
	}

	private function asset(string $path): string
	{
		return (string) file_get_contents(dirname(__DIR__, 3) . '/' . $path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
	}

	/**
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
	private function block(string $slug, array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => 'mhm-rentiva/' . $slug,
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}

	private function button_label(string $html): string
	{
		$this->assertSame(1, preg_match('#<a [^>]*data-testid="vehicle-book-btn"[^>]*>(.*?)</a>#s', $html, $m), 'No booking button in the render.');
		return trim(html_entity_decode(wp_strip_all_tags($m[1])));
	}
}
