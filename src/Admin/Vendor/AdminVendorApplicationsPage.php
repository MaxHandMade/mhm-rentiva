<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\PostType\VendorApplication;
use MHMRentiva\Core\Financial\PolicyRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin pages for vendor management:
 * - Pending Applications list + detail view with approve/reject
 * - Active Vendors list with suspend action
 */
final class AdminVendorApplicationsPage
{
    public static function register(): void
    {
        if (! Mode::canUseVendorMarketplace()) {
            return;
        }

        add_action('admin_menu', array(static::class, 'add_submenu'));
        add_action('admin_post_mhm_vendor_approve',            array(static::class, 'handle_approve_post'));
        add_action('admin_post_mhm_vendor_reject',             array(static::class, 'handle_reject_post'));
        add_action('admin_post_mhm_vendor_suspend',            array(static::class, 'handle_suspend_post'));
        add_action('admin_post_mhm_vendor_commission_update',  array(static::class, 'handle_commission_update'));
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

    // ---------------------------------------------------------------
    // Main router
    // ---------------------------------------------------------------

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $tab    = isset($_GET['tab'])    ? sanitize_key($_GET['tab'])    : 'pending';
        $view   = isset($_GET['view'])   ? (int) $_GET['view']           : 0;
        // phpcs:enable

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vendor Management', 'mhm-rentiva') . '</h1>';

        // Tab nav
        $base_url = admin_url('admin.php?page=mhm-rentiva-vendors');
        echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
        echo '<a href="' . esc_url($base_url . '&tab=pending') . '" class="nav-tab ' . ($tab === 'pending' ? 'nav-tab-active' : '') . '">' . esc_html__('Pending Applications', 'mhm-rentiva') . '</a>';
        echo '<a href="' . esc_url($base_url . '&tab=vendors') . '" class="nav-tab ' . ($tab === 'vendors' ? 'nav-tab-active' : '') . '">' . esc_html__('Active Vendors', 'mhm-rentiva') . '</a>';
        echo '<a href="' . esc_url($base_url . '&tab=commission') . '" class="nav-tab ' . ($tab === 'commission' ? 'nav-tab-active' : '') . '">' . esc_html__('Commission', 'mhm-rentiva') . '</a>';
        echo '</nav>';

        if ($tab === 'vendors') {
            static::render_vendors_tab();
        } elseif ($tab === 'commission') {
            static::render_commission_tab();
        } elseif ($view > 0) {
            static::render_application_detail($view);
        } else {
            static::render_pending_tab();
        }

        echo '</div>';
    }

    // ---------------------------------------------------------------
    // Pending Applications tab
    // ---------------------------------------------------------------

