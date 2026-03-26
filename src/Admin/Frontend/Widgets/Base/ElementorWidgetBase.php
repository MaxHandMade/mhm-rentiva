<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Base;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MHM Rentiva Elementor Base Class
 * Provides base compatibility and shared features for all Elementor widgets.
 */
abstract class ElementorWidgetBase extends Widget_Base {

	/**
	 * Default keywords for all widgets.
	 *
	 * @var array
	 */
	protected array $widget_keywords = array( 'mhm', 'rentiva' );

	/**
	 * Get Widget Categories
	 */
	public function get_categories(): array {
		return array( 'mhm-rentiva' );
	}

	/**
	 * Get Widget Keywords
	 * Returns the default keywords so all widgets appear in Elementor search results.
	 */
	public function get_keywords(): array {
		return $this->widget_keywords;
	}

	/**
	 * Elementor lifecycle entrypoint for controls.
	 * Child widgets may either override this method directly or implement
	 * register_content_controls/register_style_controls hooks.
	 */
	protected function register_controls(): void {
		if ( method_exists( $this, 'register_content_controls' ) ) {
			$this->register_content_controls();
		}

		if ( method_exists( $this, 'register_style_controls' ) ) {
			$this->register_style_controls();
		}

		$this->register_parity_controls_from_block();
	}

	/**
	 * Optional hook for child widgets.
	 */
	protected function register_content_controls(): void {}

	/**
	 * Optional hook for child widgets.
	 */
	protected function register_style_controls(): void {}

	/**
	 * Automated Attribute Preparation
	 * Converts Elementor settings directly to Shortcode attributes.
	 *
	 * @return array
	 */
	protected function get_prepared_atts(): array {
		$settings = $this->get_settings_for_display();
		$atts     = array();

		foreach ( $settings as $key => $value ) {
			// Convert 'yes'/'no' to '1'/'0' for shortcode compatibility
			if ( $value === 'yes' ) {
				$atts[ $key ] = '1';
			} elseif ( $value === 'no' ) {
				$atts[ $key ] = '0';
			} else {
				$atts[ $key ] = $value;
			}
		}

		// Sanitize everything before usage
		return array_map(
			function ( $val ) {
				return is_string( $val ) ? sanitize_text_field( $val ) : $val;
			},
			$atts
		);
	}

	/**
	 * Convert Elementor switcher-like values to canonical shortcode boolean strings.
	 *
	 * @param mixed $value Raw widget setting value.
	 * @return string '1' for enabled, '0' for disabled.
	 */
	protected function convert_switcher_to_boolean( mixed $value ): string {
		if ( \in_array( $value, array( 'yes', '1', 1, true, 'on' ), true ) ) {
			return '1';
		}

		return '0';
	}

