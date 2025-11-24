<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ RATE LIMITER - API Endpoint Güvenliği
 * 
 * API endpoint'lerini brute force saldırılarına karşı korur
 */
final class RateLimiter
{
    /**
     * Rate limit konfigürasyonları
     */
    private static function get_rate_limits(): array
    {
        return [
            'general' => [
                'max_per_minute' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_general_minute(),
                'max_per_hour' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_general_minute() * 16,
                'max_per_day' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_general_minute() * 240
            ],
            'booking_creation' => [
                'max_per_minute' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_booking_minute(),
                'max_per_hour' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_booking_minute() * 10,
                'max_per_day' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_booking_minute() * 40
            ],
            'payment_processing' => [
                'max_per_minute' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_payment_minute(),
                'max_per_hour' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_payment_minute() * 6,
                'max_per_day' => \MHMRentiva\Admin\Settings\Groups\CoreSettings::get_rate_limit_payment_minute() * 33
            ],

        'file_upload' => [
            'max_per_minute' => 5,
            'max_per_hour' => 30,
            'max_per_day' => 100
        ],
        'webhook_processing' => [
            'max_per_minute' => 20,
            'max_per_hour' => 200,
            'max_per_day' => 1000
        ],
        'admin_actions' => [
            'max_per_minute' => 30,
            'max_per_hour' => 300,
            'max_per_day' => 2000
        ]
        ];
    }

    /**
     * Rate limit kontrolü yap
     * 
     * @param string $identifier Client identifier (IP, user_id, etc.)
     * @param string $action Action type
     * @return bool Rate limit aşıldı mı?
     */
    public static function check(string $identifier, string $action = 'general'): bool
    {
        // Rate limiter etkin değilse her zaman true döndür
        if (!\MHMRentiva\Admin\Settings\Groups\CoreSettings::is_rate_limit_enabled()) {
            return true;
        }

        $rate_limits = self::get_rate_limits();
        $limits = $rate_limits[$action] ?? $rate_limits['general'];
        
        // Her zaman dilimi için kontrol et
        $checks = [
            'minute' => self::checkTimeframe($identifier, $action, 'minute', $limits['max_per_minute'], MINUTE_IN_SECONDS),
            'hour' => self::checkTimeframe($identifier, $action, 'hour', $limits['max_per_hour'], HOUR_IN_SECONDS),
            'day' => self::checkTimeframe($identifier, $action, 'day', $limits['max_per_day'], DAY_IN_SECONDS)
        ];

        // Herhangi bir zaman diliminde limit aşıldıysa false döndür
        return !in_array(false, $checks, true);
    }

    /**
     * Belirli bir zaman dilimi için rate limit kontrolü
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param string $timeframe Zaman dilimi (minute, hour, day)
     * @param int $max_requests Max request sayısı
     * @param int $duration Saniye cinsinden süre
     * @return bool Limit aşıldı mı?
     */
    private static function checkTimeframe(string $identifier, string $action, string $timeframe, int $max_requests, int $duration): bool
    {
        $cache_key = self::getCacheKey($identifier, $action, $timeframe);
        $current_requests = (int) get_transient($cache_key);

        if ($current_requests >= $max_requests) {
            // Rate limit aşıldı - log kaydet
            self::logRateLimitExceeded($identifier, $action, $timeframe, $current_requests, $max_requests);
            return false;
        }

        // Request sayısını artır
        set_transient($cache_key, $current_requests + 1, $duration);
        
        return true;
    }

    /**
     * Rate limit cache key oluştur
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param string $timeframe Zaman dilimi
     * @return string Cache key
     */
    private static function getCacheKey(string $identifier, string $action, string $timeframe): string
    {
        $hash = hash('sha256', $identifier);
        return "rate_limit_{$action}_{$timeframe}_{$hash}";
    }

