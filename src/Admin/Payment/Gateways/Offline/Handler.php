<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Offline;

use MHMRentiva\Admin\Payment\Offline\API\Upload;
use MHMRentiva\Admin\Payment\Offline\API\Verification;

if (!defined('ABSPATH')) {
    exit;
}

final class Handler
{
    /**
     * Offline payment handler kayıt işlemleri
     */
    public static function register(): void
    {
        add_action('admin_post_nopriv_mhm_rentiva_offline_receipt', [self::class, 'upload_receipt']);
        add_action('admin_post_mhm_rentiva_offline_receipt', [self::class, 'upload_receipt']);
        add_action('admin_post_mhm_rentiva_offline_verify', [self::class, 'verify_receipt']);
    }

    /**
     * Makbuz yükleme endpoint'i
     */
    public static function upload_receipt(): void
    {
        // Nonce kontrolü
        if (!isset($_POST['mhm_rentiva_offline_nonce']) || !wp_verify_nonce((string) $_POST['mhm_rentiva_offline_nonce'], 'mhm_rentiva_offline_action')) {
            wp_safe_redirect(add_query_arg(['booking' => 'error', 'code' => 'invalid_nonce'], wp_get_referer() ?: home_url('/')));
            exit;
        }

        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $fileData = $_FILES['receipt'] ?? [];

        // Upload işlemini gerçekleştir
        $result = Upload::uploadReceipt($bookingId, $fileData);

        // Sonucu işle
        if ($result['success']) {
            wp_safe_redirect($result['redirect']);
        } else {
            wp_safe_redirect($result['redirect']);
        }
        exit;
    }

    /**
     * Makbuz doğrulama endpoint'i
     */
    public static function verify_receipt(): void
    {
        // Nonce kontrolü
        if (!isset($_POST['mhm_rentiva_offline_nonce']) || !wp_verify_nonce((string) $_POST['mhm_rentiva_offline_nonce'], 'mhm_rentiva_offline_action')) {
            wp_die(__('Invalid nonce.', 'mhm-rentiva'));
        }

        $bookingId = absint($_POST['booking_id'] ?? 0);
        $decision = sanitize_text_field((string) ($_POST['decision'] ?? ''));

        // Verification işlemini gerçekleştir
        $result = Verification::verifyReceipt($bookingId, $decision);

        // Sonucu işle
        if ($result['success']) {
            wp_safe_redirect($result['redirect']);
        } else {
            wp_die($result['message']);
        }
        exit;
    }

    /**
     * Makbuz durumunu alır
     */
    public static function getReceiptStatus(int $bookingId): array
    {
        return Verification::getReceiptStatus($bookingId);
    }

    /**
     * Bekleyen makbuzları listeler
     */
    public static function getPendingReceipts(int $limit = 50): array
    {
        return Verification::getPendingReceipts($limit);
    }

    /**
     * Makbuz sayısını döndürür
     */
    public static function getReceiptCount(string $status = 'pending'): int
    {
        return Verification::getReceiptCount($status);
    }

    /**
     * Dosya doğrulama yapar
     */
    public static function validateFile(array $fileData): array
    {
        return Upload::validateFile($fileData);
    }

    /**
     * Desteklenen dosya tiplerini döndürür
     */
    public static function getSupportedMimeTypes(): array
    {
        return Upload::getSupportedMimeTypes();
    }

    /**
     * Maksimum dosya boyutunu döndürür
     */
    public static function getMaxFileSize(): int
    {
        return Upload::getMaxFileSize();
    }

    /**
     * Maksimum dosya boyutunu MB cinsinden döndürür
     */
    public static function getMaxFileSizeMB(): float
    {
        return Upload::getMaxFileSizeMB();
    }
}
