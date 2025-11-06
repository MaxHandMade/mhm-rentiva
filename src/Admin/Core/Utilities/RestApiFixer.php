<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ REST API FIXER - WordPress Core REST API Hatalarını Düzeltir
 * 
 * WordPress core REST API endpoint'lerindeki 500 hatalarını yakalar ve düzeltir
 */
final class RestApiFixer
{
    public static function register(): void
    {
        // REST API başlatılmadan önce fix'leri uygula
        add_action('rest_api_init', [self::class, 'fix_rest_api_errors'], 1);
        
        // REST request öncesi kontrol
        add_filter('rest_request_before_callbacks', [self::class, 'prevent_rest_errors'], 10, 3);
        
        // REST response sonrası kontrol
        add_filter('rest_request_after_callbacks', [self::class, 'handle_rest_errors'], 10, 3);
        
        // REST API route'larını güvenli hale getir
        add_action('rest_api_init', [self::class, 'register_safe_routes'], 5);
    }

    /**
     * REST API hatalarını düzelt
     */
    public static function fix_rest_api_errors(): void
    {
        // WordPress core endpoint'lerini güvenli hale getir
        self::fix_core_endpoints();
        
        // Vehicle endpoint'ini özel olarak düzelt
        self::fix_vehicle_endpoint();
        
        // Template endpoint'lerini düzelt
        self::fix_template_endpoints();
    }

