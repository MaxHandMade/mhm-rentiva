<?php declare(strict_types=1);

namespace MHMRentiva\REST\Payments\Helpers;

use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class ResponseHelper
{
    /**
     * Create error response
     */
    public static function err(string $code, string $msg, int $status): WP_REST_Response
    {
        return new WP_REST_Response(['ok' => false, 'code' => $code, 'message' => $msg], $status);
    }
}
