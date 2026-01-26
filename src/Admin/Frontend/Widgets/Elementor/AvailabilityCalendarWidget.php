<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AvailabilityCalendarWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'rv-availability-calendar';
	}

	public function get_title(): string {
		return __( 'Availability Calendar', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-calendar';
	}

	protected function register_content_controls(): void {
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'Settings', 'mhm-rentiva' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'vehicle_id',
			array(
				'label'       => __( 'Vehicle ID', 'mhm-rentiva' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Specific vehicle ID or leave empty for current vehicle', 'mhm-rentiva' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void {
		// No style controls needed
	}

	protected function prepare_shortcode_attributes( array $settings ): array {
		$atts = array();
		if ( ! empty( $settings['vehicle_id'] ) ) {
			$atts['vehicle_id'] = $settings['vehicle_id'];
		}
		return $atts;
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( (array) $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_shortcode( 'rentiva_availability_calendar', $atts );
	}
}
