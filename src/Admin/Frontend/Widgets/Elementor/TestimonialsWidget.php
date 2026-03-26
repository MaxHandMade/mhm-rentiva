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

class TestimonialsWidget extends ElementorWidgetBase {



	public function get_name(): string {
		return 'rv-testimonials';
	}

	public function get_title(): string {
		return __( 'Testimonials', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-testimonial';
	}

	protected function register_content_controls(): void {
		// --- Settings section ---
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of Testimonials', 'mhm-rentiva' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 5,
				'min'     => 1,
				'max'     => 50,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'   => __( 'Grid', 'mhm-rentiva' ),
					'slider' => __( 'Slider', 'mhm-rentiva' ),
					'list'   => __( 'List', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '3',
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
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
					'date'     => __( 'Date', 'mhm-rentiva' ),
					'title'    => __( 'Title', 'mhm-rentiva' ),
					'rand'     => __( 'Random', 'mhm-rentiva' ),
					'modified' => __( 'Modified', 'mhm-rentiva' ),
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

		$this->add_control(
			'rating',
			array(
				'label'       => __( 'Minimum Rating', 'mhm-rentiva' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => null,
				'min'         => 1,
				'max'         => 5,
				'description' => __( 'Filter by minimum star rating (leave empty for all).', 'mhm-rentiva' ),
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
			'show_rating',
			array(
				'label'        => __( 'Show Rating', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_date',
			array(
				'label'        => __( 'Show Date', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_vehicle',
			array(
				'label'        => __( 'Show Vehicle Name', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_customer',
			array(
				'label'        => __( 'Show Customer Name', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'auto_rotate',
			array(
				'label'        => __( 'Auto Rotate', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '0',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => '1',
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

		$atts = array(
			'limit'         => (string) max( 1, (int) ( $settings['limit'] ?? 5 ) ),
			'layout'        => sanitize_text_field( (string) ( $settings['layout'] ?? 'grid' ) ),
			'columns'       => sanitize_text_field( (string) ( $settings['columns'] ?? '3' ) ),
			'orderby'       => sanitize_text_field( (string) ( $settings['orderby'] ?? 'date' ) ),
			'order'         => $order,
			'show_rating'   => $this->convert_switcher_to_boolean( $settings['show_rating'] ?? '1' ),
			'show_date'     => $this->convert_switcher_to_boolean( $settings['show_date'] ?? '1' ),
			'show_vehicle'  => $this->convert_switcher_to_boolean( $settings['show_vehicle'] ?? '1' ),
			'show_customer' => $this->convert_switcher_to_boolean( $settings['show_customer'] ?? '1' ),
			'auto_rotate'   => $this->convert_switcher_to_boolean( $settings['auto_rotate'] ?? '0' ),
		);

		// Only pass rating if it has a value
		$rating = $settings['rating'] ?? null;
		if ( $rating !== null && $rating !== '' ) {
			$atts['rating'] = (string) (int) $rating;
		}

		return $atts;
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_testimonials', $atts );
	}
}
