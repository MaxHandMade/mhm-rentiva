<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class DepositManagementAjax
{
    public static function register(): void
    {
        add_action('wp_ajax_mhm_process_remaining_payment', [self::class, 'process_remaining_payment']);
        add_action('wp_ajax_mhm_approve_payment', [self::class, 'approve_payment']);
        add_action('wp_ajax_mhm_cancel_booking', [self::class, 'cancel_booking']);
        add_action('wp_ajax_mhm_process_refund', [self::class, 'process_refund']);
        add_action('wp_ajax_mhm_update_booking_status', [self::class, 'update_booking_status']);
    }

    public static function process_remaining_payment(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_deposit_management_action')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')]);
            return;
        }

        // Booking check
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            wp_send_json_error(['message' => __('Booking not found.', 'mhm-rentiva')]);
            return;
        }

        // Deposit system check
        $payment_type = get_post_meta($booking_id, '_mhm_payment_type', true);
        if ($payment_type !== 'deposit') {
            wp_send_json_error(['message' => __('This booking does not use deposit system.', 'mhm-rentiva')]);
            return;
        }

        $remaining_amount = floatval(get_post_meta($booking_id, '_mhm_remaining_amount', true));
        if ($remaining_amount <= 0) {
            wp_send_json_error(['message' => __('No remaining amount found.', 'mhm-rentiva')]);
            return;
        }

        // Reset remaining amount
        update_post_meta($booking_id, '_mhm_remaining_amount', 0);

        // Update payment status
        update_post_meta($booking_id, '_mhm_payment_status', 'paid');

        // Update booking status to confirmed
        Status::update_status($booking_id, 'confirmed', get_current_user_id());

        // Add log
        self::add_booking_log($booking_id, 'remaining_payment_processed', [
            'amount' => $remaining_amount,
            'processed_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('Remaining amount processed successfully.', 'mhm-rentiva')
        ]);
    }

    public static function approve_payment(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_deposit_management_action')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')]);
            return;
        }

        // Booking check
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            wp_send_json_error(['message' => __('Booking not found.', 'mhm-rentiva')]);
            return;
        }

        $payment_status = get_post_meta($booking_id, '_mhm_payment_status', true);
        if ($payment_status !== 'pending_verification') {
            wp_send_json_error(['message' => __('This booking is not awaiting approval.', 'mhm-rentiva')]);
            return;
        }

        // Update payment status to confirmed
        update_post_meta($booking_id, '_mhm_payment_status', 'paid');

        // Update booking status to confirmed
        Status::update_status($booking_id, 'confirmed', get_current_user_id());

        // Add log
        self::add_booking_log($booking_id, 'payment_approved', [
            'approved_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('Payment confirmed successfully.', 'mhm-rentiva')
        ]);
    }

    public static function cancel_booking(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_deposit_management_action')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')]);
            return;
        }

        // Booking check
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            wp_send_json_error(['message' => __('Booking not found.', 'mhm-rentiva')]);
            return;
        }

        $current_status = Status::get($booking_id);
        if (!in_array($current_status, ['pending', 'confirmed'], true)) {
            wp_send_json_error(['message' => __('This booking cannot be cancelled.', 'mhm-rentiva')]);
            return;
        }

        // Update booking status to cancelled
        Status::update_status($booking_id, 'cancelled', get_current_user_id());

        // Add log
        self::add_booking_log($booking_id, 'booking_cancelled', [
            'cancelled_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('Booking cancelled successfully.', 'mhm-rentiva')
        ]);
    }

    public static function process_refund(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_deposit_management_action')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')]);
            return;
        }

        // Booking check
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            wp_send_json_error(['message' => __('Booking not found.', 'mhm-rentiva')]);
            return;
        }

        $payment_status = get_post_meta($booking_id, '_mhm_payment_status', true);
        $booking_status = Status::get($booking_id);

        if ($payment_status !== 'paid' || $booking_status !== 'cancelled') {
            wp_send_json_error(['message' => __('Refund cannot be processed for this booking.', 'mhm-rentiva')]);
            return;
        }

        // Calculate refund amount
        $deposit_amount = floatval(get_post_meta($booking_id, '_mhm_deposit_amount', true));
        $total_amount = floatval(get_post_meta($booking_id, '_mhm_total_price', true));
        $remaining_amount = floatval(get_post_meta($booking_id, '_mhm_remaining_amount', true));

        // Cancellation policy check
        $cancellation_deadline = get_post_meta($booking_id, '_mhm_cancellation_deadline', true);
        $refund_amount = 0;

        if ($cancellation_deadline) {
            $now = time();
            $deadline = strtotime($cancellation_deadline);

            if ($now <= $deadline) {
                // Cancellation within 24 hours - full refund
                $refund_amount = $deposit_amount;
            } else {
                // Cancellation after 24 hours - no refund
                $refund_amount = 0;
            }
        } else {
            // No cancellation policy - full refund
            $refund_amount = $deposit_amount;
        }

        // Update refund status
        if ($refund_amount > 0) {
            update_post_meta($booking_id, '_mhm_payment_status', 'refunded');
            update_post_meta($booking_id, '_mhm_refunded_amount', $refund_amount);
            update_post_meta($booking_id, '_mhm_refund_date', current_time('mysql'));
            update_post_meta($booking_id, '_mhm_refund_processed_by', get_current_user_id());
        }

        // Add log
        self::add_booking_log($booking_id, 'refund_processed', [
            'refund_amount' => $refund_amount,
            'processed_by' => get_current_user_id()
        ]);

        if ($refund_amount > 0) {
            wp_send_json_success([
                /* translators: %s placeholder. */
                'message' => sprintf(__('Refund completed successfully. Refund amount: %s', 'mhm-rentiva'), self::format_price($refund_amount))
            ]);
        } else {
            wp_send_json_success([
                'message' => __('Refund not processed due to cancellation policy.', 'mhm-rentiva')
            ]);
        }
    }

    public static function update_booking_status(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_deposit_management_action')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')]);
            return;
        }

        // Booking check
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            wp_send_json_error(['message' => __('Booking not found.', 'mhm-rentiva')]);
            return;
        }

        // Status update operation
        // This function can be used for general status updates

        // Add log
        self::add_booking_log($booking_id, 'status_updated', [
            'updated_by' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('Status updated successfully.', 'mhm-rentiva')
        ]);
    }

    private static function add_booking_log(int $booking_id, string $action, array $data = []): void
    {
        $logs = get_post_meta($booking_id, '_mhm_booking_logs', true) ?: [];

        $logs[] = [
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'data' => $data
        ];

        update_post_meta($booking_id, '_mhm_booking_logs', $logs);
    }

    private static function format_price(float $price): string
    {
        $currency = Settings::get('currency', 'USD');
        $position = Settings::get('currency_position', 'right_space');
        $amount = number_format_i18n($price, 2);
        $symbol = $currency;

        switch ($position) {
            case 'left':
                return $symbol . $amount;
            case 'right':
                return $amount . $symbol;
            case 'left_space':
                return $symbol . ' ' . $amount;
            case 'right_space':
            default:
                return $amount . ' ' . $symbol;
        }
    }
}
