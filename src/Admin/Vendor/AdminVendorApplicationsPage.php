<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin page that lists pending vendor applications and allows approve/reject actions.
 */
final class AdminVendorApplicationsPage
{
    /**
     * Register admin_menu hook only when the vendor marketplace is available.
     */
    public static function register(): void
    {
        if (! Mode::canUseVendorMarketplace()) {
            return;
        }

        add_action('admin_menu', array(static::class, 'add_submenu'));
    }

    /**
     * Add submenu page under the mhm-rentiva top-level menu.
     */
    public static function add_submenu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Vendor Applications', 'mhm-rentiva'),
            __('Vendor Applications', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-vendor-applications',
            array(static::class, 'render_page')
        );
    }

    /**
     * Render the admin vendor applications list page.
     */
    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'mhm-rentiva'));
        }

        // Handle approve/reject form submissions before rendering.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonces verified inside handle methods
        $action         = isset($_GET['mhm_action']) ? sanitize_key($_GET['mhm_action']) : '';
        $application_id = isset($_GET['application_id']) ? (int) $_GET['application_id'] : 0;
        $nonce_value    = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        // phpcs:enable

        if ($action === 'approve' && $application_id > 0) {
            static::handle_approval_request($application_id, $nonce_value);
        } elseif ($action === 'reject' && $application_id > 0) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $reason = isset($_GET['reason']) ? sanitize_textarea_field(wp_unslash($_GET['reason'])) : '';
            static::handle_rejection_request($application_id, $nonce_value, $reason);
        }

        $applications = get_posts(array(
            'post_type'      => VendorApplication::POST_TYPE,
            'post_status'    => VendorApplicationManager::STATUS_PENDING,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vendor Applications', 'mhm-rentiva') . '</h1>';

        if (empty($applications)) {
            echo '<p>' . esc_html__('No pending vendor applications.', 'mhm-rentiva') . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Applicant', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('City', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('IBAN (masked)', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Date', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Actions', 'mhm-rentiva') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($applications as $app) {
                $author  = get_userdata((int) $app->post_author);
                $name    = $author ? $author->display_name : '#' . $app->post_author;
                $city    = get_post_meta($app->ID, '_vendor_city', true);
                $status  = get_post_meta($app->ID, '_vendor_status', true);

                $raw_iban = VendorApplicationManager::decrypt_iban(
                    (string) get_post_meta($app->ID, '_vendor_iban', true)
                );
                $masked = strlen($raw_iban) > 4
                    ? substr($raw_iban, 0, 2) . '** **** ' . substr($raw_iban, -4)
                    : '—';

                $approve_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'page'           => 'mhm-rentiva-vendor-applications',
                            'mhm_action'     => 'approve',
                            'application_id' => $app->ID,
                        ),
                        admin_url('admin.php')
                    ),
                    'mhm_vendor_approve_' . $app->ID
                );

                $reject_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'page'           => 'mhm-rentiva-vendor-applications',
                            'mhm_action'     => 'reject',
                            'application_id' => $app->ID,
                            'reason'         => urlencode(__('Application does not meet requirements.', 'mhm-rentiva')),
                        ),
                        admin_url('admin.php')
                    ),
                    'mhm_vendor_reject_' . $app->ID
                );

                echo '<tr>';
                echo '<td>' . esc_html($name) . '</td>';
                echo '<td>' . esc_html((string) $city) . '</td>';
                echo '<td>' . esc_html($masked) . '</td>';
                echo '<td>' . esc_html(get_the_date('Y-m-d', $app)) . '</td>';
                echo '<td>' . esc_html((string) $status) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($approve_url) . '" class="button button-primary">' . esc_html__('Approve', 'mhm-rentiva') . '</a> ';
                echo '<a href="' . esc_url($reject_url) . '" class="button button-secondary">' . esc_html__('Reject', 'mhm-rentiva') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Verify nonce and capability, then run process_approve().
     *
     * @param int    $application_id Application post ID.
     * @param string $nonce_value    Raw nonce value from the request.
     */
    public static function handle_approval_request(int $application_id, string $nonce_value): void
    {
        if (
            ! current_user_can('manage_options') ||
            ! wp_verify_nonce($nonce_value, 'mhm_vendor_approve_' . $application_id)
        ) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        static::process_approve($application_id);
    }

    /**
     * Verify nonce and capability, then run process_reject().
     *
     * @param int    $application_id Application post ID.
     * @param string $nonce_value    Raw nonce value from the request.
     * @param string $reason         Admin rejection note.
     */
    public static function handle_rejection_request(int $application_id, string $nonce_value, string $reason = ''): void
    {
        if (
            ! current_user_can('manage_options') ||
            ! wp_verify_nonce($nonce_value, 'mhm_vendor_reject_' . $application_id)
        ) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        static::process_reject($application_id, $reason);
    }

    /**
     * Approve a vendor application. Testable — no redirect.
     *
     * @param int $application_id Application post ID.
     * @return true|\WP_Error
     */
    public static function process_approve(int $application_id)
    {
        return VendorOnboardingController::approve($application_id);
    }

    /**
     * Reject a vendor application. Testable — no redirect.
     *
     * @param int    $application_id Application post ID.
     * @param string $reason         Admin rejection note.
     * @return true|\WP_Error
     */
    public static function process_reject(int $application_id, string $reason = '')
    {
        return VendorOnboardingController::reject($application_id, $reason);
    }
}
