<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesListWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Parity controls (auto-added from block.json) must register under the canonical
 * key and must never duplicate a control the widget already has.
 */
final class ParityControlsTest extends WP_UnitTestCase
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * Widget classes mapped to a shortcode tag (the parity feature only applies to these).
	 *
	 * @return array<string,string> class => tag
	 */
	private function mapped_widgets(): array
	{
		$map = array(
			'AvailabilityCalendarWidget' => 'rentiva_availability_calendar',
			'BookingFormWidget'          => 'rentiva_booking_form',
			'ContactFormWidget'          => 'rentiva_contact',
			'FeaturedVehiclesWidget'     => 'rentiva_featured_vehicles',
			'MyBookingsWidget'           => 'rentiva_my_bookings',
			'MyFavoritesWidget'          => 'rentiva_my_favorites',
			'PaymentHistoryWidget'       => 'rentiva_payment_history',
			'SearchResultsWidget'        => 'rentiva_search_results',
			'TestimonialsWidget'         => 'rentiva_testimonials',
			'UnifiedSearchWidget'        => 'rentiva_unified_search',
			'VehicleComparisonWidget'    => 'rentiva_vehicle_comparison',
			'VehicleDetailsWidget'       => 'rentiva_vehicle_details',
			'VehicleRatingWidget'        => 'rentiva_vehicle_rating_form',
			'VehiclesGridWidget'         => 'rentiva_vehicles_grid',
			'VehiclesListWidget'         => 'rentiva_vehicles_list',
			'VehicleCardWidget'          => 'rentiva_vehicles_list',
			'UserDashboardWidget'        => 'rentiva_user_dashboard',
		);

		$out = array();
		foreach ( $map as $class => $tag ) {
			$out[ self::NS . $class ] = $tag;
		}
		return $out;
	}

	/**
	 * Own-stack control ids that carry a value (no sections, no style-only controls
	 * is NOT applied here: a style control twin would be just as confusing).
	 *
	 * @return string[]
	 */
	private function own_control_ids( \Elementor\Widget_Base $widget ): array
	{
		$stack = $widget->get_stack( false );
		$ids   = array();
		foreach ( array( 'controls', 'style_controls' ) as $bucket ) {
			foreach ( (array) ( $stack[ $bucket ] ?? array() ) as $id => $control ) {
				if ( 'section' === ( $control['type'] ?? '' ) ) {
					continue;
				}
				$ids[] = (string) $id;
			}
		}
		return $ids;
	}

	public function test_no_parity_twin_of_existing_control(): void
	{
		foreach ( $this->mapped_widgets() as $class => $tag ) {
			$widget = WidgetFactory::make( $class );
			$schema = AllowlistRegistry::get_schema( $tag );

			$seen = array();
			foreach ( $this->own_control_ids( $widget ) as $id ) {
				// Elementor-internal ids (responsive suffixes, group parts) never map to attributes.
				$canonical = KeyNormalizer::normalize( $id, $schema );
				if ( ! isset( $schema[ $canonical ] ) ) {
					continue;
				}
				$seen[ $canonical ][] = $id;
			}

			foreach ( $seen as $canonical => $ids ) {
				$this->assertCount(
					1,
					array_unique( $ids ),
					"$class registers several controls for canonical key '$canonical': " . implode( ', ', $ids )
				);
			}
		}
	}

	public function test_parity_ids_are_canonical(): void
	{
		foreach ( $this->mapped_widgets() as $class => $tag ) {
			$widget = WidgetFactory::make( $class );
			$schema = AllowlistRegistry::get_schema( $tag );
			$stack  = $widget->get_stack( false );

			foreach ( (array) ( $stack['controls'] ?? array() ) as $id => $control ) {
				if ( 'mhmrentiva_parity_section' !== ( $control['section'] ?? '' ) || 'section' === ( $control['type'] ?? '' ) ) {
					continue;
				}
				$this->assertSame(
					KeyNormalizer::normalize( (string) $id, $schema ),
					(string) $id,
					"$class parity control '$id' is not a canonical key."
				);
				$this->assertArrayHasKey( $id, $schema, "$class parity control '$id' is not in the schema." );
			}
		}
	}

	public function test_stale_saved_twin_dropped_by_live_intersection(): void
	{
		$widget = WidgetFactory::make(
			VehiclesListWidget::class,
			array(
				'showPrice'  => 'yes',
				'show_price' => '',
			)
		);

		$settings = $widget->get_bridge_settings( 'rentiva_vehicles_list' );
		$this->assertArrayNotHasKey( 'showPrice', $settings );

		$atts = WidgetAttributeBridge::to_canonical( 'rentiva_vehicles_list', $settings, array() );
		$this->assertSame( '0', $atts['show_price'] );
	}
}
