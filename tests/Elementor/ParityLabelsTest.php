<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ParityLabels;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Parity controls ("Additional Settings") take their labels and enum option labels
 * from the translatable ParityLabels dictionary.
 *
 * Elementor strips `label`/`options` from the control stack outside the editor
 * (CLI/PHPUnit), so these tests never read labels from Elementor: they iterate the
 * parity-section control ids and assert against ParityLabels, and compare SELECT
 * option keys (built by the same helper the widget base uses) with block.json enums.
 */
final class ParityLabelsTest extends WP_UnitTestCase
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * The 16 Lite widgets that have parity controls, with their shortcode tag and
	 * block slug. UserDashboardWidget has none and is deliberately absent.
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private const WIDGETS = array(
		'AvailabilityCalendarWidget' => array( 'rentiva_availability_calendar', 'availability-calendar' ),
		'BookingFormWidget'          => array( 'rentiva_booking_form', 'booking-form' ),
		'ContactFormWidget'          => array( 'rentiva_contact', 'contact' ),
		'FeaturedVehiclesWidget'     => array( 'rentiva_featured_vehicles', 'featured-vehicles' ),
		'MyBookingsWidget'           => array( 'rentiva_my_bookings', 'my-bookings' ),
		'MyFavoritesWidget'          => array( 'rentiva_my_favorites', 'my-favorites' ),
		'PaymentHistoryWidget'       => array( 'rentiva_payment_history', 'payment-history' ),
		'SearchResultsWidget'        => array( 'rentiva_search_results', 'search-results' ),
		'TestimonialsWidget'         => array( 'rentiva_testimonials', 'testimonials' ),
		'UnifiedSearchWidget'        => array( 'rentiva_unified_search', 'unified-search' ),
		'VehicleCardWidget'          => array( 'rentiva_vehicles_list', 'vehicles-list' ),
		'VehicleComparisonWidget'    => array( 'rentiva_vehicle_comparison', 'vehicle-comparison' ),
		'VehicleDetailsWidget'       => array( 'rentiva_vehicle_details', 'vehicle-details' ),
		'VehicleRatingWidget'        => array( 'rentiva_vehicle_rating_form', 'vehicle-rating-form' ),
		'VehiclesGridWidget'         => array( 'rentiva_vehicles_grid', 'vehicles-grid' ),
		'VehiclesListWidget'         => array( 'rentiva_vehicles_list', 'vehicles-list' ),
	);

	/**
	 * Enum parity controls measured in Task 0
	 * (docs/superpowers/audits/2026-10-11-dilim-3b1-ceviri/enum-parite.tsv): widget => canonical => values.
	 *
	 * @var array<string,array<string,string>>
	 */
	private const ENUM_PARITY = array(
		'VehicleCardWidget'          => array(
			'orderby' => 'title,price,popularity,newest,rating,rating_count,confidence',
			'order'   => 'asc,desc',
		),
		'UnifiedSearchWidget'        => array(
			'show_time_select'      => 'default,true,false',
			'show_dropoff_location' => 'default,true,false',
			'location_required'     => 'default,true,false',
			'fields_required'       => 'default,true,false',
		),
		'VehiclesListWidget'         => array(
			'orderby' => 'title,price,popularity,newest,rating,rating_count,confidence',
			'order'   => 'asc,desc',
		),
		'VehiclesGridWidget'         => array( 'layout' => 'grid,masonry' ),
		'AvailabilityCalendarWidget' => array( 'start_week_on' => '0,1' ),
		'MyFavoritesWidget'          => array( 'layout' => 'grid,list' ),
		'PaymentHistoryWidget'       => array(
			'status'  => 'all,pending,completed,cancelled',
			'orderby' => 'date,amount',
			'order'   => 'asc,desc',
		),
		'ContactFormWidget'          => array( 'type' => 'general,booking,support,feedback' ),
	);

	/**
	 * Parity-section controls of a widget: id => stack entry.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function parity_controls( string $short_class ): array
	{
		$widget = WidgetFactory::make( self::NS . $short_class );
		$stack  = $widget->get_stack( false );
		$out    = array();
		foreach ( (array) ( $stack['controls'] ?? array() ) as $id => $control ) {
			if ( 'mhmrentiva_parity_section' !== ( $control['section'] ?? '' ) || 'section' === ( $control['type'] ?? '' ) ) {
				continue;
			}
			$out[ (string) $id ] = (array) $control;
		}
		return $out;
	}

	/**
	 * block.json attribute config whose canonical key is $canonical.
	 *
	 * @return array<string,mixed>
	 */
	private function block_attribute( string $block, string $tag, string $canonical ): array
	{
		$json   = wp_json_file_decode( MHMRENTIVA_PLUGIN_DIR . 'assets/blocks/' . $block . '/block.json', array( 'associative' => true ) );
		$schema = AllowlistRegistry::get_schema( $tag );
		foreach ( (array) $json['attributes'] as $name => $config ) {
			if ( 'className' !== $name && KeyNormalizer::normalize( (string) $name, $schema ) === $canonical ) {
				return (array) $config;
			}
		}
		$this->fail( "No block.json attribute of '$block' normalizes to '$canonical'." );
	}

	public function test_every_lite_parity_control_has_label(): void
	{
		$total = 0;
		foreach ( self::WIDGETS as $class => $pair ) {
			$block    = $pair[1];
			$controls = $this->parity_controls( $class );
			$this->assertNotEmpty( $controls, "$class has no parity controls." );
			foreach ( array_keys( $controls ) as $id ) {
				++$total;
				$label = ParityLabels::label( $id, $block );
				$this->assertNotNull( $label, "$class parity control '$id' ($block) has no dictionary label." );
				$this->assertNotSame( '', $label, "$class parity control '$id' has an empty label." );
			}
		}
		$this->assertSame( 119, $total, 'Lite parity control count changed; update the dictionary and this pin.' );
	}

	public function test_user_dashboard_has_no_parity_controls(): void
	{
		$this->assertSame( array(), $this->parity_controls( 'UserDashboardWidget' ) );
	}

	public function test_every_enum_value_has_option_label(): void
	{
		foreach ( self::ENUM_PARITY as $class => $keys ) {
			$block = self::WIDGETS[ $class ][1];
			foreach ( $keys as $canonical => $values ) {
				foreach ( explode( ',', $values ) as $value ) {
					$this->assertNotNull(
						ParityLabels::option( $canonical, $value, $block ),
						"$class '$canonical' option '$value' ($block) has no dictionary label."
					);
				}
			}
		}
	}

	public function test_option_keys_unchanged(): void
	{
		$selects = 0;
		foreach ( self::WIDGETS as $class => $pair ) {
			list( $tag, $block ) = $pair;
			foreach ( $this->parity_controls( $class ) as $id => $control ) {
				if ( 'select' !== ( $control['type'] ?? '' ) ) {
					continue;
				}
				++$selects;
				$attr = $this->block_attribute( $block, $tag, $id );
				$this->assertArrayHasKey( 'enum', $attr, "$class select '$id' has no block.json enum." );

				$enum    = array_map( 'strval', (array) $attr['enum'] );
				$options = ParityLabels::select_options( $id, (array) $attr['enum'], $block );
				$this->assertSame( $enum, array_map( 'strval', array_keys( $options ) ), "$class '$id' option keys drifted from block.json enum." );

				foreach ( $options as $value => $label ) {
					$this->assertSame( ParityLabels::option( $id, (string) $value, $block ), $label, "$class '$id' option '$value' is not the dictionary label." );
				}

				$this->assertSame(
					self::ENUM_PARITY[ $class ][ $id ] ?? null,
					implode( ',', $enum ),
					"$class '$id' is not in the Task 0 enum inventory."
				);
			}
		}
		$this->assertSame( 15, $selects, 'Select parity control count drifted from the Task 0 inventory (14 controls + the vehicle card twin).' );
	}

	public function test_unknown_option_falls_back_to_raw_value(): void
	{
		$this->assertNull( ParityLabels::option( 'order', 'sideways', 'vehicles-list' ) );
		$this->assertSame( array( 'sideways' => 'sideways' ), ParityLabels::select_options( 'order', array( 'sideways' ), 'vehicles-list' ) );
		$this->assertNull( ParityLabels::label( 'no_such_key_xyz' ) );
	}

	public function test_labels_reuse_block_inspector_msgid(): void
	{
		// vehicles-grid inspector: sortBy -> 'Sort By', sortOrder -> 'Order' (sort direction, ruling T0-1), limit -> 'Limit'.
		$this->assertSame( __( 'Sort By', 'mhm-rentiva' ), ParityLabels::label( 'orderby', 'vehicles-grid' ) );
		$this->assertSame( _x( 'Order', 'sort direction', 'mhm-rentiva' ), ParityLabels::label( 'order', 'vehicles-grid' ) );
		$this->assertSame( __( 'Limit', 'mhm-rentiva' ), ParityLabels::label( 'limit', 'vehicles-grid' ) );

		// Same canonical key, different inspector string: the block-qualified entry wins.
		$this->assertSame( __( 'Sort Order', 'mhm-rentiva' ), ParityLabels::label( 'order', 'payment-history' ) );
		$this->assertSame( __( 'Limit Results', 'mhm-rentiva' ), ParityLabels::label( 'limit', 'payment-history' ) );
		$this->assertSame( __( 'Show Vehicle Image', 'mhm-rentiva' ), ParityLabels::label( 'show_image', 'my-bookings' ) );
		$this->assertSame( __( 'Show Image', 'mhm-rentiva' ), ParityLabels::label( 'show_image', 'vehicles-grid' ) );

		// show_time_select: boolean in booking-form, enum in unified-search.
		$this->assertSame( __( 'Time Select', 'mhm-rentiva' ), ParityLabels::label( 'show_time_select', 'unified-search' ) );
		$this->assertNotSame( ParityLabels::label( 'show_time_select', 'unified-search' ), ParityLabels::label( 'show_time_select', 'booking-form' ) );

		// Option labels reuse the inspector strings too.
		$this->assertSame( __( 'Most Reviewed', 'mhm-rentiva' ), ParityLabels::option( 'orderby', 'rating_count', 'vehicles-list' ) );
		$this->assertSame( __( 'Masonry', 'mhm-rentiva' ), ParityLabels::option( 'layout', 'masonry', 'vehicles-grid' ) );
		$this->assertSame( __( 'Sunday', 'mhm-rentiva' ), ParityLabels::option( 'start_week_on', '0', 'availability-calendar' ) );
		$this->assertSame( __( 'Optional (Browse All)', 'mhm-rentiva' ), ParityLabels::option( 'fields_required', 'false', 'unified-search' ) );
	}

	public function test_filter_extends_dictionary(): void
	{
		$cb = static function ( array $dict ): array {
			$dict['labels']['pro_only_key']             = 'Pro Only';
			$dict['options']['pro_only_key']['a']       = 'Option A';
			$dict['labels']['vendor-profile:show_logo'] = 'Show Logo';
			return $dict;
		};
		add_filter( 'mhmrentiva_parity_labels', $cb );
		try {
			$this->assertSame( 'Pro Only', ParityLabels::label( 'pro_only_key' ) );
			$this->assertSame( 'Option A', ParityLabels::option( 'pro_only_key', 'a' ) );
			$this->assertSame( 'Show Logo', ParityLabels::label( 'show_logo', 'vendor-profile' ) );
			$this->assertNull( ParityLabels::label( 'show_logo', 'vehicles-list' ) );
		} finally {
			remove_filter( 'mhmrentiva_parity_labels', $cb );
		}
		$this->assertNull( ParityLabels::label( 'pro_only_key' ) );
	}

	public function test_section_label(): void
	{
		$captured = null;
		$cb       = static function ( $element, $section_id, $args ) use ( &$captured ): void {
			if ( 'mhmrentiva_parity_section' === $section_id ) {
				$captured = $args['label'] ?? null;
			}
		};
		add_action( 'elementor/element/before_section_start', $cb, 10, 3 );
		try {
			$widget = WidgetFactory::make( self::NS . 'VehiclesListWidget' );
			// The stack is cached per widget type; rebuild it so the section is registered under the hook.
			\Elementor\Plugin::$instance->controls_manager->delete_stack( $widget );
			$widget->get_stack( false );
		} finally {
			remove_action( 'elementor/element/before_section_start', $cb, 10 );
		}
		$this->assertSame( 'Additional Settings', $captured );
		$this->assertSame( __( 'Additional Settings', 'mhm-rentiva' ), ParityLabels::section_label() );
	}
}
