<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingConfirmationWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'rv-booking-confirmation';
	}

	public function get_title(): string {
		return __( 'Booking Confirmation', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-check-circle';
	}

	protected function register_content_controls(): void {
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'info',
			array(
				'type'            => 'raw_html',
				'raw'             => __( 'Displays booking confirmation details.', 'mhm-rentiva' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void {
		// No style controls needed
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_booking_confirmation', $atts );
	}
}
