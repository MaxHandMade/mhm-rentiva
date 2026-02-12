<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Base;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

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
		return array( 'mhm-rentiva-category' );
	}

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
				'post_type'      => 'rentiva_vehicle',
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
}