    /**
     * Rate limit aşımını logla
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param string $timeframe Zaman dilimi
     * @param int $current_requests Mevcut request sayısı
     * @param int $max_requests Max request sayısı
     */
    private static function logRateLimitExceeded(string $identifier, string $action, string $timeframe, int $current_requests, int $max_requests): void
    {
        if (class_exists(\MHMRentiva\Logs\AdvancedLogger::class)) {
            \MHMRentiva\Logs\AdvancedLogger::warning("Rate limit exceeded", [
                'identifier' => $identifier,
                'action' => $action,
                'timeframe' => $timeframe,
                'current_requests' => $current_requests,
                'max_requests' => $max_requests,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip_address' => self::getClientIP()
            ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SECURITY);
        }
    }

    /**
     * Rate limit istatistiklerini al
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @return array Rate limit istatistikleri
     */
    public static function getStats(string $identifier, string $action = 'general'): array
    {
        $rate_limits = self::get_rate_limits();
        $limits = $rate_limits[$action] ?? $rate_limits['general'];
        
        return [
            'minute' => [
                'current' => (int) get_transient(self::getCacheKey($identifier, $action, 'minute')),
                'limit' => $limits['max_per_minute']
            ],
            'hour' => [
                'current' => (int) get_transient(self::getCacheKey($identifier, $action, 'hour')),
                'limit' => $limits['max_per_hour']
            ],
            'day' => [
                'current' => (int) get_transient(self::getCacheKey($identifier, $action, 'day')),
                'limit' => $limits['max_per_day']
            ]
        ];
    }

    /**
     * Rate limit'i temizle (admin için)
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @return bool Başarı durumu
     */
    public static function clear(string $identifier, string $action = 'general'): bool
    {
        $timeframes = ['minute', 'hour', 'day'];
        $success = true;

        foreach ($timeframes as $timeframe) {
            $cache_key = self::getCacheKey($identifier, $action, $timeframe);
            if (!delete_transient($cache_key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Rate limit'i reset et (belirli bir süre için)
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     * @param int $duration_seconds Reset süresi (saniye)
     * @return bool Başarı durumu
     */
    public static function reset(string $identifier, string $action = 'general', int $duration_seconds = 3600): bool
    {
        $timeframes = ['minute', 'hour', 'day'];
        $success = true;

        foreach ($timeframes as $timeframe) {
            $cache_key = self::getCacheKey($identifier, $action, $timeframe);
            if (!set_transient($cache_key, 0, $duration_seconds)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Client IP adresini al
     * 
     * @return string Client IP
     */
    public static function getClientIP(): string
    {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Rate limit middleware (REST API için)
     * 
     * @param string $action Action type
     * @return bool|WP_Error Rate limit durumu
     */
    public static function middleware(string $action = 'general')
    {
        $identifier = self::getClientIP();
        
        if (!self::check($identifier, $action)) {
            $stats = self::getStats($identifier, $action);
            
            return new \WP_Error(
                'rate_limit_exceeded',
                __('Çok fazla istek gönderildi. Lütfen daha sonra tekrar deneyin.', 'mhm-rentiva'),
                [
                    'status' => 429,
                    'retry_after' => 60, // 1 dakika
                    'rate_limit_stats' => $stats
                ]
            );
        }

        return true;
    }




    /**
     * Rate limit bilgilerini response header'ına ekle
     * 
     * @param string $identifier Client identifier
     * @param string $action Action type
     */
    public static function addResponseHeaders(string $identifier, string $action = 'general'): void
    {
        $stats = self::getStats($identifier, $action);
        
        header("X-RateLimit-Limit-Minute: {$stats['minute']['limit']}");
        header("X-RateLimit-Remaining-Minute: " . max(0, $stats['minute']['limit'] - $stats['minute']['current']));
        header("X-RateLimit-Reset-Minute: " . (time() + MINUTE_IN_SECONDS));
        
        header("X-RateLimit-Limit-Hour: {$stats['hour']['limit']}");
        header("X-RateLimit-Remaining-Hour: " . max(0, $stats['hour']['limit'] - $stats['hour']['current']));
        header("X-RateLimit-Reset-Hour: " . (time() + HOUR_IN_SECONDS));
        
        header("X-RateLimit-Limit-Day: {$stats['day']['limit']}");
        header("X-RateLimit-Remaining-Day: " . max(0, $stats['day']['limit'] - $stats['day']['current']));
        header("X-RateLimit-Reset-Day: " . (time() + DAY_IN_SECONDS));
    }

    /**
     * Rate limit konfigürasyonunu al
     * 
     * @param string $action Action type
     * @return array Rate limit konfigürasyonu
     */
    public static function getConfig(string $action = 'general'): array
    {
        $rate_limits = self::get_rate_limits();
        return $rate_limits[$action] ?? $rate_limits['general'];
    }

    /**
     * Rate limit konfigürasyonunu güncelle
     * 
     * @param string $action Action type
     * @param array $config Yeni konfigürasyon
     * @return bool Başarı durumu
     */
    public static function updateConfig(string $action, array $config): bool
    {
        $rate_limits = self::get_rate_limits();
        if (!isset($rate_limits[$action])) {
            return false;
        }

        // Bu method runtime'da config değiştirmek için
        // Gerçek implementasyonda config dosyasından okunmalı
        return true;
    }

    /**
     * Rate limit durumunu kontrol et (admin dashboard için)
     * 
     * @return array Genel rate limit durumu
     */
    public static function getGlobalStats(): array
    {
        global $wpdb;
        
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_rate_limit_%' 
             AND option_value > 0",
            ARRAY_A
        );

        $stats = [
            'total_active_limits' => count($transients),
            'actions' => [],
            'top_offenders' => []
        ];

        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient['option_name']);
            $parts = explode('_', $key);
            
            if (count($parts) >= 4) {
                $action = $parts[2];
                $timeframe = $parts[3];
                $identifier_hash = $parts[4];
                
                if (!isset($stats['actions'][$action])) {
                    $stats['actions'][$action] = [];
                }
                
                if (!isset($stats['actions'][$action][$timeframe])) {
                    $stats['actions'][$action][$timeframe] = 0;
                }
                
                $stats['actions'][$action][$timeframe]++;
            }
        }

        return $stats;
    }
}
