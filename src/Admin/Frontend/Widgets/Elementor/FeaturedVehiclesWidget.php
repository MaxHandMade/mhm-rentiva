<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured Vehicles Elementor Widget
 */
class FeaturedVehiclesWidget extends ElementorWidgetBase
{

    public function get_name(): string
    {
        return 'mhm_rentiva_featured_vehicles';
    }

    public function get_title(): string
    {
        return __('MHM Featured Vehicles', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-star';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content Settings', 'mhm-rentiva'),
        ]);

        $this->add_control('limit', [
            'label'   => __('Limit', 'mhm-rentiva'),
            'type'    => 'number',
            'default' => 6,
        ]);

        $this->add_control('layout', [
            'label'   => __('Layout', 'mhm-rentiva'),
            'type'    => 'select',
            'default' => 'slider',
            'options' => [
                'slider' => __('Slider', 'mhm-rentiva'),
                'grid'   => __('Grid', 'mhm-rentiva'),
            ],
        ]);

        $this->add_control('columns', [
            'label'   => __('Columns', 'mhm-rentiva'),
            'type'    => 'select',
            'default' => '3',
            'options' => [
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
            'condition' => [
                'layout' => 'grid',
            ],
        ]);

        $this->end_controls_section();

        $this->register_standard_style_controls(
            'title_style',
            __('Vehicle Title', 'mhm-rentiva'),
            '.rv-vehicle-card__title a'
        );
    }

    protected function render(): void
    {
        $atts = $this->get_prepared_atts();
        $data = FeaturedVehicles::get_data($atts);

        if (!empty($data)) {
            extract($data);
            include MHM_RENTIVA_PLUGIN_DIR . 'templates/shortcodes/featured-vehicles.php';
        }
    }
}
