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
 * Featured Vehicles Elementor Widget
 */
class FeaturedVehiclesWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'mhm_rentiva_featured_vehicles';
	}

	public function get_title(): string {
		return __( 'MHM Featured Vehicles', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-star';
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
				'default' => 6,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'slider',
				'options' => array(
					'slider' => __( 'Slider', 'mhm-rentiva' ),
					'grid'   => __( 'Grid', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'     => __( 'Columns', 'mhm-rentiva' ),
				'type'      => 'select',
				'default'   => '3',
				'options'   => array(
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'condition' => array(
					'layout' => 'grid',
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
					'date'  => __( 'Date', 'mhm-rentiva' ),
					'title' => __( 'Title', 'mhm-rentiva' ),
					'price' => __( 'Price', 'mhm-rentiva' ),
					'rand'  => __( 'Random', 'mhm-rentiva' ),
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
			'show_book_button',
			array(
				'label'        => __( 'Show Book Button', 'mhm-rentiva' ),
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

		$this->add_control(
			'show_category',
			array(
				'label'        => __( 'Show Category', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Show', 'mhm-rentiva' ),
				'label_off'    => __( 'Hide', 'mhm-rentiva' ),
				'return_value' => '1',
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Autoplay Slider', 'mhm-rentiva' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '1',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => '1',
				'condition'    => array(
					'layout' => 'slider',
				),
			)
		);

		$this->end_controls_section();

		$this->register_standard_style_controls(
			'title_style',
			__( 'Vehicle Title', 'mhm-rentiva' ),
			'.rv-vehicle-card__title a'
		);

		$this->register_parity_controls_from_block();
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_featured_vehicles', $atts );
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
