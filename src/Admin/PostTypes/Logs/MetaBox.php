<?php declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Logs;

if (!defined('ABSPATH')) {
    exit;
}

final class MetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'add']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function add(): void
    {
        add_meta_box(
            'mhm_log_context',
            __('Context', 'mhm-rentiva'),
            [self::class, 'render'],
            PostType::TYPE,
            'normal',
            'default'
        );
    }

    public static function render(\WP_Post $post): void
    {
        $raw = (string) get_post_meta($post->ID, '_mhm_log_context', true);
        $pretty = '—';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                $pretty = $raw;
            }
        }
        // Legacy helper metas for backward compatibility.
        $gateway = strtoupper((string) get_post_meta($post->ID, '_mhm_log_gateway', true));
        $action  = (string) get_post_meta($post->ID, '_mhm_log_action', true);
        $status  = (string) get_post_meta($post->ID, '_mhm_log_status', true);
        $bid     = (int) get_post_meta($post->ID, '_mhm_log_booking_id', true);

        $ak      = (int) get_post_meta($post->ID, '_mhm_log_amount_kurus', true);
        $cur     = (string) get_post_meta($post->ID, '_mhm_log_currency', true);

        echo '<p style="margin:0 0 8px;">';
        if ($gateway) echo '<strong>' . esc_html($gateway) . '</strong> ';
        if ($action)  echo '<code>' . esc_html($action) . '</code> ';
        if ($status)  echo '<span>[' . esc_html($status) . ']</span> ';

        if ($bid) {
            $link = get_edit_post_link($bid);
            if ($link) {
                echo '<span style="margin-left:8px;">' . esc_html__('Booking', 'mhm-rentiva') . ': <a href="' . esc_url($link) . '">#' . (int) $bid . '</a></span> ';
            }
        }
        if ($ak > 0) {
            $val = number_format_i18n($ak / 100, 2) . ' ' . strtoupper($cur ?: 'TRY');
            echo '<span style="margin-left:8px;">' . esc_html__('Amount', 'mhm-rentiva') . ': ' . esc_html($val) . '</span>';
        }
        echo '</p>';

        echo '<p><button type="button" class="button" id="mhm-copy-context">' . esc_html__('Copy JSON', 'mhm-rentiva') . '</button></p>';
        echo '<textarea readonly id="mhm-context" class="large-text code mhm-json" rows="16" spellcheck="false">' . esc_textarea($pretty) . '</textarea>';
    }

    /**
     * Enqueues scripts and styles for the metabox.
     */
    public static function enqueue_assets(string $hook): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== PostType::TYPE || !in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        $plugin_url = untrailingslashit(plugin_dir_url(MHM_RENTIVA_PLUGIN_FILE));

        wp_enqueue_style(
            'mhm-log-metabox-css',
            $plugin_url . '/assets/css/admin/log-metabox.css',
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-log-metabox-js',
            $plugin_url . '/assets/js/admin/log-metabox.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        wp_localize_script('mhm-log-metabox-js', 'mhmLogMetabox', [
            'copied' => __('Copied', 'mhm-rentiva'),
            'copy'   => __('Copy JSON', 'mhm-rentiva'),
        ]);
    }
}
