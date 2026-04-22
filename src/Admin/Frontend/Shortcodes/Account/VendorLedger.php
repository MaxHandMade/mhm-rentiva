<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Ledger;



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
        $_ledger_user = get_userdata($vendor_id);
        if (!$_ledger_user || !in_array('rentiva_vendor', (array) $_ledger_user->roles, true)) {
            return '<div class="mhm-rentiva-notice is-error">' . esc_html__('Access Denied. Only vendors can view the financial ledger.', 'mhm-rentiva') . '</div>';
        }

        $limit  = 15;
        $paged  = max(1, self::get_query_int('paged', 1));
        $offset = ($paged - 1) * $limit;

        // Extract filter constraints securely
        $filters = array();
        $filter_status = self::get_query_key('filter_status');
        $filter_type   = self::get_query_text('filter_type');
        $date_from     = self::get_query_text('date_from');
        $date_to       = self::get_query_text('date_to');

        if ('' !== $filter_status) {
            $filters['status'] = $filter_status;
        }
        if ('' !== $filter_type) {
            $filters['type'] = $filter_type;
        }
        if ('' !== $date_from) {
            $filters['date_from'] = $date_from;
        }
        if ('' !== $date_to) {
            $filters['date_to'] = $date_to;
        }

        foreach (['date_from', 'date_to'] as $_date_key) {
            if (!empty($filters[$_date_key]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$_date_key])) {
                unset($filters[$_date_key]);
            }
        }

        $entries = Ledger::get_entries($vendor_id, $filters, $limit, $offset);

        return self::render_template(
            $entries,
            $filters,
            $paged,
            $limit,
            self::get_query_text('tab'),
            remove_query_arg(array('filter_status', 'filter_type', 'date_from', 'date_to', 'paged'))
        );
    }

    /**
     * Generates HTML output parsing entry loops safely injecting isolated PHP nodes explicitly decoupling processing abstractions effectively.
     *
     * @param array<int, \stdClass> $entries
     * @param array<string, string> $filters
     */
    private static function render_template(array $entries, array $filters, int $paged, int $limit, string $current_tab, string $reset_url): string
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
            $ledger_tab     = $current_tab;
            $ledger_reset_url = $reset_url;

            include $internal_path;
        } else {
            echo '<div class="mhm-rentiva-notice is-error">' . esc_html__('Vendor Ledger template missing.', 'mhm-rentiva') . '</div>';
        }

        return (string) ob_get_clean();
    }

    private static function get_query_text(string $key, string $default = ''): string
    {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
        if (!is_string($value)) {
            return $default;
        }

        return sanitize_text_field(wp_unslash($value));
    }

    private static function get_query_key(string $key, string $default = ''): string
    {
        $value = self::get_query_text($key, $default);

        return '' === $value ? $default : sanitize_key($value);
    }

    private static function get_query_int(string $key, int $default = 0): int
    {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
        if (!is_string($value)) {
            return $default;
        }

        return (int) absint(wp_unslash($value));
    }
}
