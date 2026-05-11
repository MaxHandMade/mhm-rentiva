<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Admin;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;
use MHMRentiva\Admin\VendorReport\Core\VendorReportService;
use MHMRentiva\Admin\VendorReport\Core\VendorReportStatus;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin "Vendor Reports" page — list + detail + resolve/reject.
 *
 * Submenu under MHM Rentiva. Gated by Mode::canUseVendorMarketplace() since
 * the vendor report system is part of the Pro vendor marketplace feature.
 *
 * Routes:
 *   ?page=mhm-rentiva-vendor-reports                  → list (filters + pagination)
 *   ?page=mhm-rentiva-vendor-reports&view=<id>        → single report detail
 *
 * Form actions (admin-post):
 *   mhm_vendor_report_resolve  → status='resolved' + admin_note
 *   mhm_vendor_report_reject   → status='rejected' + admin_note
 *   mhm_vendor_report_in_review → status='in_review'
 *
 * @since 4.35.0
 */
final class VendorReportsAdminPage {


    private const PAGE_SLUG    = 'mhm-rentiva-vendor-reports';
    private const NONCE_ACTION = 'mhm_vendor_report_admin';

    public static function register(): void
    {
        if (! Mode::canUseVendorMarketplace()) {
            return;
        }

        add_action('admin_menu', [ self::class, 'add_submenu' ]);
        add_action('admin_enqueue_scripts', [ self::class, 'enqueue_assets' ]);
        add_action('admin_post_mhm_vendor_report_resolve', [ self::class, 'handle_resolve' ]);
        add_action('admin_post_mhm_vendor_report_reject', [ self::class, 'handle_reject' ]);
        add_action('admin_post_mhm_vendor_report_in_review', [ self::class, 'handle_in_review' ]);
    }

    public static function add_submenu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Vendor Reports', 'mhm-rentiva'),
            __('Vendor Reports', 'mhm-rentiva'),
            'manage_options',
            self::PAGE_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    public static function enqueue_assets( string $hook_suffix ): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug check for admin hook matching.
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
            return;
        }

        \MHMRentiva\Admin\Core\AssetManager::enqueue_react_page( 'vendor-reports' );

        wp_enqueue_style(
            'mhm-vendor-reports',
            MHM_RENTIVA_PLUGIN_URL . 'build/admin/vendor-reports.css',
            array(),
            filemtime( MHM_RENTIVA_PLUGIN_DIR . 'build/admin/vendor-reports.css' ) ?: MHM_RENTIVA_VERSION
        );

        wp_localize_script(
            'mhm-rentiva-react-vendor-reports',
            'mhmRentivaVendorReports',
            array(
                'statuses'  => array(
                    VendorReportStatus::OPEN      => __( 'Open', 'mhm-rentiva' ),
                    VendorReportStatus::IN_REVIEW => __( 'In Review', 'mhm-rentiva' ),
                    VendorReportStatus::RESOLVED  => __( 'Resolved', 'mhm-rentiva' ),
                    VendorReportStatus::REJECTED  => __( 'Rejected', 'mhm-rentiva' ),
                ),
                'contexts'  => array(
                    VendorReportContext::BOOKING        => __( 'Booking', 'mhm-rentiva' ),
                    VendorReportContext::VEHICLE        => __( 'Vehicle', 'mhm-rentiva' ),
                    VendorReportContext::VEHICLE_ACTION => __( 'Vehicle action', 'mhm-rentiva' ),
                    VendorReportContext::PENALTY        => __( 'Penalty appeal', 'mhm-rentiva' ),
                    VendorReportContext::GENERAL        => __( 'General', 'mhm-rentiva' ),
                ),
                'nonces'    => array(
                    'action' => wp_create_nonce( self::NONCE_ACTION ),
                ),
                'admin_url' => admin_url(),
            )
        );
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'mhm-rentiva'));
        }
        ?>
        <div class="wrap mhm-rentiva-vendor-reports-wrap">
            <div id="mhm-vendor-reports-root"></div>
        </div>
        <?php
    }

    // ----- ACTIONS -----------------------------------------------------

    public static function handle_resolve(): void
    {
        self::dispatch_action('resolve');
    }

    public static function handle_reject(): void
    {
        self::dispatch_action('reject');
    }

    public static function handle_in_review(): void
    {
        self::dispatch_action('in_review');
    }

    private static function dispatch_action(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'mhm-rentiva'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        check_admin_referer(self::NONCE_ACTION, '_mhm_vr_nonce');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $report_id = isset($_POST['report_id']) ? (int) $_POST['report_id'] : 0;
        if ($report_id <= 0) {
            wp_die(esc_html__('Invalid report.', 'mhm-rentiva'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $admin_note = isset($_POST['admin_note']) ? sanitize_textarea_field(wp_unslash($_POST['admin_note'])) : '';
        $admin_note = trim($admin_note);
        $admin_note = $admin_note === '' ? null : $admin_note;

        $service  = new VendorReportService();
        $admin_id = (int) get_current_user_id();

        if ($action === 'resolve') {
            $result = $service->resolve_report($report_id, $admin_note, $admin_id);
        } elseif ($action === 'reject') {
            $result = $service->reject_report($report_id, $admin_note, $admin_id);
        } else {
            // in_review — direct repository update, no service-layer side effects.
            VendorReportRepository::update_status($report_id, VendorReportStatus::IN_REVIEW, $admin_note, $admin_id);
            $result = true;
        }

        $back = add_query_arg(
            [
				'page'                                     => self::PAGE_SLUG,
				'view'                                     => $report_id,
				is_wp_error($result) ? 'error' : 'updated' => '1',
			],
            admin_url('admin.php')
        );
        wp_safe_redirect($back);
        exit;
    }
}
