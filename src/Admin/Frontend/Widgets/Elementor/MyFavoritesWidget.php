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

class MyFavoritesWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'rv-my-favorites';
	}

	public function get_title(): string {
		return __( 'My Favorites', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-heart';
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
				'raw'             => __( 'Displays user favorite vehicles.', 'mhm-rentiva' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Limit', 'mhm-rentiva' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 1,
				'max'     => 100,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '3',
				'options' => array(
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
					'date'  => __( 'Date Added', 'mhm-rentiva' ),
					'title' => __( 'Title', 'mhm-rentiva' ),
					'price' => __( 'Price', 'mhm-rentiva' ),
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

		// --- Visibility section ---
		$this->start_controls_section(
			'visibility_section',
			array(
				'label' => __( 'Visibility', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'Show Price', 'mhm-rentiva' ),
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
			'show_remove_button',
			array(
				'label'        => __( 'Show Remove Button', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'show_added_date',
			array(
				'label'        => __( 'Show Date Added', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '0',
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

		if ( array_key_exists( 'limit', $settings ) ) {
			$atts['limit'] = (string) max( 1, (int) $settings['limit'] );
		}

		foreach ( array( 'columns', 'orderby' ) as $key ) {
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

		foreach ( array( 'show_price', 'show_features', 'show_rating', 'show_booking_button', 'show_favorite_button', 'show_remove_button', 'show_added_date' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = $this->convert_switcher_to_boolean( $settings[ $key ] );
			}
		}

		return $atts;
	}

	protected function render(): void {
		$this->render_canonical( 'rentiva_my_favorites' );
	}
}
