<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Notifications;

use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Messages\Settings\MessagesSettings;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class MessageNotifications
{
    public static function register(): void
    {
        // Hooks
        add_action('mhm_message_created', [self::class, 'send_new_message_notifications'], 10, 2);
        add_action('mhm_message_status_changed', [self::class, 'send_status_change_notifications'], 10, 3);
    }

    /**
     * Send notifications when a new message is created
     */
    public static function send_new_message_notifications(int $message_id, array $message_data): void
    {
        $message = get_post($message_id);
        if (!$message) {
            return;
        }

        $meta = Message::get_message_meta($message_id);

        if ($meta['message_type'] === 'customer_to_admin') {
            // Customer message - notify admin
            if (MessagesSettings::is_email_enabled('admin')) {
                self::send_admin_new_message_notification($message, $meta);
            }

            // Send auto reply
            if (MessagesSettings::get_setting('auto_reply_enabled', false)) {
                self::send_auto_reply($message, $meta);
            }
        } elseif ($meta['message_type'] === 'admin_to_customer') {
            // Admin reply - notify customer
            if (MessagesSettings::is_email_enabled('customer')) {
                self::send_customer_reply_notification($message, $meta);
            }
        }
    }

    /**
     * Send notification when message status changes
     */
    public static function send_status_change_notifications(int $message_id, string $old_status, string $new_status): void
    {
        $message = get_post($message_id);
        if (!$message) {
            return;
        }

        $meta = Message::get_message_meta($message_id);

        // Only notify customer about status change
        if ($meta['message_type'] === 'customer_to_admin' && MessagesSettings::is_email_enabled('status_change')) {
            self::send_customer_status_change_notification($message, $meta, $old_status, $new_status);
        }
    }

    /**
     * Admin new customer message notification
     */
    private static function send_admin_new_message_notification(\WP_Post $message, array $meta): void
    {
        // Get admin email from override setting or global
        $admin_email_override = MessagesSettings::get_setting('admin_email');
        $to = !empty($admin_email_override) ? $admin_email_override : get_option('admin_email');

        if (!$to) {
            return;
        }

        $context = Mailer::getMessageContext($message->ID);
        if (!$context) {
            return;
        }

        Mailer::send('message_received_admin', $to, $context, self::get_headers());
    }

    /**
     * Customer reply notification
     */
    private static function send_customer_reply_notification(\WP_Post $message, array $meta): void
    {
        if (empty($meta['customer_email'])) {
            return;
        }

        $context = Mailer::getMessageContext($message->ID);
        if (!$context) {
            return;
        }

        Mailer::send('message_replied_customer', $meta['customer_email'], $context, self::get_headers());
    }

    /**
     * Customer status change notification
     */
    private static function send_customer_status_change_notification(\WP_Post $message, array $meta, string $old_status, string $new_status): void
    {
        if (empty($meta['customer_email'])) {
            return;
        }

        $statuses = Message::get_statuses();
        $old_status_label = $statuses[$old_status] ?? $old_status;
        $new_status_label = $statuses[$new_status] ?? $new_status;

        /* translators: %s placeholder. */
        $subject = sprintf(__('Message Status Updated: %s', 'mhm-rentiva'), $message->post_title);

        $message_content = self::get_customer_status_change_template($message, $meta, $old_status_label, $new_status_label);

        wp_mail($meta['customer_email'], $subject, $message_content, self::get_headers());
    }

    /**
     * Admin message template
     */
    private static function get_admin_message_template(\WP_Post $message, array $meta): string
    {
        $categories = MessagesSettings::get_categories();
        $category_label = $categories[$meta['category']] ?? $meta['category'];

        // Load template file
        $template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/messages/admin-message-email.html.php';

        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Fallback - simple template
        return sprintf(
            '<h2>%s</h2><p><strong>%s:</strong> %s (%s)</p><p><strong>%s:</strong> %s</p><div>%s</div>',
            esc_html($message->post_title),
            esc_html(__('Customer', 'mhm-rentiva')),
            esc_html($meta['customer_name']),
            esc_html($meta['customer_email']),
            esc_html(__('Category', 'mhm-rentiva')),
            esc_html($category_label),
            wp_kses_post($message->post_content ?? '')
        );
    }

    /**
     * Customer reply template
     */
    private static function get_customer_reply_template(\WP_Post $message, array $meta): string
    {
        // Load template file
        $template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/messages/customer-reply-email.html.php';

        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Fallback - simple template
        // Get messages URL dynamically
        $base_url = AccountController::get_account_url();
        $messages_url = add_query_arg('endpoint', 'messages', $base_url);

        return sprintf(
            '<h2>%s</h2><p>%s %s,</p><p>%s</p><h4>%s</h4><div>%s</div><p><a href="%s">%s</a></p>',
            esc_html(__('Support Reply', 'mhm-rentiva')),
            esc_html(__('Hello', 'mhm-rentiva')),
            esc_html($meta['customer_name']),
            esc_html(__('You have received a reply to your message:', 'mhm-rentiva')),
            esc_html($message->post_title),
            wp_kses_post($message->post_content ?? ''),
            esc_url($messages_url),
            esc_html(__('View My Messages', 'mhm-rentiva'))
        );
    }

    /**
     * Customer status change template
     */
    private static function get_customer_status_change_template(\WP_Post $message, array $meta, string $old_status, string $new_status): string
    {
        // Load template file
        $template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/messages/customer-status-change-email.html.php';

        if (file_exists($template_path)) {
            // Define variables to pass to template
            $old_status_label = $old_status;
            $new_status_label = $new_status;

            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Fallback - simple template
        // Get messages URL dynamically
        $base_url = AccountController::get_account_url();
        $messages_url = add_query_arg('endpoint', 'messages', $base_url);

        /* translators: 1: Customer name, 2: Message title, 3: Old status, 4: New status, 5: Messages URL */
        return sprintf(
            /* translators: %1$s placeholder. */
            '<h2>' . __('Message Status Updated', 'mhm-rentiva') . '</h2><p>' . __('Hello %1$s,', 'mhm-rentiva') . '</p><p>' . __('The status of your message has been updated:', 'mhm-rentiva') . '</p><h4>%2$s</h4><p><strong>' . __('Old Status:', 'mhm-rentiva') . '</strong> %3$s</p><p><strong>' . __('New Status:', 'mhm-rentiva') . '</strong> %4$s</p><p><a href="%5$s">' . __('View My Messages', 'mhm-rentiva') . '</a></p>',
            esc_html($meta['customer_name']),
            esc_html($message->post_title),
            esc_html($old_status),
            esc_html($new_status),
            esc_url($messages_url)
        );
    }

    /**
     * Send auto reply
     */
    private static function send_auto_reply(\WP_Post $message, array $meta): void
    {
        if (empty($meta['customer_email'])) {
            return;
        }

        $context = Mailer::getMessageContext($message->ID);
        if (!$context) {
            return;
        }

        Mailer::send('message_auto_reply', $meta['customer_email'], $context, self::get_headers());
    }

    /**
     * Get email headers with override support
     */
    private static function get_headers(): array
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];

        $override_name = MessagesSettings::get_setting('from_name');
        $override_email = MessagesSettings::get_setting('from_email');

        if (!empty($override_name) || !empty($override_email)) {
            $name = !empty($override_name) ? $override_name : EmailSettings::get_from_name();
            $email = !empty($override_email) ? $override_email : EmailSettings::get_from_address();
            $headers[] = 'From: ' . $name . ' <' . $email . '>';
        }

        return $headers;
    }

    /**
     * Check email settings
     */
    public static function is_email_enabled(string $type): bool
    {
        return MessagesSettings::is_email_enabled($type);
    }

    /**
     * Customize email templates
     */
    public static function customize_email_template(string $template, array $data): string
    {
        return apply_filters('mhm_rentiva_message_email_template', $template, $data);
    }
}
