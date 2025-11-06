<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global Shortcode URL Manager
 * 
 * Dynamic URL management for all shortcodes
 * Completely eliminates hardcoded URLs
 * 
 * @since 4.0.0
 */
final class ShortcodeUrlManager
{
    /**
     * Shortcode cache
     */
    private static array $page_cache = [];

    /**
     * Get page URL for shortcode
     * 
     * @param string $shortcode Shortcode name (e.g. 'rentiva_my_account')
     * @return string Page URL
     */
    public static function get_page_url(string $shortcode): string
    {
        // Cache check
        if (isset(self::$page_cache[$shortcode])) {
            return self::$page_cache[$shortcode];
        }

        // Find page ID
        $page_id = self::find_page_by_shortcode($shortcode);
        
        if ($page_id) {
            $url = get_permalink($page_id);
            self::$page_cache[$shortcode] = $url;
            return $url;
        }

        // Show warning if page not found and return fallback
        self::show_admin_notice_missing_page($shortcode);
        
        // Fallback: Home page
        $fallback = home_url('/');
        self::$page_cache[$shortcode] = $fallback;
        return $fallback;
    }

    /**
     * Get page ID for shortcode
     * 
     * @param string $shortcode Shortcode name
     * @return int|null Page ID
     */
    public static function get_page_id(string $shortcode): ?int
    {
        return self::find_page_by_shortcode($shortcode);
    }

    /**
     * Check if page exists for shortcode
     * 
     * @param string $shortcode Shortcode name
     * @return bool Page exists
     */
    public static function page_exists(string $shortcode): bool
    {
        return self::find_page_by_shortcode($shortcode) !== null;
    }

    /**
     * List all shortcode pages
     * 
     * @return array Shortcode => Page ID mapping
     */
    public static function get_all_pages(): array
    {
        $shortcodes = [
            // Account Management Shortcodes
            'rentiva_my_account',
            'rentiva_my_bookings',
            'rentiva_my_favorites', 
            'rentiva_payment_history',
            'rentiva_account_details',
            'rentiva_login_form',
            'rentiva_register_form',
            
            // Booking Shortcodes
            'rentiva_booking_form',
            'rentiva_availability_calendar',
            'rentiva_booking_confirmation',
            
            // Vehicle Display Shortcodes
            'rentiva_vehicle_details',
            'rentiva_vehicles_grid',
            'rentiva_vehicles_list',
            'rentiva_vehicle_comparison',
            'rentiva_search',
            'rentiva_search_results',
            
            // Support Shortcodes
            'rentiva_contact',
            'rentiva_testimonials',
            'rentiva_vehicle_rating_form',
        ];

        $pages = [];
        foreach ($shortcodes as $shortcode) {
            $page_id = self::find_page_by_shortcode($shortcode);
            if ($page_id) {
                $pages[$shortcode] = $page_id;
            }
        }

        return $pages;
    }

