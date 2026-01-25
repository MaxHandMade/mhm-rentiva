<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Core;

use MHMRentiva\Admin\Messages\Core\MessageCache;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimized database queries for the messaging system
 */
final class MessageQueryHelper
{
    /**
     * Optimized message list query
     */
    public static function get_messages_query(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'post_type' => 'mhm_message',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'DESC',
            'status_filter' => '',
            'category_filter' => '',
            'customer_email' => '',
            'thread_id' => 0,
            'unread_only' => false,
            'parent_only' => true, // Varsayılan olarak sadece ana mesajları göster
        ];

        // Ensure posts_per_page is never negative or zero
        if (isset($args['posts_per_page']) && ($args['posts_per_page'] <= 0 || $args['posts_per_page'] === -1)) {
            $args['posts_per_page'] = 20;
        }

        // Ensure offset is never negative
        if (isset($args['offset']) && $args['offset'] < 0) {
            $args['offset'] = 0;
        }

        $args = array_merge($defaults, $args);

        // Cache kontrolü - POST isteklerinde, updated parametresi geldiğinde cache bypass
        // parent_only artık cache'i bypass etmiyor çünkü normal kullanım
        $cache_key = 'messages_query_' . md5(serialize($args));
        $use_cache = !isset($_POST['action']) && !isset($_POST['action2']) && !isset($_GET['updated']) && !isset($_GET['message']);

        if ($use_cache) {
            $cached_result = MessageCache::get($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }
        }

        // Base query
        $select = "SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_date, p.post_author,
                          pm_customer_name.meta_value as customer_name,
                          pm_customer_email.meta_value as customer_email,
                          pm_category.meta_value as category,
                          pm_status.meta_value as status,
                          pm_thread_id.meta_value as thread_id,
                          pm_booking_id.meta_value as booking_id,
                          pm_is_read.meta_value as is_read,
                          COALESCE(pm_parent_message_id.meta_value, '0') as parent_message_id";

        $from = "FROM {$wpdb->posts} p";

        // Meta joins
        $joins = [
            "LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'",
            "LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'",
            "LEFT JOIN {$wpdb->postmeta} pm_category ON p.ID = pm_category.post_id AND pm_category.meta_key = '_mhm_message_category'",
            "LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'",
            "LEFT JOIN {$wpdb->postmeta} pm_thread_id ON p.ID = pm_thread_id.post_id AND pm_thread_id.meta_key = '_mhm_thread_id'",
            "LEFT JOIN {$wpdb->postmeta} pm_booking_id ON p.ID = pm_booking_id.post_id AND pm_booking_id.meta_key = '_mhm_booking_id'",
            "LEFT JOIN {$wpdb->postmeta} pm_is_read ON p.ID = pm_is_read.post_id AND pm_is_read.meta_key = '_mhm_is_read'",
            "LEFT JOIN {$wpdb->postmeta} pm_parent_message_id ON p.ID = pm_parent_message_id.post_id AND pm_parent_message_id.meta_key = '_mhm_parent_message_id'"
        ];

        $where_conditions = [
            $wpdb->prepare("p.post_type = %s", $args['post_type']),
            $wpdb->prepare("p.post_status = %s", $args['post_status'])
        ];

        // Filters
        if (!empty($args['status_filter'])) {
            $where_conditions[] = $wpdb->prepare("pm_status.meta_value = %s", $args['status_filter']);
        }

        if (!empty($args['category_filter'])) {
            $where_conditions[] = $wpdb->prepare("pm_category.meta_value = %s", $args['category_filter']);
        }

        if (!empty($args['customer_email'])) {
            $where_conditions[] = $wpdb->prepare("pm_customer_email.meta_value = %s", $args['customer_email']);
        }

        if ($args['thread_id'] > 0) {
            $where_conditions[] = $wpdb->prepare("pm_thread_id.meta_value = %d", $args['thread_id']);
        }

        if ($args['unread_only']) {
            $where_conditions[] = "(pm_is_read.meta_value IS NULL OR pm_is_read.meta_value != '1')";
        }

