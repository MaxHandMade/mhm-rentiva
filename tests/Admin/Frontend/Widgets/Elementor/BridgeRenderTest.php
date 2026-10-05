<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\ContactFormWidget;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Widgets render their shortcode by passing an attribute ARRAY to the
 * registered callback (spec §2.2) instead of assembling a "[tag a="b"]" string
 * for do_shortcode(). These cases pin both the fixes (no bracket/quote leak,
 * no double encoding) and the deliberate behaviour changes (a)-(d).
 */
final class BridgeRenderTest extends WP_UnitTestCase
{
	private function render_widget(\Elementor\Widget_Base $widget): string
	{
		ob_start();
		$widget->render_content();
		return (string) ob_get_clean();
	}

	public function test_unregistered_tag_renders_empty_string(): void
	{
		$this->assertSame('', Templates::render_shortcode_atts('rentiva_nope', array()));
	}

	public function test_bracket_value_no_leak(): void
	{
		$widget = WidgetFactory::make(ContactFormWidget::class, array( 'title' => 'Bize ulaşın [7/24] & "biz"' ));
		$html   = $this->render_widget($widget);

		$this->assertStringContainsString('id="rv-contact-form"', $html);
		$this->assertStringNotContainsString('_transform_', $html);
		$this->assertStringNotContainsString('title=', $html);
		$this->assertStringNotContainsString('[7/24]', $html);
	}

	public function test_bracket_ampersand_quote_value_survives(): void
	{
		$a = ShortcodeFixtures::vehicle();
		$b = ShortcodeFixtures::vehicle();

		// The payload is built the way a widget builds it (to_canonical() adds the
		// schema defaults and `_canonical`). A bare ['_canonical' => true] array with
		// only these two keys needs AbstractShortcode to apply defaults under
		// `_canonical`, which lands in the next task (spec §2.3).
		$atts = WidgetAttributeBridge::to_canonical(
			'rentiva_vehicle_comparison',
			array(),
			array(
				'title'       => 'Karşılaştır [7/24] & "biz"',
				'vehicle_ids' => "$a,$b",
			)
		);
		$this->assertTrue($atts['_canonical']);

		$html = Templates::render_shortcode_atts('rentiva_vehicle_comparison', $atts);

		$this->assertSame(1, substr_count($html, 'Karşılaştır [7/24] &amp; &quot;biz&quot;'), $html);
	}

	public function test_auth_gate_and_kses_still_apply(): void
	{
		wp_set_current_user(0);
		$this->assertSame(
			__('Please login to view this content.', 'mhm-rentiva'),
			Templates::render_shortcode_atts('rentiva_my_bookings', array( '_canonical' => true ))
		);

		wp_set_current_user(self::factory()->user->create());
		$inject = static function ($html) {
			return $html . '<script>x</script>';
		};
		add_filter('mhmrentiva_shortcodes_rentiva_contact_html', $inject);
		try {
			$html = Templates::render_shortcode_atts('rentiva_contact', array( '_canonical' => true ));
		} finally {
			remove_filter('mhmrentiva_shortcodes_rentiva_contact_html', $inject);
		}

		$this->assertStringContainsString('id="rv-contact-form"', $html);
		$this->assertStringNotContainsString('<script', $html);
	}

	public function test_do_shortcode_tag_filters_not_run(): void
	{
		$marker = static function ($output) {
			return $output . 'DO_SHORTCODE_TAG_RAN';
		};
		add_filter('do_shortcode_tag', $marker);
		try {
			$html = $this->render_widget(WidgetFactory::make(ContactFormWidget::class));
		} finally {
			remove_filter('do_shortcode_tag', $marker);
		}

		$this->assertStringContainsString('id="rv-contact-form"', $html);
		$this->assertStringNotContainsString('DO_SHORTCODE_TAG_RAN', $html);
	}

	public function test_attachment_image_context_filter_not_installed(): void
	{
		$seen  = null;
		$probe = static function ($html) use (&$seen) {
			$seen = has_filter('wp_get_attachment_image_context', '_filter_do_shortcode_context');
			return $html;
		};
		add_filter('mhmrentiva_shortcodes_rentiva_contact_html', $probe);
		try {
			$this->render_widget(WidgetFactory::make(ContactFormWidget::class));
		} finally {
			remove_filter('mhmrentiva_shortcodes_rentiva_contact_html', $probe);
		}

		$this->assertNotNull($seen, 'The contact shortcode did not render, so the probe never ran.');
		$this->assertFalse($seen);
	}

	public function test_template_string_callers_still_render(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();

		$html = (string) Templates::render(
			'shortcodes/vehicle-details',
			array(
				'vehicle_id' => $vehicle,
				'atts'       => array( 'show_booking_form' => '1' ),
			),
			true
		);

		$this->assertStringContainsString('mhm-rentiva-rating-form', $html);
		$this->assertStringContainsString('mhm-rentiva-featured-wrapper', $html);

		ob_start();
		Templates::output_shortcode('[rentiva_testimonials]');
		$testimonials = (string) ob_get_clean();

		$this->assertStringContainsString('rv-testimonials', $testimonials);
	}

	/**
	 * output_shortcode_atts() escapes at the echo (gate G-A allows no bare echo),
	 * so its wp_kses() pass must be a no-op over what the provider already
	 * filtered; otherwise the widget path would strip markup the shortcode emits.
	 */
	public function test_echo_site_kses_pass_changes_nothing(): void
	{
		$a = ShortcodeFixtures::vehicle();
		$b = ShortcodeFixtures::vehicle();

		$cases = array(
			'rentiva_contact'            => array(),
			'rentiva_booking_form'       => array(),
			'rentiva_search_results'     => array(),
			'rentiva_vehicle_comparison' => array( 'vehicle_ids' => "$a,$b" ),
			'rentiva_vehicle_details'    => array( 'vehicle_id' => (string) $a ),
		);

		foreach ($cases as $tag => $explicit) {
			$html = Templates::render_shortcode_atts($tag, WidgetAttributeBridge::to_canonical($tag, array(), $explicit));
			$this->assertNotSame('', $html, $tag);
			$this->assertSame($html, \MHMRentiva\Helpers\Html::kses($html), $tag);
		}
	}

	public function test_old_pro_shim_routes_through_bridge(): void
	{
		$a = ShortcodeFixtures::vehicle();
		$b = ShortcodeFixtures::vehicle();

		/** @var LegacyShimWidget $widget */
		$widget = WidgetFactory::make(LegacyShimWidget::class);
		$html   = $widget->legacy_render("$a,$b");

		$this->assertSame(1, substr_count($html, 'x [y]'), $html);
		$this->assertStringNotContainsString('_transform_', $html);
	}
}

/**
 * Stands in for a Pro widget that still calls the deprecated base helper.
 */
final class LegacyShimWidget extends ElementorWidgetBase
{
	public function get_name(): string
	{
		return 'rv-test-legacy-shim';
	}

	public function get_title(): string
	{
		return 'Legacy shim';
	}

	public function legacy_render(string $vehicle_ids): string
	{
		return $this->render_shortcode(
			'rentiva_vehicle_comparison',
			array(
				'title'       => 'x [y]',
				'vehicle_ids' => $vehicle_ids,
			)
		);
	}
}
