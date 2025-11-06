<?php declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\Core\Tabs\AbstractTab;
use MHMRentiva\Admin\Licensing\Mode;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * General Information Tab
 */
final class GeneralTab extends AbstractTab
{
    protected static function get_tab_id(): string
    {
        return 'general';
    }

    protected static function get_tab_title(): string
    {
        return __('General Information', 'mhm-rentiva');
    }

    protected static function get_tab_description(): string
    {
        return __('General information and statistics about the plugin', 'mhm-rentiva');
    }

    protected static function get_tab_content(array $data = []): array
    {
        // If no data is passed, get the system information
        if (empty($data)) {
            $data = static::get_system_info();
        }

        global $wpdb;
        
        // Get statistics
        $vehicle_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'vehicle', 'publish'
        ));
        
        $booking_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'vehicle_booking', 'publish'
        ));
        
        $customer_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN (
                SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = %s AND meta_value != ''
            )",
            '_booking_customer_id'
        ));

        return [
            'title' => static::get_tab_title(),
            'description' => static::get_tab_description(),
            'sections' => [
                [
                    'type' => 'card',
                    'cards' => [
                        [
                            'title' => __('Plugin Information', 'mhm-rentiva'),
                            'content' => [
                                ['type' => 'key-value', 'label' => __('Name:', 'mhm-rentiva'), 'value' => __('MHM Rentiva', 'mhm-rentiva'), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Version:', 'mhm-rentiva'), 'value' => 'v' . MHM_RENTIVA_VERSION, 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Developer:', 'mhm-rentiva'), 'value' => __('MHM (MaxHandMade)', 'mhm-rentiva'), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('License:', 'mhm-rentiva'), 'value' => Mode::isPro() ? __('Pro', 'mhm-rentiva') : __('Lite', 'mhm-rentiva'), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('File Size:', 'mhm-rentiva'), 'value' => __('Calculating...', 'mhm-rentiva'), 'data_key' => 'plugin.file_size'],
                            ],
                        ],
                        [
                            'title' => __('Compatibility', 'mhm-rentiva'),
                            'content' => [
                                ['type' => 'key-value', 'label' => __('WordPress:', 'mhm-rentiva'), 'value' => get_bloginfo('version'), 'data_key' => '', 'suffix' => '+'],
                                ['type' => 'key-value', 'label' => __('PHP:', 'mhm-rentiva'), 'value' => PHP_VERSION, 'data_key' => '', 'suffix' => '+'],
                                ['type' => 'key-value', 'label' => __('MySQL:', 'mhm-rentiva'), 'value' => __('5.6+', 'mhm-rentiva'), 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Tested:', 'mhm-rentiva'), 'value' => __('WP 6.6, PHP 8.2', 'mhm-rentiva'), 'data_key' => ''],
                            ],
                        ],
                        [
                            'title' => __('Statistics', 'mhm-rentiva'),
                            'content' => [
                                ['type' => 'key-value', 'label' => __('Total Vehicles:', 'mhm-rentiva'), 'value' => (string)$vehicle_count, 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Total Bookings:', 'mhm-rentiva'), 'value' => (string)$booking_count, 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Total Customers:', 'mhm-rentiva'), 'value' => (string)$customer_count, 'data_key' => ''],
                                ['type' => 'key-value', 'label' => __('Active License:', 'mhm-rentiva'), 'value' => Mode::isPro() ? __('Pro Active', 'mhm-rentiva') : __('Lite Version', 'mhm-rentiva'), 'data_key' => ''],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'custom',
                    'custom_render' => [static::class, 'render_action_buttons'],
                ],
            ],
        ];
    }

    /**
     * Action buttons render
     */
    public static function render_action_buttons(array $section, array $data = []): void
    {
        echo '<div class="action-buttons">';
        
        if (Mode::isPro()) {
            echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-settings')) . '" class="button button-primary">';
            echo __('Go to Settings', 'mhm-rentiva');
            echo '</a>';
        } else {
            echo '<a href="#" class="button button-primary upgrade-button">';
            echo __('Upgrade to Pro', 'mhm-rentiva');
            echo '</a>';
        }
        
        echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-about&tab=features')) . '" class="button">';
        echo __('View Features', 'mhm-rentiva');
        echo '</a>';
        
        echo '</div>';
    }
}