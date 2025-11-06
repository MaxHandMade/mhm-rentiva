<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\BusinessLogic;

if (!defined('ABSPATH')) {
    exit;
}

final class RevenueReport
{
    public static function get_data(string $start_date, string $end_date): array
    {
        global $wpdb;

        $cache_key = 'mhm_revenue_report_' . md5($start_date . $end_date);
        $data = get_transient($cache_key);

        if ($data === false) {
            // ✅ OPTIMIZED QUERY - Günlük gelir verisi (SADECE COMPLETED VE CONFIRMED)
            $daily_revenue = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(p.post_date) as date, SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as revenue
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_total_price'
                 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND pm_status.meta_value IN ('completed', 'confirmed')
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 GROUP BY DATE(p.post_date)
                 ORDER BY date",
                $start_date, $end_date
            ));

            // ✅ OPTIMIZED QUERY - Ödeme yöntemi dağılımı
            $payment_methods = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    COALESCE(pm_method.meta_value, 'unknown') as method, 
                    SUM(COALESCE(pm_price.meta_value, 0)) as revenue, 
                    COUNT(*) as count
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_mhm_total_price'
                 LEFT JOIN {$wpdb->postmeta} pm_method ON p.ID = pm_method.post_id AND pm_method.meta_key = '_mhm_payment_gateway'
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 GROUP BY pm_method.meta_value
                 HAVING method != 'unknown'
                 ORDER BY revenue DESC",
                $start_date, $end_date
            ));

            // Aylık karşılaştırma
            $monthly_comparison = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE_FORMAT(p.post_date, '%Y-%m') as month,
                        SUM(pm.meta_value) as revenue,
                        COUNT(*) as bookings
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_type = 'vehicle_booking'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = '_mhm_total_price'
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
                 ORDER BY month",
                $start_date, $end_date
            ));

            // Toplam gelir hesapla
            $total_revenue = array_sum(array_column($daily_revenue, 'revenue'));

            // Ödeme yöntemlerini formatla
            foreach ($payment_methods as &$method) {
                $method->method_label = self::get_payment_method_label($method->method);
                $method->percentage = $total_revenue > 0 ? round(($method->revenue / $total_revenue) * 100, 1) : 0;
            }

            $data = [
                'daily' => $daily_revenue,
                'methods' => $payment_methods,
                'monthly' => $monthly_comparison,
                'total' => $total_revenue,
                'avg_daily' => count($daily_revenue) > 0 ? $total_revenue / count($daily_revenue) : 0,
                'date_range' => [
                    'start' => $start_date,
                    'end' => $end_date,
                    'days' => (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1
                ]
            ];

            set_transient($cache_key, $data, 15 * MINUTE_IN_SECONDS);
        }

        return $data;
    }

    public static function get_payment_method_label(string $method): string
    {
        $labels = [
            'offline' => __('Havale/EFT', 'mhm-rentiva'),
            'paytr' => __('PayTR', 'mhm-rentiva'),
            'stripe' => __('Stripe', 'mhm-rentiva'),
            'paypal' => __('PayPal', 'mhm-rentiva'),
            'system' => __('Sistem', 'mhm-rentiva'),
            'my_account' => __('My Account', 'mhm-rentiva'),
        ];

        return $labels[$method] ?? ucfirst($method);
    }

    public static function get_revenue_by_period(string $start_date, string $end_date, string $period = 'daily'): array
    {
        global $wpdb;

        $date_format = match($period) {
            'monthly' => '%Y-%m',
            'weekly' => '%Y-%u',
            'yearly' => '%Y',
            default => '%Y-%m-%d'
        };

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(p.post_date, %s) as period,
                    SUM(pm.meta_value) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_mhm_total_price'
             AND DATE(p.post_date) BETWEEN %s AND %s
             GROUP BY DATE_FORMAT(p.post_date, %s)
             ORDER BY period",
            $date_format, $start_date, $end_date, $date_format
        ));

        return $data;
    }

    public static function get_revenue_trends(int $days = 30): array
    {
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $current_period = self::get_revenue_by_period($start_date, $end_date, 'daily');

        // Önceki dönem ile karşılaştırma
        $prev_start = date('Y-m-d', strtotime("-{$days} days", strtotime($start_date)));
        $prev_end = $start_date;
        $previous_period = self::get_revenue_by_period($prev_start, $prev_end, 'daily');

        $current_total = array_sum(array_column($current_period, 'revenue'));
        $previous_total = array_sum(array_column($previous_period, 'revenue'));

        $change_percent = 0;
        if ($previous_total > 0) {
            $change_percent = round((($current_total - $previous_total) / $previous_total) * 100, 1);
        }

        return [
            'current_period' => $current_period,
            'previous_period' => $previous_period,
            'current_total' => $current_total,
            'previous_total' => $previous_total,
            'change_percent' => $change_percent,
            'trend' => $change_percent > 0 ? 'up' : ($change_percent < 0 ? 'down' : 'stable')
        ];
    }

    public static function get_top_revenue_sources(string $start_date, string $end_date, int $limit = 10): array
    {
        global $wpdb;

        // Araç bazlı gelir
        $vehicle_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT pm_vehicle.meta_value as vehicle_id,
                    SUM(pm_price.meta_value) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->postmeta} pm_price
             INNER JOIN {$wpdb->postmeta} pm_vehicle ON pm_price.post_id = pm_vehicle.post_id
             INNER JOIN {$wpdb->posts} p ON pm_price.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_price.meta_key = '_mhm_total_price'
             AND pm_vehicle.meta_key = '_mhm_vehicle_id'
             AND DATE(p.post_date) BETWEEN %s AND %s
             GROUP BY pm_vehicle.meta_value
             ORDER BY revenue DESC
             LIMIT %d",
            $start_date, $end_date, $limit
        ));

        // Araç başlıklarını ekle
        foreach ($vehicle_revenue as &$vehicle) {
            $vehicle->vehicle_title = get_the_title($vehicle->vehicle_id) ?: __('Unknown Vehicle', 'mhm-rentiva');
        }

        return $vehicle_revenue;
    }

    public static function export_revenue_data(string $start_date, string $end_date, string $format = 'csv'): void
    {
        $data = self::get_data($start_date, $end_date);

        $filename = sprintf('mhm-rentiva-revenue-%s-%s', $start_date, $end_date);

        $export_data = [];

        // Başlık ekle
        $export_data[] = [
            __('Date', 'mhm-rentiva'),
            __('Revenue', 'mhm-rentiva'),
            __('Booking Count', 'mhm-rentiva')
        ];

        // Günlük verileri ekle
        foreach ($data['daily'] as $day) {
            $export_data[] = [
                $day->date,
                number_format($day->revenue, 2, '.', ''),
                '1' // Her gün için 1 kayıt varsayıyoruz
            ];
        }

        Export::export_data($export_data, $filename, $format);
    }
}