    private static function render_pending_tab(): void
    {
        $applications = get_posts(array(
            'post_type'      => VendorApplication::POST_TYPE,
            'post_status'    => VendorApplicationManager::STATUS_PENDING,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        if (empty($applications)) {
            echo '<p>' . esc_html__('No pending vendor applications.', 'mhm-rentiva') . '</p>';
            return;
        }

        $base = admin_url('admin.php?page=mhm-rentiva-vendors&tab=pending');

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Applicant', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Email', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('City', 'mhm-rentiva') . '</th>';
        echo '<th style="width:120px">' . esc_html__('Applied', 'mhm-rentiva') . '</th>';
        echo '<th style="width:160px">' . esc_html__('Actions', 'mhm-rentiva') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($applications as $app) {
            $author     = get_userdata((int) $app->post_author);
            $name       = $author ? $author->display_name : '#' . $app->post_author;
            $email      = $author ? $author->user_email : '—';
            $city       = (string) get_post_meta($app->ID, '_vendor_city', true);
            $detail_url = esc_url(add_query_arg('view', $app->ID, $base));

            echo '<tr>';
            echo '<td><a href="' . $detail_url . '"><strong>' . esc_html($name) . '</strong></a></td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($city) . '</td>';
            echo '<td>' . esc_html(get_the_date('d.m.Y', $app)) . '</td>';
            echo '<td><a href="' . $detail_url . '" class="button button-small">' . esc_html__('Review', 'mhm-rentiva') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    // ---------------------------------------------------------------
    // Application detail view
    // ---------------------------------------------------------------

    private static function render_application_detail(int $application_id): void
    {
        $app = get_post($application_id);
        if (! $app || $app->post_type !== VendorApplication::POST_TYPE) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Application not found.', 'mhm-rentiva') . '</p></div>';
            return;
        }

        $author   = get_userdata((int) $app->post_author);
        $name     = $author ? $author->display_name : '#' . $app->post_author;
        $email    = $author ? $author->user_email : '—';
        $phone    = (string) get_post_meta($application_id, '_vendor_phone', true);
        $city     = (string) get_post_meta($application_id, '_vendor_city', true);
        $bio      = (string) get_post_meta($application_id, '_vendor_profile_bio', true);
        $areas    = (array) get_post_meta($application_id, '_vendor_service_areas', true);
        $tax      = (string) get_post_meta($application_id, '_vendor_tax_number', true);

        $raw_iban = VendorApplicationManager::decrypt_iban(
            (string) get_post_meta($application_id, '_vendor_iban', true)
        );
        $masked_iban = strlen($raw_iban) > 4
            ? substr($raw_iban, 0, 2) . '** **** ' . substr($raw_iban, -4)
            : '—';

        $doc_id        = (int) get_post_meta($application_id, '_vendor_doc_id', true);
        $doc_license   = (int) get_post_meta($application_id, '_vendor_doc_license', true);
        $doc_address   = (int) get_post_meta($application_id, '_vendor_doc_address', true);
        $doc_insurance = (int) get_post_meta($application_id, '_vendor_doc_insurance', true);

        $back_url = esc_url(admin_url('admin.php?page=mhm-rentiva-vendors&tab=pending'));

        echo '<p><a href="' . $back_url . '">&larr; ' . esc_html__('Back to applications', 'mhm-rentiva') . '</a></p>';
        echo '<h2>' . esc_html(sprintf(__('Application: %s', 'mhm-rentiva'), $name)) . '</h2>';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__('Full Name', 'mhm-rentiva') . '</th><td>' . esc_html($name) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Email', 'mhm-rentiva') . '</th><td>' . esc_html($email) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Phone', 'mhm-rentiva') . '</th><td>' . esc_html($phone) . '</td></tr>';
        echo '<tr><th>' . esc_html__('City', 'mhm-rentiva') . '</th><td>' . esc_html($city) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Service Areas', 'mhm-rentiva') . '</th><td>' . esc_html(implode(', ', $areas)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('IBAN (masked)', 'mhm-rentiva') . '</th><td><code>' . esc_html($masked_iban) . '</code></td></tr>';
        echo '<tr><th>' . esc_html__('Tax Number', 'mhm-rentiva') . '</th><td>' . esc_html($tax ?: '—') . '</td></tr>';
        echo '<tr><th>' . esc_html__('Bio', 'mhm-rentiva') . '</th><td>' . nl2br(esc_html($bio)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Applied', 'mhm-rentiva') . '</th><td>' . esc_html(get_the_date('d.m.Y H:i', $app)) . '</td></tr>';
        echo '</tbody></table>';

        // Documents
        echo '<h3>' . esc_html__('Documents', 'mhm-rentiva') . '</h3>';
        echo '<table class="widefat fixed" style="max-width:600px"><tbody>';
        foreach (array(
            __('ID Document', 'mhm-rentiva')       => $doc_id,
            __('Driver\'s License', 'mhm-rentiva')  => $doc_license,
            __('Address Document', 'mhm-rentiva')   => $doc_address,
            __('Vehicle Insurance', 'mhm-rentiva')  => $doc_insurance,
        ) as $label => $attachment_id) {
            $url  = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
            $link = $url
                ? '<a href="' . esc_url($url) . '" target="_blank">' . esc_html__('View', 'mhm-rentiva') . '</a>'
                : '<em>' . esc_html__('Not uploaded', 'mhm-rentiva') . '</em>';
            echo '<tr><th style="width:200px">' . esc_html($label) . '</th><td>' . wp_kses($link, array('a' => array('href' => array(), 'target' => array()), 'em' => array())) . '</td></tr>';
        }
        echo '</tbody></table>';

        // Action forms
        echo '<div style="display:flex;gap:32px;margin-top:24px;flex-wrap:wrap">';

        // Approve form
        echo '<div style="flex:1;min-width:200px">';
        echo '<h3 style="color:#2e7d32">' . esc_html__('Approve Application', 'mhm-rentiva') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('mhm_vendor_approve_' . $application_id, '_wpnonce');
        echo '<input type="hidden" name="action" value="mhm_vendor_approve">';
        echo '<input type="hidden" name="application_id" value="' . esc_attr((string) $application_id) . '">';
        echo '<p>' . esc_html__('This will assign the rentiva_vendor role and notify the applicant.', 'mhm-rentiva') . '</p>';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Approve & Activate Vendor', 'mhm-rentiva') . '" onclick="return confirm(\'' . esc_js(__('Approve this vendor application?', 'mhm-rentiva')) . '\')">';
        echo '</form>';
        echo '</div>';

        // Reject form
        echo '<div style="flex:1;min-width:280px">';
        echo '<h3 style="color:#c62828">' . esc_html__('Reject Application', 'mhm-rentiva') . '</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('mhm_vendor_reject_' . $application_id, '_wpnonce');
        echo '<input type="hidden" name="action" value="mhm_vendor_reject">';
        echo '<input type="hidden" name="application_id" value="' . esc_attr((string) $application_id) . '">';
        echo '<p><label><strong>' . esc_html__('Rejection Reason (required):', 'mhm-rentiva') . '</strong></label></p>';
        echo '<textarea name="reason" rows="4" style="width:100%;max-width:400px" required placeholder="' . esc_attr__('Explain why this application is being rejected...', 'mhm-rentiva') . '"></textarea>';
        echo '<br><br><input type="submit" class="button button-secondary" value="' . esc_attr__('Reject Application', 'mhm-rentiva') . '">';
        echo '</form>';
        echo '</div>';

        echo '</div>';
    }

    // ---------------------------------------------------------------
    // Active Vendors tab
    // ---------------------------------------------------------------

    private static function render_vendors_tab(): void
    {
        $vendors = get_users(array(
            'role'    => 'rentiva_vendor',
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 100,
        ));

        echo '<h2>' . esc_html__('Active Vendors', 'mhm-rentiva') . '</h2>';

        if (empty($vendors)) {
            echo '<p>' . esc_html__('No active vendors yet.', 'mhm-rentiva') . '</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Name', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Email', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('City', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Service Areas', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Approved', 'mhm-rentiva') . '</th>';
        echo '<th style="width:120px">' . esc_html__('Action', 'mhm-rentiva') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($vendors as $vendor) {
            $city      = (string) get_user_meta($vendor->ID, '_rentiva_vendor_city', true);
            $areas     = (array)  get_user_meta($vendor->ID, '_rentiva_vendor_service_areas', true);
            $approved  = (string) get_user_meta($vendor->ID, '_rentiva_vendor_approved_at', true);
            $status    = (string) get_user_meta($vendor->ID, '_rentiva_vendor_status', true);

            $suspend_url = wp_nonce_url(
                admin_url('admin-post.php?action=mhm_vendor_suspend&vendor_id=' . $vendor->ID),
                'mhm_vendor_suspend_' . $vendor->ID
            );

            $badge = $status === 'suspended'
                ? '<span style="color:#c62828;font-weight:bold">' . esc_html__('Suspended', 'mhm-rentiva') . '</span>'
                : '<span style="color:#2e7d32">' . esc_html__('Active', 'mhm-rentiva') . '</span>';

            echo '<tr>';
            echo '<td>' . esc_html($vendor->display_name) . ' ' . wp_kses($badge, array('span' => array('style' => array()))) . '</td>';
            echo '<td>' . esc_html($vendor->user_email) . '</td>';
            echo '<td>' . esc_html($city) . '</td>';
            echo '<td>' . esc_html(implode(', ', $areas)) . '</td>';
            echo '<td>' . esc_html($approved ? wp_date('d.m.Y', strtotime($approved)) : '—') . '</td>';
            echo '<td>';
            if ($status !== 'suspended') {
                echo '<a href="' . esc_url($suspend_url) . '" class="button button-small" onclick="return confirm(\'' . esc_js(__('Suspend this vendor?', 'mhm-rentiva')) . '\')">' . esc_html__('Suspend', 'mhm-rentiva') . '</a>';
            } else {
                echo '<em>' . esc_html__('Suspended', 'mhm-rentiva') . '</em>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    // ---------------------------------------------------------------
    // Commission tab
    // ---------------------------------------------------------------

    private static function render_commission_tab(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $saved = isset($_GET['commission_saved']) && $_GET['commission_saved'] === '1';
        // phpcs:enable

        $current_rate = PolicyRepository::get_current_global_rate();

        echo '<h2>' . esc_html__('Platform Commission Rate', 'mhm-rentiva') . '</h2>';

        if ($saved) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Commission rate updated successfully.', 'mhm-rentiva') . '</p></div>';
        }

        echo '<p>' . esc_html__('Set the global commission percentage applied to all vendor payouts. This creates a new policy record effective immediately. Previous rates are preserved in history for audit purposes.', 'mhm-rentiva') . '</p>';

        if ($current_rate !== null) {
            echo '<p><strong>' . esc_html__('Current active rate:', 'mhm-rentiva') . '</strong> ' . esc_html(number_format($current_rate, 2)) . '%</p>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('No active commission policy found. Set one below.', 'mhm-rentiva') . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:400px;margin-top:20px">';
        wp_nonce_field('mhm_vendor_commission_update', '_wpnonce');
        echo '<input type="hidden" name="action" value="mhm_vendor_commission_update">';

        echo '<table class="form-table"><tbody>';
        echo '<tr>';
        echo '<th><label for="mhm-commission-rate">' . esc_html__('New Commission Rate (%)', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<input type="number" id="mhm-commission-rate" name="global_rate" min="0" max="100" step="0.01" required style="width:100px" placeholder="15.00">';
        echo ' <span class="description">%</span>';
        echo '<p class="description">' . esc_html__('Enter a value between 0 and 100. E.g. 15 = 15%.', 'mhm-rentiva') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="mhm-commission-label">' . esc_html__('Label (optional)', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="mhm-commission-label" name="policy_label" style="width:280px" placeholder="' . esc_attr__('e.g. Q1 2026 standard rate', 'mhm-rentiva') . '">';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';

        echo '<p class="submit">';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Save Commission Rate', 'mhm-rentiva') . '">';
        echo '</p>';
        echo '</form>';
    }

    // ---------------------------------------------------------------
    // POST action handlers
    // ---------------------------------------------------------------

    public static function handle_approve_post(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $application_id = isset($_POST['application_id']) ? (int) $_POST['application_id'] : 0;
        $nonce          = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        // phpcs:enable

        if (! wp_verify_nonce($nonce, 'mhm_vendor_approve_' . $application_id)) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        static::process_approve($application_id);
        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=pending&approved=1'));
        exit;
    }

    public static function handle_reject_post(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $application_id = isset($_POST['application_id']) ? (int) $_POST['application_id'] : 0;
        $nonce          = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        $reason         = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';
        // phpcs:enable

        if (! wp_verify_nonce($nonce, 'mhm_vendor_reject_' . $application_id)) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        static::process_reject($application_id, $reason);
        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=pending&rejected=1'));
        exit;
    }

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

    public static function handle_commission_update(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        // phpcs:enable

        if (! wp_verify_nonce($nonce, 'mhm_vendor_commission_update')) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $rate  = isset($_POST['global_rate'])   ? (float) $_POST['global_rate']                                   : -1.0;
        $label = isset($_POST['policy_label'])  ? sanitize_text_field(wp_unslash($_POST['policy_label']))         : '';
        // phpcs:enable

        if ($rate < 0.0 || $rate > 100.0) {
            wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=commission&error=invalid_rate'));
            exit;
        }

        PolicyRepository::insert_global_policy($rate, $label);
        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=commission&commission_saved=1'));
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
}
