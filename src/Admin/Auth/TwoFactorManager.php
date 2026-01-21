<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Auth;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Two-Factor Authentication Manager
 * 
 * Handles 2FA for customer accounts using TOTP (Time-based One-Time Password)
 * 
 * @since 4.0.0
 */
final class TwoFactorManager
{
    /**
     * Default 2FA time step in seconds
     */
    public const DEFAULT_TIME_STEP = 30;

    /**
     * Default secret length
     */
    public const DEFAULT_SECRET_LENGTH = 16;

    /**
     * Default code length
     */
    public const DEFAULT_CODE_LENGTH = 6;

    /**
     * Default QR code size
     */
    public const DEFAULT_QR_SIZE = '200x200';

    /**
     * Default QR API URL
     */
    public const DEFAULT_QR_API_URL = 'https://api.qrserver.com/v1/create-qr-code/';

    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Initialize 2FA management
     */
    public static function init(): void
    {
        add_action('wp_login', [self::class, 'check_2fa_requirement'], 10, 2);
        add_action('wp_ajax_mhm_rentiva_enable_2fa', [self::class, 'ajax_enable_2fa']);
        add_action('wp_ajax_mhm_rentiva_disable_2fa', [self::class, 'ajax_disable_2fa']);
        add_action('wp_ajax_mhm_rentiva_verify_2fa', [self::class, 'ajax_verify_2fa']);
    }

    /**
     * Check if 2FA is required after login
     */
    public static function check_2fa_requirement(string $user_login, \WP_User $user): void
    {
        $two_factor_enabled = SettingsCore::get('mhm_rentiva_customer_two_factor', '0');
        if ($two_factor_enabled !== '1') {
            return;
        }

        $user_2fa_enabled = get_user_meta($user->ID, 'mhm_2fa_enabled', true);
        if ($user_2fa_enabled !== '1') {
            return;
        }

        // Store user ID in session for 2FA verification
        if (!session_id()) {
            session_start();
        }
        $_SESSION['mhm_2fa_user_id'] = $user->ID;
        $_SESSION['mhm_2fa_required'] = true;

        // Redirect to 2FA verification page
        wp_redirect(add_query_arg('2fa_required', '1', wp_login_url()));
        exit;
    }

    /**
     * Generate 2FA secret for user
     */
    public static function generate_secret(int $user_id): string
    {
        $secret = self::generate_random_secret();
        update_user_meta($user_id, 'mhm_2fa_secret', $secret);
        return $secret;
    }

    /**
     * Enable 2FA for user
     */
    public static function enable_2fa(int $user_id, string $secret, string $verification_code): bool
    {
        if (!self::verify_totp_code($secret, $verification_code)) {
            return false;
        }

        update_user_meta($user_id, 'mhm_2fa_enabled', '1');
        update_user_meta($user_id, 'mhm_2fa_secret', $secret);
        update_user_meta($user_id, 'mhm_2fa_enabled_date', current_time('mysql'));

        return true;
    }

    /**
     * Disable 2FA for user
     */
    public static function disable_2fa(int $user_id): bool
    {
        delete_user_meta($user_id, 'mhm_2fa_enabled');
        delete_user_meta($user_id, 'mhm_2fa_secret');
        delete_user_meta($user_id, 'mhm_2fa_enabled_date');

        return true;
    }

    /**
     * Verify 2FA code
     */
    public static function verify_2fa_code(int $user_id, string $code): bool
    {
        $secret = get_user_meta($user_id, 'mhm_2fa_secret', true);
        if (!$secret) {
            return false;
        }

        return self::verify_totp_code($secret, $code);
    }

