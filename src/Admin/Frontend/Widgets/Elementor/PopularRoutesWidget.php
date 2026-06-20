<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (! defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

/**
 * Popular Routes Elementor Widget
 *
 * Delegates render to the [rentiva_popular_routes] shortcode (canonical renderer).
 *
 * @since 4.34.0
 */
class PopularRoutesWidget extends ElementorWidgetBase {

    public function get_name(): string
    {
        return 'mhm_rentiva_popular_routes';
    }

    public function get_title(): string
    {
        return __('MHM Popular Routes', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-pin';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => __('Layout', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => __('Columns (desktop)', 'mhm-rentiva'),
                'type'    => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'       => __('Maximum cards', 'mhm-rentiva'),
                'type'        => Controls_Manager::NUMBER,
                'default'     => 6,
                'min'         => 1,
                'max'         => 50,
                'description' => __('Lite plans cap at 3 routes regardless of this value.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'theme',
            [
                'label'   => __('Theme', 'mhm-rentiva'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'light',
                'options' => [
                    'light' => __('Light', 'mhm-rentiva'),
                    'dark'  => __('Dark', 'mhm-rentiva'),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_heading',
            [
                'label' => __('Heading', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'heading',
            [
                'label'       => __('Title', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => __('Popular Routes', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'subheading',
            [
                'label'       => __('Subtitle', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => __('Most preferred VIP transfer routes', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'showViewAll',
            [
                'label'        => __('Show "View all" link', 'mhm-rentiva'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'viewAllUrl',
            [
                'label'       => __('"View all" URL', 'mhm-rentiva'),
                'type'        => Controls_Manager::URL,
                'default'     => [ 'url' => '' ],
                'description' => __('Leave empty to use the default transfer-search URL filter.', 'mhm-rentiva'),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_filters',
            [
                'label' => __('Sorting & Filters', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'order',
            [
                'label'   => __('Sort order', 'mhm-rentiva'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'featured',
                'options' => [
                    'featured'     => __('Featured (pinned first)', 'mhm-rentiva'),
                    'price_asc'    => __('Price (low → high)', 'mhm-rentiva'),
                    'price_desc'   => __('Price (high → low)', 'mhm-rentiva'),
                    'alphabetical' => __('Alphabetical', 'mhm-rentiva'),
                    'newest'       => __('Newest first', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'featuredOnly',
            [
                'label'        => __('Show only pinned ("Vitrine") routes', 'mhm-rentiva'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'filterOriginCity',
            [
                'label'       => __('Filter by origin city', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => __('e.g. Istanbul', 'mhm-rentiva'),
                'default'     => '',
            ]
        );

        $this->add_control(
            'filterOriginType',
            [
                'label'   => __('Filter by origin type', 'mhm-rentiva'),
                'type'    => Controls_Manager::SELECT,
                'default' => '',
                'options' => [
                    ''            => __('All types', 'mhm-rentiva'),
                    'airport'     => __('Airport', 'mhm-rentiva'),
                    'train'       => __('Train station', 'mhm-rentiva'),
                    'hotel'       => __('Hotel', 'mhm-rentiva'),
                    'marina'      => __('Marina / Port', 'mhm-rentiva'),
                    'city_center' => __('City center', 'mhm-rentiva'),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_card_display',
            [
                'label' => __('Card Display', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'showDuration',
            [
                'label'        => __('Show duration', 'mhm-rentiva'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'showDistance',
            [
                'label'        => __('Show distance', 'mhm-rentiva'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'showTrafficNote',
            [
                'label'        => __('Show traffic note', 'mhm-rentiva'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'showPrice',
            [
                'label'        => __('Show starting price', 'mhm-rentiva'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->register_parity_controls_from_block();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Elementor URL controls return arrays — flatten to plain string for the shortcode.
        if (isset($settings['viewAllUrl']) && is_array($settings['viewAllUrl'])) {
            $settings['viewAllUrl'] = (string) ( $settings['viewAllUrl']['url'] ?? '' );
        }

        $atts = $this->prepare_shortcode_attributes($settings);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains escaped HTML.
        echo $this->render_shortcode('rentiva_popular_routes', $atts);
    }
}
