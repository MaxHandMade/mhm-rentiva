<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Reports;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ BACKGROUND PROCESSING - Queue System for Heavy Report Operations
 * 
 * Processes heavy report generation in the background
 */
final class BackgroundProcessor
{
    /**
     * Report types
     */
    private const REPORT_TYPES = [
        'booking_report' => 'Booking Report',
        'customer_report' => 'Customer Report', 
        'vehicle_report' => 'Vehicle Report',
        'revenue_report' => 'Revenue Report',
        'export_booking_data' => 'Booking Export',
        'export_customer_data' => 'Customer Export',
        'export_vehicle_data' => 'Vehicle Export',
        'bulk_operations' => 'Bulk Operations'
    ];
    
    /**
     * Class availability cache
     */
    private static ?bool $logger_available = null;
    
    /**
     * Checks if the logger is available (cached)
     * 
     * @return bool
     */
    private static function is_logger_available(): bool
    {
        if (self::$logger_available === null) {
            self::$logger_available = class_exists(\MHMRentiva\Logs\AdvancedLogger::class);
        }
        
        return self::$logger_available;
    }

    /**
     * Adds report generation to the background job queue
     */
    public static function queue_report_generation(string $report_type, array $params, int $user_id = 0): string
    {
        if (!isset(self::REPORT_TYPES[$report_type])) {
            /* translators: %s placeholder. */
            throw new \InvalidArgumentException(sprintf(__('Invalid report type: %s', 'mhm-rentiva'), $report_type));
        }

        $user_id = $user_id ?: get_current_user_id();
        
        // Generate Job ID
        $job_id = uniqid('mhm_report_', true);
        
        // Prepare Job data
        $job_data = [
            'job_id' => $job_id,
            'report_type' => $report_type,
            'params' => $params,
            'user_id' => $user_id,
            'created_at' => current_time('mysql'),
            'status' => 'pending',
            'progress' => 0,
            'estimated_time' => self::estimate_job_time($report_type, $params)
        ];

        // Save Job to database
        self::save_job_to_db($job_data);

        // Schedule background processing with WP Cron
        wp_schedule_single_event(
            time() + 10, // Start after 10 seconds
            'mhm_generate_report',
            [$job_id]
        );

        // Log job creation
        // Removed info log
        // if (self::is_logger_available()) {
        //     \MHMRentiva\Logs\AdvancedLogger::info('Background report job queued', [
        //         'job_id' => $job_id,
        //         'report_type' => $report_type,
        //         'user_id' => $user_id,
        //         'estimated_time' => $job_data['estimated_time']
        //     ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
        // }

        return $job_id;
    }

