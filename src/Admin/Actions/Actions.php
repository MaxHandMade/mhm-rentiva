<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Actions;

use MHMRentiva\Maintenance\LogRetention;
use MHMRentiva\Admin\Payment\Refunds\Service as RefundService;

if (!defined('ABSPATH')) {
    exit;
}

final class Actions
{
    public static function register(): void
    {
        add_action('admin_post_mhm_rentiva_purge_logs', [self::class, 'purge_logs']);
        add_action('admin_notices', [self::class, 'notices']);
        add_action('admin_post_mhm_rentiva_refund_booking', [self::class, 'refund_booking']);
        add_action('wp_ajax_mhm_rentiva_create_my_account_page', [self::class, 'create_my_account_page']);
    }

    public static function refund_booking(): void
    {
        $bid = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

        // ✅ SECURITY: Granular permission check
        if (!self::checkGranularPermission('refund_booking', $bid)) {
            wp_die(__('You do not have permission for this action.', 'mhm-rentiva'));
        }

        check_admin_referer('mhm_rentiva_refund_booking');
        $amount = isset($_POST['amount_kurus']) ? (int) $_POST['amount_kurus'] : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field((string) $_POST['reason']) : '';
        $res = RefundService::process($bid, $amount, $reason);
        wp_safe_redirect(add_query_arg($res, get_edit_post_link($bid, '') ?: admin_url('edit.php?post_type=vehicle_booking')));
        exit;
    }

    public static function purge_logs(): void
    {
        // ✅ SECURITY: Granular permission check
        if (!self::checkGranularPermission('purge_logs')) {
            wp_die(__('You do not have permission for this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_purge_logs');

        $days = isset($_POST['days']) ? (int) $_POST['days'] : (int) get_option('mhm_rentiva_log_retention_days', 90);
        if ($days <= 0) $days = 90;
        $limit = (int) apply_filters('mhm_rentiva_log_purge_limit_manual', 1000);
        $deleted = LogRetention::purge($days, $limit);

        $ref = wp_get_referer();
        if (!$ref) {
            $ref = admin_url('options-general.php');
        }
        $url = add_query_arg([
            'mhm_purged' => '1',
            'mhm_purge_count' => (int) $deleted,
        ], $ref);
        wp_safe_redirect($url);
        exit;
    }

    public static function notices(): void
    {
        if (!is_admin()) return;
        // Refund result
        if (isset($_GET['mhm_refund']) && (string) $_GET['mhm_refund'] !== '') {
            $ok   = (string) $_GET['mhm_refund'] === '1';
            $msg  = isset($_GET['mhm_refund_msg']) ? sanitize_text_field((string) $_GET['mhm_refund_msg']) : '';
            $type = $ok ? 'success' : 'error';
            $base = $ok ? __('Refund processed.', 'mhm-rentiva') : __('Refund failed.', 'mhm-rentiva');
            $full = $msg ? $base . ' ' . $msg : $base;
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($full) . '</p></div>';
        }

        if (!isset($_GET['mhm_purged']) || (string) $_GET['mhm_purged'] !== '1') return;
        $count = isset($_GET['mhm_purge_count']) ? (int) $_GET['mhm_purge_count'] : 0;
        /* translators: %d placeholder. */
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d old records deleted.', 'mhm-rentiva'), (int) $count) . '</p></div>';
    }

    public static function create_my_account_page(): void
    {
        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
        }

        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_create_my_account_page')) {
            wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
        }

        // Check if page already exists
        $existing_page = get_page_by_path('my-account');
        if ($existing_page) {
            wp_send_json_error(__('My Account page already exists.', 'mhm-rentiva'));
        }

        // Create page
        $page_data = [
            'post_title' => __('My Account', 'mhm-rentiva'),
            'post_name' => 'my-account',
            'post_content' => '[rentiva_my_account]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        ];

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error(__('Error occurred while creating page: ', 'mhm-rentiva') . $page_id->get_error_message());
        }

        // Success message
        $page_url = get_permalink($page_id);
        $edit_url = get_edit_post_link($page_id);

        wp_send_json_success([
            'message' => __('My Account page created successfully!', 'mhm-rentiva'),
            'page_id' => $page_id,
            'page_url' => $page_url,
            'edit_url' => $edit_url,
        ]);
    }

