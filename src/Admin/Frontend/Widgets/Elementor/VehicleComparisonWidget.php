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
		$atts = array();

		foreach ( array( 'vehicle_ids' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = sanitize_text_field( (string) $settings[ $key ] );
			}
		}

		if ( array_key_exists( 'max_vehicles', $settings ) ) {
			$atts['max_vehicles'] = (string) max( 2, min( 6, (int) $settings['max_vehicles'] ) );
		}

		foreach ( array( 'layout', 'show_features' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = sanitize_text_field( (string) $settings[ $key ] );
			}
		}

		foreach ( array( 'show_images', 'show_prices', 'show_booking_buttons', 'show_remove_buttons' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = $this->convert_switcher_to_boolean( $settings[ $key ] );
			}
		}

		return $atts;
	}

	protected function render(): void {
		$this->render_canonical( 'rentiva_vehicle_comparison' );
	}
}
