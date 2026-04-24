<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public REST endpoint (v4.30.0+) that mhm-license-server v1.9.0+ calls
 * during activation to reverse-validate the client site.
 *
 * Flow:
 *   1. Server issues `wp_remote_get()` to /wp-json/mhm-rentiva-verify/v1/ping
 *      with `X-MHM-Challenge: {uuid}` header
 *   2. We respond with `HMAC-SHA256(challenge, PING_SECRET)`
 *   3. Server compares against its own HMAC; mismatch → activation rejected
 *
 * Defeats fake-activation scripts: an attacker who doesn't control the
 * claimed site cannot answer the challenge.
 */
final class VerifyEndpoint {

    public const NAMESPACE = 'mhm-rentiva-verify/v1';
    public const ROUTE     = '/ping';

    public static function register(): void
    {
        add_action('rest_api_init', [ self::class, 'register_route' ]);
    }

    public static function register_route(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'handle_ping' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_ping(WP_REST_Request $request): WP_REST_Response
    {
        $challenge = (string) $request->get_header('x-mhm-challenge');

        if ($challenge === '') {
            return new WP_REST_Response([
                'code'    => 'challenge_missing',
                'message' => __('X-MHM-Challenge header is required.', 'mhm-rentiva'),
            ], 400);
        }

        $secret = ClientSecrets::getPingSecret();
        if ($secret === '') {
            return new WP_REST_Response([
                'code'    => 'ping_secret_not_configured',
                'message' => __('Ping secret is not configured on this site.', 'mhm-rentiva'),
            ], 503);
        }

        return new WP_REST_Response([
            'challenge_response' => hash_hmac('sha256', $challenge, $secret),
            'site_url'           => home_url(),
            'product_slug'       => 'mhm-rentiva',
            'version'            => defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'unknown',
        ], 200);
    }
}
