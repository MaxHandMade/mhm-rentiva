<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Core\Financial\PolicyRepository;



/**
 * Admin pages for vendor management:
 * - Faz A (React SPA): Pending Applications list + detail view, IBAN Requests
 * - Faz B (PHP-rendered): Active Vendors list, Commission, Settings
 */
final class AdminVendorApplicationsPage
{
    public static function register(): void
    {
        if (! Mode::canUseVendorMarketplace()) {
            return;
        }

        add_action('admin_menu',            array(static::class, 'add_submenu'));
        add_action('admin_enqueue_scripts', array(static::class, 'enqueue_assets'));
        add_action('admin_post_mhm_vendor_suspend',           array(static::class, 'handle_suspend_post'));
        add_action('admin_post_mhm_vendor_commission_update', array(static::class, 'handle_commission_update'));
        add_action('admin_post_mhm_vendor_settings_save',     array(static::class, 'handle_settings_save'));
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

        // React only needed for Faz A tabs. Faz B tabs (vendors/commission/settings) use PHP rendering.
        if (in_array($tab, ['vendors', 'commission', 'settings'], true)) {
            return;
        }

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

        // Tab nav — PHP renders all 5 tabs (IBAN badge count available server-side).
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

        // Content: Faz B tabs stay PHP-rendered; Faz A tabs use React SPA.
        if ($tab === 'vendors') {
            static::render_vendors_tab();
        } elseif ($tab === 'commission') {
            static::render_commission_tab();
        } elseif ($tab === 'settings') {
            static::render_settings_tab();
        } else {
            // Faz A: pending list, application detail (?view=ID), iban_requests.
            echo '<div id="mhm-vendor-management-root"></div>';
        }

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
            echo '<td>' . esc_html($approved ? wp_date(get_option('date_format'), strtotime($approved)) : '—') . '</td>';
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
    // Settings tab
    // ---------------------------------------------------------------

    private static function render_settings_tab(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $saved = isset($_GET['settings_saved']) && $_GET['settings_saved'] === '1';
        // phpcs:enable

        if ($saved) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'mhm-rentiva') . '</p></div>';
        }

        // Current values
        $min_payout      = (float) get_option('mhm_min_payout_amount', 100);
        $payout_freeze   = get_option('mhm_rentiva_global_payout_freeze', 'no');
        $min_photos      = (int) get_option('mhm_vehicle_min_photos', 4);
        $max_photos      = (int) get_option('mhm_vehicle_max_photos', 8);
        $doc_max_mb      = (int) get_option('mhm_vendor_doc_max_file_size_mb', 5);
        $min_year        = (int) get_option('mhm_vehicle_min_year', 1990);
        $bio_max_chars   = (int) get_option('mhm_vendor_bio_max_length', 400);
        $service_cities_raw = get_option('mhm_vendor_service_cities', '');
        $default_cities  = array('Istanbul', 'Ankara', 'Izmir', 'Antalya', 'Bursa', 'Adana', 'Konya', 'Other');
        $service_cities  = !empty($service_cities_raw)
            ? implode("\n", (array) maybe_unserialize($service_cities_raw))
            : implode("\n", $default_cities);

        echo '<h2>' . esc_html__('Vendor Marketplace Settings', 'mhm-rentiva') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('mhm_vendor_settings_save', '_wpnonce');
        echo '<input type="hidden" name="action" value="mhm_vendor_settings_save">';

        echo '<table class="form-table"><tbody>';

        // Payout freeze
        echo '<tr>';
        echo '<th><label>' . esc_html__('Global Payout Freeze', 'mhm-rentiva') . '</label></th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="payout_freeze" value="yes"' . checked('yes', $payout_freeze, false) . '> ';
        echo esc_html__('Freeze all vendor payout requests site-wide', 'mhm-rentiva') . '</label>';
        echo '</td></tr>';

