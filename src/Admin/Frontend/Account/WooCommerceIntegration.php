<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Integration
 * 
 * Integrates MHM Rentiva with WooCommerce My Account system
 * 
 * @since 4.0.0
 */
final class WooCommerceIntegration
{
    public static function register(): void
    {
        // Don't run if WooCommerce is not installed
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Add tabs to WooCommerce My Account
        add_filter('woocommerce_account_menu_items', [self::class, 'add_menu_items'], 20);
        
        // Add endpoints (priority 5 to run before WooCommerce's default endpoints)
        add_action('init', [self::class, 'add_endpoints'], 5);
        
        // Endpoint query var check
        add_filter('woocommerce_get_query_vars', [self::class, 'add_query_vars']);
        
        // Render content
        // We use dynamic hooks based on slugs, but add_action doesn't support dynamic tag names clearly in definition
        // So we hook into 'woocommerce_account_{slug}_endpoint' dynamically in a method or use a loop if possible.
        // However, since slugs can change, we should hook to the *current* slug.
        add_action('woocommerce_account_' . self::get_endpoint_slug('bookings', 'my-vehicle-bookings') . '_endpoint', [self::class, 'render_bookings']);
        add_action('woocommerce_account_' . self::get_endpoint_slug('favorites', 'my-favorite-vehicles') . '_endpoint', [self::class, 'render_favorites']);
        add_action('woocommerce_account_' . self::get_endpoint_slug('payment_history', 'my-payment-history') . '_endpoint', [self::class, 'render_payment_history']);
        add_action('woocommerce_account_' . self::get_endpoint_slug('messages', 'my-messages') . '_endpoint', [self::class, 'render_messages']);
        add_action('woocommerce_account_' . self::get_endpoint_slug('view_booking', 'view-vehicle-booking') . '_endpoint', [self::class, 'render_view_booking']);
        
        // Endpoint titles
        add_filter('the_title', [self::class, 'endpoint_title'], 10, 2);
        
        // Flush rewrite rules on plugin activation/update (one-time)
        add_action('admin_init', [self::class, 'maybe_flush_rewrite_rules']);
    }

    /**
     * Add items to WooCommerce My Account menu
     * 
     * @param array $items Existing menu items
     * @return array Modified menu items
     */
    public static function add_menu_items(array $items): array
    {
        // Temporarily remove logout to add our items before it
        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);
        
        // Add Rentiva items (i18n ready - translations via .po/.mo files)
        // Insert after 'orders' or 'dashboard' if orders doesn't exist
        $new_items = [];
        $inserted = false;
        
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            // Insert Rentiva items after 'orders' or 'dashboard'
            if (!$inserted && ($key === 'orders' || $key === 'dashboard')) {
                $new_items[self::get_endpoint_slug('bookings', 'my-vehicle-bookings')] = __('Vehicle Bookings', 'mhm-rentiva');
                $new_items[self::get_endpoint_slug('favorites', 'my-favorite-vehicles')] = __('Favorite Vehicles', 'mhm-rentiva');
                $new_items[self::get_endpoint_slug('payment_history', 'my-payment-history')] = __('Vehicle Payments', 'mhm-rentiva');
                
                // Add Messages if feature is enabled
                if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && 
                    \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
                    $new_items[self::get_endpoint_slug('messages', 'my-messages')] = __('Messages', 'mhm-rentiva');
                }
                