    /**
     * Process background job
     */
    public static function process_report_job(string $job_id): void
    {
        $job_data = self::get_job_from_db($job_id);
        
        if (!$job_data) {
            if (self::is_logger_available()) {
                \MHMRentiva\Logs\AdvancedLogger::error('Background job not found', [
                    'job_id' => $job_id
                ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
            return;
        }

        // Change Job status to processing
        self::update_job_status($job_id, 'processing', 10);

        try {
            // Process according to report type
            $result = self::generate_report_by_type($job_data);
            
            if ($result['success']) {
                self::update_job_status($job_id, 'completed', 100, $result['data']);
                
                // Send email to user
                self::notify_user_job_completed($job_data, $result);
                
                // Removed info log
                // if (self::is_logger_available()) {
                //     \MHMRentiva\Logs\AdvancedLogger::info('Background report job completed', [
                //         'job_id' => $job_id,
                //         'report_type' => $job_data['report_type'],
                //         'execution_time' => $result['execution_time'] ?? 0
                //     ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
                // }
            } else {
                self::update_job_status($job_id, 'failed', 0, null, $result['error']);
                
                if (self::is_logger_available()) {
                    \MHMRentiva\Logs\AdvancedLogger::error('Background report job failed', [
                        'job_id' => $job_id,
                        'report_type' => $job_data['report_type'],
                        'error' => $result['error']
                    ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
                }
            }

        } catch (\Exception $e) {
            self::update_job_status($job_id, 'failed', 0, null, $e->getMessage());
            
            if (self::is_logger_available()) {
                \MHMRentiva\Logs\AdvancedLogger::error('Background report job exception', [
                    'job_id' => $job_id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], \MHMRentiva\Logs\AdvancedLogger::CATEGORY_SYSTEM);
            }
        }
    }

    /**
     * Generation according to report type
     */
    private static function generate_report_by_type(array $job_data): array
    {
        $start_time = microtime(true);
        $report_type = $job_data['report_type'];
        $params = $job_data['params'];

        switch ($report_type) {
            case 'booking_report':
                $result = self::generate_booking_report($params);
                break;
                
            case 'customer_report':
                $result = self::generate_customer_report($params);
                break;
                
            case 'vehicle_report':
                $result = self::generate_vehicle_report($params);
                break;
                
            case 'revenue_report':
                $result = self::generate_revenue_report($params);
                break;
                
            case 'export_booking_data':
                $result = self::export_booking_data_background($params);
                break;
                
            case 'export_customer_data':
                $result = self::export_customer_data_background($params);
                break;
                
            case 'export_vehicle_data':
                $result = self::export_vehicle_data_background($params);
                break;
                
            case 'bulk_operations':
                $result = self::process_bulk_operations($params);
                break;
                
            default:
                /* translators: %s placeholder. */
                $result = ['success' => false, 'error' => sprintf(__('Unknown report type: %s', 'mhm-rentiva'), $report_type)];
        }

        $end_time = microtime(true);
        $result['execution_time'] = round(($end_time - $start_time) * 1000, 2);

        return $result;
    }

    /**
     * Booking report generation
     */
    private static function generate_booking_report(array $params): array
    {
        try {
            $start_date = $params['start_date'] ?? date('Y-m-01');
            $end_date = $params['end_date'] ?? date('Y-m-d');
            
            $data = \MHMRentiva\Admin\Reports\BusinessLogic\BookingReport::get_data($start_date, $end_date);
            
            // Save to cache
            $cache_key = "mhm_booking_report_bg_{$start_date}_{$end_date}";
            \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache_object($cache_key, $data, 24 * HOUR_IN_SECONDS);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'booking_report',
                    'cache_key' => $cache_key,
                    'total_bookings' => $data['total_bookings'] ?? 0,
                    'date_range' => $data['date_range'] ?? []
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Customer report generation
     */
    private static function generate_customer_report(array $params): array
    {
        try {
            $start_date = $params['start_date'] ?? date('Y-m-01');
            $end_date = $params['end_date'] ?? date('Y-m-d');
            
            $data = \MHMRentiva\Admin\Reports\BusinessLogic\CustomerReport::get_data($start_date, $end_date);
            
            // Save to cache
            $cache_key = "mhm_customer_report_bg_{$start_date}_{$end_date}";
            \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache_object($cache_key, $data, 24 * HOUR_IN_SECONDS);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'customer_report',
                    'cache_key' => $cache_key,
                    'total_customers' => $data['total_customers'] ?? 0,
                    'date_range' => $data['date_range'] ?? []
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Vehicle report generation
     */
    private static function generate_vehicle_report(array $params): array
    {
        try {
            $start_date = $params['start_date'] ?? date('Y-m-01');
            $end_date = $params['end_date'] ?? date('Y-m-d');
            
            $data = \MHMRentiva\Admin\Vehicle\Reports\VehicleReport::get_data($start_date, $end_date);
            
            // Save to cache
            $cache_key = "mhm_vehicle_report_bg_{$start_date}_{$end_date}";
            \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache_object($cache_key, $data, 24 * HOUR_IN_SECONDS);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'vehicle_report',
                    'cache_key' => $cache_key,
                    'total_vehicles' => $data['total_vehicles'] ?? 0,
                    'date_range' => $data['date_range'] ?? []
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revenue report generation
     */
    private static function generate_revenue_report(array $params): array
    {
        try {
            $start_date = $params['start_date'] ?? date('Y-m-01');
            $end_date = $params['end_date'] ?? date('Y-m-d');
            
            $data = \MHMRentiva\Admin\Reports\BusinessLogic\RevenueReport::get_data($start_date, $end_date);
            
            // Save to cache
            $cache_key = "mhm_revenue_report_bg_{$start_date}_{$end_date}";
            \MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache_object($cache_key, $data, 24 * HOUR_IN_SECONDS);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'revenue_report',
                    'cache_key' => $cache_key,
                    'total_revenue' => $data['total_revenue'] ?? 0,
                    'date_range' => $data['date_range'] ?? []
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Background export booking data - Export.php integration
     */
    private static function export_booking_data_background(array $params): array
    {
        try {
            $post_type = $params['post_type'] ?? 'vehicle_booking';
            $format = $params['format'] ?? 'csv';
            $query_args = $params['query_args'] ?? [];
            $user_id = $params['user_id'] ?? get_current_user_id();
            
            // ✅ Use stream methods from Export.php
            $filename_prefix = $post_type === 'vehicle_booking' ? 'bookings' : 'logs';
            $stamp = gmdate('Ymd-His');
            
            // Create file path
            $upload_dir = wp_upload_dir();
            $export_dir = $upload_dir['basedir'] . '/mhm-rentiva-exports/';
            
            if (!file_exists($export_dir)) {
                wp_mkdir_p($export_dir);
            }
            
            $filename = $export_dir . $filename_prefix . '-' . $stamp . '.' . $format;
            
            if ($format === 'csv') {
                $out = fopen($filename, 'w');
                if (!$out) {
                    throw new \Exception(__('Cannot create export file', 'mhm-rentiva'));
                }
                
                // UTF-8 BOM for Excel compatibility
                fwrite($out, "\xEF\xBB\xBF");
                
                // CSV headers
                if ($post_type === 'vehicle_booking') {
                    $headers = [
                        __('ID', 'mhm-rentiva'), __('Date', 'mhm-rentiva'), __('Status', 'mhm-rentiva'), __('Payment Status', 'mhm-rentiva'), __('Gateway', 'mhm-rentiva'),
                        __('Total', 'mhm-rentiva'), __('Paid Amount', 'mhm-rentiva'), __('Currency', 'mhm-rentiva'),
                        __('Name', 'mhm-rentiva'), __('Email', 'mhm-rentiva'), __('Phone', 'mhm-rentiva'),
                        __('PayTR OID', 'mhm-rentiva'), __('Stripe PI', 'mhm-rentiva'), __('Stripe Charge', 'mhm-rentiva'),
                    ];
                } else {
                    $headers = [
                        __('ID', 'mhm-rentiva'), __('Date', 'mhm-rentiva'), __('Gateway', 'mhm-rentiva'), __('Action', 'mhm-rentiva'), __('Status', 'mhm-rentiva'),
                        __('Booking ID', 'mhm-rentiva'), __('Code', 'mhm-rentiva'), __('OID', 'mhm-rentiva'),
                        __('Amount (kurus)', 'mhm-rentiva'), __('Currency', 'mhm-rentiva'), __('Message', 'mhm-rentiva'),
                    ];
                }
                fputcsv($out, $headers);
                
                // Data export
                $paged = 1;
                do {
                    $q = new \WP_Query(array_merge($query_args, ['paged' => $paged]));
                    if (!$q->have_posts()) break;
                    
                    foreach ($q->posts as $pid) {
                        $pid = (int) $pid;
                        if ($post_type === 'vehicle_booking') {
                            $date   = get_post($pid)->post_date_gmt;
                            $status = (string) get_post_meta($pid, '_mhm_status', true);
                            $pstat  = (string) get_post_meta($pid, '_mhm_payment_status', true);
                            $gw     = (string) get_post_meta($pid, '_mhm_payment_gateway', true);
                            $total  = (float) get_post_meta($pid, '_mhm_total_price', true);
                            $paidk  = (int) get_post_meta($pid, '_mhm_payment_amount', true);
                            $cur    = (string) get_post_meta($pid, '_mhm_payment_currency', true);
                            $name   = (string) get_post_meta($pid, '_mhm_contact_name', true);
                            $email  = (string) get_post_meta($pid, '_mhm_contact_email', true);
                            $phone  = (string) get_post_meta($pid, '_mhm_contact_phone', true);
                            $oid    = (string) get_post_meta($pid, '_mhm_paytr_merchant_oid', true);
                            $pi     = (string) get_post_meta($pid, '_mhm_stripe_payment_intent', true);
                            $chg    = (string) get_post_meta($pid, '_mhm_stripe_charge_id', true);
                            
                            fputcsv($out, [
                                $pid, $date, $status, $pstat, $gw,
                                number_format($total, 2, '.', ''),
                                number_format($paidk / 100, 2, '.', ''),
                                strtoupper($cur ?: ''),
                                $name, $email, $phone, $oid, $pi, $chg,
                            ]);
                        } else {
                            // Log export logic
                            $p = get_post($pid);
                            $date = $p ? $p->post_date_gmt : '';
                            $gw = (string) get_post_meta($pid, '_mhm_log_gateway', true);
                            $action = (string) get_post_meta($pid, '_mhm_log_action', true);
                            $status = (string) get_post_meta($pid, '_mhm_log_status', true);
                            $booking_id = (string) get_post_meta($pid, '_mhm_log_booking_id', true);
                            $code = (string) get_post_meta($pid, '_mhm_log_code', true);
                            $oid = (string) get_post_meta($pid, '_mhm_log_oid', true);
                            $amount = (int) get_post_meta($pid, '_mhm_log_amount_kurus', true);
                            $currency = (string) get_post_meta($pid, '_mhm_log_currency', true);
                            $message = (string) get_post_meta($pid, '_mhm_log_message', true);
                            
                            fputcsv($out, [
                                $pid, $date, $gw, $action, $status,
                                $booking_id, $code, $oid,
                                $amount, $currency, $message
                            ]);
                        }
                    }
                    $paged++;
                } while ($paged <= $q->max_num_pages);
                
                fclose($out);
            }
            
            // ✅ Send email to user
            self::send_export_email($user_id, $filename, $filename_prefix, $format);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'export_' . $post_type . '_data',
                    'format' => $format,
                    'filename' => basename($filename),
                    'file_path' => $filename,
                    'download_url' => $upload_dir['baseurl'] . '/mhm-rentiva-exports/' . basename($filename)
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Background export customer data
     */
    private static function export_customer_data_background(array $params): array
    {
        try {
            $start_date = $params['start_date'] ?? date('Y-m-01');
            $end_date = $params['end_date'] ?? date('Y-m-d');
            $format = $params['format'] ?? 'csv';
            
            // Run export in background
            \MHMRentiva\Admin\Reports\BusinessLogic\CustomerReport::export_customer_data($start_date, $end_date, $format);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'export_customer_data',
                    'format' => $format,
                    'date_range' => "{$start_date} - {$end_date}"
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Background export vehicle data
     */
    private static function export_vehicle_data_background(array $params): array
    {
        try {
            $start_date = $params['start_date'] ?? date('Y-m-01');
            $end_date = $params['end_date'] ?? date('Y-m-d');
            $format = $params['format'] ?? 'csv';
            
            // Run export in background
            \MHMRentiva\Admin\Vehicle\Reports\VehicleReport::export_vehicle_data($start_date, $end_date, $format);
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'export_vehicle_data',
                    'format' => $format,
                    'date_range' => "{$start_date} - {$end_date}"
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bulk operations processing
     */
    private static function process_bulk_operations(array $params): array
    {
        try {
            $operation = $params['operation'] ?? '';
            $ids = $params['ids'] ?? [];
            
            if (empty($operation) || empty($ids)) {
                return ['success' => false, 'error' => __('Invalid bulk operation parameters', 'mhm-rentiva')];
            }
            
            $processed = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    switch ($operation) {
                        case 'delete_bookings':
                            wp_delete_post($id, true);
                            $processed++;
                            break;
                            
                        case 'update_status':
                            $status = $params['status'] ?? __('confirmed', 'mhm-rentiva');
                            update_post_meta($id, '_mhm_status', $status);
                            $processed++;
                            break;
                            
                        case 'bulk_export':
                            // Bulk export operation
                            $processed++;
                            break;
                            
                        default:
                            /* translators: %s placeholder. */
                            $errors[] = sprintf(__('Unknown operation: %s', 'mhm-rentiva'), $operation);
                    }
                } catch (\Exception $e) {
                    /* translators: 1: %d; 2: %s. */
                    $errors[] = sprintf(__('Error processing ID %1$d: %2$s', 'mhm-rentiva'), $id, $e->getMessage());
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'report_type' => 'bulk_operations',
                    'operation' => $operation,
                    'processed' => $processed,
                    'errors' => $errors
                ]
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Job execution time estimation
     */
    private static function estimate_job_time(string $report_type, array $params): int
    {
        $base_times = [
            'booking_report' => 30,
            'customer_report' => 20,
            'vehicle_report' => 25,
            'revenue_report' => 35,
            'export_booking_data' => 60,
            'export_customer_data' => 40,
            'export_vehicle_data' => 45,
            'bulk_operations' => 90
        ];

        $base_time = $base_times[$report_type] ?? 30;
        
        // Time estimation based on date range
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $days = (strtotime($params['end_date']) - strtotime($params['start_date'])) / (60 * 60 * 24);
            if ($days > 365) {
                $base_time *= 3; // More than 1 year
            } elseif ($days > 90) {
                $base_time *= 2; // More than 3 months
            }
        }

        // Based on ID count for bulk operations
        if ($report_type === 'bulk_operations' && isset($params['ids'])) {
            $count = count($params['ids']);
            $base_time += ($count * 0.5); // Add 0.5 seconds for each ID
        }

        return min($base_time, 300); // Max 5 minutes
    }

    /**
     * Save Job to database
     */
    private static function save_job_to_db(array $job_data): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'mhm_rentiva_background_jobs',
            [
                'job_id' => $job_data['job_id'],
                'report_type' => $job_data['report_type'],
                'params' => json_encode($job_data['params']),
                'user_id' => $job_data['user_id'],
                'status' => $job_data['status'],
                'progress' => $job_data['progress'],
                'estimated_time' => $job_data['estimated_time'],
                'created_at' => $job_data['created_at'],
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Get Job from database
     */
    private static function get_job_from_db(string $job_id): ?array
    {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mhm_rentiva_background_jobs WHERE job_id = %s",
            $job_id
        ));

        if (!$result) {
            return null;
        }

        return [
            'job_id' => $result->job_id,
            'report_type' => $result->report_type,
            'params' => json_decode($result->params, true),
            'user_id' => $result->user_id,
            'status' => $result->status,
            'progress' => $result->progress,
            'estimated_time' => $result->estimated_time,
            'created_at' => $result->created_at,
            'updated_at' => $result->updated_at
        ];
    }

    /**
     * Update Job status
     */
    private static function update_job_status(string $job_id, string $status, int $progress, ?array $result_data = null, ?string $error = null): void
    {
        global $wpdb;

        $update_data = [
            'status' => $status,
            'progress' => $progress,
            'updated_at' => current_time('mysql')
        ];

        if ($result_data !== null) {
            $update_data['result_data'] = json_encode($result_data);
        }

        if ($error !== null) {
            $update_data['error_message'] = $error;
        }

        $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_background_jobs',
            $update_data,
            ['job_id' => $job_id],
            array_fill(0, count($update_data), '%s'),
            ['%s']
        );
    }

    /**
     * Job completion notification to user
     */
    private static function notify_user_job_completed(array $job_data, array $result): void
    {
        $user = get_user_by('id', $job_data['user_id']);
        if (!$user) {
            return;
        }

        $subject = sprintf(
            /* translators: %s placeholder. */
            __('MHM Rentiva: %s Completed', 'mhm-rentiva'),
            self::REPORT_TYPES[$job_data['report_type']] ?? $job_data['report_type']
        );

        /* translators: 1: User name, 2: Report type, 3: Report type label, 4: Creation date, 5: Processing time */
        $message = sprintf(
            /* translators: 1: %1$s; 2: %2$s; 3: %3$s; 4: %4$s; 5: %5$s. */
            __('Hello %1$s,

Your %2$s report has been successfully completed.

Report Details:
- Report Type: %3$s
- Creation Date: %4$s
- Processing Time: %5$s ms

You can view the report by logging into the admin panel.

MHM Rentiva Team', 'mhm-rentiva'),
            $user->display_name,
            self::REPORT_TYPES[$job_data['report_type']] ?? $job_data['report_type'],
            self::REPORT_TYPES[$job_data['report_type']] ?? $job_data['report_type'],
            $job_data['created_at'],
            $result['execution_time'] ?? 0
        );

        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send email to user when export is complete
     */
    private static function send_export_email(int $user_id, string $file_path, string $file_prefix, string $format): void
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $download_url = $upload_dir['baseurl'] . '/mhm-rentiva-exports/' . basename($file_path);
        
        $subject = sprintf(
            /* translators: %s placeholder. */
            __('MHM Rentiva: %s Export Completed', 'mhm-rentiva'),
            ucfirst($file_prefix)
        );

        /* translators: 1: User name, 2: Data type, 3: File name, 4: Format, 5: Creation date, 6: Download URL */
        $message = sprintf(
            /* translators: 1: %1$s; 2: %2$s; 3: %3$s; 4: %4$s; 5: %5$s; 6: %6$s. */
            __('Hello %1$s,

Your %2$s data export has been successfully completed.

File Details:
- File Name: %3$s
- Format: %4$s
- Creation Date: %5$s

You can click the link below to download the file:
%6$s

Note: This file will be accessible for 7 days.

MHM Rentiva Team', 'mhm-rentiva'),
            $user->display_name,
            ucfirst($file_prefix),
            basename($file_path),
            strtoupper($format),
            current_time('Y-m-d H:i:s'),
            $download_url
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Get Job status
     */
    public static function get_job_status(string $job_id): ?array
    {
        return self::get_job_from_db($job_id);
    }

    /**
     * List user's jobs
     */
    public static function get_user_jobs(int $user_id, int $limit = 10): array
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mhm_rentiva_background_jobs 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));

        $jobs = [];
        foreach ($results as $result) {
            $jobs[] = [
                'job_id' => $result->job_id,
                'report_type' => $result->report_type,
                'status' => $result->status,
                'progress' => $result->progress,
                'created_at' => $result->created_at,
                'estimated_time' => $result->estimated_time,
                'execution_time' => $result->execution_time,
                'error_message' => $result->error_message
            ];
        }

        return $jobs;
    }

    // cleanup_old_jobs method removed - should be handled by centralized maintenance utilities

    /**
     * Create background jobs table
     */
    public static function create_background_jobs_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_background_jobs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_id varchar(255) NOT NULL,
            report_type varchar(100) NOT NULL,
            params longtext,
            user_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'pending',
            progress int(3) DEFAULT 0,
            estimated_time int(11) DEFAULT 30,
            execution_time int(11) DEFAULT NULL,
            result_data longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
