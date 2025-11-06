<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Availability Calendar Shortcode
 * 
 * [rentiva_availability_calendar] - Date-based availability calendar for a specific vehicle
 * [rentiva_availability_calendar vehicle_id="123" show_pricing="1" theme="compact"]
 * 
 * Features:
 * - Dynamic updates via AJAX
 * - Seasonal/discount integration
 * - Integration with pricing shortcode
 * - Responsive design
 */
final class AvailabilityCalendar extends AbstractShortcode
{
    public const SHORTCODE = 'rentiva_availability_calendar';

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
        parent::register();
        
        // AJAX handlers
        add_action('wp_ajax_mhm_rentiva_availability_data', [self::class, 'ajax_availability_data']);
        add_action('wp_ajax_nopriv_mhm_rentiva_availability_data', [self::class, 'ajax_availability_data']);
        
        add_action('wp_ajax_mhm_rentiva_availability_pricing', [self::class, 'ajax_availability_pricing']);
        add_action('wp_ajax_nopriv_mhm_rentiva_availability_pricing', [self::class, 'ajax_availability_pricing']);
        
        add_action('wp_ajax_mhm_rentiva_load_booking_form', [self::class, 'ajax_load_booking_form']);
        add_action('wp_ajax_nopriv_mhm_rentiva_load_booking_form', [self::class, 'ajax_load_booking_form']);
        
