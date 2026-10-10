<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleComparisonWidget;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Vehicle comparison settings, spec 2026-10-10 Elementor bridge slice 3A Task 4.
 *
 * APPLY: `show_images` (the vehicle image in the table header, the mobile card
 * and the cards layout) and `show_remove_buttons` (the remove button in the same
 * three places; lets an owner lock a curated comparison).
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget) stay today's,
 * recorded before the change (tests/fixtures/golden/vehicle-comparison/,
 * MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class VehicleComparisonSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/vehicle-comparison';

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

	public function test_comparison_images_follow_flag(): void
	{
		// Table layout: header image + mobile card image, per vehicle.
		$on = $this->shortcode('');
		$this->assertSame(2, substr_count($on, 'class="rv-vehicle-image"'));
		$this->assertSame(2, substr_count($on, '<div class="rv-mobile-card-image">'));
		$this->assertSame(4, substr_count($on, '<img '));

		$off = $this->shortcode('show_images="0"');
		$this->assertSame(0, substr_count($off, '<img '));
		$this->assertStringNotContainsString('rv-vehicle-image-container', $off);
		$this->assertStringNotContainsString('rv-mobile-card-image', $off);
		// The rest of the header stays.
		$this->assertSame(2, substr_count($off, '<h4>'));
		$this->assertSame(2, substr_count($off, 'class="rv-mobile-title"'));

		// Cards layout.
		$this->assertSame(2, substr_count($this->shortcode('layout="cards"'), 'class="rv-card-image"'));
		$this->assertSame(0, substr_count($this->shortcode('layout="cards" show_images="0"'), '<img '));

		// Block and widget.
		$ids = $this->catalog[0] . ',' . $this->catalog[1];
		$this->assertSame(4, substr_count($this->block(array( 'vehicleIds' => $ids )), '<img '));
		$this->assertSame(0, substr_count($this->block(array( 'vehicleIds' => $ids, 'showComparisonImages' => false )), '<img '));
		$this->assertSame(4, substr_count($this->widget(array( 'vehicle_ids' => $ids )), '<img '));
		$this->assertSame(0, substr_count($this->widget(array( 'vehicle_ids' => $ids, 'show_images' => '' )), '<img '));
	}

	public function test_remove_buttons_follow_flag(): void
	{
		$button = 'class="rv-remove-vehicle"';

		// Table layout: header + mobile card, per vehicle.
		$this->assertSame(4, substr_count($this->shortcode(''), $button));
		$off = $this->shortcode('show_remove_buttons="0"');
		$this->assertSame(0, substr_count($off, $button));
		$this->assertSame(2, substr_count($off, 'class="rv-book-now-btn"'), 'Only the remove buttons go.');

		// Cards layout.
		$this->assertSame(2, substr_count($this->shortcode('layout="cards"'), $button));
		$this->assertSame(0, substr_count($this->shortcode('layout="cards" show_remove_buttons="0"'), $button));

		// Block and widget.
		$ids = $this->catalog[0] . ',' . $this->catalog[1];
		$this->assertSame(4, substr_count($this->block(array( 'vehicleIds' => $ids )), $button));
		$this->assertSame(0, substr_count($this->block(array( 'vehicleIds' => $ids, 'showRemoveButtons' => false )), $button));
		$this->assertSame(4, substr_count($this->widget(array( 'vehicle_ids' => $ids )), $button));
		$this->assertSame(0, substr_count($this->widget(array( 'vehicle_ids' => $ids, 'show_remove_buttons' => '' )), $button));
	}

	/**
	 * A removed key's saved value changes nothing (spec §2.4): the Elementor
	 * "Show Features" select is gone, and a saved value of it is inert.
	 */
	public function test_saved_value_of_removed_control_is_inert(): void
	{
		$controls = WidgetFactory::make(VehicleComparisonWidget::class)->get_controls();
		$this->assertArrayNotHasKey('show_features', $controls);

		$ids = $this->catalog[0] . ',' . $this->catalog[1];
		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array( 'vehicle_ids' => $ids ))),
			RenderNormalizer::normalize($this->widget(array( 'vehicle_ids' => $ids, 'show_features' => 'basic' )))
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->shortcode('')),
			RenderNormalizer::normalize($this->shortcode('show_features="basic" show_technical_specs="0" show_category="0"'))
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
		$this->assertStringContainsString('rv-comparison-table', $html, "$name rendered no comparison; the golden would pin an empty render.");

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
		$names = array( 'shortcode', 'block', 'widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render_default_surface(string $name): string
	{
		$ids = $this->catalog[0] . ',' . $this->catalog[1];
		switch ($name) {
			case 'shortcode':
				return $this->shortcode('');
			case 'block':
				return $this->block(array( 'vehicleIds' => $ids ));
			case 'widget':
				return $this->widget(array( 'vehicle_ids' => $ids ));
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
			'/(?<=_id=|[?&]p=)(\d+)\b/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = RenderNormalizer::normalize($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_vehicle_comparison vehicle_ids="' . $this->catalog[0] . ',' . $this->catalog[1] . '" ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(VehicleComparisonWidget::class, $settings);
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
				'blockName'    => 'mhm-rentiva/vehicle-comparison',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
