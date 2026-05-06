<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

/**
 * Vendor Directory Elementor Widget.
 *
 * Delegates render to the [rentiva_vendor_directory] shortcode (canonical renderer).
 * Pro-gated via Mode::canUseVendorMarketplace() inside the shortcode.
 *
 * @since 4.38.0
 */
class VendorDirectoryWidget extends ElementorWidgetBase {

    /** @var array<int,string> */
    protected array $widget_keywords = [ 'mhm', 'rentiva', 'vendor', 'directory', 'bayi', 'liste' ];

    public function get_name(): string
    {
        return 'mhm_rentiva_vendor_directory';
    }

    public function get_title(): string
    {
        return __('MHM Vendor Directory', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-post-list';
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
            'per_page',
            [
                'label'   => __('Vendors per page', 'mhm-rentiva'),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 50,
                'default' => 12,
            ]
        );

        $this->add_control(
            'default_sort',
            [
                'label'   => __('Default sort', 'mhm-rentiva'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'rating',
                'options' => [
                    'rating' => __('Rating', 'mhm-rentiva'),
                    'newest' => __('Newest first', 'mhm-rentiva'),
                    'alpha'  => __('Alphabetical', 'mhm-rentiva'),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_sections',
            [
                'label' => __('Sections', 'mhm-rentiva'),
            ]
        );

        // Static __() calls — variable-based __() is not extractable by makepot.
        $section_labels = [
            'show_filter_bar' => __('Filter bar', 'mhm-rentiva'),
            'show_breadcrumb' => __('Breadcrumb', 'mhm-rentiva'),
            'show_pagination' => __('Pagination', 'mhm-rentiva'),
        ];

        foreach ($section_labels as $key => $label) {
            $this->add_control(
                $key,
                [
                    'label'        => $label,
                    'type'         => Controls_Manager::SWITCHER,
                    'return_value' => 'yes',
                    'default'      => 'yes',
                ]
            );
        }

        $this->end_controls_section();

        $this->start_controls_section(
            'section_empty',
            [
                'label' => __('Empty State', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label'       => __('Empty message', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => __('Custom text when no vendors match the current filters.', 'mhm-rentiva'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Pass attrs through raw — the shortcode normalizes 'yes'/'no' itself
        // (Phase 7 of v4.37.0 lesson: get_prepared_atts() maps 'yes' → '1' which
        // bypasses the shortcode's strict === 'yes' comparison).
        $allowed = [
            'per_page',
            'default_sort',
            'show_filter_bar',
            'show_breadcrumb',
            'show_pagination',
            'empty_message',
        ];

        $atts = [];
        foreach ($allowed as $key) {
            if (!isset($settings[ $key ])) {
                continue;
            }
            $value = $settings[ $key ];
            if ($value === '' || $value === null) {
                continue;
            }
            $atts[ $key ] = (string) $value;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output is escaped by the canonical renderer.
        echo $this->render_shortcode('rentiva_vendor_directory', $atts);
    }
}
