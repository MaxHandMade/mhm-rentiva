<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Core\Financial\Ledger;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders the vendor ledger transactions grid supporting pagination and parameterized filtering natively without complex shortcode wrappers limiting inputs securely via GET processing.
 */
final class VendorLedger
{
    /**
     * Register hooks required by the shortcode.
     */
    public static function register(): void
    {
        add_shortcode('rentiva_vendor_ledger', array(self::class, 'render'));
    }

    /**
     * Render the transactions ledger.
     *
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts = array()): string
    {
        if (! is_user_logged_in()) {
            return '';
        }

        $vendor_id = get_current_user_id();

        // Ensure strictly Vendor contexts limit access safely
        if (! user_can($vendor_id, 'mhm_rentiva_vendor')) {
            return '<div class="mhm-rentiva-notice is-error">' . esc_html__('Access Denied. Only vendors can view the financial ledger.', 'mhm-rentiva') . '</div>';
        }

        $limit  = 15;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only queries over public grids perfectly permissible implicitly.
        $paged  = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $limit;

        // Extract filter constraints securely
        $filters = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pure read filters implicitly protected via sanitize functions below mapping strictly strings natively.
        $raw_get = $_GET;

        if (! empty($raw_get['filter_status']) && is_string($raw_get['filter_status'])) {
            $filters['status'] = sanitize_key($raw_get['filter_status']);
        }
        if (! empty($raw_get['filter_type']) && is_string($raw_get['filter_type'])) {
            $filters['type'] = sanitize_text_field($raw_get['filter_type']);
        }
        if (! empty($raw_get['date_from']) && is_string($raw_get['date_from'])) {
            $filters['date_from'] = sanitize_text_field($raw_get['date_from']);
        }
        if (! empty($raw_get['date_to']) && is_string($raw_get['date_to'])) {
            $filters['date_to'] = sanitize_text_field($raw_get['date_to']);
        }

        $entries = Ledger::get_entries($vendor_id, $filters, $limit, $offset);

        return self::render_template($entries, $filters, $paged, $limit);
    }

    /**
     * Generates HTML output parsing entry loops safely injecting isolated PHP nodes explicitly decoupling processing abstractions effectively.
     *
     * @param array<int, \stdClass> $entries
     * @param array<string, string> $filters
     */
    private static function render_template(array $entries, array $filters, int $paged, int $limit): string
    {
        ob_start();

        // Map template path allowing standard override overrides seamlessly
        $internal_path = apply_filters(
            'mhm_rentiva_template_path',
            MHM_RENTIVA_PLUGIN_DIR . 'templates/account/vendor-ledger.php',
            'account/vendor-ledger.php'
        );

        if (file_exists($internal_path)) {
            // Expose standard variables matching standard WP patterns securely
            $ledger_entries = $entries;
            $ledger_filters = $filters;
            $ledger_paged   = $paged;
            $ledger_limit   = $limit;

            include $internal_path;
        } else {
            echo '<div class="mhm-rentiva-notice is-error">' . esc_html__('Vendor Ledger template missing.', 'mhm-rentiva') . '</div>';
        }

        return (string) ob_get_clean();
    }
}
