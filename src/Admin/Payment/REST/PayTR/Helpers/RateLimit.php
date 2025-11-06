<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayTR\Helpers;

use MHMRentiva\Admin\Core\Utilities\RateLimiter;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class RateLimit
{
    /**
     * PayTR için rate limiting kontrolü
     * 
     * Merkezi RateLimiter sınıfını kullanarak IP ve booking bazlı
     * rate limiting sağlar.
     * 
     * @param string $ip Client IP adresi
     * @param int $booking_id Rezervasyon ID'si
     * @return bool|WP_Error Rate limit durumu
     */
    public static function checkRateLimit(string $ip, int $booking_id): bool|WP_Error
    {
        return RateLimiter::checkPayTRLimit($ip, $booking_id);
    }
    
    /**
     * PayTR rate limit istatistiklerini al
     * 
     * @param string $ip Client IP adresi
     * @param int $booking_id Rezervasyon ID'si
     * @return array Rate limit istatistikleri
     */
    public static function getStats(string $ip, int $booking_id): array
    {
        return [
            'ip_stats' => RateLimiter::getStats($ip, 'payment_paytr_ip'),
            'booking_stats' => RateLimiter::getStats($ip . '_booking_' . $booking_id, 'payment_paytr_booking')
        ];
    }
    
    /**
     * PayTR rate limit'i temizle (admin için)
     * 
     * @param string $ip Client IP adresi
     * @param int $booking_id Rezervasyon ID'si
     * @return bool Başarı durumu
     */
    public static function clear(string $ip, int $booking_id): bool
    {
        $ipSuccess = RateLimiter::clear($ip, 'payment_paytr_ip');
        $bookingSuccess = RateLimiter::clear($ip . '_booking_' . $booking_id, 'payment_paytr_booking');
        
        return $ipSuccess && $bookingSuccess;
    }
}
