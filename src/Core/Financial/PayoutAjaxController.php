<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle AJAX requests for Payout actions in the Vendor Dashboard.
 */
class PayoutAjaxController
{
    public static function register(): void
    {
        add_action('wp_ajax_mhm_request_payout', array(self::class, 'handle_request_payout'));
    }

    public static function handle_request_payout(): void
    {
        check_ajax_referer('mhm_rentiva_vendor_nonce', 'nonce');

        if (! current_user_can('rentiva_vendor')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mhm-rentiva')), 403);
            exit;
        }

        $vendor_id = get_current_user_id();
        $requested_amount = isset($_POST['payout_amount']) ? (float) sanitize_text_field(wp_unslash((string) $_POST['payout_amount'])) : 0.0;

        // Perform fast validations before hitting the service logic
        if ($requested_amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid payout amount requested.', 'mhm-rentiva')), 400);
            exit;
        }

        if (PayoutService::vendor_has_pending_payout($vendor_id)) {
            wp_send_json_error(array('message' => __('You have a pending payout request. You cannot submit another until it is processed.', 'mhm-rentiva')), 400);
            exit;
        }

        $min_payout = PayoutService::get_minimum_payout_amount();
        if ($requested_amount < $min_payout) {
            wp_send_json_error(array(
                'message' => sprintf(
                    /* translators: %s: minimum payout amount */
                    __('Requested amount is below the minimum payout threshold of %s.', 'mhm-rentiva'),
                    $min_payout
                )
            ), 400);
            exit;
        }

        // Call Service
        $result = PayoutService::request_payout($vendor_id, $requested_amount);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        } else {
            wp_send_json_success(array(
                'message' => __('Your payout request has been submitted. We will process it shortly.', 'mhm-rentiva')
            ));
        }

        exit;
    }
}