        add_action('wp_ajax_mhm_rentiva_get_vehicle_info', [self::class, 'ajax_get_vehicle_info']);
        add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_info', [self::class, 'ajax_get_vehicle_info']);
    }

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_availability_calendar';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/availability-calendar';
    }

    protected static function get_default_attributes(): array
    {
        return [
            'vehicle_id' => '',
            'show_pricing' => apply_filters('mhm_rentiva/availability_calendar/show_pricing', '1'),
            'theme' => apply_filters('mhm_rentiva/availability_calendar/theme', 'default'),
            'start_date' => '',
            'months_ahead' => apply_filters('mhm_rentiva/availability_calendar/months_ahead', '3'),
            'show_weekends' => apply_filters('mhm_rentiva/availability_calendar/show_weekends', '1'),
            'show_past_dates' => apply_filters('mhm_rentiva/availability_calendar/show_past_dates', '0'),
            'class' => '',
        ];
    }

    protected static function get_css_filename(): string
    {
        return 'availability-calendar.css';
    }

    protected static function get_js_filename(): string
    {
        return 'availability-calendar.js';
    }

    /**
     * Returns CSS files
     */
    protected static function get_css_files(): array
    {
        $css_files = [
            'assets/css/frontend/availability-calendar.css'
        ];
        
        
        return $css_files;
    }

    /**
     * Loads CSS files - Override
     */
    protected static function enqueue_styles(): void
    {
        
        $css_files = static::get_css_files();
        foreach ($css_files as $css_file) {
            if (static::asset_exists($css_file)) {
                $handle = static::get_asset_handle();
                
                // Check for minified version
                $minified_file = str_replace('.css', '.min.css', $css_file);
                $css_url = MHM_RENTIVA_PLUGIN_URL . (static::asset_exists($minified_file) ? $minified_file : $css_file);
                
                wp_enqueue_style(
                    $handle,
                    $css_url,
                    static::get_css_dependencies(),
                    MHM_RENTIVA_VERSION
                );
                break;
            }
        }
        
    }

    /**
     * Loads JS files - Override
     */
    protected static function enqueue_scripts(): void
    {
        
        $js_files = static::get_js_files();
        foreach ($js_files as $js_file) {
            if (static::asset_exists($js_file)) {
                $handle = static::get_asset_handle();
                
                // Check for minified version
                $minified_file = str_replace('.js', '.min.js', $js_file);
                $js_url = MHM_RENTIVA_PLUGIN_URL . (static::asset_exists($minified_file) ? $minified_file : $js_file);
                
                wp_enqueue_script(
                    $handle,
                    $js_url,
                    static::get_js_dependencies(),
                    MHM_RENTIVA_VERSION,
                    true
                );
                
                // Load JavaScript variables
                wp_localize_script(
                    $handle,
                    'mhmRentivaAvailability',
                    [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('mhm_rentiva_availability_nonce'),
                        'currencySymbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
                        'pluginUrl' => plugin_dir_url(__FILE__) . '../../../',
                        'dateFormat' => get_option('date_format', 'd.m.Y'),
                        'timeFormat' => get_option('time_format', 'H:i'),
                        'locale' => get_locale(),
                        'messages' => [
                            'error' => __('An error occurred.', 'mhm-rentiva'),
                            'success' => __('Operation successful.', 'mhm-rentiva')
                        ]
                    ]
                );
                
                // Localize script
                static::localize_script($handle);
                break;
            }
        }
        
    }

    /**
     * Returns JS files
     */
    protected static function get_js_files(): array
    {
        return [
            'assets/js/frontend/availability-calendar.js'
        ];
    }

    /**
     * Override asset loading method
     */
    protected static function enqueue_assets(): void
    {
        
        // Load CSS
        static::enqueue_styles();
        
        // Load JS
        static::enqueue_scripts();
        
    }

    /**
     * Asset loading check - Override
     */
    protected static function enqueue_assets_once(): void
    {
        $tag = static::get_shortcode_tag();
        
        // Static cache check
        static $enqueued_assets = [];
        
        // Load assets
        static::enqueue_assets();
        $enqueued_assets[$tag] = true;
        
    }

    /**
     * Returns CSS dependencies
     */
    protected static function get_css_dependencies(): array
    {
        return [];
    }

    /**
     * Returns JS dependencies
     */
    protected static function get_js_dependencies(): array
    {
        return ['jquery'];
    }

    protected static function get_script_object_name(): string
    {
        return 'mhmRentivaAvailabilityCalendar';
    }

    protected static function get_localized_strings(): array
    {
        return [
            'loading' => __('Loading...', 'mhm-rentiva'),
            'error' => __('An error occurred', 'mhm-rentiva'),
            'available' => __('Available', 'mhm-rentiva'),
            'unavailable' => __('Unavailable', 'mhm-rentiva'),
            'booked' => __('Booked', 'mhm-rentiva'),
            'maintenance' => __('Maintenance', 'mhm-rentiva'),
        ];
    }

    public static function render(array $atts = [], ?string $content = null): string
    {
        $defaults = [
            'vehicle_id'           => '',            // Vehicle ID (empty for all vehicles)
            'show_pricing'         => apply_filters('mhm_rentiva/availability_calendar/show_pricing', '1'),           // Show pricing info
            'show_seasonal_prices' => apply_filters('mhm_rentiva/availability_calendar/show_seasonal_prices', '1'),           // Show seasonal prices
            'show_discounts'       => apply_filters('mhm_rentiva/availability_calendar/show_discounts', '1'),           // Show discounts
            'show_booking_btn'     => apply_filters('mhm_rentiva/availability_calendar/show_booking_btn', '1'),           // Show booking button
            'theme'                => apply_filters('mhm_rentiva/availability_calendar/theme', 'default'),     // Theme (default, compact, detailed)
            'months_to_show'       => apply_filters('mhm_rentiva/availability_calendar/months_to_show', '1'),           // How many months to show
            'start_month'          => '',            // Start month (empty for current month)
            'class'                => '',            // Custom CSS class
            'integrate_pricing'    => apply_filters('mhm_rentiva/availability_calendar/integrate_pricing', '1'),           // Integration with pricing shortcode
        ];
        $atts = shortcode_atts($defaults, $atts, self::SHORTCODE);

        // Prepare template data
        $template_data = self::prepare_template_data($atts);
        
        // Asset loading
        static::enqueue_assets_once();

        // Render template
        try {
            $output = Templates::render('shortcodes/availability-calendar', $template_data, true);
            
            if (empty($output)) {
                return '<div class="rv-availability-error">' . __('Template file not found.', 'mhm-rentiva') . '</div>';
            }
            
            return $output;
        } catch (Exception $e) {
            return '<div class="rv-availability-error">' . __('An error occurred while loading the availability calendar.', 'mhm-rentiva') . '</div>';
        }
    }


    protected static function prepare_template_data(array $atts): array
    {
        // Get vehicle information
        $vehicle = null;
        $vehicle_id = 0;
        
        if (!empty($atts['vehicle_id'])) {
            $vehicle = get_post($atts['vehicle_id']);
            if ($vehicle && $vehicle->post_type === 'vehicle') {
                $vehicle_id = intval($atts['vehicle_id']);
            }
        } else {
            // If vehicle ID not provided, get first available vehicle
            $vehicles = get_posts([
                'post_type' => 'vehicle',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            
            
            if (!empty($vehicles)) {
                $vehicle = $vehicles[0];
                $vehicle_id = $vehicle->ID;
            } else {
            }
        }

        // Vehicle list (for dropdown)
        $vehicles_list = self::get_vehicles_list();

        // Start month
        $start_month = !empty($atts['start_month']) ? $atts['start_month'] : date('Y-m');
        $months_to_show = intval($atts['months_to_show']);

        // If vehicle not selected but vehicles exist, select first one
        if ($vehicle_id === 0 && !empty($vehicles_list)) {
            $vehicle_id = $vehicles_list[0]['id'];
            $vehicle = get_post($vehicle_id);
        }

        // Get calendar data (if vehicle exists)
        $availability_data = [];
        if ($vehicle_id > 0) {
            $availability_data = self::get_availability_data($vehicle_id, $start_month, $months_to_show);
        }

        // Get pricing data (if to be shown and vehicle exists)
        $pricing_data = [];
        if ($atts['show_pricing'] === '1' && $vehicle_id > 0) {
            $pricing_data = self::get_pricing_data($vehicle_id, $start_month, $months_to_show);
        }

        return [
            'atts' => $atts,
            'vehicle' => $vehicle,
            'vehicle_id' => $vehicle_id,
            'vehicles_list' => $vehicles_list,
            'start_month' => $start_month,
            'months_to_show' => $months_to_show,
            'availability_data' => $availability_data,
            'pricing_data' => $pricing_data,
            'current_user' => wp_get_current_user(),
        ];
    }

    private static function get_vehicles_list(): array
    {
        // Cache key
        $cache_key = 'availability_calendar_vehicles_list';
        
        // Cache'den kontrol et
        $cached_data = \MHMRentiva\Admin\Core\PerformanceHelper::cache_get($cache_key);
        if ($cached_data !== null) {
            return $cached_data;
        }
        
        // Performans monitoring
        $performance_data = \MHMRentiva\Admin\Core\PerformanceHelper::time_execution(function() {
            $vehicles = get_posts([
                'post_type' => 'vehicle',
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);

            // Batch load vehicle prices to prevent N+1 queries
            $vehicle_ids = array_map(function($vehicle) {
                return $vehicle->ID;
            }, $vehicles);
            
            $vehicle_data_batch = \MHMRentiva\Admin\Core\PerformanceHelper::batch_load_vehicle_data($vehicle_ids);

            $vehicles_list = [];
            foreach ($vehicles as $vehicle) {
                $vehicle_id = $vehicle->ID;
                $batch_data = $vehicle_data_batch[$vehicle_id] ?? [];
                
                $price = 0;
                
                // Get price directly from meta fields
                $daily_price = get_post_meta($vehicle_id, '_mhm_rentiva_daily_price', true);
                $price_per_day = get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
                
                if ($daily_price) {
                    $price = floatval($daily_price);
                } elseif ($price_per_day) {
                    $price = floatval($price_per_day);
                }
                
                
                $vehicles_list[] = [
                    'id' => $vehicle_id,
                    'title' => $vehicle->post_title,
                    'slug' => $vehicle->post_name,
                    'excerpt' => wp_trim_words($vehicle->post_excerpt, 15),
                    'price' => $price
                ];
            }
            
            return $vehicles_list;
        }, 'vehicles_list_loading');
        
        $vehicles_list = $performance_data['result'];
        
        // Save to cache (30 minutes)
        \MHMRentiva\Admin\Core\PerformanceHelper::cache_set(
            $cache_key, 
            $vehicles_list, 
            1800, 
            ['vehicles', 'availability_calendar']
        );
        
        // Debug log
        if ($performance_data['execution_time'] > 500) { // If slower than 500ms
        }
        
        return $vehicles_list;
    }

    private static function get_availability_data(int $vehicle_id, string $start_month, int $months_to_show): array
    {
        // Cache key
        $cache_key = "availability_data_{$vehicle_id}_{$start_month}_{$months_to_show}";
        
        // Cache'den kontrol et
        $cached_data = \MHMRentiva\Admin\Core\PerformanceHelper::cache_get($cache_key);
        if ($cached_data !== null) {
            return $cached_data;
        }
        
        // Performans monitoring
        $performance_data = \MHMRentiva\Admin\Core\PerformanceHelper::time_execution(function() use ($vehicle_id, $start_month, $months_to_show) {
            global $wpdb;

            $availability_data = [];
            $current_month = $start_month;

            // Calculate date range for single query
            $start_date = date('Y-m-01', strtotime($start_month));
            $end_date = date('Y-m-t', strtotime($start_month . ' +' . ($months_to_show - 1) . ' months'));

            // Single optimized query for all months
            $bookings = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    p.ID,
                    p.post_title,
                    pm_start.meta_value as start_date,
                    pm_end.meta_value as end_date,
                    pm_status.meta_value as status,
                    pm_payment.meta_value as payment_status
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = '_mhm_vehicle_id'
                INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_mhm_start_date'
                INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_mhm_end_date'
                LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
                LEFT JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_mhm_payment_status'
                WHERE p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'pending', 'confirmed')
                AND pm_vehicle.meta_value = %d
                AND pm_start.meta_value <= %s
                AND pm_end.meta_value >= %s
                ORDER BY pm_start.meta_value ASC
            ", $vehicle_id, $end_date, $start_date));

            // Process bookings for all months
            for ($i = 0; $i < $months_to_show; $i++) {
                $month_start = date('Y-m-01', strtotime($current_month));
                $month_end = date('Y-m-t', strtotime($current_month));
                
                // Calculate daily states
                $days = [];
                $current_date = $month_start;
                
                while ($current_date <= $month_end) {
                    $day_status = 'available';
                    $day_bookings = [];
                    $day_occupancy = 0;
                    
                    foreach ($bookings as $booking) {
                        if ($current_date >= $booking->start_date && $current_date <= $booking->end_date) {
                            $day_status = $booking->status ?: 'booked';
                            $day_bookings[] = [
                                'id' => $booking->ID,
                                'title' => $booking->post_title,
                                'status' => $booking->status ?: 'booked',
                                'payment_status' => $booking->payment_status ?: 'unpaid'
                            ];
                            $day_occupancy++;
                        }
                    }
                    
                    // State determination
                    if ($day_occupancy > 0) {
                        if ($day_occupancy >= 1) { // If vehicle capacity is 1
                            $day_status = 'booked';
                        } else {
                            $day_status = 'partial';
                        }
                    }
                    
                    $days[$current_date] = [
                        'status' => $day_status,
                        'bookings' => $day_bookings,
                        'occupancy' => $day_occupancy,
                        'day_number' => date('j', strtotime($current_date)),
                        'is_weekend' => in_array(date('N', strtotime($current_date)), [6, 7]),
                        'is_today' => $current_date === date('Y-m-d'),
                        'is_past' => $current_date < date('Y-m-d')
                    ];
                    
                    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                }

                $availability_data[$current_month] = [
                    'month_name' => self::get_month_name($current_month),
                'year' => date('Y', strtotime($current_month)),
                'days' => $days,
                'stats' => [
                    'total_days' => count($days),
                    'available_days' => count(array_filter($days, function($day) { return $day['status'] === 'available'; })),
                    'booked_days' => count(array_filter($days, function($day) { return $day['status'] === 'booked'; })),
                    'partial_days' => count(array_filter($days, function($day) { return $day['status'] === 'partial'; }))
                ]
            ];

                $current_month = date('Y-m', strtotime($current_month . ' +1 month'));
            }

            return $availability_data;
        }, "availability_data_{$vehicle_id}");
        
        $availability_data = $performance_data['result'];
        
        // Save to cache (15 minutes)
        \MHMRentiva\Admin\Core\PerformanceHelper::cache_set(
            $cache_key, 
            $availability_data, 
            900, 
            ['vehicles', 'availability', "vehicle_{$vehicle_id}"]
        );
        
        // Debug log
        if ($performance_data['execution_time'] > 1000) { // If slower than 1 second
        }
        
        return $availability_data;
    }

    private static function get_pricing_data(int $vehicle_id, string $start_month, int $months_to_show): array
    {
        // Integration with pricing shortcode
        if (class_exists('MHM\Admin\Vehicle\Frontend\VehiclePricing')) {
            return self::get_pricing_data_from_shortcode($vehicle_id, $start_month, $months_to_show);
        }
        
        // Fallback: Simple price calculation
        return self::get_simple_pricing_data($vehicle_id, $start_month, $months_to_show);
    }

    private static function get_pricing_data_from_shortcode(int $vehicle_id, string $start_month, int $months_to_show): array
    {
        // Use pricing shortcode's price calculation method
        $pricing_data = [];
        $current_month = $start_month;

        for ($i = 0; $i < $months_to_show; $i++) {
            $month_start = date('Y-m-01', strtotime($current_month));
            $month_end = date('Y-m-t', strtotime($current_month));

            // Get pricing data from pricing shortcode
            $base_price = self::get_vehicle_base_price($vehicle_id);
            $weekend_price = self::get_vehicle_weekend_price($vehicle_id);
            $seasonal_prices = self::get_vehicle_seasonal_prices($vehicle_id);
            $discounts = self::get_vehicle_discounts($vehicle_id);

            $days = [];
            $current_date = $month_start;
            
            while ($current_date <= $month_end) {
                $is_weekend = in_array(date('N', strtotime($current_date)), [6, 7]);
                $day_price = $base_price;
                $discount_amount = 0;
                
                // Weekend price
                if ($is_weekend && $weekend_price > 0) {
                    $day_price = $weekend_price;
                }
                
                // Check seasonal prices
                foreach ($seasonal_prices as $season) {
                    if ($current_date >= $season['start_date'] && $current_date <= $season['end_date']) {
                        $day_price = $season['price'];
                        break;
                    }
                }
                
                // Check discounts
                foreach ($discounts as $discount) {
                    if ($current_date >= $discount['start_date'] && $current_date <= $discount['end_date']) {
                        if ($discount['type'] === 'percentage') {
                            $discount_amount = ($day_price * $discount['value']) / 100;
                        } else {
                            $discount_amount = $discount['value'];
                        }
                        $day_price = max(0, $day_price - $discount_amount);
                        break;
                    }
                }
                
                $days[$current_date] = [
                    'base_price' => $base_price,
                    'day_price' => $day_price,
                    'is_weekend' => $is_weekend,
                    'has_discount' => $discount_amount > 0,
                    'discount_amount' => $discount_amount,
                    'original_price' => $day_price + $discount_amount
                ];
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }

            $pricing_data[$current_month] = [
                'month_name' => self::get_month_name($current_month),
                'year' => date('Y', strtotime($current_month)),
                'days' => $days,
                'base_price' => $base_price,
                'weekend_price' => $weekend_price
            ];

            $current_month = date('Y-m', strtotime($current_month . ' +1 month'));
        }

        return $pricing_data;
    }

    private static function get_simple_pricing_data(int $vehicle_id, string $start_month, int $months_to_show): array
    {
        // Basit fiyat hesaplama (fallback)
        $pricing_data = [];
        $current_month = $start_month;

        for ($i = 0; $i < $months_to_show; $i++) {
            $month_start = date('Y-m-01', strtotime($current_month));
            $month_end = date('Y-m-t', strtotime($current_month));

            // Get vehicle pricing information - Use correct meta fields
            $base_price = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0);
            $weekend_price = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_week', true) ?: $base_price);
            $seasonal_prices = []; // Seasonal pricing meta field not yet available

            $days = [];
            $current_date = $month_start;
            
            while ($current_date <= $month_end) {
                $is_weekend = in_array(date('N', strtotime($current_date)), [6, 7]);
                $day_price = $base_price;
                
                // Weekend price
                if ($is_weekend) {
                    $day_price = $weekend_price;
                }
                
                // Check seasonal prices
                foreach ($seasonal_prices as $season) {
                    if ($current_date >= $season['start_date'] && $current_date <= $season['end_date']) {
                        $day_price = $season['price'];
                        break;
                    }
                }
                
                $days[$current_date] = [
                    'base_price' => $base_price,
                    'day_price' => $day_price,
                    'is_weekend' => $is_weekend,
                    'has_discount' => $day_price < $base_price,
                    'discount_amount' => $base_price - $day_price
                ];
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }

            $pricing_data[$current_month] = [
                'month_name' => self::get_month_name($current_month),
                'year' => date('Y', strtotime($current_month)),
                'days' => $days,
                'base_price' => $base_price,
                'weekend_price' => $weekend_price
            ];

            $current_month = date('Y-m', strtotime($current_month . ' +1 month'));
        }

        return $pricing_data;
    }

    private static function get_vehicle_base_price(int $vehicle_id): float
    {
        $price = floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0);
        return $price;
    }

    private static function get_vehicle_weekend_price(int $vehicle_id): float
    {
        // Weekend pricing not yet in separate meta field, use daily price
        return floatval(get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true) ?: 0);
    }

    private static function get_vehicle_seasonal_prices(int $vehicle_id): array
    {
        // Seasonal pricing not yet in meta field
        return [];
    }

    private static function get_vehicle_discounts(int $vehicle_id): array
    {
        // Discounts not yet in meta field
        return [];
    }

    public static function ajax_availability_data(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_availability_nonce')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }

        try {
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $start_month = self::sanitize_text_field_safe($_POST['start_month'] ?? date('Y-m'));
            $months_to_show = intval($_POST['months_to_show'] ?? 3);

            if (!$vehicle_id) {
                wp_send_json_error(['message' => __('Vehicle ID is required.', 'mhm-rentiva')]);
            }

            $availability_data = self::get_availability_data($vehicle_id, $start_month, $months_to_show);

            wp_send_json_success([
                'availability_data' => $availability_data,
                'message' => __('Availability data updated.', 'mhm-rentiva')
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while retrieving data.', 'mhm-rentiva')]);
        }
    }

    public static function ajax_availability_pricing(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_availability_nonce')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }

        try {
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $start_month = self::sanitize_text_field_safe($_POST['start_month'] ?? date('Y-m'));
            $months_to_show = intval($_POST['months_to_show'] ?? 3);

            if (!$vehicle_id) {
                wp_send_json_error(['message' => __('Vehicle ID is required.', 'mhm-rentiva')]);
            }

            $pricing_data = self::get_pricing_data($vehicle_id, $start_month, $months_to_show);

            wp_send_json_success([
                'pricing_data' => $pricing_data,
                'message' => __('Pricing data updated.', 'mhm-rentiva')
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while retrieving pricing data.', 'mhm-rentiva')]);
        }
    }

    public static function ajax_get_vehicle_info(): void
    {
        try {
            // Nonce check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_availability_nonce')) {
                wp_send_json_error('Security check failed');
                return;
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            
            if ($vehicle_id <= 0) {
                wp_send_json_error('Invalid vehicle ID');
                return;
            }

            $vehicle = get_post($vehicle_id);
            if (!$vehicle || $vehicle->post_type !== 'vehicle') {
                wp_send_json_error('Vehicle not found');
                return;
            }

            // Vehicle image
            $image_url = '';
            if (has_post_thumbnail($vehicle_id)) {
                $image_url = get_the_post_thumbnail_url($vehicle_id, 'medium');
            }

            // Vehicle features
            $specs = [];
            $fuel_type = get_post_meta($vehicle_id, '_mhm_rentiva_fuel_type', true);
            $transmission = get_post_meta($vehicle_id, '_mhm_rentiva_transmission', true);
            $year = get_post_meta($vehicle_id, '_mhm_rentiva_year', true);
            $mileage = get_post_meta($vehicle_id, '_mhm_rentiva_mileage', true);
            $seats = get_post_meta($vehicle_id, '_mhm_rentiva_seats', true);
            
            if ($fuel_type) $specs['fuel'] = $fuel_type;
            if ($transmission) $specs['transmission'] = $transmission;
            if ($year) $specs['year'] = $year;
            if ($mileage) $specs['mileage'] = $mileage . ' km';
            if ($seats) $specs['seats'] = $seats . ' people';

            // Fiyat
            $price = 0;
            $daily_price = get_post_meta($vehicle_id, '_mhm_rentiva_daily_price', true);
            if ($daily_price) {
                $price = floatval($daily_price);
            } else {
                $price_per_day = get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
                if ($price_per_day) {
                    $price = floatval($price_per_day);
                }
            }

            $response_data = [
                'id' => $vehicle_id,
                'title' => $vehicle->post_title,
                'image' => $image_url,
                'specs' => $specs,
                'price' => number_format($price, 0, ',', '.')
            ];

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while retrieving vehicle information.', 'mhm-rentiva')]);
        }
    }

    public static function ajax_load_booking_form(): void
    {
        try {
            // Nonce check
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'mhm_rentiva_availability_nonce')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $start_date = self::sanitize_text_field_safe($_POST['start_date'] ?? '');
            $end_date = self::sanitize_text_field_safe($_POST['end_date'] ?? '');

            if ($vehicle_id <= 0 || empty($start_date) || empty($end_date)) {
                wp_send_json_error(['message' => __('Invalid parameters.', 'mhm-rentiva')]);
            }

            // Render booking form shortcode
            $form_html = do_shortcode("[rentiva_booking_form vehicle_id=\"{$vehicle_id}\" start_date=\"{$start_date}\" end_date=\"{$end_date}\"]");

            wp_send_json_success([
                'form_html' => $form_html,
                'message' => __('Booking form loaded.', 'mhm-rentiva')
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while loading the booking form.', 'mhm-rentiva')]);
        }
    }

    private static function get_month_name(string $month): string
    {
        $months = [
            '01' => __('January', 'mhm-rentiva'), '02' => __('February', 'mhm-rentiva'), 
            '03' => __('March', 'mhm-rentiva'), '04' => __('April', 'mhm-rentiva'),
            '05' => __('May', 'mhm-rentiva'), '06' => __('June', 'mhm-rentiva'), 
            '07' => __('July', 'mhm-rentiva'), '08' => __('August', 'mhm-rentiva'),
            '09' => __('September', 'mhm-rentiva'), '10' => __('October', 'mhm-rentiva'), 
            '11' => __('November', 'mhm-rentiva'), '12' => __('December', 'mhm-rentiva')
        ];
        
        $month_num = date('m', strtotime($month));
        return $months[$month_num] ?? __('Unknown', 'mhm-rentiva');
    }
}
