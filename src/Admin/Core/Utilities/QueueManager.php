<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ BULK OPERATIONS QUEUE SİSTEMİ - Arka Plan İşlem Yönetimi
 * 
 * Büyük veri setleri için queue tabanlı işlem yönetimi
 */
final class QueueManager
{
    /**
     * Queue tablo adı
     */
    private const QUEUE_TABLE = 'mhm_rentiva_queue';

    /**
     * Job durumları
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Job türleri
     */
    public const TYPE_BULK_BOOKING_UPDATE = 'bulk_booking_update';
    public const TYPE_BULK_VEHICLE_UPDATE = 'bulk_vehicle_update';
    public const TYPE_BULK_CUSTOMER_UPDATE = 'bulk_customer_update';
    public const TYPE_BULK_EMAIL_SEND = 'bulk_email_send';
    public const TYPE_BULK_EXPORT = 'bulk_export';
    public const TYPE_BULK_IMPORT = 'bulk_import';
    public const TYPE_CACHE_WARMUP = 'cache_warmup';
    public const TYPE_DATA_CLEANUP = 'data_cleanup';
    public const TYPE_REPORT_GENERATION = 'report_generation';

    /**
     * Queue tablosunu oluştur
     */
    public static function create_table(): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(100) NOT NULL,
            job_data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 10,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            started_at datetime NULL,
            completed_at datetime NULL,
            error_message text NULL,
            progress_percent int(11) NOT NULL DEFAULT 0,
            total_items int(11) NOT NULL DEFAULT 0,
            processed_items int(11) NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY job_type (job_type),
            KEY priority (priority),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Job ekle
     */
    public static function add_job(string $job_type, array $job_data, int $priority = 10, int $max_attempts = 3, ?int $user_id = null): int
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $result = $wpdb->insert(
            $table_name,
            [
                'job_type' => $job_type,
                'job_data' => wp_json_encode($job_data, JSON_UNESCAPED_UNICODE),
                'priority' => $priority,
                'max_attempts' => $max_attempts,
                'user_id' => $user_id ?: get_current_user_id(),
                'status' => self::STATUS_PENDING,
            ],
            ['%s', '%s', '%d', '%d', '%d', '%s']
        );
        
        if ($result === false) {
            return 0;
        }
        
        // Queue işleme başlat (eğer cron çalışmıyorsa)
        self::maybe_start_processing();
        
