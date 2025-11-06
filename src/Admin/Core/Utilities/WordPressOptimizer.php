<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress genel performans optimizasyonu
 */
final class WordPressOptimizer
{
    public static function register(): void
    {
        // PERFORMANS: Gereksiz WordPress özelliklerini devre dışı bırak
        add_action('init', [self::class, 'disable_unnecessary_features'], 1);
        
        add_action('admin_enqueue_scripts', [self::class, 'disable_heartbeat'], 1);
        add_action('admin_enqueue_scripts', [self::class, 'remove_unnecessary_admin_scripts'], 999);
        add_action('admin_notices', [self::class, 'limit_admin_notices'], 1);
    }

    /**
     * Gereksiz WordPress özelliklerini devre dışı bırak
     */
    public static function disable_unnecessary_features(): void
    {
        // Gutenberg editor aktif (tüm post type'larda)
        // add_filter('use_block_editor_for_post_type', '__return_true', 10, 2);
        
        // REST API hatalarını engelle (Gutenberg için gerekli olduğundan devre dışı)
        // add_filter('rest_pre_dispatch', [self::class, 'filter_rest_requests'], 10, 3);
        
        // Gereksiz WordPress emoji'leri devre dışı bırak
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        
        // Gereksiz WordPress embed'leri devre dışı bırak
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // Gereksiz WordPress feed'leri devre dışı bırak
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        
        // Gereksiz WordPress meta'ları devre dışı bırak
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'start_post_rel_link');
        remove_action('wp_head', 'index_rel_link');
        remove_action('wp_head', 'adjacent_posts_rel_link');
        
        // Gereksiz WordPress pingback'leri devre dışı bırak
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('template_redirect', 'wp_shortlink_header', 11);
        
        // Gereksiz WordPress XML-RPC'yi devre dışı bırak
        add_filter('xmlrpc_enabled', '__return_false');
        
