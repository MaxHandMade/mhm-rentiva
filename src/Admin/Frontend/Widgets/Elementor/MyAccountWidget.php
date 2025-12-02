<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Account Elementor Widget
 *
 * @since 3.0.1
 */
class MyAccountWidget extends ElementorWidgetBase
{
    public function get_name(): string
    {
        return 'rv-my-account';
    }

    public function get_title(): string
    {
        return __('My Account Dashboard', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-user-circle-o';
    }

    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'account', 'dashboard', 'profile', 'user'
        ]);
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
            'info',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => __('Displays user account dashboard with all sections.', 'mhm-rentiva'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $this->end_controls_section();
    }

    protected function register_style_controls(): void
    {
        // No style controls needed
    }

    protected function render(): void
    {
        echo do_shortcode('[rentiva_my_account]');
    }
}

