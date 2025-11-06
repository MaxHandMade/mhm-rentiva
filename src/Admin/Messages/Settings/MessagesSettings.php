<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Settings;

use MHMRentiva\Admin\Core\Utilities\SettingsHelper;
use MHMRentiva\Admin\Core\Utilities\ErrorHandler;
use MHMRentiva\Admin\Messages\Core\MessageUrlHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Messaging system settings
 */
final class MessagesSettings
{
    const OPTION_GROUP = 'mhm_rentiva_messages';
    const OPTION_NAME = 'mhm_rentiva_messages_settings';

    /**
     * Initialize settings
     */
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'register_settings']);
    }

    /**
     * Register settings
     */
    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default' => self::get_default_settings()
            ]
        );

        // Email settings section
        add_settings_section(
            'mhm_messages_email',
            __('Email Settings', 'mhm-rentiva'),
            null,
            self::OPTION_GROUP
        );

        // General settings section
        add_settings_section(
            'mhm_messages_general',
            __('General Settings', 'mhm-rentiva'),
            null,
            self::OPTION_GROUP
        );

        // Categories section
        add_settings_section(
            'mhm_messages_categories',
            __('Message Categories', 'mhm-rentiva'),
            null,
            self::OPTION_GROUP
        );

        // Statuses section
        add_settings_section(
            'mhm_messages_statuses',
            __('Message Statuses', 'mhm-rentiva'),
            null,
            self::OPTION_GROUP
        );
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings(array $input): array
    {
        $sanitized = [];
        
        // Boolean values
        $boolean_fields = [
            'email_admin_notifications',
            'email_customer_notifications', 
            'email_reply_notifications',
            'email_status_change_notifications',
            'dashboard_widget_enabled',
            'auto_reply_enabled'
        ];
        
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? (bool) $input[$field] : false;
        }
        
        // Numeric values
        $sanitized['lite_messages_per_month'] = absint($input['lite_messages_per_month'] ?? 10);
        $sanitized['lite_messages_per_day'] = absint($input['lite_messages_per_day'] ?? 3);
        $sanitized['dashboard_widget_max_messages'] = absint($input['dashboard_widget_max_messages'] ?? 5);
        
        // String values
        $sanitized['admin_email'] = sanitize_email((string) ($input['admin_email'] ?? get_option('admin_email')));
        $sanitized['from_name'] = sanitize_text_field((string) ($input['from_name'] ?? get_bloginfo('name')));
        $sanitized['from_email'] = sanitize_email((string) ($input['from_email'] ?? get_option('admin_email')));
        
        // Categories and statuses
        $sanitized['categories'] = self::sanitize_categories($input['categories'] ?? []);
        $sanitized['statuses'] = self::sanitize_statuses($input['statuses'] ?? []);
        
        // Email templates
        $sanitized['auto_reply_message'] = wp_kses_post($input['auto_reply_message'] ?? '');
        
        return $sanitized;
    }

    /**
     * Sanitize categories
     */
    private static function sanitize_categories(array $categories): array
    {
        $sanitized = [];
        foreach ($categories as $key => $value) {
            // Support both array format [key => name] and nested array format [['name' => 'value']]
            if (is_array($value)) {
                $name = $value['name'] ?? $value[0] ?? '';
            } else {
                $name = $value;
            }
            
            if (!empty(trim((string) $name))) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : sanitize_key($name);
                $sanitized[$sanitized_key] = sanitize_text_field((string) $name);
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize statuses
     */
    private static function sanitize_statuses(array $statuses): array
    {
        $sanitized = [];
        foreach ($statuses as $key => $value) {
            // Support both array format [key => name] and nested array format [['name' => 'value']]
            if (is_array($value)) {
                $name = $value['name'] ?? $value[0] ?? '';
            } else {
                $name = $value;
            }
            
            if (!empty(trim((string) $name))) {
                $sanitized_key = is_string($key) ? sanitize_key($key) : sanitize_key($name);
                $sanitized[$sanitized_key] = sanitize_text_field((string) $name);
            }
        }
        return $sanitized;
    }

    /**
     * Default settings
     */
    public static function get_default_settings(): array
    {
        return [
            // Email settings
            'email_admin_notifications' => true,
            'email_customer_notifications' => true,
            'email_reply_notifications' => true,
            'email_status_change_notifications' => true,
            
            // Message limits (for Lite version)
            'lite_messages_per_month' => 10,
            'lite_messages_per_day' => 3,
            
            // Message categories
            'categories' => [
                'general' => __('General', 'mhm-rentiva'),
                'booking' => __('Booking', 'mhm-rentiva'),
                'payment' => __('Payment', 'mhm-rentiva'),
                'technical' => __('Technical Support', 'mhm-rentiva'),
                'complaint' => __('Complaint', 'mhm-rentiva'),
                'suggestion' => __('Suggestion', 'mhm-rentiva'),
            ],
            
            // Message statuses
            'statuses' => [
                'pending' => __('Pending', 'mhm-rentiva'),
                'answered' => __('Answered', 'mhm-rentiva'),
                'closed' => __('Closed', 'mhm-rentiva'),
                'urgent' => __('Urgent', 'mhm-rentiva'),
            ],
            
            // Message priorities
            'priorities' => [
                'normal' => __('Normal', 'mhm-rentiva'),
                'high' => __('High', 'mhm-rentiva'),
                'urgent' => __('Urgent', 'mhm-rentiva'),
            ],
            
            // Email template settings
            'admin_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'email_from_name' => get_bloginfo('name'),
            'email_from_email' => get_option('admin_email'),
            'email_reply_to' => get_option('admin_email'),
            
            // Dashboard widget settings
            'dashboard_widget_enabled' => true,
            'dashboard_widget_max_messages' => 5,
            
            // Auto reply settings
            'auto_reply_enabled' => false,
            'auto_reply_message' => __('Your message has been received. We will get back to you as soon as possible.', 'mhm-rentiva'),
            
            // Thread settings
            'max_thread_messages' => 50,
            'auto_close_inactive_days' => 30,
            
            // Notification settings
            'notification_sound_enabled' => false,
            'notification_popup_enabled' => true,
            
            // Security settings
            'token_expiry_hours' => 24,
            'max_attachments_per_message' => 3,
            'allowed_attachment_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
            'max_attachment_size_mb' => 5,
        ];
    }

    /**
     * Get current settings
     */
    public static function get_settings(): array
    {
        $defaults = self::get_default_settings();
        $settings = get_option(self::OPTION_NAME, []);
        
        return array_merge($defaults, $settings);
    }

    /**
     * Get specific setting
     */
    public static function get_setting(string $key, $default = null)
    {
        $settings = self::get_settings();
        return $settings[$key] ?? $default;
    }

    /**
     * Save settings
     */
    public static function save_settings(array $settings): bool
    {
        $sanitized = self::sanitize_settings($settings);
        return update_option(self::OPTION_NAME, $sanitized);
    }



    /**
     * Get categories
     */
    public static function get_categories(): array
    {
        return self::get_setting('categories', []);
    }

    /**
     * Get statuses
     */
    public static function get_statuses(): array
    {
        return self::get_setting('statuses', []);
    }

    /**
     * Get priorities
     */
    public static function get_priorities(): array
    {
        return self::get_setting('priorities', [
            'normal' => __('Normal', 'mhm-rentiva'),
            'high' => __('High', 'mhm-rentiva'),
            'urgent' => __('Urgent', 'mhm-rentiva'),
        ]);
    }

    /**
     * Check if email notifications are enabled
     */
    public static function is_email_enabled(string $type): bool
    {
        return self::get_setting('email_' . $type . '_notifications', true);
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
        $active_tab = sanitize_key($_GET['tab'] ?? 'email');
        
        ?>
        <div class="wrap mhm-settings-tabs">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(MessageUrlHelper::get_messages_settings_url('email')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(MessageUrlHelper::get_messages_settings_url('general')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(MessageUrlHelper::get_messages_settings_url('categories')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Categories', 'mhm-rentiva'); ?>
                </a>
                <a href="<?php echo esc_url(MessageUrlHelper::get_messages_settings_url('statuses')); ?>" 
                   class="nav-tab <?php echo $active_tab === 'statuses' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Statuses', 'mhm-rentiva'); ?>
                </a>
            </nav>

            <!-- Settings Form -->
            <form method="post" action="options.php" id="mhm-messages-settings-form">
                <?php 
                settings_fields(self::OPTION_GROUP);
                ?>

                <!-- Email Tab -->
                <div id="email" class="tab-content <?php echo $active_tab === 'email' ? 'active' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Admin Email', 'mhm-rentiva'); ?></th>
                            <td>
                                <input type="email" name="<?php echo self::OPTION_NAME; ?>[admin_email]" 
                                       value="<?php echo esc_attr($settings['admin_email']); ?>" class="regular-text">
                                <p class="description"><?php _e('Email address for message notifications', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sender Name', 'mhm-rentiva'); ?></th>
                            <td>
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[from_name]" 
                                       value="<?php echo esc_attr($settings['from_name']); ?>" class="regular-text">
                                <p class="description"><?php _e('Sender name to display in emails', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sender Email', 'mhm-rentiva'); ?></th>
                            <td>
                                <input type="email" name="<?php echo self::OPTION_NAME; ?>[from_email]" 
                                       value="<?php echo esc_attr($settings['from_email']); ?>" class="regular-text">
                                <p class="description"><?php _e('Email address to send emails from', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Admin Notifications', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[email_admin_notifications]" 
                                           value="1" <?php checked($settings['email_admin_notifications']); ?>>
                                    <?php _e('Send notification to admin when new message arrives', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Customer Notifications', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[email_customer_notifications]" 
                                           value="1" <?php checked($settings['email_customer_notifications']); ?>>
                                    <?php _e('Send notification to customer when reply arrives', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- General Tab -->
                <div id="general" class="tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Dashboard Widget', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[dashboard_widget_enabled]" 
                                           value="1" <?php checked($settings['dashboard_widget_enabled']); ?>>
                                    <?php _e('Show message widget in dashboard', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Widget Max Messages', 'mhm-rentiva'); ?></th>
                            <td>
                                <input type="number" name="<?php echo self::OPTION_NAME; ?>[dashboard_widget_max_messages]" 
                                       value="<?php echo esc_attr($settings['dashboard_widget_max_messages']); ?>" 
                                       min="1" max="20" class="small-text">
                                <p class="description"><?php _e('Maximum number of messages to show in dashboard widget', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Auto Reply', 'mhm-rentiva'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo self::OPTION_NAME; ?>[auto_reply_enabled]" 
                                           value="1" <?php checked($settings['auto_reply_enabled']); ?>>
                                    <?php _e('Send automatic reply to new messages', 'mhm-rentiva'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Auto Reply Message', 'mhm-rentiva'); ?></th>
                            <td>
                                <textarea name="<?php echo self::OPTION_NAME; ?>[auto_reply_message]" 
                                          rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['auto_reply_message']); ?></textarea>
                                <p class="description"><?php _e('Automatic reply message to send to customers', 'mhm-rentiva'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Categories Tab -->
                <div id="categories" class="tab-content <?php echo $active_tab === 'categories' ? 'active' : ''; ?>">
                    <div id="category-list">
                        <?php foreach ($settings['categories'] as $key => $name): ?>
                            <div class="mhm-category-item">
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[categories][<?php echo esc_attr($key); ?>]" 
                                       value="<?php echo esc_attr($name); ?>" class="category-name" required>
                                <button type="button" class="remove-category-btn"><?php _e('Delete', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mhm-add-item">
                        <input type="text" id="new-category-name" placeholder="<?php _e('New category name', 'mhm-rentiva'); ?>">
                        <button type="button" id="add-category-btn" class="button"><?php _e('Add Category', 'mhm-rentiva'); ?></button>
                    </div>
                </div>

                <!-- Statuses Tab -->
                <div id="statuses" class="tab-content <?php echo $active_tab === 'statuses' ? 'active' : ''; ?>">
                    <div id="status-list">
                        <?php foreach ($settings['statuses'] as $key => $name): ?>
                            <div class="mhm-status-item">
                                <input type="text" name="<?php echo self::OPTION_NAME; ?>[statuses][<?php echo esc_attr($key); ?>]" 
                                       value="<?php echo esc_attr($name); ?>" class="status-name" required>
                                <button type="button" class="remove-status-btn"><?php _e('Delete', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mhm-add-item">
                        <input type="text" id="new-status-name" placeholder="<?php _e('New status name', 'mhm-rentiva'); ?>">
                        <button type="button" id="add-status-btn" class="button"><?php _e('Add Status', 'mhm-rentiva'); ?></button>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'mhm-rentiva')); ?>
            </form>
        </div>
        <?php
    }
}