                $inserted = true;
            }
        }
        
        // If orders/dashboard not found, add at the beginning
        if (!$inserted) {
            $rentiva_items = [
                self::get_endpoint_slug('bookings', 'my-vehicle-bookings') => __('Vehicle Bookings', 'mhm-rentiva'),
                self::get_endpoint_slug('favorites', 'my-favorite-vehicles') => __('Favorite Vehicles', 'mhm-rentiva'),
                self::get_endpoint_slug('payment_history', 'my-payment-history') => __('Vehicle Payments', 'mhm-rentiva'),
            ];
            
            // Add Messages if feature is enabled
            if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && 
                \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
                $rentiva_items[self::get_endpoint_slug('messages', 'my-messages')] = __('Messages', 'mhm-rentiva');
            }
            
            $new_items = array_merge($rentiva_items, $new_items);
        }
        
        // Restore logout at the end
        if ($logout) {
            $new_items['customer-logout'] = $logout;
        }
        
        return $new_items;
    }

    /**
     * Add rewrite endpoints
     * WooCommerce endpoints should use EP_PAGES only (not EP_ROOT)
     */
    public static function add_endpoints(): void
    {
        // WooCommerce My Account endpoints - use EP_PAGES only
        add_rewrite_endpoint(self::get_endpoint_slug('bookings', 'my-vehicle-bookings'), EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('favorites', 'my-favorite-vehicles'), EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('payment_history', 'my-payment-history'), EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('messages', 'my-messages'), EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('view_booking', 'view-vehicle-booking'), EP_PAGES);
    }

    /**
     * Add to WooCommerce query vars
     */
    public static function add_query_vars(array $vars): array
    {
        $vars[self::get_endpoint_slug('bookings', 'my-vehicle-bookings')] = self::get_endpoint_slug('bookings', 'my-vehicle-bookings');
        $vars[self::get_endpoint_slug('favorites', 'my-favorite-vehicles')] = self::get_endpoint_slug('favorites', 'my-favorite-vehicles');
        $vars[self::get_endpoint_slug('payment_history', 'my-payment-history')] = self::get_endpoint_slug('payment_history', 'my-payment-history');
        $vars[self::get_endpoint_slug('messages', 'my-messages')] = self::get_endpoint_slug('messages', 'my-messages');
        $vars[self::get_endpoint_slug('view_booking', 'view-vehicle-booking')] = self::get_endpoint_slug('view_booking', 'view-vehicle-booking');
        
        return $vars;
    }

    /**
     * Bookings endpoint content
     */
    public static function render_bookings(): void
    {
        // Simply render list
        echo AccountRenderer::render_bookings(['hide_nav' => true]);
    }

    /**
     * View Booking Detail endpoint content
     */
    public static function render_view_booking($booking_id): void
    {
        $id = $booking_id;
        // If not passed as argument, try query var
        if (empty($id)) {
            global $wp_query;
            $var = self::get_endpoint_slug('view_booking', 'view-vehicle-booking');
            $id = $wp_query->get($var);
        }

        echo AccountRenderer::render_booking_detail((int)$id, true);
    }

    /**
     * Favorites endpoint content
     */
    public static function render_favorites(): void
    {
        echo AccountRenderer::render_favorites(['hide_nav' => true]);
    }

    /**
     * Payment History endpoint content
     */
    public static function render_payment_history(): void
    {
        echo AccountRenderer::render_payment_history(['hide_nav' => true]);
    }

    /**
     * Messages endpoint content
     */
    public static function render_messages(): void
    {
        // ⭐ Directly call AccountRenderer instead of shortcode
        // Shortcode would redirect to WooCommerce page, causing infinite loop
        echo AccountRenderer::render_messages(['hide_nav' => true]);
    }

    /**
     * Customize endpoint titles
     */
    public static function endpoint_title(string $title, int $id = 0): string
    {
        global $wp_query;
        
        $is_endpoint = isset($wp_query->query_vars[self::get_endpoint_slug('bookings', 'my-vehicle-bookings')]) ||
                      isset($wp_query->query_vars[self::get_endpoint_slug('favorites', 'my-favorite-vehicles')]) ||
                      isset($wp_query->query_vars[self::get_endpoint_slug('payment_history', 'my-payment-history')]) ||
                      isset($wp_query->query_vars[self::get_endpoint_slug('messages', 'my-messages')]);
        
        if (!$is_endpoint || !in_the_loop()) {
            return $title;
        }
        
        $endpoint_title = '';
        
        if (isset($wp_query->query_vars[self::get_endpoint_slug('bookings', 'my-vehicle-bookings')])) {
            $endpoint_title = __('Vehicle Bookings', 'mhm-rentiva');
        } elseif (isset($wp_query->query_vars[self::get_endpoint_slug('favorites', 'my-favorite-vehicles')])) {
            $endpoint_title = __('Favorite Vehicles', 'mhm-rentiva');
        } elseif (isset($wp_query->query_vars[self::get_endpoint_slug('payment_history', 'my-payment-history')])) {
            $endpoint_title = __('Vehicle Payments', 'mhm-rentiva');
        } elseif (isset($wp_query->query_vars[self::get_endpoint_slug('messages', 'my-messages')])) {
            $endpoint_title = __('Messages', 'mhm-rentiva');
        }
        
        return $endpoint_title ?: $title;
    }

    /**
     * Flush rewrite rules (only on activation)
     */
    public static function flush_rewrite_rules(): void
    {
        self::add_endpoints();
        flush_rewrite_rules();
    }

    /**
     * Check if rewrite rules need to be flushed
     * This runs once after plugin update/activation
     */
    public static function maybe_flush_rewrite_rules(): void
    {
        // Check if we need to flush rewrite rules
        $flush_key = 'mhm_rentiva_woocommerce_endpoints_flushed';
        $version_key = 'mhm_rentiva_woocommerce_endpoints_version';
        $current_version = '1.0.1'; // Increment when endpoints change
        
        $flushed = get_option($flush_key, false);
        $saved_version = get_option($version_key, '0');
        
        // Flush if not flushed before or version changed
        if (!$flushed || version_compare($saved_version, $current_version, '<')) {
            self::add_endpoints();
            flush_rewrite_rules(); // Make sure to hard flush
            update_option($flush_key, true);
            update_option($version_key, $current_version);
        }
    }

    /**
     * Get endpoint slug with translation and option support
     * 
     * Priority:
     * 1. Database option (custom user setting)
     * 2. Translation file (po/mo) via _x()
     * 3. Default hardcoded value
     * 
     * @param string $key Identifier key (e.g., 'bookings')
     * @param string $default Default slug in English
     * @return string Sanitized slug
     */
    public static function get_endpoint_slug(string $key, string $default): string
    {
        // 1. Check database option
        $settings = get_option('mhm_rentiva_settings', []);
        $option_key = 'mhm_rentiva_endpoint_' . $key;
        $slug = $settings[$option_key] ?? '';

        if (empty($slug)) {
            // 2. Use translation if no option set
            // context 'endpoint slug' helps translators know this is part of URL
            $slug = _x($default, 'endpoint slug', 'mhm-rentiva');
        }

        return sanitize_title($slug);
    }

}
