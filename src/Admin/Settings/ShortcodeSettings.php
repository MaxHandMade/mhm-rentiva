<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings;

use MHMRentiva\Admin\Core\ShortcodeServiceProvider;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode Settings
 * 
 * Admin page for shortcode management and configuration
 * 
 * @since 3.0.1
 */
final class ShortcodeSettings
{
    /**
     * Settings option name
     */
    private const OPTION_NAME = 'mhm_rentiva_shortcode_settings';

    /**
     * Register settings page
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_settings_page'], 13); // Priority 13 to run after ShortcodePages
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Add settings submenu page
     */
    public static function add_settings_page(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Shortcode Settings', 'mhm-rentiva'),
            __('Shortcode Settings', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-shortcode-settings',
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings(): void
    {
        register_setting(
            'mhm_rentiva_shortcode_settings_group',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default' => self::get_default_settings(),
            ]
        );
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== 'rentiva_page_mhm-rentiva-shortcode-settings') {
            return;
        }

        wp_enqueue_style(
            'mhm-rentiva-shortcode-settings',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/shortcode-settings.css',
            [],
            MHM_RENTIVA_VERSION
        );

        wp_enqueue_script(
            'mhm-rentiva-shortcode-settings',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/shortcode-settings.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_localize_script('mhm-rentiva-shortcode-settings', 'mhmRentivaShortcodeSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_shortcode_settings'),
            'i18n' => [
                'copied' => __('Copied!', 'mhm-rentiva'),
                'copy_failed' => __('Copy failed', 'mhm-rentiva'),
                'confirm_reset' => __('Are you sure you want to reset all settings?', 'mhm-rentiva'),
            ],
        ]);
    }

    /**
     * Get default settings
     */
    private static function get_default_settings(): array
    {
        $defaults = [
            'global' => [
                'cache_enabled' => true,
                'cache_ttl' => 300,
                'debug_mode' => false,
                'load_assets_conditionally' => true,
            ],
        ];

        // Default settings for each shortcode
        $groups = ShortcodeServiceProvider::get_shortcode_groups();
        
        foreach ($groups as $group => $data) {
            foreach ($data['shortcodes'] as $shortcode) {
                $defaults['shortcodes'][$shortcode] = [
                    'enabled' => true,
                    'cache_enabled' => true,
                    'cache_ttl' => 300,
                ];
            }
        }

        return $defaults;
    }

    /**
     * Get current settings
     */
    public static function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, self::get_default_settings());
        return wp_parse_args($settings, self::get_default_settings());
    }

    /**
     * Get shortcode setting
     */
    public static function get_shortcode_setting(string $shortcode, string $key, $default = null)
    {
        $settings = self::get_settings();
        
        // Special check for global settings
        if ($shortcode === 'global') {
            return $settings['global'][$key] ?? $default;
        }
        
        return $settings['shortcodes'][$shortcode][$key] ?? $default;
    }

    /**
     * Check if shortcode is enabled
     */
    public static function is_shortcode_enabled(string $shortcode): bool
    {
        return (bool) self::get_shortcode_setting($shortcode, 'enabled', true);
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input): array
    {
        if (!is_array($input)) {
            return self::get_default_settings();
        }

        $sanitized = [];

        // Global settings
        if (isset($input['global']) && is_array($input['global'])) {
            $cache_ttl = absint($input['global']['cache_ttl'] ?? 300);
            $sanitized['global'] = [
                'cache_enabled' => isset($input['global']['cache_enabled']),
                'cache_ttl' => max(60, min(86400, $cache_ttl)), // Between 60 seconds and 24 hours
                'debug_mode' => isset($input['global']['debug_mode']),
                'load_assets_conditionally' => isset($input['global']['load_assets_conditionally']),
            ];
        }

        // Shortcode settings
        if (isset($input['shortcodes']) && is_array($input['shortcodes'])) {
            foreach ($input['shortcodes'] as $shortcode => $settings) {
                if (is_array($settings)) {
                    $cache_ttl = absint($settings['cache_ttl'] ?? 300);
                    $sanitized['shortcodes'][sanitize_key($shortcode)] = [
                        'enabled' => isset($settings['enabled']),
                        'cache_enabled' => isset($settings['cache_enabled']),
                        'cache_ttl' => max(60, min(3600, $cache_ttl)), // Between 60 seconds and 1 hour
                    ];
                }
            }
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'mhm-rentiva'));
        }

        $settings = self::get_settings();
        $groups = ShortcodeServiceProvider::get_shortcode_groups();
        $active_tab = sanitize_key($_GET['tab'] ?? 'general');

        ?>
        <div class="wrap mhm-shortcode-settings">
            <h1><?php echo esc_html__('Shortcode Settings', 'mhm-rentiva'); ?></h1>
            
            <p class="description">
                <?php echo esc_html__('Manage and configure MHM Rentiva shortcodes.', 'mhm-rentiva'); ?>
            </p>

            <?php settings_errors('mhm_rentiva_shortcode_settings'); ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=mhm-rentiva-shortcode-settings&tab=general"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('General Settings', 'mhm-rentiva'); ?>
                </a>
                <?php foreach ($groups as $group => $data): ?>
                    <a href="?page=mhm-rentiva-shortcode-settings&tab=<?php echo esc_attr($group); ?>" 
                       class="nav-tab <?php echo $active_tab === $group ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($data['name']); ?>
                        <span class="count">(<?php echo esc_html($data['count']); ?>)</span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields('mhm_rentiva_shortcode_settings_group'); ?>
                
                <div class="mhm-settings-content">
                    <?php if ($active_tab === 'general'): ?>
                        <?php self::render_general_settings($settings); ?>
                    <?php else: ?>
                        <?php self::render_group_settings($active_tab, $groups[$active_tab] ?? [], $settings); ?>
                    <?php endif; ?>
                </div>

                <div class="mhm-settings-footer">
                    <?php submit_button(__('Save Settings', 'mhm-rentiva'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary mhm-reset-settings">
                        <?php echo esc_html__('Reset to Defaults', 'mhm-rentiva'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings tab
     */
    private static function render_general_settings(array $settings): void
    {
        $global = $settings['global'] ?? [];
        ?>
        <div class="mhm-settings-section">
            <h2><?php echo esc_html__('General Settings', 'mhm-rentiva'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cache_enabled">
                            <?php echo esc_html__('Cache System', 'mhm-rentiva'); ?>
                        </label>
                    </th>
                    <td>
                        <label class="mhm-toggle">
                            <input type="checkbox" 
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[global][cache_enabled]" 
                                   id="cache_enabled"
                                   value="1"
                                   <?php checked(!empty($global['cache_enabled'])); ?>>
                            <span class="mhm-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Improves performance by caching shortcode outputs.', 'mhm-rentiva'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="cache_ttl">
                            <?php echo esc_html__('Cache Duration', 'mhm-rentiva'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               name="<?php echo esc_attr(self::OPTION_NAME); ?>[global][cache_ttl]" 
                               id="cache_ttl"
                               value="<?php echo esc_attr($global['cache_ttl'] ?? 300); ?>"
                               min="60"
                               max="86400"
                               step="60"
                               class="small-text">
                        <span class="description"><?php echo esc_html__('seconds', 'mhm-rentiva'); ?></span>
                        <p class="description">
                            <?php echo esc_html__('How long the cache will remain valid (60-86400 seconds).', 'mhm-rentiva'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="load_assets_conditionally">
                            <?php echo esc_html__('Conditional Asset Loading', 'mhm-rentiva'); ?>
                        </label>
                    </th>
                    <td>
                        <label class="mhm-toggle">
                            <input type="checkbox"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[global][load_assets_conditionally]"
                                   id="load_assets_conditionally"
                                   value="1"
                                   <?php checked(!empty($global['load_assets_conditionally'])); ?>>
                            <span class="mhm-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Loads CSS/JS files only when shortcode is used.', 'mhm-rentiva'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="debug_mode">
                            <?php echo esc_html__('Debug Mode', 'mhm-rentiva'); ?>
                        </label>
                    </th>
                    <td>
                        <label class="mhm-toggle">
                            <input type="checkbox"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[global][debug_mode]"
                                   id="debug_mode"
                                   value="1"
                                   <?php checked(!empty($global['debug_mode'])); ?>>
                            <span class="mhm-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php echo esc_html__('Shows debugging information (for administrators only).', 'mhm-rentiva'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="mhm-stats-box">
                <h3><?php echo esc_html__('System Information', 'mhm-rentiva'); ?></h3>
                <ul>
                    <li>
                        <strong><?php echo esc_html__('Total Shortcodes:', 'mhm-rentiva'); ?></strong>
                        <?php echo esc_html(ShortcodeServiceProvider::get_total_count()); ?>
                    </li>
                    <li>
                        <strong><?php echo esc_html__('Active Shortcodes:', 'mhm-rentiva'); ?></strong>
                        <?php echo esc_html(self::count_enabled_shortcodes($settings)); ?>
                    </li>
                    <li>
                        <strong><?php echo esc_html__('Cache Status:', 'mhm-rentiva'); ?></strong>
                        <?php echo !empty($global['cache_enabled']) ?
                            '<span class="status-enabled">' . esc_html__('Active', 'mhm-rentiva') . '</span>' :
                            '<span class="status-disabled">' . esc_html__('Inactive', 'mhm-rentiva') . '</span>'; ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render group settings tab
     */
    private static function render_group_settings(string $group, array $group_data, array $settings): void
    {
        if (empty($group_data)) {
            echo '<p>' . esc_html__('Group not found.', 'mhm-rentiva') . '</p>';
            return;
        }

        ?>
        <div class="mhm-settings-section">
            <h2><?php echo esc_html($group_data['name']); ?></h2>
            
            <div class="mhm-shortcodes-grid">
                <?php foreach ($group_data['shortcodes'] as $shortcode): ?>
                    <?php 
                    $shortcode_info = ShortcodeServiceProvider::get_shortcode_info($shortcode);
                    $shortcode_settings = $settings['shortcodes'][$shortcode] ?? [];
                    ?>
                    <div class="mhm-shortcode-card">
                        <div class="mhm-shortcode-header">
                            <h3>
                                <code><?php echo esc_html($shortcode); ?></code>
                            </h3>
                            <label class="mhm-toggle">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr(self::OPTION_NAME); ?>[shortcodes][<?php echo esc_attr($shortcode); ?>][enabled]" 
                                       value="1"
                                       <?php checked(!empty($shortcode_settings['enabled'])); ?>>
                                <span class="mhm-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="mhm-shortcode-body">
                            <div class="mhm-shortcode-info">
                                <?php if (!empty($shortcode_info['dependencies'])): ?>
                                    <p class="dependencies">
                                        <strong><?php echo esc_html__('Dependencies:', 'mhm-rentiva'); ?></strong>
                                        <?php echo esc_html(implode(', ', $shortcode_info['dependencies'])); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($shortcode_info['requires_auth'])): ?>
                                    <p class="requires-auth">
                                        <span class="dashicons dashicons-lock"></span>
                                        <?php echo esc_html__('Requires login', 'mhm-rentiva'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="mhm-shortcode-settings">
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr(self::OPTION_NAME); ?>[shortcodes][<?php echo esc_attr($shortcode); ?>][cache_enabled]"
                                           value="1"
                                           <?php checked(!empty($shortcode_settings['cache_enabled'])); ?>>
                                    <?php echo esc_html__('Cache Active', 'mhm-rentiva'); ?>
                                </label>

                                <div class="cache-ttl-group">
                                    <label>
                                        <?php echo esc_html__('Cache Duration:', 'mhm-rentiva'); ?>
                                        <input type="number"
                                               name="<?php echo esc_attr(self::OPTION_NAME); ?>[shortcodes][<?php echo esc_attr($shortcode); ?>][cache_ttl]"
                                               value="<?php echo esc_attr($shortcode_settings['cache_ttl'] ?? 300); ?>"
                                               min="60"
                                               max="3600"
                                               step="60"
                                               class="small-text">
                                        <span class="unit"><?php echo esc_html__('sec', 'mhm-rentiva'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="mhm-shortcode-actions">
                                <button type="button" class="button button-small mhm-copy-shortcode" data-shortcode="[<?php echo esc_attr($shortcode); ?>]">
                                    <span class="dashicons dashicons-admin-page"></span>
                                    <?php echo esc_html__('Copy Code', 'mhm-rentiva'); ?>
                                </button>

                                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-shortcode-pages')); ?>"
                                   class="button button-small">
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <?php echo esc_html__('Page Management', 'mhm-rentiva'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Count enabled shortcodes
     */
    private static function count_enabled_shortcodes(array $settings): int
    {
        $count = 0;
        
        // Get all shortcodes
        $groups = ShortcodeServiceProvider::get_shortcode_groups();
        $all_shortcodes = [];
        
        foreach ($groups as $group => $data) {
            $all_shortcodes = array_merge($all_shortcodes, $data['shortcodes']);
        }
        
        // Check enabled status for each shortcode
        foreach ($all_shortcodes as $shortcode) {
            $shortcode_settings = $settings['shortcodes'][$shortcode] ?? [];
            $enabled = $shortcode_settings['enabled'] ?? true; // Default to enabled
            
            if ($enabled) {
                $count++;
            }
        }

        return $count;
    }
}
