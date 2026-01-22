<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Actions;

use MHMRentiva\Admin\PostTypes\Maintenance\LogRetention;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Payment\Refunds\Service as RefundService;

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain()
    {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../../languages/');
    }
    mhm_rentiva_load_textdomain();
}

final class Actions
{
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

        // ✅ SECURITY: Granular permission control
        if (!self::checkGranularPermission('refund_booking', $bid)) {
            wp_die(esc_html__('You do not have permission for this action.', 'mhm-rentiva'));
        }

        check_admin_referer('mhm_rentiva_refund_booking');
        $amount = isset($_POST['amount_kurus']) ? (int) $_POST['amount_kurus'] : 0;
        $reason = isset($_POST['reason']) ? self::sanitize_text_field_safe((string) $_POST['reason']) : '';
        $res = RefundService::process($bid, $amount, $reason);
        wp_safe_redirect(add_query_arg($res, get_edit_post_link($bid, '') ?: admin_url('edit.php?post_type=vehicle_booking')));
        exit;
    }

    public static function purge_logs(): void
    {
        // ✅ SECURITY: Granular permission control
        if (!self::checkGranularPermission('purge_logs')) {
            wp_die(esc_html__('You do not have permission for this action.', 'mhm-rentiva'));
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
            $msg  = isset($_GET['mhm_refund_msg']) ? self::sanitize_text_field_safe((string) $_GET['mhm_refund_msg']) : '';
            $type = $ok ? 'success' : 'error';
            $base = $ok ? esc_html__('Refund processed.', 'mhm-rentiva') : esc_html__('Refund failed.', 'mhm-rentiva');
            $full = $msg ? $base . ' ' . $msg : $base;
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($full) . '</p></div>';
        }

        if (!isset($_GET['mhm_purged']) || (string) $_GET['mhm_purged'] !== '1') return;
        $count = isset($_GET['mhm_purge_count']) ? (int) $_GET['mhm_purge_count'] : 0;
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d old records deleted.', 'mhm-rentiva'), (int) $count) . '</p></div>';
    }



    /**
     * ✅ SECURITY: Granular permission control
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
                    $booking_user_id = (int) get_post_meta($resource_id, '_mhm_user_id', true);
                    return $booking_user_id === $user->ID;
                }

                return false;

            case 'purge_logs':
                // Only super admin
                return current_user_can('manage_options');

            case 'view_booking':
                // Admin, booking owner or authorized staff
                if (current_user_can('manage_options') || current_user_can('edit_posts')) {
                    return true;
                }

                if ($resource_id) {
                    $booking_user_id = (int) get_post_meta($resource_id, '_mhm_user_id', true);
                    return $booking_user_id === $user->ID;
                }

                return false;

            case 'edit_booking':
                // Only admin and authorized staff
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'delete_booking':
                // Only super admin
                return current_user_can('manage_options');

            case 'export_data':
                // Admin and authorized staff
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'manage_settings':
                // Only super admin
                return current_user_can('manage_options');

            case 'view_reports':
                // Admin and authorized staff
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'manage_payments':
                // Only admin and authorized staff
                return current_user_can('manage_options') || current_user_can('edit_posts');

            case 'view_customers':
                // Admin, authorized staff and booking owner
                if (current_user_can('manage_options') || current_user_can('edit_posts')) {
                    return true;
                }

                if ($resource_id) {
                    $booking_user_id = (int) get_post_meta($resource_id, '_mhm_user_id', true);
                    return $booking_user_id === $user->ID;
                }

                return false;

            case 'create_my_account':
                // Admin and authorized staff
                return current_user_can('manage_options') || current_user_can('edit_posts');

            default:
                // Default: manage_options permission required
                return current_user_can('manage_options');
        }
    }

    /**
     * ✅ SECURITY: Audit log for permission checks
     * 
     * @param string $action Action type
     * @param bool $granted Permission granted?
     * @param int|null $resource_id Resource ID
     */
    private static function logPermissionCheck(string $action, bool $granted, ?int $resource_id = null): void
    {
        if (class_exists(AdvancedLogger::class)) {
            AdvancedLogger::info(__('Permission check', 'mhm-rentiva'), [
                'action' => $action,
                'granted' => $granted,
                'resource_id' => $resource_id,
                'user_id' => get_current_user_id(),
                'user_caps' => wp_get_current_user()->allcaps,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ], AdvancedLogger::CATEGORY_SECURITY);
        }
    }

    /**
     * ✅ SECURITY: Role-based access control
     * 
     * @param string $capability Required capability
     * @param int|null $resource_id Resource ID
     * @return bool Access granted?
     */
    private static function checkRoleBasedAccess(string $capability, ?int $resource_id = null): bool
    {
        $user = wp_get_current_user();

        // Super Admin - full access
        if (current_user_can('manage_options')) {
            return true;
        }

        // Editor - most access except sensitive operations
        if (current_user_can('edit_posts')) {
            $restricted_caps = ['delete_booking', 'manage_settings', 'purge_logs'];
            return !in_array($capability, $restricted_caps, true);
        }

        // Author - limited access
        if (current_user_can('edit_published_posts')) {
            $allowed_caps = ['view_booking', 'view_customers'];
            return in_array($capability, $allowed_caps, true);
        }

        // Subscriber - very limited access (own bookings only)
        if (current_user_can('read')) {
            if ($resource_id && in_array($capability, ['view_booking', 'view_customers'], true)) {
                $booking_user_id = (int) get_post_meta($resource_id, '_mhm_user_id', true);
                return $booking_user_id === $user->ID;
            }
        }

        return false;
    }
}
