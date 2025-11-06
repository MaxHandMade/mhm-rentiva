<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Offline\API;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\PostTypes\Logs\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class Verification
{
    /**
     * Makbuz doğrulama işlemini gerçekleştirir
     */
    public static function verifyReceipt(int $bookingId, string $decision): array
    {
        // Booking doğrulama
        if ($bookingId <= 0 || get_post_type($bookingId) !== 'vehicle_booking') {
            return [
                'success' => false,
                'message' => __('Invalid booking', 'mhm-rentiva'),
                'redirect' => home_url('/')
            ];
        }

        // Yetki kontrolü
        if (!current_user_can('edit_post', $bookingId)) {
            return [
                'success' => false,
                'message' => __('You do not have permission to verify payments', 'mhm-rentiva'),
                'redirect' => home_url('/')
            ];
        }

        // Karar doğrulama
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return [
                'success' => false,
                'message' => __('Invalid decision', 'mhm-rentiva'),
                'redirect' => get_edit_post_link($bookingId, '')
            ];
        }

        // Makbuz ID'sini al
        $receiptId = get_post_meta($bookingId, '_mhm_offline_receipt_id', true);
        if (empty($receiptId)) {
            return [
                'success' => false,
                'message' => __('Receipt not found', 'mhm-rentiva'),
                'redirect' => get_edit_post_link($bookingId, '')
            ];
        }

        // Karara göre işlem yap
        if ($decision === 'approve') {
            $result = self::approveReceipt($bookingId);
        } else {
            $result = self::rejectReceipt($bookingId);
        }

        // Hook tetikle
        do_action('mhm_rentiva_offline_verified', $bookingId, $decision);

        Logger::info('Offline makbuz doğrulandı: Booking ' . $bookingId . ' - Karar: ' . $decision);

        return [
            'success' => true,
            'message' => $result['message'],
            'decision' => $decision,
            'redirect' => get_edit_post_link($bookingId, '')
        ];
    }

    /**
     * Makbuzu onaylar
     */
    private static function approveReceipt(int $bookingId): array
    {
        // Ödeme durumunu güncelle
        update_post_meta($bookingId, '_mhm_payment_status', 'paid');

        // Rezervasyon durumunu kontrol et ve güncelle
        $currentStatus = (string) get_post_meta($bookingId, '_mhm_status', true);
        if ($currentStatus !== 'confirmed') {
            Status::update_status($bookingId, 'confirmed', get_current_user_id() ?: 0);
        }

        return [
            'message' => __('Receipt approved and payment completed', 'mhm-rentiva')
        ];
    }

    /**
     * Makbuzu reddeder
     */
    private static function rejectReceipt(int $bookingId): array
    {
        // Ödeme durumunu güncelle
        update_post_meta($bookingId, '_mhm_payment_status', 'failed');

        return [
            'message' => __('Receipt rejected', 'mhm-rentiva')
        ];
    }

    /**
     * Makbuz durumunu kontrol eder
     */
    public static function getReceiptStatus(int $bookingId): array
    {
        $paymentStatus = get_post_meta($bookingId, '_mhm_payment_status', true);
        $receiptId = get_post_meta($bookingId, '_mhm_offline_receipt_id', true);
        $gateway = get_post_meta($bookingId, '_mhm_payment_gateway', true);

        return [
            'payment_status' => $paymentStatus,
            'receipt_id' => $receiptId,
            'gateway' => $gateway,
            'has_receipt' => !empty($receiptId),
            'is_offline' => $gateway === 'offline',
            'is_pending' => $paymentStatus === 'pending_verification',
            'is_approved' => $paymentStatus === 'paid',
            'is_rejected' => $paymentStatus === 'failed'
        ];
    }

    /**
     * Bekleyen makbuzları listeler
     */
    public static function getPendingReceipts(int $limit = 50): array
    {
        $args = [
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_mhm_payment_gateway',
                    'value' => 'offline',
                    'compare' => '='
                ],
                [
                    'key' => '_mhm_payment_status',
                    'value' => 'pending_verification',
                    'compare' => '='
                ],
                [
                    'key' => '_mhm_offline_receipt_id',
                    'compare' => 'EXISTS'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $bookings = get_posts($args);
        $result = [];

        foreach ($bookings as $booking) {
            $result[] = [
                'id' => $booking->ID,
                'title' => $booking->post_title,
                'date' => $booking->post_date,
                'receipt_id' => get_post_meta($booking->ID, '_mhm_offline_receipt_id', true),
                'payment_amount' => get_post_meta($booking->ID, '_mhm_payment_amount', true),
                'customer_email' => get_post_meta($booking->ID, '_mhm_customer_email', true)
            ];
        }

        return $result;
    }

    /**
     * Makbuz sayısını döndürür
     */
    public static function getReceiptCount(string $status = 'pending'): int
    {
        $metaQuery = [
            'relation' => 'AND',
            [
                'key' => '_mhm_payment_gateway',
                'value' => 'offline',
                'compare' => '='
            ],
            [
                'key' => '_mhm_offline_receipt_id',
                'compare' => 'EXISTS'
            ]
        ];

        if ($status === 'pending') {
            $metaQuery[] = [
                'key' => '_mhm_payment_status',
                'value' => 'pending_verification',
                'compare' => '='
            ];
        } elseif ($status === 'approved') {
            $metaQuery[] = [
                'key' => '_mhm_payment_status',
                'value' => 'paid',
                'compare' => '='
            ];
        } elseif ($status === 'rejected') {
            $metaQuery[] = [
                'key' => '_mhm_payment_status',
                'value' => 'failed',
                'compare' => '='
            ];
        }

        $args = [
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => $metaQuery,
            'fields' => 'ids'
        ];

        $bookings = get_posts($args);
        return count($bookings);
    }
}
