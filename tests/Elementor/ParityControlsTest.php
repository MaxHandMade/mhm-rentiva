<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget;
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

	/**
	 * The card forces limit=1 / columns=1 in its explicit mapping, so it opts those
	 * keys out of parity (get_parity_exclusions()); the list widget, built from the
	 * same block.json, still has them. Pins that the exclusion is the only cause.
	 */
	public function test_vehicle_card_excludes_limit_and_columns_from_parity(): void
	{
		$card = WidgetFactory::make( VehicleCardWidget::class );
		\Elementor\Plugin::$instance->controls_manager->delete_stack( $card );
		$card_ids = $this->own_control_ids( $card );

		$this->assertNotContains( 'limit', $card_ids );
		$this->assertNotContains( 'columns', $card_ids );

		$list_ids = $this->own_control_ids( WidgetFactory::make( VehiclesListWidget::class ) );
		$this->assertContains( 'limit', $list_ids );
		$this->assertContains( 'columns', $list_ids );
	}

	/**
	 * The block.json the parity feature reads comes from an overridable method
	 * (Pro widgets point it at their own assets). Each probe has its own name so
	 * Elementor's per-name controls-stack cache never touches the real widget.
	 */
	public function test_parity_source_is_overridable(): void
	{
		$data = array(
			'id'       => 'tprobe',
			'elType'   => 'widget',
			'settings' => array(),
		);

		$empty = new class( $data, array() ) extends VehiclesListWidget {
			public function get_name(): string
			{
				return 'zz_parity_probe_a';
			}

			protected function get_mapped_shortcode_tag(): string
			{
				return 'rentiva_vehicles_list';
			}

			protected function get_parity_block_json_path(): string
			{
				return '';
			}
		};

		$temp_dir  = trailingslashit( get_temp_dir() ) . 'zz-probe';
		$temp_json = $temp_dir . '/block.json';

		$foreign = new class( $data, array() ) extends VehiclesListWidget {
			public string $path = '';

			public function get_name(): string
			{
				return 'zz_parity_probe_b';
			}

			protected function get_mapped_shortcode_tag(): string
			{
				return 'rentiva_vehicles_list';
			}

			protected function get_parity_block_json_path(): string
			{
				return $this->path;
			}
		};
		$foreign->path = $temp_json;

		$lite = new class( $data, array() ) extends VehiclesListWidget {
			public function get_name(): string
			{
				return 'zz_parity_probe_c';
			}

			protected function get_mapped_shortcode_tag(): string
			{
				return 'rentiva_vehicles_list';
			}

			protected function get_parity_block_json_path(): string
			{
				return trailingslashit( MHMRENTIVA_PLUGIN_DIR ) . 'assets/blocks/vehicles-list/block.json';
			}
		};

		$manager = \Elementor\Plugin::$instance->controls_manager;
		$parity  = static function ( \Elementor\Widget_Base $widget ) use ( $manager ): array {
			$manager->delete_stack( $widget );
			$stack = $widget->get_stack( false );
			$ids   = array();
			foreach ( (array) ( $stack['controls'] ?? array() ) as $id => $control ) {
				if ( 'mhmrentiva_parity_section' === ( $control['section'] ?? '' ) && 'section' !== ( $control['type'] ?? '' ) ) {
					$ids[] = (string) $id;
				}
			}
			sort( $ids );
			return $ids;
		};

		try {
			// (a) Empty path: no parity section at all.
			$manager->delete_stack( $empty );
			$this->assertArrayNotHasKey( 'mhmrentiva_parity_section', $empty->get_stack( false )['controls'] ?? array() );

			// (b) Foreign block.json whose key is not in the shortcode schema: no section, no fatal.
			wp_mkdir_p( $temp_dir );
			file_put_contents( $temp_json, '{"attributes":{"showZz":{"type":"boolean","default":true}}}' );
			$manager->delete_stack( $foreign );
			$this->assertArrayNotHasKey( 'mhmrentiva_parity_section', $foreign->get_stack( false )['controls'] ?? array() );

			// (c) Lite's own block.json: the path is really read and matches the real widget.
			$manager->delete_stack( $lite );
			$this->assertArrayHasKey( 'mhmrentiva_parity_section', $lite->get_stack( false )['controls'] ?? array() );
			$real_ids = $parity( WidgetFactory::make( VehiclesListWidget::class ) );
			$this->assertNotEmpty( $real_ids );
			$this->assertSame( $real_ids, $parity( $lite ) );
		} finally {
			if ( file_exists( $temp_json ) ) {
				unlink( $temp_json );
			}
			if ( is_dir( $temp_dir ) ) {
				rmdir( $temp_dir );
			}
			foreach ( array( $empty, $foreign, $lite ) as $probe ) {
				$manager->delete_stack( $probe );
			}
			$manager->delete_stack( WidgetFactory::make( VehiclesListWidget::class ) );
		}
	}
}
