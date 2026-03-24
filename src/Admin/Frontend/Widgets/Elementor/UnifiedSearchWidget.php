<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified Search Elementor Widget
 *
 * Displays the unified rental and transfer search form within Elementor.
 *
 * @since 3.0.1
 */
class UnifiedSearchWidget extends ElementorWidgetBase {



	/**
	 * Widget slug.
	 */
	public function get_name(): string {
		return 'rv-vehicle-search';
	}

	/**
	 * Widget title.
	 */
	public function get_title(): string {
		return __( 'Unified Search & Transfer', 'mhm-rentiva' );
	}

	/**
	 * Widget description.
	 */
	public function get_description(): string {
		return __( 'Advanced search form for vehicle rentals and VIP transfers.', 'mhm-rentiva' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon(): string {
		return 'eicon-search';
	}

	/**
	 * Widget keywords.
	 */
	public function get_keywords(): array {
		return array_merge(
			$this->widget_keywords,
			array(
				'search',
				'find',
				'transfer',
				'rental',
				'unified',
			)
		);
	}

	/**
	 * Register content tab controls.
	 */
	protected function register_content_controls(): void {
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'General Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'service_type',
			array(
				'label'   => __( 'Service Type', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'both',
				'options' => array(
					'both'     => __( 'Both (Rental & Transfer)', 'mhm-rentiva' ),
					'rental'   => __( 'Rental Only', 'mhm-rentiva' ),
					'transfer' => __( 'Transfer Only', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout Style', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => __( 'Horizontal (Full)', 'mhm-rentiva' ),
					'vertical'   => __( 'Vertical (Sidebar)', 'mhm-rentiva' ),
					'compact'    => __( 'Compact', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'style',
			array(
				'label'   => __( 'Design Style', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'glass',
				'options' => array(
					'glass' => __( 'Glassmorphism', 'mhm-rentiva' ),
					'solid' => __( 'Solid', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'default_tab',
			array(
				'label'     => __( 'Default Active Tab', 'mhm-rentiva' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'rental',
				'options'   => array(
					'rental'   => __( 'Rental', 'mhm-rentiva' ),
					'transfer' => __( 'Transfer', 'mhm-rentiva' ),
				),
				'condition' => array(
					'service_type' => 'both',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'visibility_section',
			array(
				'label' => __( 'Visibility Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'show_rental_tab',
			array(
				'label'        => __( 'Show Rental Tab', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => array(
					'service_type' => 'both',
				),
			)
		);

		$this->add_control(
			'show_transfer_tab',
			array(
				'label'        => __( 'Show Transfer Tab', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => array(
					'service_type' => 'both',
				),
			)
		);

		$this->add_control(
			'show_location_select',
			array(
				'label'        => __( 'Show Location Select', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_date_picker',
			array(
				'label'        => __( 'Show Date Picker', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register style tab controls.
	 */
	protected function register_style_controls(): void {
		$this->register_standard_style_controls( 'main', __( 'General Style', 'mhm-rentiva' ), '.rv-unified-search' );
	}

	/**
	 * Prepare shortcode attributes from widget settings.
	 */
	protected function prepare_shortcode_attributes( array $settings ): array {
		$atts = array();

		// Mapping service types
		if ( $settings['service_type'] !== 'both' ) {
			$atts['show_rental_tab']   = ( $settings['service_type'] === 'rental' ) ? '1' : '0';
			$atts['show_transfer_tab'] = ( $settings['service_type'] === 'transfer' ) ? '1' : '0';
		} else {
			$atts['show_rental_tab']   = ( $settings['show_rental_tab'] === 'yes' ) ? '1' : '0';
			$atts['show_transfer_tab'] = ( $settings['show_transfer_tab'] === 'yes' ) ? '1' : '0';
		}

		$atts['layout']      = $settings['layout'];
		$atts['style']       = $settings['style'];
		$atts['default_tab'] = $settings['default_tab'];

		$atts['show_location_select'] = ( $settings['show_location_select'] === 'yes' ) ? '1' : '0';
		$atts['show_date_picker']     = ( $settings['show_date_picker'] === 'yes' ) ? '1' : '0';

		return $atts;
	}

	/**
	 * Render widget output.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		// Prepare shortcode attributes
		$atts = $this->prepare_shortcode_attributes( $settings );

		// Render shortcode output
		$shortcode_output = $this->render_shortcode( 'rentiva_unified_search', $atts );

		// Output widget wrapper
		printf( '<div class="elementor-widget-rv-unified-search rv-style--%s">', esc_attr( $settings['style'] ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $shortcode_output;
		echo '</div>';
	}
}
