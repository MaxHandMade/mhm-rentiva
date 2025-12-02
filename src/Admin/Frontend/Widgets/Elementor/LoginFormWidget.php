<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

class LoginFormWidget extends ElementorWidgetBase
{
    public function get_name(): string
    {
        return 'rv-login-form';
    }

    public function get_title(): string
    {
        return __('Login Form', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-lock-user';
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
            'redirect',
            [
                'label' => __('Redirect URL', 'mhm-rentiva'),
                'type' => Controls_Manager::URL,
                'placeholder' => home_url('/my-account'),
                'description' => __('Where to redirect after login', 'mhm-rentiva'),
            ]
        );

        $this->end_controls_section();
    }

    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];
        if (!empty($settings['redirect']['url'])) {
            $atts['redirect'] = $settings['redirect']['url'];
        }
        return $atts;
    }

    protected function register_style_controls(): void
    {
        // No style controls needed
    }

    protected function render(): void
    {
        $atts = $this->prepare_shortcode_attributes($this->get_settings_for_display());
        echo $this->render_shortcode('rentiva_login_form', $atts);
    }
}

