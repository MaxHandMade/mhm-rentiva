<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Ajax;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportService;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * AJAX endpoints for vendor report creation from the frontend panel.
 *
 * Single endpoint: `mhm_vendor_report_create`. Vendor panel JS sends
 * context_type + context_id + title + description + nonce. Service layer
 * validates and persists; this handler is a thin transport wrapper.
 *
 * Authentication: vendor role required + Pro license gate
 * (Mode::canUseVendorMarketplace) — vendor reports are part of the vendor
 * marketplace feature.
 *
 * @since 4.35.0
 */
final class VendorReportAjaxHandler {


    public const NONCE_ACTION = 'mhm_rentiva_vendor_report';

    public static function register(): void
    {
        add_action('wp_ajax_mhm_vendor_report_create', [ self::class, 'handle_create' ]);
    }

    public static function handle_create(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (! Mode::canUseVendorMarketplace()) {
            wp_send_json_error(
                [ 'message' => __('Vendor reports are a Pro feature.', 'mhm-rentiva') ],
                403
            );
            exit;
        }

        $current_user = wp_get_current_user();
        if (! $current_user instanceof \WP_User
            || ! in_array('rentiva_vendor', (array) $current_user->roles, true)
        ) {
            wp_send_json_error([ 'message' => __('Unauthorized.', 'mhm-rentiva') ], 403);
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer above already validated the nonce.
        $context_type = isset($_POST['context_type']) ? sanitize_key(wp_unslash($_POST['context_type'])) : '';
        if (! VendorReportContext::is_valid($context_type)) {
            wp_send_json_error([ 'message' => __('Unknown report context.', 'mhm-rentiva') ]);
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $context_id_raw = isset($_POST['context_id']) ? sanitize_text_field(wp_unslash($_POST['context_id'])) : '';
        $context_id     = $context_id_raw === '' ? null : $context_id_raw;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $description = isset($_POST['description'])
            ? sanitize_textarea_field(wp_unslash($_POST['description']))
            : '';

        $service = new VendorReportService();
        $result  = $service->create_report(
            (int) $current_user->ID,
            $context_type,
            $context_id,
            $title,
            $description
        );

        if (is_wp_error($result)) {
            $http_status = $result->get_error_code() === 'duplicate_open_report' ? 409 : 400;
            wp_send_json_error(
                [
                    'message' => $result->get_error_message(),
                    'code'    => $result->get_error_code(),
                ],
                $http_status
            );
            exit;
        }

        wp_send_json_success([
            'message'   => __('Your report has been sent to the administrator.', 'mhm-rentiva'),
            'report_id' => (int) $result,
        ]);
        exit;
    }
}
