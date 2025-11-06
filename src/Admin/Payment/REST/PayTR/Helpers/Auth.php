<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayTR\Helpers;

use MHMRentiva\Admin\REST\Helpers\AuthHelper;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

final class Auth
{
    /**
     * PayTR REST API isteği doğrulama
     * 
     * Verify request authenticity:
     * - Prefer WP REST nonce (X-WP-Nonce) if present and valid.
     * - Otherwise require mhm_nonce that verifies wp_verify_nonce with action "mhm_paytr_{booking_id}".
     *
     * @param WP_REST_Request $request REST request objesi
     * @param int $booking_id Rezervasyon ID'si
     * @return bool|WP_Error Başarılı ise true, hata ise WP_Error
     */
    public static function verifyAuth(WP_REST_Request $request, int $booking_id): bool|WP_Error
    {
        return AuthHelper::verifyAuth($request, $booking_id, 'paytr');
    }
    
    /**
     * Admin yetkisi kontrolü
     */
    public static function adminPermissionsCheck(WP_REST_Request $request): bool
    {
        return AuthHelper::adminPermissionsCheck($request);
    }
}