        // Gereksiz WordPress ping'leri devre dışı bırak
        remove_action('do_pings', 'do_all_pings');
        remove_action('publish_post', '_publish_post_hook', 5);
    }

    /**
     * Admin sayfa performansını optimize et
     */
    public static function optimize_admin_performance(): void
    {
        // Gereksiz admin script'lerini devre dışı bırak
        wp_dequeue_script('heartbeat');
        wp_dequeue_script('autosave');
        wp_dequeue_script('wp-pointer');
        wp_dequeue_script('thickbox');
        wp_dequeue_script('media-upload');
        wp_dequeue_script('jquery-ui-tabs');
        wp_dequeue_script('jquery-ui-dialog');
        wp_dequeue_script('jquery-ui-widget');
        wp_dequeue_script('jquery-ui-mouse');
        wp_dequeue_script('jquery-ui-draggable');
        wp_dequeue_script('jquery-ui-droppable');
        wp_dequeue_script('jquery-ui-sortable');
        wp_dequeue_script('jquery-ui-resizable');
        wp_dequeue_script('jquery-ui-selectable');
        
        // Gereksiz admin CSS'lerini devre dışı bırak
        wp_dequeue_style('thickbox');
        wp_dequeue_style('wp-pointer');
        wp_dequeue_style('media-upload');
        wp_dequeue_style('jquery-ui-tabs');
        wp_dequeue_style('jquery-ui-dialog');
        wp_dequeue_style('jquery-ui-widget');
        wp_dequeue_style('jquery-ui-mouse');
        wp_dequeue_style('jquery-ui-draggable');
        wp_dequeue_style('jquery-ui-droppable');
        wp_dequeue_style('jquery-ui-sortable');
        wp_dequeue_style('jquery-ui-resizable');
        wp_dequeue_style('jquery-ui-selectable');
        
        // Gereksiz WordPress admin bar'ı devre dışı bırak
        if (!current_user_can('administrator')) {
            show_admin_bar(false);
        }
        
        // Gereksiz WordPress admin notice'leri sınırla
        add_action('admin_notices', [self::class, 'limit_admin_notices'], 1);
    }

    /**
     * Database sorgularını optimize et
     */
    public static function optimize_database_queries(array $clauses, \WP_Query $query): array
    {
        // Gereksiz meta sorgularını sınırla
        if (isset($query->query_vars['meta_query']) && is_array($query->query_vars['meta_query'])) {
            // Meta sorgu sayısını sınırla
            if (count($query->query_vars['meta_query']) > 5) {
                $query->query_vars['meta_query'] = array_slice($query->query_vars['meta_query'], 0, 5);
            }
        }
        
        // Gereksiz JOIN'leri engelle
        if (strpos($clauses['join'], 'LEFT JOIN') !== false) {
            // Gereksiz JOIN'leri temizle
            $clauses['join'] = preg_replace('/LEFT JOIN.*?ON.*?(?=LEFT JOIN|$)/s', '', $clauses['join']);
        }
        
        return $clauses;
    }

    /**
     * Gereksiz cron job'ları temizle
     */
    public static function cleanup_unnecessary_cron_jobs(): void
    {
        // Gereksiz WordPress cron job'larını temizle
        wp_clear_scheduled_hook('wp_scheduled_delete');
        wp_clear_scheduled_hook('wp_scheduled_auto_draft_delete');
        wp_clear_scheduled_hook('wp_scheduled_revision_delete');
        
        // Gereksiz WordPress ping'leri temizle
        wp_clear_scheduled_hook('do_pings');
        
        // Gereksiz WordPress update'leri temizle
        wp_clear_scheduled_hook('wp_update_plugins');
        wp_clear_scheduled_hook('wp_update_themes');
        wp_clear_scheduled_hook('wp_update_core');
        
        // Gereksiz WordPress comment'leri temizle
        wp_clear_scheduled_hook('wp_scheduled_comment_cleanup');
    }

    /**
     * Memory kullanımını optimize et
     */
    public static function optimize_memory_usage(): void
    {
        // Memory limit'i artır
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '256M');
        }
        
        // Gereksiz WordPress cache'leri temizle
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Gereksiz WordPress transients'leri temizle
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_value = ''");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
    }

    /**
     * Heartbeat'i devre dışı bırak
     */
    public static function disable_heartbeat(): void
    {
        if (!self::is_plugin_admin_screen()) {
            return;
        }

        global $pagenow;
        
        // Sadece gerekli sayfalarda heartbeat'i aktif et
        $allowed_pages = ['post.php', 'post-new.php', 'edit.php'];
        if (!in_array($pagenow, $allowed_pages, true)) {
            wp_deregister_script('heartbeat');
        }
    }

    /**
     * Gereksiz script'leri kaldır
     */
    public static function remove_unnecessary_scripts(): void
    {
        // Gereksiz jQuery script'lerini kaldır
        wp_dequeue_script('jquery-ui-core');
        wp_dequeue_script('jquery-ui-widget');
        wp_dequeue_script('jquery-ui-mouse');
        wp_dequeue_script('jquery-ui-draggable');
        wp_dequeue_script('jquery-ui-droppable');
        wp_dequeue_script('jquery-ui-sortable');
        wp_dequeue_script('jquery-ui-resizable');
        wp_dequeue_script('jquery-ui-selectable');
        
        // Gereksiz WordPress script'lerini kaldır
        wp_dequeue_script('wp-embed');
        wp_dequeue_script('wp-emoji');
        wp_dequeue_script('comment-reply');
        
        // Gereksiz WordPress CSS'lerini kaldır
        wp_dequeue_style('wp-embed');
        wp_dequeue_style('wp-emoji');
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-blocks-style');
    }

    /**
     * Gereksiz admin script'leri kaldır
     */
    public static function remove_unnecessary_admin_scripts(): void
    {
        if (!self::is_plugin_admin_screen()) {
            return;
        }

        // Yalnızca isteğe bağlı yardımcıları kaldır, kritik çekirdek varlıklarını koru
        wp_dequeue_script('wp-pointer');
        wp_dequeue_style('wp-pointer');
    }

    /**
     * Admin notice'leri sınırla
     */
    public static function limit_admin_notices(): void
    {
        if (!self::is_plugin_admin_screen()) {
            return;
        }

        // Gereksiz WordPress admin notice'leri kaldır
        remove_action('admin_notices', 'update_nag', 3);
        remove_action('admin_notices', 'maintenance_nag', 10);
        remove_action('admin_notices', 'wp_php_version_notice', 10);
        remove_action('admin_notices', 'wp_update_php_notice', 10);
        remove_action('admin_notices', 'wp_update_php_annotation', 10);
    }
    private static function is_plugin_admin_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        if (!empty($screen->id) && strpos($screen->id, 'mhm-rentiva') !== false) {
            return true;
        }

        $post_type = $screen->post_type ?? null;
        return in_array($post_type, ['vehicle', 'vehicle_booking', 'vehicle_addon'], true);
    }

    /**
     * REST API isteklerini filtrele ve gereksiz hataları engelle
     */
    public static function filter_rest_requests($result, $server, $request): mixed
    {
        $route = $request->get_route();
        
        // Gutenberg editor'ün gereksiz REST API isteklerini engelle
        $blocked_routes = [
            '/wp/v2/settings',
            '/wp/v2/templates',
            '/wp/v2/global-styles'
            // Vehicle post type'ı engellemeyelim - Gutenberg için gerekli
        ];
        
        foreach ($blocked_routes as $blocked_route) {
            if (strpos($route, $blocked_route) === 0) {
                // 404 hatası yerine boş response döndür
                return new \WP_REST_Response(['message' => 'Endpoint not available'], 200);
            }
        }
        
        return $result;
    }
}
