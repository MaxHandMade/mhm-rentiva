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

class VehicleDetailsWidget extends ElementorWidgetBase {



	public function get_name(): string {
		return 'rv-vehicle-details';
	}

	public function get_title(): string {
		return __( 'Vehicle Details', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-info-circle';
	}

	protected function register_content_controls(): void {
		// --- Content section ---
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'vehicle_id',
			array(
				'label'       => __( 'Vehicle ID', 'mhm-rentiva' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Specific vehicle ID or leave empty for current vehicle.', 'mhm-rentiva' ),
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
			'show_gallery',
			array(
				'label'        => __( 'Show Gallery', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_features',
			array(
				'label'        => __( 'Show Features', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_calendar',
			array(
				'label'        => __( 'Show Calendar', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_pricing',
			array(
				'label'        => __( 'Show Pricing', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'Show Price Tag', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_booking',
			array(
				'label'        => __( 'Show Booking Section', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_booking_button',
			array(
				'label'        => __( 'Show Booking Button', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_favorite_button',
			array(
				'label'        => __( 'Show Favorite Button', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_compare_button',
			array(
				'label'        => __( 'Show Compare Button', 'mhm-rentiva' ),
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
		$atts = array(
			'show_gallery'         => $this->convert_switcher_to_boolean( $settings['show_gallery'] ?? '1' ),
			'show_features'        => $this->convert_switcher_to_boolean( $settings['show_features'] ?? '1' ),
			'show_calendar'        => $this->convert_switcher_to_boolean( $settings['show_calendar'] ?? '1' ),
			'show_pricing'         => $this->convert_switcher_to_boolean( $settings['show_pricing'] ?? '1' ),
			'show_price'           => $this->convert_switcher_to_boolean( $settings['show_price'] ?? '1' ),
			'show_booking'         => $this->convert_switcher_to_boolean( $settings['show_booking'] ?? '1' ),
			'show_booking_button'  => $this->convert_switcher_to_boolean( $settings['show_booking_button'] ?? '1' ),
			'show_favorite_button' => $this->convert_switcher_to_boolean( $settings['show_favorite_button'] ?? '1' ),
			'show_compare_button'  => $this->convert_switcher_to_boolean( $settings['show_compare_button'] ?? '1' ),
		);

		if ( ! empty( $settings['vehicle_id'] ) ) {
			$atts['vehicle_id'] = sanitize_text_field( (string) $settings['vehicle_id'] );
		}

		return $atts;
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_shortcode( 'rentiva_vehicle_details', $atts );
	}
}
