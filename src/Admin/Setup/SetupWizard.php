<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Setup;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

if (!defined('ABSPATH')) {
    exit;
}

final class SetupWizard
{
    private const PAGE_SLUG = 'mhm-rentiva-setup';
    private const OPTION_COMPLETED = 'mhm_rentiva_setup_completed';
    private const OPTION_REDIRECT = 'mhm_rentiva_setup_redirect';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybe_redirect']);

        add_action('admin_post_mhm_rentiva_setup_save_license', [self::class, 'handle_save_license']);
        add_action('admin_post_mhm_rentiva_setup_create_pages', [self::class, 'handle_create_pages']);
        add_action('admin_post_mhm_rentiva_setup_save_email', [self::class, 'handle_save_email']);
        add_action('admin_post_mhm_rentiva_setup_save_frontend', [self::class, 'handle_save_frontend']);
        add_action('admin_post_mhm_rentiva_setup_finish', [self::class, 'handle_finish']);
        add_action('admin_post_mhm_rentiva_setup_skip', [self::class, 'handle_skip']);
    }

    public static function register_menu(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Setup Wizard', 'mhm-rentiva'),
            __('Setup Wizard', 'mhm-rentiva'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::print_styles();
        $current_step = self::get_current_step();
        $steps = self::get_steps();

        $license_manager = class_exists(LicenseManager::class) ? LicenseManager::instance() : null;
        $license_data = $license_manager ? $license_manager->get() : [];
        $license_context = [
            'key'               => $license_manager ? $license_manager->getKey() : get_option('mhm_rentiva_license_key', ''),
            'expires_at'        => isset($license_data['expires_at']) ? (int) $license_data['expires_at'] : null,
            'plan'              => $license_data['plan'] ?? '',
            'status'            => $license_data['status'] ?? 'inactive',
            'activation_id'     => $license_data['activation_id'] ?? '',
            'is_active'         => $license_manager ? $license_manager->isActive() : !empty(get_option('mhm_rentiva_license_key', '')),
            'is_dev_env'        => $license_manager ? $license_manager->isDevelopmentEnvironment() : false,
            'dev_mode_disabled' => (bool) get_option('mhm_rentiva_disable_dev_mode', false),
        ];

        ?>
        <div class="wrap mhm-setup-wrapper">
            <h1><?php esc_html_e('MHM Rentiva Setup Wizard', 'mhm-rentiva'); ?></h1>
            <p><?php esc_html_e('Follow the steps below to prepare Rentiva on a fresh WordPress installation. You can re-open this wizard later from the MHM Rentiva menu.', 'mhm-rentiva'); ?></p>
            <p><a class="button button-link-delete" href="<?php echo esc_url(self::skip_url()); ?>"><?php esc_html_e('Skip wizard and configure later', 'mhm-rentiva'); ?></a></p>

            <?php self::render_step_navigation($steps, $current_step); ?>

            <div class="mhm-setup-step">
                <?php
                switch ($current_step) {
                    case 'system':
                        self::render_step_system();
                        break;
                    case 'license':
                        self::render_step_license($license_context);
                        break;
                    case 'pages':
                        self::render_step_pages();
                        break;
                    case 'email':
                        self::render_step_email();
                        break;
                    case 'frontend':
                        self::render_step_frontend();
                        break;
                    case 'summary':
                    default:
                        self::render_step_summary();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private static function render_step_navigation(array $steps, string $current_step): void
    {
        echo '<ol class="mhm-setup-steps">';
        foreach ($steps as $key => $label) {
            $classes = [];
            if ($key === $current_step) {
                $classes[] = 'current';
            }
            if (self::is_step_completed($key)) {
                $classes[] = 'completed';
            }
            $class_attr = implode(' ', $classes);
            printf(
                '<li class="%1$s"><a href="%2$s">%3$s</a></li>',
                esc_attr($class_attr),
                esc_url(self::step_url($key)),
                esc_html($label)
            );
        }
        echo '</ol>';
    }

    private static function render_step_system(): void
    {
        $checks = self::get_system_checks();
        ?>
        <h2><?php esc_html_e('Step 1: System Requirements', 'mhm-rentiva'); ?></h2>
        <p><?php esc_html_e('We scanned your WordPress environment to ensure Rentiva can run reliably. Resolve any item marked as required before continuing.', 'mhm-rentiva'); ?></p>
        <table class="widefat striped mhm-system-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Requirement', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Current Value', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Recommended', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $check): ?>
                    <tr>
                        <td><?php echo esc_html($check['label']); ?></td>
                        <td><?php echo esc_html($check['current']); ?></td>
                        <td><?php echo esc_html($check['expected']); ?></td>
                        <td>
                            <?php echo self::format_status_badge($check['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php if (!empty($check['message'])): ?>
                                <div class="mhm-system-note"><?php echo esc_html($check['message']); ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="mhm-step-actions">
            <a class="button button-primary" href="<?php echo esc_url(self::step_url('license')); ?>"><?php esc_html_e('Continue to License', 'mhm-rentiva'); ?></a>
        </p>
        <?php
    }

    private static function render_step_license(array $license): void
    {
        $license_key   = (string) ($license['key'] ?? '');
        $is_active     = (bool) ($license['is_active'] ?? false);
        $expires_at    = $license['expires_at'] ?? null;
        $plan          = $license['plan'] ?: __('Unknown', 'mhm-rentiva');
        $status        = $license['status'] ?? 'inactive';
        $license_page  = admin_url('admin.php?page=mhm-rentiva-license');
        $dev_env       = (bool) ($license['is_dev_env'] ?? false);
        $dev_disabled  = (bool) ($license['dev_mode_disabled'] ?? false);
        $dev_allowed   = $dev_env && !$dev_disabled;

        ?>
        <h2><?php esc_html_e('Step 2: License Activation', 'mhm-rentiva'); ?></h2>
        <p><?php esc_html_e('Activate your license to unlock Pro features (online payments, unlimited vehicles, advanced export and more).', 'mhm-rentiva'); ?></p>

        <?php
        // ⭐ Show error messages if any
        if (isset($_GET['error'])) {
            $error_code = sanitize_text_field($_GET['error']);
            $error_message = isset($_GET['message']) ? urldecode(sanitize_text_field($_GET['message'])) : '';
            
            $error_text = '';
            switch ($error_code) {
                case 'empty_key':
                    $error_text = __('Please enter a license key.', 'mhm-rentiva');
                    break;
                case 'license_activation_failed':
                    $error_text = $error_message ?: __('License activation failed. Please check your key and try again.', 'mhm-rentiva');
                    break;
                case 'invalid_key':
                    $error_text = __('Invalid license key. Please check your key and try again.', 'mhm-rentiva');
                    break;
                case 'already_activated':
                    $error_text = __('This license is already activated on another site. Maximum 1 activation allowed.', 'mhm-rentiva');
                    break;
                default:
                    $error_text = $error_message ?: __('License activation failed. Please try again.', 'mhm-rentiva');
            }
            
            echo '<div class="notice notice-error inline"><p>' . esc_html($error_text) . '</p></div>';
        }
        
        // ⭐ Show success message if license was activated
        if (isset($_GET['license']) && $_GET['license'] === 'activated') {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('License activated successfully!', 'mhm-rentiva') . '</p></div>';
        }
        ?>

        <?php if ($is_active): ?>
            <div class="mhm-license-card mhm-license-card--active">
                <div class="mhm-license-card__status"><?php esc_html_e('✅ Pro license active on this site', 'mhm-rentiva'); ?></div>
                <div class="mhm-license-grid">
                    <div>
                        <div class="mhm-license-label"><?php esc_html_e('License Key', 'mhm-rentiva'); ?></div>
                        <code class="mhm-license-code"><?php echo esc_html($license_key); ?></code>
                    </div>
                    <div>
                        <div class="mhm-license-label"><?php esc_html_e('Plan', 'mhm-rentiva'); ?></div>
                        <span><?php echo esc_html(ucfirst($plan)); ?></span>
                    </div>
                    <div>
                        <div class="mhm-license-label"><?php esc_html_e('Expires At', 'mhm-rentiva'); ?></div>
                        <span><?php echo esc_html(self::format_license_expiration($expires_at)); ?></span>
                    </div>
                </div>
                <p class="description">
                    <?php esc_html_e('Need to deactivate or change the key? Use the License page from the main menu.', 'mhm-rentiva'); ?>
                </p>
            </div>
            <p class="mhm-step-actions">
                <a class="button" href="<?php echo esc_url($license_page); ?>" target="_blank"><?php esc_html_e('Open License Page', 'mhm-rentiva'); ?></a>
                <a class="button button-primary" href="<?php echo esc_url(self::step_url('pages')); ?>"><?php esc_html_e('Continue to Required Pages', 'mhm-rentiva'); ?></a>
            </p>
        <?php else: ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mhm_rentiva_setup_license'); ?>
                <input type="hidden" name="action" value="mhm_rentiva_setup_save_license">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('License Key', 'mhm-rentiva'); ?></th>
                        <td>
                            <input type="text" name="license_key" class="regular-text" value="<?php echo esc_attr($license_key); ?>" placeholder="XXXX-XXXX-XXXX-XXXX" />
                            <p class="description"><?php esc_html_e('Paste the key from your purchase receipt or customer dashboard.', 'mhm-rentiva'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Activate & Continue', 'mhm-rentiva')); ?>
                <div class="mhm-step-actions">
                    <a class="button button-secondary" href="<?php echo esc_url(self::step_url('system')); ?>">&larr; <?php esc_html_e('Back to System Check', 'mhm-rentiva'); ?></a>
                    <a class="button button-link" href="<?php echo esc_url(self::step_url('pages')); ?>"><?php esc_html_e('Skip for now', 'mhm-rentiva'); ?></a>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($dev_allowed): ?>
            <div class="notice notice-info inline">
                <p>
                    <?php esc_html_e('Developer mode detected: this local/staging domain can test the plugin without a license. Activate a license before going live to keep Pro features enabled.', 'mhm-rentiva'); ?>
                </p>
            </div>
        <?php elseif ($dev_env && $dev_disabled): ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e('Developer mode has been disabled for this installation. Activate a valid license key to continue using Pro features.', 'mhm-rentiva'); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$is_active): ?>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: License page URL */
                    esc_html__('Already have an active license? Review it on the %s.', 'mhm-rentiva'),
                    '<a href="' . esc_url($license_page) . '" target="_blank">' . esc_html__('License page', 'mhm-rentiva') . '</a>'
                );
                ?>
            </p>
        <?php endif; ?>
        <?php
    }

    private static function render_step_pages(): void
    {
        $required_pages = self::get_required_pages();
        ?>
        <h2><?php esc_html_e('Step 3: Required Pages', 'mhm-rentiva'); ?></h2>
        <p><?php esc_html_e('Rentiva uses dedicated WordPress pages for booking, confirmation and customer account screens. Create missing pages automatically or link existing ones.', 'mhm-rentiva'); ?></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Page', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Shortcode', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Recommended URL', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($required_pages as $page): ?>
                    <?php
                    $page_id = self::locate_shortcode_page($page['shortcode']);
                    $status = $page_id ? __('Present', 'mhm-rentiva') : __('Missing', 'mhm-rentiva');
                    ?>
                    <tr>
                        <td><?php echo esc_html($page['label']); ?></td>
                        <td><code><?php echo esc_html($page['shortcode']); ?></code></td>
                        <td>
                            <?php
                            if ($page_id) {
                                printf(
                                    '<span class="status-present">%1$s</span> <a href="%2$s" target="_blank">%3$s</a>',
                                    esc_html($status),
                                    esc_url(get_edit_post_link($page_id)),
                                    esc_html__('Edit', 'mhm-rentiva')
                                );
                            } else {
                                echo '<span class="status-missing">' . esc_html($status) . '</span>';
                            }
                            ?>
                        </td>
                        <td><code><?php echo esc_html($page['recommended_url']); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
            <?php wp_nonce_field('mhm_rentiva_setup_pages'); ?>
            <input type="hidden" name="action" value="mhm_rentiva_setup_create_pages">
            <?php submit_button(__('Create Missing Pages', 'mhm-rentiva')); ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-shortcode-pages')); ?>" target="_blank">
                <?php esc_html_e('Open Shortcode Pages', 'mhm-rentiva'); ?>
            </a>
        </form>
        <p class="mhm-step-actions">
            <a class="button button-secondary" href="<?php echo esc_url(self::step_url('license')); ?>">&larr; <?php esc_html_e('Back to License', 'mhm-rentiva'); ?></a>
            <a class="button button-primary" href="<?php echo esc_url(self::step_url('email')); ?>"><?php esc_html_e('Continue to Email', 'mhm-rentiva'); ?></a>
        </p>
        <?php
    }

    private static function render_step_email(): void
    {
        $sender_name = SettingsCore::get('mhm_rentiva_email_from_name', get_bloginfo('name'));
        $sender_email = SettingsCore::get('mhm_rentiva_email_from_address', get_option('admin_email'));
        $reply_address = SettingsCore::get('mhm_rentiva_email_reply_to', get_option('admin_email'));
        $test_mode = SettingsCore::get('mhm_rentiva_email_test_mode', '0');
        $test_address = SettingsCore::get('mhm_rentiva_email_test_address', get_option('admin_email'));
        $send_enabled = SettingsCore::get('mhm_rentiva_email_send_enabled', '1');
        $auto_enabled = SettingsCore::get('mhm_rentiva_email_auto_send', '1');
        $log_enabled = SettingsCore::get('mhm_rentiva_email_log_enabled', '1');
        ?>
        <h2><?php esc_html_e('Step 4: Email & Notifications', 'mhm-rentiva'); ?></h2>
        <p><?php esc_html_e('Configure the sender information and enable automatic notifications for bookings.', 'mhm-rentiva'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mhm_rentiva_setup_email'); ?>
            <input type="hidden" name="action" value="mhm_rentiva_setup_save_email">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Sender Name', 'mhm-rentiva'); ?></th>
                    <td><input type="text" name="sender_name" class="regular-text" value="<?php echo esc_attr($sender_name); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Sender Email', 'mhm-rentiva'); ?></th>
                    <td><input type="email" name="sender_email" class="regular-text" value="<?php echo esc_attr($sender_email); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Reply-To Address', 'mhm-rentiva'); ?></th>
                    <td><input type="email" name="reply_address" class="regular-text" value="<?php echo esc_attr($reply_address); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Test Mode', 'mhm-rentiva'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="test_mode" value="1" <?php checked($test_mode, '1'); ?> />
                            <?php esc_html_e('Enable test mode (emails will be sent only to the test address)', 'mhm-rentiva'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Test Email Address', 'mhm-rentiva'); ?></th>
                    <td><input type="email" name="test_address" class="regular-text" value="<?php echo esc_attr($test_address); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Automation', 'mhm-rentiva'); ?></th>
                    <td>
                        <label><input type="checkbox" name="send_enabled" value="1" <?php checked($send_enabled, '1'); ?> /> <?php esc_html_e('Email sending enabled', 'mhm-rentiva'); ?></label><br>
                        <label><input type="checkbox" name="auto_enabled" value="1" <?php checked($auto_enabled, '1'); ?> /> <?php esc_html_e('Automatic email sending enabled', 'mhm-rentiva'); ?></label><br>
                        <label><input type="checkbox" name="log_enabled" value="1" <?php checked($log_enabled, '1'); ?> /> <?php esc_html_e('Log emails in the email log post type', 'mhm-rentiva'); ?></label>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save and Continue', 'mhm-rentiva')); ?>
            <a class="button button-secondary" href="<?php echo esc_url(self::step_url('pages')); ?>">&larr; <?php esc_html_e('Back to Required Pages', 'mhm-rentiva'); ?></a>
        </form>
        <?php
    }

    private static function render_step_frontend(): void
    {
        // Check if WooCommerce is active and use its currency
        if (class_exists('WooCommerce')) {
            $currency = get_woocommerce_currency();
            $is_woocommerce_currency = true;
        } else {
            $currency = SettingsCore::get('mhm_rentiva_currency', 'USD');
            $is_woocommerce_currency = false;
        }
        
        $currency_position = SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
        $currencies = CurrencyHelper::get_currency_list_for_dropdown();
        
        // Get currency symbol for position examples
        $currency_symbol = CurrencyHelper::get_currency_symbol($currency);
        
        $positions = [
            'left'        => sprintf(__('Left (%s100)', 'mhm-rentiva'), $currency_symbol),
            'left_space'  => sprintf(__('Left Space (%s 100)', 'mhm-rentiva'), $currency_symbol),
            'right'       => sprintf(__('Right (100%s)', 'mhm-rentiva'), $currency_symbol),
            'right_space' => sprintf(__('Right Space (100 %s)', 'mhm-rentiva'), $currency_symbol),
        ];
        $default_days = (int) SettingsCore::get('mhm_rentiva_default_rental_days', 1);
        $min_days = (int) SettingsCore::get('mhm_rentiva_vehicle_min_rental_days', 1);
        $max_days = (int) SettingsCore::get('mhm_rentiva_vehicle_max_rental_days', 30);
        $show_features = SettingsCore::get('mhm_rentiva_vehicle_show_features', '1');
        $show_availability = SettingsCore::get('mhm_rentiva_vehicle_show_availability', '1');
        ?>
        <h2><?php esc_html_e('Step 5: Frontend & Display', 'mhm-rentiva'); ?></h2>
        <p><?php esc_html_e('Fine tune the visible defaults that appear on booking forms and vehicle cards.', 'mhm-rentiva'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mhm_rentiva_setup_frontend'); ?>
            <input type="hidden" name="action" value="mhm_rentiva_setup_save_frontend">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Currency', 'mhm-rentiva'); ?></th>
                    <td>
                        <?php if ($is_woocommerce_currency): ?>
                            <p class="description">
                                <strong><?php esc_html_e('Managed by WooCommerce:', 'mhm-rentiva'); ?></strong> 
                                <?php echo esc_html($currencies[$currency] ?? $currency); ?>
                            </p>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: link to WooCommerce settings */
                                    esc_html__('To change the currency, please visit %s.', 'mhm-rentiva'),
                                    '<a href="' . esc_url(admin_url('admin.php?page=wc-settings')) . '" target="_blank">' . esc_html__('WooCommerce Settings', 'mhm-rentiva') . '</a>'
                                );
                                ?>
                            </p>
                        <?php else: ?>
                            <select name="currency">
                                <?php foreach ($currencies as $code => $label): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Currency Position', 'mhm-rentiva'); ?></th>
                    <td>
                        <select name="currency_position">
                            <?php foreach ($positions as $pos => $label): ?>
                                <option value="<?php echo esc_attr($pos); ?>" <?php selected($currency_position, $pos); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Default Rental Days', 'mhm-rentiva'); ?></th>
                    <td><input type="number" name="default_days" min="1" max="30" value="<?php echo esc_attr($default_days); ?>" /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Minimum Rental Days', 'mhm-rentiva'); ?></th>
                    <td><input type="number" name="min_days" min="1" max="365" value="<?php echo esc_attr($min_days); ?>" /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Maximum Rental Days', 'mhm-rentiva'); ?></th>
                    <td><input type="number" name="max_days" min="<?php echo esc_attr(max(1, $min_days)); ?>" max="365" value="<?php echo esc_attr($max_days); ?>" /></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Vehicle Cards', 'mhm-rentiva'); ?></th>
                    <td>
                        <label><input type="checkbox" name="show_features" value="1" <?php checked($show_features, '1'); ?> /> <?php esc_html_e('Show feature badges', 'mhm-rentiva'); ?></label><br>
                        <label><input type="checkbox" name="show_availability" value="1" <?php checked($show_availability, '1'); ?> /> <?php esc_html_e('Show availability badge', 'mhm-rentiva'); ?></label>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save and Continue', 'mhm-rentiva')); ?>
            <a class="button button-secondary" href="<?php echo esc_url(self::step_url('email')); ?>">&larr; <?php esc_html_e('Back to Email', 'mhm-rentiva'); ?></a>
        </form>
        <?php
    }

    private static function render_step_summary(): void
    {
        $steps = self::get_steps();
        $completed = get_option(self::OPTION_COMPLETED, '0') === '1';
        ?>
        <h2><?php esc_html_e('Final Step: Summary & Tests', 'mhm-rentiva'); ?></h2>
        <p><?php esc_html_e('Review the checklist below and run a quick booking to confirm everything is ready.', 'mhm-rentiva'); ?></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Step', 'mhm-rentiva'); ?></th>
                    <th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($steps as $slug => $label): ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><?php echo self::format_status_badge(self::is_step_completed($slug) ? 'ok' : 'warning'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mhm-summary-actions">
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings')); ?>"><?php esc_html_e('Open Settings', 'mhm-rentiva'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-shortcode-pages')); ?>"><?php esc_html_e('Review Shortcode Pages', 'mhm-rentiva'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-settings&tab=email-templates')); ?>"><?php esc_html_e('Send Test Email', 'mhm-rentiva'); ?></a>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mhm_rentiva_setup_finish'); ?>
            <input type="hidden" name="action" value="mhm_rentiva_setup_finish">
            <?php
            if ($completed) {
                echo '<p>' . esc_html__('Setup wizard was completed previously. You can still finish again to return to the dashboard.', 'mhm-rentiva') . '</p>';
            }
            submit_button(__('Complete Setup', 'mhm-rentiva'));
            ?>
        </form>
        <p class="mhm-step-actions">
            <a class="button button-secondary" href="<?php echo esc_url(self::step_url('frontend')); ?>">&larr; <?php esc_html_e('Back to Frontend Settings', 'mhm-rentiva'); ?></a>
            <a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-dashboard')); ?>"><?php esc_html_e('Go to Dashboard', 'mhm-rentiva'); ?></a>
        </p>
        <?php
    }

    public static function handle_save_license(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_setup_license');
        $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        
        if (empty($key)) {
            wp_safe_redirect(self::step_url('license', ['error' => 'empty_key']));
            exit;
        }
        
        // ⭐ Use LicenseManager to activate license (same as License Admin page)
        $license_manager = class_exists(LicenseManager::class) ? LicenseManager::instance() : null;
        if ($license_manager) {
            $result = $license_manager->activate($key);
            
            if (is_wp_error($result)) {
                $error_code = $result->get_error_code();
                wp_safe_redirect(self::step_url('license', ['error' => $error_code, 'message' => urlencode($result->get_error_message())]));
                exit;
            }
            
            // Success - redirect to next step
            wp_safe_redirect(self::step_url('pages', ['updated' => '1', 'license' => 'activated']));
            exit;
        } else {
            // Fallback: just save the key if LicenseManager is not available
            update_option('mhm_rentiva_license_key', $key);
            wp_safe_redirect(self::step_url('pages', ['updated' => '1']));
            exit;
        }
    }

    public static function handle_create_pages(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_setup_pages');

        $created = 0;
        foreach (self::get_required_pages() as $page) {
            if (!self::locate_shortcode_page($page['shortcode'])) {
                $new_id = self::create_page($page);
                if ($new_id) {
                    $created++;
                    ShortcodeUrlManager::clear_cache($page['shortcode']);
                }
            }
        }

        wp_safe_redirect(self::step_url('pages', ['created' => (string) $created]));
        exit;
    }

    public static function handle_save_email(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_setup_email');

        $settings = get_option('mhm_rentiva_settings', []);
        $settings['mhm_rentiva_email_from_name'] = sanitize_text_field(wp_unslash($_POST['sender_name'] ?? ''));
        $settings['mhm_rentiva_email_from_address'] = sanitize_email(wp_unslash($_POST['sender_email'] ?? ''));
        $settings['mhm_rentiva_email_reply_to'] = sanitize_email(wp_unslash($_POST['reply_address'] ?? ''));
        $settings['mhm_rentiva_email_test_mode'] = isset($_POST['test_mode']) ? '1' : '0';
        $settings['mhm_rentiva_email_test_address'] = sanitize_email(wp_unslash($_POST['test_address'] ?? ''));
        $settings['mhm_rentiva_email_send_enabled'] = isset($_POST['send_enabled']) ? '1' : '0';
        $settings['mhm_rentiva_email_auto_send'] = isset($_POST['auto_enabled']) ? '1' : '0';
        $settings['mhm_rentiva_email_log_enabled'] = isset($_POST['log_enabled']) ? '1' : '0';
        update_option('mhm_rentiva_settings', $settings);

        wp_safe_redirect(self::step_url('frontend', ['saved' => '1']));
        exit;
    }

    public static function handle_save_frontend(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_setup_frontend');

        $settings = get_option('mhm_rentiva_settings', []);
        $settings['mhm_rentiva_currency'] = sanitize_text_field(wp_unslash($_POST['currency'] ?? 'USD'));

        $currency_position = sanitize_text_field(wp_unslash($_POST['currency_position'] ?? 'right_space'));
        $allowed_positions = ['left', 'left_space', 'right', 'right_space'];
        $settings['mhm_rentiva_currency_position'] = in_array($currency_position, $allowed_positions, true) ? $currency_position : 'right_space';

        $settings['mhm_rentiva_default_rental_days'] = (string) max(1, min(30, (int) ($_POST['default_days'] ?? 1)));
        $settings['mhm_rentiva_vehicle_min_rental_days'] = (string) max(1, min(365, (int) ($_POST['min_days'] ?? 1)));
        $settings['mhm_rentiva_vehicle_max_rental_days'] = (string) max((int) $settings['mhm_rentiva_vehicle_min_rental_days'], min(365, (int) ($_POST['max_days'] ?? 30)));

        $settings['mhm_rentiva_vehicle_show_features'] = isset($_POST['show_features']) ? '1' : '0';
        $settings['mhm_rentiva_vehicle_show_availability'] = isset($_POST['show_availability']) ? '1' : '0';

        update_option('mhm_rentiva_settings', $settings);

        wp_safe_redirect(self::step_url('summary', ['saved' => '1']));
        exit;
    }

    public static function handle_finish(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_setup_finish');

        update_option(self::OPTION_COMPLETED, '1');
        delete_option(self::OPTION_REDIRECT);

        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-dashboard'));
        exit;
    }

    public static function handle_skip(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to perform this action.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_setup_skip');
        update_option(self::OPTION_COMPLETED, '1');
        delete_option(self::OPTION_REDIRECT);
        wp_safe_redirect(admin_url('admin.php?page=mhm-rentiva-dashboard'));
        exit;
    }

    public static function maybe_redirect(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        $should_redirect = get_option(self::OPTION_REDIRECT, '0') === '1';
        $completed = get_option(self::OPTION_COMPLETED, '0') === '1';

        if ($should_redirect && !$completed) {
            delete_option(self::OPTION_REDIRECT);
            if (!self::is_wizard_page()) {
                wp_safe_redirect(self::step_url('system'));
                exit;
            }
        }
    }

    private static function is_wizard_page(): bool
    {
        return isset($_GET['page']) && $_GET['page'] === self::PAGE_SLUG;
    }

    private static function get_steps(): array
    {
        return [
            'system'   => __('System Check', 'mhm-rentiva'),
            'license'  => __('License', 'mhm-rentiva'),
            'pages'    => __('Required Pages', 'mhm-rentiva'),
            'email'    => __('Email Settings', 'mhm-rentiva'),
            'frontend' => __('Frontend & Display', 'mhm-rentiva'),
            'summary'  => __('Summary & Tests', 'mhm-rentiva'),
        ];
    }

    private static function get_current_step(): string
    {
        $steps = array_keys(self::get_steps());
        $requested = isset($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : '';
        if ($requested && in_array($requested, $steps, true)) {
            return $requested;
        }
        return $steps[0];
    }

    private static function step_url(string $step, array $args = []): string
    {
        $url = add_query_arg(
            array_merge(
                [
                    'page' => self::PAGE_SLUG,
                    'step' => $step,
                ],
                $args
            ),
            admin_url('admin.php')
        );
        return $url;
    }

    private static function skip_url(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=mhm_rentiva_setup_skip'),
            'mhm_rentiva_setup_skip'
        );
    }

    private static function get_required_pages(): array
    {
        return [
            [
                'label' => __('Booking Form', 'mhm-rentiva'),
                'shortcode' => 'rentiva_booking_form',
                'recommended_url' => '/rentiva/booking-form/',
            ],
            [
                'label' => __('Booking Confirmation', 'mhm-rentiva'),
                'shortcode' => 'rentiva_booking_confirmation',
                'recommended_url' => '/rentiva/booking-confirmation/',
            ],
            [
                'label' => __('My Account (Dashboard)', 'mhm-rentiva'),
                'shortcode' => 'rentiva_my_account',
                'recommended_url' => '/rentiva/account/dashboard/',
            ],
            [
                'label' => __('My Bookings', 'mhm-rentiva'),
                'shortcode' => 'rentiva_my_bookings',
                'recommended_url' => '/rentiva/account/bookings/',
            ],
            [
                'label' => __('Favorites', 'mhm-rentiva'),
                'shortcode' => 'rentiva_my_favorites',
                'recommended_url' => '/rentiva/account/favorites/',
            ],
            [
                'label' => __('Payment History', 'mhm-rentiva'),
                'shortcode' => 'rentiva_payment_history',
                'recommended_url' => '/rentiva/account/payments/',
            ],
            [
                'label' => __('Login Form', 'mhm-rentiva'),
                'shortcode' => 'rentiva_login_form',
                'recommended_url' => '/rentiva/account/login/',
            ],
            [
                'label' => __('Registration Form', 'mhm-rentiva'),
                'shortcode' => 'rentiva_register_form',
                'recommended_url' => '/rentiva/account/register/',
            ],
            [
                'label' => __('Contact Form', 'mhm-rentiva'),
                'shortcode' => 'rentiva_contact',
                'recommended_url' => '/rentiva/contact/',
            ],
        ];
    }

    private static function locate_shortcode_page(string $shortcode)
    {
        if (!class_exists(ShortcodeUrlManager::class)) {
            return null;
        }

        return ShortcodeUrlManager::get_page_id($shortcode);
    }

    private static function create_page(array $page): ?int
    {
        $content = '[' . $page['shortcode'] . ']';
        $existing = self::locate_shortcode_page($page['shortcode']);
        if ($existing) {
            return $existing;
        }

        $post_id = wp_insert_post([
            'post_title'   => $page['label'],
            'post_name'    => sanitize_title($page['label']),
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        if (is_wp_error($post_id)) {
            return null;
        }

        return (int) $post_id;
    }

    private static function print_styles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style>
            .mhm-setup-steps {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                list-style: none;
                padding-left: 0;
                margin-bottom: 20px;
            }
            .mhm-setup-steps li {
                padding: 6px 14px;
                border-radius: 20px;
                background: #f6f7f7;
                border: 1px solid #dcdcdc;
                color: #1d2327;
                transition: all 0.2s;
            }
            .mhm-setup-steps li.current {
                background: #2271b1;
                color: #fff;
                border-color: #2271b1;
                box-shadow: none;
            }
            .mhm-setup-steps li.completed {
                border-color: #dcdcdc;
                box-shadow: none;
            }
            .mhm-setup-steps li a {
                color: inherit;
                text-decoration: none;
            }
            .mhm-system-table .mhm-system-note {
                font-size: 12px;
                margin-top: 4px;
                color: #646970;
            }
            .mhm-status {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 999px;
                font-size: 12px;
            }
            .mhm-status-ok {
                background: #d5f2e3;
                color: #1d7a46;
            }
            .mhm-status-warning {
                background: #fff4ce;
                color: #7a5b00;
            }
            .mhm-status-fail {
                background: #fdeaea;
                color: #a12622;
            }
            .mhm-step-actions {
                margin-top: 16px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .mhm-summary-actions {
                display: flex;
                gap: 10px;
                margin: 16px 0;
                flex-wrap: wrap;
            }
            .mhm-license-card {
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                background: #fff;
                padding: 16px 20px;
                margin-bottom: 16px;
            }
            .mhm-license-card--active {
                border-color: #3ab27b;
                box-shadow: 0 0 0 1px rgba(58, 178, 123, 0.2);
            }
            .mhm-license-card__status {
                font-weight: 600;
                margin-bottom: 12px;
            }
            .mhm-license-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 12px 24px;
                margin-bottom: 8px;
            }
            .mhm-license-label {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #646970;
            }
            .mhm-license-code {
                display: inline-block;
                font-size: 14px;
                padding: 4px 8px;
                background: #f6f7f7;
                border-radius: 4px;
                margin-top: 4px;
            }
        </style>
        <?php
    }

    private static function get_system_checks(): array
    {
        global $wpdb;

        $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
        $wp_version = get_bloginfo('version');
        $wp_ok = version_compare($wp_version, '6.0', '>=');
        $db_version = $wpdb instanceof \wpdb ? $wpdb->db_version() : __('Unknown', 'mhm-rentiva');
        $memory_mb = self::memory_limit_mb();

        $checks = [
            [
                'label' => __('PHP Version', 'mhm-rentiva'),
                'current' => PHP_VERSION,
                'expected' => '7.4+',
                'status' => $php_ok ? 'ok' : 'fail',
                'message' => $php_ok ? '' : __('Update PHP to 7.4 or higher.', 'mhm-rentiva'),
            ],
            [
                'label' => __('WordPress Version', 'mhm-rentiva'),
                'current' => $wp_version,
                'expected' => '6.0+',
                'status' => $wp_ok ? 'ok' : 'fail',
                'message' => $wp_ok ? '' : __('Please update WordPress to the latest version.', 'mhm-rentiva'),
            ],
            [
                'label' => __('Database Version', 'mhm-rentiva'),
                'current' => (string) $db_version,
                'expected' => __('MySQL 5.7+ / MariaDB 10.3+', 'mhm-rentiva'),
                'status' => 'ok',
                'message' => '',
            ],
            [
                'label' => __('WP Memory Limit', 'mhm-rentiva'),
                'current' => sprintf('%d MB', $memory_mb),
                'expected' => __('256 MB recommended', 'mhm-rentiva'),
                'status' => $memory_mb >= 256 ? 'ok' : ($memory_mb >= 128 ? 'warning' : 'fail'),
                'message' => $memory_mb >= 256 ? '' : __('Increase WP_MEMORY_LIMIT to at least 256 MB via wp-config.php', 'mhm-rentiva'),
            ],
        ];

        $max_execution = (int) ini_get('max_execution_time');
        $checks[] = [
            'label' => __('PHP max_execution_time', 'mhm-rentiva'),
            'current' => $max_execution ? sprintf('%d s', $max_execution) : __('Unlimited', 'mhm-rentiva'),
            'expected' => __('60s+', 'mhm-rentiva'),
            'status' => $max_execution >= 60 || $max_execution === 0 ? 'ok' : 'warning',
            'message' => $max_execution >= 60 || $max_execution === 0 ? '' : __('Increase max_execution_time to 60 seconds for large imports.', 'mhm-rentiva'),
        ];

        $https = is_ssl() || (defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN);
        $checks[] = [
            'label' => __('HTTPS / SSL', 'mhm-rentiva'),
            'current' => $https ? __('Enabled', 'mhm-rentiva') : __('Not detected', 'mhm-rentiva'),
            'expected' => __('Valid SSL certificate', 'mhm-rentiva'),
            'status' => $https ? 'ok' : 'warning',
            'message' => $https ? '' : __('Install an SSL certificate to secure customer data.', 'mhm-rentiva'),
        ];

        $cron_enabled = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;
        $checks[] = [
            'label' => __('WP-Cron', 'mhm-rentiva'),
            'current' => $cron_enabled ? __('Active', 'mhm-rentiva') : __('Disabled', 'mhm-rentiva'),
            'expected' => __('Required for scheduled emails & jobs', 'mhm-rentiva'),
            'status' => $cron_enabled ? 'ok' : 'warning',
            'message' => $cron_enabled ? '' : __('Enable WP-Cron or set a real cron job for reminders.', 'mhm-rentiva'),
        ];

        $email_layer = self::is_smtp_layer_detected();
        $checks[] = [
            'label' => __('Email Delivery', 'mhm-rentiva'),
            'current' => $email_layer ? __('SMTP/Provider detected', 'mhm-rentiva') : __('Default wp_mail()', 'mhm-rentiva'),
            'expected' => __('SMTP provider recommended', 'mhm-rentiva'),
            'status' => $email_layer ? 'ok' : 'warning',
            'message' => $email_layer ? '' : __('Configure SMTP (WP Mail SMTP, Post SMTP, etc.) for reliable delivery.', 'mhm-rentiva'),
        ];

        return $checks;
    }

    private static function format_license_expiration(?int $timestamp): string
    {
        if (empty($timestamp)) {
            return __('Unknown', 'mhm-rentiva');
        }

        $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);

        if ($timestamp >= time()) {
            $remaining = human_time_diff(time(), $timestamp);
            /* translators: 1: %1$s; 2: %2$s. */
            return sprintf(__('Expires %1$s (%2$s remaining)', 'mhm-rentiva'), $date, $remaining);
        }

        $since = human_time_diff($timestamp, time());
        /* translators: 1: %1$s; 2: %2$s. */
        return sprintf(__('Expired %1$s (%2$s ago)', 'mhm-rentiva'), $date, $since);
    }

    private static function format_status_badge(string $status): string
    {
        $labels = [
            'ok' => __('Ready', 'mhm-rentiva'),
            'warning' => __('Warning', 'mhm-rentiva'),
            'fail' => __('Required', 'mhm-rentiva'),
        ];
        $text = $labels[$status] ?? __('Unknown', 'mhm-rentiva');

        return sprintf(
            '<span class="mhm-status mhm-status-%1$s">%2$s</span>',
            esc_attr($status),
            esc_html($text)
        );
    }

    private static function memory_limit_mb(): int
    {
        $limit = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : ini_get('memory_limit');
        if (function_exists('wp_convert_hr_to_bytes')) {
            $bytes = wp_convert_hr_to_bytes($limit);
        } else {
            $bytes = (int) $limit;
        }
        if ($bytes <= 0) {
            return 0;
        }
        return (int) floor($bytes / 1048576);
    }

    private static function are_system_checks_ok(): bool
    {
        foreach (self::get_system_checks() as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }
        }
        return true;
    }

    private static function is_step_completed(string $step): bool
    {
        switch ($step) {
            case 'system':
                return self::are_system_checks_ok();
            case 'license':
                if (class_exists(LicenseManager::class)) {
                    return LicenseManager::instance()->isActive();
                }
                return (bool) get_option('mhm_rentiva_license_key', '');
            case 'pages':
                return self::are_required_pages_present();
            case 'email':
                return SettingsCore::get('mhm_rentiva_email_send_enabled', '0') === '1'
                    && (bool) SettingsCore::get('mhm_rentiva_email_from_address', '');
            case 'payments':
                return self::is_payment_ready();
            case 'frontend':
                return (bool) SettingsCore::get('mhm_rentiva_currency', 'USD');
            case 'summary':
                return get_option(self::OPTION_COMPLETED, '0') === '1';
            default:
                return false;
        }
    }

    private static function are_required_pages_present(): bool
    {
        foreach (self::get_required_pages() as $page) {
            if (!self::locate_shortcode_page($page['shortcode'])) {
                return false;
            }
        }
        return true;
    }

    private static function is_payment_ready(): bool
    {
        $settings = get_option('mhm_rentiva_settings', []);
        $gateways = [

            '0', // ⭐ Offline payment removed - WooCommerce handles all payments
        ];

        return in_array('1', $gateways, true);
    }

    private static function is_smtp_layer_detected(): bool
    {
        $hooks = ['phpmailer_init', 'wp_mail', 'pre_wp_mail'];
        foreach ($hooks as $hook) {
            if (has_filter($hook)) {
                return true;
            }
        }

        $constants = [
            'WP_MAIL_SMTP_VERSION',
            'FLUENTMAIL_PLUGIN_VERSION',
            'FLUENTMAIL_VERSION',
            'POST_SMTP_VER',
            'POSTSMTP_VER',
            'SENDGRID_VERSION',
            'MAILBANK_PLUGIN_VERSION',
        ];
        foreach ($constants as $constant) {
            if (defined($constant)) {
                return true;
            }
        }

        $actions = [
            'wp_mail_smtp_init',
            'fluentmail_loaded',
            'post_smtp_init',
            'mailbank_loaded',
        ];
        foreach ($actions as $action) {
            if (did_action($action)) {
                return true;
            }
        }

        $classes = [
            'FluentMail\\App\\Plugin',
            'FluentMail\\App\\Services\\Mailer',
            'Postman',
            'PostmanWpMailBinder',
            'Mail_Bank',
            'Sendgrid_For_WordPress',
        ];
        foreach ($classes as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        if (function_exists('wp_mail_smtp')) {
            return true;
        }

        return false;
    }
}

