<?php declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\Core\Tabs\AbstractTab;

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
     */
    public static function get_features_list(): array
    {
        return [
            'lite_vs_pro' => [
                'title' => __('Lite vs Pro Comparison', 'mhm-rentiva'),
                'features' => [
                    [
                        'name' => __('Vehicle Limit', 'mhm-rentiva'),
                        'lite' => sprintf(__('%d vehicles', 'mhm-rentiva'), 3),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Booking Limit', 'mhm-rentiva'),
                        'lite' => sprintf(__('%d bookings', 'mhm-rentiva'), 50),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Payment Methods', 'mhm-rentiva'),
                        'lite' => sprintf(__('%d methods', 'mhm-rentiva'), 2),
                        'pro' => sprintf(__('%d methods', 'mhm-rentiva'), 4),
                    ],
                    [
                        'name' => __('Support', 'mhm-rentiva'),
                        'lite' => __('Email', 'mhm-rentiva'),
                        'pro' => __('Priority Support', 'mhm-rentiva'),
                    ],
                ],
            ],
            'pro_features' => [
                'title' => __('Pro Features', 'mhm-rentiva'),
                'features' => [
                    __('Advanced Reporting', 'mhm-rentiva'),
                    __('Custom Design', 'mhm-rentiva'),
                    __('API Access', 'mhm-rentiva'),
                    __('Priority Updates', 'mhm-rentiva'),
                    __('Advanced Integrations', 'mhm-rentiva'),
                    __('Custom Widgets', 'mhm-rentiva'),
                ],
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
                    'type' => 'card',
                    'cards' => [
                        [
                            'title' => __('Lite vs Pro Comparison', 'mhm-rentiva'),
                            'content' => [
                                ['type' => 'key-value', 'label' => __('Vehicle Limit:', 'mhm-rentiva'), 'value' => sprintf(__('Lite: %d vehicles, Pro: %s', 'mhm-rentiva'), 3, __('Unlimited', 'mhm-rentiva')), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Booking Limit:', 'mhm-rentiva'), 'value' => sprintf(__('Lite: %d bookings, Pro: %s', 'mhm-rentiva'), 50, __('Unlimited', 'mhm-rentiva')), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Payment Methods:', 'mhm-rentiva'), 'value' => sprintf(__('Lite: %d methods, Pro: %d methods', 'mhm-rentiva'), 2, 4), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Support:', 'mhm-rentiva'), 'value' => sprintf(__('Lite: %s, Pro: %s', 'mhm-rentiva'), __('Email', 'mhm-rentiva'), __('Priority Support', 'mhm-rentiva')), 'data_key' => ''],
                            ],
                        ],
                        [
                            'title' => __('Pro Features', 'mhm-rentiva'),
                            'content' => [
                                ['type' => 'key-value', 'label' => __('Advanced Reporting:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Custom Design:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('API Access:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Priority Updates:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'custom',
                    'title' => __('Detailed Feature List', 'mhm-rentiva'),
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
                echo '<tr>';
                echo '<td><strong>' . esc_html($feature['name']) . '</strong></td>';
                echo '<td>' . esc_html($feature['lite']) . '</td>';
                echo '<td><strong>' . esc_html($feature['pro']) . '</strong></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }

        // Pro features
        if (isset($data['pro_features'])) {
            echo '<div class="pro-features-section">';
            echo '<h4>' . esc_html($data['pro_features']['title']) . '</h4>';
            echo '<div class="pro-features-grid">';
            
            foreach ($data['pro_features']['features'] as $feature) {
                echo '<div class="pro-feature-item">';
                echo '<span class="feature-icon">✓</span>';
                echo '<span class="feature-text">' . esc_html($feature) . '</span>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
}