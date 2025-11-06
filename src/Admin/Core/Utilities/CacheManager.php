<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ CACHE OPTİMİZASYONU - Merkezi Cache Yönetimi
 * 
 * Gereksiz cache temizleme işlemlerini önler ve performansı artırır
 */
final class CacheManager
{
    /**
     * Cache prefix sabiti
     */
    private const CACHE_PREFIX = 'mhm_rentiva_';

    /**
     * Multisite-safe cache key oluştur
     * 
     * @param string $base_key Base cache key
     * @return string Multisite-safe cache key
     */
    private static function get_multisite_cache_key(string $base_key): string
    {
        // Multisite desteği: Blog ID ekle
        if (is_multisite()) {
            return $base_key . '_blog_' . get_current_blog_id();
        }
        return $base_key;
    }

    /**
     * Cache anahtarları
     */
    private const CACHE_KEYS = [
        'dashboard_stats' => 'mhm_rentiva_dashboard_stats',
        'booking_report' => 'mhm_rentiva_booking_report_',
        'customer_report' => 'mhm_rentiva_customer_report_',
        'vehicle_report' => 'mhm_rentiva_vehicle_report_',
        'revenue_report' => 'mhm_rentiva_revenue_report_',
        'addon_list' => 'mhm_rentiva_addon_list',
        'vehicle_list' => 'rv_vlist_',
        'system_info' => 'mhm_rentiva_system_info',
    ];

    /**
     * Cache etkin mi kontrol et
     * 
     * @return bool Cache etkin durumu
     */
    public static function is_cache_enabled(): bool
    {
        // Cache her zaman etkin (basit kontrol)
        return true;
    }

    /**
     * Cache türleri ve süreleri - dinamik olarak alınır
     */
    private static function get_cache_durations(): array
    {
        return [
            'dashboard_stats' => self::get_cache_duration_reports(),
            'booking_report' => self::get_cache_duration_reports(),
            'customer_report' => self::get_cache_duration_reports(),
            'vehicle_report' => self::get_cache_duration_reports(),
            'revenue_report' => self::get_cache_duration_reports(),
            'addon_list' => self::get_cache_duration_lists(),
            'vehicle_list' => self::get_cache_duration_lists(),
            'system_info' => self::get_cache_duration_system(),
        ];
    }

    /**
     * Cache süreleri - Ayarlardan alınır
     */
    private static function get_cache_duration_reports(): int
    {
        return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_reports_ttl();
    }

    private static function get_cache_duration_lists(): int
    {
        return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_lists_ttl();
    }

    private static function get_cache_duration_system(): int
    {
        return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_default_ttl();
    }

    private static function get_cache_duration_default(): int
    {
        return \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_cache_default_ttl();
    }

    /**
     * Cache'i temizle - Object Cache + Transient (sadece belirtilen türleri)
     */
    public static function clear_cache(array $types = []): void
    {
        if (empty($types)) {
            // Tüm cache'leri temizle
            $types = array_keys(self::CACHE_KEYS);
        }

        foreach ($types as $type) {
            if (!isset(self::CACHE_KEYS[$type])) {
                continue;
            }

            $pattern = self::CACHE_KEYS[$type];
            
            if (str_ends_with($pattern, '_')) {
                // Pattern ile temizle (örn: booking_report_*)
                self::clear_cache_by_pattern($pattern);
            } else {
                // Tek cache temizle
                self::delete_cache_object($pattern);
            }
        }
    }

