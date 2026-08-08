<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified Search Elementor Widget
 *
 * Displays the rental search form within Elementor.
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
		return __( 'Vehicle Search', 'mhm-rentiva' );
	}

	/**
	 * Widget description.
	 */
	public function get_description(): string {
		return __( 'Search form for vehicle rentals.', 'mhm-rentiva' );
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
			'layout',
			array(
				'label'   => __( 'Layout Style', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => __( 'Horizontal (Full)', 'mhm-rentiva' ),
					'vertical'   => __( 'Vertical (Sidebar)', 'mhm-rentiva' ),
				),
			)
		);

		// A 'Design Style' select (Glassmorphism / Solid) and a third 'Compact'
		// layout used to live here. Neither had a stylesheet behind it, so every
		// choice rendered identically -- controls that promised a change and
		// delivered none. Both are gone rather than left in the panel.

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

		$atts['show_rental_tab'] = ( $settings['show_rental_tab'] === 'yes' ) ? '1' : '0';
		$atts['layout']          = $settings['layout'];

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

		// Output widget wrapper. The wrapper used to carry an `rv-style--{glass|solid}`
		// class from the removed Design Style control; no stylesheet ever matched it.
		echo '<div class="elementor-widget-rv-unified-search">';
		$this->output_shortcode( 'rentiva_unified_search', $atts );
		echo '</div>';
	}
}
