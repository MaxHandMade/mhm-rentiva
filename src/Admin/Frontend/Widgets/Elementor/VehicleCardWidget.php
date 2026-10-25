<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicle Card Elementor Widget
 *
 * Displays a single vehicle card inside Elementor.
 *
 * @since 3.0.1
 */
class VehicleCardWidget extends ElementorWidgetBase {



	/**
	 * Widget slug.
	 */
	public function get_name(): string {
		return 'rv-vehicle-card';
	}

	/**
	 * Widget title.
	 */
	public function get_title(): string {
		return __( 'Vehicle Card', 'mhm-rentiva' );
	}

	/**
	 * Widget description.
	 */
	public function get_description(): string {
		return __( 'Displays a single vehicle card - in list or standalone', 'mhm-rentiva' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon(): string {
		return 'eicon-frame-expand';
	}

	/**
	 * Widget keywords.
	 */
	public function get_keywords(): array {
		return array_merge(
			$this->widget_keywords,
			array(
				'mhmrentiva_vehicle',
				'card',
				'car',
				'rental',
				'booking',
			)
		);
	}

	/**
	 * Retrieve the list of styles the widget depends on.
	 *
	 * @return array Widget styles dependencies.
	 */
	public function get_style_depends(): array {
		return array( 'mhm-rentiva-elementor', 'mhm-rentiva-rvs-card' );
	}
	/**
	 * Register content controls.
	 */
	protected function register_content_controls(): void {
		// Vehicle Selection
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Content', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_vehicle_selection_control();

		$this->add_layout_control();

		$this->end_controls_section();

		// Display Options
		$this->start_controls_section(
			'display_section',
			array(
				'label' => __( 'Display Options', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_display_options_control();

		$this->end_controls_section();

		// Button & Interaction Options
		$this->start_controls_section(
			'button_section',
			array(
				'label' => __( 'Buttons and Interaction', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_button_options_control();

		$this->end_controls_section();

		// Rating Options
		$this->start_controls_section(
			'rating_section',
			array(
				'label' => __( 'Rating', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_rating_options_control();

		$this->end_controls_section();

		// Advanced Options
		$this->start_controls_section(
			'advanced_section',
			array(
				'label' => __( 'Advanced Options', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'custom_css_class',
			array(
				'label'       => __( 'Custom CSS Class', 'mhm-rentiva' ),
				'type'        => 'text',
				'default'     => '',
				'title'       => __( 'Add custom CSS class', 'mhm-rentiva' ),
				'description' => __( 'Attach a custom CSS class to this widget.', 'mhm-rentiva' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register style controls.
	 */
	protected function register_style_controls(): void {
		// The card is the front-end standard's (templates/partials/rvs-vehicle-card.php).
		// Plain controls write --rvs-card-* tokens on the widget's .rvs-shell, which
		// rvs-card.css reads; group controls target the rvs-card classes. Control ids
		// are unchanged -- they are what saved pages store values under. No control
		// carries a default: an unstyled card keeps the standard's look.

		// Card Styles
		$this->start_controls_section(
			'card_style_section',
			array(
				'label' => __( 'Card Style', 'mhm-rentiva' ),
				'tab'   => 'style',
			)
		);

		$this->add_rvs_token_control( 'card_background', __( 'Background Color', 'mhm-rentiva' ), 'card-bg' );

		$this->add_border_control( 'card_border', '.rvs-card' );

		$this->add_rvs_token_dimensions_control( 'border_radius', __( 'Border Radius', 'mhm-rentiva' ), 'card-radius', array( 'size_units' => array( 'px', '%' ) ) );

		$this->add_box_shadow_control( 'card_shadow', '.rvs-card' );

		$this->add_rvs_token_dimensions_control( 'card_padding', __( 'Padding', 'mhm-rentiva' ), 'card-pad' );

		$this->end_controls_section();

		// Title Styles
		$this->start_controls_section(
			'title_style_section',
			array(
				'label' => __( 'Title Style', 'mhm-rentiva' ),
				'tab'   => 'style',
			)
		);

		$this->add_rvs_token_control( 'title_color', __( 'Color', 'mhm-rentiva' ), 'card-title' );

		$this->add_rvs_token_control( 'title_hover_color', __( 'Hover Color', 'mhm-rentiva' ), 'card-title-hover' );

		$this->add_typography_control( 'title_typography', '.rvs-card__title' );

		$this->add_rvs_token_dimensions_control( 'title_margin', __( 'Margin', 'mhm-rentiva' ), 'card-title-margin' );

		$this->end_controls_section();

		// Price Styles
		$this->start_controls_section(
			'price_style_section',
			array(
				'label' => __( 'Price Style', 'mhm-rentiva' ),
				'tab'   => 'style',
			)
		);

		$this->add_rvs_token_control( 'price_color', __( 'Color', 'mhm-rentiva' ), 'card-price' );

		$this->add_rvs_token_control( 'price_period_color', __( 'Period Color', 'mhm-rentiva' ), 'card-price-period' );

		$this->add_typography_control( 'price_amount_typography', '.rvs-card__amount' );

		$this->end_controls_section();

		// Button Styles (the card renders one button: Select / Book)
		$this->start_controls_section(
			'button_style_section',
			array(
				'label' => __( 'Button Style', 'mhm-rentiva' ),
				'tab'   => 'style',
			)
		);

		$this->add_rvs_token_control( 'primary_button_color', __( 'Primary Button Color', 'mhm-rentiva' ), 'card-btn-bg' );

		$this->add_rvs_token_control( 'primary_button_hover_color', __( 'Primary Button Hover Color', 'mhm-rentiva' ), 'card-btn-bg-hover' );

		$this->add_rvs_token_dimensions_control( 'button_border_radius', __( 'Border Radius', 'mhm-rentiva' ), 'card-btn-radius', array( 'size_units' => array( 'px', '%' ) ) );

		$this->add_typography_control( 'booking_button_typography', '.rvs-card__select' );

		$this->end_controls_section();
	}

	/**
	 * Render widget output.
	 */
	protected function render(): void {
		// Widget wrapper
		echo '<div class="elementor-widget-rv-vehicle-card">';
		$this->render_canonical( 'rentiva_vehicles_list' );
		echo '</div>';
	}

	/**
	 * The card always renders one vehicle in one column (prepare_shortcode_attributes()
	 * forces both), so parity controls for them would be dead.
	 *
	 * @return string[]
	 */
	protected function get_parity_exclusions(): array {
		return array( 'limit', 'columns' );
	}

	/**
	 * Map the card's controls onto the Vehicles List shortcode.
	 *
	 * The card has no shortcode of its own: it renders Vehicles List pinned to a
	 * single vehicle, so limit and columns are fixed rather than derived from input.
	 *
	 * @param array $settings Filtered bridge settings.
	 * @return array
	 */
	protected function prepare_shortcode_attributes( array $settings ): array {
		$atts = array(
			'limit'   => '1',
			'columns' => '1',
		);

		// Append custom CSS class
		if ( ! empty( $settings['custom_css_class'] ) ) {
			$atts['class'] = $settings['custom_css_class'];
		}

		// If vehicle ID is set, use it
		if ( ! empty( $settings['vehicle_id'] ) ) {
			$atts['ids'] = $settings['vehicle_id'];
		}

		if ( ! empty( $settings['max_features'] ) ) {
			$atts['max_features'] = $settings['max_features'];
		}

		return $atts;
	}

	/**
	 * Register button option controls.
	 */
	protected function add_button_options_control(): void {
		$this->add_control(
			'show_booking_btn',
			array(
				'label'        => __( 'Show Booking Button', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button Text', 'mhm-rentiva' ),
				'type'        => 'text',
				// Empty keeps the card's own label (Book Now / Unavailable).
				'default'     => '',
				'placeholder' => __( 'Book Now', 'mhm-rentiva' ),
				'condition'   => array(
					'show_booking_btn' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_favorite',
			array(
				'label'        => __( 'Show Favorite Button', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);
	}

	/**
	 * Register rating option controls.
	 */
	protected function add_rating_options_control(): void {
		$this->add_control(
			'show_rating',
			array(
				'label'        => __( 'Show Star Rating', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_rating_count',
			array(
				'label'        => __( 'Show Rating Count', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => '1',
				// Off: the card has always shown the average alone (spec 3A §2.2).
				'default'      => '0',
				'condition'    => array(
					'show_rating' => 'yes',
				),
			)
		);
	}

	/**
	 * Register advanced display option controls.
	 */
	protected function add_display_options_control( array $options = array() ): void {
		$this->add_control(
			'show_image',
			array(
				'label'        => __( 'Show Image', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Show Title', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_category',
			array(
				'label'        => __( 'Show Category', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_features',
			array(
				'label'        => __( 'Show Features', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'max_features',
			array(
				'label'     => __( 'Maximum Feature Count', 'mhm-rentiva' ),
				'type'      => 'number',
				'min'       => 1,
				'max'       => 10,
				'step'      => 1,
				'default'   => 3,
				'condition' => array(
					'show_features' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'Show Price', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_description',
			array(
				'label'        => __( 'Show Description', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);
	}

	/**
	 * Render widget template JS (unused).
	 */
	protected function content_template(): void {
		// JavaScript template (gerekirse)
	}
}