    /**
     * Pattern ile cache temizle
     */
    private static function clear_cache_by_pattern(string $pattern): void
    {
        global $wpdb;
        
        // Transient cache'den pattern ile eşleşenleri bul
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE '_transient_%'",
            '%' . $wpdb->esc_like($pattern) . '%'
        ));

        foreach ($results as $result) {
            $transient_name = str_replace('_transient_', '', $result->option_name);
            delete_transient($transient_name);
        }
    }

    /**
     * Cache'e veri kaydet (Object Cache + Transient fallback)
     */
    public static function set_cache(string $type, string $key, $data, ?int $duration = null): bool
    {
        // Cache etkin değilse false döndür
        if (!\MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled()) {
            return false;
        }

        if (!isset(self::CACHE_KEYS[$type])) {
            return false;
        }

        $cache_key = self::get_multisite_cache_key(self::CACHE_KEYS[$type] . $key);
        $cache_durations = self::get_cache_durations();
        $duration = $duration ?? $cache_durations[$type] ?? self::get_cache_duration_default();

        // Object cache kullan (eğer mevcut ise)
        if (wp_using_ext_object_cache()) {
            return wp_cache_set($cache_key, $data, 'mhm_rentiva', $duration);
        }

        // Fallback: Transient cache
        return set_transient($cache_key, $data, $duration);
    }

    /**
     * Cache'den veri al (Object Cache + Transient fallback)
     */
    public static function get_cache(string $type, string $key = '')
    {
        if (!isset(self::CACHE_KEYS[$type])) {
            return false;
        }

        $cache_key = self::get_multisite_cache_key(self::CACHE_KEYS[$type] . $key);

        // Object cache kullan (eğer mevcut ise)
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($cache_key, 'mhm_rentiva');
        }

        // Fallback: Transient cache
        return get_transient($cache_key);
    }

    /**
     * Object Cache entegrasyonu - Generic cache object getter
     * 
     * @param string $key Cache anahtarı
     * @return mixed Cache değeri veya false
     */
    public static function get_cache_object(string $key)
    {
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($key, 'mhm_rentiva');
        }
        return get_transient($key);
    }

    /**
     * Object Cache entegrasyonu - Generic cache object setter
     * 
     * @param string $key Cache anahtarı
     * @param mixed $data Cache edilecek veri
     * @param int $ttl Time to live (saniye)
     * @return bool Başarı durumu
     */
    public static function set_cache_object(string $key, $data, int $ttl = null): bool
    {
        // Cache etkin değilse false döndür
        if (!\MHMRentiva\Admin\Settings\Groups\CoreSettings::is_cache_enabled()) {
            return false;
        }

        $ttl = $ttl ?? self::get_cache_duration_default();
        
        if (wp_using_ext_object_cache()) {
            return wp_cache_set($key, $data, 'mhm_rentiva', $ttl);
        }
        return set_transient($key, $data, $ttl);
    }

    /**
     * Object Cache entegrasyonu - Generic cache object deleter
     * 
     * @param string $key Cache anahtarı
     * @return bool Başarı durumu
     */
    public static function delete_cache_object(string $key): bool
    {
        if (wp_using_ext_object_cache()) {
            return wp_cache_delete($key, 'mhm_rentiva');
        }
        return delete_transient($key);
    }

    /**
     * Rezervasyon değişikliklerinde cache temizle
     */
    public static function clear_booking_cache(int $booking_id = 0): void
    {
        $types = ['dashboard_stats', 'booking_report', 'customer_report', 'revenue_report'];
        self::clear_cache($types);
        
        // Vehicle cache'i de temizle
        if ($booking_id > 0) {
            $vehicle_id = get_post_meta($booking_id, '_mhm_vehicle_id', true);
            if ($vehicle_id) {
                self::clear_cache(['vehicle_report']);
            }
        }
    }

    /**
     * Araç değişikliklerinde cache temizle
     */
    public static function clear_vehicle_cache(int $vehicle_id = 0): void
    {
        $types = ['dashboard_stats', 'vehicle_report', 'revenue_report', 'vehicle_list'];
        self::clear_cache($types);
    }

    /**
     * Addon değişikliklerinde cache temizle
     */
    public static function clear_addon_cache(int $addon_id = 0): void
    {
        self::clear_cache(['addon_list']);
        
        // Spesifik addon cache'i temizle
        if ($addon_id > 0) {
            wp_cache_delete($addon_id, 'post_meta');
            if (wp_using_ext_object_cache()) {
                wp_cache_delete($addon_id, 'posts');
            }
        }
    }

    /**
     * Settings değişikliklerinde cache temizle
     */
    public static function clear_settings_cache(): void
    {
        // Settings değişikliğinde sadece gerekli cache'leri temizle
        $types = ['dashboard_stats', 'system_info'];
        self::clear_cache($types);
    }

    /**
     * Cache istatistikleri
     */
    public static function get_cache_stats(): array
    {
        global $wpdb;
        
        $stats = [];
        foreach (self::CACHE_KEYS as $type => $pattern) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE '_transient_%'",
                '%' . $wpdb->esc_like($pattern) . '%'
            ));
            
            $stats[$type] = (int) $count;
        }
        
        return $stats;
    }

    /**
     * Belirli türdeki tüm cache'leri temizle
     * 
     * @param string $type Cache türü
     * @return bool Başarı durumu
     */
    public static function clear_cache_by_type(string $type): bool
    {
        global $wpdb;
        
        $pattern = self::CACHE_PREFIX . $type . '_';
        
        // Transient cache'leri temizle
        $transient_keys = $wpdb->get_col($wpdb->prepare("
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%' 
            AND option_name LIKE %s
        ", '%' . $wpdb->esc_like($pattern) . '%'));
        
        $success = true;
        foreach ($transient_keys as $key) {
            $transient_name = str_replace('_transient_', '', $key);
            if (!delete_transient($transient_name)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Cache'i sil
     * 
     * @param string $type Cache türü
     * @param string $key Cache anahtarı
     * @return bool Başarı durumu
     */
    public static function delete_cache(string $type, string $key): bool
    {
        if (!self::is_cache_enabled()) {
            return false;
        }

        $cache_key = self::CACHE_PREFIX . $type . '_' . $key;
        return delete_transient($cache_key);
    }
}