    /**
     * Verify TOTP code
     */
    private static function verify_totp_code(string $secret, string $code): bool
    {
        $time_step = apply_filters('mhm_rentiva_2fa_time_step', self::DEFAULT_TIME_STEP);
        $current_time = floor(time() / $time_step);

        // Check current time window and previous/next windows for clock drift
        for ($i = -1; $i <= 1; $i++) {
            $time = $current_time + $i;
            $expected_code = self::generate_totp_code($secret, $time);
            if (hash_equals($expected_code, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code
     */
    private static function generate_totp_code(string $secret, int $time): string
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        $code_length = apply_filters('mhm_rentiva_2fa_code_length', self::DEFAULT_CODE_LENGTH);
        return str_pad((string) $code, $code_length, '0', STR_PAD_LEFT);
    }

    /**
     * Generate random secret
     */
    private static function generate_random_secret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret_length = apply_filters('mhm_rentiva_2fa_secret_length', self::DEFAULT_SECRET_LENGTH);
        $secret = '';
        for ($i = 0; $i < $secret_length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /**
     * Get QR code URL for 2FA setup
     */
    public static function get_qr_code_url(int $user_id, string $secret): string
    {
        $user = get_userdata($user_id);
        $site_name = get_bloginfo('name');
        $issuer = apply_filters('mhm_rentiva_2fa_issuer', __('MHMRentiva', 'mhm-rentiva'));

        $otpauth_url = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($user->user_email),
            $secret,
            rawurlencode($issuer)
        );

        $qr_api_url = apply_filters('mhm_rentiva_qr_api_url', self::DEFAULT_QR_API_URL);
        $qr_size = apply_filters('mhm_rentiva_qr_size', self::DEFAULT_QR_SIZE);
        return $qr_api_url . '?size=' . $qr_size . '&data=' . urlencode($otpauth_url);
    }

    /**
     * Check if 2FA is enabled for user
     */
    public static function is_2fa_enabled(int $user_id): bool
    {
        return get_user_meta($user_id, 'mhm_2fa_enabled', true) === '1';
    }

    /**
     * AJAX handler for enabling 2FA
     */
    public static function ajax_enable_2fa(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Not logged in.', 'mhm-rentiva')]);
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_2fa_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        $user_id = get_current_user_id();
        $verification_code = self::sanitize_text_field_safe($_POST['code'] ?? '');

        if (empty($verification_code)) {
            wp_send_json_error(['message' => __('Verification code is required.', 'mhm-rentiva')]);
            return;
        }

        $secret = get_user_meta($user_id, 'mhm_2fa_secret', true);
        if (!$secret) {
            wp_send_json_error(['message' => __('2FA secret not found.', 'mhm-rentiva')]);
            return;
        }

        if (self::enable_2fa($user_id, $secret, $verification_code)) {
            wp_send_json_success(['message' => __('2FA enabled successfully.', 'mhm-rentiva')]);
        } else {
            wp_send_json_error(['message' => __('Invalid verification code.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX handler for disabling 2FA
     */
    public static function ajax_disable_2fa(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Not logged in.', 'mhm-rentiva')]);
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_2fa_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        $user_id = get_current_user_id();

        if (self::disable_2fa($user_id)) {
            wp_send_json_success(['message' => __('2FA disabled successfully.', 'mhm-rentiva')]);
        } else {
            wp_send_json_error(['message' => __('Failed to disable 2FA.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX handler for verifying 2FA
     */
    public static function ajax_verify_2fa(): void
    {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['mhm_2fa_user_id'])) {
            wp_send_json_error(['message' => __('2FA session not found.', 'mhm-rentiva')]);
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_2fa_verify_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        $user_id = $_SESSION['mhm_2fa_user_id'];
        $verification_code = self::sanitize_text_field_safe($_POST['code'] ?? '');

        if (empty($verification_code)) {
            wp_send_json_error(['message' => __('Verification code is required.', 'mhm-rentiva')]);
            return;
        }

        if (self::verify_2fa_code($user_id, $verification_code)) {
            // Clear 2FA session
            unset($_SESSION['mhm_2fa_user_id']);
            unset($_SESSION['mhm_2fa_required']);

            // Log user in
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            wp_send_json_success(['message' => __('2FA verification successful.', 'mhm-rentiva')]);
        } else {
            wp_send_json_error(['message' => __('Invalid verification code.', 'mhm-rentiva')]);
        }
    }
}

/**
 * Base32 decode function
 */
if (!function_exists('base32_decode')) {
    function base32_decode(string $data): string
    {
        $map = [
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 3,
            'E' => 4,
            'F' => 5,
            'G' => 6,
            'H' => 7,
            'I' => 8,
            'J' => 9,
            'K' => 10,
            'L' => 11,
            'M' => 12,
            'N' => 13,
            'O' => 14,
            'P' => 15,
            'Q' => 16,
            'R' => 17,
            'S' => 18,
            'T' => 19,
            'U' => 20,
            'V' => 21,
            'W' => 22,
            'X' => 23,
            'Y' => 24,
            'Z' => 25,
            '2' => 26,
            '3' => 27,
            '4' => 28,
            '5' => 29,
            '6' => 30,
            '7' => 31
        ];

        $data = strtoupper($data);
        $data = str_replace('=', '', $data);

        $result = '';
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            if (!isset($map[$char])) {
                continue;
            }

            $value = ($value << 5) | $map[$char];
            $bits += 5;

            if ($bits >= 8) {
                $result .= chr(($value >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }

        return $result;
    }
}
