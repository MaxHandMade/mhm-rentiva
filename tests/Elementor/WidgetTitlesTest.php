<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Widget and block titles carry no "MHM " prefix; ambiguous short labels carry a
 * translation context.
 */
final class WidgetTitlesTest extends WP_UnitTestCase
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function widget_classes(): array
	{
		$out = array();
		foreach ( glob( dirname( __DIR__, 2 ) . '/src/Admin/Frontend/Widgets/Elementor/*Widget.php' ) as $file ) {
			$short = basename( $file, '.php' );
			if ( in_array( $short, array( 'BaseWidget', 'ElementorWidgetBase' ), true ) ) {
				continue;
			}
			$out[ $short ] = array( $short );
		}
		return $out;
	}

	/**
	 * @dataProvider widget_classes
	 */
	public function test_widget_title_has_no_brand_prefix( string $short ): void
	{
		$class = self::NS . $short;
		if ( ! class_exists( $class ) || ( new \ReflectionClass( $class ) )->isAbstract() ) {
			$this->markTestSkipped( $short . ' is not a concrete widget.' );
		}
		$widget = WidgetFactory::make( $class );
		$this->assertStringStartsNotWith( 'MHM ', $widget->get_title(), $short );
	}

	public function test_all_lite_block_titles_have_no_brand_prefix(): void
	{
		$files = glob( dirname( __DIR__, 2 ) . '/assets/blocks/*/block.json' );
		$this->assertCount( 16, $files );
		foreach ( $files as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true );
			$this->assertStringStartsNotWith( 'MHM ', (string) $json['title'], $file );
		}
	}

	public function test_elementor_category_title_is_rentiva(): void
	{
		$cats = array();
		$mgr  = new class( $cats ) {
			public array $cats;
			public function __construct( array &$cats ) { $this->cats = &$cats; }
			public function add_category( string $slug, array $args, int $pos = 0 ): void { $this->cats[ $slug ] = $args; }
		};
		\MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration::register_category( $mgr );
		$this->assertSame( 'Rentiva', $mgr->cats['mhm-rentiva']['title'] ?? null );
	}

	public function test_order_label_uses_sort_direction_context_in_js_blocks(): void
	{
		foreach ( array( 'vehicles-grid', 'vehicles-list' ) as $slug ) {
			$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/blocks/' . $slug . '/index.js' );
			$this->assertStringNotContainsString( "__('Order'", $js, $slug );
			$this->assertMatchesRegularExpression( "/_x\(\s*'Order',\s*'sort direction',\s*'mhm-rentiva'\s*\)/", $js, $slug );
		}
	}

	public function test_order_label_uses_sort_direction_context_in_widgets(): void
	{
		foreach ( array( 'FeaturedVehicles', 'MyBookings', 'MyFavorites', 'Testimonials', 'VehiclesGrid' ) as $w ) {
			$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/Frontend/Widgets/Elementor/' . $w . 'Widget.php' );
			$this->assertStringNotContainsString( "__( 'Order'", $php, $w );
			$this->assertStringContainsString( "_x( 'Order', 'sort direction', 'mhm-rentiva' )", $php, $w );
		}
	}
}
