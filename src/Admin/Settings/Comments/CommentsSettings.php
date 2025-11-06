<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Comments;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ COMMENTS SETTINGS - Dynamic Comment Settings
 * 
 * Moves hardcoded values to central settings
 */
final class CommentsSettings
{
    const OPTION_NAME = 'mhm_rentiva_comments_settings';
    
    /**
     * Default settings
     */
    public static function get_default_settings(): array
    {
        return [
            // Comment approval settings
            'approval' => [
                'auto_approve' => false,                    // Auto approve
                'require_login' => true,                    // Login required
                'allow_guest_comments' => false,            // Guest comments
                'moderation_required' => true,              // Moderator approval required
                'admin_notification' => true                // Admin notification
            ],
            
            // Comment limits
            'limits' => [
                'comments_per_page' => 10,                  // Comments per page
                'max_comments_per_user' => 0,               // Max comments per user (0 = unlimited)
                'max_comments_per_vehicle' => 0,            // Max comments per vehicle (0 = unlimited)
                'comment_length_min' => 5,                  // Minimum comment length
                'comment_length_max' => 1000,               // Maximum comment length
                'rating_required' => true                    // Rating required
            ],
            
            // Spam protection
            'spam_protection' => [
                'enabled' => true,                          // Spam protection active
                'rate_limiting' => [
                    'enabled' => true,                      // Rate limiting
                    'time_window' => 1,                     // Time window (minutes)
                    'max_attempts' => 1,                    // Maximum attempts
                    'cooldown_period' => 10                  // Cooldown period (minutes)
                ],
                'duplicate_detection' => [
                    'enabled' => true,                      // Duplicate detection
                    'time_window' => 1,                     // Time window (minutes)
                    'max_duplicates' => 1,                  // Maximum duplicates
                    'check_content' => true                 // Content check
                ],
                'spam_words' => [                           // Spam words
                    'spam', 'viagra', 'casino', 'loan', 
                    'free money', 'click here', 'buy now',
                    'discount', 'offer', 'deal'
                ],
                'ip_blocking' => [
                    'enabled' => false,                     // IP blocking
                    'block_duration' => 24,                 // Block duration (hours)
                    'max_violations' => 5                   // Maximum violations
                ]
            ],
            
            // Comment display
            'display' => [
                'show_ratings' => true,                     // Show ratings
                'show_avatars' => true,                    // Show avatars
                'show_dates' => true,                      // Show dates
                'show_edit_buttons' => true,               // Show edit buttons
                'show_delete_buttons' => true,             // Show delete buttons
                'allow_editing' => true,                    // Allow editing
                'allow_deletion' => true,                  // Allow deletion
                'edit_time_limit' => 24,                    // Edit time limit (hours)
                'sort_order' => 'newest',                   // Sort order (newest, oldest, highest_rated)
                'pagination' => true                       // Pagination
            ],
            
            // Email notifications
            'notifications' => [
                'admin_new_comment' => true,                // Admin new comment notification
                'admin_comment_edited' => true,            // Admin edited comment notification
                'admin_comment_deleted' => true,           // Admin deleted comment notification
                'user_comment_approved' => true,           // User approval notification
                'user_comment_rejected' => true,           // User rejection notification
                'email_template' => 'default'               // Email template
            ],
            
            // Cache settings
            'cache' => [
                'enabled' => true,                          // Cache active
                'duration' => 15,                           // Cache duration (minutes)
                'clear_on_comment' => true,                // Clear cache on comment
                'clear_on_edit' => true,                   // Clear cache on edit
                'clear_on_delete' => true                  // Clear cache on delete
            ],
            
            // Performance settings
            'performance' => [
                'lazy_loading' => true,                     // Lazy loading
                'ajax_loading' => true,                    // AJAX loading
                'infinite_scroll' => false,                 // Infinite scroll
                'batch_size' => 10,                        // Batch size
                'background_processing' => false           // Background processing
            ]
        ];
    }
    
    /**
     * Get settings
     */
    public static function get_settings(): array
    {
        // First try from main settings
        $main_settings = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_all();
        
        if (isset($main_settings['comments_approval']) || 
            isset($main_settings['comments_limits']) || 
            isset($main_settings['comments_spam_protection']) || 
            isset($main_settings['comments_display']) || 
            isset($main_settings['comments_cache'])) {
            
            // Get from main settings
            return [
                'approval' => $main_settings['comments_approval'] ?? self::get_default_settings()['approval'],
                'limits' => $main_settings['comments_limits'] ?? self::get_default_settings()['limits'],
                'spam_protection' => $main_settings['comments_spam_protection'] ?? self::get_default_settings()['spam_protection'],
                'display' => $main_settings['comments_display'] ?? self::get_default_settings()['display'],
                'cache' => $main_settings['comments_cache'] ?? self::get_default_settings()['cache']
            ];
        }
        
        // Fallback: get from old option
        $defaults = self::get_default_settings();
        $settings = get_option(self::OPTION_NAME, []);
        
        return array_merge($defaults, $settings);
    }
    
