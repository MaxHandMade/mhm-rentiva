<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\CalendarView;
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
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'vehicle_id',
			array(
				'label'       => __( 'Vehicle ID', 'mhm-rentiva' ),
				'type'        => 'text',
				'description' => __( 'Specific vehicle ID, or leave empty for the newest vehicle', 'mhm-rentiva' ),
			)
		);

		$this->add_control(
			'months_to_show',
			array(
				'label'   => __( 'Months to Show', 'mhm-rentiva' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 2,
				'min'     => 1,
				'max'     => CalendarView::MAX_MONTH_SPAN,
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
		if ( array_key_exists( 'months_to_show', $settings ) ) {
			$atts['months_to_show'] = (string) CalendarView::span( $settings['months_to_show'] );
		}
		return $atts;
	}

	/**
	 * The block's calendarHeight sizes BlockRegistry's wrapper, which a widget does
	 * not have; Elementor has its own sizing (spec 3A Task 6).
	 *
	 * @return string[]
	 */
	protected function get_parity_exclusions(): array {
		return array( 'height' );
	}

	protected function render(): void {
		$this->render_canonical( 'rentiva_availability_calendar' );
	}
}