        return $wpdb->insert_id;
    }

    /**
     * Job al (FIFO + priority)
     */
    public static function get_next_job(): ?array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status = %s 
             AND attempts < max_attempts
             ORDER BY priority ASC, created_at ASC 
             LIMIT 1",
            self::STATUS_PENDING
        ), ARRAY_A);
        
        if (!$job) {
            return null;
        }
        
        // Job'u processing olarak işaretle
        $wpdb->update(
            $table_name,
            [
                'status' => self::STATUS_PROCESSING,
                'started_at' => current_time('mysql'),
                'attempts' => $job['attempts'] + 1,
            ],
            ['id' => $job['id']],
            ['%s', '%s', '%d'],
            ['%d']
        );
        
        // Job data'yı decode et
        $job['job_data'] = json_decode($job['job_data'], true) ?: [];
        
        return $job;
    }

    /**
     * Job durumunu güncelle
     */
    public static function update_job_status(int $job_id, string $status, ?string $error_message = null, ?int $progress_percent = null): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $update_data = ['status' => $status];
        $update_format = ['%s'];
        
        if ($status === self::STATUS_COMPLETED) {
            $update_data['completed_at'] = current_time('mysql');
            $update_data['progress_percent'] = 100;
            $update_format[] = '%s';
            $update_format[] = '%d';
        } elseif ($status === self::STATUS_FAILED && $error_message) {
            $update_data['error_message'] = $error_message;
            $update_format[] = '%s';
        }
        
        if ($progress_percent !== null) {
            $update_data['progress_percent'] = $progress_percent;
            $update_format[] = '%d';
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $job_id],
            $update_format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Job progress güncelle
     */
    public static function update_job_progress(int $job_id, int $processed_items, int $total_items): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $progress_percent = $total_items > 0 ? round(($processed_items / $total_items) * 100) : 0;
        
        return $wpdb->update(
            $table_name,
            [
                'processed_items' => $processed_items,
                'total_items' => $total_items,
                'progress_percent' => $progress_percent,
            ],
            ['id' => $job_id],
            ['%d', '%d', '%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Job iptal et
     */
    public static function cancel_job(int $job_id): bool
    {
        return self::update_job_status($job_id, self::STATUS_CANCELLED);
    }

    /**
     * Job sil
     */
    public static function delete_job(int $job_id): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        return $wpdb->delete($table_name, ['id' => $job_id], ['%d']) !== false;
    }

    /**
     * Job detayları al
     */
    public static function get_job(int $job_id): ?array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $job_id
        ), ARRAY_A);
        
        if (!$job) {
            return null;
        }
        
        $job['job_data'] = json_decode($job['job_data'], true) ?: [];
        
        return $job;
    }

    /**
     * Kullanıcı job'larını al
     */
    public static function get_user_jobs(int $user_id, string $status = '', int $limit = 50): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $where_conditions = ['user_id = %d'];
        $where_values = [$user_id];
        
        if (!empty($status)) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (empty($where_conditions)) {
            $jobs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $limit
            ), ARRAY_A);
        } else {
            // Placeholder sayısını kontrol et
            $placeholder_count = substr_count($where_clause, '%');
            if ($placeholder_count === count($where_values)) {
                $jobs = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name} 
                     WHERE {$where_clause}
                     ORDER BY created_at DESC 
                     LIMIT %d",
                    array_merge($where_values, [$limit])
                ), ARRAY_A);
            } else {
                // Placeholder olmayan where clause
                $jobs = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name} 
                     WHERE {$where_clause}
                     ORDER BY created_at DESC 
                     LIMIT %d",
                    $limit
                ), ARRAY_A);
            }
        }
        
        foreach ($jobs as &$job) {
            $job['job_data'] = json_decode($job['job_data'], true) ?: [];
        }
        
        return $jobs;
    }

    /**
     * Queue istatistikleri
     */
    public static function get_queue_stats(): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_jobs,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_jobs,
                AVG(progress_percent) as avg_progress
             FROM {$table_name}",
            ARRAY_A
        );
        
        return $stats ?: [];
    }

    /**
     * Eski job'ları temizle
     */
    public static function cleanup_old_jobs(int $days = 30): int
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE status IN (%s, %s) 
             AND completed_at < %s",
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            $cutoff_date
        ));
    }

    /**
     * Başarısız job'ları yeniden deneme
     */
    public static function retry_failed_jobs(): int
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        
        return $wpdb->update(
            $table_name,
            [
                'status' => self::STATUS_PENDING,
                'error_message' => null,
                'attempts' => 0,
            ],
            [
                'status' => self::STATUS_FAILED,
            ],
            ['%s', '%s', '%d'],
            ['%s']
        );
    }

    /**
     * Queue işleme başlat (eğer gerekirse)
     */
    public static function maybe_start_processing(): void
    {
        // Eğer cron job çalışmıyorsa, manuel olarak başlat
        if (!wp_next_scheduled('mhm_rentiva_process_queue')) {
            wp_schedule_single_event(time(), 'mhm_rentiva_process_queue');
        }
    }

    /**
     * Bulk booking update job
     */
    public static function add_bulk_booking_update_job(array $booking_ids, array $update_data, int $user_id = 0): int
    {
        return self::add_job(
            self::TYPE_BULK_BOOKING_UPDATE,
            [
                'booking_ids' => $booking_ids,
                'update_data' => $update_data,
            ],
            5, // Yüksek öncelik
            3,
            $user_id ?: get_current_user_id()
        );
    }

    /**
     * Bulk vehicle update job
     */
    public static function add_bulk_vehicle_update_job(array $vehicle_ids, array $update_data, int $user_id = 0): int
    {
        return self::add_job(
            self::TYPE_BULK_VEHICLE_UPDATE,
            [
                'vehicle_ids' => $vehicle_ids,
                'update_data' => $update_data,
            ],
            5,
            3,
            $user_id ?: get_current_user_id()
        );
    }

    /**
     * Bulk email send job
     */
    public static function add_bulk_email_job(array $recipients, string $subject, string $template, array $template_data, int $user_id = 0): int
    {
        return self::add_job(
            self::TYPE_BULK_EMAIL_SEND,
            [
                'recipients' => $recipients,
                'subject' => $subject,
                'template' => $template,
                'template_data' => $template_data,
            ],
            7, // Orta öncelik
            2,
            $user_id ?: get_current_user_id()
        );
    }

    /**
     * Bulk export job
     */
    public static function add_bulk_export_job(string $export_type, array $filters, string $format, int $user_id = 0): int
    {
        return self::add_job(
            self::TYPE_BULK_EXPORT,
            [
                'export_type' => $export_type,
                'filters' => $filters,
                'format' => $format,
            ],
            8, // Düşük öncelik
            1,
            $user_id ?: get_current_user_id()
        );
    }

    /**
     * Cache warmup job
     */
    public static function add_cache_warmup_job(array $cache_keys, int $user_id = 0): int
    {
        return self::add_job(
            self::TYPE_CACHE_WARMUP,
            [
                'cache_keys' => $cache_keys,
            ],
            9, // En düşük öncelik
            1,
            $user_id ?: get_current_user_id()
        );
    }

    /**
     * Job processor - farklı job türlerini işle
     */
    public static function process_job(array $job): bool
    {
        try {
            switch ($job['job_type']) {
                case self::TYPE_BULK_BOOKING_UPDATE:
                    return self::process_bulk_booking_update($job);
                    
                case self::TYPE_BULK_VEHICLE_UPDATE:
                    return self::process_bulk_vehicle_update($job);
                    
                case self::TYPE_BULK_EMAIL_SEND:
                    return self::process_bulk_email_send($job);
                    
                case self::TYPE_BULK_EXPORT:
                    return self::process_bulk_export($job);
                    
                case self::TYPE_CACHE_WARMUP:
                    return self::process_cache_warmup($job);
                    
                default:
                    self::update_job_status($job['id'], self::STATUS_FAILED, 'Unknown job type: ' . $job['job_type']);
                    return false;
            }
        } catch (Exception $e) {
            self::update_job_status($job['id'], self::STATUS_FAILED, $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk booking update işleme
     */
    private static function process_bulk_booking_update(array $job): bool
    {
        $booking_ids = $job['job_data']['booking_ids'] ?? [];
        $update_data = $job['job_data']['update_data'] ?? [];
        $total_items = count($booking_ids);
        
        if ($total_items === 0) {
            self::update_job_status($job['id'], self::STATUS_COMPLETED);
            return true;
        }
        
        self::update_job_progress($job['id'], 0, $total_items);
        
        $processed = 0;
        foreach ($booking_ids as $booking_id) {
            // Job iptal edildi mi kontrol et
            $current_job = self::get_job($job['id']);
            if ($current_job && $current_job['status'] === self::STATUS_CANCELLED) {
                return false;
            }
            
            // Booking güncelle
            foreach ($update_data as $meta_key => $meta_value) {
                update_post_meta($booking_id, $meta_key, $meta_value);
            }
            
            $processed++;
            self::update_job_progress($job['id'], $processed, $total_items);
            
            // Memory kullanımını kontrol et
            if ($processed % 50 === 0) {
                wp_cache_flush();
            }
        }
        
        self::update_job_status($job['id'], self::STATUS_COMPLETED);
        return true;
    }

    /**
     * Bulk vehicle update işleme
     */
    private static function process_bulk_vehicle_update(array $job): bool
    {
        $vehicle_ids = $job['job_data']['vehicle_ids'] ?? [];
        $update_data = $job['job_data']['update_data'] ?? [];
        $total_items = count($vehicle_ids);
        
        if ($total_items === 0) {
            self::update_job_status($job['id'], self::STATUS_COMPLETED);
            return true;
        }
        
        self::update_job_progress($job['id'], 0, $total_items);
        
        $processed = 0;
        foreach ($vehicle_ids as $vehicle_id) {
            // Job iptal edildi mi kontrol et
            $current_job = self::get_job($job['id']);
            if ($current_job && $current_job['status'] === self::STATUS_CANCELLED) {
                return false;
            }
            
            // Vehicle güncelle
            foreach ($update_data as $meta_key => $meta_value) {
                update_post_meta($vehicle_id, $meta_key, $meta_value);
            }
            
            $processed++;
            self::update_job_progress($job['id'], $processed, $total_items);
            
            // Memory kullanımını kontrol et
            if ($processed % 50 === 0) {
                wp_cache_flush();
            }
        }
        
        self::update_job_status($job['id'], self::STATUS_COMPLETED);
        return true;
    }

    /**
     * Bulk email send işleme
     */
    private static function process_bulk_email_send(array $job): bool
    {
        $recipients = $job['job_data']['recipients'] ?? [];
        $subject = $job['job_data']['subject'] ?? '';
        $template = $job['job_data']['template'] ?? '';
        $template_data = $job['job_data']['template_data'] ?? [];
        $total_items = count($recipients);
        
        if ($total_items === 0) {
            self::update_job_status($job['id'], self::STATUS_COMPLETED);
            return true;
        }
        
        self::update_job_progress($job['id'], 0, $total_items);
        
        $processed = 0;
        foreach ($recipients as $recipient) {
            // Job iptal edildi mi kontrol et
            $current_job = self::get_job($job['id']);
            if ($current_job && $current_job['status'] === self::STATUS_CANCELLED) {
                return false;
            }
            
            // Email gönder
            $email_data = array_merge($template_data, ['recipient' => $recipient]);
            // Email gönderme logic'i burada olacak
            
            $processed++;
            self::update_job_progress($job['id'], $processed, $total_items);
            
            // Rate limiting
            if ($processed % 10 === 0) {
                sleep(1);
            }
        }
        
        self::update_job_status($job['id'], self::STATUS_COMPLETED);
        return true;
    }

    /**
     * Bulk export işleme
     */
    private static function process_bulk_export(array $job): bool
    {
        $export_type = $job['job_data']['export_type'] ?? '';
        $filters = $job['job_data']['filters'] ?? [];
        $format = $job['job_data']['format'] ?? 'csv';
        
        // Export logic'i burada olacak
        // Bu örnekte basit bir implementation
        
        self::update_job_progress($job['id'], 50, 100);
        sleep(2); // Simulated processing
        self::update_job_progress($job['id'], 100, 100);
        
        self::update_job_status($job['id'], self::STATUS_COMPLETED);
        return true;
    }

    /**
     * Cache warmup işleme
     */
    private static function process_cache_warmup(array $job): bool
    {
        $cache_keys = $job['job_data']['cache_keys'] ?? [];
        $total_items = count($cache_keys);
        
        if ($total_items === 0) {
            self::update_job_status($job['id'], self::STATUS_COMPLETED);
            return true;
        }
        
        self::update_job_progress($job['id'], 0, $total_items);
        
        $processed = 0;
        foreach ($cache_keys as $cache_key) {
            // Cache warmup logic'i burada olacak
            // Örnek: ObjectCache::get($cache_key, $group);
            
            $processed++;
            self::update_job_progress($job['id'], $processed, $total_items);
        }
        
        self::update_job_status($job['id'], self::STATUS_COMPLETED);
        return true;
    }
}
