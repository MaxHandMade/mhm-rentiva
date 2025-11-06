<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Helpers;

use MHMRentiva\Admin\PostTypes\Message\Message;

if (!defined('ABSPATH')) {
    exit;
}

final class MessageFormatter
{
    /**
     * Format thread messages
     */
    public static function formatThreadMessages(array $thread_messages, string $customer_email): array
    {
        $formatted_messages = [];
        
        foreach ($thread_messages as $message) {
            $meta = Message::get_message_meta($message->ID);

            // Show only messages belonging to this customer or admin replies
            if ($meta['customer_email'] !== $customer_email && $meta['message_type'] !== 'admin_to_customer') {
                continue;
            }

            $formatted_messages[] = [
                'id' => $message->ID,
                'content' => wp_kses_post($message->post_content ?? ''),
                'message_type' => $meta['message_type'],
                'date' => $message->post_date,
                'date_human' => human_time_diff(strtotime($message->post_date), current_time('timestamp')) . ' ' . __('ago', 'mhm-rentiva'),
                'date_full' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->post_date)),
                'customer_name' => $meta['customer_name'] ?? '',
                'admin_name' => $meta['message_type'] === 'admin_to_customer' ? get_the_author_meta('display_name', $message->post_author) : '',
                'attachments' => $meta['attachments'] ?? [],
            ];
        }

        return $formatted_messages;
    }

    /**
     * Check thread status
     */
    public static function getThreadStatus(array $thread_messages): array
    {
        if (empty($thread_messages)) {
            return ['can_reply' => false, 'status' => 'unknown'];
        }

        $last_message = end($thread_messages);
        $last_meta = Message::get_message_meta($last_message->ID);
        
        return [
            'can_reply' => $last_meta['status'] !== 'closed',
            'status' => $last_meta['status'],
        ];
    }

    /**
     * Create message preview
     */
    public static function createPreview(string $content, int $length = 100): string
    {
        $preview = wp_strip_all_tags($content);
        return $preview . (strlen($preview) > $length - 3 ? '...' : '');
    }
}
