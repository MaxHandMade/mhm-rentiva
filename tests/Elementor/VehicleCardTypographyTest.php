<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use Elementor\Plugin;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Spec §2.7: the four typography groups of the vehicle card must not share one
 * control name. Elementor 4.3.2 rejects a redeclared control with a
 * _doing_it_wrong, which WP_UnitTestCase turns into a failure.
 */
final class VehicleCardTypographyTest extends WP_UnitTestCase
{
	/**
	 * Registers the card's controls from scratch.
	 *
	 * Elementor caches the control stack per widget type for the whole process, so
	 * without dropping it first a later test would read an earlier test's controls
	 * (and never run register_controls() under its own locale). Outside the editor
	 * Elementor files style-tab controls under `style_controls`.
	 *
	 * @return array<string,mixed>
	 */
	private function fresh_style_controls(): array
	{
		$widget = WidgetFactory::make( VehicleCardWidget::class );
		Plugin::$instance->controls_manager->delete_stack( $widget );

		// Which bucket Elementor uses is decided once per process (Performance's static
		// is_frontend cache), so it depends on test order: read both.
		$stack = $widget->get_stack( false );

		return (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
	}

	public function test_vehicle_card_registers_without_incorrect_usage(): void
	{
		$controls = $this->fresh_style_controls();

		foreach ( array( 'title_typography_font_family', 'price_amount_typography_font_family', 'booking_button_typography_font_family' ) as $id ) {
			$this->assertArrayHasKey( $id, $controls );
		}
	}

	public function test_vehicle_card_registers_without_incorrect_usage_in_turkish(): void
	{
		switch_to_locale( 'tr_TR' );
		load_textdomain( 'mhm-rentiva', dirname( __DIR__, 2 ) . '/languages/mhm-rentiva-tr_TR.mo', 'tr_TR' );

		try {
			// Without the translated label the run would just repeat en_US.
			if ( 'Tipografi' !== __( 'Typography', 'mhm-rentiva' ) ) {
				$this->markTestSkipped( 'tr_TR translation could not be loaded in the test environment.' );
			}

			$controls = $this->fresh_style_controls();

			// Slice 2: group ids no longer follow the translated label.
			$this->assertArrayNotHasKey( 'tipografi_typography_font_family', $controls );
			foreach ( array( 'title_typography_font_family', 'price_amount_typography_font_family', 'booking_button_typography_font_family' ) as $id ) {
				$this->assertArrayHasKey( $id, $controls );
			}
		} finally {
			restore_previous_locale();
			// load_textdomain() above is not undone by restoring the locale; drop it so later tests stay in English.
			unload_textdomain( 'mhm-rentiva', true );
			Plugin::$instance->controls_manager->delete_stack( WidgetFactory::make( VehicleCardWidget::class ) );
		}
	}
}