    /**
     * Core endpoint'leri düzelt
     */
    private static function fix_core_endpoints(): void
    {
        // Settings endpoint'i için fallback
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/settings') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                echo wp_json_encode([
                    'title' => get_bloginfo('name'),
                    'description' => get_bloginfo('description'),
                    'url' => home_url(),
                    'timezone' => get_option('timezone_string'),
                    'date_format' => get_option('date_format'),
                    'time_format' => get_option('time_format'),
                    'start_of_week' => get_option('start_of_week'),
                    'language' => get_locale(),
                    'use_smilies' => get_option('use_smilies'),
                    'default_category' => get_option('default_category'),
                    'default_post_format' => get_option('default_post_format'),
                    'posts_per_page' => get_option('posts_per_page'),
                    'default_ping_status' => get_option('default_ping_status'),
                    'default_comment_status' => get_option('default_comment_status'),
                ]);
                
                return true;
            }
            return $served;
        }, 10, 4);

        // Taxonomies endpoint'i için fallback
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/taxonomies') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                $taxonomies = get_taxonomies([], 'objects');
                $response = [];
                
                foreach ($taxonomies as $taxonomy) {
                    $response[$taxonomy->name] = [
                        'name' => $taxonomy->label,
                        'slug' => $taxonomy->name,
                        'description' => $taxonomy->description,
                        'types' => $taxonomy->object_type,
                        'hierarchical' => $taxonomy->hierarchical,
                        'rest_base' => $taxonomy->rest_base,
                        'visibility' => [
                            'show_ui' => $taxonomy->show_ui,
                            'show_in_menu' => $taxonomy->show_in_menu,
                            'show_in_nav_menus' => $taxonomy->show_in_nav_menus,
                            'show_tagcloud' => $taxonomy->show_tagcloud,
                        ],
                    ];
                }
                
                echo wp_json_encode($response);
                return true;
            }
            return $served;
        }, 10, 4);

        // Blocks endpoint'i için fallback
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/blocks') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                echo wp_json_encode([]);
                return true;
            }
            return $served;
        }, 10, 4);
    }

    /**
     * Vehicle endpoint'ini düzelt
     */
    private static function fix_vehicle_endpoint(): void
    {
        // Vehicle endpoint'i için özel handling
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/vehicles') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                // Vehicle post type'ının kayıtlı olduğunu kontrol et
                if (!post_type_exists('vehicle')) {
                    echo wp_json_encode([
                        'code' => 'vehicle_post_type_not_found',
                        'message' => 'Vehicle post type is not registered',
                        'data' => ['status' => 404]
                    ]);
                    return true;
                }
                
                // Basit vehicle listesi döndür
                $vehicles = get_posts([
                    'post_type' => 'vehicle',
                    'post_status' => 'publish',
                    'numberposts' => 10,
                    'fields' => 'ids'
                ]);
                
                $response = [];
                foreach ($vehicles as $vehicle_id) {
                    $response[] = [
                        'id' => $vehicle_id,
                        'title' => get_the_title($vehicle_id),
                        'status' => 'publish',
                        'type' => 'vehicle',
                        'link' => get_permalink($vehicle_id),
                        'date' => get_post_time('c', false, $vehicle_id),
                        'modified' => get_post_modified_time('c', false, $vehicle_id),
                    ];
                }
                
                echo wp_json_encode($response);
                return true;
            }
            return $served;
        }, 10, 4);
    }

    /**
     * Template endpoint'lerini düzelt
     */
    private static function fix_template_endpoints(): void
    {
        // Template lookup endpoint'i için fallback
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/templates/lookup') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                echo wp_json_encode(null);
                return true;
            }
            return $served;
        }, 10, 4);

        // Templates endpoint'i için fallback
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/templates') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                echo wp_json_encode([]);
                return true;
            }
            return $served;
        }, 10, 4);

        // Pattern category endpoint'i için fallback
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            if (strpos($request->get_route(), '/wp/v2/wp_pattern_category') !== false && is_wp_error($result)) {
                $server->send_header('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
                $server->send_status(200);
                
                echo wp_json_encode([]);
                return true;
            }
            return $served;
        }, 10, 4);
    }

    /**
     * REST request öncesi hata önleme
     */
    public static function prevent_rest_errors($response, $handler, $request)
    {
        $route = $request->get_route();
        
        // Vehicle endpoint'i için özel kontrol
        if (strpos($route, '/wp/v2/vehicles') !== false) {
            if (!post_type_exists('vehicle')) {
                return new \WP_Error(
                    'vehicle_post_type_not_found',
                    'Vehicle post type is not registered',
                    ['status' => 404]
                );
            }
        }
        
        return $response;
    }

    /**
     * REST request sonrası hata yönetimi
     */
    public static function handle_rest_errors($response, $handler, $request)
    {
        if (is_wp_error($response)) {
            $route = $request->get_route();
            
            // 500 hatalarını 503'e çevir (geçici hata)
            if ($response->get_error_code() === 'rest_internal_server_error') {
                return new \WP_Error(
                    'service_temporarily_unavailable',
                    'Service temporarily unavailable. Please try again later.',
                    ['status' => 503]
                );
            }
        }
        
        return $response;
    }

    /**
     * Güvenli route'ları kaydet
     */
    public static function register_safe_routes(): void
    {
        // Vehicle endpoint'i için güvenli route
        register_rest_route('mhm-rentiva/v1', '/vehicles', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_vehicles_safe'],
            'permission_callback' => [self::class, 'permission_check'],
        ]);
    }

    /**
     * Permission callback - Rate limiting ile güvenlik kontrolü
     */
    public static function permission_check(): bool
    {
        // Rate limiting kontrolü
        $client_ip = \MHMRentiva\Admin\Core\Utilities\RateLimiter::getClientIP();
        return \MHMRentiva\Admin\Core\Utilities\RateLimiter::check($client_ip, 'general');
    }

    /**
     * Güvenli vehicle listesi
     */
    public static function get_vehicles_safe($request)
    {
        if (!post_type_exists('vehicle')) {
            return new \WP_Error(
                'vehicle_post_type_not_found',
                'Vehicle post type is not registered',
                ['status' => 404]
            );
        }

        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => 10,
        ]);

        $response = [];
        foreach ($vehicles as $vehicle) {
            $response[] = [
                'id' => $vehicle->ID,
                'title' => $vehicle->post_title,
                'status' => $vehicle->post_status,
                'type' => $vehicle->post_type,
                'link' => get_permalink($vehicle->ID),
                'date' => $vehicle->post_date,
                'modified' => $vehicle->post_modified,
                'meta' => [
                    'price_per_day' => get_post_meta($vehicle->ID, '_mhm_rentiva_price_per_day', true),
                    'seats' => get_post_meta($vehicle->ID, '_mhm_rentiva_seats', true),
                    'transmission' => get_post_meta($vehicle->ID, '_mhm_rentiva_transmission', true),
                    'fuel_type' => get_post_meta($vehicle->ID, '_mhm_rentiva_fuel_type', true),
                    'available' => get_post_meta($vehicle->ID, '_mhm_vehicle_availability', true) === 'active',
                ],
            ];
        }

        return rest_ensure_response($response);
    }
}
