<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Search Elementor Widget
 *
 * Displays the vehicle search form within Elementor.
 *
 * @since 3.0.1
 */
class VehicleSearchWidget extends ElementorWidgetBase
{
    /**
     * Widget slug.
     */
    public function get_name(): string
    {
        return 'rv-vehicle-search';
    }

    /**
     * Widget title.
     */
    public function get_title(): string
    {
        return __('Vehicle Search', 'mhm-rentiva');
    }

    /**
     * Widget description.
     */
    public function get_description(): string
    {
        return __('Advanced vehicle search form with filters', 'mhm-rentiva');
    }

    /**
     * Widget icon.
     */
    public function get_icon(): string
    {
        return 'eicon-search';
    }

    /**
     * Widget keywords.
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'search', 'find', 'filter', 'form'
        ]);
    }

    /**
     * Register content tab controls.
     */
    protected function register_content_controls(): void
    {
        $this->start_controls_section(
            'general_section',
            [
                'label' => __('General Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'compact',
                'options' => [
                    'default' => __('Default', 'mhm-rentiva'),
                    'compact' => __('Compact', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'mhm-rentiva'),
                'label_off' => __('Hide', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style tab controls.
     */
    protected function register_style_controls(): void
    {
        // Style controls can be added here if needed
    }

    /**
     * Prepare shortcode attributes from widget settings.
     */
    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];

        if (!empty($settings['layout'])) {
            $atts['layout'] = $settings['layout'];
        }

        if (isset($settings['show_title'])) {
            $atts['show_title'] = ($settings['show_title'] === 'yes') ? '1' : '0';
        }

        return $atts;
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
        $shortcode_output = $this->render_shortcode('rentiva_search', $atts);
        
        // Output widget wrapper
        echo '<div class="elementor-widget-rv-vehicle-search">';
        echo $shortcode_output;
        echo '</div>';
    }
}

