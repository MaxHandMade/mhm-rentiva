<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicles Grid Elementor Widget
 */
class VehiclesGridWidget extends ElementorWidgetBase
{

    public function get_name(): string
    {
        return 'mhm_rentiva_vehicles_grid';
    }

    public function get_title(): string
    {
        return __('MHM Vehicles Grid', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-gallery-grid';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content Settings', 'mhm-rentiva'),
        ]);

        $this->add_control('limit', [
            'label'   => __('Limit', 'mhm-rentiva'),
            'type'    => 'number',
            'default' => 12,
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
        ]);

        $this->add_control('orderby', [
            'label'   => __('Order By', 'mhm-rentiva'),
            'type'    => 'select',
            'default' => 'title',
            'options' => [
                'title'    => __('Title', 'mhm-rentiva'),
                'date'     => __('Date', 'mhm-rentiva'),
                'price'    => __('Price', 'mhm-rentiva'),
                'featured' => __('Featured', 'mhm-rentiva'),
            ],
        ]);

        $this->add_control('order', [
            'label'   => __('Order', 'mhm-rentiva'),
            'type'    => 'select',
            'default' => 'ASC',
            'options' => [
                'ASC'  => __('Ascending', 'mhm-rentiva'),
                'DESC' => __('Descending', 'mhm-rentiva'),
            ],
        ]);

        $this->add_control('show_price', [
            'label'     => __('Show Price', 'mhm-rentiva'),
            'type'      => 'switcher',
            'default'   => 'yes',
            'label_on'  => __('Show', 'mhm-rentiva'),
            'label_off' => __('Hide', 'mhm-rentiva'),
        ]);

        $this->end_controls_section();

        $this->register_standard_style_controls(
            'title_style',
            __('Vehicle Title', 'mhm-rentiva'),
            '.rv-vehicle-card__title a'
        );

        $this->register_standard_style_controls(
            'price_style',
            __('Price Tag', 'mhm-rentiva'),
            '.rv-price-amount'
        );
    }

    protected function render(): void
    {
        $atts = $this->get_prepared_atts();
        $data = VehiclesGrid::get_data($atts);

        if (!empty($data)) {
            extract($data);
            include MHM_RENTIVA_PLUGIN_DIR . 'templates/shortcodes/vehicles-grid.php';
        }
    }
}
