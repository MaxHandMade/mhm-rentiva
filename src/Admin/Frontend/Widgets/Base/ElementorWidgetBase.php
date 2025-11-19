<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Base;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Elementor Widget Class
 * 
 * Base class shared by all MHM Rentiva Elementor widgets.
 * 
 * @since 3.0.1
 */
abstract class ElementorWidgetBase extends Widget_Base
{
    /**
     * Widget category slug.
     */
    protected string $widget_category = 'mhm-rentiva';
    
    /**
     * Widget icon slug.
     */
    protected string $widget_icon = 'eicon-car';
    
    /**
     * Widget keywords.
     */
    protected array $widget_keywords = ['mhm', 'rentiva', 'vehicle', 'rental'];

    /**
     * Return widget categories.
     */
    public function get_categories(): array
    {
        return [$this->widget_category];
    }

    /**
     * Return widget icon.
     */
    public function get_icon(): string
    {
        return $this->widget_icon;
    }

    /**
     * Return widget keywords.
     */
    public function get_keywords(): array
    {
        return $this->widget_keywords;
    }

    /**
     * Return script dependencies for the widget.
     */
    public function get_script_depends(): array
    {
        return ['mhm-rentiva-elementor'];
    }

    /**
     * Return style dependencies for the widget.
     */
    public function get_style_depends(): array
    {
        return ['mhm-rentiva-elementor'];
    }

    /**
     * Register content tab controls.
     */
    abstract protected function register_content_controls(): void;

    /**
     * Register style tab controls.
     */
    abstract protected function register_style_controls(): void;

    /**
     * Register widget controls.
     */
    protected function register_controls(): void
    {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Add vehicle selection control.
     * 
     * @param string $control_id Control ID
     * @param string $label Control label
     * @param string $description Control description
     */
    protected function add_vehicle_selection_control(
        string $control_id = 'vehicle_id',
        string $label = 'Select Vehicle',
        string $description = 'Choose which vehicle to display'
    ): void {
        $this->add_control(
            $control_id,
            [
                'label' => $label,
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => false,
                'options' => $this->get_vehicle_options(),
                'description' => $description,
                'default' => $this->get_default_vehicle_id(),
            ]
        );
    }

    /**
     * Add layout selection control.
     * 
     * @param string $control_id Control ID
     * @param string $label Control label
     */
    protected function add_layout_control(
        string $control_id = 'layout',
        string $label = 'Layout'
    ): void {
        $this->add_control(
            $control_id,
            [
                'label' => $label,
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => 'Default',
                    'compact' => 'Compact',
                    'grid' => 'Grid',
                    'featured' => 'Featured',
                ],
            ]
        );
    }

    /**
     * Add display options controls.
     * 
     * @param array $options Options to display
     */
    protected function add_display_options_control(array $options = []): void
    {
        $default_options = [
            'show_image' => 'Show image',
            'show_title' => 'Show title',
            'show_price' => 'Show price',
            'show_features' => 'Show features',
            'show_rating' => 'Show rating',
            'show_booking_btn' => 'Show booking button',
            'show_favorite_btn' => 'Show favorite button',
        ];

        $options = array_merge($default_options, $options);

        $this->add_control(
            'display_options',
            [
                'label' => 'Display Options',
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        foreach ($options as $key => $label) {
            $this->add_control(
                $key,
                [
                    'label' => $label,
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Yes',
                    'label_off' => 'No',
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );
        }
    }

    /**
     * Add typography control.
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_typography_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => $selector . '_typography',
                'label' => $label,
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Add border control.
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_border_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => $selector . '_border',
                'label' => $label,
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Add box shadow control.
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_box_shadow_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => $selector . '_shadow',
                'label' => $label,
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Add background control.
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_background_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => $selector . '_background',
                'label' => $label,
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Retrieve vehicle options.
     * 
     * @return array Vehicle options
     */
    protected function get_vehicle_options(): array
    {
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = [];
        foreach ($vehicles as $vehicle) {
            $options[$vehicle->ID] = $vehicle->post_title;
        }

        return $options;
    }

    /**
     * Get default vehicle ID.
     * 
     * @return int Default vehicle ID
     */
    protected function get_default_vehicle_id(): int
    {
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return $vehicles ? $vehicles[0]->ID : 0;
    }

    /**
     * Prepare shortcode attributes.
     * 
     * @param array $settings Elementor settings
     * @return array Shortcode attributes
     */
    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];

        // Vehicle ID
        if (!empty($settings['vehicle_id'])) {
            $atts['id'] = $settings['vehicle_id'];
        }

        // Layout
        if (!empty($settings['layout'])) {
            $atts['layout'] = $settings['layout'];
        }

        // Display options
        $display_options = [
            'show_image', 'show_title', 'show_price', 'show_features',
            'show_rating', 'show_booking_btn', 'show_favorite_btn'
        ];

        foreach ($display_options as $option) {
            if (isset($settings[$option])) {
                $atts[$option] = $settings[$option] === 'yes' ? '1' : '0';
            }
        }

        return $atts;
    }

    /**
     * Render shortcode.
     * 
     * @param string $shortcode_tag Shortcode tag
     * @param array $atts Shortcode attributes
     * @return string Rendered shortcode
     */
    protected function render_shortcode(string $shortcode_tag, array $atts): string
    {
        $shortcode = '[' . $shortcode_tag;
        
        foreach ($atts as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        
        $shortcode .= ']';
        
        return do_shortcode($shortcode);
    }
}