    /**
     * Find page containing shortcode
     * 
     * @param string $shortcode Shortcode name
     * @return int|null Page ID
     */
    private static function find_page_by_shortcode(string $shortcode): ?int
    {
        global $wpdb;
        
        // Cache check
        $cache_key = 'mhm_shortcode_' . $shortcode;
        $cached_id = wp_cache_get($cache_key, 'mhm_rentiva');
        if ($cached_id !== false) {
            return $cached_id ? (int) $cached_id : null;
        }

        // Search in database - advanced search (including parameterized shortcodes)
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish' 
             AND (
                 post_content LIKE %s OR
                 post_content LIKE %s OR
                 post_content LIKE %s
             )
             ORDER BY post_date DESC
             LIMIT 1",
            '%[' . $shortcode . ']%',           // [rentiva_quick_booking]
            '%[' . $shortcode . ' %]%',         // [rentiva_quick_booking columns="2"]
            '%[' . $shortcode . '=%'            // [rentiva_quick_booking=something]
        ));

        // Save to cache
        wp_cache_set($cache_key, $page_id ?: 0, 'mhm_rentiva', HOUR_IN_SECONDS);

        return $page_id ? (int) $page_id : null;
    }

    /**
     * Show admin warning for missing page
     * 
     * @param string $shortcode Shortcode name
     */
    private static function show_admin_notice_missing_page(string $shortcode): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show only once
        $transient_key = 'mhm_missing_page_' . $shortcode;
        if (get_transient($transient_key)) {
            return;
        }

        add_action('admin_notices', function() use ($shortcode) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>MHM Rentiva:</strong> ';
            printf(
                __('No page found containing the <code>[%s]</code> shortcode. ', 'mhm-rentiva'),
                esc_html($shortcode)
            );
            echo '<a href="' . admin_url('post-new.php?post_type=page') . '">';
            echo __('Create a new page</a> and add the shortcode.', 'mhm-rentiva');
            echo '</p>';
            echo '</div>';
        });

        // Don't show warning for 1 hour
        set_transient($transient_key, true, HOUR_IN_SECONDS);
    }

    /**
     * Clear cache
     * 
     * @param string|null $shortcode Specific shortcode (null = clear all)
     */
    public static function clear_cache(?string $shortcode = null): void
    {
        if ($shortcode) {
            wp_cache_delete('mhm_shortcode_' . $shortcode, 'mhm_rentiva');
            delete_transient('mhm_missing_page_' . $shortcode);
        } else {
            // Clear all cache
            $shortcodes = [
                // Account Management Shortcodes
                'rentiva_my_account', 'rentiva_my_bookings', 'rentiva_my_favorites', 
                'rentiva_payment_history', 'rentiva_account_details', 'rentiva_login_form',
                'rentiva_register_form',
                
                // Booking Shortcodes
                'rentiva_booking_form', 'rentiva_availability_calendar', 'rentiva_booking_confirmation',
                
                // Vehicle Display Shortcodes
                'rentiva_vehicle_details', 'rentiva_vehicles_grid', 'rentiva_vehicles_list',
                'rentiva_vehicle_comparison', 'rentiva_search', 'rentiva_search_results',
                
                // Support Shortcodes
                'rentiva_contact', 'rentiva_testimonials', 'rentiva_vehicle_rating_form',
            ];

            foreach ($shortcodes as $sc) {
                wp_cache_delete('mhm_shortcode_' . $sc, 'mhm_rentiva');
                delete_transient('mhm_missing_page_' . $sc);
            }
        }
    }

    /**
     * Clear cache when page is updated
     * 
     * @param int $post_id Page ID
     */
    public static function clear_cache_on_page_update(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') {
            return;
        }

        // Find all shortcodes on page and clear their caches
        $content = $post->post_content;
        $shortcodes = [
            // Account Management Shortcodes
            'rentiva_my_account', 'rentiva_my_bookings', 'rentiva_my_favorites', 
            'rentiva_payment_history', 'rentiva_account_details', 'rentiva_login_form',
            'rentiva_register_form',
            
            // Booking Shortcodes
            'rentiva_booking_form', 'rentiva_availability_calendar', 'rentiva_booking_confirmation',
            
            // Vehicle Display Shortcodes
            'rentiva_vehicle_details', 'rentiva_vehicles_grid', 'rentiva_vehicles_list',
            'rentiva_vehicle_comparison', 'rentiva_search', 'rentiva_search_results',
            
            // Support Shortcodes
            'rentiva_contact', 'rentiva_testimonials', 'rentiva_vehicle_rating_form',
        ];

        foreach ($shortcodes as $shortcode) {
            // Advanced shortcode detection
            if (strpos($content, '[' . $shortcode . ']') !== false ||
                strpos($content, '[' . $shortcode . ' ') !== false ||
                strpos($content, '[' . $shortcode . '=') !== false) {
                self::clear_cache($shortcode);
            }
        }
    }
}
