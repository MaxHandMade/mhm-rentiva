<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicles Grid Elementor Widget
 */
class VehiclesGridWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'mhm_rentiva_vehicles_grid';
	}

	public function get_title(): string {
		return __( 'MHM Vehicles Grid', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-gallery-grid';
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content Settings', 'mhm-rentiva' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Limit', 'mhm-rentiva' ),
				'type'    => 'number',
				'default' => 12,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'mhm-rentiva' ),
				'type'    => 'select',
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
				'type'    => 'select',
				'default' => 'title',
				'options' => array(
					'title'    => __( 'Title', 'mhm-rentiva' ),
					'date'     => __( 'Date', 'mhm-rentiva' ),
					'price'    => __( 'Price', 'mhm-rentiva' ),
					'featured' => __( 'Featured', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'ASC',
				'options' => array(
					'ASC'  => __( 'Ascending', 'mhm-rentiva' ),
					'DESC' => __( 'Descending', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'     => __( 'Show Price', 'mhm-rentiva' ),
				'type'      => 'switcher',
				'default'   => 'yes',
				'label_on'  => __( 'Show', 'mhm-rentiva' ),
				'label_off' => __( 'Hide', 'mhm-rentiva' ),
			)
		);

		$this->end_controls_section();

		$this->register_standard_style_controls(
			'title_style',
			__( 'Vehicle Title', 'mhm-rentiva' ),
			'.rv-vehicle-card__title a'
		);

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
		echo $this->render_shortcode( 'rentiva_vehicles_grid', $atts );
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
