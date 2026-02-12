<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking Form Elementor Widget
 *
 * Displays the booking form as an Elementor widget.
 *
 * @since 3.0.1
 */
class BookingFormWidget extends ElementorWidgetBase {




	/**
	 * Return widget slug.
	 */
	public function get_name(): string {
		return 'rv-booking-form';
	}

	/**
	 * Return widget title.
	 */
	public function get_title(): string {
		return __( 'Booking Form', 'mhm-rentiva' );
	}

	/**
	 * Return widget description.
	 */
	public function get_description(): string {
		return __( 'Advanced booking form with vehicle selection, add-ons, and deposit flow.', 'mhm-rentiva' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon(): string {
		return 'eicon-date';
	}

	/**
	 * Return widget keywords.
	 */
	public function get_keywords(): array {
		return array_merge(
			$this->widget_keywords,
			array(
				'booking',
				'reservation',
				'form',
				'rental',
			)
		);
	}

	/**
	 * Retrieve the list of styles the widget depends on.
	 *
	 * @return array Widget styles dependencies.
	 */
	public function get_style_depends(): array {
		return array( 'mhm-rentiva-elementor', 'mhm-rentiva-booking-form' );
	}

	/**
	 * Register content tab controls.
	 */
	protected function register_content_controls(): void {
		// General Settings
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'General Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'form_title',
			array(
				'label'   => __( 'Form Title', 'mhm-rentiva' ),
				'type'    => 'text',
				'default' => __( 'Booking Form', 'mhm-rentiva' ),
			)
		);

		$this->add_vehicle_selection_control(
			'vehicle_id',
			__( 'Specific Vehicle', 'mhm-rentiva' ),
			__( 'Leave empty to allow users to choose a vehicle.', 'mhm-rentiva' )
		);

		$this->end_controls_section();

		// Form Options
		$this->start_controls_section(
			'form_options_section',
			array(
				'label' => __( 'Form Options', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'show_vehicle_selector',
			array(
				'label'        => __( 'Show Vehicle Selector', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_vehicle_info',
			array(
				'label'        => __( 'Show Vehicle Info', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_addons',
			array(
				'label'        => __( 'Show Add-ons', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_payment_options',
			array(
				'label'        => __( 'Show Payment Options', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		// Booking Settings
		$this->start_controls_section(
			'booking_settings_section',
			array(
				'label' => __( 'Booking Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'default_days',
			array(
				'label'   => __( 'Default Number of Days', 'mhm-rentiva' ),
				'type'    => 'number',
				'min'     => 1,
				'max'     => 365,
				'default' => 3,
			)
		);

		$this->add_control(
			'min_days',
			array(
				'label'   => __( 'Minimum Number of Days', 'mhm-rentiva' ),
				'type'    => 'number',
				'min'     => 1,
				'max'     => 365,
				'default' => 1,
			)
		);

		$this->add_control(
			'max_days',
			array(
				'label'   => __( 'Maximum Number of Days', 'mhm-rentiva' ),
				'type'    => 'number',
				'min'     => 1,
				'max'     => 365,
				'default' => 30,
			)
		);

		$this->end_controls_section();

		// Payment Settings
		$this->start_controls_section(
			'payment_section',
			array(
				'label' => __( 'Payment Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'enable_deposit',
			array(
				'label'        => __( 'Enable Deposit System', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'mhm-rentiva' ),
				'label_off'    => __( 'No', 'mhm-rentiva' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'default_payment',
			array(
				'label'   => __( 'Default Payment Type', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'deposit',
				'options' => array(
					'deposit' => __( 'Deposit', 'mhm-rentiva' ),
					'full'    => __( 'Full Payment', 'mhm-rentiva' ),
				),
			)
		);

		$this->end_controls_section();

		// Advanced Settings
		$this->start_controls_section(
			'advanced_section',
			array(
				'label' => __( 'Advanced Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'redirect_url',
			array(
				'label'       => __( 'Redirect After Success', 'mhm-rentiva' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'https://example.com/thank-you', 'mhm-rentiva' ),
			)
		);

		$this->add_control(
			'custom_css_class',
			array(
				'label'   => __( 'Custom CSS Class', 'mhm-rentiva' ),
				'type'    => 'text',
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register style tab controls.
	 */
	protected function register_style_controls(): void {
		// Form styles
		$this->start_controls_section(
			'form_style_section',
			array(
				'label' => __( 'Form Style', 'mhm-rentiva' ),
				'tab'   => 'style',
			)
		);

		$this->add_control(
			'form_background',
			array(
				'label'     => __( 'Background Color', 'mhm-rentiva' ),
				'type'      => 'color',
				'selectors' => array(
					'{{WRAPPER}} .rv-booking-form' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'form_border_radius',
			array(
				'label'      => __( 'Border Radius', 'mhm-rentiva' ),
				'type'       => 'dimensions',
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .rv-booking-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_box_shadow_control( '.rv-booking-form', __( 'Shadow', 'mhm-rentiva' ) );

		$this->end_controls_section();

		// Button styles
		$this->start_controls_section(
			'button_style_section',
			array(
				'label' => __( 'Button Style', 'mhm-rentiva' ),
				'tab'   => 'style',
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Button Color', 'mhm-rentiva' ),
				'type'      => 'color',
				'selectors' => array(
					'{{WRAPPER}} .rv-btn-submit' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => __( 'Button Hover Color', 'mhm-rentiva' ),
				'type'      => 'color',
				'selectors' => array(
					'{{WRAPPER}} .rv-btn-submit:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_typography_control( '.rv-btn-submit', __( 'Button Typography', 'mhm-rentiva' ) );

		$this->end_controls_section();
	}

	/**
	 * Prepare shortcode attributes.
	 *
	 * @param array $settings Elementor settings
	 * @return array Shortcode attributes
	 */
	protected function prepare_shortcode_attributes( array $settings ): array {
		$atts = array();

		// General
		if ( ! empty( $settings['form_title'] ) ) {
			$atts['title'] = $settings['form_title'];
		}
		if ( ! empty( $settings['vehicle_id'] ) ) {
			$atts['vehicle_id'] = $settings['vehicle_id'];
		}

		// Display options
		$display_options = array(
			'show_vehicle_selector',
			'show_vehicle_info',
			'show_addons',
			'show_payment_options',
		);

		foreach ( $display_options as $option ) {
			if ( isset( $settings[ $option ] ) ) {
				$val             = $settings[ $option ];
				$atts[ $option ] = ( $val === 'yes' || $val === '1' || $val === 1 || $val === true ) ? '1' : '0';
			}
		}

		// Booking settings
		if ( ! empty( $settings['default_days'] ) ) {
			$atts['default_days'] = $settings['default_days'];
		}
		if ( ! empty( $settings['min_days'] ) ) {
			$atts['min_days'] = $settings['min_days'];
		}
		if ( ! empty( $settings['max_days'] ) ) {
			$atts['max_days'] = $settings['max_days'];
		}

		// Payment settings
		if ( isset( $settings['enable_deposit'] ) ) {
			$val                    = $settings['enable_deposit'];
			$atts['enable_deposit'] = ( $val === 'yes' || $val === '1' || $val === 1 || $val === true ) ? '1' : '0';
		}
		if ( ! empty( $settings['default_payment'] ) ) {
			$atts['default_payment'] = $settings['default_payment'];
		}

		// Advanced
		if ( ! empty( $settings['redirect_url'] ) ) {
			$atts['redirect_url'] = $settings['redirect_url'];
		}
		if ( ! empty( $settings['custom_css_class'] ) ) {
			$atts['class'] = $settings['custom_css_class'];
		}

		return $atts;
	}

	/**
	 * Render widget output.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		// Prepare shortcode attributes
		$atts = $this->prepare_shortcode_attributes( $settings );

		// Render shortcode output
		$shortcode_output = $this->render_shortcode( 'rentiva_booking_form', $atts );

		// Output widget wrapper
		echo '<div class="elementor-widget-rv-booking-form">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $shortcode_output;
		echo '</div>';
	}

	/**
	 * Return widget JavaScript code.
	 */
	protected function content_template(): void {
		// JavaScript template (if needed)
	}
}
