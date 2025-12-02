<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Card Elementor Widget
 *
 * Displays a single vehicle card inside Elementor.
 *
 * @since 3.0.1
 */
class VehicleCardWidget extends ElementorWidgetBase
{
    /**
     * Widget slug.
     */
    public function get_name(): string
    {
        return 'rv-vehicle-card';
    }

    /**
     * Widget title.
     */
    public function get_title(): string
    {
        return __('Vehicle Card', 'mhm-rentiva');
    }

    /**
     * Widget description.
     */
    public function get_description(): string
    {
        return __('Displays a single vehicle card - in list or standalone', 'mhm-rentiva');
    }

    /**
     * Widget icon.
     */
    public function get_icon(): string
    {
        return 'eicon-frame-expand';
    }

    /**
     * Widget keywords.
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'vehicle', 'card', 'car', 'rental', 'booking'
        ]);
    }

    /**
     * Retrieve the list of styles the widget depends on.
     *
     * @return array Widget styles dependencies.
     */
    public function get_style_depends(): array
    {
        return ['mhm-rentiva-elementor', 'mhm-rentiva-vehicles-list'];
    }
    /**
     * Register content controls.
     */
    protected function register_content_controls(): void
    {
        // Vehicle Selection
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_vehicle_selection_control();

        $this->add_layout_control();

        $this->end_controls_section();

        // Display Options
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Options', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_display_options_control();

        $this->end_controls_section();

        // Button & Interaction Options
        $this->start_controls_section(
            'button_section',
            [
                'label' => __('Buttons and Interaction', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_button_options_control();

        $this->end_controls_section();

        // Rating Options
        $this->start_controls_section(
            'rating_section',
            [
                'label' => __('Rating', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_rating_options_control();

        $this->end_controls_section();

        // Advanced Options
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Advanced Options', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Custom CSS Class', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'title' => __('Add custom CSS class', 'mhm-rentiva'),
                'description' => __('Attach a custom CSS class to this widget.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'enable_animation',
            [
                'label' => __('Enable Animation', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls.
     */
    protected function register_style_controls(): void
    {
        // Card Styles
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => __('Card Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background',
            [
                'label' => __('Background Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card' => 'background-color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->add_border_control('.rv-vehicle-card', __('Border', 'mhm-rentiva'));

        $this->add_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 12,
                    'right' => 12,
                    'bottom' => 12,
                    'left' => 12,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_box_shadow_control('.rv-vehicle-card', __('Shadow', 'mhm-rentiva'));

        $this->add_control(
            'card_padding',
            [
                'label' => __('Padding', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 16,
                    'right' => 16,
                    'bottom' => 16,
                    'left' => 16,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->end_controls_section();

        // Title Styles
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Title Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title' => 'color: {{VALUE}}',
                ],
                'default' => '#1e293b',
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => __('Hover Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title-link:hover' => 'color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_typography_control('.rv-vehicle-card__title', __('Typography', 'mhm-rentiva'));

        $this->add_control(
            'title_margin',
            [
                'label' => __('Margin', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Price Styles
        $this->start_controls_section(
            'price_style_section',
            [
                'label' => __('Price Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __('Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-price-amount' => 'color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'price_period_color',
            [
                'label' => __('Period Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-price-period' => 'color: {{VALUE}}',
                ],
                'default' => '#64748b',
            ]
        );

        $this->add_typography_control('.rv-price-amount', __('Typography', 'mhm-rentiva'));

        $this->end_controls_section();

        // Button Styles
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_button_color',
            [
                'label' => __('Primary Button Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn--primary' => 'background-color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'primary_button_hover_color',
            [
                'label' => __('Primary Button Hover Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn--primary:hover' => 'background-color: {{VALUE}}',
                ],
                'default' => '#1d4ed8',
            ]
        );

        $this->add_control(
            'secondary_button_color',
            [
                'label' => __('Secondary Button Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn--secondary' => 'color: {{VALUE}}; border-color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_typography_control('.rv-btn', __('Typography', 'mhm-rentiva'));

        $this->end_controls_section();

        // Badge Styles
        $this->start_controls_section(
            'badge_style_section',
            [
                'label' => __('Badge Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'badge_background',
            [
                'label' => __('Background Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__badge' => 'background-color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'badge_text_color',
            [
                'label' => __('Text Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__badge' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'badge_border_radius',
            [
                'label' => __('Border Radius', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 4,
                    'right' => 4,
                    'bottom' => 4,
                    'left' => 4,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_typography_control('.rv-vehicle-card__badge', __('Typography', 'mhm-rentiva'));

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
        
        // Append custom CSS class
        if (!empty($settings['custom_css_class'])) {
            $atts['class'] = $settings['custom_css_class'];
        }
        
        // Animation toggle
        if ($settings['enable_animation'] !== 'yes') {
            $atts['disable_animation'] = '1';
        }
        
        // Use Vehicles List shortcode with limit=1 and specific ID
        // This simulates a single vehicle card
        $atts['limit'] = '1';
        $atts['columns'] = '1';
        
        // If vehicle ID is set, use it
        if (!empty($settings['vehicle_id'])) {
            $atts['ids'] = $settings['vehicle_id'];
        }

        // Pass max_features and price_format
        if (!empty($settings['max_features'])) {
            $atts['max_features'] = $settings['max_features'];
        }
        if (!empty($settings['price_format'])) {
            $atts['price_format'] = $settings['price_format'];
        }
        
        // Render shortcode
        $shortcode_output = $this->render_shortcode('rentiva_vehicles_list', $atts);
        
        // Widget wrapper
        echo '<div class="elementor-widget-rv-vehicle-card">';
        echo $shortcode_output;
        echo '</div>';
    }

    /**
     * Register button option controls.
     */
    protected function add_button_options_control(): void
    {
        $this->add_control(
            'show_booking_btn',
            [
                'label' => __('Show Booking Button', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Book Now', 'mhm-rentiva'),
                'placeholder' => __('Enter button text', 'mhm-rentiva'),
                'condition' => [
                    'show_booking_btn' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'button_style',
            [
                'label' => __('Button Style', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'primary',
                'options' => [
                    'primary' => __('Primary', 'mhm-rentiva'),
                    'secondary' => __('Secondary', 'mhm-rentiva'),
                    'outline' => __('Outlined', 'mhm-rentiva'),
                ],
                'condition' => [
                    'show_booking_btn' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_favorite',
            [
                'label' => __('Show Favorite Button', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
    }

    /**
     * Register rating option controls.
     */
    protected function add_rating_options_control(): void
    {
        $this->add_control(
            'show_rating',
            [
                'label' => __('Show Star Rating', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'rating_position',
            [
                'label' => __('Rating Position', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'overlay',
                'options' => [
                    'overlay' => __('Image Overlay', 'mhm-rentiva'),
                    'below_image' => __('Below Image', 'mhm-rentiva'),
                    'footer' => __('Footer Area', 'mhm-rentiva'),
                ],
                'condition' => [
                    'show_rating' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_rating_count',
            [
                'label' => __('Show Rating Count', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
                'condition' => [
                    'show_rating' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'custom_rating',
            [
                'label' => __('Custom Rating', 'mhm-rentiva'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [''],
                'range' => [
                    '' => [
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => '',
                    'size' => 0,
                ],
                'description' => __('0 = Automatic, 0.1-5.0 = Custom value', 'mhm-rentiva'),
                'condition' => [
                    'show_rating' => 'yes',
                ],
            ]
        );
    }

    /**
     * Register advanced display option controls.
     */
    protected function add_display_options_control(array $options = []): void
    {
        $this->add_control(
            'show_image',
            [
                'label' => __('Show Image', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_category',
            [
                'label' => __('Show Category', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_features',
            [
                'label' => __('Show Features', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'max_features',
            [
                'label' => __('Maximum Feature Count', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default' => 3,
                'condition' => [
                    'show_features' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Show Price', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'price_format',
            [
                'label' => __('Price Format', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'daily',
                'options' => [
                    'daily' => __('Daily', 'mhm-rentiva'),
                    'hourly' => __('Hourly', 'mhm-rentiva'),
                    'weekly' => __('Weekly', 'mhm-rentiva'),
                    'monthly' => __('Monthly', 'mhm-rentiva'),
                ],
                'condition' => [
                    'show_price' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_badges',
            [
                'label' => __('Show Badges', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Show Description', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_availability',
            [
                'label' => __('Show Availability', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
    }

    /**
     * Render widget template JS (unused).
     */
    protected function content_template(): void
    {
        // JavaScript template (gerekirse)
    }
}
