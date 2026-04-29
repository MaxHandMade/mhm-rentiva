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

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
        $view = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        // phpcs:enable

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vendor Reports', 'mhm-rentiva') . '</h1>';

        if ($view > 0) {
            self::render_detail($view);
        } else {
            self::render_list();
        }

        echo '</div>';
    }

    // ----- LIST --------------------------------------------------------

    private static function render_list(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $status_filter  = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'open';
        $context_filter = isset($_GET['context']) ? sanitize_key($_GET['context']) : '';
        // phpcs:enable

        if ($status_filter !== '' && ! VendorReportStatus::is_valid($status_filter) && $status_filter !== 'all') {
            $status_filter = 'open';
        }

        $where  = [ '1=1' ];
        $params = [];

        if ($status_filter !== 'all' && VendorReportStatus::is_valid($status_filter)) {
            $where[]  = 'status = %s';
            $params[] = $status_filter;
        }

        if ($context_filter !== '' && VendorReportContext::is_valid($context_filter)) {
            $where[]  = 'context_type = %s';
            $params[] = $context_filter;
        }

        $where_sql = implode(' AND ', $where);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- $table = sanitized prefix+suffix; $where_sql composed from %s/%d placeholders bound via prepare(array).
        if (! empty($params)) {
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 100", $params)
            );
        } else {
            $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100");
        }
        // phpcs:enable

        $rows = is_array($rows) ? $rows : [];

        // Filter form
        $base_url = admin_url('admin.php?page=' . self::PAGE_SLUG);
        echo '<form method="get" style="margin: 16px 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';

        echo '<select name="status">';
        $statuses = [ 'all' => __('All statuses', 'mhm-rentiva') ] + array_combine(
            VendorReportStatus::all(),
            array_map([ self::class, 'status_label' ], VendorReportStatus::all())
        );
        foreach ($statuses as $key => $label) {
            echo '<option value="' . esc_attr( (string) $key) . '"';
            selected($status_filter, (string) $key);
            echo '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';

        echo '<select name="context">';
        echo '<option value="">' . esc_html__('All contexts', 'mhm-rentiva') . '</option>';
        foreach (VendorReportContext::all() as $ctx) {
            echo '<option value="' . esc_attr($ctx) . '"';
            selected($context_filter, $ctx);
            echo '>' . esc_html(self::context_label($ctx)) . '</option>';
        }
        echo '</select> ';

        submit_button(__('Filter', 'mhm-rentiva'), 'secondary', 'submit', false);
        echo '</form>';

        if (empty($rows)) {
            echo '<p>' . esc_html__('No reports found.', 'mhm-rentiva') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Vendor', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Context', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Title', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Created', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Action', 'mhm-rentiva') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $detail_url = add_query_arg('view', (int) $row->id, $base_url);
            $vendor     = get_userdata( (int) $row->vendor_id);
            $vendor_lbl = $vendor instanceof \WP_User ? $vendor->display_name . ' (#' . (int) $row->vendor_id . ')' : '#' . (int) $row->vendor_id;

            echo '<tr>';
            echo '<td>#' . esc_html( (string) $row->id) . '</td>';
            echo '<td>' . esc_html($vendor_lbl) . '</td>';
            echo '<td>' . esc_html(self::context_label( (string) $row->context_type)) . '</td>';
            echo '<td><a href="' . esc_url($detail_url) . '">' . esc_html( (string) $row->title) . '</a></td>';
            echo '<td><span class="mhm-vr-status mhm-vr-status--' . esc_attr( (string) $row->status) . '">' . esc_html(self::status_label( (string) $row->status)) . '</span></td>';
            echo '<td>' . esc_html(date_i18n('Y-m-d H:i', strtotime( (string) $row->created_at))) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($detail_url) . '">' . esc_html__('Open', 'mhm-rentiva') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    // ----- DETAIL ------------------------------------------------------

    private static function render_detail(int $report_id): void
    {
        $row = VendorReportRepository::find($report_id);
        if ($row === null) {
            echo '<p>' . esc_html__('Report not found.', 'mhm-rentiva') . '</p>';
            return;
        }

        $vendor     = get_userdata( (int) $row->vendor_id);
        $vendor_lbl = $vendor instanceof \WP_User
            ? $vendor->display_name . ' (' . $vendor->user_email . ')'
            : __('Unknown vendor', 'mhm-rentiva') . ' #' . (int) $row->vendor_id;

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">&larr; ' . esc_html__('Back to list', 'mhm-rentiva') . '</a></p>';

        echo '<div class="mhm-vr-detail" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;max-width:760px;">';

        echo '<h2 style="margin-top:0;">' . esc_html( (string) $row->title) . '</h2>';

        echo '<p><strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong> ' . esc_html(self::status_label( (string) $row->status));
        echo ' &middot; <strong>' . esc_html__('Vendor:', 'mhm-rentiva') . '</strong> ' . esc_html($vendor_lbl);
        echo ' &middot; <strong>' . esc_html__('Context:', 'mhm-rentiva') . '</strong> ' . esc_html(self::context_label( (string) $row->context_type));
        if ($row->context_id !== null && (string) $row->context_id !== '') {
            echo ' (#' . esc_html( (string) $row->context_id) . ')';
        }
        echo '</p>';

        echo '<p><strong>' . esc_html__('Submitted:', 'mhm-rentiva') . '</strong> ' . esc_html(date_i18n('Y-m-d H:i', strtotime( (string) $row->created_at))) . '</p>';

        echo '<h3>' . esc_html__('Description', 'mhm-rentiva') . '</h3>';
        echo '<div style="background:#f9fafb;padding:14px;border-radius:6px;white-space:pre-wrap;">' . esc_html( (string) $row->description) . '</div>';

        if ($row->admin_note !== null && (string) $row->admin_note !== '') {
            echo '<h3>' . esc_html__('Administrator Note', 'mhm-rentiva') . '</h3>';
            echo '<div style="background:#fff7e6;padding:14px;border-radius:6px;white-space:pre-wrap;border:1px solid #fde68a;">' . esc_html( (string) $row->admin_note) . '</div>';
        }

        if (! VendorReportStatus::is_terminal( (string) $row->status)) {
            self::render_action_form($report_id);
        } else {
            echo '<p style="color:#6b7280;font-style:italic;margin-top:24px;">' . esc_html__('This report is closed. No further action is possible.', 'mhm-rentiva') . '</p>';
        }

        echo '</div>';
    }

    private static function render_action_form(int $report_id): void
    {
        echo '<hr style="margin:24px 0;">';
        echo '<h3>' . esc_html__('Resolve this report', 'mhm-rentiva') . '</h3>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:540px;">';
        wp_nonce_field(self::NONCE_ACTION, '_mhm_vr_nonce');
        echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $report_id) . '">';

        echo '<p><label for="mhm-vr-admin-note"><strong>' . esc_html__('Administrator Note', 'mhm-rentiva') . '</strong></label></p>';
        echo '<textarea id="mhm-vr-admin-note" name="admin_note" rows="4" style="width:100%;" placeholder="' . esc_attr__('Optional — explain your decision so the vendor knows why.', 'mhm-rentiva') . '"></textarea>';

        echo '<p style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;">';
        echo '<button type="submit" name="action" value="mhm_vendor_report_resolve" class="button button-primary">' . esc_html__('Mark as Resolved', 'mhm-rentiva') . '</button>';
        echo '<button type="submit" name="action" value="mhm_vendor_report_reject" class="button">' . esc_html__('Reject', 'mhm-rentiva') . '</button>';
        echo '<button type="submit" name="action" value="mhm_vendor_report_in_review" class="button button-link">' . esc_html__('Mark In Review', 'mhm-rentiva') . '</button>';
        echo '</p>';

        echo '</form>';
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

    // ----- HELPERS -----------------------------------------------------

    private static function status_label(string $status): string
    {
        switch ($status) {
            case VendorReportStatus::OPEN:
                return __('Open', 'mhm-rentiva');
            case VendorReportStatus::IN_REVIEW:
                return __('In Review', 'mhm-rentiva');
            case VendorReportStatus::RESOLVED:
                return __('Resolved', 'mhm-rentiva');
            case VendorReportStatus::REJECTED:
                return __('Rejected', 'mhm-rentiva');
            default:
                return ucfirst($status);
        }
    }

    private static function context_label(string $context): string
    {
        switch ($context) {
            case VendorReportContext::BOOKING:
                return __('Booking', 'mhm-rentiva');
            case VendorReportContext::VEHICLE:
                return __('Vehicle', 'mhm-rentiva');
            case VendorReportContext::VEHICLE_ACTION:
                return __('Vehicle action', 'mhm-rentiva');
            case VendorReportContext::PENALTY:
                return __('Penalty appeal', 'mhm-rentiva');
            case VendorReportContext::GENERAL:
                return __('General', 'mhm-rentiva');
            default:
                return ucfirst($context);
        }
    }
}
