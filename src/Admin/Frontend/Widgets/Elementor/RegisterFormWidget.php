<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Form Elementor Widget
 *
 * @since 3.0.1
 */
class RegisterFormWidget extends ElementorWidgetBase
{
    /**
     * Widget slug.
     */
    public function get_name(): string
    {
        return 'rv-register-form';
    }

    /**
     * Widget title.
     */
    public function get_title(): string
    {
        return __('Register Form', 'mhm-rentiva');
    }

    /**
     * Widget description.
     */
    public function get_description(): string
    {
        return __('User registration form', 'mhm-rentiva');
    }

    /**
     * Widget icon.
     */
    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    /**
     * Widget keywords.
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'register', 'signup', 'form', 'user'
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
            'redirect_url',
            [
                'label' => __('Redirect URL', 'mhm-rentiva'),
                'type' => Controls_Manager::URL,
                'placeholder' => home_url('/my-account'),
                'description' => __('Where to redirect after successful registration', 'mhm-rentiva'),
                'show_external' => false,
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style tab controls.
     */
    protected function register_style_controls(): void
    {
        // No custom style controls needed
    }

    /**
     * Prepare shortcode attributes from widget settings.
     */
    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];
        
        if (!empty($settings['redirect_url']['url'])) {
            $atts['redirect'] = $settings['redirect_url']['url'];
        }
        
        return $atts;
    }

    /**
     * Render widget output.
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $atts = $this->prepare_shortcode_attributes($settings);
        
        echo '<div class="elementor-widget-rv-register-form">';
        echo $this->render_shortcode('rentiva_register_form', $atts);
        echo '</div>';
    }
}

