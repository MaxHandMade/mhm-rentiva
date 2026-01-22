<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Reports\Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for Report Data
 * 
 * Centralizes all raw SQL queries used in reports.
 * Modernized to use custom `mhm_bookings` table for high performance.
 */
class ReportRepository
{
    /**
     * Get total bookings count
     */
    public static function get_total_bookings_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mhm_bookings WHERE status != 'trash'");
    }

    /**
     * Get monthly revenue amount (completed and confirmed only)
     */
    public static function get_monthly_revenue_amount(string $start_date, string $end_date): float
    {
        global $wpdb;
        return (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price)
             FROM {$wpdb->prefix}mhm_bookings
             WHERE status IN ('completed', 'confirmed')
             AND created_at >= %s
             AND created_at < %s",
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
            "SELECT COUNT(*) FROM {$wpdb->prefix}mhm_bookings
             WHERE status IN ('confirmed', 'in_progress')"
        );
    }

    /**
     * Get total vehicles count (Still using wp_posts for Vehicles)
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
            "SELECT DATE(created_at) as date, SUM(total_price) as revenue
             FROM {$wpdb->prefix}mhm_bookings
             WHERE status IN ('completed', 'confirmed')
             AND created_at >= %s AND created_at <= %s
             GROUP BY DATE(created_at)
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
                COALESCE(payment_method, 'unknown') as method, 
                SUM(total_price) as revenue, 
                COUNT(*) as count
             FROM {$wpdb->prefix}mhm_bookings
             WHERE status != 'trash'
             AND created_at >= %s AND created_at <= %s
             GROUP BY payment_method
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
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(total_price) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->prefix}mhm_bookings
             WHERE status IN ('completed', 'confirmed')
             AND created_at >= %s AND created_at <= %s
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
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
            "SELECT DATE_FORMAT(created_at, %s) as period,
                    SUM(total_price) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->prefix}mhm_bookings
             WHERE status IN ('completed', 'confirmed')
             AND created_at >= %s AND created_at <= %s
             GROUP BY DATE_FORMAT(created_at, %s)
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
            "SELECT vehicle_id,
                    SUM(total_price) as revenue,
                    COUNT(*) as bookings
             FROM {$wpdb->prefix}mhm_bookings
             WHERE status IN ('completed', 'confirmed')
             AND created_at >= %s AND created_at <= %s
             GROUP BY vehicle_id
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
                COUNT(b.id) as booking_count
            FROM {$wpdb->terms} t
            LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} v ON tr.object_id = v.ID 
                AND v.post_type = 'vehicle' 
                AND v.post_status = 'publish'
            LEFT JOIN {$wpdb->prefix}mhm_bookings b ON v.ID = b.vehicle_id
            WHERE tt.taxonomy = 'vehicle_category'
            AND b.status != 'trash'
            AND b.created_at >= %s AND b.created_at <= %s
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
                customer_email,
                customer_name,
                COUNT(*) as booking_count,
                SUM(total_price) as total_spent,
                MAX(created_at) as last_booking
            FROM {$wpdb->prefix}mhm_bookings
            WHERE status IN ('completed', 'confirmed')
            AND customer_email IS NOT NULL AND customer_email != ''
            AND created_at >= %s AND created_at <= %s
            GROUP BY customer_email, customer_name
            ORDER BY total_spent DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    }
    /**
     * Get upcoming operations (Rentals + Transfers)
     * 
     * @param int $limit Number of records to fetch per type
     * @return array Mixed array of operations sorted by date
     */
    public static function get_upcoming_operations(int $limit = 5): array
    {
        global $wpdb;
        $operations = [];
        $now = current_time('mysql');

        // 1. Rentals - mhm_bookings
        // Optimize: Suppress errors in case return_date column is missing in older DB versions
        $wpdb->suppress_errors();

        try {
            // Try to fetch with return_date
            $rentals = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    b.id, 
                    b.vehicle_id, 
                    p.post_title as vehicle_title,
                    b.customer_name, 
                    b.pickup_date as start_date, 
                    b.return_date as end_date,
                    b.status,
                    'rental' as type 
                FROM {$wpdb->prefix}mhm_bookings b
                LEFT JOIN {$wpdb->posts} p ON b.vehicle_id = p.ID
                WHERE b.status IN ('confirmed', 'pending', 'active') 
                AND b.pickup_date >= %s 
                ORDER BY b.pickup_date ASC
                LIMIT %d",
                $now,
                $limit
            ), ARRAY_A);

            if ($rentals) {
                $operations = array_merge($operations, $rentals);
            }
        } catch (\Exception $e) {
            // Fail silently
        }

        // 2. Transfers (if table exists)
        $transfer_table = $wpdb->prefix . 'mhm_transfers';
        if ($wpdb->get_var("SHOW TABLES LIKE '$transfer_table'") === $transfer_table) {
            $transfers = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    id, 
                    customer_name,
                    pickup_date as start_date,
                    origin,
                    destination,
                    status,
                    'transfer' as type
                FROM {$transfer_table}
                WHERE status IN ('confirmed', 'pending')
                AND pickup_date >= %s
                ORDER BY pickup_date ASC
                LIMIT %d",
                $now,
                $limit
            ), ARRAY_A);

            if ($transfers) {
                $operations = array_merge($operations, $transfers);
            }
        }

        $wpdb->suppress_errors(false);

        // Sort merged results by date
        usort($operations, function ($a, $b) {
            return strtotime($a['start_date']) - strtotime($b['start_date']);
        });

        return array_slice($operations, 0, $limit);
    }
}
