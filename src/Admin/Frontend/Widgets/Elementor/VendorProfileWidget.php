<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

/**
 * Vendor Profile Elementor Widget.
 *
 * Delegates render to the [rentiva_vendor_profile] shortcode (canonical renderer).
 * Pro-gated via Mode::canUseVendorMarketplace() inside the shortcode.
 *
 * @since 4.37.0
 */
class VendorProfileWidget extends ElementorWidgetBase {

    /** @var array<int,string> */
    protected array $widget_keywords = [ 'mhm', 'rentiva', 'vendor', 'profile', 'bayi' ];

    public function get_name(): string
    {
        return 'mhm_rentiva_vendor_profile';
    }

    public function get_title(): string
    {
        return __('MHM Vendor Profile', 'mhm-rentiva');
    }

    public function get_icon(): string
    {
        return 'eicon-person';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_vendor',
            [
                'label' => __('Vendor', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'slug',
            [
                'label'       => __('Vendor slug', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => 'akif-otomotiv',
                'description' => __("The vendor's public profile slug. Required.", 'mhm-rentiva'),
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
            'show_badge'    => __('Badge', 'mhm-rentiva'),
            'show_rating'   => __('Rating', 'mhm-rentiva'),
            'show_about'    => __('About', 'mhm-rentiva'),
            'show_vehicles' => __('Vehicles', 'mhm-rentiva'),
            'show_reviews'  => __('Reviews', 'mhm-rentiva'),
            'show_location' => __('Location', 'mhm-rentiva'),
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
            'section_limits',
            [
                'label' => __('Limits', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'max_vehicles',
            [
                'label'   => __('Max vehicles', 'mhm-rentiva'),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 24,
                'default' => 6,
            ]
        );

        $this->add_control(
            'max_reviews',
            [
                'label'   => __('Max reviews', 'mhm-rentiva'),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 50,
                'default' => 10,
            ]
        );

        $this->add_control(
            'vehicle_sort',
            [
                'label'   => __('Vehicle sort order', 'mhm-rentiva'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'rating-newest',
                'options' => [
                    'rating-newest' => __('Rating, then newest', 'mhm-rentiva'),
                    'newest'        => __('Newest first', 'mhm-rentiva'),
                    'price-asc'     => __('Price (low to high)', 'mhm-rentiva'),
                    'price-desc'    => __('Price (high to low)', 'mhm-rentiva'),
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_empty',
            [
                'label' => __('Empty States', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'empty_vehicles_message',
            [
                'label'       => __('Empty vehicles message', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => __('Custom text when vendor has no public vehicles.', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'empty_reviews_message',
            [
                'label'       => __('Empty reviews message', 'mhm-rentiva'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => __('Custom text when vendor has no reviews yet.', 'mhm-rentiva'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        // Pass attrs through raw — the shortcode templates use strict
        // === 'yes' comparison, so we must NOT convert via get_prepared_atts()
        // (which maps 'yes' → '1' and would silently disable every section).
        $allowed = [
            'slug',
            'show_badge',
            'show_rating',
            'show_about',
            'show_vehicles',
            'max_vehicles',
            'vehicle_sort',
            'show_reviews',
            'max_reviews',
            'show_location',
            'empty_vehicles_message',
            'empty_reviews_message',
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
        echo $this->render_shortcode('rentiva_vendor_profile', $atts);
    }
}
