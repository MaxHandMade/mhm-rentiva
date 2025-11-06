<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Comments\CommentsSettings;

/**
 * ✅ COMMENTS SETTINGS GROUP
 * 
 * Manages all settings related to comments
 */
final class CommentsSettingsGroup
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    public static function register(): void
    {
        // Comments Settings Section
        add_settings_section(
            'mhm_rentiva_comments_section',
            __('Comments Settings', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            'mhm_rentiva_settings'
        );

        // Register settings for saving
        register_setting(
            'mhm_rentiva_comments_settings',
            'mhm_rentiva_comments_settings',
            [
                'sanitize_callback' => [self::class, 'sanitize_comments_settings'],
                'default' => CommentsSettings::get_default_settings()
            ]
        );

        // Approval Settings
        add_settings_field(
            'comments_auto_approve',
            __('Auto Approve Comments', 'mhm-rentiva'),
            [self::class, 'render_auto_approve_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'comments_require_login',
            __('Require Login', 'mhm-rentiva'),
            [self::class, 'render_require_login_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'comments_allow_guests',
            __('Allow Guest Comments', 'mhm-rentiva'),
            [self::class, 'render_allow_guests_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        // Limits Settings
        add_settings_field(
            'comments_per_page',
            __('Comments Per Page', 'mhm-rentiva'),
            [self::class, 'render_comments_per_page_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'comment_length_min',
            __('Minimum Comment Length', 'mhm-rentiva'),
            [self::class, 'render_comment_length_min_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'comment_length_max',
            __('Maximum Comment Length', 'mhm-rentiva'),
            [self::class, 'render_comment_length_max_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        // Spam Protection Settings
        add_settings_field(
            'spam_protection_enabled',
            __('Enable Spam Protection', 'mhm-rentiva'),
            [self::class, 'render_spam_protection_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'spam_words',
            __('Spam Words', 'mhm-rentiva'),
            [self::class, 'render_spam_words_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'rate_limiting_enabled',
            __('Enable Rate Limiting', 'mhm-rentiva'),
            [self::class, 'render_rate_limiting_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'rate_limiting_time_window',
            __('Rate Limiting Time Window (minutes)', 'mhm-rentiva'),
            [self::class, 'render_rate_limiting_time_window_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'rate_limiting_max_attempts',
            __('Max Attempts Per Time Window', 'mhm-rentiva'),
            [self::class, 'render_rate_limiting_max_attempts_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        // Display Settings
        add_settings_field(
            'show_ratings',
            __('Show Ratings', 'mhm-rentiva'),
            [self::class, 'render_show_ratings_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'show_avatars',
            __('Show Avatars', 'mhm-rentiva'),
            [self::class, 'render_show_avatars_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'allow_editing',
            __('Allow Comment Editing', 'mhm-rentiva'),
            [self::class, 'render_allow_editing_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'allow_deletion',
            __('Allow Comment Deletion', 'mhm-rentiva'),
            [self::class, 'render_allow_deletion_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'edit_time_limit',
            __('Edit Time Limit (hours)', 'mhm-rentiva'),
            [self::class, 'render_edit_time_limit_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        // Notifications Settings
        add_settings_field(
            'admin_notification_new',
            __('Notify Admin on New Comment', 'mhm-rentiva'),
            [self::class, 'render_admin_notification_new_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'user_notification_approved',
            __('Notify User on Comment Approval', 'mhm-rentiva'),
            [self::class, 'render_user_notification_approved_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        // Cache Settings
        add_settings_field(
            'cache_enabled',
            __('Enable Comment Cache', 'mhm-rentiva'),
            [self::class, 'render_cache_enabled_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );

        add_settings_field(
            'cache_duration',
            __('Cache Duration (minutes)', 'mhm-rentiva'),
            [self::class, 'render_cache_duration_field'],
            'mhm_rentiva_settings',
            'mhm_rentiva_comments_section'
        );
    }

    public static function render_section_description(): void
    {
        echo '<p>' . esc_html__('Configure comment and rating system settings for vehicles.', 'mhm-rentiva') . '</p>';
    }

    // Approval Settings
    public static function render_auto_approve_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['approval']['auto_approve']) ? (bool) $settings['approval']['auto_approve'] : false;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[approval][auto_approve]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Automatically approve new comments without moderation.', 'mhm-rentiva') . '</p>';
    }

    public static function render_require_login_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['approval']['require_login']) ? (bool) $settings['approval']['require_login'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[approval][require_login]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Require users to be logged in to comment.', 'mhm-rentiva') . '</p>';
    }

    public static function render_allow_guests_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['approval']['allow_guest_comments']) ? (bool) $settings['approval']['allow_guest_comments'] : false;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[approval][allow_guest_comments]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Allow guest users to comment (requires login to be disabled).', 'mhm-rentiva') . '</p>';
    }

    // Limits Settings
    public static function render_comments_per_page_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['limits']['comments_per_page']) ? absint($settings['limits']['comments_per_page']) : 10;
        echo '<input type="number" name="mhm_rentiva_comments_settings[limits][comments_per_page]" value="' . esc_attr($value) . '" min="1" max="100" class="small-text" />';
        echo '<p class="description">' . esc_html__('Number of comments to display per page.', 'mhm-rentiva') . '</p>';
    }

    public static function render_comment_length_min_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['limits']['comment_length_min']) ? absint($settings['limits']['comment_length_min']) : 5;
        echo '<input type="number" name="mhm_rentiva_comments_settings[limits][comment_length_min]" value="' . esc_attr($value) . '" min="1" max="1000" class="small-text" />';
        echo '<p class="description">' . esc_html__('Minimum number of characters required for comments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_comment_length_max_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['limits']['comment_length_max']) ? absint($settings['limits']['comment_length_max']) : 1000;
        echo '<input type="number" name="mhm_rentiva_comments_settings[limits][comment_length_max]" value="' . esc_attr($value) . '" min="10" max="5000" class="small-text" />';
        echo '<p class="description">' . esc_html__('Maximum number of characters allowed for comments.', 'mhm-rentiva') . '</p>';
    }

    // Spam Protection Settings
    public static function render_spam_protection_enabled_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['spam_protection']['enabled']) ? (bool) $settings['spam_protection']['enabled'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[spam_protection][enabled]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Enable spam protection for comments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_spam_words_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $spam_words = isset($settings['spam_protection']['spam_words']) && is_array($settings['spam_protection']['spam_words']) 
            ? $settings['spam_protection']['spam_words'] 
            : ['spam', 'viagra', 'casino', 'loan', 'free money', 'click here'];
        $value = implode(', ', array_map('sanitize_text_field', $spam_words));
        echo '<textarea name="mhm_rentiva_comments_settings[spam_protection][spam_words]" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . esc_html__('Comma-separated list of spam words to filter out.', 'mhm-rentiva') . '</p>';
    }

    public static function render_rate_limiting_enabled_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['spam_protection']['rate_limiting']['enabled']) ? (bool) $settings['spam_protection']['rate_limiting']['enabled'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[spam_protection][rate_limiting][enabled]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Enable rate limiting to prevent spam.', 'mhm-rentiva') . '</p>';
    }

    public static function render_rate_limiting_time_window_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['spam_protection']['rate_limiting']['time_window']) ? absint($settings['spam_protection']['rate_limiting']['time_window']) : 1;
        echo '<input type="number" name="mhm_rentiva_comments_settings[spam_protection][rate_limiting][time_window]" value="' . esc_attr($value) . '" min="1" max="60" class="small-text" />';
        echo '<p class="description">' . esc_html__('Time window for rate limiting in minutes.', 'mhm-rentiva') . '</p>';
    }

    public static function render_rate_limiting_max_attempts_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['spam_protection']['rate_limiting']['max_attempts']) ? absint($settings['spam_protection']['rate_limiting']['max_attempts']) : 1;
        echo '<input type="number" name="mhm_rentiva_comments_settings[spam_protection][rate_limiting][max_attempts]" value="' . esc_attr($value) . '" min="1" max="10" class="small-text" />';
        echo '<p class="description">' . esc_html__('Maximum number of comment attempts per time window.', 'mhm-rentiva') . '</p>';
    }

    // Display Settings
    public static function render_show_ratings_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['display']['show_ratings']) ? (bool) $settings['display']['show_ratings'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[display][show_ratings]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Show star ratings with comments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_show_avatars_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['display']['show_avatars']) ? (bool) $settings['display']['show_avatars'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[display][show_avatars]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Show user avatars with comments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_allow_editing_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['display']['allow_editing']) ? (bool) $settings['display']['allow_editing'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[display][allow_editing]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Allow users to edit their own comments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_allow_deletion_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['display']['allow_deletion']) ? (bool) $settings['display']['allow_deletion'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[display][allow_deletion]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Allow users to delete their own comments.', 'mhm-rentiva') . '</p>';
    }

    public static function render_edit_time_limit_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['display']['edit_time_limit']) ? absint($settings['display']['edit_time_limit']) : 24;
        echo '<input type="number" name="mhm_rentiva_comments_settings[display][edit_time_limit]" value="' . esc_attr($value) . '" min="1" max="168" class="small-text" />';
        echo '<p class="description">' . esc_html__('Time limit in hours for editing comments (0 = no limit).', 'mhm-rentiva') . '</p>';
    }

    // Notifications Settings
    public static function render_admin_notification_new_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['notifications']['admin_new_comment']) ? (bool) $settings['notifications']['admin_new_comment'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[notifications][admin_new_comment]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Send email notification to admin when new comment is posted.', 'mhm-rentiva') . '</p>';
    }

    public static function render_user_notification_approved_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['notifications']['user_comment_approved']) ? (bool) $settings['notifications']['user_comment_approved'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[notifications][user_comment_approved]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Send email notification to user when comment is approved.', 'mhm-rentiva') . '</p>';
    }

    // Cache Settings
    public static function render_cache_enabled_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['cache']['enabled']) ? (bool) $settings['cache']['enabled'] : true;
        echo '<input type="checkbox" name="mhm_rentiva_comments_settings[cache][enabled]" value="1" ' . checked($value, true, false) . ' />';
        echo '<p class="description">' . esc_html__('Enable caching for comments to improve performance.', 'mhm-rentiva') . '</p>';
    }

    public static function render_cache_duration_field(): void
    {
        $settings = CommentsSettings::get_settings();
        $value = isset($settings['cache']['duration']) ? absint($settings['cache']['duration']) : 15;
        echo '<input type="number" name="mhm_rentiva_comments_settings[cache][duration]" value="' . esc_attr($value) . '" min="1" max="1440" class="small-text" />';
        echo '<p class="description">' . esc_html__('Cache duration in minutes.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Sanitize comments settings
     */
    public static function sanitize_comments_settings($input): array
    {
        if (!is_array($input)) {
            return CommentsSettings::get_default_settings();
        }

        $sanitized = [];
        
        // Approval settings
        $sanitized['approval'] = [
            'auto_approve' => isset($input['approval']['auto_approve']) ? (bool) $input['approval']['auto_approve'] : false,
            'require_login' => isset($input['approval']['require_login']) ? (bool) $input['approval']['require_login'] : false, // Unchecked = false
            'allow_guest_comments' => isset($input['approval']['allow_guest_comments']) ? (bool) $input['approval']['allow_guest_comments'] : false,
            'moderation_required' => isset($input['approval']['moderation_required']) ? (bool) $input['approval']['moderation_required'] : false, // Unchecked = false
            'admin_notification' => isset($input['approval']['admin_notification']) ? (bool) $input['approval']['admin_notification'] : false // Unchecked = false
        ];

        // Limits settings
        $sanitized['limits'] = [
            'comments_per_page' => isset($input['limits']['comments_per_page']) ? max(1, min(100, (int) $input['limits']['comments_per_page'])) : 10,
            'max_comments_per_user' => isset($input['limits']['max_comments_per_user']) ? max(0, (int) $input['limits']['max_comments_per_user']) : 0,
            'max_comments_per_vehicle' => isset($input['limits']['max_comments_per_vehicle']) ? max(0, (int) $input['limits']['max_comments_per_vehicle']) : 0,
            'comment_length_min' => isset($input['limits']['comment_length_min']) ? max(1, min(1000, (int) $input['limits']['comment_length_min'])) : 5,
            'comment_length_max' => isset($input['limits']['comment_length_max']) ? max(10, min(5000, (int) $input['limits']['comment_length_max'])) : 1000,
            'rating_required' => isset($input['limits']['rating_required']) ? (bool) $input['limits']['rating_required'] : false // Unchecked = false
        ];

        // Spam protection settings
        $sanitized['spam_protection'] = [
            'enabled' => isset($input['spam_protection']['enabled']) ? (bool) $input['spam_protection']['enabled'] : false, // Unchecked = false
            'rate_limiting' => [
                'enabled' => isset($input['spam_protection']['rate_limiting']['enabled']) ? (bool) $input['spam_protection']['rate_limiting']['enabled'] : false, // Unchecked = false
                'time_window' => isset($input['spam_protection']['rate_limiting']['time_window']) ? max(1, min(60, (int) $input['spam_protection']['rate_limiting']['time_window'])) : 1,
                'max_attempts' => isset($input['spam_protection']['rate_limiting']['max_attempts']) ? max(1, min(10, (int) $input['spam_protection']['rate_limiting']['max_attempts'])) : 1,
                'cooldown_period' => isset($input['spam_protection']['rate_limiting']['cooldown_period']) ? max(1, min(60, (int) $input['spam_protection']['rate_limiting']['cooldown_period'])) : 10
            ],
            'duplicate_detection' => [
                'enabled' => isset($input['spam_protection']['duplicate_detection']['enabled']) ? (bool) $input['spam_protection']['duplicate_detection']['enabled'] : false, // Unchecked = false
                'time_window' => isset($input['spam_protection']['duplicate_detection']['time_window']) ? max(1, min(60, (int) $input['spam_protection']['duplicate_detection']['time_window'])) : 1,
                'max_duplicates' => isset($input['spam_protection']['duplicate_detection']['max_duplicates']) ? max(1, min(10, (int) $input['spam_protection']['duplicate_detection']['max_duplicates'])) : 1,
                'check_content' => isset($input['spam_protection']['duplicate_detection']['check_content']) ? (bool) $input['spam_protection']['duplicate_detection']['check_content'] : false // Unchecked = false
            ],
            'spam_words' => isset($input['spam_protection']['spam_words']) ? 
                (is_array($input['spam_protection']['spam_words']) ? 
                    array_map('sanitize_text_field', $input['spam_protection']['spam_words']) : 
                    array_map('sanitize_text_field', explode(',', (string) $input['spam_protection']['spam_words']))) : 
                ['spam', 'viagra', 'casino', 'loan', 'free money', 'click here'],
            'ip_blocking' => [
                'enabled' => isset($input['spam_protection']['ip_blocking']['enabled']) ? (bool) $input['spam_protection']['ip_blocking']['enabled'] : false,
                'block_duration' => isset($input['spam_protection']['ip_blocking']['block_duration']) ? max(1, min(168, (int) $input['spam_protection']['ip_blocking']['block_duration'])) : 24,
                'max_violations' => isset($input['spam_protection']['ip_blocking']['max_violations']) ? max(1, min(20, (int) $input['spam_protection']['ip_blocking']['max_violations'])) : 5
            ]
        ];

        // Display settings
        $sanitized['display'] = [
            'show_ratings' => isset($input['display']['show_ratings']) ? (bool) $input['display']['show_ratings'] : false, // Unchecked = false
            'show_avatars' => isset($input['display']['show_avatars']) ? (bool) $input['display']['show_avatars'] : false, // Unchecked = false
            'show_dates' => isset($input['display']['show_dates']) ? (bool) $input['display']['show_dates'] : false, // Unchecked = false
            'show_edit_buttons' => isset($input['display']['show_edit_buttons']) ? (bool) $input['display']['show_edit_buttons'] : false, // Unchecked = false
            'show_delete_buttons' => isset($input['display']['show_delete_buttons']) ? (bool) $input['display']['show_delete_buttons'] : false, // Unchecked = false
            'allow_editing' => isset($input['display']['allow_editing']) ? (bool) $input['display']['allow_editing'] : false, // Unchecked = false
            'allow_deletion' => isset($input['display']['allow_deletion']) ? (bool) $input['display']['allow_deletion'] : false, // Unchecked = false
            'edit_time_limit' => isset($input['display']['edit_time_limit']) ? max(0, min(168, (int) $input['display']['edit_time_limit'])) : 24,
            'sort_order' => isset($input['display']['sort_order']) ? self::sanitize_text_field_safe($input['display']['sort_order']) : 'newest',
            'pagination' => isset($input['display']['pagination']) ? (bool) $input['display']['pagination'] : false // Unchecked = false
        ];

        // Notifications settings
        $sanitized['notifications'] = [
            'admin_new_comment' => isset($input['notifications']['admin_new_comment']) ? (bool) $input['notifications']['admin_new_comment'] : false, // Unchecked = false
            'admin_comment_edited' => isset($input['notifications']['admin_comment_edited']) ? (bool) $input['notifications']['admin_comment_edited'] : false, // Unchecked = false
            'admin_comment_deleted' => isset($input['notifications']['admin_comment_deleted']) ? (bool) $input['notifications']['admin_comment_deleted'] : false, // Unchecked = false
            'user_comment_approved' => isset($input['notifications']['user_comment_approved']) ? (bool) $input['notifications']['user_comment_approved'] : false, // Unchecked = false
            'user_comment_rejected' => isset($input['notifications']['user_comment_rejected']) ? (bool) $input['notifications']['user_comment_rejected'] : false, // Unchecked = false
            'email_template' => isset($input['notifications']['email_template']) ? self::sanitize_text_field_safe($input['notifications']['email_template']) : 'default'
        ];

        // Cache settings
        $sanitized['cache'] = [
            'enabled' => isset($input['cache']['enabled']) ? (bool) $input['cache']['enabled'] : false, // Unchecked = false
            'duration' => isset($input['cache']['duration']) ? max(1, min(1440, (int) $input['cache']['duration'])) : 15,
            'clear_on_comment' => isset($input['cache']['clear_on_comment']) ? (bool) $input['cache']['clear_on_comment'] : false, // Unchecked = false
            'clear_on_edit' => isset($input['cache']['clear_on_edit']) ? (bool) $input['cache']['clear_on_edit'] : false, // Unchecked = false
            'clear_on_delete' => isset($input['cache']['clear_on_delete']) ? (bool) $input['cache']['clear_on_delete'] : false // Unchecked = false
        ];

        // Performance settings
        $sanitized['performance'] = [
            'lazy_loading' => isset($input['performance']['lazy_loading']) ? (bool) $input['performance']['lazy_loading'] : false, // Unchecked = false
            'ajax_loading' => isset($input['performance']['ajax_loading']) ? (bool) $input['performance']['ajax_loading'] : false, // Unchecked = false
            'infinite_scroll' => isset($input['performance']['infinite_scroll']) ? (bool) $input['performance']['infinite_scroll'] : false,
            'batch_size' => isset($input['performance']['batch_size']) ? max(1, min(100, (int) $input['performance']['batch_size'])) : 10,
            'background_processing' => isset($input['performance']['background_processing']) ? (bool) $input['performance']['background_processing'] : false
        ];

        return $sanitized;
    }
}
