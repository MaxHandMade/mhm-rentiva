<?php
/**
 * Message thread view template
 * 
 * @var WP_Post $message
 * @var array $meta
 * @var array $thread_messages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}
?>
<div class="message-thread-header">
    <a href="<?php echo esc_url(\MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_messages_list_url()); ?>" class="button">
        ← <?php esc_html_e('Back to All Messages', 'mhm-rentiva'); ?>
    </a>

    <div class="message-info">
        <h2><?php echo esc_html($message->post_title); ?></h2>
        <div class="message-meta">
            <span class="customer-name"><?php echo esc_html($meta['customer_name']); ?> (<?php echo esc_html($meta['customer_email']); ?>)</span>
            <span class="message-category"><?php echo esc_html($category_label); ?></span>
            <span class="message-status status-<?php echo esc_attr($meta['status']); ?>">
                <?php echo esc_html($status_label); ?>
            </span>
        </div>
    </div>

    <div class="message-actions">
        <select id="message-status-select" data-message-id="<?php echo esc_attr($message_id); ?>">
            <?php foreach ($statuses as $status_key => $status_label): ?>
                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($meta['status'], $status_key); ?>>
                    <?php echo esc_html($status_label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <a href="<?php echo esc_url(\MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_message_reply_url($message_id)); ?>" class="button button-primary">
            <?php esc_html_e('Reply', 'mhm-rentiva'); ?>
        </a>
    </div>
</div>

<div class="message-thread">
    <?php foreach ($thread_messages as $thread_message): ?>
        <div class="message-item <?php echo esc_attr($thread_message->meta['message_type']); ?>">
            <div class="message-header">
                <div class="message-author">
                    <?php if ($thread_message->meta['message_type'] === 'customer_to_admin'): ?>
                        <strong><?php echo esc_html($thread_message->meta['customer_name']); ?></strong>
                        <span class="message-type customer"><?php esc_html_e('Customer', 'mhm-rentiva'); ?></span>
                    <?php else: ?>
                        <strong><?php echo esc_html(get_the_author_meta('display_name', $thread_message->post_author)); ?></strong>
                        <span class="message-type admin"><?php esc_html_e('Administrator', 'mhm-rentiva'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="message-date">
                    <?php printf(esc_html__('%s ago', 'mhm-rentiva'), esc_html(human_time_diff(strtotime($thread_message->post_date)))); ?>
                </div>
            </div>

            <div class="message-content">
                <?php echo wp_kses_post($thread_message->post_content ?? ''); ?>
            </div>

            <?php if (!empty($thread_message->meta['attachments'])): ?>
                <div class="message-attachments">
                    <strong><?php esc_html_e('Attachments:', 'mhm-rentiva'); ?></strong>
                    <?php foreach ($thread_message->meta['attachments'] as $attachment_id): ?>
                        <?php $attachment_url = wp_get_attachment_url($attachment_id); ?>
                        <?php if ($attachment_url): ?>
                            <a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="attachment-link">
                                <?php echo esc_html(get_the_title($attachment_id)); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
