<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ DATABASE MIGRATION YÖNETİCİSİ - Otomatik Index ve Schema Güncellemeleri
 * 
 * Performans optimizasyonu için kritik index'leri otomatik olarak oluşturur
 */
final class DatabaseMigrator
{
    /**
     * Migration version
     */
    private const CURRENT_VERSION = '3.0.2';

    /**
     * Migration'ları çalıştır
     */
    public static function run_migrations(): void
    {
        $current_version = get_option('mhm_rentiva_db_version', '1.0.0');

        if (version_compare($current_version, self::CURRENT_VERSION, '<')) {
            self::create_transfer_tables(); // VIP Transfer Tables
            self::add_performance_indexes();
            self::optimize_existing_indexes();
            self::add_missing_indexes();
            self::cleanup_orphan_data();

            // Version'u güncelle
            update_option('mhm_rentiva_db_version', self::CURRENT_VERSION);

            // Log migration
            if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info('Database migration completed', [
                    'from_version' => $current_version,
                    'to_version' => self::CURRENT_VERSION,
                    'indexes_added' => true
                ], \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
        }
    }

    /**
     * Kritik performans indexlerini ekle
     */
    private static function add_performance_indexes(): void
    {
        global $wpdb;

        $indexes = [
            // 1. Status queries için composite index
            "CREATE INDEX IF NOT EXISTS idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)",

            // 2. Date range queries için timestamp index
            "CREATE INDEX IF NOT EXISTS idx_mhm_timestamp_range ON {$wpdb->postmeta} (post_id, meta_key(50), meta_value(20))",

            // 3. Vehicle booking lookups için
            "CREATE INDEX IF NOT EXISTS idx_mhm_vehicle_bookings ON {$wpdb->postmeta} (meta_value(20), post_id)",

            // 4. Post date queries için
            "CREATE INDEX IF NOT EXISTS idx_posts_date_type ON {$wpdb->posts} (post_date, post_type(20), post_status(20))",

            // 5. Booking meta queries için
            "CREATE INDEX IF NOT EXISTS idx_mhm_booking_meta ON {$wpdb->postmeta} (meta_key(50), post_id, meta_value(50))",

            // 6. Customer email lookups için
            "CREATE INDEX IF NOT EXISTS idx_mhm_customer_email ON {$wpdb->postmeta} (meta_key(50), meta_value(100))",

            // 7. Price range queries için
            "CREATE INDEX IF NOT EXISTS idx_mhm_price_range ON {$wpdb->postmeta} (meta_key(50), meta_value(20))",

            // 8. Combined booking lookup için
            "CREATE INDEX IF NOT EXISTS idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))",
        ];

        foreach ($indexes as $sql) {
            try {
                $result = $wpdb->query($sql);
                if ($result === false) {
                    self::log_index_error($sql, $wpdb->last_error);
                }
            } catch (\Exception $e) {
                self::log_index_error($sql, $e->getMessage());
            }
        }
    }

    /**
     * Mevcut indexleri optimize et
     */
    private static function optimize_existing_indexes(): void
    {
        global $wpdb;

        // Index analizi yap
        $analysis_queries = [
            "ANALYZE TABLE {$wpdb->posts}",
            "ANALYZE TABLE {$wpdb->postmeta}",
        ];

        foreach ($analysis_queries as $sql) {
            try {
                $wpdb->query($sql);
            } catch (\Exception $e) {
                self::log_index_error($sql, $e->getMessage());
            }
        }
    }

    /**
     * Eksik indexleri tespit et ve ekle
     */
    private static function add_missing_indexes(): void
    {
        global $wpdb;

        // Eksik indexleri tespit et
        $missing_indexes = self::detect_missing_indexes();

        foreach ($missing_indexes as $index_sql) {
            try {
                $result = $wpdb->query($index_sql);
                if ($result === false) {
                    self::log_index_error($index_sql, $wpdb->last_error);
                }
            } catch (\Exception $e) {
                self::log_index_error($index_sql, $e->getMessage());
            }
        }
    }

    /**
     * Eksik indexleri tespit et
     */
    private static function detect_missing_indexes(): array
    {
        global $wpdb;

        $missing_indexes = [];

        // MHM Rentiva meta key'leri için özel indexler
        $mhm_meta_keys = [
            '_mhm_status',
            '_mhm_vehicle_id',
            '_mhm_start_ts',
            '_mhm_end_ts',
            '_mhm_total_price',
            '_mhm_contact_email',
            '_mhm_contact_name',
            '_mhm_customer_id'
        ];

        foreach ($mhm_meta_keys as $meta_key) {
            // Her meta key için özel index oluştur
            $index_name = 'idx_mhm_' . str_replace('_mhm_', '', $meta_key);
            $missing_indexes[] = "CREATE INDEX IF NOT EXISTS {$index_name} ON {$wpdb->postmeta} (meta_key(50), meta_value(50), post_id)";
        }

        return $missing_indexes;
    }

    /**
     * Index durumunu kontrol et
     */
    public static function check_index_status(): array
    {
        global $wpdb;

        $status = [
            'total_indexes' => 0,
            'mhm_indexes' => 0,
            'performance_score' => 0,
            'missing_indexes' => [],
            'recommendations' => []
        ];

        try {
            // Posts tablosu indexleri
            $posts_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->posts}");
            $status['total_indexes'] += count($posts_indexes);

            // Postmeta tablosu indexleri
            $postmeta_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
            $status['total_indexes'] += count($postmeta_indexes);

            // MHM Rentiva indexlerini say
            foreach ($postmeta_indexes as $index) {
                if (strpos($index->Key_name, 'idx_mhm_') === 0) {
                    $status['mhm_indexes']++;
                }
            }

            // Performance score hesapla
            $status['performance_score'] = min(100, ($status['mhm_indexes'] / 8) * 100);

            // Öneriler
            if ($status['mhm_indexes'] < 5) {
                $status['recommendations'][] = 'Daha fazla MHM Rentiva indexi eklenmeli';
            }

            if ($status['performance_score'] < 70) {
                $status['recommendations'][] = 'Database performansı optimize edilmeli';
            }
        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Index performans testi
     */
    public static function test_index_performance(): array
    {
        global $wpdb;

        $results = [];

        // Test query'leri
        $test_queries = [
            'status_lookup' => "
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_status' 
                AND meta_value = 'confirmed'
            ",
            'date_range' => "
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_start_ts' 
                AND meta_value > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ",
            'vehicle_bookings' => "
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_mhm_vehicle_id' 
                AND meta_value = '123'
            ",
            'post_date_query' => "
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'vehicle_booking' 
                AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            "
        ];

        foreach ($test_queries as $test_name => $query) {
            $start_time = microtime(true);
            $result = $wpdb->get_var($query);
            $end_time = microtime(true);

            $results[$test_name] = [
                'execution_time' => round(($end_time - $start_time) * 1000, 2), // ms
                'result' => $result,
                'query' => $query
            ];
        }

        return $results;
    }

    /**
     * Database optimizasyonu çalıştır
     */
    public static function optimize_database(): array
    {
        global $wpdb;

        $results = [];

        try {
            // Tabloları optimize et
            $tables = [$wpdb->posts, $wpdb->postmeta];

            foreach ($tables as $table) {
                $start_time = microtime(true);
                $result = $wpdb->query("OPTIMIZE TABLE {$table}");
                $end_time = microtime(true);

                $results['optimize'][$table] = [
                    'success' => $result !== false,
                    'execution_time' => round(($end_time - $start_time) * 1000, 2),
                    'error' => $result === false ? $wpdb->last_error : null
                ];
            }

            // Index'leri yeniden oluştur
            $results['rebuild_indexes'] = self::rebuild_indexes();
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Indexleri yeniden oluştur
     */
    private static function rebuild_indexes(): array
    {
        global $wpdb;

        $results = [];

        // Kritik indexleri yeniden oluştur
        $critical_indexes = [
            "DROP INDEX IF EXISTS idx_mhm_status_lookup ON {$wpdb->postmeta}",
            "CREATE INDEX idx_mhm_status_lookup ON {$wpdb->postmeta} (meta_key(50), meta_value(20), post_id)",
            "DROP INDEX IF EXISTS idx_mhm_booking_combined ON {$wpdb->postmeta}",
            "CREATE INDEX idx_mhm_booking_combined ON {$wpdb->postmeta} (post_id, meta_key(50))"
        ];

        foreach ($critical_indexes as $sql) {
            $start_time = microtime(true);
            $result = $wpdb->query($sql);
            $end_time = microtime(true);

            $results[] = [
                'sql' => $sql,
                'success' => $result !== false,
                'execution_time' => round(($end_time - $start_time) * 1000, 2),
                'error' => $result === false ? $wpdb->last_error : null
            ];
        }

        return $results;
    }

    /**
     * Migration durumunu kontrol et
     */
    public static function get_migration_status(): array
    {
        $current_version = get_option('mhm_rentiva_db_version', '1.0.0');
        $index_status = self::check_index_status();
        $performance_test = self::test_index_performance();

        return [
            'current_version' => $current_version,
            'target_version' => self::CURRENT_VERSION,
            'needs_migration' => version_compare($current_version, self::CURRENT_VERSION, '<'),
            'index_status' => $index_status,
            'performance_test' => $performance_test,
            'last_migration' => get_option('mhm_rentiva_last_migration', 'Never')
        ];
    }

    /**
     * Migration'ı geri al
     */
    public static function rollback_migration(): bool
    {
        global $wpdb;

        try {
            // MHM Rentiva indexlerini sil
            $drop_indexes = [
                "DROP INDEX IF EXISTS idx_mhm_status_lookup ON {$wpdb->postmeta}",
                "DROP INDEX IF EXISTS idx_mhm_timestamp_range ON {$wpdb->postmeta}",
                "DROP INDEX IF EXISTS idx_mhm_vehicle_bookings ON {$wpdb->postmeta}",
                "DROP INDEX IF EXISTS idx_posts_date_type ON {$wpdb->posts}",
                "DROP INDEX IF EXISTS idx_mhm_booking_meta ON {$wpdb->postmeta}",
                "DROP INDEX IF EXISTS idx_mhm_customer_email ON {$wpdb->postmeta}",
                "DROP INDEX IF EXISTS idx_mhm_price_range ON {$wpdb->postmeta}",
                "DROP INDEX IF EXISTS idx_mhm_booking_combined ON {$wpdb->postmeta}"
            ];

            foreach ($drop_indexes as $sql) {
                $wpdb->query($sql);
            }

            // Version'u eski haline getir
            update_option('mhm_rentiva_db_version', '1.0.0');

            if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning('Database migration rolled back', [], \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }

            return true;
        } catch (\Exception $e) {
            if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error('Migration rollback failed', [
                    'error' => $e->getMessage()
                ], \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
            return false;
        }
    }

    /**
     * Index hata logla
     */
    private static function log_index_error(string $sql, string $error): void
    {
        if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error('Database index creation failed', [
                'sql' => $sql,
                'error' => $error
            ], \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM);
        }
    }

    /**
     * Admin notice göster
     */
    public static function show_migration_notice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $status = self::get_migration_status();

        if ($status['needs_migration']) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('MHM Rentiva: Database migration required. Run migration for performance.', 'mhm-rentiva');
            echo ' <a href="' . admin_url('admin.php?page=mhm-rentiva&action=run_migration') . '">';
            echo esc_html__('Run Migration', 'mhm-rentiva');
            echo '</a>';
            echo '</p></div>';
        } elseif ($status['index_status']['performance_score'] < 80) {
            echo '<div class="notice notice-info"><p>';
            echo esc_html__('MHM Rentiva: Database performance can be optimized.', 'mhm-rentiva');
            echo ' <a href="' . admin_url('admin.php?page=mhm-rentiva&action=optimize_db') . '">';
            echo esc_html__('Optimize', 'mhm-rentiva');
            echo '</a>';
            echo '</p></div>';
        }
    }
    /**
     * Creates VIP Transfer tables
     */
    private static function create_transfer_tables(): void
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // 1. Transfer Locations
        $table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $sql_locations = "CREATE TABLE $table_locations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            priority int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active)
        ) $charset_collate;";

        dbDelta($sql_locations);

        // 2. Transfer Routes
        $table_routes = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        $sql_routes = "CREATE TABLE $table_routes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            origin_id bigint(20) NOT NULL,
            destination_id bigint(20) NOT NULL,
            distance_km float DEFAULT 0,
            duration_min int(11) DEFAULT 0,
            pricing_method enum('fixed', 'calculated') DEFAULT 'fixed',
            base_price decimal(10,2) DEFAULT 0.00,
            min_price decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY origin_dest (origin_id, destination_id),
            KEY pricing_method (pricing_method)
        ) $charset_collate;";