    /**
     * ✅ SECURITY: Granular permission check
     * 
     * @param string $action Action type
     * @param int|null $resource_id Resource ID (optional)
     * @return bool Permission granted?
     */
    private static function checkGranularPermission(string $action, ?int $resource_id = null): bool
    {
        $user = wp_get_current_user();

        switch ($action) {
            case 'refund_booking':
                // Only admin or booking owner
                if (current_user_can('manage_options')) {
                    return true;
                }

                if ($resource_id) {
                    return self::user_owns_booking($user->ID, $resource_id);
                }

                return false;

            case 'purge_logs':
                // Only super admin
                return current_user_can('manage_options');

            case 'view_booking':
                // Admin, booking owner or authorized personnel
                if (current_user_can('manage_options') || current_user_can('edit_posts')) {
                    return true;
                }

                if ($resource_id) {
                    return self::user_owns_booking($user->ID, $resource_id);
                }

                return false;

            case 'edit_booking':
                // Only admin and authorized personnel
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'delete_booking':
                // Only super admin
                return current_user_can('manage_options');

            case 'export_data':
                // Admin and authorized personnel
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'manage_settings':
                // Only super admin
                return current_user_can('manage_options');

            case 'view_reports':
                // Admin and authorized personnel
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'manage_payments':
                // Only admin and authorized personnel
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'view_customers':
                // Admin, authorized personnel and booking owner
                if (current_user_can('manage_options') || current_user_can('edit_posts')) {
                    return true;
                }

                if ($resource_id) {
                    return self::user_owns_booking($user->ID, $resource_id);
                }

                return false;

            case 'create_my_account':
                // Admin and authorized personnel
                return current_user_can('manage_options') || current_user_can('edit_posts');

            default:
                // Default: manage_options capability required
                return current_user_can('manage_options');
        }
    }

    /**
     * Audit log for permission checks
     * 
     * @param string $action Action type
     * @param bool $granted Permission granted?
     * @param int|null $resource_id Resource ID
     */
    private static function logPermissionCheck(string $action, bool $granted, ?int $resource_id = null): void
    {
        if (class_exists(\MHMRentiva\Logs\AdvancedLogger::class)) {
            \MHMRentiva\Logs\AdvancedLogger::info(__('Permission check', 'mhm-rentiva'), [
                'action' => $action,
                'granted' => $granted,
                'resource_id' => $resource_id,
                'user_id' => get_current_user_id(),
                'user_caps' => wp_get_current_user()->allcaps,
                'ip_address' => self::get_client_ip(),
                'user_agent' => self::get_user_agent()
            ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SECURITY);
        }
    }

    /**
     * Get client IP address safely
     * 
     * @return string Client IP address
     */
    private static function get_client_ip(): string
    {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Get user agent safely
     * 
     * @return string User agent string
     */
    private static function get_user_agent(): string
    {
        return !empty($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : 'unknown';
    }

    /**
     * Get booking user ID with caching
     * 
     * @param int $booking_id Booking ID
     * @return int User ID
     */
    private static function get_booking_user_id(int $booking_id): int
    {
        static $cache = [];

        if (!isset($cache[$booking_id])) {
            $cache[$booking_id] = (int) get_post_meta($booking_id, '_mhm_user_id', true);
        }

        return $cache[$booking_id];
    }

    /**
     * Check if user owns the booking
     * 
     * @param int $user_id User ID
     * @param int $booking_id Booking ID
     * @return bool User owns booking?
     */
    private static function user_owns_booking(int $user_id, int $booking_id): bool
    {
        return self::get_booking_user_id($booking_id) === $user_id;
    }
}
