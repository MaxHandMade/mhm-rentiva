<?php declare(strict_types=1);

namespace MHMRentiva\Admin\REST\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ SECURE TOKEN SYSTEM - JWT-like Güvenli Token Sistemi
 * 
 * Base64 decode güvenlik açığını gideren güvenli token sistemi
 */
final class SecureToken
{
    /**
     * Token süresi (varsayılan 24 saat)
     */
    private const DEFAULT_EXPIRY_HOURS = 24;
    
    /**
     * Güvenli token oluştur
     * 
     * @param array $payload Token içeriği
     * @param int $expiry_hours Token süresi (saat)
     * @return string Güvenli token
     */
    public static function create(array $payload, int $expiry_hours = self::DEFAULT_EXPIRY_HOURS): string
    {
        // Payload'a expiry ekle
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + ($expiry_hours * 3600); // Expiry
        $payload['iss'] = get_site_url(); // Issuer
        
        // Header oluştur
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
            'kid' => 'mhm-rentiva-v1'
        ];
        
        // Base64 URL encode
        $encoded_header = self::base64url_encode(json_encode($header));
        $encoded_payload = self::base64url_encode(json_encode($payload));
        
        // Signature oluştur
        $signature = hash_hmac('sha256', $encoded_header . '.' . $encoded_payload, self::get_secret_key(), true);
        $encoded_signature = self::base64url_encode($signature);
        
        return $encoded_header . '.' . $encoded_payload . '.' . $encoded_signature;
    }
    
    /**
     * Güvenli token doğrula
     * 
     * @param string $token Doğrulanacak token
     * @return array|null Doğrulanmış payload veya null
     */
    public static function verify(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }
        
        // Token parçalarını ayır
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        [$encoded_header, $encoded_payload, $encoded_signature] = $parts;
        
        // Signature doğrula
        $expected_signature = hash_hmac('sha256', $encoded_header . '.' . $encoded_payload, self::get_secret_key(), true);
        $expected_encoded_signature = self::base64url_encode($expected_signature);
        
        if (!hash_equals($expected_encoded_signature, $encoded_signature)) {
            return null;
        }
        
        // Payload decode et
        $payload = json_decode(self::base64url_decode($encoded_payload), true);
        if (!$payload) {
            return null;
        }
        
        // Süre kontrolü
        if (isset($payload['exp']) && time() > $payload['exp']) {
            return null;
        }
        
        // Issuer kontrolü
        if (isset($payload['iss']) && $payload['iss'] !== get_site_url()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Müşteri token oluştur
     * 
     * @param string $email Müşteri e-postası
     * @param string $name Müşteri adı
     * @param int $booking_id Rezervasyon ID'si
     * @param int $expiry_hours Token süresi
     * @return string Güvenli müşteri token'ı
     */
    public static function create_customer_token(string $email, string $name = '', int $booking_id = 0, int $expiry_hours = self::DEFAULT_EXPIRY_HOURS): string
    {
        $payload = [
            'email' => $email,
            'name' => $name,
            'booking_id' => $booking_id,
            'type' => 'customer',
            'version' => '1.0'
        ];
        
        return self::create($payload, $expiry_hours);
    }
    
    /**
     * Müşteri token doğrula
     * 
     * @param string $token Doğrulanacak token
     * @param string $post_type Post type (varsayılan: vehicle_booking)
     * @param string $email_meta_key Email meta key (varsayılan: _booking_customer_email)
     * @return array|null Doğrulanmış müşteri bilgileri veya null
     */
    public static function verify_customer_token(string $token, string $post_type = 'vehicle_booking', string $email_meta_key = '_booking_customer_email'): ?array
    {
        $payload = self::verify($token);
        if (!$payload || ($payload['type'] ?? '') !== 'customer') {
            return null;
        }
        
        $email = $payload['email'] ?? '';
        if (empty($email)) {
            return null;
        }
        
        // Müşteri kontrolü (rezervasyon yapmış mı?)
        global $wpdb;
        $customer_check = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             AND pm.meta_value = %s",
            $post_type,
            $email_meta_key,
            $email
        ));
        
        if (!$customer_check) {
            return null;
        }
        
        return [
            'email' => $email,
            'name' => $payload['name'] ?? '',
            'booking_id' => $payload['booking_id'] ?? 0,
            'token' => $token,
            'verified_at' => time()
        ];
    }
    
    /**
     * Token'dan email çıkar (güvenli olmayan, sadece okuma için)
     * 
     * @param string $token Token
     * @return string|null Email veya null
     */
    public static function extract_email(string $token): ?string
    {
        $payload = self::verify($token);
        return $payload['email'] ?? null;
    }
    
    /**
     * Token süresi kontrol et
     * 
     * @param string $token Token
     * @return bool Token geçerli mi?
     */
    public static function is_expired(string $token): bool
    {
        $payload = self::verify($token);
        return $payload === null;
    }
    
    /**
     * Token yenile
     * 
     * @param string $old_token Eski token
     * @param int $expiry_hours Yeni token süresi
     * @return string|null Yeni token veya null
     */
    public static function refresh(string $old_token, int $expiry_hours = self::DEFAULT_EXPIRY_HOURS): ?string
    {
        $payload = self::verify($old_token);
        if (!$payload) {
            return null;
        }
        
        // Eski payload'dan yeni token oluştur
        unset($payload['iat'], $payload['exp'], $payload['iss']);
        return self::create($payload, $expiry_hours);
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64url_decode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Gizli anahtarı al
     */
    private static function get_secret_key(): string
    {
        // Veritabanından anahtarı al
        $key = get_option('mhm_rentiva_secret_key');
        
        // Eğer anahtar yoksa oluştur (Fallback)
        if (!$key) {
            $key = wp_generate_password(64, true, true);
            update_option('mhm_rentiva_secret_key', $key);
        }

        // WordPress salt'ını kullan
        $wp_secret = defined('SECRET_KEY') ? SECRET_KEY : 'mhm-rentiva-fallback-key';
        return $key . '_' . $wp_secret;
    }
    
    /**
     * Token güvenlik bilgilerini al
     */
    public static function get_security_info(): array
    {
        return [
            'algorithm' => 'HS256',
            'key_derivation' => 'HMAC-SHA256',
            'signature_method' => 'hash_hmac',
            'encoding' => 'Base64URL',
            'timing_attack_protection' => 'hash_equals',
            'secret_source' => 'WordPress SECRET_KEY + custom key'
        ];
    }
}
