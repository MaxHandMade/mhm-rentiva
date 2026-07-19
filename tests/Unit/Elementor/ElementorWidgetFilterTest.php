<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * `mhm_rentiva_elementor_widgets` seam inversion (companion to
 * BlockRegistryFilterTest / ShortcodeRegistryFilterTest).
 *
 * Lite no longer declares the 6 Pro Elementor widget classes at all -- they are
 * contributed by Pro's own `ElementorExtensions` filter subscriber, gated by
 * `\MHMRentiva\Pro\Edition`, not Lite's `Mode`/`pro_widget_classes()`/
 * `allowsSeam()`.
 *
 * Elementor may not be loaded in the test environment, so this tests the
 * `get_widget_classes()` accessor and the filter directly, not live Elementor
 * widget registration (mirrors how the block/shortcode filter tests exercise
 * the config/registry accessor, not live WP registration).
 *
 * @covers \MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration::get_widget_classes
 */
final class ElementorWidgetFilterTest extends WP_UnitTestCase {

	private const PRO_WIDGETS = array(
		'MHMRentiva\Admin\Frontend\Widgets\Elementor\MyMessagesWidget',
		'MHMRentiva\Admin\Frontend\Widgets\Elementor\TransferSearchWidget',
		'MHMRentiva\Admin\Frontend\Widgets\Elementor\TransferResultsWidget',
		'MHMRentiva\Admin\Frontend\Widgets\Elementor\PopularRoutesWidget',
		'MHMRentiva\Admin\Frontend\Widgets\Elementor\VendorProfileWidget',
		'MHMRentiva\Admin\Frontend\Widgets\Elementor\VendorDirectoryWidget',
	);

	protected function tearDown(): void {
		remove_all_filters( 'mhm_rentiva_elementor_widgets' );
		parent::tearDown();
	}

	/** @return string[] */
	private function widget_classes(): array {
		$m = new ReflectionMethod( ElementorIntegration::class, 'get_widget_classes' );
		$m->setAccessible( true );
		return (array) $m->invoke( null );
	}

	public function test_lite_has_no_pro_widget_classes(): void {
		$widgets = $this->widget_classes();

		foreach ( self::PRO_WIDGETS as $pro_widget ) {
			$this->assertNotContains( $pro_widget, $widgets, $pro_widget );
		}

		$this->assertContains(
			'MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget',
			$widgets,
			'A core Lite widget must still be present.'
		);
	}

	public function test_filter_admits_a_subscriber_widget(): void {
		add_filter(
			'mhm_rentiva_elementor_widgets',
			static function ( array $widgets ): array {
				$widgets[] = 'MHMRentiva\Tests\Fixtures\DemoElementorWidget';
				return $widgets;
			}
		);

		$this->assertContains( 'MHMRentiva\Tests\Fixtures\DemoElementorWidget', $this->widget_classes() );
	}
}
