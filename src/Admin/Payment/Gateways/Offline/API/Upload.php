<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Payment\Offline\API;

use MHMRentiva\Admin\PostTypes\Logs\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class Upload
{
    // Max receipt size: 6 MB
    private const MAX_FILE_SIZE = 6291456;
    // Allowed mime types for receipts
    private const MIMES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

    /**
     * Makbuz yükleme işlemini gerçekleştirir
     */
    public static function uploadReceipt(int $bookingId, array $fileData): array
    {
        // Booking doğrulama
        if ($bookingId <= 0 || get_post_type($bookingId) !== 'vehicle_booking') {
            return [
                'success' => false,
                'message' => __('Invalid booking', 'mhm-rentiva'),
                'redirect' => add_query_arg(['booking' => 'error', 'code' => 'invalid_booking'], wp_get_referer() ?: home_url('/'))
            ];
        }

        // Dosya kontrolü
        if (empty($fileData) || (int) ($fileData['size'] ?? 0) <= 0) {
            return [
                'success' => false,
                'message' => __('No file selected', 'mhm-rentiva'),
                'redirect' => add_query_arg(['booking' => 'ok', 'bid' => $bookingId, 'offline' => 'nofile'], wp_get_referer() ?: home_url('/'))
            ];
        }

        // Dosya boyutu kontrolü
        $size = (int) ($fileData['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return [
                'success' => false,
                'message' => __('File too large (Max: 6MB)', 'mhm-rentiva'),
                'redirect' => add_query_arg(['booking' => 'ok', 'bid' => $bookingId, 'offline' => 'too_large'], wp_get_referer() ?: home_url('/'))
            ];
        }

        // WordPress dosya işleme fonksiyonlarını yükle
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Dosya yükleme ayarları
        $overrides = [
            'test_form' => false,
            'mimes' => self::MIMES,
        ];

        $file = wp_handle_upload($fileData, $overrides);
        
        if (isset($file['error'])) {
            Logger::error('Offline makbuz yükleme hatası: ' . $file['error']);
            return [
                'success' => false,
                'message' => __('File upload error:', 'mhm-rentiva') . ' ' . $file['error'],
                'redirect' => add_query_arg(['booking' => 'ok', 'bid' => $bookingId, 'offline' => 'upload_error'], wp_get_referer() ?: home_url('/'))
            ];
        }

        // Attachment oluştur
        $attachId = wp_insert_attachment([
            'post_mime_type' => $file['type'],
            'post_title' => __('Offline Receipt', 'mhm-rentiva') . ' ' . $bookingId,
            'post_content' => '',
            'post_status' => 'inherit',
        ], $file['file'], $bookingId);

        if (!$attachId) {
            Logger::error('Offline makbuz attachment oluşturulamadı: ' . $bookingId);
            return [
                'success' => false,
                'message' => __('Receipt could not be saved', 'mhm-rentiva'),
                'redirect' => add_query_arg(['booking' => 'ok', 'bid' => $bookingId, 'offline' => 'upload_error'], wp_get_referer() ?: home_url('/'))
            ];
        }

        // Attachment metadata oluştur
        $meta = wp_generate_attachment_metadata($attachId, $file['file']);
        wp_update_attachment_metadata($attachId, $meta);

        // Booking meta güncelle
        update_post_meta($bookingId, '_mhm_offline_receipt_id', (int) $attachId);
        update_post_meta($bookingId, '_mhm_payment_status', 'pending_verification');
        update_post_meta($bookingId, '_mhm_payment_gateway', 'offline');

        // Hook tetikle
        do_action('mhm_rentiva_offline_receipt_uploaded', $bookingId, (int) $attachId);

        Logger::info('Offline makbuz yüklendi: Booking ' . $bookingId . ' - Attachment ' . $attachId);

        return [
            'success' => true,
            'message' => __('Receipt uploaded successfully', 'mhm-rentiva'),
            'attachment_id' => $attachId,
            'redirect' => add_query_arg(['booking' => 'ok', 'bid' => $bookingId, 'offline' => 'uploaded'], wp_get_referer() ?: home_url('/'))
        ];
    }

    /**
     * Dosya doğrulama yapar
     */
    public static function validateFile(array $fileData): array
    {
        $errors = [];

        // Dosya var mı?
        if (empty($fileData) || (int) ($fileData['size'] ?? 0) <= 0) {
            $errors[] = __('No file selected', 'mhm-rentiva');
            return ['valid' => false, 'errors' => $errors];
        }

        // Dosya boyutu
        $size = (int) ($fileData['size'] ?? 0);
        if ($size <= 0) {
            $errors[] = __('Invalid file size', 'mhm-rentiva');
        } elseif ($size > self::MAX_FILE_SIZE) {
            $errors[] = __('File too large (Max: 6MB)', 'mhm-rentiva');
        }

        // Dosya tipi
        $type = $fileData['type'] ?? '';
        if (!in_array($type, array_values(self::MIMES), true)) {
            $errors[] = __('Unsupported file type (JPG, PNG, PDF)', 'mhm-rentiva');
        }

        // Dosya uzantısı
        $name = $fileData['name'] ?? '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::MIMES)) {
            $errors[] = __('Invalid file extension', 'mhm-rentiva');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Desteklenen dosya tiplerini döndürür
     */
    public static function getSupportedMimeTypes(): array
    {
        return self::MIMES;
    }

    /**
     * Maksimum dosya boyutunu döndürür
     */
    public static function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Maksimum dosya boyutunu MB cinsinden döndürür
     */
    public static function getMaxFileSizeMB(): float
    {
        return round(self::MAX_FILE_SIZE / 1024 / 1024, 1);
    }
}
