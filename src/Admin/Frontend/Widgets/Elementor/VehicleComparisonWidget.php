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

class VehicleComparisonWidget extends ElementorWidgetBase {



	public function get_name(): string {
		return 'rv-vehicle-comparison';
	}

	public function get_title(): string {
		return __( 'Vehicle Comparison', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-h-align-stretch';
	}

	protected function register_content_controls(): void {
		// --- Content section ---
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'Content', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'vehicle_ids',
			array(
				'label'       => __( 'Vehicle IDs', 'mhm-rentiva' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Comma-separated vehicle IDs to pre-load (leave empty to use session comparison list).', 'mhm-rentiva' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'max_vehicles',
			array(
				'label'   => __( 'Max Vehicles', 'mhm-rentiva' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 4,
				'min'     => 2,
				'max'     => 6,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'table',
				'options' => array(
					'table' => __( 'Table', 'mhm-rentiva' ),
					'cards' => __( 'Cards', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'show_features',
			array(
				'label'   => __( 'Show Features', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'      => __( 'All', 'mhm-rentiva' ),
					'basic'    => __( 'Basic', 'mhm-rentiva' ),
					'detailed' => __( 'Detailed', 'mhm-rentiva' ),
				),
			)
		);

		$this->end_controls_section();

		// --- Visibility section ---
		$this->start_controls_section(
			'visibility_section',
			array(
				'label' => __( 'Visibility', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'show_images',
			array(
				'label'        => __( 'Show Images', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_prices',
			array(
				'label'        => __( 'Show Prices', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_booking_buttons',
			array(
				'label'        => __( 'Show Booking Buttons', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_add_vehicle',
			array(
				'label'        => __( 'Show Add Vehicle Button', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_remove_buttons',
			array(
				'label'        => __( 'Show Remove Buttons', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void {
		// No style controls needed
	}

	protected function prepare_shortcode_attributes( array $settings ): array {
		return array(
			'vehicle_ids'          => sanitize_text_field( (string) ( $settings['vehicle_ids'] ?? '' ) ),
			'max_vehicles'         => (string) max( 2, min( 6, (int) ( $settings['max_vehicles'] ?? 4 ) ) ),
			'layout'               => sanitize_text_field( (string) ( $settings['layout'] ?? 'table' ) ),
			'show_features'        => sanitize_text_field( (string) ( $settings['show_features'] ?? 'all' ) ),
			'show_images'          => $this->convert_switcher_to_boolean( $settings['show_images'] ?? '1' ),
			'show_prices'          => $this->convert_switcher_to_boolean( $settings['show_prices'] ?? '1' ),
			'show_booking_buttons' => $this->convert_switcher_to_boolean( $settings['show_booking_buttons'] ?? '1' ),
			'show_add_vehicle'     => $this->convert_switcher_to_boolean( $settings['show_add_vehicle'] ?? '1' ),
			'show_remove_buttons'  => $this->convert_switcher_to_boolean( $settings['show_remove_buttons'] ?? '1' ),
		);
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_vehicle_comparison', $atts );
	}
}
