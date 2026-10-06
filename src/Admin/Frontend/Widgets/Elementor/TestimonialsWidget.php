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
		$atts = array();

		if ( array_key_exists( 'limit', $settings ) ) {
			$atts['limit'] = (string) max( 1, (int) $settings['limit'] );
		}

		foreach ( array( 'layout', 'columns', 'orderby' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = sanitize_text_field( (string) $settings[ $key ] );
			}
		}

		// Map only keys Elementor delivered; an absent key must fall through to the shortcode default.
		if ( array_key_exists( 'order', $settings ) ) {
			$order = strtoupper( sanitize_text_field( (string) $settings['order'] ) );
			if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				$atts['order'] = $order;
			}
		}

		foreach ( array( 'show_rating', 'show_date', 'show_vehicle', 'show_customer', 'auto_rotate' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = $this->convert_switcher_to_boolean( $settings[ $key ] );
			}
		}

		// Only pass rating if it has a value.
		$rating = $settings['rating'] ?? null;
		if ( null !== $rating && '' !== $rating ) {
			$atts['rating'] = (string) (int) $rating;
		}

		return $atts;
	}

	protected function render(): void {
		$this->render_canonical( 'rentiva_testimonials' );
	}
}
