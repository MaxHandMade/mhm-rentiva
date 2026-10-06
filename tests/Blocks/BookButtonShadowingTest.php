<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\FeaturedVehiclesWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleComparisonWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * The "book button" toggle of the featured-vehicles and vehicle-comparison blocks.
 *
 * Both editor controls write `showBookButton`, which the schema routed to
 * `show_book_button`, while the templates read `show_booking_button(s)` first
 * (vehicle-card-base.php:40, vehicle-comparison.php:33) and block.json always
 * appended that key at its default. Turning the toggle off did nothing.
 */
class BookButtonShadowingTest extends WP_UnitTestCase
{
	private const FEATURED_BUTTON   = 'data-testid="vehicle-book-btn"';
	private const COMPARISON_BUTTON = 'rv-book-now-btn';

	private int $v1;
	private int $v2;

	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->v1 = ShortcodeFixtures::vehicle();
		$this->v2 = ShortcodeFixtures::vehicle();
		update_post_meta($this->v1, MetaKeys::VEHICLE_FEATURED, '1');
		update_post_meta($this->v2, MetaKeys::VEHICLE_FEATURED, '1');
	}

	private function block(string $slug, array $attrs): string
	{
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

	private function widget(string $class, array $settings): string
	{
		ob_start();
		WidgetFactory::make($class, $settings)->render_content();
		return (string) ob_get_clean();
	}

	/** Both fixture vehicles rendered, so "no button" cannot pass on empty output. */
	private function assertRendersBothVehicles(string $html): void
	{
		$this->assertStringContainsString((string) $this->v1, $html);
		$this->assertStringContainsString((string) $this->v2, $html);
	}

	private function comparison_ids(): string
	{
		return $this->v1 . ',' . $this->v2;
	}

	private function assertComparisonTable(string $html): void
	{
		$this->assertRendersBothVehicles($html);
		$this->assertStringNotContainsStringIgnoringCase('list is ready', $html, 'Rendered the empty state, not the comparison table.');
	}

	public function test_comparison_block_hides_book_button_when_control_off(): void
	{
		$html = $this->block('vehicle-comparison', array( 'vehicleIds' => $this->comparison_ids(), 'showBookButton' => false ));
		$this->assertComparisonTable($html);
		$this->assertStringNotContainsString(self::COMPARISON_BUTTON, $html);
	}

	public function test_comparison_block_shows_book_button_by_default(): void
	{
		$html = $this->block('vehicle-comparison', array( 'vehicleIds' => $this->comparison_ids() ));
		$this->assertComparisonTable($html);
		$this->assertStringContainsString(self::COMPARISON_BUTTON, $html);
	}

	public function test_featured_block_hides_book_button_when_control_off(): void
	{
		$html = $this->block('featured-vehicles', array( 'showBookButton' => false ));
		$this->assertRendersBothVehicles($html);
		$this->assertStringNotContainsString(self::FEATURED_BUTTON, $html);
	}

	public function test_featured_block_shows_book_button_by_default(): void
	{
		$html = $this->block('featured-vehicles', array());
		$this->assertRendersBothVehicles($html);
		$this->assertStringContainsString(self::FEATURED_BUTTON, $html);
	}

	/**
	 * Negative control: the shortcode text and Elementor paths keep today's
	 * behaviour (measured before the change, ledger Task 0).
	 */
	public function test_shortcode_and_elementor_paths_unchanged(): void
	{
		$ids = $this->comparison_ids();

		$this->assertSame(0, substr_count(do_shortcode('[rentiva_featured_vehicles show_book_button="0"]'), self::FEATURED_BUTTON));
		$this->assertSame(0, substr_count(do_shortcode('[rentiva_vehicle_comparison vehicle_ids="' . $ids . '" show_booking_buttons="0"]'), self::COMPARISON_BUTTON));

		if (! class_exists('\Elementor\Widget_Base')) {
			$this->markTestIncomplete('Elementor absent: widget half of the negative control not run.');
		}

		$this->assertSame(0, substr_count($this->widget(VehicleComparisonWidget::class, array( 'vehicle_ids' => $ids, 'show_booking_buttons' => '' )), self::COMPARISON_BUTTON));
		// Known defect, out of this slice's scope: the Elementor featured switcher does
		// not hide the button either (same shadowing, widget path). Pinned at today's
		// value so this slice provably leaves the Elementor path untouched.
		$this->assertSame(2, substr_count($this->widget(FeaturedVehiclesWidget::class, array( 'show_book_button' => '' )), self::FEATURED_BUTTON));
	}

	public function test_alias_routing(): void
	{
		$this->assertSame('show_booking_button', KeyNormalizer::normalize('showBookButton', AllowlistRegistry::get_schema('rentiva_featured_vehicles')));
		$this->assertSame('show_booking_buttons', KeyNormalizer::normalize('showBookButton', AllowlistRegistry::get_schema('rentiva_vehicle_comparison')));
	}
}
