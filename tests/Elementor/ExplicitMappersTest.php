<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge;
use ReflectionClass;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The widgets with an explicit prepare_shortcode_attributes() must map only the
 * keys Elementor actually delivered. A mapper that invents a value for an absent
 * key defeats the empty-means-unset rule (and the shortcode defaults behind it).
 */
final class ExplicitMappersTest extends WP_UnitTestCase
{
	public static function mapper_provider(): array
	{
		$ns = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

		return array(
			'my bookings'        => array( $ns . 'MyBookingsWidget', 'rentiva_my_bookings', 'limit', null ),
			'my favorites'       => array( $ns . 'MyFavoritesWidget', 'rentiva_my_favorites', 'limit', 'show_price' ),
			'testimonials'       => array( $ns . 'TestimonialsWidget', 'rentiva_testimonials', 'limit', 'show_rating' ),
			'search results'     => array( $ns . 'SearchResultsWidget', 'rentiva_search_results', 'limit', 'show_price' ),
			'vehicle comparison' => array( $ns . 'VehicleComparisonWidget', 'rentiva_vehicle_comparison', 'max_vehicles', 'show_images' ),
			'vehicle details'    => array( $ns . 'VehicleDetailsWidget', 'rentiva_vehicle_details', null, 'show_price' ),
			'unified search'     => array( $ns . 'UnifiedSearchWidget', 'rentiva_unified_search', null, 'show_rental_tab' ),
		);
	}

	private function prepare( string $class, string $tag, array $input ): array
	{
		$widget   = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
		$filtered = WidgetAttributeBridge::filter_empty( $tag, $input );

		return ( new ReflectionMethod( $class, 'prepare_shortcode_attributes' ) )->invoke( $widget, $filtered );
	}

	/**
	 * @dataProvider mapper_provider
	 */
	public function test_seven_preparers_do_not_synthesize( string $class, string $tag, ?string $numeric_key, ?string $bool_key ): void
	{
		$this->assertSame( array(), $this->prepare( $class, $tag, array() ), "$class invented a value from empty settings." );

		if ( null !== $numeric_key ) {
			$this->assertSame(
				array(),
				$this->prepare( $class, $tag, array( $numeric_key => '' ) ),
				"$class mapped an emptied numeric control."
			);
		}

		if ( null !== $bool_key ) {
			$result = $this->prepare( $class, $tag, array( $bool_key => '' ) );
			$this->assertArrayHasKey( $bool_key, $result, "$class dropped an explicitly switched-off control." );
			$this->assertContains( $result[ $bool_key ], array( '0', '' ) );
		}
	}

	public function test_present_values_are_still_mapped_and_validated(): void
	{
		$ns     = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';
		$result = $this->prepare( $ns . 'MyBookingsWidget', 'rentiva_my_bookings', array( 'limit' => '7', 'order' => 'asc' ) );
		$this->assertSame( '7', $result['limit'] );
		$this->assertSame( 'ASC', $result['order'] );

		$result = $this->prepare( $ns . 'MyBookingsWidget', 'rentiva_my_bookings', array( 'order' => 'sideways' ) );
		$this->assertArrayNotHasKey( 'order', $result, 'An invalid order must not be forwarded.' );
	}

	public function test_search_results_limit_reaches_results_per_page(): void
	{
		$ns     = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';
		$input  = array( 'limit' => '9' );
		$result = $this->prepare( $ns . 'SearchResultsWidget', 'rentiva_search_results', $input );
		$this->assertSame( '9', $result['results_per_page'] );

		$canonical = WidgetAttributeBridge::to_canonical( 'rentiva_search_results', $input, $result );
		$this->assertSame( '9', (string) $canonical['results_per_page'] );
	}
}