        // Show only parent messages (if parent_message_id is null or 0)
        // Disable parent_only if thread_id is filtered (all messages in thread should be shown)
        if (!empty($args['parent_only']) && $args['thread_id'] === 0) {
            $where_conditions[] = "(pm_parent_message_id.meta_value IS NULL OR pm_parent_message_id.meta_value = '' OR pm_parent_message_id.meta_value = '0')";
        }

        $where = "WHERE " . implode(' AND ', $where_conditions);

        // Order by
        $orderby_map = [
            'date' => 'p.post_date',
            'title' => 'p.post_title',
            'customer' => 'pm_customer_name.meta_value',
            'status' => 'pm_status.meta_value',
            'category' => 'pm_category.meta_value'
        ];

        $orderby_field = $orderby_map[$args['orderby']] ?? 'p.post_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = "ORDER BY {$orderby_field} {$order}";

        // Limit
        $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", $args['posts_per_page'], $args['offset']);

        // Count query
        $count_query = "SELECT COUNT(DISTINCT p.ID) {$from} " . implode(' ', $joins) . " {$where}";

        // Main query
        $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", (int)$args['posts_per_page'], (int)$args['offset']);
        $main_query = "{$select} {$from} " . implode(' ', $joins) . " {$where} {$order_clause} {$limit_clause}";

        $total_count = (int) $wpdb->get_var($count_query);
        $messages = $wpdb->get_results($main_query);

        $result = [
            'messages' => $messages,
            'total' => $total_count,
            'pages' => ceil($total_count / $args['posts_per_page']),
            'current_page' => floor($args['offset'] / $args['posts_per_page']) + 1
        ];

        // Cache'e kaydet (5 dakika) - sadece GET isteklerinde cache kullan
        if ($use_cache) {
            MessageCache::set($cache_key, $result, [], 300);
        }

