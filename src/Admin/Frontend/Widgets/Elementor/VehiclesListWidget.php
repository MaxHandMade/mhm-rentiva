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
 * Vehicles List Elementor Widget
 *
 * Displays vehicles as a grid or list within Elementor.
 *
 * @since 3.0.1
 */
class VehiclesListWidget extends ElementorWidgetBase
{
    /**
     * Return widget slug.
     */
    public function get_name(): string
    {
        return 'rv-vehicles-list';
    }

    /**
     * Return widget title.
     */
    public function get_title(): string
    {
        return __('Vehicles List', 'mhm-rentiva');
    }

    /**
     * Return widget description.
     */
    public function get_description(): string
    {
        return __('Display vehicles in grid or list layouts with booking actions.', 'mhm-rentiva');
    }

    /**
     * Return widget keywords.
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'vehicle', 'list', 'cars', 'rental', 'booking', 'grid'
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
     * Register content tab controls.
     */
    protected function register_content_controls(): void
    {
        // Query Section
        $this->start_controls_section(
            'query_section',
            [
                'label' => __('Query Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_query_controls();

        $this->end_controls_section();

        // Layout Section
        $this->start_controls_section(
            'layout_section',
            [
                'label' => __('Layout Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_layout_controls();

        $this->end_controls_section();

        // Display Options
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Options', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_display_options();

        $this->end_controls_section();

        // Button & Interaction Options
        $this->start_controls_section(
            'interaction_section',
            [
                'label' => __('Buttons & Interaction', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_interaction_options();

        $this->end_controls_section();

        // Advanced Options
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Advanced Options', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_advanced_options();

        $this->end_controls_section();
    }

    /**
     * Register style tab controls.
     */
    protected function register_style_controls(): void
    {
        // Card styles
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => __('Card Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_card_styles();

        $this->end_controls_section();

        // Grid styles
        $this->start_controls_section(
            'grid_style_section',
            [
                'label' => __('Grid Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_grid_styles();

        $this->end_controls_section();

        // Typography styles
        $this->start_controls_section(
            'typography_style_section',
            [
                'label' => __('Typography', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_typography_styles();

        $this->end_controls_section();

        // Button styles
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Button Style', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_button_styles();

        $this->end_controls_section();
    }

    /**
     * Register query controls.
     */
    protected function add_query_controls(): void
    {
        $this->add_control(
            'limit',
            [
                'label' => __('Number of Vehicles', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 100,
                'step' => 1,
                'default' => 9,
                'description' => __('How many vehicles to display.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Order', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => __('Ascending', 'mhm-rentiva'),
                    'DESC' => __('Descending', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __('Order By', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => __('Date', 'mhm-rentiva'),
                    'title' => __('Title', 'mhm-rentiva'),
                    'price' => __('Price', 'mhm-rentiva'),
                    'rating' => __('Rating', 'mhm-rentiva'),
                    'rand' => __('Random', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'exclude',
            [
                'label' => __('Exclude Vehicle IDs', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('e.g. 1,2,3', 'mhm-rentiva'),
                'description' => __('Comma separated vehicle IDs to hide.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'ids',
            [
                'label' => __('Include Vehicle IDs', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('e.g. 1,2,3', 'mhm-rentiva'),
                'description' => __('Comma separated vehicle IDs to show.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'category',
            [
                'label' => __('Category', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_vehicle_categories(),
                'multiple' => true,
                'label_block' => true,
                'placeholder' => __('All Categories', 'mhm-rentiva'),
                'description' => __('Select categories to display.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'featured',
            [
                'label' => __('Featured Only', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
    }

    /**
     * Add layout controls.
     */
    protected function add_layout_controls(): void
    {
        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __('Grid', 'mhm-rentiva'),
                    'list' => __('List', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Number of Columns', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
            ]
        );

        $this->add_control(
            'gap',
            [
                'label' => __('Gap', 'mhm-rentiva'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'rem',
                    'size' => 1.5,
                ],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicles-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
    }

    /**
     * Add display options.
     */
    protected function add_display_options(): void
    {
        $this->add_control(
            'show_image',
            [
                'label' => __('Show image', 'mhm-rentiva'),
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
                'label' => __('Show title', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Show price', 'mhm-rentiva'),
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
                'label' => __('Show features', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_rating',
            [
                'label' => __('Show rating', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
    }

    /**
     * Add interaction options.
     */
    protected function add_interaction_options(): void
    {
        $this->add_control(
            'show_booking_btn',
            [
                'label' => __('Show booking button', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_favorite_btn',
            [
                'label' => __('Show favorite button', 'mhm-rentiva'),
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
                'label' => __('Show category', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_badges',
            [
                'label' => __('Show badges', 'mhm-rentiva'),
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
                'label' => __('Show description', 'mhm-rentiva'),
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
                'label' => __('Show availability', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
    }

    /**
     * Register advanced options.
     */
    protected function add_advanced_options(): void
    {
        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Custom CSS Class', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'description' => __('Add a custom CSS class for this widget.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'enable_lazy_load',
            [
                'label' => __('Enable lazy loading', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'enable_ajax_filtering',
            [
                'label' => __('Enable AJAX filtering', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'enable_infinite_scroll',
            [
                'label' => __('Enable infinite scroll', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_compare_btn',
            [
                'label' => __('Show compare button', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'mhm-rentiva'),
                'label_off' => __('No', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
    }

    /**
     * Add card styles.
     */
    protected function add_card_styles(): void
    {
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

        $this->add_control(
            'card_border_radius',
            [
                'label' => __('Border Radius', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_box_shadow_control('.rv-vehicle-card', __('Shadow', 'mhm-rentiva'));
    }

    /**
     * Add grid styles.
     */
    protected function add_grid_styles(): void
    {
        $this->add_control(
            'grid_gap',
            [
                'label' => __('Grid Gap', 'mhm-rentiva'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicles-list--grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
    }

    /**
     * Add typography styles.
     */
    protected function add_typography_styles(): void
    {
        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-vehicle-card__title', __('Title Typography', 'mhm-rentiva'));

        $this->add_control(
            'price_color',
            [
                'label' => __('Price Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-price-amount' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-price-amount', __('Price Typography', 'mhm-rentiva'));
    }

    /**
     * Add button styles.
     */
    protected function add_button_styles(): void
    {
        $this->add_control(
            'button_color',
            [
                'label' => __('Button Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-booking' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Button Hover Color', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-booking:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-btn-booking', __('Button Typography', 'mhm-rentiva'));
    }

    /**
     * Render widget output.
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        
        // Prepare shortcode attributes
        $atts = $this->prepare_shortcode_attributes($settings);
        
        
        // Render shortcode
        $shortcode_output = $this->render_shortcode('rentiva_vehicles_list', $atts);
        
        // Output wrapper
        echo '<div class="elementor-widget-rv-vehicles-list">';
        echo $shortcode_output;
        echo '</div>';
    }


    /**
     * Prepare shortcode attributes (customized for VehiclesListWidget).
     *
     * @param array $settings Elementor settings
     * @return array Shortcode attributes
     */
    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];

        // Query parameters
        if (isset($settings['limit']) && $settings['limit'] !== '') {
            $atts['limit'] = $settings['limit'];
        }
        if (isset($settings['order']) && $settings['order'] !== '') {
            $atts['order'] = $settings['order'];
        }
        if (isset($settings['orderby']) && $settings['orderby'] !== '') {
            $atts['orderby'] = $settings['orderby'];
        }
        if (isset($settings['exclude']) && $settings['exclude'] !== '') {
            // Convert arrays to CSV string if needed
            if (is_array($settings['exclude'])) {
                $atts['exclude'] = implode(',', $settings['exclude']);
            } else {
                $atts['exclude'] = $settings['exclude'];
            }
        }
        if (isset($settings['category']) && $settings['category'] !== '') {
            // Convert category array to CSV string if needed
            if (is_array($settings['category'])) {
                $atts['category'] = implode(',', $settings['category']);
            } else {
                $atts['category'] = $settings['category'];
            }
        }
        if (isset($settings['featured'])) {
            $atts['featured'] = $settings['featured'] === 'yes' ? '1' : '0';
        }

        if (isset($settings['ids']) && $settings['ids'] !== '') {
            $atts['ids'] = $settings['ids'];
        }

        // Layout
        if (isset($settings['layout']) && $settings['layout'] !== '') {
            // Ensure layout stored as string
            $atts['layout'] = (string) $settings['layout'];
        }
        if (isset($settings['columns']) && $settings['columns'] !== '') {
            $atts['columns'] = $settings['columns'];
        }
        if (isset($settings['gap']) && $settings['gap'] !== '') {
            $atts['gap'] = $settings['gap'];
        }

        // Display options
        $display_options = [
            'show_image', 'show_title', 'show_price', 'show_features',
            'show_rating', 'show_booking_btn', 'show_favorite_btn', 'show_category'
        ];

        foreach ($display_options as $option) {
            if (isset($settings[$option])) {
                $value = $settings[$option];
                $atts[$option] = ($value === 'yes') ? '1' : '0';
            }
        }

        // Additional options
        if (isset($settings['show_badges'])) {
            $value = $settings['show_badges'];
            $atts['show_badges'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['show_description'])) {
            $value = $settings['show_description'];
            $atts['show_description'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['show_availability'])) {
            $value = $settings['show_availability'];
            $atts['show_availability'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['show_compare_btn'])) {
            $value = $settings['show_compare_btn'];
            $atts['show_compare_btn'] = ($value === 'yes') ? '1' : '0';
        }

        // Interaction options
        if (isset($settings['enable_ajax_filtering'])) {
            $value = $settings['enable_ajax_filtering'];
            $atts['enable_ajax_filtering'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['enable_lazy_load'])) {
            $value = $settings['enable_lazy_load'];
            $atts['enable_lazy_load'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['enable_infinite_scroll'])) {
            $value = $settings['enable_infinite_scroll'];
            $atts['enable_infinite_scroll'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['custom_css_class']) && $settings['custom_css_class'] !== '') {
            $atts['custom_css_class'] = $settings['custom_css_class'];
        }

        return $atts;
    }

    /**
     * Return widget JavaScript template.
     */
    protected function content_template(): void
    {
        // JavaScript template (gerekirse)
    }
    /**
     * Get vehicle categories.
     */
    protected function get_vehicle_categories(): array
    {
        $terms = get_terms([
            'taxonomy' => 'vehicle_category',
            'hide_empty' => false,
        ]);

        $options = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }
        }

        return $options;
    }
}