        dbDelta($sql_routes);
    }

    /**
     * Creates rating database table
     */
    public static function create_rating_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_ratings';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vehicle_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            rating decimal(2,1) NOT NULL,
            comment text DEFAULT NULL,
            status varchar(20) DEFAULT 'approved',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_vehicle_user (vehicle_id, user_id),
            KEY vehicle_id (vehicle_id),
            KEY user_id (user_id),
            KEY rating (rating),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Orphan dataları temizle
     */
    private static function cleanup_orphan_data(): void
    {
        global $wpdb;

        // 1. Orphan Post Meta Cleaning
        $meta_sql = "DELETE pm
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
        WHERE wp.ID IS NULL
        AND pm.meta_key LIKE '_mhm_%'";

        $wpdb->query($meta_sql);

        // 2. Transient Data Cleaning
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_mhm_rate_limit_%' 
             OR option_name LIKE '_transient_timeout_mhm_rate_limit_%'"
        );
    }
    /**
     * Create specific table by key
     */
    public static function create_table(string $table_key): bool
    {
        switch ($table_key) {
            case 'payment_log':
            case 'mhm_payment_log':
                self::create_payment_log_table();
                return true;
            case 'sessions':
            case 'mhm_sessions':
                self::create_sessions_table();
                return true;
            case 'transfer_locations':
            case 'mhm_rentiva_transfer_locations':
                self::create_transfer_tables();
                return true;
            case 'transfer_routes':
            case 'mhm_rentiva_transfer_routes':
                self::create_transfer_tables();
                return true;
            case 'ratings':
            case 'mhm_rentiva_ratings':
                self::create_rating_table();
                return true;
        }
        return false;
    }

    /**
     * Create payment log table
     */
    public static function create_payment_log_table(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_payment_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            transaction_id varchar(100) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'USD',
            gateway varchar(50) DEFAULT NULL,
            method varchar(50) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            raw_data text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY transaction_id (transaction_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create sessions table
     */
    public static function create_sessions_table(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_sessions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            session_id bigint(20) NOT NULL AUTO_INCREMENT,
            session_key varchar(32) NOT NULL,
            session_value longtext NOT NULL,
            session_expiry bigint(20) NOT NULL,
            PRIMARY KEY (session_id),
            UNIQUE KEY session_key (session_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
