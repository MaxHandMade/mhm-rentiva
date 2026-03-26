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

class MyBookingsWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'rv-my-bookings';
	}

	public function get_title(): string {
		return __( 'My Bookings', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-calendar';
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
				'raw'             => __( 'Displays user booking history.', 'mhm-rentiva' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Bookings Per Page', 'mhm-rentiva' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 10,
				'min'     => 1,
				'max'     => 100,
			)
		);

		$this->add_control(
			'status',
			array(
				'label'   => __( 'Filter by Status', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''            => __( 'All', 'mhm-rentiva' ),
					'pending'     => __( 'Pending', 'mhm-rentiva' ),
					'confirmed'   => __( 'Confirmed', 'mhm-rentiva' ),
					'in_progress' => __( 'In Progress', 'mhm-rentiva' ),
					'completed'   => __( 'Completed', 'mhm-rentiva' ),
					'cancelled'   => __( 'Cancelled', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order By', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date' => __( 'Date', 'mhm-rentiva' ),
					'id'   => __( 'ID', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => array(
					'DESC' => __( 'Descending', 'mhm-rentiva' ),
					'ASC'  => __( 'Ascending', 'mhm-rentiva' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void {
		// No style controls needed
	}

	protected function prepare_shortcode_attributes( array $settings ): array {
		$order = strtoupper( sanitize_text_field( (string) ( $settings['order'] ?? 'DESC' ) ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		return array(
			'limit'   => (string) max( 1, (int) ( $settings['limit'] ?? 10 ) ),
			'status'  => sanitize_text_field( (string) ( $settings['status'] ?? '' ) ),
			'orderby' => sanitize_text_field( (string) ( $settings['orderby'] ?? 'date' ) ),
			'order'   => $order,
		);
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_my_bookings', $atts );
	}
}
