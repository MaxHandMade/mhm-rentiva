<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Export;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Export History Management
 */
final class ExportHistory
{
    private const OPTION_NAME = 'mhm_rentiva_export_history';
    private const MAX_HISTORY_ITEMS = 100;

    /**
     * Add export to history
     */
    public static function add_export(array $export_data): void
    {
        $history = get_option(self::OPTION_NAME, []);
        
        $export_record = [
            'id' => uniqid('export_'),
            'date' => current_time('mysql'),
            'type' => $export_data['type'] ?? 'unknown',
            'format' => $export_data['format'] ?? 'csv',
            'records' => $export_data['records'] ?? 0,
            'status' => $export_data['status'] ?? 'completed',
            'user_id' => get_current_user_id(),
            'user_name' => wp_get_current_user()->display_name ?? 'Unknown',
            'filename' => $export_data['filename'] ?? '',
            'file_size' => $export_data['file_size'] ?? 0,
            'filters' => $export_data['filters'] ?? [],
            'duration' => $export_data['duration'] ?? 0,
        ];

        // Add to beginning of array
        array_unshift($history, $export_record);

        // Limit history size
        if (count($history) > self::MAX_HISTORY_ITEMS) {
            $history = array_slice($history, 0, self::MAX_HISTORY_ITEMS);
        }

        update_option(self::OPTION_NAME, $history);

        // Update statistics
        ExportStats::update_stats(
            $export_record['type'],
            $export_record['format'],
            $export_record['records'],
            $export_record['status'] === 'completed'
        );
    }

    /**
     * Get export history
     */
    public static function get_history(int $limit = 50): array
    {
        $history = get_option(self::OPTION_NAME, []);
        
        if ($limit > 0) {
            $history = array_slice($history, 0, $limit);
        }
        
        return $history;
    }

    /**
     * Get export by ID
     */
    public static function get_export_by_id(string $export_id): ?array
    {
        $history = self::get_history();
        
        foreach ($history as $export) {
            if ($export['id'] === $export_id) {
                return $export;
            }
        }
        
        return null;
    }

    /**
     * Delete export from history
     */
    public static function delete_export(string $export_id): bool
    {
        $history = get_option(self::OPTION_NAME, []);
        
        foreach ($history as $key => $export) {
            if ($export['id'] === $export_id) {
                unset($history[$key]);
                update_option(self::OPTION_NAME, array_values($history));
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear all export history
     */
    public static function clear_history(): void
    {
        delete_option(self::OPTION_NAME);
        
        // Also reset statistics
        ExportStats::reset_stats();
    }

    /**
     * Get export statistics from history
     */
    public static function get_history_stats(): array
    {
        $history = self::get_history();
        
        $stats = [
            'total_exports' => count($history),
            'successful_exports' => 0,
            'failed_exports' => 0,
            'total_records' => 0,
            'total_file_size' => 0,
            'formats' => [],
            'types' => [],
            'last_7_days' => 0,
            'last_30_days' => 0,
        ];

        $seven_days_ago = strtotime('-7 days');
        $thirty_days_ago = strtotime('-30 days');

        foreach ($history as $export) {
            // Count successful/failed exports
            if ($export['status'] === 'completed') {
                $stats['successful_exports']++;
            } else {
                $stats['failed_exports']++;
            }

            // Sum records and file size
            $stats['total_records'] += $export['records'];
            $stats['total_file_size'] += $export['file_size'];

            // Count formats
            $format = $export['format'];
            $stats['formats'][$format] = ($stats['formats'][$format] ?? 0) + 1;

            // Count types
            $type = $export['type'];
            $stats['types'][$type] = ($stats['types'][$type] ?? 0) + 1;

            // Count recent exports
            $export_time = strtotime($export['date']);
            if ($export_time >= $seven_days_ago) {
                $stats['last_7_days']++;
            }
            if ($export_time >= $thirty_days_ago) {
                $stats['last_30_days']++;
            }
        }

        // Calculate success rate
        $total = $stats['total_exports'];
        $stats['success_rate'] = $total > 0 ? round(($stats['successful_exports'] / $total) * 100, 1) : 0;

        return $stats;
    }

    /**
     * Render export history table
     */
    public static function render_history_table(): void
    {
        $history = self::get_history(20); // Last 20 exports
        
        if (empty($history)) {
            echo '<div class="notice notice-info inline">';
            echo '<p>' . esc_html__('No export history found.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Date', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Type', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Format', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Records', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('User', 'mhm-rentiva') . '</th>';
        echo '<th>' . esc_html__('Actions', 'mhm-rentiva') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($history as $export) {
            $status_class = $export['status'] === 'completed' ? 'status-completed' : 'status-failed';
            $status_text = $export['status'] === 'completed' ? 
                __('Completed', 'mhm-rentiva') : 
                __('Failed', 'mhm-rentiva');

            echo '<tr>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($export['date']))) . '</td>';
            echo '<td>' . esc_html(ucfirst(str_replace('_', ' ', $export['type']))) . '</td>';
            echo '<td>' . esc_html(strtoupper($export['format'])) . '</td>';
            echo '<td>' . esc_html(number_format($export['records'])) . '</td>';
            echo '<td><span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
            echo '<td>' . esc_html($export['user_name']) . '</td>';
            echo '<td>';
            echo '<button type="button" class="button button-small" onclick="viewExportDetails(\'' . esc_js($export['id']) . '\')">';
            echo esc_html__('View Details', 'mhm-rentiva');
            echo '</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Get export types for filtering
     */
    public static function get_export_types(): array
    {
        return [
            'vehicle_booking' => __('Bookings', 'mhm-rentiva'),
            'mhm_payment_log' => __('Payment Logs', 'mhm-rentiva'),
            'reports' => __('Reports', 'mhm-rentiva'),
        ];
    }

    /**
     * Get export formats for filtering
     */
    public static function get_export_formats(): array
    {
        return [
            'csv' => 'CSV',
            'xls' => 'Excel',
            'json' => 'JSON',
            'xml' => 'XML',
        ];
    }
}