        // Minimum payout amount
        echo '<tr>';
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '';
        $payout_label    = $currency_symbol !== ''
            ? sprintf(
                /* translators: %s: currency symbol */
                __('Minimum Payout Amount (%s)', 'mhm-rentiva'),
                $currency_symbol
            )
            : __('Minimum Payout Amount', 'mhm-rentiva');
        echo '<th><label for="min_payout">' . esc_html($payout_label) . '</label></th>';
        echo '<td><input type="number" id="min_payout" name="min_payout" value="' . esc_attr((string) $min_payout) . '" min="0" step="1" style="width:120px">
            <p class="description">' . esc_html__('Vendors must have at least this balance to request a payout.', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        // Min vehicle photos
        echo '<tr>';
        echo '<th><label for="min_photos">' . esc_html__('Min Vehicle Photos', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="number" id="min_photos" name="min_photos" value="' . esc_attr((string) $min_photos) . '" min="1" max="10" style="width:80px">
            <p class="description">' . esc_html__('Minimum number of photos required per vehicle listing.', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        // Max vehicle photos
        echo '<tr>';
        echo '<th><label for="max_photos">' . esc_html__('Max Vehicle Photos', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="number" id="max_photos" name="max_photos" value="' . esc_attr((string) $max_photos) . '" min="1" max="20" style="width:80px">
            <p class="description">' . esc_html__('Maximum number of photos per vehicle listing.', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        // Document file size limit
        echo '<tr>';
        echo '<th><label for="doc_max_mb">' . esc_html__('Document Upload Limit (MB)', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="number" id="doc_max_mb" name="doc_max_mb" value="' . esc_attr((string) $doc_max_mb) . '" min="1" max="50" style="width:80px">
            <p class="description">' . esc_html__('Maximum file size for vendor identity documents (ID, license, etc.).', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        // Minimum vehicle year
        echo '<tr>';
        echo '<th><label for="min_year">' . esc_html__('Minimum Vehicle Year', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="number" id="min_year" name="min_year" value="' . esc_attr((string) $min_year) . '" min="1900" max="' . esc_attr((string) (int) gmdate('Y')) . '" style="width:100px">
            <p class="description">' . esc_html__('Oldest vehicle year allowed in listings.', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        // Bio max length
        echo '<tr>';
        echo '<th><label for="bio_max">' . esc_html__('Vendor Bio Max Characters', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="number" id="bio_max" name="bio_max" value="' . esc_attr((string) $bio_max_chars) . '" min="50" max="2000" style="width:100px">
            <p class="description">' . esc_html__('Maximum character count for the vendor profile bio.', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        // Service cities
        echo '<tr>';
        echo '<th><label for="service_cities">' . esc_html__('Service Area Cities', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="service_cities" name="service_cities" rows="10" style="width:320px;font-family:monospace">' . esc_textarea($service_cities) . '</textarea>
            <p class="description">' . esc_html__('One city per line. Shown as checkboxes in the vendor application form.', 'mhm-rentiva') . '</p></td>';
        echo '</tr>';

        echo '</tbody></table>';
        echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__('Save Settings', 'mhm-rentiva') . '"></p>';
        echo '</form>';
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

    public static function handle_settings_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        // phpcs:enable

        if (!wp_verify_nonce($nonce, 'mhm_vendor_settings_save')) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        update_option('mhm_rentiva_global_payout_freeze', isset($_POST['payout_freeze']) ? 'yes' : 'no');
        update_option('mhm_min_payout_amount', max(0, (float) ($_POST['min_payout'] ?? 100)));
        update_option('mhm_vehicle_min_photos', max(1, min(10, (int) ($_POST['min_photos'] ?? 4))));
        update_option('mhm_vehicle_max_photos', max(1, min(20, (int) ($_POST['max_photos'] ?? 8))));
        update_option('mhm_vendor_doc_max_file_size_mb', max(1, min(50, (int) ($_POST['doc_max_mb'] ?? 5))));
        update_option('mhm_vehicle_min_year', max(1900, min((int) gmdate('Y'), (int) ($_POST['min_year'] ?? 1990))));
        update_option('mhm_vendor_bio_max_length', max(50, min(2000, (int) ($_POST['bio_max'] ?? 400))));

        $raw_cities = sanitize_textarea_field(wp_unslash($_POST['service_cities'] ?? ''));
        // phpcs:enable
        $cities_array = array_values(array_filter(array_map('trim', explode("\n", $raw_cities))));
        update_option('mhm_vendor_service_cities', $cities_array);

        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-vendors&tab=settings&settings_saved=1'));
        exit;
    }

    public static function process_approve(int $application_id)
    {
        return VendorOnboardingController::approve($application_id);
    }

    public static function process_reject(int $application_id, string $reason = '')
    {
        return VendorOnboardingController::reject($application_id, $reason);
    }
}
