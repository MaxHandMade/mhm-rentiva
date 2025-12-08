<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search Results Shortcode
 * 
 * [rentiva_search_results] - Vehicle search results page
 * [rentiva_search_results layout="grid"] - Grid view
 * [rentiva_search_results layout="list"] - List view
 * [rentiva_search_results show_filters="1"] - Show sidebar filters
 * 
 * Features:
 * - Sidebar filter system
 * - Dynamic filtering via AJAX
 * - Grid/List view options
 * - Pagination
 * - SEO-friendly URL parameters
 */
final class SearchResults extends AbstractShortcode
{
    public const SHORTCODE = 'rentiva_search_results';

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
        add_action('wp_ajax_mhm_rentiva_filter_results', [self::class, 'ajax_filter_results']);
        add_action('wp_ajax_nopriv_mhm_rentiva_filter_results', [self::class, 'ajax_filter_results']);
        
        add_action('wp_ajax_mhm_rentiva_update_filters', [self::class, 'ajax_update_filters']);
        add_action('wp_ajax_nopriv_mhm_rentiva_update_filters', [self::class, 'ajax_update_filters']);
    }

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_search_results';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/search-results';
    }

    protected static function get_default_attributes(): array
    {
        return [
            'layout'              => 'grid',     // grid/list
            'show_filters'        => '1',        // 1/0
            'results_per_page'    => '12',       // Results per page
            'show_pagination'     => '1',        // 1/0
            'show_sorting'        => '1',        // 1/0
            'show_view_toggle'    => '1',        // 1/0
            'default_sort'        => 'relevance', // relevance/price_asc/price_desc/name_asc/name_desc
            'class'               => '',         // Custom CSS class
        ];
    }

    protected static function prepare_template_data(array $atts): array
    {
        // Get search criteria from URL parameters
        $search_params = self::get_search_params_from_url();
        
        // Get search results
        $results = self::perform_search($search_params, $atts);
        
        // Prepare filter options
        $filter_options = self::get_filter_options($search_params);
        
        // Prepare template data
        return [
            'atts' => $atts,
            'search_params' => $search_params,
            'results' => $results,
            'filter_options' => $filter_options,
            'pagination' => $results['pagination'] ?? [],
            'sorting_options' => self::get_sorting_options(),
            'nonce_field' => wp_nonce_field('mhm_rentiva_search_results', 'mhm_rentiva_search_results_nonce', true, false),
        ];
    }

    public static function render(array $atts = [], ?string $content = null): string
    {
        return parent::render($atts, $content);
    }

    /**
     * Enqueue asset files
     */
    protected static function enqueue_assets(): void
    {
        // Core styles
        if (class_exists('\MHMRentiva\Admin\Core\Utilities\Styles')) {
            wp_enqueue_style(\MHMRentiva\Admin\Core\Utilities\Styles::getCssHandle());
        }

        // Search results CSS
        wp_enqueue_style(
            'mhm-rentiva-search-results-css',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/search-results.css',
            [],
            MHM_RENTIVA_VERSION . '-' . filemtime(MHM_RENTIVA_PLUGIN_DIR . 'assets/css/frontend/search-results.css'),
            'all'
        );

        // Search results JavaScript
        wp_enqueue_script(
            'mhm-rentiva-search-results-js',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/search-results.js',
            ['jquery'],
            MHM_RENTIVA_VERSION . '-' . filemtime(MHM_RENTIVA_PLUGIN_DIR . 'assets/js/frontend/search-results.js'),
            true
        );

        // Localize script
        wp_localize_script('mhm-rentiva-search-results-js', 'mhmRentivaSearchResults', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_search_results'),
            'current_url' => home_url(add_query_arg(null, null)),
            'search_page_url' => ShortcodeUrlManager::get_page_url('rentiva_search'),
            'i18n' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'no_results' => __('No vehicles found', 'mhm-rentiva'),
                'error' => __('An error occurred. Please try again.', 'mhm-rentiva'),
                'filter_applied' => __('Filter applied', 'mhm-rentiva'),
                'clear_filters' => __('Clear all filters', 'mhm-rentiva'),
                'clear_all' => __('Clear All', 'mhm-rentiva'),
                'clear_all_with_count' => __('Clear All (%d)', 'mhm-rentiva'),
                'try_adjusting' => __('Try adjusting your search criteria or filters.', 'mhm-rentiva'),
                'back_to_search' => __('Back to Search', 'mhm-rentiva'),
            ],
        ]);
    }

    /**
     * Get search parameters from URL parameters
     */
    private static function get_search_params_from_url(): array
    {
        return [
            'keyword' => self::sanitize_text_field_safe($_GET['keyword'] ?? ''),
            'pickup_date' => self::sanitize_text_field_safe($_GET['pickup_date'] ?? ''),
            'return_date' => self::sanitize_text_field_safe($_GET['return_date'] ?? ''),
            // Legacy support
            'start_date' => self::sanitize_text_field_safe($_GET['start_date'] ?? $_GET['pickup_date'] ?? ''),
            'end_date' => self::sanitize_text_field_safe($_GET['end_date'] ?? $_GET['return_date'] ?? ''),
            'min_price' => (int) ($_GET['min_price'] ?? 0),
            'max_price' => (int) ($_GET['max_price'] ?? 0),
            'fuel_type' => self::sanitize_text_field_safe($_GET['fuel_type'] ?? ''),
            'transmission' => self::sanitize_text_field_safe($_GET['transmission'] ?? ''),
            'seats' => self::sanitize_text_field_safe($_GET['seats'] ?? ''),
            'brand' => self::sanitize_text_field_safe($_GET['brand'] ?? ''),
            'year_min' => (int) ($_GET['year_min'] ?? 0),
            'year_max' => (int) ($_GET['year_max'] ?? 0),
            'mileage_max' => (int) ($_GET['mileage_max'] ?? 0),
            'category' => self::sanitize_text_field_safe($_GET['category'] ?? ''),
            'sort' => self::sanitize_text_field_safe($_GET['sort'] ?? 'relevance'),
            'page' => (int) ($_GET['page'] ?? 1),
        ];
    }

    /**
     * Perform search operation
     */
    private static function perform_search(array $params, array $atts): array
    {
        $args = [
            'post_type' => PT_Vehicle::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => (int) $atts['results_per_page'],
            'paged' => $params['page'] ?? 1,
            'meta_query' => [],
        ];

        // Keyword search
        if (!empty($params['keyword'])) {
            $args['s'] = $params['keyword'];
        }

        // Price range
        if ($params['min_price'] > 0 || $params['max_price'] > 0) {
            $price_query = [
                'key' => '_mhm_rentiva_price_per_day',
                'type' => 'NUMERIC',
            ];
            
            if ($params['min_price'] > 0 && $params['max_price'] > 0) {
                $price_query['value'] = [$params['min_price'], $params['max_price']];
                $price_query['compare'] = 'BETWEEN';
            } elseif ($params['min_price'] > 0) {
                $price_query['value'] = $params['min_price'];
                $price_query['compare'] = '>=';
            } elseif ($params['max_price'] > 0) {
                $price_query['value'] = $params['max_price'];
                $price_query['compare'] = '<=';
            }
            
            $args['meta_query'][] = $price_query;
        }

        // Fuel type (process as array)
        if (!empty($params['fuel_type'])) {
            $fuel_values = is_array($params['fuel_type']) ? $params['fuel_type'] : [$params['fuel_type']];
            $args['meta_query'][] = [
                'key' => '_mhm_rentiva_fuel_type',
                'value' => $fuel_values,
                'compare' => 'IN',
            ];
        }

        // Transmission type (process as array)
        if (!empty($params['transmission'])) {
            $transmission_values = is_array($params['transmission']) ? $params['transmission'] : [$params['transmission']];
            $args['meta_query'][] = [
                'key' => '_mhm_rentiva_transmission',
                'value' => $transmission_values,
                'compare' => 'IN',
            ];
        }

        // Seat count (process as array)
        if (!empty($params['seats'])) {
            $seats_values = is_array($params['seats']) ? $params['seats'] : [$params['seats']];
            $args['meta_query'][] = [
                'key' => '_mhm_rentiva_seats',
                'value' => $seats_values,
                'compare' => 'IN',
            ];
        }

        // Brand (process as array)
        if (!empty($params['brand'])) {
            $brand_values = is_array($params['brand']) ? $params['brand'] : [$params['brand']];
            $args['meta_query'][] = [
                'key' => '_mhm_rentiva_brand',
                'value' => $brand_values,
                'compare' => 'IN',
            ];
        }

        // Year range
        if ($params['year_min'] > 0 || $params['year_max'] > 0) {
            $year_query = [
                'key' => '_mhm_rentiva_year',
                'type' => 'NUMERIC',
            ];
            
            if ($params['year_min'] > 0 && $params['year_max'] > 0) {
                $year_query['value'] = [$params['year_min'], $params['year_max']];
                $year_query['compare'] = 'BETWEEN';
            } elseif ($params['year_min'] > 0) {
                $year_query['value'] = $params['year_min'];
                $year_query['compare'] = '>=';
            } elseif ($params['year_max'] > 0) {
                $year_query['value'] = $params['year_max'];
                $year_query['compare'] = '<=';
            }
            
            $args['meta_query'][] = $year_query;
        }

        // Mileage
        if ($params['mileage_max'] > 0) {
            $args['meta_query'][] = [
                'key' => '_mhm_rentiva_mileage',
                'value' => $params['mileage_max'],
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        // Sorting
        switch ($params['sort']) {
            case 'price_asc':
                $args['meta_key'] = '_mhm_rentiva_price_per_day';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'price_desc':
                $args['meta_key'] = '_mhm_rentiva_price_per_day';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'name_asc':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'name_desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
            case 'year_desc':
                $args['meta_key'] = '_mhm_rentiva_year';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            default: // relevance
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
        }

        // Meta query relation
        if (count($args['meta_query']) > 1) {
            $args['meta_query']['relation'] = 'AND';
        }

        // Execute query
        $query = new \WP_Query($args);
        
        // Format results
        $vehicles = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $vehicles[] = self::format_vehicle_data(get_the_ID());
            }
            wp_reset_postdata();
        }

        $current_page = $params['page'] ?? 1;
        
        return [
            'vehicles' => $vehicles,
            'total' => $query->found_posts,
            'current_page' => $current_page,
            'per_page' => (int) $atts['results_per_page'],
            'max_pages' => $query->max_num_pages,
            'pagination' => [
                'current' => $current_page,
                'total' => $query->max_num_pages,
                'has_prev' => $current_page > 1,
                'has_next' => $current_page < $query->max_num_pages,
                'prev_page' => max(1, $current_page - 1),
                'next_page' => min($query->max_num_pages, $current_page + 1),
            ],
        ];
    }

    /**
     * Formats vehicle data
     */
    private static function format_vehicle_data(int $vehicle_id): array
    {
        $vehicle = get_post($vehicle_id);
        if (!$vehicle) {
            return [];
        }

        $featured_image_id = get_post_thumbnail_id($vehicle_id);
        $featured_image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'medium') : '';

        return [
            'id' => $vehicle_id,
            'title' => $vehicle->post_title,
            'excerpt' => $vehicle->post_excerpt,
            'url' => get_permalink($vehicle_id) ?: '',
            'featured_image' => [
                'url' => $featured_image_url,
                'alt' => $vehicle->post_title,
            ],
            'price_per_day' => (float) get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true),
            'currency' => \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD'),
            'currency_symbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
            'brand' => get_post_meta($vehicle_id, '_mhm_rentiva_brand', true),
            'model' => get_post_meta($vehicle_id, '_mhm_rentiva_model', true),
            'year' => get_post_meta($vehicle_id, '_mhm_rentiva_year', true),
            'fuel_type' => get_post_meta($vehicle_id, '_mhm_rentiva_fuel_type', true),
            'transmission' => get_post_meta($vehicle_id, '_mhm_rentiva_transmission', true),
            'seats' => get_post_meta($vehicle_id, '_mhm_rentiva_seats', true),
            'mileage' => get_post_meta($vehicle_id, '_mhm_rentiva_mileage', true),
            'rating' => [
                'average' => (float) get_post_meta($vehicle_id, '_mhm_rentiva_rating_average', true),
                'count' => (int) get_post_meta($vehicle_id, '_mhm_rentiva_rating_count', true),
            ],
        ];
    }

    /**
     * Gets filter options
     */
    private static function get_filter_options(array $current_params): array
    {
        global $wpdb;

        // Fuel types
        $fuel_types = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_fuel_type' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        ");

        // Transmission types
        $transmissions = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_transmission' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        ");

        // Seat counts
        $seats = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_seats' 
            AND meta_value != '' 
            ORDER BY CAST(meta_value AS UNSIGNED) ASC
        ");

        // Brands
        $brands = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_brand' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        ");

        // Year range
        $year_range = $wpdb->get_row("
            SELECT 
                MIN(CAST(meta_value AS UNSIGNED)) as min_year,
                MAX(CAST(meta_value AS UNSIGNED)) as max_year
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_year' 
            AND meta_value != '' 
            AND meta_value REGEXP '^[0-9]+$'
        ");

        // Price range
        $price_range = $wpdb->get_row("
            SELECT 
                MIN(CAST(meta_value AS DECIMAL(10,2))) as min_price,
                MAX(CAST(meta_value AS DECIMAL(10,2))) as max_price
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_price_per_day' 
            AND meta_value != '' 
            AND meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
        ");

        return [
            'fuel_types' => $fuel_types ?: [],
            'transmissions' => $transmissions ?: [],
            'seats' => $seats ?: [],
            'brands' => $brands ?: [],
            'year_range' => [
                'min' => (int) ($year_range->min_year ?? 1990),
                'max' => (int) ($year_range->max_year ?? date('Y')),
            ],
            'price_range' => [
                'min' => (float) ($price_range->min_price ?? 0),
                'max' => (float) ($price_range->max_price ?? 10000),
            ],
        ];
    }

    /**
     * Gets sorting options
     */
    private static function get_sorting_options(): array
    {
        return [
            'relevance' => __('Most Relevant', 'mhm-rentiva'),
            'price_asc' => __('Price: Low to High', 'mhm-rentiva'),
            'price_desc' => __('Price: High to Low', 'mhm-rentiva'),
            'name_asc' => __('Name: A to Z', 'mhm-rentiva'),
            'name_desc' => __('Name: Z to A', 'mhm-rentiva'),
            'year_desc' => __('Newest First', 'mhm-rentiva'),
        ];
    }

    /**
     * AJAX: Update filter results
     */
    public static function ajax_filter_results(): void
    {
        check_ajax_referer('mhm_rentiva_search_results', 'nonce');

        try {
            // Get filters from POST parameters
            $search_params = [
                'keyword' => self::sanitize_text_field_safe($_POST['keyword'] ?? ''),
                'pickup_date' => self::sanitize_text_field_safe($_POST['pickup_date'] ?? ''),
                'return_date' => self::sanitize_text_field_safe($_POST['return_date'] ?? ''),
                'min_price' => (int) ($_POST['min_price'] ?? 0),
                'max_price' => (int) ($_POST['max_price'] ?? 0),
                'fuel_type' => $_POST['fuel_type'] ?? [],
                'transmission' => $_POST['transmission'] ?? [],
                'seats' => $_POST['seats'] ?? [],
                'brand' => $_POST['brand'] ?? [],
                'year_min' => (int) ($_POST['year_min'] ?? 0),
                'year_max' => (int) ($_POST['year_max'] ?? 0),
                'mileage_max' => (int) ($_POST['mileage_max'] ?? 0),
                'sort' => self::sanitize_text_field_safe($_POST['sort'] ?? 'relevance'),
                'page' => (int) ($_POST['page'] ?? 1),
            ];

            $atts = [
                'layout' => self::sanitize_text_field_safe($_POST['layout'] ?? 'grid'),
                'results_per_page' => (int) ($_POST['per_page'] ?? 12),
            ];

            $results = self::perform_search($search_params, $atts);

            wp_send_json_success([
                'html' => self::render_vehicles_list($results['vehicles'], $atts['layout']),
                'pagination' => $results['pagination'],
                'total' => $results['total'],
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Renders vehicles list
     */
    private static function render_vehicles_list(array $vehicles, string $layout): string
    {
        if (empty($vehicles)) {
            return '<div class="rv-no-results">' . __('No vehicles found matching your criteria.', 'mhm-rentiva') . '</div>';
        }

        $html = '';
        foreach ($vehicles as $vehicle) {
            $html .= self::render_vehicle_card($vehicle, $layout);
        }

        return $html;
    }

    /**
     * Renders single vehicle card
     */
    public static function render_vehicle_card(array $vehicle, string $layout): string
    {
        if (empty($vehicle)) {
            return '';
        }

        // Parent container controls layout now. Card class is neutral.
        $card_class = '';
        
        ob_start();
        ?>
        <div class="rv-vehicle-card <?php echo esc_attr($card_class); ?>" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
            
            <!-- Vehicle Image -->
            <div class="rv-vehicle-image">
                <?php if (!empty($vehicle['featured_image']['url'])): ?>
                    <img src="<?php echo esc_url($vehicle['featured_image']['url']); ?>" 
                         alt="<?php echo esc_attr($vehicle['featured_image']['alt']); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="rv-no-image">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM5 19V5h14v14H5z"/>
                            <path d="M7 7h10v6H7z"/>
                        </svg>
                    </div>
                <?php endif; ?>
                
                <!-- Price Badge -->
                <div class="rv-price-badge">
                    <span class="rv-price-amount"><?php echo esc_html($vehicle['currency_symbol'] ?? '$'); ?><?php echo esc_html(number_format($vehicle['price_per_day'] ?? 0)); ?></span>
                    <span class="rv-price-period"><?php _e('/day', 'mhm-rentiva'); ?></span>
                </div>
            </div>

            <!-- Vehicle Info -->
            <div class="rv-vehicle-info">
                <h3 class="rv-vehicle-title">
                    <a href="<?php echo esc_url($vehicle['url'] ?? '#'); ?>">
                        <?php echo esc_html($vehicle['title'] ?? ''); ?>
                    </a>
                </h3>
                
                <?php if (!empty($vehicle['brand']) || !empty($vehicle['model'])): ?>
                <p class="rv-vehicle-meta">
                    <?php if (!empty($vehicle['brand'])): ?>
                        <span class="rv-brand"><?php echo esc_html($vehicle['brand']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vehicle['model'])): ?>
                        <span class="rv-model"><?php echo esc_html($vehicle['model']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vehicle['year'])): ?>
                        <span class="rv-year"><?php echo esc_html($vehicle['year']); ?></span>
                    <?php endif; ?>
                </p>
                <?php endif; ?>

                <!-- Vehicle Features -->
                <div class="rv-vehicle-features">
                    <?php if (!empty($vehicle['fuel_type'])): ?>
                        <span class="rv-feature">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            <?php echo esc_html($vehicle['fuel_type']); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['transmission'])): ?>
                        <span class="rv-feature">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <?php echo esc_html($vehicle['transmission']); ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($vehicle['seats'])): ?>
                        <span class="rv-feature">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <?php echo esc_html($vehicle['seats']); ?> <?php _e('seats', 'mhm-rentiva'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Rating -->
                <?php if (!empty($vehicle['rating']['average']) && $vehicle['rating']['average'] > 0): ?>
                <div class="rv-vehicle-rating">
                    <div class="rv-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="rv-star <?php echo $i <= $vehicle['rating']['average'] ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="rv-rating-count">
                        <?php
                        /* translators: %d: number of reviews. */
                        printf(_n('(%d review)', '(%d reviews)', $vehicle['rating']['count'] ?? 0, 'mhm-rentiva'), $vehicle['rating']['count'] ?? 0);
                        ?>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="rv-vehicle-actions">
                    <a href="<?php echo esc_url($vehicle['url'] ?? '#'); ?>" class="rv-btn rv-btn-primary">
                        <?php _e('View Details', 'mhm-rentiva'); ?>
                    </a>
                    <button type="button" class="rv-btn rv-btn-secondary rv-add-to-favorites" data-vehicle-id="<?php echo esc_attr($vehicle['id'] ?? 0); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
