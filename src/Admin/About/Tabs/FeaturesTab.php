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
                        'name' => __('Maximum Vehicles', 'mhm-rentiva'),
                        'lite' => sprintf(
                            /* translators: %d: maximum number of vehicles allowed in Lite version. */
                            __('%d vehicles', 'mhm-rentiva'),
                            3
                        ),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Maximum Bookings', 'mhm-rentiva'),
                        'lite' => sprintf(
                            /* translators: %d: maximum number of bookings allowed in Lite version. */
                            __('%d bookings', 'mhm-rentiva'),
                            50
                        ),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Maximum Customers', 'mhm-rentiva'),
                        'lite' => sprintf(
                            /* translators: %d: maximum number of customers allowed in Lite version. */
                            __('%d customers', 'mhm-rentiva'),
                            3
                        ),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Maximum Addons', 'mhm-rentiva'),
                        'lite' => sprintf(
                            /* translators: %d: maximum number of addon services allowed in Lite version. */
                            __('%d addon services', 'mhm-rentiva'),
                            4
                        ),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Payment Gateways', 'mhm-rentiva'),
                        'lite' => __('Offline + PayPal', 'mhm-rentiva'),
                        'pro' => __('All (Stripe, PayPal, PayTR, Offline)', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Export Formats', 'mhm-rentiva'),
                        'lite' => __('CSV, JSON', 'mhm-rentiva'),
                        'pro' => __('CSV, JSON, Excel, XML, PDF', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Report Date Range', 'mhm-rentiva'),
                        'lite' => __('30 days max', 'mhm-rentiva'),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Report Rows', 'mhm-rentiva'),
                        'lite' => __('500 max', 'mhm-rentiva'),
                        'pro' => __('Unlimited', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Advanced Reports', 'mhm-rentiva'),
                        'lite' => __('❌ Not available', 'mhm-rentiva'),
                        'pro' => __('✅ Available', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('Messaging System', 'mhm-rentiva'),
                        'lite' => __('❌ Not available', 'mhm-rentiva'),
                        'pro' => __('✅ Available', 'mhm-rentiva'),
                    ],
                    [
                        'name' => __('REST API Access', 'mhm-rentiva'),
                        'lite' => __('Limited', 'mhm-rentiva'),
                        'pro' => __('Full REST API', 'mhm-rentiva'),
                    ],
                ],
            ],
            'pro_features' => [
                'title' => __('Pro Features', 'mhm-rentiva'),
                'features' => [
                    __('Advanced Reporting (FEATURE_REPORTS_ADV)', 'mhm-rentiva'),
                    __('Messaging System (FEATURE_MESSAGES)', 'mhm-rentiva'),
                    __('Full REST API Access', 'mhm-rentiva'),
                    __('Stripe Payment Gateway', 'mhm-rentiva'),
                    __('PayTR Payment Gateway', 'mhm-rentiva'),
                    __('Excel Export (XLS)', 'mhm-rentiva'),
                    __('XML Export', 'mhm-rentiva'),
                    __('PDF Export', 'mhm-rentiva'),
                    __('Unlimited Date Range for Reports', 'mhm-rentiva'),
                    __('Unlimited Report Rows', 'mhm-rentiva'),
                    __('Email Notifications', 'mhm-rentiva'),
                    __('GDPR Compliance Tools', 'mhm-rentiva'),
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
                            'title' => __('Quick Comparison', 'mhm-rentiva'),
                            'content' => [
                                [
                                    'type' => 'key-value',
                                    'label' => __('Maximum Vehicles:', 'mhm-rentiva'),
                                    'value' => sprintf(
                                        /* translators: 1: Lite plan value, 2: Pro plan value. */
                                        __('Lite: %1$d, Pro: %2$s', 'mhm-rentiva'),
                                        3,
                                        __('Unlimited', 'mhm-rentiva')
                                    ),
                                    'data_key' => '',
                                ],
                                [
                                    'type' => 'key-value',
                                    'label' => __('Maximum Bookings:', 'mhm-rentiva'),
                                    'value' => sprintf(
                                        /* translators: 1: Lite plan value, 2: Pro plan value. */
                                        __('Lite: %1$d, Pro: %2$s', 'mhm-rentiva'),
                                        50,
                                        __('Unlimited', 'mhm-rentiva')
                                    ),
                                    'data_key' => '',
                                ],
                                [
                                    'type' => 'key-value',
                                    'label' => __('Payment Gateways:', 'mhm-rentiva'),
                                    'value' => sprintf(
                                        /* translators: 1: Lite plan value, 2: Pro plan value. */
                                        __('Lite: %1$s, Pro: %2$s', 'mhm-rentiva'),
                                        __('Offline + PayPal', 'mhm-rentiva'),
                                        __('All (Stripe, PayPal, PayTR, Offline)', 'mhm-rentiva')
                                    ),
                                    'data_key' => '',
                                ],
                                [
                                    'type' => 'key-value',
                                    'label' => __('Advanced Reports:', 'mhm-rentiva'),
                                    'value' => sprintf(
                                        /* translators: 1: Lite plan value, 2: Pro plan value. */
                                        __('Lite: %1$s, Pro: %2$s', 'mhm-rentiva'),
                                        __('Not available', 'mhm-rentiva'),
                                        __('Available', 'mhm-rentiva')
                                    ),
                                    'data_key' => '',
                                ],
                                [
                                    'type' => 'key-value',
                                    'label' => __('Messaging System:', 'mhm-rentiva'),
                                    'value' => sprintf(
                                        /* translators: 1: Lite plan value, 2: Pro plan value. */
                                        __('Lite: %1$s, Pro: %2$s', 'mhm-rentiva'),
                                        __('Not available', 'mhm-rentiva'),
                                        __('Available', 'mhm-rentiva')
                                    ),
                                    'data_key' => '',
                                ],
                            ],
                        ],
                        [
                            'title' => __('Pro Features Overview', 'mhm-rentiva'),
                            'content' => [
                                ['type' => 'key-value', 'label' => __('Advanced Reporting:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Messaging System:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Full REST API:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Stripe & PayTR:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Excel/XML/PDF Export:', 'mhm-rentiva'), 'value' => '✓', 'data_key' => ''],
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