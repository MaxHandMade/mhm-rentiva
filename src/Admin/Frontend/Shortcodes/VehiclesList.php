<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicles List Shortcode
 * 
 * Displays all vehicles in a list format
 * 
 * Usage: 
 * - [rentiva_vehicles_list]
 * - [rentiva_vehicles_list limit="6" layout="grid"]
 * - [rentiva_vehicles_list category="sedan" orderby="price"]
 * - [rentiva_vehicles_list featured="1" show_price="1"]
 * 
 * @since 3.0.1
 */
final class VehiclesList extends AbstractShortcode
{
    /**
     * Default placeholder image (Base64 SVG)
     */
    private const DEFAULT_PLACEHOLDER_IMAGE = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5WZWhpY2xlIEltYWdlPC90ZXh0Pjwvc3ZnPg==';

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

    /**
     * Safe excerpt getter that handles null values
     */
    public static function get_safe_excerpt(int $post_id): string
    {
        $excerpt = get_the_excerpt($post_id);
        if ($excerpt === null || $excerpt === false) {
            return '';
        }
        return $excerpt;
    }

    /**
     * Returns shortcode tag
     */
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vehicles_list';
    }

    /**
     * Returns template file path
     */
    protected static function get_template_path(): string
    {
        return 'shortcodes/vehicles-list';
    }

    /**
     * Returns default attributes for template
     */
    protected static function get_default_attributes(): array
    {
        return [
            'limit' => '12',
            'columns' => '1', // 1, 2, 3, 4 - For list layout
            'orderby' => 'title', // title, date, price, featured
            'order' => 'ASC', // ASC, DESC
            'category' => '', // Vehicle category
            'featured' => '0', // 0: all, 1: featured only
            'show_image' => '1',
            'show_title' => '1',
            'show_price' => '1',
            'show_features' => '1',
            'show_rating' => '1',
            'show_booking_btn' => '1',
            'show_favorite_btn' => '1',
            'show_category' => '1',
            'show_badges' => '1',
            'show_description' => '0',
            'show_availability' => '0',
            'show_compare_btn' => '0',
            'enable_lazy_load' => '1',
            'enable_ajax_filtering' => '0',
            'enable_infinite_scroll' => '0',
            'image_size' => 'medium',
            'ids' => '', // Comma separated vehicle IDs
            'max_features' => '5',
            'price_format' => 'daily',
            'class' => '',
            'custom_css_class' => '',
        ];
    }

    /**
     * Shortcode register
     */
    public static function register(): void
    {
        parent::register();

        // AJAX handlers
        add_action('wp_ajax_mhm_rentiva_toggle_favorite', [self::class, 'ajax_toggle_favorite']);
        add_action('wp_ajax_nopriv_mhm_rentiva_toggle_favorite', [self::class, 'ajax_toggle_favorite']);
        // Rating functions moved to VehicleRatingForm
        // add_action('wp_ajax_mhm_rentiva_submit_rating', [self::class, 'ajax_submit_rating']);
        // add_action('wp_ajax_nopriv_mhm_rentiva_submit_rating', [self::class, 'ajax_submit_rating']);
    }

    /**
     * Override asset handle
     */
    protected static function get_asset_handle(): string
    {
        return 'mhm-rentiva-vehicles-list';
    }

    /**
     * Override CSS filename
     */
    protected static function get_css_filename(): string
    {
        return 'vehicles-list.css';
    }

    /**
     * Override JS filename
     */
    protected static function get_js_filename(): string
    {
        return 'vehicles-list.js';
    }

    /**
     * Override script object name
     */
    protected static function get_script_object_name(): string
    {
        return 'mhmRentivaVehiclesList';
    }

    /**
     * Override localized data
     */
    protected static function get_localized_data(): array
    {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_vehicles_list'),
            'bookingUrl' => self::get_booking_url(),
            'loginUrl' => self::get_login_url(),
            'text' => self::get_text(),
            'strings' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'no_vehicles' => __('No vehicles found', 'mhm-rentiva'),
                'error' => __('An error occurred', 'mhm-rentiva'),
                'book_now' => __('Book Now', 'mhm-rentiva'),
                'view_details' => __('View Details', 'mhm-rentiva'),
                'added_to_favorites' => __('Added to favorites', 'mhm-rentiva'),
                'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
                'login_required' => __('You must be logged in to add to favorites', 'mhm-rentiva'),
                'invalid_vehicle_id' => __('Invalid vehicle ID', 'mhm-rentiva'),
                'error_occurred' => __('An error occurred', 'mhm-rentiva'),
                'connection_error' => __('Connection error', 'mhm-rentiva'),
                'booking_url_not_configured' => __('Booking URL is not configured', 'mhm-rentiva'),
                'add_to_favorites' => __('Add to favorites', 'mhm-rentiva'),
                'per_day' => __('/day', 'mhm-rentiva'),
                'no_vehicles_found_yet' => __('No vehicles found yet.', 'mhm-rentiva'),
            ],
        ];
    }

    /**
     * Prepares template data
     */
    protected static function prepare_template_data(array $atts): array
    {
        $vehicles = self::get_vehicles($atts);

        // For list layout, columns should always be 1
        $atts['columns'] = '1';

        // Inject custom texts from settings if not already set via shortcode attribute
        $text_settings = self::get_text();
        $atts['booking_btn_text'] = $atts['booking_btn_text'] ?? $text_settings['book_now'];
        $atts['view_details_btn_text'] = $atts['view_details_btn_text'] ?? $text_settings['view_details'];

        return [
            'atts' => $atts,
            'vehicles' => $vehicles,
            'total_vehicles' => count($vehicles),
            'has_vehicles' => !empty($vehicles),
            'layout_class' => 'rv-vehicles-list', // Only list layout
            'columns_class' => 'rv-vehicles-list--columns-1', // Always single column
            'wrapper_class' => self::get_wrapper_class($atts),
            'booking_url' => self::get_booking_url(), // Pass booking URL to template
        ];
    }

    /**
     * Gets vehicles
     */
    private static function get_vehicles(array $atts): array
    {
        $args = [
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
        ];

        // Category filter
        if (!empty($atts['category'])) {
            $categories = explode(',', $atts['category']);
            if (count($categories) > 1) {
                $cat_query = ['relation' => 'OR'];
                foreach ($categories as $cat) {
                    $cat_query[] = [
                        'key' => '_mhm_rentiva_category',
                        'value' => trim($cat),
                        'compare' => 'LIKE'
                    ];
                }
                $args['meta_query'][] = $cat_query;
            } else {
                $args['meta_query'][] = [
                    'key' => '_mhm_rentiva_category',
                    'value' => self::sanitize_text_field_safe($atts['category']),
                    'compare' => 'LIKE'
                ];
            }
        }

        // Featured vehicles filter
        if ($atts['featured'] === '1') {
            $args['meta_query'][] = [
                'key' => '_mhm_rentiva_featured',
                'value' => '1',
                'compare' => '='
            ];
        }

        // Special meta_query for price sorting
        if ($atts['orderby'] === 'price') {
            $args['meta_key'] = '_mhm_rentiva_price_per_day';
            $args['orderby'] = 'meta_value_num';
        }

        // Filter by specific IDs
        if (!empty($atts['ids'])) {
            $ids = array_map('intval', explode(',', $atts['ids']));
            $args['post__in'] = $ids;
            $args['orderby'] = 'post__in'; // Preserve order of IDs
        }

        $posts = get_posts($args);
        $vehicles = [];

        foreach ($posts as $post) {
            $vehicle_data = self::get_vehicle_data_for_shortcode($post->ID, $atts);
            if ($vehicle_data) {
                $vehicles[] = $vehicle_data;
            }
        }

        return $vehicles;
    }

    /**
     * Gets single vehicle data (for shortcode)
     */
    private static function get_vehicle_data_for_shortcode(int $vehicle_id, array $atts): ?array
    {
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            return null;
        }

        return [
            'id' => $vehicle_id,
            'title' => get_the_title($vehicle_id) ?: '',
            'excerpt' => self::get_safe_excerpt($vehicle_id),
            'permalink' => get_permalink($vehicle_id) ?: '',
            'image_url' => self::get_vehicle_image($vehicle_id, $atts['image_size']),
            'price' => self::get_vehicle_price($vehicle_id),
            'features' => self::get_limited_features($vehicle_id, intval($atts['max_features'])),
            'category' => self::get_vehicle_category($vehicle_id),
            'rating' => self::get_vehicle_rating($vehicle_id),
            'availability' => self::check_vehicle_availability($vehicle_id),
            'badge' => self::get_vehicle_badge($vehicle_id),
            'is_featured' => get_post_meta($vehicle_id, '_mhm_rentiva_featured', true) === '1',
            'price_format' => $atts['price_format'],
        ];
    }

    /**
     * Gets vehicle image
     */
    public static function get_vehicle_image(int $vehicle_id, ?string $size = null): string
    {
        $image_id = get_post_thumbnail_id($vehicle_id);
        if (!$image_id) {
            return self::get_placeholder_image_url();
        }

        $size = $size ?: 'medium';
        $image_url = wp_get_attachment_image_url($image_id, $size);
        return $image_url ?: self::get_placeholder_image_url();
    }

    /**
     * Get placeholder image URL with fallback
     * Checks for placeholder files and falls back to WordPress default or data URI
     */
    private static function get_placeholder_image_url(): string
    {
        // Try different placeholder file extensions
        $possible_files = [
            'placeholder-vehicle.jpg',
            'placeholder-vehicle.png',
            'placeholder-vehicle.svg',
            'no-image.jpg',
            'no-image.png'
        ];

        foreach ($possible_files as $filename) {
            $file_path = MHM_RENTIVA_PLUGIN_DIR . 'assets/images/' . $filename;
            if (file_exists($file_path)) {
                return MHM_RENTIVA_PLUGIN_URL . 'assets/images/' . $filename;
            }
        }

        // Fallback: Use data URI (1x1 transparent pixel with text)
        return self::DEFAULT_PLACEHOLDER_IMAGE;
    }

    /**
     * Gets vehicle price
     */
    public static function get_vehicle_price(int $vehicle_id): array
    {
        // Check price meta keys in order using Helper
        $daily_price = VehicleDataHelper::get_price_per_day($vehicle_id);

        $currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
        $currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

        // Use default value if price is 0
        if (empty($daily_price) || floatval($daily_price) == 0) {
            $daily_price = 1000; // Default price
        }

        return [
            'daily' => floatval($daily_price),
            'currency' => $currency,
            'symbol' => $currency_symbol,
            'formatted' => self::format_price_with_position(floatval($daily_price)),
        ];
    }

    /**
     * Format price with currency position
     */
    private static function format_price_with_position(float $price): string
    {
        $symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
        $position = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
        $formatted_amount = number_format($price, 0, ',', '.');

        switch ($position) {
            case 'left':
                return $symbol . $formatted_amount;
            case 'left_space':
                return $symbol . ' ' . $formatted_amount;
            case 'right':
                return $formatted_amount . $symbol;
            case 'right_space':
            default:
                return $formatted_amount . ' ' . $symbol;
        }
    }

    /**
     * Gets vehicle features
     */
    public static function get_vehicle_features(int $vehicle_id): array
    {
        return VehicleFeatureHelper::collect_items($vehicle_id);
    }

    /**
     * Gets limited vehicle features
     */
    public static function get_limited_features(int $vehicle_id, int $limit = 5): array
    {
        $features = self::get_vehicle_features($vehicle_id);
        if ($limit > 0 && count($features) > $limit) {
            return array_slice($features, 0, $limit);
        }
        return $features;
    }

    /**
     * Gets vehicle category
     */
    public static function get_vehicle_category(int $vehicle_id): string
    {
        return get_post_meta($vehicle_id, '_mhm_rentiva_category', true) ?: '';
    }

    /**
     * Gets all data for vehicle (for favorites page)
     */
    public static function get_vehicle_data(int $vehicle_id): ?array
    {
        $vehicle_post = get_post($vehicle_id);
        if (!$vehicle_post || $vehicle_post->post_type !== 'vehicle') {
            return null;
        }

        return [
            'id' => $vehicle_id,
            'title' => $vehicle_post->post_title,
            'image' => self::get_vehicle_image($vehicle_id),
            'price' => self::get_vehicle_price($vehicle_id),
            'features' => self::get_vehicle_features($vehicle_id),
            'rating' => self::get_vehicle_rating($vehicle_id),
            'availability' => self::check_vehicle_availability($vehicle_id),
            'badge' => self::get_vehicle_badge($vehicle_id),
            'category' => self::get_vehicle_category($vehicle_id)
        ];
    }

    /**
     * Get vehicle rating
     */
    public static function get_vehicle_rating(int $vehicle_id): array
    {
        if ($vehicle_id <= 0) {
            return ['average' => 0, 'count' => 0, 'stars' => '☆☆☆☆☆'];
        }

        // Calculate current rating from WordPress comments system
        $comments = get_comments([
            'post_id' => $vehicle_id,
            'status' => 'approve',
            'meta_query' => [
                [
                    'key' => 'mhm_rating',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $total_rating = 0;
        $count = 0;

        foreach ($comments as $comment) {
            $rating = intval(get_comment_meta($comment->comment_ID, 'mhm_rating', true));
            if ($rating > 0) {
                $total_rating += $rating;
                $count++;
            }
        }

        $average = $count > 0 ? round($total_rating / $count, 1) : 0;

        return [
            'average' => $average,
            'count' => $count,
            'stars' => self::get_star_rating($average),
        ];
    }

    /**
     * Gets star rating
     */
    private static function get_star_rating(float $rating): string
    {
        $stars = '';
        $full_stars = floor($rating);
        $has_half_star = ($rating - $full_stars) >= 0.5;

        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars .= '★';
            } elseif ($i == $full_stars + 1 && $has_half_star) {
                $stars .= '☆';
            } else {
                $stars .= '☆';
            }
        }

        return $stars;
    }

    /**
     * Updates vehicle rating (for Admin panel)
     */
    public static function update_vehicle_rating(int $vehicle_id, float $new_rating, ?int $new_count = null): bool
    {
        if ($vehicle_id <= 0 || $new_rating < 0 || $new_rating > 5) {
            return false;
        }

        // Get current rating data
        $current_average = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_rating_average', true));
        $current_count = intval(get_post_meta($vehicle_id, '_mhm_rentiva_rating_count', true));

        if ($new_count !== null) {
            // Use new count value if provided
            $updated_count = $new_count;
            $updated_average = $new_rating;
        } else {
            // If adding new rating, calculate average
            $updated_count = $current_count + 1;
            $updated_average = (($current_average * $current_count) + $new_rating) / $updated_count;
        }

        // Update meta data
        $result1 = update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', $updated_average);
        $result2 = update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $updated_count);

        return $result1 !== false && $result2 !== false;
    }

    // Table creation logic moved to DatabaseMigrator

    /**
     * Saves user rating
     */
    public static function save_user_rating(int $vehicle_id, float $rating, string $comment = '', ?int $user_id = null): bool
    {
        global $wpdb;

        if ($rating < 0 || $rating > 5) {
            return false;
        }

        $user_id = $user_id ?: get_current_user_id();
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $table_name = $wpdb->prefix . 'mhm_rentiva_ratings';

        // Check if user has rated before
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE vehicle_id = %d AND user_id = %d",
            $vehicle_id,
            $user_id
        ));

        if ($existing) {
            // Update
            $result = $wpdb->update(
                $table_name,
                [
                    'rating' => $rating,
                    'comment' => $comment,
                    'updated_at' => current_time('mysql')
                ],
                [
                    'vehicle_id' => $vehicle_id,
                    'user_id' => $user_id
                ]
            );
        } else {
            // New record
            $result = $wpdb->insert(
                $table_name,
                [
                    'vehicle_id' => $vehicle_id,
                    'user_id' => $user_id,
                    'user_ip' => $user_ip,
                    'rating' => $rating,
                    'comment' => $comment,
                    'status' => 'approved',
                    'created_at' => current_time('mysql')
                ]
            );
        }

        if ($result !== false) {
            // Calculate and update average rating
            self::update_vehicle_rating_from_database($vehicle_id);
            return true;
        }

        return false;
    }

    /**
     * Calculates and updates average rating from database
     */
    private static function update_vehicle_rating_from_database(int $vehicle_id): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_ratings';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as average, COUNT(*) as count FROM $table_name WHERE vehicle_id = %d AND status = 'approved'",
            $vehicle_id
        ));

        if ($stats) {
            $average = round(floatval($stats->average), 1);
            $count = intval($stats->count);

            update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', $average);
            update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $count);
        }
    }

    /**
     * Gets user rating for a vehicle
     */
    public static function get_user_rating(int $vehicle_id, ?int $user_id = null): ?array
    {
        global $wpdb;

        $user_id = $user_id ?: get_current_user_id();

        if (!$user_id) {
            return null;
        }

        $table_name = $wpdb->prefix . 'mhm_rentiva_ratings';

        $rating = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE vehicle_id = %d AND user_id = %d",
            $vehicle_id,
            $user_id
        ));

        return $rating ? (array) $rating : null;
    }

    /**
     * Gets all ratings for vehicle (for admin)
     */
    public static function get_vehicle_ratings(int $vehicle_id, int $limit = 10, int $offset = 0): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mhm_rentiva_ratings';

        $ratings = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email 
             FROM $table_name r 
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
             WHERE r.vehicle_id = %d 
             ORDER BY r.created_at DESC 
             LIMIT %d OFFSET %d",
            $vehicle_id,
            $limit,
            $offset
        ));

        return $ratings ? array_map(function ($rating) {
            return (array) $rating;
        }, $ratings) : [];
    }

    /**
     * Checks vehicle availability
     */
    /**
     * Checks vehicle availability
     */
    private static function check_vehicle_availability(int $vehicle_id): array
    {
        $status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);

        // Fallback for older data or if status is not set
        if (empty($status)) {
            $old_availability = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
            // Handle legacy values
            if ($old_availability === '0' || $old_availability === 'passive' || $old_availability === 'inactive') {
                $status = 'inactive';
            } elseif ($old_availability === '1' || $old_availability === 'active') {
                $status = 'active';
            } elseif ($old_availability === 'maintenance') {
                $status = 'maintenance';
            } else {
                $status = 'active'; // Default
            }
        }

        $is_available = ($status === 'active');

        return [
            'is_available' => $is_available,
            'status' => $status,
            'text' => $is_available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva')
        ];
    }

    /**
     * Gets vehicle badge
     */
    private static function get_vehicle_badge(int $vehicle_id): ?array
    {
        $is_featured = get_post_meta($vehicle_id, '_mhm_rentiva_featured', true) === '1';
        if ($is_featured) {
            return [
                'text' => __('Featured', 'mhm-rentiva'),
                'class' => 'featured'
            ];
        }

        return null;
    }

    /**
     * Creates wrapper class
     */
    private static function get_wrapper_class(array $atts): string
    {
        $classes = ['rv-vehicles-list'];

        if (!empty($atts['class'])) {
            $classes[] = sanitize_html_class($atts['class']);
        }

        return implode(' ', $classes);
    }

    /**
     * Gets booking URL
     */
    public static function get_booking_url(): string
    {
        // First check from settings
        $booking_url = SettingsCore::get('mhm_rentiva_booking_url', '');
        if (!empty($booking_url)) {
            return $booking_url;
        }

        // Check from ShortcodeUrlManager
        if (class_exists('\MHMRentiva\Admin\Core\ShortcodeUrlManager')) {
            $url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');
            if ($url) {
                return $url;
            }
        }

        // Fallback
        return ShortcodeUrlManager::get_page_url('rentiva_booking_form');
    }

    /**
     * Gets login URL
     */
    private static function get_login_url(): string
    {
        // First check from settings
        $login_url = SettingsCore::get('mhm_rentiva_login_url', '');
        if (!empty($login_url)) {
            return $login_url;
        }

        // Check from ShortcodeUrlManager
        if (class_exists('\MHMRentiva\Admin\Core\ShortcodeUrlManager')) {
            $url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_login');
            if ($url) {
                return $url;
            }
        }

        // Fallback
        return wp_login_url();
    }

    /**
     * Gets texts with fallback to i18n defaults
     */
    private static function get_text(): array
    {
        return [
            'book_now' => SettingsCore::get('mhm_rentiva_text_book_now', '') ?: __('Book Now', 'mhm-rentiva'),
            'view_details' => SettingsCore::get('mhm_rentiva_text_view_details', '') ?: __('View Details', 'mhm-rentiva'),
            'added_to_favorites' => SettingsCore::get('mhm_rentiva_text_added_to_favorites', '') ?: __('Added to favorites', 'mhm-rentiva'),
            'removed_from_favorites' => SettingsCore::get('mhm_rentiva_text_removed_from_favorites', '') ?: __('Removed from favorites', 'mhm-rentiva'),
            'login_required' => SettingsCore::get('mhm_rentiva_text_login_required', '') ?: __('You must be logged in to add to favorites', 'mhm-rentiva'),
        ];
    }

    /**
     * AJAX favorite add/remove
     */
    public static function ajax_toggle_favorite(): void
    {
        try {
            $nonce = sanitize_text_field($_POST['nonce'] ?? '');
            if (
                empty($nonce) ||
                (!wp_verify_nonce($nonce, 'mhm_rentiva_vehicles_list') && !wp_verify_nonce($nonce, 'mhm_rentiva_toggle_favorite'))
            ) {
                throw new \Exception(__('Security error', 'mhm-rentiva'));
            }

            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in', 'mhm-rentiva'));
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            if (!$vehicle_id) {
                throw new \Exception(__('Invalid vehicle ID', 'mhm-rentiva'));
            }

            $user_id = get_current_user_id();
            $favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true);
            if (!is_array($favorites)) {
                $favorites = array_filter(array_map('intval', (array) $favorites));
            }

            $key = array_search($vehicle_id, $favorites);
            if ($key !== false) {
                // Remove from favorites
                unset($favorites[$key]);
                $favorites = array_values($favorites);
                $message = __('Removed from favorites', 'mhm-rentiva');
                $action = 'removed';
            } else {
                // Add to favorites
                $favorites[] = $vehicle_id;
                $favorites = array_values(array_unique(array_map('intval', $favorites)));
                $message = __('Added to favorites', 'mhm-rentiva');
                $action = 'added';
            }

            update_user_meta($user_id, 'mhm_rentiva_favorites', $favorites);

            wp_send_json_success([
                'message' => $message,
                'action' => $action,
                'vehicle_id' => $vehicle_id,
                'favorites_count' => count($favorites)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX: Submit rating
     */
    public static function ajax_submit_rating(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_rating_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        $rating = floatval($_POST['rating'] ?? 0);
        $comment = sanitize_textarea_field((string) (($_POST['comment'] ?? '') ?: ''));

        if ($vehicle_id <= 0 || $rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => __('Invalid rating value.', 'mhm-rentiva')]);
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to rate.', 'mhm-rentiva')]);
            return;
        }

        $result = self::save_user_rating($vehicle_id, $rating, $comment);

        if ($result) {
            // Update vehicle meta (compatible with VehicleRatingForm)
            \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::update_vehicle_rating_meta($vehicle_id);

            // Get updated rating information
            $vehicle_rating = self::get_vehicle_rating($vehicle_id);
            $user_rating = self::get_user_rating($vehicle_id);

            wp_send_json_success([
                'message' => __('Rating saved successfully.', 'mhm-rentiva'),
                'vehicle_rating' => $vehicle_rating,
                'user_rating' => $user_rating
            ]);
        } else {
            wp_send_json_error(['message' => __('Rating could not be saved.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX: Get user rating
     */
    public static function ajax_get_user_rating(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'mhm-rentiva')]);
            return;
        }

        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);

        if ($vehicle_id <= 0) {
            wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            return;
        }

        $user_rating = self::get_user_rating($vehicle_id);

        wp_send_json_success([
            'user_rating' => $user_rating
        ]);
    }

    /**
     * AJAX: Get vehicle ratings
     */
    public static function ajax_get_vehicle_ratings(): void
    {
        $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);

        if ($vehicle_id <= 0) {
            wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            return;
        }

        $ratings = self::get_vehicle_ratings($vehicle_id, $limit, $offset);

        wp_send_json_success([
            'ratings' => $ratings
        ]);
    }

    /**
     * Registers AJAX handlers
     */
    protected static function register_ajax_handlers(): void
    {
        add_action('wp_ajax_mhm_rentiva_toggle_favorite', [self::class, 'ajax_toggle_favorite']);
        add_action('wp_ajax_nopriv_mhm_rentiva_toggle_favorite', [self::class, 'ajax_toggle_favorite']);

        // Rating AJAX handlers
        // Rating functions moved to VehicleRatingForm
        // add_action('wp_ajax_mhm_rentiva_submit_rating', [self::class, 'ajax_submit_rating']);
        // add_action('wp_ajax_nopriv_mhm_rentiva_submit_rating', [self::class, 'ajax_submit_rating']);
        add_action('wp_ajax_mhm_rentiva_get_user_rating', [self::class, 'ajax_get_user_rating']);
        add_action('wp_ajax_nopriv_mhm_rentiva_get_user_rating', [self::class, 'ajax_get_user_rating']);
        add_action('wp_ajax_mhm_rentiva_get_vehicle_ratings', [self::class, 'ajax_get_vehicle_ratings']);
        add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_ratings', [self::class, 'ajax_get_vehicle_ratings']);
    }

    /**
     * Checks user favorites
     */
    public static function is_favorite(int $vehicle_id, ?int $user_id = null): bool
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true) ?: [];
        return in_array($vehicle_id, $favorites);
    }

    /**
     * Registers hooks
     */
    protected static function register_hooks(): void
    {
        parent::register_hooks();

        // Clear cache on page changes
        add_action('wp_head', [self::class, 'clear_page_cache'], 1);
        add_action('template_redirect', [self::class, 'clear_page_cache'], 1);
    }

    /**
     * Clears shortcode cache (on page changes)
     */
    public static function clear_page_cache(): void
    {
        // Clear cache for current page
        $page_id = get_the_ID();
        if ($page_id) {
            global $wpdb;
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
            ", '_transient_shortcode_rentiva_vehicles_list_%'));
        }
    }

    /**
     * Disables cache (for development)
     */
    protected static function is_caching_enabled(): bool
    {
        // Turn off cache in development environment
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return false;
        }

        // Turn off cache for admin users
        if (is_admin() || current_user_can('administrator')) {
            return false;
        }

        // Temporarily turn off cache for testing
        return false;

        // return parent::is_caching_enabled();
    }
}
