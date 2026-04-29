<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\VendorReport\Ajax\VendorReportAjaxHandler;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Report — frontend asset and modal injection.
 *
 * Loads the shared modal markup, CSS, and JS into vendor-facing pages so
 * any "Sorun Bildir" / "İtiraz Et" / withdrawal-reason trigger has the
 * modal ready. Gated by Mode::canUseVendorMarketplace() since the entire
 * report system is a Pro feature.
 *
 * Detection: vendor-facing pages set the `mhm_rentiva_vendor_panel_active`
 * flag via `do_action('mhm_rentiva_vendor_panel_loaded')`, but as a safety
 * net we also enqueue when the current user has the rentiva_vendor role
 * (the modal is invisible until triggered).
 *
 * @since 4.35.0
 */
final class VendorReportAssets {


    private const SCRIPT_HANDLE = 'mhm-vendor-report-modal';
    private const STYLE_HANDLE  = 'mhm-vendor-report-modal-css';

    private static bool $modal_printed = false;

    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [ self::class, 'enqueue' ]);
        add_action('wp_footer', [ self::class, 'maybe_print_modal' ], 5);
    }

    public static function enqueue(): void
    {
        if (! self::should_load()) {
            return;
        }

        $base    = defined('MHM_RENTIVA_PLUGIN_URL') ? rtrim( (string) constant('MHM_RENTIVA_PLUGIN_URL'), '/') . '/' : '';
        $dir     = defined('MHM_RENTIVA_PLUGIN_DIR') ? rtrim( (string) constant('MHM_RENTIVA_PLUGIN_DIR'), '/\\') . '/' : '';
        $version = defined('MHM_RENTIVA_VERSION') ? (string) constant('MHM_RENTIVA_VERSION') : '4.35.0';

        $css_rel = 'assets/css/frontend/vendor-report-modal.css';
        $js_rel  = 'assets/js/frontend/vendor-report-modal.js';

        if ($dir !== '' && file_exists($dir . $css_rel)) {
            $version_css = $version . '.' . filemtime($dir . $css_rel);
        } else {
            $version_css = $version;
        }
        if ($dir !== '' && file_exists($dir . $js_rel)) {
            $version_js = $version . '.' . filemtime($dir . $js_rel);
        } else {
            $version_js = $version;
        }

        wp_enqueue_style(self::STYLE_HANDLE, $base . $css_rel, [], $version_css);
        wp_enqueue_script(self::SCRIPT_HANDLE, $base . $js_rel, [], $version_js, true);

        wp_localize_script(self::SCRIPT_HANDLE, 'mhmVendorReportConfig', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'reportNonce'    => wp_create_nonce(VendorReportAjaxHandler::NONCE_ACTION),
            'lifecycleNonce' => wp_create_nonce('mhm_rentiva_vehicle_lifecycle'),
            'i18n'           => self::i18n_strings(),
        ]);
    }

    public static function maybe_print_modal(): void
    {
        if (self::$modal_printed || ! self::should_load()) {
            return;
        }

        $template = defined('MHM_RENTIVA_PLUGIN_DIR')
            ? rtrim( (string) constant('MHM_RENTIVA_PLUGIN_DIR'), '/\\') . '/templates/account/partials/vendor-report-modal.php'
            : '';

        if ($template === '' || ! file_exists($template)) {
            return;
        }

        include $template;
        self::$modal_printed = true;
    }

    /**
     * Should the report modal load on the current request?
     */
    private static function should_load(): bool
    {
        if (! Mode::canUseVendorMarketplace()) {
            return false;
        }

        if (! is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (! $user instanceof \WP_User) {
            return false;
        }

        $is_vendor = in_array('rentiva_vendor', (array) $user->roles, true);
        $is_admin  = in_array('administrator', (array) $user->roles, true);

        return $is_vendor || $is_admin;
    }

    /**
     * @return array<string, string>
     */
    private static function i18n_strings(): array
    {
        return [
            'reportBooking'          => __('Report a booking issue', 'mhm-rentiva'),
            'reportVehicle'          => __('Appeal vehicle action', 'mhm-rentiva'),
            'reportPenalty'          => __('Appeal Penalty', 'mhm-rentiva'),
            'reportGeneral'          => __('Contact Administrator', 'mhm-rentiva'),
            'reportSubtitle'         => __('Tell the administrator what happened. Your message goes only to the platform team.', 'mhm-rentiva'),
            'reportDescriptionLabel' => __('Describe the issue in detail...', 'mhm-rentiva'),
            'submitReport'           => __('Submit Report', 'mhm-rentiva'),
            'submitting'             => __('Submitting...', 'mhm-rentiva'),
            'submitted'              => __('Report submitted successfully', 'mhm-rentiva'),
            'descTooShort'           => __('Please describe the issue in at least 20 characters.', 'mhm-rentiva'),
            'genericError'           => __('An error occurred.', 'mhm-rentiva'),
            'networkError'           => __('Network error. Please try again.', 'mhm-rentiva'),
            'lifecycleDone'          => __('Done.', 'mhm-rentiva'),
            'withdrawTitle'          => __('Withdraw vehicle', 'mhm-rentiva'),
            'withdrawSubtitle'       => __('A penalty applies to vehicle withdrawals. Tell the administrator the reason — if accepted, the penalty will not apply.', 'mhm-rentiva'),
            'reasonForWithdrawal'    => __('Reason for withdrawal', 'mhm-rentiva'),
            'confirmWithdraw'        => __('Withdraw Vehicle', 'mhm-rentiva'),
            'withdrawalReasonTitle'  => __('Withdrawal reason', 'mhm-rentiva'),
            'pauseTitle'             => __('Pause vehicle', 'mhm-rentiva'),
            'pauseSubtitle'          => __('A penalty may apply to repeated pauses. Tell the administrator the reason — if accepted, the penalty will not apply.', 'mhm-rentiva'),
            'reasonForPausing'       => __('Reason for pausing', 'mhm-rentiva'),
            'confirmPause'           => __('Pause Vehicle', 'mhm-rentiva'),
            'pauseReasonTitle'       => __('Pause reason', 'mhm-rentiva'),
        ];
    }
}