	/**
	 * Render Shortcode Helper
	 *
	 * @param string $tag  Shortcode tag.
	 * @param array  $atts Shortcode attributes.
	 * @return string
	 */
	protected function render_shortcode( string $tag, array $atts = array() ): string {
		$atts_string = '';
		foreach ( $atts as $key => $value ) {
			$atts_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}
		return do_shortcode( sprintf( '[%s%s]', $tag, $atts_string ) );
	}
	/**
	 * Standard Style Controls
	 * Shared typography and color settings for all MHM widgets.
	 *
	 * @param string $section_id Section ID.
	 * @param string $label      Section label.
	 * @param string $selector   CSS selector.
	 */
	protected function register_standard_style_controls( string $section_id, string $label, string $selector ): void {
		$this->start_controls_section(
			$section_id,
			array(
				'label' => $label,
				'tab'   => 'style',
			)
		);

		$this->add_control(
			$section_id . '_color',
			array(
				'label'     => __( 'Text Color', 'mhm-rentiva' ),
				'type'      => 'color',
				'selectors' => array(
					'{{WRAPPER}} ' . $selector => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_typography_control( $selector, $label );

		$this->end_controls_section();
	}

	/**
	 * Helper: Add Typography Control
	 */
	protected function add_typography_control( string $selector, string $label ): void {
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => sanitize_title( $label ) . '_typography',
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
	}

	/**
	 * Helper: Add Border Control
	 */
	protected function add_border_control( string $selector, string $label ): void {
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => sanitize_title( $label ) . '_border',
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
	}

	/**
	 * Helper: Add Box Shadow Control
	 */
	protected function add_box_shadow_control( string $selector, string $label ): void {
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => sanitize_title( $label ) . '_shadow',
				'selector' => '{{WRAPPER}} ' . $selector,
			)
		);
	}

	/**
	 * Prepare attributes for shortcode (Default implementation)
	 */
	protected function prepare_shortcode_attributes( array $settings ): array {
		return $this->get_prepared_atts();
	}

	/**
	 * Helper: Add Vehicle Selection Control
	 */
	protected function add_vehicle_selection_control(): void {
		$this->add_control(
			'vehicle_id',
			array(
				'label'       => __( 'Select Vehicle', 'mhm-rentiva' ),
				'type'        => 'select2',
				'label_block' => true,
				'multiple'    => false,
				'options'     => $this->get_vehicle_options(),
			)
		);
	}

	/**
	 * Get all vehicles for select options
	 */
	protected function get_vehicle_options(): array {
		$vehicles = get_posts(
			array(
				'post_type'      => 'vehicle',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$options = array( '' => __( 'Select a vehicle', 'mhm-rentiva' ) );
		foreach ( $vehicles as $vehicle ) {
			$options[ $vehicle->ID ] = $vehicle->post_title;
		}

		return $options;
	}

	/**
	 * Helper: Add Layout Control
	 */
	protected function add_layout_control(): void {
		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'list',
				'options' => array(
					'list' => __( 'List', 'mhm-rentiva' ),
					'grid' => __( 'Grid', 'mhm-rentiva' ),
				),
			)
		);
	}

	/**
	 * Auto-add missing widget controls from corresponding Gutenberg block attributes.
	 * This keeps Elementor controls aligned with canonical block/shortcode contracts.
	 */
	protected function register_parity_controls_from_block(): void {
		$shortcode_tag = $this->get_mapped_shortcode_tag();
		if ( '' === $shortcode_tag ) {
			return;
		}

		$block_slug = self::get_block_slug_by_shortcode_tag( $shortcode_tag );
		if ( '' === $block_slug ) {
			return;
		}

		$json_path = trailingslashit( MHM_RENTIVA_PLUGIN_DIR ) . 'assets/blocks/' . $block_slug . '/block.json';
		if ( ! file_exists( $json_path ) ) {
			return;
		}

		// Local file read for plugin-owned block metadata.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $json_path );
		if ( false === $raw ) {
			return;
		}

		$block_json = json_decode( $raw, true );
		if ( ! is_array( $block_json ) || empty( $block_json['attributes'] ) || ! is_array( $block_json['attributes'] ) ) {
			return;
		}

		$schema            = AllowlistRegistry::get_schema( $shortcode_tag );
		$existing_controls = array_keys( $this->get_controls() );
		$missing           = array();

		foreach ( $block_json['attributes'] as $attr_name => $attr_config ) {
			if ( ! is_string( $attr_name ) || '' === $attr_name || ! is_array( $attr_config ) ) {
				continue;
			}

			// Skip className because Elementor already provides this in Advanced tab.
			if ( 'className' === $attr_name ) {
				continue;
			}

			if ( in_array( $attr_name, $existing_controls, true ) ) {
				continue;
			}

			$canonical = KeyNormalizer::normalize( $attr_name, $schema );
			if ( ! isset( $schema[ $canonical ] ) ) {
				continue;
			}

			$missing[ $attr_name ] = $attr_config;
		}

		if ( empty( $missing ) ) {
			return;
		}

		$this->start_controls_section(
			'mhm_parity_section',
			array(
				'label' => __( 'Parity Controls', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		foreach ( $missing as $attr_name => $attr_config ) {
			$this->add_parity_control_from_schema( $attr_name, $attr_config );
		}

		$this->end_controls_section();
	}

	/**
	 * Adds one Elementor control derived from block attribute schema.
	 */
	private function add_parity_control_from_schema( string $attr_name, array $attr_config ): void {
		$type    = isset( $attr_config['type'] ) && is_string( $attr_config['type'] ) ? $attr_config['type'] : 'string';
		$default = $attr_config['default'] ?? '';
		$label   = $this->format_control_label( $attr_name );

		if ( 'boolean' === $type ) {
			$this->add_control(
				$attr_name,
				array(
					'label'        => $label,
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $default ? 'yes' : 'no',
				)
			);
			return;
		}

		if ( ( 'integer' === $type || 'number' === $type ) && isset( $attr_config['enum'] ) && is_array( $attr_config['enum'] ) ) {
			$options = array();
			foreach ( $attr_config['enum'] as $enum_value ) {
				if ( is_scalar( $enum_value ) ) {
					$enum_key             = (string) $enum_value;
					$options[ $enum_key ] = $enum_key;
				}
			}

			$this->add_control(
				$attr_name,
				array(
					'label'   => $label,
					'type'    => Controls_Manager::SELECT,
					'options' => $options,
					'default' => is_scalar( $default ) ? (string) $default : '',
				)
			);
			return;
		}

		if ( ( 'string' === $type || 'integer' === $type || 'number' === $type ) && isset( $attr_config['enum'] ) && is_array( $attr_config['enum'] ) ) {
			$options = array();
			foreach ( $attr_config['enum'] as $enum_value ) {
				if ( is_scalar( $enum_value ) ) {
					$enum_key             = (string) $enum_value;
					$options[ $enum_key ] = $enum_key;
				}
			}

			$this->add_control(
				$attr_name,
				array(
					'label'   => $label,
					'type'    => Controls_Manager::SELECT,
					'options' => $options,
					'default' => is_scalar( $default ) ? (string) $default : '',
				)
			);
			return;
		}

		if ( 'integer' === $type || 'number' === $type ) {
			$this->add_control(
				$attr_name,
				array(
					'label'   => $label,
					'type'    => Controls_Manager::NUMBER,
					'default' => is_numeric( $default ) ? (float) $default : null,
				)
			);
			return;
		}

		$this->add_control(
			$attr_name,
			array(
				'label'   => $label,
				'type'    => Controls_Manager::TEXT,
				'default' => is_scalar( $default ) ? (string) $default : '',
			)
		);
	}

	/**
	 * Resolve shortcode tag by widget class for parity mapping.
	 */
	protected function get_mapped_shortcode_tag(): string {
		$class = (string) static::class;
		$class = str_replace('\\', '/', $class);
		$class = basename( $class );

		$map = array(
			'AvailabilityCalendarWidget' => 'rentiva_availability_calendar',
			'BookingFormWidget'          => 'rentiva_booking_form',
			'ContactFormWidget'          => 'rentiva_contact',
			'FeaturedVehiclesWidget'     => 'rentiva_featured_vehicles',
			'MyBookingsWidget'           => 'rentiva_my_bookings',
			'MyFavoritesWidget'          => 'rentiva_my_favorites',
			'MyMessagesWidget'           => 'rentiva_messages',
			'PaymentHistoryWidget'       => 'rentiva_payment_history',
			'SearchResultsWidget'        => 'rentiva_search_results',
			'TestimonialsWidget'         => 'rentiva_testimonials',
			'TransferResultsWidget'      => 'rentiva_transfer_results',
			'TransferSearchWidget'       => 'rentiva_transfer_search',
			'UnifiedSearchWidget'        => 'rentiva_unified_search',
			'VehicleComparisonWidget'    => 'rentiva_vehicle_comparison',
			'VehicleDetailsWidget'       => 'rentiva_vehicle_details',
			'VehicleRatingWidget'        => 'rentiva_vehicle_rating_form',
			'VehiclesGridWidget'         => 'rentiva_vehicles_grid',
			'VehiclesListWidget'         => 'rentiva_vehicles_list',
			'VehicleCardWidget'          => 'rentiva_vehicles_list',
			'UserDashboardWidget'        => 'rentiva_user_dashboard',
		);

		return $map[ $class ] ?? '';
	}

	/**
	 * Resolve block slug by shortcode tag.
	 */
	private static function get_block_slug_by_shortcode_tag( string $shortcode_tag ): string {
		$map = array(
			'rentiva_availability_calendar' => 'availability-calendar',
			'rentiva_booking_form'          => 'booking-form',
			'rentiva_contact'               => 'contact',
			'rentiva_featured_vehicles'     => 'featured-vehicles',
			'rentiva_messages'              => 'messages',
			'rentiva_my_bookings'           => 'my-bookings',
			'rentiva_my_favorites'          => 'my-favorites',
			'rentiva_payment_history'       => 'payment-history',
			'rentiva_search_results'        => 'search-results',
			'rentiva_testimonials'          => 'testimonials',
			'rentiva_transfer_results'      => 'transfer-results',
			'rentiva_transfer_search'       => 'transfer-search',
			'rentiva_unified_search'        => 'unified-search',
			'rentiva_vehicle_comparison'    => 'vehicle-comparison',
			'rentiva_vehicle_details'       => 'vehicle-details',
			'rentiva_vehicle_rating_form'   => 'vehicle-rating-form',
			'rentiva_vehicles_grid'         => 'vehicles-grid',
			'rentiva_vehicles_list'         => 'vehicles-list',
			'rentiva_user_dashboard'        => 'user-dashboard',
		);

		return $map[ $shortcode_tag ] ?? '';
	}

	/**
	 * Convert control key to readable label.
	 */
	private function format_control_label( string $name ): string {
		$name = preg_replace( '/(?<!^)[A-Z]/', ' $0', $name );
		$name = str_replace( '_', ' ', (string) $name );
		return ucwords( trim( (string) $name ) );
	}
}
