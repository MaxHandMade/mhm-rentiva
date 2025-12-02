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
        add_action('woocommerce_account_rentiva-bookings_endpoint', [self::class, 'render_bookings']);
        add_action('woocommerce_account_rentiva-favorites_endpoint', [self::class, 'render_favorites']);
        add_action('woocommerce_account_rentiva-payment-history_endpoint', [self::class, 'render_payment_history']);
        add_action('woocommerce_account_rentiva-messages_endpoint', [self::class, 'render_messages']);
        
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
                $new_items['rentiva-bookings'] = __('Vehicle Bookings', 'mhm-rentiva');
                $new_items['rentiva-favorites'] = __('Favorite Vehicles', 'mhm-rentiva');
                $new_items['rentiva-payment-history'] = __('Vehicle Payments', 'mhm-rentiva');
                
                // Add Messages if feature is enabled
                if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && 
                    \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
                    $new_items['rentiva-messages'] = __('Messages', 'mhm-rentiva');
                }
                
                $inserted = true;
            }
        }
        
        // If orders/dashboard not found, add at the beginning
        if (!$inserted) {
            $rentiva_items = [
                'rentiva-bookings' => __('Vehicle Bookings', 'mhm-rentiva'),
                'rentiva-favorites' => __('Favorite Vehicles', 'mhm-rentiva'),
                'rentiva-payment-history' => __('Vehicle Payments', 'mhm-rentiva'),
            ];
            
            // Add Messages if feature is enabled
            if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && 
                \MHMRentiva\Admin\Licensing\Mode::featureEnabled(\MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES)) {
                $rentiva_items['rentiva-messages'] = __('Messages', 'mhm-rentiva');
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
        add_rewrite_endpoint('rentiva-bookings', EP_PAGES);
        add_rewrite_endpoint('rentiva-favorites', EP_PAGES);
        add_rewrite_endpoint('rentiva-payment-history', EP_PAGES);
        add_rewrite_endpoint('rentiva-messages', EP_PAGES);
    }

    /**
     * Add to WooCommerce query vars
     */
    public static function add_query_vars(array $vars): array
    {
        $vars['rentiva-bookings'] = 'rentiva-bookings';
        $vars['rentiva-favorites'] = 'rentiva-favorites';
        $vars['rentiva-payment-history'] = 'rentiva-payment-history';
        $vars['rentiva-messages'] = 'rentiva-messages';
        
        return $vars;
    }

    /**
     * Bookings endpoint content
     */
    public static function render_bookings(): void
    {
        echo do_shortcode('[rentiva_my_bookings]');
    }

    /**
     * Favorites endpoint content
     */
    public static function render_favorites(): void
    {
        echo do_shortcode('[rentiva_my_favorites]');
    }

    /**
     * Payment History endpoint content
     */
    public static function render_payment_history(): void
    {
        echo do_shortcode('[rentiva_payment_history]');
    }

    /**
     * Messages endpoint content
     */
    public static function render_messages(): void
    {
        // ⭐ Directly call AccountRenderer instead of shortcode
        // Shortcode would redirect to WooCommerce page, causing infinite loop
        echo AccountRenderer::render_messages([]);
    }

    /**
     * Customize endpoint titles
     */
    public static function endpoint_title(string $title, int $id = 0): string
    {
        global $wp_query;
        
        $is_endpoint = isset($wp_query->query_vars['rentiva-bookings']) ||
                      isset($wp_query->query_vars['rentiva-favorites']) ||
                      isset($wp_query->query_vars['rentiva-payment-history']) ||
                      isset($wp_query->query_vars['rentiva-messages']);
        
        if (!$is_endpoint || !in_the_loop()) {
            return $title;
        }
        
        $endpoint_title = '';
        
        if (isset($wp_query->query_vars['rentiva-bookings'])) {
            $endpoint_title = __('Vehicle Bookings', 'mhm-rentiva');
        } elseif (isset($wp_query->query_vars['rentiva-favorites'])) {
            $endpoint_title = __('Favorite Vehicles', 'mhm-rentiva');
        } elseif (isset($wp_query->query_vars['rentiva-payment-history'])) {
            $endpoint_title = __('Vehicle Payments', 'mhm-rentiva');
        } elseif (isset($wp_query->query_vars['rentiva-messages'])) {
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
        $current_version = '1.0.0'; // Increment when endpoints change
        
        $flushed = get_option($flush_key, false);
        $saved_version = get_option($version_key, '0');
        
        // Flush if not flushed before or version changed
        if (!$flushed || version_compare($saved_version, $current_version, '<')) {
            self::add_endpoints();
            flush_rewrite_rules(false); // false = don't write to .htaccess, just update rules
            update_option($flush_key, true);
            update_option($version_key, $current_version);
        }
    }
}

