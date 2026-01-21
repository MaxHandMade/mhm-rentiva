<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for Report Data
 * 
 * Centralizes all raw SQL queries used in reports to ensure security and maintainability.
 */
class ReportRepository
{
    /**
     * Get total bookings count
     */
    public static function get_total_bookings_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'vehicle_booking',
            'publish'
        ));
    }

    /**
     * Get monthly revenue amount (completed and confirmed only)
     */
    public static function get_monthly_revenue_amount(string $start_date, string $end_date): float
    {
        global $wpdb;
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_mhm_total_price'
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s
             AND p.post_date < %s",
            $start_date,
            $end_date
        ));
    }

    /**
     * Get active bookings count
     */
    public static function get_active_bookings_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_mhm_status'
             AND pm.meta_value IN ('confirmed', 'in_progress')"
        );
    }

    /**
     * Get total vehicles count
     */
    public static function get_total_vehicles_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'vehicle',
            'publish'
        ));
    }

    /**
     * Get daily revenue data
     */
    public static function get_daily_revenue_data(string $start_date, string $end_date): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(p.post_date) as date, SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as revenue
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_mhm_total_price'
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY DATE(p.post_date)
             ORDER BY date",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }

    /**
     * Get payment method distribution
     */
    public static function get_payment_method_distribution(string $start_date, string $end_date): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                COALESCE(pm_method.meta_value, 'unknown') as method, 
                SUM(COALESCE(pm_price.meta_value, 0)) as revenue, 
                COUNT(*) as count
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_mhm_total_price'
             LEFT JOIN {$wpdb->postmeta} pm_method ON p.ID = pm_method.post_id AND pm_method.meta_key = '_mhm_payment_gateway'
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY pm_method.meta_value
             HAVING method != 'unknown'
             ORDER BY revenue DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }

    /**
     * Get monthly revenue comparison
     */
    public static function get_monthly_revenue_comparison(string $start_date, string $end_date): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(p.post_date, '%Y-%m') as month,
                    SUM(pm.meta_value) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_mhm_total_price'
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY DATE_FORMAT(p.post_date, '%Y-%m')
             ORDER BY month",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }

    /**
     * Get revenue by period
     */
    public static function get_revenue_by_period(string $start_date, string $end_date, string $period = 'daily'): array
    {
        global $wpdb;

        $date_format = match ($period) {
            'monthly' => '%Y-%m',
            'weekly' => '%Y-%u',
            'yearly' => '%Y',
            default => '%Y-%m-%d'
        };

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(p.post_date, %s) as period,
                    SUM(pm.meta_value) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_mhm_total_price'
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY DATE_FORMAT(p.post_date, %s)
             ORDER BY period",
            $date_format,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            $date_format
        ));
    }

    /**
     * Get top revenue sources (vehicles)
     */
    public static function get_top_revenue_sources(string $start_date, string $end_date, int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
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
             AND p.post_date >= %s AND p.post_date <= %s
             GROUP BY pm_vehicle.meta_value
             ORDER BY revenue DESC
             LIMIT %d",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            $limit
        ));
    }

    /**
     * Get vehicle category performance
     */
    public static function get_vehicle_category_performance(string $start_date, string $end_date): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.name as category_name,
                COUNT(DISTINCT b.ID) as booking_count
            FROM {$wpdb->terms} t
            LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
                AND p.post_type = 'vehicle' 
                AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->posts} b ON p.ID = (
                SELECT pm_vehicle.meta_value 
                FROM {$wpdb->postmeta} pm_vehicle 
                WHERE pm_vehicle.post_id = b.ID 
                AND pm_vehicle.meta_key = '_mhm_vehicle_id'
            )
            AND b.post_type = 'vehicle_booking'
            AND b.post_status = 'publish'
            AND b.post_date >= %s AND b.post_date <= %s
            WHERE tt.taxonomy = 'vehicle_category'
            GROUP BY t.term_id, t.name
            ORDER BY booking_count DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }

    /**
     * Get customer spending data
     */
    public static function get_customer_spending_data(string $start_date, string $end_date): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                pm_email.meta_value as customer_email,
                pm_name.meta_value as customer_name,
                COUNT(*) as booking_count,
                SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id 
                AND pm_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id 
                AND pm_name.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id 
                AND pm_price.meta_key = '_mhm_total_price'
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND pm_email.meta_value IS NOT NULL
            AND pm_email.meta_value != ''
            AND p.post_date >= %s AND p.post_date <= %s
            GROUP BY pm_email.meta_value
            ORDER BY total_spent DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }
}
