<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Form Elementor Widget
 * 
 * Displays the booking form as an Elementor widget.
 * 
 * @since 3.0.1
 */
class BookingFormWidget extends ElementorWidgetBase
{
    /**
     * Return widget slug.
     */
    public function get_name(): string
    {
        return 'rv-booking-form';
    }

    /**
     * Return widget title.
     */
    public function get_title(): string
    {
        return __('Booking Form', 'mhm-rentiva');
    }

    /**
     * Return widget description.
     */
    public function get_description(): string
    {
        return __('Advanced booking form with vehicle selection, add-ons, and deposit flow.', 'mhm-rentiva');
    }

    /**
     * Return widget keywords.
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'booking', 'reservation', 'form', 'rental'
        ]);
    }

    /**
     * Register content tab controls.
     */
    protected function register_content_controls(): void
    {
        // General Settings
        $this->start_controls_section(
            'general_section',
            [
                'label' => __('General Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_title',
            [
                'label' => __('Form Title', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Booking Form', 'mhm-rentiva'),
            ]
        );

        $this->add_vehicle_selection_control(
            'vehicle_id',
            __('Specific Vehicle', 'mhm-rentiva'),
            __('Leave empty to allow users to choose a vehicle.', 'mhm-rentiva')
        );

        $this->end_controls_section();

        // Form Options
        $this->start_controls_section(
            'form_options_section',
            [
                'label' => __('Form Options', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_vehicle_selector',
            [
                'label' => __('Show Vehicle Selector', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_vehicle_info',
            [
                'label' => __('Show Vehicle Info', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_addons',
            [
                'label' => __('Show Add-ons', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_payment_options',
            [
                'label' => __('Show Payment Options', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->end_controls_section();

        // Booking Settings
        $this->start_controls_section(
            'booking_settings_section',
            [
                'label' => __('Booking Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'default_days',
            [
                'label' => __('Default Number of Days', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 365,
                'default' => 3,
            ]
        );

        $this->add_control(
            'min_days',
            [
                'label' => __('Minimum Number of Days', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 365,
                'default' => 1,
            ]
        );

        $this->add_control(
            'max_days',
            [
                'label' => __('Maximum Number of Days', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 365,
                'default' => 30,
            ]
        );

        $this->end_controls_section();

        // Payment Settings
        $this->start_controls_section(
            'payment_section',
            [
                'label' => __('Payment Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'enable_deposit',
            [
                'label' => __('Enable Deposit System', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'default_payment',
            [
                'label' => __('Default Payment Type', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'deposit',
                'options' => [
                    'deposit' => __('Deposit', 'mhm-rentiva'),
                    'full' => __('Full Payment', 'mhm-rentiva'),
                ],
            ]
        );

        $this->end_controls_section();

        // Advanced Settings
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Advanced Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'redirect_url',
            [
                'label' => __('Redirect After Success', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('https://example.com/thank-you', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Custom CSS Class', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style tab controls.
     */
    protected function register_style_controls(): void
    {
        // Form styles
        $this->start_controls_section(
            'form_style_section',
            [
                'label' => __('Form Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'form_background',
            [
                'label' => __('Background Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-booking-form' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'form_border_radius',
            [
                'label' => __('Border Radius', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-booking-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_box_shadow_control('.rv-booking-form', __('Gölge', 'mhm-rentiva'));

        $this->end_controls_section();

        // Button styles
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Button Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-submit' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Button Hover Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-submit:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-btn-submit', __('Button Typography', 'mhm-rentiva'));

        $this->end_controls_section();
    }

    /**
     * Render widget output.
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        
        // Prepare shortcode attributes
        $atts = $this->prepare_shortcode_attributes($settings);
        
        // Render shortcode output
        $shortcode_output = $this->render_shortcode('rentiva_booking_form', $atts);
        
        // Output widget wrapper
        echo '<div class="elementor-widget-rv-booking-form">';
        echo $shortcode_output;
        echo '</div>';
    }

    /**
     * Return widget JavaScript code.
     */
    protected function content_template(): void
    {
        // JavaScript template (if needed)
    }
}

