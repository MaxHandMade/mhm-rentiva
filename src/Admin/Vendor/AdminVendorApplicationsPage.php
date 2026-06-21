<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Licensing\Mode;



/**
 * Admin page for vendor management — fully React-driven SPA.
 * All 5 tabs (pending applications, active vendors, IBAN requests, commission, settings)
 * are rendered client-side via the React vendor-management bundle.
 */
final class AdminVendorApplicationsPage
{
    public static function register(): void
    {
        if (! Mode::canUseVendorMarketplace()) {
            return;
        }

        add_action('admin_enqueue_scripts', array(static::class, 'enqueue_assets'));
        add_action('admin_post_mhm_vendor_suspend',           array(static::class, 'handle_suspend_post'));
        add_action('admin_post_mhm_vendor_unsuspend',         array(static::class, 'handle_unsuspend_post'));
    }

    public static function add_submenu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Vendor Management', 'mhm-rentiva'),
            __('Vendor Management', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-vendors',
            array(static::class, 'render_page')
        );
    }

    public static function enqueue_assets(string $hook_suffix): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! isset($_GET['page']) || $_GET['page'] !== 'mhm-rentiva-vendors') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'pending';

        \MHMRentiva\Admin\Core\AssetManager::enqueue_react_page('vendor-management');

        wp_enqueue_style(
            'mhm-vendor-management',
            MHM_RENTIVA_PLUGIN_URL . 'build/admin/vendor-management.css',
            array(),
            filemtime(MHM_RENTIVA_PLUGIN_DIR . 'build/admin/vendor-management.css') ?: MHM_RENTIVA_VERSION
        );

        // Flash pattern: read URL params before WP's common.js strips them via history.replaceState.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $flash = null;
        if (isset($_GET['updated']) && '1' === $_GET['updated']) {
            $flash = array('type' => 'success', 'message' => __('Operation completed successfully.', 'mhm-rentiva'));
        } elseif (isset($_GET['error'])) {
            $flash = array('type' => 'error', 'message' => __('An error occurred. Please try again.', 'mhm-rentiva'));
        }
        // phpcs:enable

        wp_localize_script(
            'mhm-rentiva-react-vendor-management',
            'mhmRentivaVendorManagement',
            array(
                // phpcs:disable WordPress.Security.NonceVerification.Recommended
                'initialTab'       => $tab,
                'initialView'      => isset($_GET['view']) ? (int) $_GET['view'] : 0,
                // phpcs:enable
                'pendingIbanCount' => static::get_pending_iban_count(),
                'nonce'            => wp_create_nonce('wp_rest'),
                'adminUrl'         => admin_url(),
                'pageUrl'          => admin_url('admin.php?page=mhm-rentiva-vendors'),
                'payoutsUrl'       => admin_url('admin.php?page=mhm-rentiva-payouts'),
                'flash'            => $flash,
            )
        );
    }

    // ---------------------------------------------------------------
    // Main router
    // ---------------------------------------------------------------

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $tab  = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'pending';
        $view = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        // phpcs:enable

        $base_url           = admin_url('admin.php?page=mhm-rentiva-vendors');
        $pending_iban_count = static::get_pending_iban_count();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vendor Management', 'mhm-rentiva') . '</h1>';

        // Tab navigation — IBAN badge count is server-side only (passed via React props).
        $iban_title = __('IBAN Requests', 'mhm-rentiva');
        if ($pending_iban_count > 0) {
            $iban_title .= ' <span class="update-plugins count-' . esc_attr((string) $pending_iban_count) . '"><span class="plugin-count">' . esc_html((string) $pending_iban_count) . '</span></span>';
        }

        echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
        echo '<a href="' . esc_url($base_url . '&tab=pending') . '" class="nav-tab ' . ($tab === 'pending' || $view > 0 ? 'nav-tab-active' : '') . '">' . esc_html__('Pending Applications', 'mhm-rentiva') . '</a>';
        echo '<a href="' . esc_url($base_url . '&tab=vendors') . '" class="nav-tab ' . ($tab === 'vendors' ? 'nav-tab-active' : '') . '">' . esc_html__('Active Vendors', 'mhm-rentiva') . '</a>';
        echo '<a href="' . esc_url($base_url . '&tab=iban_requests') . '" class="nav-tab ' . ($tab === 'iban_requests' ? 'nav-tab-active' : '') . '">' . wp_kses_post($iban_title) . '</a>';
        echo '<a href="' . esc_url($base_url . '&tab=commission') . '" class="nav-tab ' . ($tab === 'commission' ? 'nav-tab-active' : '') . '">' . esc_html__('Commission', 'mhm-rentiva') . '</a>';
        echo '<a href="' . esc_url($base_url . '&tab=settings') . '" class="nav-tab ' . ($tab === 'settings' ? 'nav-tab-active' : '') . '">' . esc_html__('Settings', 'mhm-rentiva') . '</a>';
        echo '</nav>';

        echo '<div id="mhm-vendor-management-root"></div>';

        echo '</div>';
    }

    private static function get_pending_iban_count(): int
    {
        $query = new \WP_User_Query(array(
            'role'       => 'rentiva_vendor',
            'meta_key'   => '_rentiva_iban_change_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'fields'     => 'ID',
        ));
        return (int) $query->get_total();
    }




    // ---------------------------------------------------------------
    // POST action handlers
    // ---------------------------------------------------------------

    public static function handle_suspend_post(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $vendor_id   = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
        $nonce       = isset($_GET['_wpnonce'])  ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        // phpcs:enable

        if (! wp_verify_nonce($nonce, 'mhm_vendor_suspend_' . $vendor_id)) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        VendorOnboardingController::suspend($vendor_id);
        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=vendors&suspended=1'));
        exit;
    }

    public static function handle_unsuspend_post(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
        $nonce     = isset($_GET['_wpnonce'])  ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        // phpcs:enable

        if (! wp_verify_nonce($nonce, 'mhm_vendor_unsuspend_' . $vendor_id)) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        VendorOnboardingController::unsuspend($vendor_id);
        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=vendors&unsuspended=1'));
        exit;
    }


    // ---------------------------------------------------------------
    // Testable delegates (no redirect)
    // ---------------------------------------------------------------

    public static function process_approve(int $application_id)
    {
        return VendorOnboardingController::approve($application_id);
    }

    public static function process_reject(int $application_id, string $reason = '')
    {
        return VendorOnboardingController::reject($application_id, $reason);
    }

    public static function process_unsuspend(int $user_id): bool
    {
        return VendorOnboardingController::unsuspend($user_id);
    }
}
