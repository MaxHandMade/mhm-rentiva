<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Search filters and batched lookup queries are intentional and bounded by pagination/ID limits.

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use Exception;

use MHMRentiva\Admin\Core\QueryHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
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
	/**
	 * Context key for the availability injector filter.
	 */
	private const SEARCH_CONTEXT_KEY = 'mhm_search_context';


	public const SHORTCODE = 'rentiva_search_results';

	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value Value to sanitize
	 * @return string
	 */
	public static function sanitize_text_field_safe($value): string
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field((string) $value);
	}

	private static function get_text(string $key, string $default = ''): string
	{
		$get = $GLOBALS['_GET'] ?? [];
		if (! isset($get[$key])) {
			return $default;
		}

		return sanitize_text_field(wp_unslash((string) $get[$key]));
	}

	private static function get_int(string $key, int $default = 0): int
	{
		$get = $GLOBALS['_GET'] ?? [];
		if (! isset($get[$key])) {
			return $default;
		}

		return (int) wp_unslash((string) $get[$key]);
	}

	private static function post_text(string $key, string $default = ''): string
	{
		$post = $GLOBALS['_POST'] ?? [];
		if (! isset($post[$key])) {
			return $default;
		}

		return sanitize_text_field(wp_unslash((string) $post[$key]));
	}

	private static function post_int(string $key, int $default = 0): int
	{
		$post = $GLOBALS['_POST'] ?? [];
		if (! isset($post[$key])) {
			return $default;
		}

		return (int) wp_unslash((string) $post[$key]);
	}

	private static function post_text_or_array(string $key)
	{
		$post = $GLOBALS['_POST'] ?? [];
		if (! isset($post[$key])) {
			return array();
		}

		$value = wp_unslash($post[$key]);
		return is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field((string)$value);
	}

	public static function register(): void
	{
		parent::register();

		// AJAX handlers
		add_action('wp_ajax_mhm_rentiva_filter_results', array(self::class, 'ajax_filter_results'));
		add_action('wp_ajax_nopriv_mhm_rentiva_filter_results', array(self::class, 'ajax_filter_results'));
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
		return array(
			'layout'               => 'grid',     // grid/list
			'show_filters'         => '1',        // 1/0
			'results_per_page'     => '12',       // Results per page
			'show_pagination'      => '1',        // 1/0
			'show_sorting'         => '1',        // 1/0
			'show_view_toggle'     => '1',        // 1/0
			'show_favorite_button' => '1',        // 1/0
			'show_compare_button'  => '1',        // 1/0
			'show_booking_btn'     => '1',        // 1/0
			'show_price'           => '1',        // 1/0
			'show_title'           => '1',        // 1/0
			'show_features'        => '1',        // 1/0
			'show_rating'          => '1',        // 1/0
			'show_badges'          => '1',        // 1/0
			'default_sort'         => 'price_asc', // relevance/price_asc/price_desc/name_asc/name_desc
			'class'                => '',         // Custom CSS class
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		// Get search criteria from URL parameters
		$search_params = self::get_search_params_from_url();

		// Get search results
		$results = self::perform_search($search_params, $atts);

		// Prepare filter options
		$filter_options = self::get_filter_options($search_params);

		// Compact Handling: If layout is 'compact', force internal layout to 'grid' but flags 'is_compact'
		$is_compact = $atts['layout'] === 'compact';
		if ($is_compact) {
			$atts['layout'] = 'grid'; // Force grid structure for vehicle cards
		}

		// Prepare template data
		return array(
			'atts'            => $atts,
			'is_compact'      => $is_compact,
			'search_params'   => $search_params,
			'results'         => $results,
			'filter_options'  => $filter_options,
			'pagination'      => $results['pagination'] ?? array(),
			'sorting_options' => self::get_sorting_options(),
			'nonce_field'     => wp_nonce_field('mhm_rentiva_search_results', 'mhm_rentiva_search_results_nonce', true, false),
		);
	}


	/**
	 * Force-enqueue runtime assets for block rendering path.
	 *
	 * Block render callbacks may return cached shortcode HTML where asset enqueue
	 * hooks are not guaranteed; this ensures toggle JS and localization are present.
	 */
	public static function ensure_runtime_assets(array $atts = array()): void
	{
		$normalized_atts = shortcode_atts(static::get_default_attributes(), $atts, static::get_shortcode_tag());
		static::enqueue_assets($normalized_atts);
	}

	/**
	 * Enqueue asset files
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// Core styles and Shared vehicle card styles
		if (class_exists('\MHMRentiva\Admin\Core\AssetManager')) {
			\MHMRentiva\Admin\Core\AssetManager::enqueue_core_css();
			\MHMRentiva\Admin\Core\AssetManager::enqueue_core_js();
		} elseif (class_exists('\MHMRentiva\Admin\Core\Utilities\Styles')) {
			// Fallback for older versions if AssetManager doesn't exist
			wp_enqueue_style(\MHMRentiva\Admin\Core\Utilities\Styles::getCssHandle());
			wp_enqueue_style('mhm-vehicle-card-css');
		} else {
			// Last resort fallback
			wp_enqueue_style('mhm-vehicle-card-css');
		}

		// Search results CSS
		wp_enqueue_style(
			'mhm-rentiva-search-results-css',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/search-results.css',
			array('mhm-vehicle-card-css'),
			MHM_RENTIVA_VERSION . '-' . filemtime(MHM_RENTIVA_PLUGIN_DIR . 'assets/css/frontend/search-results.css'),
			'all'
		);

		// Search results JavaScript
		wp_enqueue_script(
			'mhm-rentiva-search-results-js',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/search-results.js',
			array('jquery', 'mhm-vehicle-interactions'),
			MHM_RENTIVA_VERSION . '-' . filemtime(MHM_RENTIVA_PLUGIN_DIR . 'assets/js/frontend/search-results.js'),
			true
		);

		// Ensure vehicle interaction globals are available on block-only pages.
		if (wp_script_is('mhm-vehicle-interactions', 'enqueued')) {
			wp_localize_script(
				'mhm-vehicle-interactions',
				'mhm_rentiva_vars',
				array(
					'ajax_url'         => admin_url('admin-ajax.php'),
					'nonce'            => wp_create_nonce('mhm_rentiva_toggle_favorite'),
					'fav_nonce'        => wp_create_nonce('mhm_rentiva_toggle_favorite'),
					'compare_nonce'    => wp_create_nonce('mhm_rentiva_toggle_compare'),
					'compare_page_url' => \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicle_comparison'),
					'favorites_page_url' => \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_my_favorites'),
				)
			);
		}

		// Localize script
		wp_localize_script(
			'mhm-rentiva-search-results-js',
			'mhmRentivaSearchResults',
			array(
				'ajax_url'        => admin_url('admin-ajax.php'),
				'nonce'           => wp_create_nonce('mhm_rentiva_search_results'),
				'current_url'     => home_url(add_query_arg(null, null)),
				'search_page_url' => ShortcodeUrlManager::get_page_url('rentiva_search'),
				'i18n'            => array(
					'loading'                => __('Loading...', 'mhm-rentiva'),
					'no_results'             => __('No vehicles found', 'mhm-rentiva'),
					'error'                  => __('An error occurred. Please try again.', 'mhm-rentiva'),
					'filter_applied'         => __('Filter applied', 'mhm-rentiva'),
					'clear_filters'          => __('Clear all filters', 'mhm-rentiva'),
					'clear_all'              => __('Clear All', 'mhm-rentiva'),
					/* translators: %d: count of filters. */
					'clear_all_with_count'   => __('Clear All (%d)', 'mhm-rentiva'),
					'try_adjusting'          => __('Try adjusting your search criteria or filters.', 'mhm-rentiva'),
					'back_to_search'         => __('Back to Search', 'mhm-rentiva'),
					'added_to_favorites'     => __('Added to favorites', 'mhm-rentiva'),
					'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
					'per_day'                => __('/day', 'mhm-rentiva'),
					'seats'                  => __('seats', 'mhm-rentiva'),
					'review'                 => __('review', 'mhm-rentiva'),
					'reviews'                => __('reviews', 'mhm-rentiva'),
					'view_details'           => __('View Details', 'mhm-rentiva'),
					'previous'               => __('Previous', 'mhm-rentiva'),
					'next'                   => __('Next', 'mhm-rentiva'),
					'error'                  => __('Error', 'mhm-rentiva'),
					'try_again'              => __('Try Again', 'mhm-rentiva'),
					/* translators: %d: number of vehicles. */
					'vehicle_found'          => __('%d vehicle found', 'mhm-rentiva'),
					/* translators: %d: number of vehicles. */
					'vehicles_found'         => __('%d vehicles found', 'mhm-rentiva'),
				),
				'favorite_nonce'  => wp_create_nonce('mhm_rentiva_toggle_favorite'),
				'icons'           => array(
					'heart' => \MHMRentiva\Helpers\Icons::get('heart'),
				),
			)
		);
	}

	/**
	 * Get search parameters from URL parameters
	 */
	private static function get_search_params_from_url(): array
	{
		return array(
			'keyword'      => self::get_text('keyword'),
			'pickup_date'  => self::get_text('pickup_date'),
			'return_date'  => self::get_text('return_date'),
			// Legacy support
			'start_date'   => self::get_text('start_date', self::get_text('pickup_date')),
			'end_date'     => self::get_text('end_date', self::get_text('return_date')),
			'min_price'    => self::get_int('min_price'),
			'max_price'    => self::get_int('max_price'),
			'fuel_type'    => self::get_text('fuel_type'),
			'transmission' => self::get_text('transmission'),
			'seats'        => self::get_text('seats'),
			'brand'        => self::get_text('brand'),
			'year_min'     => self::get_int('year_min'),
			'year_max'     => self::get_int('year_max'),
			'mileage_max'  => self::get_int('mileage_max'),
			'category'     => self::get_text('category'),
			'sort'         => self::get_text('sort', 'relevance'),
			'page'         => self::get_int('page', 1),
		);
	}

	/**
	 * Perform search operation
	 */
	private static function perform_search(array $params, array $atts): array
	{
		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['results_per_page'],
			'paged'          => $params['page'] ?? 1,
			'meta_query'     => array(),
		);

		// ⭐ Submission Integrity: Enforce minimum rental days
		$pickup_date = ! empty($params['pickup_date']) ? $params['pickup_date'] : (! empty($params['start_date']) ? $params['start_date'] : '');
		$return_date = ! empty($params['return_date']) ? $params['return_date'] : (! empty($params['end_date']) ? $params['end_date'] : '');

		if (! empty($pickup_date) && ! empty($return_date)) {
			$min_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_min_rental_days', 1);
			$start_ts = strtotime($pickup_date);
			$end_ts   = strtotime($return_date);

			if ($start_ts && $end_ts) {
				$diff_days = round(($end_ts - $start_ts) / (60 * 60 * 24));
				if ($diff_days < $min_days) {
					// Logic: If user bypassed JS and sent invalid date range
					// Force an impossible condition to return no results safely
					$args['post__in'] = array(0);
				}
			}
		}

		// Keyword search
		if (! empty($params['keyword'])) {
			$args['s'] = $params['keyword'];
		}

		// Price range
		if ($params['min_price'] > 0 || $params['max_price'] > 0) {
			$price_query = array(
				'key'  => '_mhm_rentiva_price_per_day',
				'type' => 'NUMERIC',
			);

			if ($params['min_price'] > 0 && $params['max_price'] > 0) {
				$price_query['value']   = array($params['min_price'], $params['max_price']);
				$price_query['compare'] = 'BETWEEN';
			} elseif ($params['min_price'] > 0) {
				$price_query['value']   = $params['min_price'];
				$price_query['compare'] = '>=';
			} elseif ($params['max_price'] > 0) {
				$price_query['value']   = $params['max_price'];
				$price_query['compare'] = '<=';
			}

			$args['meta_query'][] = $price_query;
		}

		// Fuel type (process as array)
		if (! empty($params['fuel_type'])) {
			$fuel_values          = is_array($params['fuel_type']) ? $params['fuel_type'] : array($params['fuel_type']);
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_fuel_type',
				'value'   => $fuel_values,
				'compare' => 'IN',
			);
		}

		// Transmission type (process as array)
		if (! empty($params['transmission'])) {
			$transmission_values  = is_array($params['transmission']) ? $params['transmission'] : array($params['transmission']);
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_transmission',
				'value'   => $transmission_values,
				'compare' => 'IN',
			);
		}

		// Seat count (process as array)
		if (! empty($params['seats'])) {
			$seats_values         = is_array($params['seats']) ? $params['seats'] : array($params['seats']);
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_seats',
				'value'   => $seats_values,
				'compare' => 'IN',
			);
		}

		// Brand (process as array)
		if (! empty($params['brand'])) {
			$brand_values         = is_array($params['brand']) ? $params['brand'] : array($params['brand']);
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_brand',
				'value'   => $brand_values,
				'compare' => 'IN',
			);
		}

		// Year range
		if ($params['year_min'] > 0 || $params['year_max'] > 0) {
			$year_query = array(
				'key'  => '_mhm_rentiva_year',
				'type' => 'NUMERIC',
			);

			if ($params['year_min'] > 0 && $params['year_max'] > 0) {
				$year_query['value']   = array($params['year_min'], $params['year_max']);
				$year_query['compare'] = 'BETWEEN';
			} elseif ($params['year_min'] > 0) {
				$year_query['value']   = $params['year_min'];
				$year_query['compare'] = '>=';
			} elseif ($params['year_max'] > 0) {
				$year_query['value']   = $params['year_max'];
				$year_query['compare'] = '<=';
			}

			$args['meta_query'][] = $year_query;
		}

		// Mileage
		if ($params['mileage_max'] > 0) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_mileage',
				'value'   => $params['mileage_max'],
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		// Sorting
		switch ($params['sort']) {
			case 'price_asc':
				$args['meta_key'] = '_mhm_rentiva_price_per_day';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price_desc':
				$args['meta_key'] = '_mhm_rentiva_price_per_day';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'name_asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'name_desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;
			case 'year_desc':
				$args['meta_key'] = '_mhm_rentiva_year';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default: // relevance
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
		}

		// Meta query relation
		if (count($args['meta_query']) > 1) {
			$args['meta_query']['relation'] = 'AND';
		}

		// Optimization: Inject Availability Filter
		$args[self::SEARCH_CONTEXT_KEY] = true;
		$availability_filter = function ($where, \WP_Query $query) use ($params) {
			if ($query->get(self::SEARCH_CONTEXT_KEY) !== true) {
				return $where;
			}

			// Generate subquery using central helper
			$subquery = QueryHelper::get_availability_subquery(
				$params['pickup_date'],
				$params['return_date']
			);

			return $where . $subquery;
		};

		add_filter('posts_where', $availability_filter, 10, 2);

		try {
			// Execute query
			$query = new \WP_Query($args);
		} finally {
			// Mandatory Scope Isolation
			remove_filter('posts_where', $availability_filter, 10);
		}

		// Deep Batch Priming: Optimization for N+1 Attachments and Pages
		if (! empty($query->posts)) {
			$vehicle_ids    = wp_list_pluck($query->posts, 'ID');
			$attachment_ids = array();

			// 1. Prime vehicles and their meta
			_prime_post_caches($vehicle_ids, true, true);

			// 2. Collect attachment IDs (thumbnails)
			foreach ($vehicle_ids as $vid) {
				$tid = get_post_thumbnail_id($vid);
				if ($tid) {
					$attachment_ids[] = (int) $tid;
				}
			}

			// 3. Collect Booking Page ID for URL cache priming
			$booking_page_id = ShortcodeUrlManager::get_page_id('rentiva_booking_form');
			if ($booking_page_id) {
				$attachment_ids[] = (int) $booking_page_id;
			}

			// 4. Batch prime all collected dependencies
			if (! empty($attachment_ids)) {
				_prime_post_caches(array_unique($attachment_ids), true, true);
			}
		}

		// Format results
		$vehicles = array();
		if ($query->have_posts()) {
			// Prime static caches for this batch
			VehicleFeatureHelper::prime_static_feature_map();

			while ($query->have_posts()) {
				$query->the_post();
				$vehicles[] = self::format_vehicle_data(get_the_ID(), $atts);
			}
			wp_reset_postdata();
		}

		$current_page = $params['page'] ?? 1;

		return array(
			'vehicles'     => $vehicles,
			'total'        => (int) $query->found_posts,
			'current_page' => (int) $current_page,
			'per_page'     => (int) $atts['results_per_page'],
			'max_pages'    => (int) $query->max_num_pages,
			'pagination'   => array(
				'current'   => (int) $current_page,
				'total'     => (int) $query->max_num_pages,
				'has_prev'  => $current_page > 1,
				'has_next'  => $current_page < $query->max_num_pages,
				'prev_page' => max(1, $current_page - 1),
				'next_page' => min($query->max_num_pages, $current_page + 1),
			),
		);
	}

	/**
	 * Formats vehicle data using canonical VehiclesList logic
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @param array $atts Shortcode attributes
	 * @return array Standardized vehicle data
	 */
	private static function format_vehicle_data(int $vehicle_id, array $atts): array
	{
		// Default atts for canonical formatting if missing
		$defaults = array(
			'max_features' => 5,
			'image_size'   => 'medium',
			'price_format' => 'daily',
		);
		$merged_atts = wp_parse_args($atts, $defaults);

		return VehiclesList::get_vehicle_data_for_shortcode($vehicle_id, $merged_atts);
	}

	/**
	 * Gets filter options
	 */
	/**
	 * Gets filter options
	 */
	private static function get_filter_options(array $current_params): array
	{
		// Caching Strategy: Search filters are global and expensive to compute.
		// We cache them for 24 hours, invalidated on vehicle save/update.
		$cache_key = 'mhm_rentiva_search_filters_v1';
		$cached    = get_transient($cache_key);

		if (false !== $cached && is_array($cached)) {
			// Validation: Ensure cache isn't effectively empty if it shouldn't be
			if (! empty($cached['brands']) || ! empty($cached['fuel_types'])) {
				return $cached;
			}
		}

		global $wpdb;

		// Fuel types - Get keys and convert to labels
		$fuel_type_keys = $wpdb->get_col(
			"
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_fuel_type' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        "
		);

		$fuel_types_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
		$fuel_types     = array();
		if (! empty($fuel_type_keys)) {
			foreach ($fuel_type_keys as $key) {
				if (isset($fuel_types_map[$key])) {
					$fuel_types[$key] = $fuel_types_map[$key];
				} else {
					$fuel_types[$key] = $key; // Fallback to key if label not found
				}
			}
		}

		// Transmission types - Get keys and convert to labels
		$transmission_keys = $wpdb->get_col(
			"
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_transmission' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        "
		);

		$transmissions_map = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();
		$transmissions     = array();
		if (! empty($transmission_keys)) {
			foreach ($transmission_keys as $key) {
				if (isset($transmissions_map[$key])) {
					$transmissions[$key] = $transmissions_map[$key];
				} else {
					$transmissions[$key] = $key; // Fallback to key if label not found
				}
			}
		}

		// Seat counts
		$seats = $wpdb->get_col(
			"
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_seats' 
            AND meta_value != '' 
            ORDER BY CAST(meta_value AS UNSIGNED) ASC
        "
		);

		// Brands
		$brands = $wpdb->get_col(
			"
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_brand' 
            AND meta_value != '' 
            ORDER BY meta_value ASC
        "
		);

		// Year range
		$year_range = $wpdb->get_row(
			"
            SELECT 
                MIN(CAST(meta_value AS UNSIGNED)) as min_year,
                MAX(CAST(meta_value AS UNSIGNED)) as max_year
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_year' 
            AND meta_value != '' 
            AND meta_value REGEXP '^[0-9]+$'
        "
		);

		// Price range
		$price_range = $wpdb->get_row(
			"
            SELECT 
                MIN(CAST(meta_value AS DECIMAL(10,2))) as min_price,
                MAX(CAST(meta_value AS DECIMAL(10,2))) as max_price
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_mhm_rentiva_price_per_day' 
            AND meta_value != '' 
            AND meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
        "
		);

		$data = array(
			'fuel_types'    => $fuel_types ?: array(),
			'transmissions' => $transmissions ?: array(),
			'seats'         => $seats ?: array(),
			'brands'        => $brands ?: array(),
			'year_range'    => array(
				'min' => (int) ($year_range->min_year ?? 1990),
				'max' => (int) ($year_range->max_year ?? gmdate('Y')),
			),
			'price_range'   => array(
				'min' => (float) ($price_range->min_price ?? 0),
				'max' => (float) ($price_range->max_price ?? 10000),
			),
		);

		// Cache Logic: 24 Hours
		// Empty State Protection: If no brands/fuel types found, cache for only 5 minutes
		// to prevent "permanent empty state" if cache warms up before vehicles are added.
		$is_empty = empty($data['brands']) && empty($data['fuel_types']);
		$ttl      = $is_empty ? 300 : DAY_IN_SECONDS;

		set_transient($cache_key, $data, $ttl);

		return $data;
	}

	/**
	 * Gets sorting options
	 */
	private static function get_sorting_options(): array
	{
		return array(
			'relevance'  => __('Most Relevant', 'mhm-rentiva'),
			'price_asc'  => __('Price: Low to High', 'mhm-rentiva'),
			'price_desc' => __('Price: High to Low', 'mhm-rentiva'),
			'name_asc'   => __('Name: A to Z', 'mhm-rentiva'),
			'name_desc'  => __('Name: Z to A', 'mhm-rentiva'),
			'year_desc'  => __('Newest First', 'mhm-rentiva'),
		);
	}

	/**
	 * AJAX: Update filter results
	 */
	public static function ajax_filter_results(): void
	{
		check_ajax_referer('mhm_rentiva_search_results', 'nonce');

		try {
			// Get filters from POST parameters
			$search_params = array(
				'keyword'      => self::post_text('keyword'),
				'pickup_date'  => self::post_text('pickup_date'),
				'return_date'  => self::post_text('return_date'),
				'min_price'    => self::post_int('min_price'),
				'max_price'    => self::post_int('max_price'),
				'fuel_type'    => self::post_text_or_array('fuel_type'),
				'transmission' => self::post_text_or_array('transmission'),
				'seats'        => self::post_text_or_array('seats'),
				'brand'        => self::post_text_or_array('brand'),
				'year_min'     => self::post_int('year_min'),
				'year_max'     => self::post_int('year_max'),
				'mileage_max'  => self::post_int('mileage_max'),
				'sort'         => self::post_text('sort', 'relevance'),
				'page'         => self::post_int('page', 1),
			);

			$atts = array(
				'layout'               => self::post_text('layout', 'grid'),
				'results_per_page'     => self::post_int('per_page', 12),
				'show_favorite_button' => self::post_text('show_favorite_button', '1'),
				'show_compare_button'  => self::post_text('show_compare_button', '1'),
				'show_booking_btn'     => self::post_text('show_booking_btn', '1'),
				'show_price'           => self::post_text('show_price', '1'),
				'show_title'           => self::post_text('show_title', '1'),
				'show_features'        => self::post_text('show_features', '1'),
				'show_rating'          => self::post_text('show_rating', '1'),
				'show_badges'          => self::post_text('show_badges', '1'),
			);

			$results = self::perform_search($search_params, $atts);

			wp_send_json_success(
				array(
					'html'       => self::render_vehicles_list($results['vehicles'], $atts['layout'], $atts),
					'pagination' => self::render_pagination($results['pagination']),
					'meta'       => array(
						'total'        => (int) $results['total'],
						'max_pages'    => (int) $results['max_pages'],
						'current_page' => (int) $results['current_page'],
						'layout'       => $atts['layout'],
					),
				)
			);
		} catch (Exception $e) {
			$debug_mode = defined('WP_DEBUG') && WP_DEBUG;
			$message    = \MHMRentiva\Admin\Core\SecurityHelper::get_safe_error_message(
				$e->getMessage(),
				$debug_mode
			);

			wp_send_json_error(
				array(
					'message' => $message,
				)
			);
		}
	}

	/**
	 * Check vehicle availability
	 *
	 * @param int $vehicle_id Vehicle ID
	 * @return array
	 */
	private static function check_vehicle_availability(int $vehicle_id): array
	{
		$status = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($vehicle_id);
		$is_available = ($status === 'active');

		return array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva'),
		);
	}

	/**
	 * Renders vehicles list
	 *
	 * @param array $vehicles Vehicles list
	 * @param string $layout Layout type
	 * @return string
	 */
	private static function render_vehicles_list(array $vehicles, string $layout, array $atts = array()): string
	{
		if (empty($vehicles)) {
			return '<div class="rv-no-results">' . __('No vehicles found matching your criteria.', 'mhm-rentiva') . '</div>';
		}

		$html = '';
		foreach ($vehicles as $vehicle) {
			$html .= self::render_vehicle_card($vehicle, $layout, $atts);
		}

		return $html;
	}

	/**
	 * Renders single vehicle card using canonical partial
	 *
	 * @param array $vehicle Standardized vehicle data
	 * @param string $layout Layout type (grid/list)
	 * @return string
	 */
	public static function render_vehicle_card(array $vehicle, string $layout, array $atts = array()): string
	{
		if (empty($vehicle)) {
			return '';
		}

		$card_atts = wp_parse_args(
			$atts,
			array(
				'show_favorite_button' => true,
				'show_compare_button'  => true,
				'show_booking_btn'     => true,
				'show_price'           => true,
				'show_title'           => true,
				'show_features'        => true,
				'show_rating'          => true,
				'show_badges'          => true,
				'booking_url'          => VehiclesList::get_booking_url(),
			)
		);

		// Use the standardized partial directly
		// All data preparation is already handled by format_vehicle_data/VehiclesList
		return Templates::render(
			'partials/vehicle-card',
			array(
				'vehicle' => $vehicle,
				'layout'  => $layout,
				'atts'    => $card_atts,
			),
			true
		);
	}

	/**
	 * Renders pagination HTML for AJAX/template use
	 *
	 * @param array $pagination Pagination data
	 * @return string HTML
	 */
	public static function render_pagination(array $pagination): string
	{
		if (empty($pagination) || (int) $pagination['total'] <= 1) {
			return '';
		}

		$pagination_args = array(
			'base'         => '%_%',
			'total'        => (int) $pagination['total'],
			'current'      => (int) $pagination['current'],
			'format'       => '?page=%#%',
			'show_all'     => false,
			'end_size'     => 1,
			'mid_size'     => 2,
			'prev_next'    => true,
			'prev_text'    => esc_html__('Previous', 'mhm-rentiva'),
			'next_text'    => esc_html__('Next', 'mhm-rentiva'),
			'type'         => 'list',
			'add_args'     => false,
			'add_fragment' => '',
		);

		return (string) paginate_links($pagination_args);
	}
}

