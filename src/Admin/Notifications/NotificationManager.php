<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Notifications;

use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Notification Manager
 * 
 * Handles customer notification frequency and scheduling
 * 
 * @since 4.0.0
 */
final class NotificationManager
{
    /**
     * Initialize notification management
     */
    public static function init(): void
    {
        add_action('init', [self::class, 'schedule_notifications']);
        add_action('mhm_send_scheduled_notifications', [self::class, 'process_notification_queue']);
    }

    /**
     * Get notification frequency setting
     */
    public static function get_notification_frequency(): string
    {
        return SettingsCore::get('mhm_rentiva_customer_notification_frequency', 'immediate');
    }

    /**
     * Schedule notifications based on frequency
     */
    public static function schedule_notifications(): void
    {
        if (!wp_next_scheduled('mhm_send_scheduled_notifications')) {
            $frequency = self::get_notification_frequency();
            
            switch ($frequency) {
                case 'immediate':
                    // Send immediately - no scheduling needed
                    break;
                case 'hourly':
                    wp_schedule_event(time(), 'hourly', 'mhm_send_scheduled_notifications');
                    break;
                case 'daily':
                    wp_schedule_event(time(), 'daily', 'mhm_send_scheduled_notifications');
                    break;
                case 'weekly':
                    wp_schedule_event(time(), 'weekly', 'mhm_send_scheduled_notifications');
                    break;
            }
        }
    }

    /**
     * Send notification based on frequency
     */
    public static function send_notification(string $type, int $user_id, array $data = []): bool
    {
        $frequency = self::get_notification_frequency();
        
        if ($frequency === 'immediate') {
            return self::send_immediate_notification($type, $user_id, $data);
        } else {
            return self::queue_notification($type, $user_id, $data);
        }
    }

    /**
     * Send immediate notification
     */
    private static function send_immediate_notification(string $type, int $user_id, array $data): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        switch ($type) {
            case 'booking_confirmation':
                return self::send_booking_confirmation($user, $data);
            case 'booking_reminder':
                return self::send_booking_reminder($user, $data);
            case 'payment_confirmation':
                return self::send_payment_confirmation($user, $data);
            case 'welcome':
                return self::send_welcome_notification($user);
            default:
                return false;
        }
    }

    /**
     * Queue notification for later sending
     */
    private static function queue_notification(string $type, int $user_id, array $data): bool
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mhm_notification_queue';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'notification_type' => $type,
                'notification_data' => json_encode($data),
                'scheduled_for' => self::get_next_send_time(),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
        );

        return $result !== false;
    }

    /**
     * Process notification queue
     */
    public static function process_notification_queue(): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mhm_notification_queue';
        
        // Get pending notifications
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE status = 'pending'
            AND scheduled_for <= %s
            ORDER BY created_at ASC
            LIMIT 50
        ", current_time('mysql')));

        foreach ($notifications as $notification) {
            $user_id = $notification->user_id;
            $type = $notification->notification_type;
            $data = json_decode($notification->notification_data, true);
            
            if (self::send_immediate_notification($type, $user_id, $data)) {
                // Mark as sent
                $wpdb->update(
                    $table_name,
                    ['status' => 'sent', 'sent_at' => current_time('mysql')],
                    ['id' => $notification->id],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                // Mark as failed
                $wpdb->update(
                    $table_name,
                    ['status' => 'failed', 'error_message' => __('Failed to send notification', 'mhm-rentiva')],
                    ['id' => $notification->id],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
    }

    /**
     * Get next send time based on frequency
     */
    private static function get_next_send_time(): string
    {
        $frequency = self::get_notification_frequency();
        
        switch ($frequency) {
            case 'hourly':
                return date('Y-m-d H:i:s', strtotime('+1 hour'));
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day'));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('+1 week'));
            default:
                return current_time('mysql');
        }
    }

    /**
     * Send booking confirmation notification
     */
    private static function send_booking_confirmation(\WP_User $user, array $data): bool
    {
        return self::send_notification_via_mailer('booking_confirmation', $user, $data);
    }

    /**
     * Send booking reminder notification
     */
    private static function send_booking_reminder(\WP_User $user, array $data): bool
    {
        return self::send_notification_via_mailer('booking_reminder', $user, $data);
    }

    /**
     * Send payment confirmation notification
     */
    private static function send_payment_confirmation(\WP_User $user, array $data): bool
    {
        return self::send_notification_via_mailer('payment_confirmation', $user, $data);
    }

    /**
     * Send welcome notification
     */
    private static function send_welcome_notification(\WP_User $user): bool
    {
        return self::send_notification_via_mailer('welcome', $user, []);
    }

    /**
     * Send notification via mailer system (common method to reduce code duplication)
     */
    private static function send_notification_via_mailer(string $type, \WP_User $user, array $data): bool
    {
        return \MHMRentiva\Admin\Emails\Core\Mailer::send(
            $type,
            $user->user_email,
            array_merge($data, ['customer' => ['email' => $user->user_email]])
        );
    }

    /**
     * Create notification queue table
     */
    public static function create_notification_queue_table(): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mhm_notification_queue';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            notification_type varchar(50) NOT NULL,
            notification_data longtext,
            scheduled_for datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            sent_at datetime NULL,
            error_message text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get notification statistics
     * Optimized to use a single query instead of multiple queries
     */
    public static function get_notification_stats(): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mhm_notification_queue';
        
        // Single optimized query to get all statistics
        $stats_query = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_notifications,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_notifications,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_notifications,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_notifications
            FROM {$table_name}
        ", ARRAY_A);
        
        $stats = [
            'total_notifications' => (int) ($stats_query['total_notifications'] ?? 0),
            'pending_notifications' => (int) ($stats_query['pending_notifications'] ?? 0),
            'sent_notifications' => (int) ($stats_query['sent_notifications'] ?? 0),
            'failed_notifications' => (int) ($stats_query['failed_notifications'] ?? 0),
            'frequency' => self::get_notification_frequency()
        ];
        
        return $stats;
    }

}