        return $result;
    }

    /**
     * Optimized message statistics query
     */
    public static function get_message_stats(): array
    {
        global $wpdb;

        // Cache control - updated parameter bypasses cache
        $cache_key = 'message_stats';
        $use_cache = !isset($_GET['updated']) && !isset($_GET['message']);

        if ($use_cache) {
            $cached_result = MessageCache::get($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }
        }

        // Basic statistics - ONLY PARENT MESSAGES (parent_only)
        $stats_query = "
            SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN pm_status.meta_value = 'pending' THEN 1 ELSE 0 END) as pending_messages,
                SUM(CASE WHEN pm_status.meta_value = 'answered' THEN 1 ELSE 0 END) as answered_messages,
                SUM(CASE WHEN pm_status.meta_value = 'closed' THEN 1 ELSE 0 END) as closed_messages,
                SUM(CASE WHEN pm_is_read.meta_value IS NULL OR pm_is_read.meta_value != '1' THEN 1 ELSE 0 END) as unread_messages
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
            LEFT JOIN {$wpdb->postmeta} pm_is_read ON p.ID = pm_is_read.post_id AND pm_is_read.meta_key = '_mhm_is_read'
            LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_mhm_parent_message_id'
            WHERE p.post_type = 'mhm_message' 
            AND p.post_status = 'publish'
            AND (pm_parent.meta_value IS NULL OR pm_parent.meta_value = '' OR pm_parent.meta_value = '0')
        ";

        $result = $wpdb->get_row($stats_query, ARRAY_A);

        // Calculate average response time with a separate query
        // First calculate response time for each answered message, then take AVG
        $response_time_query = "
            SELECT 
                p.ID,
                p.post_date as message_date,
                pm_thread_id.meta_value as thread_id,
                (SELECT MIN(p2.post_date)
                 FROM {$wpdb->posts} p2
                 INNER JOIN {$wpdb->postmeta} pm2_thread ON p2.ID = pm2_thread.post_id AND pm2_thread.meta_key = '_mhm_thread_id'
                 INNER JOIN {$wpdb->postmeta} pm2_type ON p2.ID = pm2_type.post_id AND pm2_type.meta_key = '_mhm_message_type'
                 WHERE pm2_thread.meta_value = pm_thread_id.meta_value
                 AND p2.post_type = 'mhm_message'
                 AND p2.post_status = 'publish'
                 AND p2.post_author != 0
                 AND pm2_type.meta_value = 'admin_to_customer'
                 AND p2.post_date > p.post_date
                ) as reply_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
            LEFT JOIN {$wpdb->postmeta} pm_thread_id ON p.ID = pm_thread_id.post_id AND pm_thread_id.meta_key = '_mhm_thread_id'
            LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_mhm_parent_message_id'
            WHERE p.post_type = 'mhm_message'
            AND p.post_status = 'publish'
            AND (pm_parent.meta_value IS NULL OR pm_parent.meta_value = '' OR pm_parent.meta_value = '0')
            AND pm_status.meta_value = 'answered'
        ";

        $response_data = $wpdb->get_results($response_time_query);

        // PHP tarafında ortalama hesapla
        $response_times = [];
        foreach ($response_data as $row) {
            if (!empty($row->reply_date) && !empty($row->message_date)) {
                $hours = (strtotime($row->reply_date) - strtotime($row->message_date)) / 3600;
                if ($hours > 0) {
                    $response_times[] = $hours;
                }
            }
        }

        $response_result = !empty($response_times) ? (array_sum($response_times) / count($response_times)) : null;

        // Calculate percentages
        $total = (int) $result['total_messages'];
        $pending = (int) $result['pending_messages'];
        $answered = (int) $result['answered_messages'];
        $unread = (int) $result['unread_messages'];

        // Convert avg_response_time_hours to float (could be string)
        $avg_response_time = isset($response_result) && $response_result !== null && $response_result !== ''
            ? (float) $response_result
            : 0;

        $stats = [
            'total_messages' => $total,
            'pending_messages' => $pending,
            'answered_messages' => $answered,
            'closed_messages' => (int) ($result['closed_messages'] ?? 0),
            'unread_messages' => $unread,
            'pending_percentage' => $total > 0 ? round(($pending / $total) * 100) : 0,
            'answered_percentage' => $total > 0 ? round(($answered / $total) * 100) : 0,
            'avg_response_time' => $avg_response_time > 0 ? round($avg_response_time) . 'h' : '0'
        ];

        // Cache'e kaydet (10 dakika)
        MessageCache::set($cache_key, $stats, [], 600);

        return $stats;
    }

    /**
     * Check customer message limits
     */
    public static function check_customer_limits(string $email): array
    {
        global $wpdb;

        // Cache control
        $cache_key = 'customer_limits';
        $params = ['email' => $email];
        $cached_result = MessageCache::get($cache_key, $params);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Get message counts sent this month and today in a single query
        $limits_query = $wpdb->prepare("
            SELECT 
                COUNT(CASE WHEN DATE_FORMAT(p.post_date, '%%Y-%%m') = DATE_FORMAT(NOW(), '%%Y-%%m') THEN 1 END) as this_month_count,
                COUNT(CASE WHEN DATE(p.post_date) = CURDATE() THEN 1 END) as today_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'mhm_message'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_mhm_customer_email'
            AND pm.meta_value = %s
        ", $email);

        $result = $wpdb->get_row($limits_query, ARRAY_A);

        $limits = [
            'this_month_count' => (int) $result['this_month_count'],
            'today_count' => (int) $result['today_count']
        ];

        // Cache'e kaydet (1 saat)
        MessageCache::set($cache_key, $limits, $params, 3600);

        return $limits;
    }

    /**
     * Retrieve thread messages in an optimized way
     */
    public static function get_thread_messages(int $thread_id): array
    {
        global $wpdb;

        // Cache control
        $cache_key = 'thread_messages_optimized';
        $params = ['thread_id' => $thread_id];
        $cached_result = MessageCache::get($cache_key, $params);

        if ($cached_result !== false) {
            return $cached_result;
        }

        $query = $wpdb->prepare("
            SELECT p.*, 
                   pm_customer_name.meta_value as customer_name,
                   pm_customer_email.meta_value as customer_email,
                   pm_message_type.meta_value as message_type,
                   pm_status.meta_value as status,
                   pm_booking_id.meta_value as booking_id,
                   pm_attachments.meta_value as attachments
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_message_type ON p.ID = pm_message_type.post_id AND pm_message_type.meta_key = '_mhm_message_type'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
            LEFT JOIN {$wpdb->postmeta} pm_booking_id ON p.ID = pm_booking_id.post_id AND pm_booking_id.meta_key = '_mhm_booking_id'
            LEFT JOIN {$wpdb->postmeta} pm_attachments ON p.ID = pm_attachments.post_id AND pm_attachments.meta_key = '_mhm_attachments'
            INNER JOIN {$wpdb->postmeta} pm_thread ON p.ID = pm_thread.post_id AND pm_thread.meta_key = '_mhm_thread_id'
            WHERE pm_thread.meta_value = %d
            AND p.post_type = 'mhm_message'
            AND p.post_status = 'publish'
            ORDER BY p.post_date ASC
        ", $thread_id);

        $messages = $wpdb->get_results($query);

        // Cache'e kaydet (30 dakika)
        MessageCache::set($cache_key, $messages, $params, 1800);

        return $messages;
    }

    /**
     * Message search query
     */
    public static function search_messages(string $search_term, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'posts_per_page' => 20,
            'offset' => 0,
            'search_in' => ['title', 'content', 'customer_name', 'customer_email'] // Fields to search in
        ];

        $args = array_merge($defaults, $args);

        $search_conditions = [];
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';

        if (in_array('title', $args['search_in'])) {
            $search_conditions[] = $wpdb->prepare("p.post_title LIKE %s", $search_term);
        }

        if (in_array('content', $args['search_in'])) {
            $search_conditions[] = $wpdb->prepare("p.post_content LIKE %s", $search_term);
        }

        if (in_array('customer_name', $args['search_in'])) {
            $search_conditions[] = $wpdb->prepare("pm_customer_name.meta_value LIKE %s", $search_term);
        }

        if (in_array('customer_email', $args['search_in'])) {
            $search_conditions[] = $wpdb->prepare("pm_customer_email.meta_value LIKE %s", $search_term);
        }

        if (empty($search_conditions)) {
            return ['messages' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1];
        }

        $where_conditions = [
            "p.post_type = 'mhm_message'",
            "p.post_status = 'publish'",
            "(" . implode(' OR ', $search_conditions) . ")"
        ];

        $limit_clause = $wpdb->prepare("LIMIT %d OFFSET %d", (int)$args['posts_per_page'], (int)$args['offset']);

        $query = "
            SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_date,
                   pm_customer_name.meta_value as customer_name,
                   pm_customer_email.meta_value as customer_email,
                   pm_category.meta_value as category,
                   pm_status.meta_value as status
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'
            LEFT JOIN {$wpdb->postmeta} pm_category ON p.ID = pm_category.post_id AND pm_category.meta_key = '_mhm_message_category'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_message_status'
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY p.post_date DESC
            {$limit_clause}
        ";

        $count_query = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer_name ON p.ID = pm_customer_name.post_id AND pm_customer_name.meta_key = '_mhm_customer_name'
            LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id AND pm_customer_email.meta_key = '_mhm_customer_email'
            WHERE " . implode(' AND ', $where_conditions);

        $total_count = (int) $wpdb->get_var($count_query);
        $messages = $wpdb->get_results($query);

        return [
            'messages' => $messages,
            'total' => $total_count,
            'pages' => ceil($total_count / $args['posts_per_page']),
            'current_page' => floor($args['offset'] / $args['posts_per_page']) + 1,
            'search_term' => $search_term
        ];
    }

    /**
     * Database performance statistics
     */
    public static function get_query_performance_stats(): array
    {
        global $wpdb;

        return [
            'total_queries' => $wpdb->num_queries,
            'cache_hits' => wp_cache_get('mhm_messages_cache_hits', 'mhm_messages') ?: 0,
            'cache_misses' => wp_cache_get('mhm_messages_cache_misses', 'mhm_messages') ?: 0,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
    }
}
