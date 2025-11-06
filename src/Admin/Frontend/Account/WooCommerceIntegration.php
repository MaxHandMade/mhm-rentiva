<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

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
        
        // Add endpoints
        add_action('init', [self::class, 'add_endpoints']);
        
        // Endpoint query var check
        add_filter('woocommerce_get_query_vars', [self::class, 'add_query_vars']);
        
        // Render content
        add_action('woocommerce_account_rentiva-bookings_endpoint', [self::class, 'render_bookings']);
        add_action('woocommerce_account_rentiva-favorites_endpoint', [self::class, 'render_favorites']);
        add_action('woocommerce_account_rentiva-payment-history_endpoint', [self::class, 'render_payment_history']);
        
        // Endpoint titles
        add_filter('the_title', [self::class, 'endpoint_title'], 10, 2);
    }

    /**
     * Add items to WooCommerce My Account menu
     */
    public static function add_menu_items(array $items): array
    {
        // Temporarily remove logout
        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);
        
        // Add Rentiva items
        $items['rentiva-bookings'] = __('Vehicle Bookings', 'mhm-rentiva');
        $items['rentiva-favorites'] = __('Favorite Vehicles', 'mhm-rentiva');
        $items['rentiva-payment-history'] = __('Vehicle Payments', 'mhm-rentiva');
        
        // Restore logout
        if ($logout) {
            $items['customer-logout'] = $logout;
        }
        
        return $items;
    }

    /**
     * Add rewrite endpoints
     */
    public static function add_endpoints(): void
    {
        add_rewrite_endpoint('rentiva-bookings', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('rentiva-favorites', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('rentiva-payment-history', EP_ROOT | EP_PAGES);
    }

    /**
     * Add to WooCommerce query vars
     */
    public static function add_query_vars(array $vars): array
    {
        $vars['rentiva-bookings'] = 'rentiva-bookings';
        $vars['rentiva-favorites'] = 'rentiva-favorites';
        $vars['rentiva-payment-history'] = 'rentiva-payment-history';
        
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
     * Customize endpoint titles
     */
    public static function endpoint_title(string $title, int $id = 0): string
    {
        global $wp_query;
        
        $is_endpoint = isset($wp_query->query_vars['rentiva-bookings']) ||
                      isset($wp_query->query_vars['rentiva-favorites']) ||
                      isset($wp_query->query_vars['rentiva-payment-history']);
        
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
}

