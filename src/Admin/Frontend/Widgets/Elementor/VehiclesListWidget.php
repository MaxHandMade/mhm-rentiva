<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicles List Elementor Widget
 * * Automatically maps Elementor controls to MHM Shortcode attributes.
 */
class VehiclesListWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'mhm_rentiva_vehicles_list';
	}

	public function get_title(): string {
		return __( 'MHM Vehicles List', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-post-list';
	}

	protected function register_controls(): void {
		// --- CONTENT SECTION ---
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content Settings', 'mhm-rentiva' ),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'mhm-rentiva' ),
				'type'    => 'select',
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
			'show_images',
			array(
				'label'        => __( 'Show Images', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'   => __( 'Show Price', 'mhm-rentiva' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'booking_btn_text',
			array(
				'label'       => __( 'Button Text', 'mhm-rentiva' ),
				'type'        => 'text',
				'placeholder' => __( 'Book Now', 'mhm-rentiva' ),
				'default'     => __( 'Book Now', 'mhm-rentiva' ),
			)
		);

		$this->end_controls_section();

		// --- STYLE SECTION (The Magic Part) ---
		// Başlık stili için otomatik selector bağlantısı
		$this->register_standard_style_controls(
			'title_style',
			__( 'Vehicle Title', 'mhm-rentiva' ),
			'.rv-vehicle-card__title a'
		);

		// Fiyat stili için otomatik selector bağlantısı
		$this->register_standard_style_controls(
			'price_style',
			__( 'Price Tag', 'mhm-rentiva' ),
			'.rv-price-amount'
		);

		$this->register_parity_controls_from_block();
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_vehicles_list', $atts );
	}

	private static function include_template_with_vars( string $template_path, array $template_data ): void {
		( static function () use ( $template_path, $template_data ): void {
			foreach ( $template_data as $key => $value ) {
				if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
					continue;
				}
				${$key} = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			}
			include $template_path;
		} )();
	}
}
