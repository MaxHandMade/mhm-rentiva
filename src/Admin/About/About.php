<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\About\SystemInfo;
use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\About\Tabs\GeneralTab;
use MHMRentiva\Admin\About\Tabs\FeaturesTab;
use MHMRentiva\Admin\About\Tabs\SystemTab;
use MHMRentiva\Admin\About\Tabs\SupportTab;
use MHMRentiva\Admin\About\Tabs\DeveloperTab;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * About page main class
 */
final class About
{
    /**
     * Register the About class
     */
    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('wp_ajax_mhm_about_load_tab', [self::class, 'ajax_load_tab']);
    }

    /**
     * Render the About page
     */
    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Get system info from cache and set it globally
        $system_info = SystemInfo::get_cached_system_info();
        $GLOBALS['system_info'] = $system_info;
        $features = FeaturesTab::get_features_list();
        $changelog = SupportTab::get_changelog();


        // Active tab
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

?>
        <div class="wrap mhm-about-wrap">
            <div class="about-header">
                <div class="header-content">
                    <h1><?php _e('About MHM Rentiva', 'mhm-rentiva'); ?></h1>
                    <div class="version-info">
                        <span class="version-badge">v<?php echo esc_html(MHM_RENTIVA_VERSION); ?></span>
                        <span class="license-badge <?php echo esc_attr(Mode::isPro() ? 'pro' : 'lite'); ?>">
                            <?php echo esc_html(Mode::isPro() ? __('Pro Version', 'mhm-rentiva') : __('Lite Version', 'mhm-rentiva')); ?>
                        </span>
                    </div>
                </div>
                <div class="header-actions">
                    <?php
                    $company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();
                    $support_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();
                    ?>
                    <?php echo Helpers::render_external_link(
                        'https://maxhandmade.github.io/mhm-rentiva-docs/',
                        __('Documentation', 'mhm-rentiva'),
                        ['class' => 'button button-secondary']
                    ); ?>
                    <?php echo Helpers::render_external_link(
                        'mailto:' . $support_email,
                        __('Support', 'mhm-rentiva'),
                        ['class' => 'button button-primary']
                    ); ?>
                </div>
            </div>

            <div class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'general')); ?>"
                    class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General Information', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'features')); ?>"
                    class="nav-tab <?php echo $active_tab === 'features' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Features', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'system')); ?>"
                    class="nav-tab <?php echo $active_tab === 'system' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('System Information', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'support')); ?>"
                    class="nav-tab <?php echo $active_tab === 'support' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Support', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'developer')); ?>"
                    class="nav-tab <?php echo $active_tab === 'developer' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Developer', 'mhm-rentiva'); ?>
                </a>
            </div>

            <div class="tab-content">
                <?php
                self::render_tab_content($active_tab, $system_info, $features, $changelog);
                ?>
            </div>
        </div>
<?php
    }

    /**
     * Render tab content (shared between render_page and ajax_load_tab)
     */
    private static function render_tab_content(string $tab, array $system_info, array $features, array $changelog): void
    {
        switch ($tab) {
            case 'general':
                GeneralTab::render($system_info);
                break;
            case 'features':
                FeaturesTab::render($features);
                break;
            case 'system':
                SystemTab::render($system_info);
                break;
            case 'support':
                SupportTab::render($changelog);
                break;
            case 'developer':
                DeveloperTab::render();
                break;
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts(string $hook): void
    {
        if ($hook !== 'mhm-rentiva_page_mhm-rentiva-about') {
            return;
        }

        wp_enqueue_style(
            'mhm-about-admin',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/about.css',
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-about-admin',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/about.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_localize_script('mhm-about-admin', 'mhmAboutAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_about_admin'),
            'strings' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'error' => __('An error occurred.', 'mhm-rentiva'),
            ]
        ]);
    }

    /**
     * AJAX handler for tab loading
     */
    public static function ajax_load_tab(): void
    {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_about_admin')) {
            wp_send_json_error(['message' => __('Security error', 'mhm-rentiva')]);
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission error', 'mhm-rentiva')]);
            return;
        }

        $tab = sanitize_key($_POST['tab'] ?? '');

        if (empty($tab)) {
            wp_send_json_error(['message' => __('Invalid tab', 'mhm-rentiva')]);
            return;
        }

        // Start output buffering
        ob_start();

        try {
            // Get system info from cache and set it globally
            $system_info = SystemInfo::get_cached_system_info();
            $GLOBALS['system_info'] = $system_info;
            $features = FeaturesTab::get_features_list();
            $changelog = SupportTab::get_changelog();

            // Render the requested tab
            if (!in_array($tab, ['general', 'features', 'system', 'support', 'developer'], true)) {
                wp_send_json_error(['message' => __('Unknown tab', 'mhm-rentiva')]);
                return;
            }

            self::render_tab_content($tab, $system_info, $features, $changelog);

            $content = ob_get_clean();

            wp_send_json_success(['content' => $content]);
        } catch (\Exception $e) {
            ob_end_clean();
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
