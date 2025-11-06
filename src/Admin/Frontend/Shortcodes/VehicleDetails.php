<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use DateTime;
use DateInterval;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Details Shortcode
 * 
 * Full-featured shortcode for vehicle detail page
 * Includes gallery, features, pricing, booking button
 * 
 * Usage:
 * [rentiva_vehicle_details] - Current vehicle (single-vehicle.php)
 * [rentiva_vehicle_details vehicle_id="123"] - Specific vehicle
 */
final class VehicleDetails extends AbstractShortcode
{
    public const SHORTCODE = 'rentiva_vehicle_details';

    /**
     * Register shortcode
     */
    public static function register(): void
    {
        parent::register();
        
        add_action('wp_ajax_mhm_rentiva_get_calendar', [self::class, 'ajax_get_calendar']);
        add_action('wp_ajax_nopriv_mhm_rentiva_get_calendar', [self::class, 'ajax_get_calendar']);
    }

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vehicle_details';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/vehicle-details';
    }

    protected static function get_default_attributes(): array
    {
        return [
            'vehicle_id' => '',
            'show_gallery' => '1',
            'show_features' => '1',
            'show_pricing' => '1',
            'show_booking' => '1',
            'show_calendar' => '1',
            'show_price' => '1',
            'show_booking_button' => '1',
            'class' => '',
        ];
    }

    protected static function get_css_filename(): string
    {
        return 'vehicle-details.css';
    }

    protected static function get_js_filename(): string
    {
        return 'vehicle-details.js';
    }

    /**
     * Override asset handle (fix double rentiva issue)
     */
    protected static function get_asset_handle(): string
    {
        return 'mhm-rentiva-vehicle-details';
    }

    protected static function get_css_dependencies(): array
    {
        return [];
    }

    protected static function get_js_dependencies(): array
    {
        return ['jquery'];
    }

    /**
     * Return JavaScript files
     */
    protected static function get_js_files(): array
    {
        return [
            static::get_assets_path() . '/js/frontend/' . static::get_js_filename(),
        ];
    }

    /**
     * Load asset files
     */
    protected static function enqueue_assets(): void
    {
        // CSS
        wp_enqueue_style(
            'mhm-rentiva-vehicle-details',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vehicle-details.css',
            static::get_css_dependencies(),
            MHM_RENTIVA_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'mhm-rentiva-vehicle-details',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vehicle-details.js',
            static::get_js_dependencies(),
            MHM_RENTIVA_VERSION,
            true
        );

        // Localize script
        static::localize_script('mhm-rentiva-vehicle-details');
    }

    protected static function get_script_object_name(): string
    {
        return 'mhmRentivaVehicleDetails';
    }

    protected static function get_localized_strings(): array
    {
        return [
            'loading' => __('Loading...', 'mhm-rentiva'),
            'error_occurred' => __('An error occurred', 'mhm-rentiva'),
            'try_again' => __('Please try again', 'mhm-rentiva'),
            'book_now' => __('Book Now', 'mhm-rentiva'),
            'view_gallery' => __('View Gallery', 'mhm-rentiva'),
            'close_gallery' => __('Close Gallery', 'mhm-rentiva'),
            'next_image' => __('Next Image', 'mhm-rentiva'),
            'previous_image' => __('Previous Image', 'mhm-rentiva'),
        ];
    }

    protected static function get_js_config(): array
    {
        return [
            'currency_symbol' => self::get_currency_symbol(),
            'locale' => self::get_js_locale(),
        ];
    }

    /**
     * Get JavaScript locale data
     */
    private static function get_js_locale(): array
    {
        return [
            'loading' => __('Loading...', 'mhm-rentiva'),
            'error' => __('An error occurred', 'mhm-rentiva'),
            'success' => __('Success', 'mhm-rentiva'),
            'confirm' => __('Are you sure?', 'mhm-rentiva'),
            'cancel' => __('Cancel', 'mhm-rentiva'),
            'save' => __('Save', 'mhm-rentiva'),
            'close' => __('Close', 'mhm-rentiva'),
        ];
    }


    /**
     * Get vehicle ID - Optimized version
     */
    private static function get_vehicle_id(array $atts): int
    {
        // First from shortcode parameters
        if (!empty($atts['vehicle_id'])) {
            return absint($atts['vehicle_id']);
        }

        // Then from global post
        global $post;
        if ($post && $post->post_type === 'vehicle') {
            return $post->ID;
        }

        // Try get_the_ID()
        $current_id = get_the_ID();
        if ($current_id && get_post_type($current_id) === 'vehicle') {
            return $current_id;
        }

        // Try from URL parameters
        $url_vehicle_id = isset($_GET['vehicle_id']) ? absint($_GET['vehicle_id']) : 0;
        if ($url_vehicle_id > 0) {
            return $url_vehicle_id;
        }

        return 0;
    }

    /**
     * Prepare template data - Optimized cache version
     */
    protected static function prepare_template_data(array $atts): array
    {
        // Get vehicle ID
        $vehicle_id = self::get_vehicle_id($atts);
        
        if (!$vehicle_id) {
            return [];
        }
        
        // Cache check
        $cache_key = 'vehicle_details_' . $vehicle_id . '_' . md5(serialize($atts));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $vehicle = get_post($vehicle_id);
        
        if (!$vehicle || $vehicle->post_type !== 'vehicle') {
            // If vehicle not found, return safe default values
            return self::get_default_template_data($atts);
        }

        // Inject custom texts from settings if not already set via shortcode attribute
        $text_settings = self::get_text();
        $atts['booking_btn_text'] = $atts['booking_btn_text'] ?? $text_settings['book_now'];
        
        $template_data = [
            'vehicle_id' => $vehicle_id,
            'vehicle' => $vehicle,
            'atts' => $atts,
            
            // Basic Information
            'title' => $vehicle->post_title,
            'content' => $vehicle->post_content,
            'excerpt' => $vehicle->post_excerpt,
            
            // Images
            'featured_image' => self::get_featured_image($vehicle_id),
            'gallery' => self::get_gallery($vehicle_id),
            
            // Meta Information
            'brand' => get_post_meta($vehicle_id, '_mhm_rentiva_brand', true),
            'model' => get_post_meta($vehicle_id, '_mhm_rentiva_model', true),
            'year' => self::get_meta_with_fallback($vehicle_id, ['_mhm_rentiva_year', 'yıl', '_mhm_rentiva_yil', 'year']),
            'fuel_type' => self::get_meta_with_fallback($vehicle_id, ['_mhm_rentiva_fuel_type', 'yakıt_türü', '_mhm_rentiva_yakit_turu', 'fuel_type']),
            'transmission' => self::get_meta_with_fallback($vehicle_id, ['_mhm_rentiva_transmission', 'vites', '_mhm_rentiva_vites', 'transmission']),
            'seats' => self::get_meta_with_fallback($vehicle_id, ['_mhm_rentiva_seats', 'koltuk_sayısı', '_mhm_rentiva_koltuk_sayisi', 'seats']),
            'doors' => self::get_meta_with_fallback($vehicle_id, ['_mhm_rentiva_doors', 'kapı_sayısı', '_mhm_rentiva_kapi_sayisi', 'doors']),
            'mileage' => self::get_meta_with_fallback($vehicle_id, ['_mhm_rentiva_mileage', 'kilometre', '_mhm_rentiva_kilometre', 'mileage']),
            'color' => get_post_meta($vehicle_id, '_mhm_rentiva_color', true),
            'license_plate' => get_post_meta($vehicle_id, '_mhm_rentiva_license_plate', true),
            
            // Price
            'price_per_day' => get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true),
            'price_per_week' => get_post_meta($vehicle_id, '_mhm_rentiva_price_per_week', true),
            'price_per_month' => get_post_meta($vehicle_id, '_mhm_rentiva_price_per_month', true),
            'currency' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD'),
            'currency_symbol' => self::get_currency_symbol(),
            
            // Features
            'features' => self::get_features($vehicle_id),
            
            // Category
            'categories' => self::get_categories($vehicle_id),
            
            // Booking URL - Redirect to existing booking form
            'booking_url' => self::get_booking_url($vehicle_id),
            
            // Rating
            'rating' => self::get_vehicle_rating($vehicle_id),
        ];
        
        // Cache save (15 minutes) (cache duration)
        set_transient($cache_key, $template_data, 15 * MINUTE_IN_SECONDS);
        
        return $template_data;
    }

    /**
     * Default template data - when vehicle not found (fallback values)
     */
    private static function get_default_template_data(array $atts): array
    {
        return [
            'vehicle_id' => 0,
            'vehicle' => null,
            'atts' => $atts,
            
            // Basic Information
            'title' => __('Vehicle Not Found', 'mhm-rentiva'),
            'content' => __('The requested vehicle could not be found.', 'mhm-rentiva'),
            'excerpt' => '',
            
            // Images
            'featured_image' => [
                'url' => self::get_placeholder_image_url(),
                'alt' => __('Vehicle Image', 'mhm-rentiva'),
                'title' => ''
            ],
            'gallery' => [],
            
            // Meta Information
            'brand' => '',
            'model' => '',
            'year' => '',
            'fuel_type' => '',
            'transmission' => '',
            'seats' => '',
            'doors' => '',
            'mileage' => '',
            'color' => '',
            'license_plate' => '',
            
            // Price
            'price_per_day' => 0,
            'price_per_week' => 0,
            'price_per_month' => 0,
            'currency' => 'USD',
            'currency_symbol' => '$',
            
            // Features
            'features' => [],
            
            // Category
            'categories' => [],
            
            // Booking URL
            'booking_url' => ShortcodeUrlManager::get_page_url('rentiva_booking_form'),
            
            // Rating
            'rating' => [
                'average' => 0,
                'count' => 0,
                'total' => 0
            ],
        ];
    }

    /**
     * Featured image get (return array with image URL, alt text, and title)
     */
    private static function get_featured_image(int $vehicle_id): array
    {
        $image_id = get_post_thumbnail_id($vehicle_id);
        
        if (!$image_id) {
            return [
                'url' => self::get_placeholder_image_url(),
                'alt' => __('Vehicle Image', 'mhm-rentiva'),
                'title' => ''
            ];
        }

        return [
            'url' => wp_get_attachment_image_url($image_id, 'large'),
            'url_full' => wp_get_attachment_image_url($image_id, 'full'),
            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
            'title' => get_the_title($image_id) ?: '',
        ];
    }

    /**
     * Gallery get
     */
    private static function get_gallery(int $vehicle_id): array
    {
        // Try all possible meta keys
        $possible_keys = [
            '_mhm_rentiva_gallery_images',  // Real meta key
            '_mhm_rentiva_gallery',
            'gallery',
            '_gallery',
            '_vehicle_gallery',
            'vehicle_gallery',
            '_mhm_gallery',
            'mhm_gallery'
        ];
        
        $gallery_ids = [];
        
        foreach ($possible_keys as $key) {
            $meta_value = get_post_meta($vehicle_id, $key, true);
            
            if (!empty($meta_value)) {
                if (is_array($meta_value)) {
                    $gallery_ids = $meta_value;
                } elseif (is_string($meta_value) && $key === '_mhm_rentiva_gallery_images') {
                    // Parse JSON string
                    $gallery_data = json_decode($meta_value, true);
                    if (is_array($gallery_data)) {
                        $gallery_ids = array_column($gallery_data, 'id');
                    }
                }
                
                if (!empty($gallery_ids)) {
                    break;
                }
            }
        }
        
        if (empty($gallery_ids)) {
            // Use featured image as gallery
            $featured_image_id = get_post_thumbnail_id($vehicle_id);
            if ($featured_image_id) {
                $gallery_ids = [$featured_image_id];
            } else {
                return [];
            }
        }

        $gallery = [];
        foreach ($gallery_ids as $image_id) {
            if (is_numeric($image_id)) {
                $gallery[] = [
                    'id' => $image_id,
                    'url' => wp_get_attachment_image_url($image_id, 'medium'),
                    'url_large' => wp_get_attachment_image_url($image_id, 'large'),
                    'url_full' => wp_get_attachment_image_url($image_id, 'full'),
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    'title' => get_the_title($image_id) ?: '',
                ];
            }
        }

        return $gallery;
    }

    /**
     * Features get
     */
    private static function get_features(int $vehicle_id): array
    {
        // Meta field get (stored as array)
        $features = get_post_meta($vehicle_id, '_mhm_rentiva_features', true);
        
        if (empty($features) || !is_array($features)) {
            return [];
        }

        // Return feature names in array
        return $features;
    }


    /**
     * Categories get
     */
    private static function get_categories(int $vehicle_id): array
    {
        $terms = get_the_terms($vehicle_id, 'vehicle_category');
        
        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }

        $categories = [];
        foreach ($terms as $term) {
            $categories[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'url' => get_term_link($term),
            ];
        }

        return $categories;
    }

    /**
     * Get currency symbol
     * 
     * @deprecated Use CurrencyHelper::get_currency_symbol() instead
     */
    private static function get_currency_symbol(): string
    {
        return \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
    }

    /**
     * Vehicle rating get
     */
    private static function get_vehicle_rating(int $vehicle_id): array
    {
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
            'total' => $count
        ];
    }

    /**
     * Meta data get with fallback keys
     */
    private static function get_meta_with_fallback(int $vehicle_id, array $meta_keys): string
    {
        foreach ($meta_keys as $key) {
            $value = get_post_meta($vehicle_id, $key, true);
            if (!empty($value)) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Monthly availability calendar render
     */
    public static function render_monthly_calendar(?int $vehicle_id = null, ?int $month = null, ?int $year = null): string
    {
        // If vehicle ID is not set, return empty calendar
        if (!$vehicle_id || $vehicle_id <= 0) {
            return '<div class="rv-calendar-error"><p>' . esc_html__('Vehicle ID required for calendar', 'mhm-rentiva') . '</p></div>';
        }

        $current_month = $month ?? (int) date('n');
        $current_year = $year ?? (int) date('Y');
        $days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year));
        $first_day = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));
        
        // Get booked days
        $booked_days = self::get_booked_days($vehicle_id, $current_month, $current_year);
        
        $calendar_html = '<div class="rv-calendar-grid">';
        
        // Day names
        $day_names = [
            __('Sun', 'mhm-rentiva'), 
            __('Mon', 'mhm-rentiva'), 
            __('Tue', 'mhm-rentiva'), 
            __('Wed', 'mhm-rentiva'), 
            __('Thu', 'mhm-rentiva'), 
            __('Fri', 'mhm-rentiva'), 
            __('Sat', 'mhm-rentiva')
        ];
        $calendar_html .= '<div class="rv-calendar-header">';
        foreach ($day_names as $day_name) {
            $calendar_html .= '<div class="rv-calendar-day-name">' . $day_name . '</div>';
        }
        $calendar_html .= '</div>';
        
        // Calendar days
        $calendar_html .= '<div class="rv-calendar-days">';
        
        // Empty days for first week
        for ($i = 0; $i < $first_day; $i++) {
            $calendar_html .= '<div class="rv-calendar-day empty"></div>';
        }
        
        // Days of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $is_booked = in_array($day, $booked_days);
            $is_today = ($day == date('j') && $current_month == date('n') && $current_year == date('Y'));
            
            $class = 'rv-calendar-day';
            if ($is_booked) {
                $class .= ' booked';
            }
            if ($is_today) {
                $class .= ' today';
            }
            
            $calendar_html .= '<div class="' . $class . '">' . $day . '</div>';
        }
        
        $calendar_html .= '</div>';
        $calendar_html .= '</div>';
        
        return $calendar_html;
    }

    /**
     * Get booked days
     */
    private static function get_booked_days(int $vehicle_id, int $month, int $year): array
    {
        global $wpdb;
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));
        
        // Get bookings from WordPress post meta
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm_start.meta_value as start_date, pm_end.meta_value as end_date, pm_status.meta_value as status
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = '_mhm_vehicle_id'
            INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_mhm_pickup_date'
            INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_mhm_dropoff_date'
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
            WHERE p.post_type = 'vehicle_booking'
            AND p.post_status = 'publish'
            AND pm_vehicle.meta_value = %d
            AND pm_status.meta_value IN ('confirmed', 'pending')
            AND (
                (pm_start.meta_value <= %s AND pm_end.meta_value >= %s) OR
                (pm_start.meta_value >= %s AND pm_start.meta_value <= %s) OR
                (pm_end.meta_value >= %s AND pm_end.meta_value <= %s)
            )
        ", $vehicle_id, $start_date, $start_date, $start_date, $end_date, $start_date, $end_date));
        
        $booked_days = [];
        
        foreach ($bookings as $booking) {
            $start = new DateTime($booking->start_date);
            $end = new DateTime($booking->end_date);
            
            while ($start <= $end) {
                if ($start->format('n') == $month && $start->format('Y') == $year) {
                    $booked_days[] = (int) $start->format('j');
                }
                $start->add(new DateInterval('P1D'));
            }
        }
        
        return array_unique($booked_days);
    }

    /**
     * Get booking URL
     */
    private static function get_booking_url(int $vehicle_id): string
    {
        // First check from settings
        $booking_url = SettingsCore::get('mhm_rentiva_booking_url', '');
        if (!empty($booking_url)) {
            return add_query_arg('vehicle_id', $vehicle_id, $booking_url);
        }

        // Check ShortcodeUrlManager
        if (class_exists('\MHMRentiva\Admin\Core\ShortcodeUrlManager')) {
            $url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');
            if ($url) {
                return add_query_arg('vehicle_id', $vehicle_id, $url);
            }
        }

        // Fallback: Redirect to vehicles list page with vehicle_id parameter
        return add_query_arg('vehicle_id', $vehicle_id, ShortcodeUrlManager::get_page_url('rentiva_vehicles_list'));
    }
    
    /**
     * Get texts with fallback to i18n defaults
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
     * Get placeholder image URL with fallback
     * Checks for placeholder files and falls back to WordPress default or data URI
     */
    private static function get_placeholder_image_url(): string
    {
        // Try different placeholder file extensions
        $possible_files = [
            'placeholder-vehicle.png',
            'placeholder-vehicle.jpg',
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
        
        // Fallback: Use WordPress default placeholder (1x1 transparent pixel)
        // This prevents 404 errors and ensures the page loads correctly
        return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5WZWhpY2xlIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
    }

    /**
     * AJAX handler for calendar navigation
     */
    public static function ajax_get_calendar(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_calendar_nonce')) {
            wp_die('Security check failed');
        }

        $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
        $month = (int) ($_POST['month'] ?? date('n'));
        $year = (int) ($_POST['year'] ?? date('Y'));

        // If vehicle_id is 0 or 'current', try to get from current context
        if (!$vehicle_id || $vehicle_id === 'current') {
            global $post;
            if ($post && $post->post_type === 'vehicle') {
                $vehicle_id = $post->ID;
            } else {
                $vehicle_id = get_the_ID();
            }
        }

        if (!$vehicle_id) {
            wp_send_json_error(__('Vehicle ID required', 'mhm-rentiva'));
        }

        // Generate calendar HTML
        $calendar_html = self::render_monthly_calendar($vehicle_id, $month, $year);
        // Manual month names for global compatibility
        $month_names = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        $month_year = $month_names[$month] . ' ' . $year;

        wp_send_json_success([
            'calendar_html' => $calendar_html,
            'month_year' => $month_year
        ]);
    }


}

