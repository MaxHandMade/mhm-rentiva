<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ DEBUG HELPER - WordPress Debug Modunu Yönetir
 * 
 * Development ortamında debug modunu aktifleştirir ve hataları yakalar
 */
final class DebugHelper
{
    public static function register(): void
    {
        // Debug modunu aktifleştir (sadece development'ta)
        if (self::is_development_environment()) {
            self::enable_debug_mode();
            self::setup_error_handling();
        }
    }

    /**
     * Development ortamı kontrolü
     */
    private static function is_development_environment(): bool
    {
        // Localhost kontrolü
        if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
            return true;
        }
        
        // XAMPP kontrolü
        if (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false) {
            return true;
        }
        
        // WP_DEBUG kontrolü
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        
        return false;
    }

    /**
     * Debug modunu aktifleştir
     */
    private static function enable_debug_mode(): void
    {
        // WordPress debug modunu aktifleştir
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }
        
        if (!defined('WP_DEBUG_DISPLAY')) {
            define('WP_DEBUG_DISPLAY', false);
        }
        
        if (!defined('SCRIPT_DEBUG')) {
            define('SCRIPT_DEBUG', true);
        }
        
        // REST API debug - PERFORMANS OPTİMİZASYONU: Kapatıldı
        // if (!defined('REST_REQUEST')) {
        //     define('REST_REQUEST', true);
        // }
    }

    /**
     * Error handling kurulumu
     */
    private static function setup_error_handling(): void
    {
        // PHP error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        
        // WordPress error handling
        add_action('wp_loaded', [self::class, 'log_rest_api_errors']);
        add_action('rest_api_init', [self::class, 'log_rest_api_init']);
    }

    /**
     * REST API hatalarını logla
     */
    public static function log_rest_api_errors(): void
    {
        // PERFORMANS OPTİMİZASYONU: Debug log'ları kapatıldı
        // if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        //     error_log('MHM Rentiva: REST API Debug - wp_loaded action fired');
        // }
    }

    /**
     * REST API init logla
     */
    public static function log_rest_api_init(): void
    {
        // PERFORMANS OPTİMİZASYONU: Debug log'ları kapatıldı
        // if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        //     error_log('MHM Rentiva: REST API Debug - rest_api_init action fired');
        //     
        //     // Vehicle post type kontrolü
        //     if (post_type_exists('vehicle')) {
        //         error_log('MHM Rentiva: Vehicle post type is registered');
        //     } else {
        //         error_log('MHM Rentiva: ERROR - Vehicle post type is NOT registered');
        //     }
        // }
    }

    /**
     * REST API request'lerini logla
     */
    public static function log_rest_request($response, $handler, $request): mixed
    {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $route = $request->get_route();
            $method = $request->get_method();
            
            error_log("MHM Rentiva: REST API Request - {$method} {$route}");
            
            if (is_wp_error($response)) {
                error_log("MHM Rentiva: REST API Error - " . $response->get_error_message());
            }
        }
        
        return $response;
    }

    /**
     * Debug bilgilerini döndür
     */
    public static function get_debug_info(): array
    {
        return [
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
            'script_debug' => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : false,
            'vehicle_post_type_exists' => post_type_exists('vehicle'),
            'rest_api_available' => function_exists('rest_url'),
            'current_user_can_edit_posts' => current_user_can('edit_posts'),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'theme' => get_template(),
            'plugins' => get_option('active_plugins', []),
        ];
    }
}
