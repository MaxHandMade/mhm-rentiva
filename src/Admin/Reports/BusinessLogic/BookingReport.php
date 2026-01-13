<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\BusinessLogic;

use MHMRentiva\Admin\Booking\Core\Status;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingReport
{
    /**
     * Cache durations - Magic numbers elimination
     */
    private const CACHE_DURATION_REPORTS = 15 * MINUTE_IN_SECONDS; // 15 minutes
    private const CACHE_DURATION_TRENDS = 15 * MINUTE_IN_SECONDS; // 15 minutes
    private const LARGE_DATASET_THRESHOLD = 5000; // Large dataset threshold
    private const PAGINATION_LIMIT = 1000; // Pagination limit
    private const PEAK_RESULTS_LIMIT = 5; // Peak results limit
    
    /**
     * Class availability cache
     */
    private static ?array $class_cache = null;
    
    /**
     * Check if class is available (cached)
     * 
     * @param string $class Class name
     * @return bool
     */
    private static function is_class_available(string $class): bool
    {
        if (self::$class_cache === null) {
            self::$class_cache = [
                'Core\ObjectCache' => class_exists(\MHMRentiva\Core\ObjectCache::class),
                'Admin\Reports\BackgroundProcessor' => class_exists(\MHMRentiva\Admin\Reports\BackgroundProcessor::class),
                'Logs\AdvancedLogger' => class_exists(\MHMRentiva\Logs\AdvancedLogger::class),
                'Core\QueueManager' => class_exists(\MHMRentiva\Core\QueueManager::class),
            ];
        }
        
        return self::$class_cache[$class] ?? false;
    }
    public static function get_data(string $start_date, string $end_date): array
    {
        global $wpdb;

        $cache_key = 'mhm_booking_report_' . md5($start_date . $end_date);
        
        // Use object cache (if available)
        if (self::is_class_available('Core\ObjectCache')) {
            $data = \MHMRentiva\Core\ObjectCache::get($cache_key, \MHMRentiva\Core\ObjectCache::GROUP_REPORTS);
            if ($data !== false) {
                return $data;
            }
        } else {
            $data = get_transient($cache_key);
            if ($data !== false) {
                return $data;
            }
        }

        // SINGLE MASTER QUERY - Index-aware optimized query
        $master_query = $wpdb->prepare("
            SELECT  
                p.ID,
                p.post_date,
                COALESCE(pm_status.meta_value, 'unknown') as status,
                COALESCE(pm_start.meta_value, 0) as start_ts,
                COALESCE(pm_end.meta_value, 0) as end_ts
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = %s
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND p.post_date >= %s 
            AND p.post_date < %s
            ORDER BY p.post_date DESC
        ", 
        \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
        \MHMRentiva\Admin\Core\MetaKeys::BOOKING_START_TS,
        \MHMRentiva\Admin\Core\MetaKeys::BOOKING_END_TS,
        $start_date . ' 00:00:00', date('Y-m-d', strtotime($end_date) + 86400) . ' 00:00:00');
        
        $bookings = $wpdb->get_results($master_query);
        
        // PHP'de istatistikleri hesapla
        $data = self::calculate_stats_from_raw_data($bookings, $start_date, $end_date);

        // Cache'e kaydet
        if (self::is_class_available('Core\ObjectCache')) {
            \MHMRentiva\Core\ObjectCache::set($cache_key, $data, \MHMRentiva\Core\ObjectCache::GROUP_REPORTS, self::CACHE_DURATION_REPORTS);
        } else {
            set_transient($cache_key, $data, self::CACHE_DURATION_REPORTS);
        }

        return $data;
    }

    /**
     * Calculate statistics from raw data (in PHP)
     */
    private static function calculate_stats_from_raw_data(array $bookings, string $start_date, string $end_date): array
    {
        // Arrays for calculating statistics
        $status_counts = [];
        $daily_counts = [];
        $hourly_counts = [];
        $weekday_counts = [];
        $total_bookings = count($bookings);
        $cancelled_count = 0;
        $confirmed_count = 0;
        $duration_sum = 0;
        $duration_count = 0;

        foreach ($bookings as $booking) {
            $post_date = strtotime($booking->post_date);
            $status = $booking->status;
            $hour = (int) date('H', $post_date);
            $weekday = (int) date('w', $post_date); // 0=Pazar, 6=Cumartesi
            $day_key = date('Y-m-d', $post_date);

            // Status-based distribution
            $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;

            // Daily trend
            $daily_counts[$day_key] = ($daily_counts[$day_key] ?? 0) + 1;

            // Hourly distribution
            $hourly_counts[$hour] = ($hourly_counts[$hour] ?? 0) + 1;

            // Day of week distribution (convert to MySQL DAYOFWEEK format: 1=Sunday, 7=Saturday)
            $mysql_weekday = $weekday === 0 ? 1 : $weekday + 1;
            $weekday_counts[$mysql_weekday] = ($weekday_counts[$mysql_weekday] ?? 0) + 1;

            // Cancellation rates
            if ($status === 'cancelled') {
                $cancelled_count++;
            }

            // Conversion rates
            if ($status === 'confirmed') {
                $confirmed_count++;
            }

            // Average booking duration
            if ($booking->start_ts > 0 && $booking->end_ts > 0) {
                $duration_days = ($booking->end_ts - $booking->start_ts) / (24 * 60 * 60);
                if ($duration_days > 0) {
                    $duration_sum += $duration_days;
                    $duration_count++;
                }
            }
        }

        // Convert to status distribution format
        $status_distribution = [];
        foreach ($status_counts as $status => $count) {
            $status_distribution[] = (object) ['status' => $status, 'count' => $count];
        }
        usort($status_distribution, fn($a, $b) => $b->count <=> $a->count);

        // Convert to daily trend format
        $daily_trend = [];
        foreach ($daily_counts as $date => $count) {
            $daily_trend[] = (object) ['date' => $date, 'bookings' => $count];
        }
        usort($daily_trend, fn($a, $b) => $a->date <=> $b->date);

        // Convert to hourly distribution format
        $hourly_distribution = [];
        foreach ($hourly_counts as $hour => $count) {
            $hourly_distribution[] = (object) ['hour' => $hour, 'bookings' => $count];
        }
        usort($hourly_distribution, fn($a, $b) => $a->hour <=> $b->hour);

        // Convert to weekday distribution format
        $weekday_distribution = [];
        $weekday_labels = [
            1 => __('Sunday', 'mhm-rentiva'),
            2 => __('Monday', 'mhm-rentiva'),
            3 => __('Tuesday', 'mhm-rentiva'),
            4 => __('Wednesday', 'mhm-rentiva'),
            5 => __('Thursday', 'mhm-rentiva'),
            6 => __('Friday', 'mhm-rentiva'),
            7 => __('Saturday', 'mhm-rentiva'),
        ];
        
        foreach ($weekday_counts as $weekday => $count) {
            $weekday_distribution[] = (object) [
                'weekday' => $weekday,
                'bookings' => $count,
                'day_label' => $weekday_labels[$weekday] ?? $weekday
            ];
        }
        usort($weekday_distribution, fn($a, $b) => $a->weekday <=> $b->weekday);

        // Calculate rates
        $cancellation_rate = $total_bookings > 0 ? round(($cancelled_count / $total_bookings) * 100, 2) : 0;
        $conversion_rate = $total_bookings > 0 ? round(($confirmed_count / $total_bookings) * 100, 2) : 0;
        $avg_duration = $duration_count > 0 ? round($duration_sum / $duration_count, 1) : 0;

        return [
            'status_distribution' => $status_distribution,
            'daily_trend' => $daily_trend,
            'hourly_distribution' => $hourly_distribution,
            'weekday_distribution' => $weekday_distribution,
            'total_bookings' => $total_bookings,
            'cancelled_bookings' => $cancelled_count,
            'cancellation_rate' => $cancellation_rate,
            'conversion_rate' => $conversion_rate,
            'avg_duration' => $avg_duration,
            'date_range' => [
                'start' => $start_date,
                'end' => $end_date,
                'days' => (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1
            ]
        ];
    }

    public static function get_booking_trends(int $days = 30): array
    {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $current_period = self::get_data($start_date, $end_date);

        // Compare with previous period - optimize with single query
        $prev_start = date('Y-m-d', strtotime("-{$days} days", strtotime($start_date)));
        $prev_end = $start_date;

        // Cache check
        $cache_key = 'mhm_booking_trends_' . md5($prev_start . $prev_end);
        
        if (class_exists(\MHMRentiva\Core\ObjectCache::class)) {
            $prev_total = \MHMRentiva\Core\ObjectCache::get($cache_key, \MHMRentiva\Core\ObjectCache::GROUP_REPORTS);
        } else {
            $prev_total = get_transient($cache_key);
        }

        if ($prev_total === false) {
            global $wpdb;
            $prev_total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'vehicle_booking'
                 AND post_status = 'publish'
                 AND DATE(post_date) BETWEEN %s AND %s",
                $prev_start, $prev_end
            ));

            // Cache'e kaydet
            if (self::is_class_available('Core\ObjectCache')) {
                \MHMRentiva\Core\ObjectCache::set($cache_key, $prev_total, \MHMRentiva\Core\ObjectCache::GROUP_REPORTS, self::CACHE_DURATION_TRENDS);
            } else {
                set_transient($cache_key, $prev_total, self::CACHE_DURATION_TRENDS);
            }
        }

        $current_total = $current_period['total_bookings'];

        $change_percent = 0;
        if ($prev_total > 0) {
            $change_percent = round((($current_total - $prev_total) / $prev_total) * 100, 1);
        }

        return [
            'current_total' => $current_total,
            'previous_total' => $prev_total,
            'change_percent' => $change_percent,
            'trend' => $change_percent > 0 ? 'up' : ($change_percent < 0 ? 'down' : 'stable'),
            'current_cancellation_rate' => $current_period['cancellation_rate']
        ];
    }

    public static function get_peak_booking_times(string $start_date, string $end_date): array
    {
        // Calculate peak times from existing data (no extra query needed)
        $data = self::get_data($start_date, $end_date);
        
        // Get busiest hours from hourly distribution
        $hourly_distribution = $data['hourly_distribution'];
        usort($hourly_distribution, fn($a, $b) => $b->bookings <=> $a->bookings);
        $peak_hours = array_slice($hourly_distribution, 0, self::PEAK_RESULTS_LIMIT);

        // Get busiest days from daily trend
        $daily_trend = $data['daily_trend'];
        usort($daily_trend, fn($a, $b) => $b->bookings <=> $a->bookings);
        $peak_days = array_slice($daily_trend, 0, self::PEAK_RESULTS_LIMIT);

        return [
            'peak_hours' => $peak_hours,
            'peak_days' => $peak_days
        ];
    }

    public static function get_status_flow(string $start_date, string $end_date): array
    {
        // Mevcut data'dan status flow'u al (ekstra query'ye gerek yok)
        $data = self::get_data($start_date, $end_date);
        
        // Convert status distribution to status_flow format
        $status_flow = [];
        foreach ($data['status_distribution'] as $status_data) {
            $status_flow[] = (object) [
                'status' => $status_data->status,
                'count' => $status_data->count
            ];
        }

        return $status_flow;
    }

    public static function export_booking_data(string $start_date, string $end_date, string $format = 'csv'): void
    {
        global $wpdb;

        // Use background processing system (priority)
        if (self::is_class_available('Admin\Reports\BackgroundProcessor')) {
            $job_id = \MHMRentiva\Admin\Reports\BackgroundProcessor::queue_report_generation(
                'export_booking_data',
                [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'format' => $format
                ],
                get_current_user_id()
            );
            
            // Notify user of job ID
            if (self::is_class_available('Logs\AdvancedLogger')) {
                \MHMRentiva\Logs\AdvancedLogger::info("Background export job queued", [
                    'job_id' => $job_id,
                    'format' => $format,
                    'date_range' => "{$start_date} - {$end_date}",
                    'system' => 'background_processor'
                ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
            
            return;
        }

        // Fallback: Add bulk export job using queue system
        if (self::is_class_available('Core\QueueManager')) {
            $job_id = \MHMRentiva\Core\QueueManager::add_bulk_export_job(
                'booking_export',
                ['start_date' => $start_date, 'end_date' => $end_date],
                $format,
                get_current_user_id()
            );
            
            // Notify user of job ID
            if (self::is_class_available('Logs\AdvancedLogger')) {
                \MHMRentiva\Logs\AdvancedLogger::info("Export job queued", [
                    'job_id' => $job_id,
                    'format' => $format,
                    'date_range' => "{$start_date} - {$end_date}",
                    'system' => 'queue_manager'
                ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
            
            return;
        }

        // Fallback: Smart export (pagination for large datasets)
        $total_count = self::get_export_total_count($start_date, $end_date);
        
        if ($total_count > self::LARGE_DATASET_THRESHOLD) {
            // Large dataset - Use queue system
            if (self::is_class_available('Logs\AdvancedLogger')) {
                \MHMRentiva\Logs\AdvancedLogger::warning("Large dataset export requested", [
                    'total_count' => $total_count,
                    'date_range' => "{$start_date} - {$end_date}",
                    'format' => $format
                ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
            
            // Queue'ye ekle
            if (self::is_class_available('Core\QueueManager')) {
                $job_id = \MHMRentiva\Core\QueueManager::add_bulk_export_job(
                    'booking_export_large',
                    ['start_date' => $start_date, 'end_date' => $end_date, 'total_count' => $total_count],
                    $format,
                    get_current_user_id()
                );
                
                if (self::is_class_available('Logs\AdvancedLogger')) {
                    \MHMRentiva\Logs\AdvancedLogger::info("Large export job queued", [
                        'job_id' => $job_id,
                        'total_count' => $total_count
                    ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
                }
                return;
            }
        }
        
        // Small dataset - direct export
        $bookings = self::get_export_data_optimized($start_date, $end_date);

        $export_data = [];

        // Add headers
        $export_data[] = [
            'ID',
            'Title',
            'Tarih',
            'Vehicle_ID',
            'Durum',
            'Toplam_Fiyat',
            'Customer_Email',
            'Customer_Name',
            'Start_Date',
            'End_Date'
        ];

        // Add data
        foreach ($bookings as $booking) {
            $export_data[] = [
                $booking->ID,
                $booking->post_title,
                date('d.m.Y H:i', strtotime($booking->post_date)),
                $booking->vehicle_id,
                Status::get_label($booking->status),
                number_format($booking->total_price, 2, ',', '.'),
                $booking->customer_email,
                $booking->customer_name,
                $booking->start_date ? date('d.m.Y', $booking->start_date) : '',
                $booking->end_date ? date('d.m.Y', $booking->end_date) : ''
            ];
        }

        $filename = sprintf('mhm-rentiva-bookings-%s-%s', $start_date, $end_date);
        Export::export_data($export_data, $filename, $format);
    }

    /**
     * Optimized export data retrieval
     * Optimized query instead of 10 separate JOINs
     */
    private static function get_export_data_optimized(string $start_date, string $end_date): array
    {
        global $wpdb;

        // First get booking IDs (index-aware optimized)
        $booking_ids = $wpdb->get_col($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND p.post_date >= %s 
            AND p.post_date < %s
            ORDER BY p.post_date DESC
        ", $start_date . ' 00:00:00', date('Y-m-d', strtotime($end_date) + 86400) . ' 00:00:00'));

        if (empty($booking_ids)) {
            return [];
        }

        // Optimized meta query with IN() clause
        $placeholders = implode(',', array_fill(0, count($booking_ids), '%d'));
        
        $query = $wpdb->prepare("
            SELECT 
                p.ID, 
                p.post_title, 
                p.post_date,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as vehicle_id,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as status,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as total_price,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as customer_email,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as customer_name,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as start_date,
                MAX(CASE WHEN pm.meta_key = %s THEN pm.meta_value END) as end_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.ID IN ({$placeholders})
            AND pm.meta_key IN (
                %s, %s, %s, %s, %s, %s, %s
            )
            GROUP BY p.ID, p.post_title, p.post_date
            ORDER BY p.post_date DESC
        ", array_merge($booking_ids, [
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_VEHICLE_ID,
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_STATUS,
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_TOTAL_PRICE,
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_CONTACT_EMAIL,
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_CONTACT_NAME,
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_START_TS,
            \MHMRentiva\Admin\Core\MetaKeys::BOOKING_END_TS
        ]));
        
        return $wpdb->get_results($query);
    }

    /**
     * Pagination supported export (for large datasets)
     */
    private static function get_export_data_paginated(string $start_date, string $end_date, int $limit = null, int $offset = 0): array
    {
        global $wpdb;
        
        $limit = $limit ?? self::PAGINATION_LIMIT;

        $query = $wpdb->prepare("
            SELECT 
                p.ID, 
                p.post_title, 
                p.post_date,
                MAX(CASE WHEN pm.meta_key = '_mhm_vehicle_id' THEN pm.meta_value END) as vehicle_id,
                MAX(CASE WHEN pm.meta_key = '_mhm_status' THEN pm.meta_value END) as status,
                MAX(CASE WHEN pm.meta_key = '_mhm_total_price' THEN pm.meta_value END) as total_price,
                MAX(CASE WHEN pm.meta_key = '_mhm_contact_email' THEN pm.meta_value END) as customer_email,
                MAX(CASE WHEN pm.meta_key = '_mhm_contact_name' THEN pm.meta_value END) as customer_name,
                MAX(CASE WHEN pm.meta_key = '_mhm_start_ts' THEN pm.meta_value END) as start_date,
                MAX(CASE WHEN pm.meta_key = '_mhm_end_ts' THEN pm.meta_value END) as end_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND p.post_date >= %s 
            AND p.post_date < %s
            AND pm.meta_key IN (
                '_mhm_vehicle_id',
                '_mhm_status', 
                '_mhm_total_price',
                '_mhm_contact_email',
                '_mhm_contact_name',
                '_mhm_start_ts',
                '_mhm_end_ts'
            )
            GROUP BY p.ID, p.post_title, p.post_date
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ", $start_date . ' 00:00:00', date('Y-m-d', strtotime($end_date) + 86400) . ' 00:00:00', $limit, $offset);
        
        return $wpdb->get_results($query);
    }

    /**
     * Get total record count for export
     */
    private static function get_export_total_count(string $start_date, string $end_date): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND p.post_date >= %s 
            AND p.post_date < %s
        ", $start_date . ' 00:00:00', date('Y-m-d', strtotime($end_date) + 86400) . ' 00:00:00'));
    }
}
