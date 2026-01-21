<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\Core\Tabs\AbstractTab;
use MHMRentiva\Admin\Licensing\Mode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Features Tab
 */
final class FeaturesTab extends AbstractTab
{
    protected static function get_tab_id(): string
    {
        return 'features';
    }

    protected static function get_tab_title(): string
    {
        return __('Features', 'mhm-rentiva');
    }

    protected static function get_tab_description(): string
    {
        return __('Lite vs Pro feature comparison', 'mhm-rentiva');
    }

    /**
     * Get features list
     * Uses centralized Mode class for single source of truth
     */
    public static function get_features_list(): array
    {
        return [
            'lite_vs_pro' => [
                'title' => __('Lite vs Pro Comparison', 'mhm-rentiva'),
                'features' => Mode::get_comparison_table_data(),
            ],
            'pro_features' => [
                'title' => __('Pro Features', 'mhm-rentiva'),
                'features' => Mode::get_pro_features_list(),
            ],
        ];
    }

    protected static function get_tab_content(array $data = []): array
    {
        // If no data is passed, get the features list
        if (empty($data)) {
            $data = static::get_features_list();
        }

        return [
            'title' => static::get_tab_title(),
            'description' => static::get_tab_description(),
            'sections' => [
                [
                    'type' => 'custom',
                    'title' => '', // Removing redundant title as table has its own header
                    'custom_render' => [static::class, 'render_detailed_features'],
                ],
            ],
        ];
    }

    /**
     * Render detailed features list
     */
    public static function render_detailed_features(array $section, array $data = []): void
    {
        if (empty($data)) {
            $data = static::get_features_list();
        }

        echo '<div class="features-detailed">';

        // Lite vs Pro comparison
        if (isset($data['lite_vs_pro'])) {
            echo '<div class="comparison-section">';
            echo '<h4>' . esc_html($data['lite_vs_pro']['title']) . '</h4>';
            echo '<div class="comparison-table">';
            echo '<table class="widefat">';
            echo '<thead><tr>';
            echo '<th>' . __('Feature', 'mhm-rentiva') . '</th>';
            echo '<th>' . __('Lite', 'mhm-rentiva') . '</th>';
            echo '<th>' . __('Pro', 'mhm-rentiva') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($data['lite_vs_pro']['features'] as $feature) {
                $lite_display = isset($feature['lite_icon'])
                    ? $feature['lite_icon'] . ' ' . $feature['lite']
                    : $feature['lite'];

                $pro_display = isset($feature['pro_icon'])
                    ? $feature['pro_icon'] . ' ' . $feature['pro']
                    : $feature['pro'];

                echo '<tr>';
                echo '<td><strong>' . esc_html($feature['name']) . '</strong></td>';
                echo '<td>' . esc_html($lite_display) . '</td>';
                echo '<td><strong>' . esc_html($pro_display) . '</strong></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }



        echo '</div>';
    }
}