    /**
     * Get specific setting value
     */
    public static function get_setting(string $key, $default = null)
    {
        $settings = self::get_settings();
        $keys = explode('.', $key);
        
        foreach ($keys as $k) {
            if (!isset($settings[$k])) {
                return $default;
            }
            $settings = $settings[$k];
        }
        
        return $settings;
    }
    
    /**
     * Save settings
     */
    public static function save_settings(array $settings): bool
    {
        return update_option(self::OPTION_NAME, $settings);
    }
    
    /**
     * Reset settings
     */
    public static function reset_settings(): bool
    {
        return delete_option(self::OPTION_NAME);
    }
    
    /**
     * Spam protection check
     */
    public static function check_spam_protection(int $user_id, int $vehicle_id, string $comment): bool
    {
        // Input validation
        if ($user_id <= 0 || $vehicle_id <= 0 || empty(trim($comment))) {
            return false;
        }
        
        $spam_protection = self::get_setting('spam_protection');
        
        if (!$spam_protection['enabled']) {
            return true;
        }
        
        // Rate limiting check
        if ($spam_protection['rate_limiting']['enabled']) {
            if (!self::check_rate_limit($user_id, $vehicle_id, $spam_protection['rate_limiting'])) {
                return false;
            }
        }
        
        // Duplicate detection check
        if ($spam_protection['duplicate_detection']['enabled']) {
            if (!self::check_duplicate_detection($user_id, $vehicle_id, $comment, $spam_protection['duplicate_detection'])) {
                return false;
            }
        }
        
        // Spam words check
        if (!self::check_spam_words($comment, $spam_protection['spam_words'])) {
            return false;
        }
        
        // Comment length check
        $limits = self::get_setting('limits');
        if (strlen(trim($comment)) < $limits['comment_length_min']) {
            return false;
        }
        
        if (strlen($comment) > $limits['comment_length_max']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Rate limiting check
     */
    private static function check_rate_limit(int $user_id, int $vehicle_id, array $rate_limiting): bool
    {
        // Validate rate limiting settings
        if (!isset($rate_limiting['time_window']) || !isset($rate_limiting['max_attempts'])) {
            return true; // If settings are invalid, allow the comment
        }
        
        $time_window = absint($rate_limiting['time_window']) * 60; // Convert minutes to seconds
        $max_attempts = absint($rate_limiting['max_attempts']);
        
        if ($time_window <= 0 || $max_attempts <= 0) {
            return true; // If settings are invalid, allow the comment
        }
        
        $recent_comments = get_comments([
            'user_id' => $user_id,
            'post_id' => $vehicle_id,
            'date_query' => [
                'after' => $time_window . ' seconds ago'
            ],
            'number' => $max_attempts + 1
        ]);
        
        return count($recent_comments) <= $max_attempts;
    }
    
    /**
     * Duplicate detection check
     */
    private static function check_duplicate_detection(int $user_id, int $vehicle_id, string $comment, array $duplicate_detection): bool
    {
        if (!$duplicate_detection['check_content']) {
            return true;
        }
        
        // Validate duplicate detection settings
        if (!isset($duplicate_detection['time_window']) || !isset($duplicate_detection['max_duplicates'])) {
            return true; // If settings are invalid, allow the comment
        }
        
        $time_window = absint($duplicate_detection['time_window']) * 60; // Convert minutes to seconds
        $max_duplicates = absint($duplicate_detection['max_duplicates']);
        
        if ($time_window <= 0 || $max_duplicates < 0) {
            return true; // If settings are invalid, allow the comment
        }
        
        $duplicate_comments = get_comments([
            'user_id' => $user_id,
            'post_id' => $vehicle_id,
            'comment_content' => $comment,
            'date_query' => [
                'after' => $time_window . ' seconds ago'
            ],
            'number' => $max_duplicates + 1
        ]);
        
        return count($duplicate_comments) <= $max_duplicates;
    }
    
    /**
     * Spam words check
     */
    private static function check_spam_words(string $comment, array $spam_words): bool
    {
        // Validate input
        if (empty($comment) || !is_array($spam_words)) {
            return true;
        }
        
        $comment_lower = strtolower($comment);
        
        foreach ($spam_words as $spam_word) {
            if (!is_string($spam_word) || empty(trim($spam_word))) {
                continue; // Skip invalid spam words
            }
            
            if (strpos($comment_lower, strtolower(trim($spam_word))) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Comment approval status
     */
    public static function get_comment_approval_status(): int
    {
        $approval = self::get_setting('approval');
        return $approval['auto_approve'] ? 1 : 0;
    }
    
    /**
     * Comments per page
     */
    public static function get_comments_per_page(): int
    {
        return self::get_setting('limits.comments_per_page', 10);
    }
    
    /**
     * Cache duration
     */
    public static function get_cache_duration(): int
    {
        $cache = self::get_setting('cache');
        return $cache['enabled'] ? $cache['duration'] : 0;
    }
}
