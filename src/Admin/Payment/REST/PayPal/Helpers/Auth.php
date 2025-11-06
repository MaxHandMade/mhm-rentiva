<?php declare(strict_types=1);

namespace MHMRentiva\REST\PayPal\Helpers;

use MHMRentiva\Admin\REST\Helpers\AuthHelper;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

final class Auth
{
    /**
     * PayPal REST API isteği doğrulama
     */
    public static function verifyAuth(WP_REST_Request $request, int $booking_id): bool|WP_Error
    {
        return AuthHelper::verifyAuth($request, $booking_id, 'paypal');
    }

    /**
     * Admin yetkisi kontrolü
     */
    public static function adminPermissionsCheck(WP_REST_Request $request): bool
    {
        return AuthHelper::adminPermissionsCheck($request);
    }
}
