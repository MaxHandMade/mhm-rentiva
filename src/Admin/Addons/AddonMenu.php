<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonManager;

if (!defined('ABSPATH')) {
    exit;
}

final class AddonMenu
{
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'admin_notices']);
        add_action('admin_notices', [self::class, 'add_addon_page_title']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_scripts']);
    }

    public static function add_menu_pages(): void
    {
        // WordPress automatically adds post type menus
        // This function is no longer needed
    }

    public static function add_addon_page_title(): void
    {
        global $pagenow, $post_type;
        
        // Only show on addon list page
        if ($pagenow !== 'edit.php' || $post_type !== 'vehicle_addon') {
            return;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Additional Services', 'mhm-rentiva'); ?></h1>
            <hr class="wp-header-end">
        </div>
        <?php
    }

    public static function admin_notices(): void
    {
        // Show license limit notice
        if (isset($_GET['addon_limit_reached']) && $_GET['addon_limit_reached'] === '1') {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>' . esc_html(AddonManager::get_addon_limit_message()) . '</p>';
            echo '</div>';
        }

        // Show success message for addon creation
        if (isset($_GET['addon_created']) && $_GET['addon_created'] === '1') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Additional service created successfully.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }
    }


    public static function enqueue_admin_scripts(string $hook): void
    {
        // Only load on addon pages
        if (strpos($hook, 'vehicle_addon') === false) {
            return;
        }

        wp_enqueue_style(
            'mhm-rentiva-addon-admin',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/addon-admin.css',
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-rentiva-addon-admin',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-admin.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_localize_script('mhm-rentiva-addon-admin', 'mhmAddonAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_addon_admin'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this additional service?', 'mhm-rentiva'),
                'confirm_bulk_enable' => __('Are you sure you want to enable selected additional services?', 'mhm-rentiva'),
                'confirm_bulk_disable' => __('Are you sure you want to disable selected additional services?', 'mhm-rentiva'),
            ],
        ]);
    }

}
