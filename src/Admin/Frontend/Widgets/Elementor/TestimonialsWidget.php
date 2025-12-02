<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class TestimonialsWidget extends ElementorWidgetBase
{
    public function get_name(): string
    {
        return 'rv-testimonials';
    }

    public function get_title(): string
    {
        return __('Testimonials', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-testimonial';
    }

    protected function register_content_controls(): void
    {
        $this->start_controls_section(
            'general_section',
            [
                'label' => __('Settings', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Number of Testimonials', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->end_controls_section();
    }

    protected function register_style_controls(): void
    {
        // No style controls needed
    }

    protected function prepare_shortcode_attributes(array $settings): array
    {
        return [
            'limit' => $settings['limit'] ?? 6,
        ];
    }

    protected function render(): void
    {
        $atts = $this->prepare_shortcode_attributes($this->get_settings_for_display());
        echo $this->render_shortcode('rentiva_testimonials', $atts);
    }
}

