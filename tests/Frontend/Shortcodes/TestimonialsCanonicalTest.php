<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\TestimonialsWidget;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Testimonials overrides AbstractShortcode::render(). It must still honour the
 * `_canonical` contract (no shortcode_atts() truncation, shortcode defaults for
 * omitted keys) and fire the public `_html` filter without the internal flag.
 */
final class TestimonialsCanonicalTest extends WP_UnitTestCase
{
	/** @var array<int,array<string,mixed>> */
	private array $seen = array();

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcodes_rentiva_testimonials_html', array( $this, 'record' ), 10, 2);
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @param string              $html Markup.
	 * @param array<string,mixed> $atts Attributes the shortcode used.
	 */
	public function record(string $html, array $atts): string
	{
		$this->seen[] = $atts;
		return $html;
	}

	public function test_widget_value_outside_shortcode_defaults_reaches_the_filter(): void
	{
		// The widget's values arrive mapped (_canonical) and reach the filter.
		$widget = WidgetFactory::make(
			TestimonialsWidget::class,
			array(
				'show_quotes' => '',
			)
		);

		ob_start();
		$widget->render_content();
		ob_end_clean();

		$this->assertCount(1, $this->seen, 'The testimonials _html filter did not fire on the widget path.');
		$atts = $this->seen[0];
		$this->assertSame('0', $atts['show_quotes'] ?? null);
		$this->assertArrayNotHasKey('_canonical', $atts);
		// An omitted key still gets the shortcode's own default.
		$this->assertSame('3', (string) ( $atts['columns'] ?? '' ));

		// A mapped key that is not one of Testimonials::get_default_attributes()
		// (`sort_by` is in the schema only) is not truncated by shortcode_atts().
		// Since slice 3A Task 8 every key a widget control writes is a default
		// (`category` was removed, `show_quotes` became one), so the canonical
		// payload is handed over directly.
		\MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::render(
			array(
				'_canonical' => true,
				'sort_by'    => 'zz-probe',
			)
		);
		$this->assertCount(2, $this->seen);
		$this->assertSame('zz-probe', $this->seen[1]['sort_by'] ?? null);
		$this->assertArrayNotHasKey('_canonical', $this->seen[1]);
	}

	public function test_shortcode_path_still_truncates_to_defaults(): void
	{
		do_shortcode('[rentiva_testimonials category="zz-probe" limit="2"]');

		$this->assertCount(1, $this->seen);
		$this->assertArrayNotHasKey('category', $this->seen[0]);
		$this->assertSame('2', $this->seen[0]['limit']);
	}
}
