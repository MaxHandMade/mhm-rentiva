<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\BookingFormWidget;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Aşama 2 PR C: the booking form widget's controls keep their ids (saved pages
 * store values under them) and now drive the front-end standard form: the plain
 * controls write --rvs-booking-* tokens on .rvs-booking, which rvs-booking.css
 * reads; the typography targets the submit button of the new markup.
 */
final class BookingFormTokenBridgeTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		if (! class_exists(\Elementor\Widget_Base::class)) {
			$this->markTestSkipped('Elementor is absent from this test environment.');
		}
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function controls(): array
	{
		$widget = WidgetFactory::make(BookingFormWidget::class);
		\Elementor\Plugin::$instance->controls_manager->delete_stack($widget);
		$stack = $widget->get_stack(false);

		return (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
	}

	private function css(string $relative): string
	{
		return (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/' . $relative); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function token_controls(): array
	{
		return array(
			'form_background'     => array( 'form_background', '--rvs-booking-bg' ),
			'form_border_radius'  => array( 'form_border_radius', '--rvs-booking-radius' ),
			'shadow_shadow'       => array( 'shadow_shadow_box_shadow', '--rvs-booking-shadow' ),
			'button_color'        => array( 'button_color', '--rvs-booking-cta' ),
			'button_hover_color'  => array( 'button_hover_color', '--rvs-booking-cta-hover' ),
		);
	}

	/**
	 * @dataProvider token_controls
	 */
	public function test_controls_write_tokens_the_stylesheet_reads(string $id, string $token): void
	{
		$control = $this->controls()[ $id ] ?? null;

		$this->assertIsArray($control, "$id must keep its id.");
		$this->assertSame(array( '{{WRAPPER}} .rvs-booking' ), array_keys($control['selectors']));
		$this->assertStringStartsWith($token . ': ', (string) reset($control['selectors']));

		$this->assertStringContainsString('var(' . $token . ')', $this->css('frontend/rvs-booking.css'), "rvs-booking.css must read $token.");
		$this->assertStringContainsString($token . ':', $this->css('rvs/rvs-tokens.css'), "$token needs its default in rvs-tokens.css.");
	}

	public function test_typography_targets_the_new_submit_button(): void
	{
		$json = (string) wp_json_encode($this->controls()['submit_typography_font_family'] ?? array());

		$this->assertStringContainsString('.rvs-booking .rvs-booking__submit', $json);
		$this->assertStringNotContainsString('rv-submit-btn', $json);
	}

	public function test_the_six_control_ids_are_unchanged(): void
	{
		$controls = $this->controls();

		foreach (array( 'form_background', 'form_border_radius', 'shadow_shadow_box_shadow', 'button_color', 'button_hover_color', 'submit_typography_font_family' ) as $id) {
			$this->assertArrayHasKey($id, $controls);
		}
	}

	public function test_style_depends_names_the_rvs_booking_chain(): void
	{
		$depends = WidgetFactory::make(BookingFormWidget::class)->get_style_depends();

		$this->assertContains('mhm-rentiva-rvs-booking', $depends);
		$this->assertNotContains('mhm-rentiva-booking-form', $depends);
	}
}
