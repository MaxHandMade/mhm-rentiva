<?php declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ CLIENT UTILITIES - Merkezi İstemci Bilgileri Sınıfı
 * 
 * Tüm PostTypes sınıfları için ortak istemci bilgilerini merkezileştirir
 */
final class ClientUtilities
{
    /**
     * İstemci IP adresini güvenli şekilde alır
     * 
     * Proxy ve load balancer desteği ile
     */
    public static function get_client_ip(): string
    {
        // Proxy header'larını kontrol et
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',           // Proxy
            'HTTP_X_FORWARDED_FOR',     // Load balancer
            'HTTP_X_FORWARDED',         // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
            'HTTP_FORWARDED_FOR',       // Proxy
            'HTTP_FORWARDED',           // Proxy
            'REMOTE_ADDR'               // Direct connection
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // X-Forwarded-For birden fazla IP içerebilir (virgülle ayrılmış)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // IP adresini doğrula
                if (self::is_valid_ip($ip)) {
                    return sanitize_text_field($ip);
                }
            }
        }
        
        return 'unknown';
    }

    /**
     * User agent bilgisini güvenli şekilde alır
     */
    public static function get_user_agent(): string
    {
        return sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }

    /**
     * Referer bilgisini güvenli şekilde alır
     */
    public static function get_referer(): string
    {
        return esc_url_raw($_SERVER['HTTP_REFERER'] ?? '');
    }

    /**
     * İstemci bilgilerini toplu olarak alır
     */
    public static function get_client_info(): array
    {
        return [
            'ip_address' => self::get_client_ip(),
            'user_agent' => self::get_user_agent(),
            'referer' => self::get_referer(),
            'timestamp' => current_time('mysql'),
            'request_uri' => esc_url_raw($_SERVER['REQUEST_URI'] ?? ''),
            'request_method' => sanitize_text_field($_SERVER['REQUEST_METHOD'] ?? 'GET')
        ];
    }

    /**
     * IP adresinin geçerli olup olmadığını kontrol eder
     */
    private static function is_valid_ip(string $ip): bool
    {
        // IPv4 ve IPv6 desteği
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        
        // Private IP'ler de kabul edilebilir (local development için)
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        return false;
    }

    /**
     * IP adresini maskeleyerek gizlilik korur
     * 
     * @param string $ip IP adresi
     * @param int $mask_last_octets Son kaç oktet maskelenecek (default: 1)
     */
    public static function mask_ip(string $ip, int $mask_last_octets = 1): string
    {
        if ($ip === 'unknown') {
            return $ip;
        }

        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return $ip; // IPv6 veya geçersiz format
        }

        for ($i = count($parts) - $mask_last_octets; $i < count($parts); $i++) {
            $parts[$i] = 'xxx';
        }

        return implode('.', $parts);
    }

    /**
     * İstemci lokasyon bilgisini alır (IP tabanlı)
     * 
     * @return array ['country' => string, 'region' => string, 'city' => string]
     */
    public static function get_client_location(): array
    {
        $ip = self::get_client_ip();
        
        if ($ip === 'unknown' || self::is_private_ip($ip)) {
            return [
                'country' => 'unknown',
                'region' => 'unknown', 
                'city' => 'unknown'
            ];
        }

        // Geobytes API kullanımı (ücretsiz)
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city");
        
        if (is_wp_error($response)) {
            return [
                'country' => 'unknown',
                'region' => 'unknown',
                'city' => 'unknown'
            ];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['status'] === 'success') {
            return [
                'country' => sanitize_text_field($data['country'] ?? 'unknown'),
                'region' => sanitize_text_field($data['regionName'] ?? 'unknown'),
                'city' => sanitize_text_field($data['city'] ?? 'unknown')
            ];
        }

        return [
            'country' => 'unknown',
            'region' => 'unknown',
            'city' => 'unknown'
        ];
    }

    /**
     * IP adresinin private olup olmadığını kontrol eder
     */
    private static function is_private_ip(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Bot tespiti yapar
     */
    public static function is_bot(): bool
    {
        $user_agent = strtolower(self::get_user_agent());
        
        $bot_patterns = [
            'bot', 'crawler', 'spider', 'scraper', 'facebook', 'twitter',
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver'
        ];

        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
