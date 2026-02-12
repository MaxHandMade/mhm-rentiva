<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
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
final class SearchResults extends AbstractShortcode {


	public const SHORTCODE = 'rentiva_search_results';

	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value Value to sanitize
	 * @return string
	 */
	public static function sanitize_text_field_safe( $value ): string {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	public static function register(): void {
		parent::register();

		// AJAX handlers
		add_action( 'wp_ajax_mhm_rentiva_filter_results', array( self::class, 'ajax_filter_results' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_filter_results', array( self::class, 'ajax_filter_results' ) );
	}

	protected static function get_shortcode_tag(): string {
		return 'rentiva_search_results';
	}

	protected static function get_template_path(): string {
		return 'shortcodes/search-results';
	}

	protected static function get_default_attributes(): array {
		return array(
			'layout'               => 'grid',     // grid/list
			'show_filters'         => '1',        // 1/0
			'results_per_page'     => '12',       // Results per page
			'show_pagination'      => '1',        // 1/0
			'show_sorting'         => '1',        // 1/0
			'show_view_toggle'     => '1',        // 1/0
			'show_favorite_button' => '1',        // 1/0
			'show_compare_button'  => '1',        // 1/0
			'default_sort'         => 'relevance', // relevance/price_asc/price_desc/name_asc/name_desc
			'class'                => '',         // Custom CSS class
		);
	}

	protected static function prepare_template_data( array $atts ): array {
		// Get search criteria from URL parameters
		$search_params = self::get_search_params_from_url();

		// Get search results
		$results = self::perform_search( $search_params, $atts );

		// Prepare filter options
		$filter_options = self::get_filter_options( $search_params );

		// Compact Handling: If layout is 'compact', force internal layout to 'grid' but flags 'is_compact'
		$is_compact = ( $atts['layout'] ?? '' ) === 'compact';
		if ( $is_compact ) {
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
			'nonce_field'     => wp_nonce_field( 'mhm_rentiva_search_results', 'mhm_rentiva_search_results_nonce', true, false ),
		);
	}

	public static function render( array $atts = array(), ?string $content = null ): string {
		return parent::render( $atts, $content );
	}

	/**
	 * Enqueue asset files
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		// Core styles and Shared vehicle card styles
		if ( class_exists( '\MHMRentiva\Admin\Core\AssetManager' ) ) {
			\MHMRentiva\Admin\Core\AssetManager::enqueue_core_css();
		} elseif ( class_exists( '\MHMRentiva\Admin\Core\Utilities\Styles' ) ) {
			// Fallback for older versions if AssetManager doesn't exist
			wp_enqueue_style( \MHMRentiva\Admin\Core\Utilities\Styles::getCssHandle() );
			wp_enqueue_style( 'mhm-vehicle-card-css' );
		} else {
			// Last resort fallback
			wp_enqueue_style( 'mhm-vehicle-card-css' );
		}

		// Search results CSS
		wp_enqueue_style(
			'mhm-rentiva-search-results-css',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/search-results.css',
			array( 'mhm-vehicle-card-css' ),
			MHM_RENTIVA_VERSION . '-' . filemtime( MHM_RENTIVA_PLUGIN_DIR . 'assets/css/frontend/search-results.css' ),
			'all'
		);

		// Search results JavaScript
		wp_enqueue_script(
			'mhm-rentiva-search-results-js',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/search-results.js',
			array( 'jquery', 'mhm-vehicle-interactions' ),
			MHM_RENTIVA_VERSION . '-' . filemtime( MHM_RENTIVA_PLUGIN_DIR . 'assets/js/frontend/search-results.js' ),
			true
		);

		// Localize script
		wp_localize_script(
			'mhm-rentiva-search-results-js',
			'mhmRentivaSearchResults',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'mhm_rentiva_search_results' ),
				'current_url'     => home_url( add_query_arg( null, null ) ),
				'search_page_url' => ShortcodeUrlManager::get_page_url( 'rentiva_search' ),
				'i18n'            => array(
					'loading'                => __( 'Loading...', 'mhm-rentiva' ),
					'no_results'             => __( 'No vehicles found', 'mhm-rentiva' ),
					'error'                  => __( 'An error occurred. Please try again.', 'mhm-rentiva' ),
					'filter_applied'         => __( 'Filter applied', 'mhm-rentiva' ),
					'clear_filters'          => __( 'Clear all filters', 'mhm-rentiva' ),
					'clear_all'              => __( 'Clear All', 'mhm-rentiva' ),
					/* translators: %d: count of filters. */
					'clear_all_with_count'   => __( 'Clear All (%d)', 'mhm-rentiva' ),
					'try_adjusting'          => __( 'Try adjusting your search criteria or filters.', 'mhm-rentiva' ),
					'back_to_search'         => __( 'Back to Search', 'mhm-rentiva' ),
					'added_to_favorites'     => __( 'Added to favorites', 'mhm-rentiva' ),
					'removed_from_favorites' => __( 'Removed from favorites', 'mhm-rentiva' ),
					'per_day'                => __( '/day', 'mhm-rentiva' ),
					'seats'                  => __( 'seats', 'mhm-rentiva' ),
					'review'                 => __( 'review', 'mhm-rentiva' ),
					'reviews'                => __( 'reviews', 'mhm-rentiva' ),
					'view_details'           => __( 'View Details', 'mhm-rentiva' ),
					'previous'               => __( 'Previous', 'mhm-rentiva' ),
					'next'                   => __( 'Next', 'mhm-rentiva' ),
					'error'                  => __( 'Error', 'mhm-rentiva' ),
					'try_again'              => __( 'Try Again', 'mhm-rentiva' ),
					/* translators: %d: number of vehicles. */
					'vehicle_found'          => __( '%d vehicle found', 'mhm-rentiva' ),
					/* translators: %d: number of vehicles. */
					'vehicles_found'         => __( '%d vehicles found', 'mhm-rentiva' ),
				),
				'favorite_nonce'  => wp_create_nonce( 'mhm_rentiva_toggle_favorite' ),
			)
		);
	}

	/**
	 * Get search parameters from URL parameters
	 */
	private static function get_search_params_from_url(): array {
		return array(
			'keyword'      => self::sanitize_text_field_safe( isset( $_GET['keyword'] ) ? wp_unslash( $_GET['keyword'] ) : '' ),
			'pickup_date'  => self::sanitize_text_field_safe( isset( $_GET['pickup_date'] ) ? wp_unslash( $_GET['pickup_date'] ) : '' ),
			'return_date'  => self::sanitize_text_field_safe( isset( $_GET['return_date'] ) ? wp_unslash( $_GET['return_date'] ) : '' ),
			// Legacy support
			'start_date'   => self::sanitize_text_field_safe( isset( $_GET['start_date'] ) ? wp_unslash( $_GET['start_date'] ) : ( isset( $_GET['pickup_date'] ) ? wp_unslash( $_GET['pickup_date'] ) : '' ) ),
			'end_date'     => self::sanitize_text_field_safe( isset( $_GET['end_date'] ) ? wp_unslash( $_GET['end_date'] ) : ( isset( $_GET['return_date'] ) ? wp_unslash( $_GET['return_date'] ) : '' ) ),
			'min_price'    => (int) ( isset( $_GET['min_price'] ) ? wp_unslash( $_GET['min_price'] ) : 0 ),
			'max_price'    => (int) ( isset( $_GET['max_price'] ) ? wp_unslash( $_GET['max_price'] ) : 0 ),
			'fuel_type'    => self::sanitize_text_field_safe( isset( $_GET['fuel_type'] ) ? wp_unslash( $_GET['fuel_type'] ) : '' ),
			'transmission' => self::sanitize_text_field_safe( isset( $_GET['transmission'] ) ? wp_unslash( $_GET['transmission'] ) : '' ),
			'seats'        => self::sanitize_text_field_safe( isset( $_GET['seats'] ) ? wp_unslash( $_GET['seats'] ) : '' ),
			'brand'        => self::sanitize_text_field_safe( isset( $_GET['brand'] ) ? wp_unslash( $_GET['brand'] ) : '' ),
			'year_min'     => (int) ( isset( $_GET['year_min'] ) ? wp_unslash( $_GET['year_min'] ) : 0 ),
			'year_max'     => (int) ( isset( $_GET['year_max'] ) ? wp_unslash( $_GET['year_max'] ) : 0 ),
			'mileage_max'  => (int) ( isset( $_GET['mileage_max'] ) ? wp_unslash( $_GET['mileage_max'] ) : 0 ),
			'category'     => self::sanitize_text_field_safe( isset( $_GET['category'] ) ? wp_unslash( $_GET['category'] ) : '' ),
			'sort'         => self::sanitize_text_field_safe( isset( $_GET['sort'] ) ? wp_unslash( $_GET['sort'] ) : 'relevance' ),
			'page'         => (int) ( isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : 1 ),
		);
	}

	/**
	 * Perform search operation
	 */
	private static function perform_search( array $params, array $atts ): array {
		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['results_per_page'],
			'paged'          => $params['page'] ?? 1,
			'meta_query'     => array(),
		);

		// ⭐ Submission Integrity: Enforce minimum rental days
		$pickup_date = ! empty( $params['pickup_date'] ) ? $params['pickup_date'] : ( ! empty( $params['start_date'] ) ? $params['start_date'] : '' );
		$return_date = ! empty( $params['return_date'] ) ? $params['return_date'] : ( ! empty( $params['end_date'] ) ? $params['end_date'] : '' );

		if ( ! empty( $pickup_date ) && ! empty( $return_date ) ) {
			$min_days = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_min_rental_days', 1 );
			$start_ts = strtotime( $pickup_date );
			$end_ts   = strtotime( $return_date );

			if ( $start_ts && $end_ts ) {
				$diff_days = round( ( $end_ts - $start_ts ) / ( 60 * 60 * 24 ) );
				if ( $diff_days < $min_days ) {
					// Logic: If user bypassed JS and sent invalid date range
					// Force an impossible condition to return no results safely
					$args['post__in'] = array( 0 );
				}
			}
		}

		// Keyword search
		if ( ! empty( $params['keyword'] ) ) {
			$args['s'] = $params['keyword'];
		}

		// Price range
		if ( $params['min_price'] > 0 || $params['max_price'] > 0 ) {
			$price_query = array(
				'key'  => '_mhm_rentiva_price_per_day',
				'type' => 'NUMERIC',
			);

			if ( $params['min_price'] > 0 && $params['max_price'] > 0 ) {
				$price_query['value']   = array( $params['min_price'], $params['max_price'] );
				$price_query['compare'] = 'BETWEEN';
			} elseif ( $params['min_price'] > 0 ) {
				$price_query['value']   = $params['min_price'];
				$price_query['compare'] = '>=';
			} elseif ( $params['max_price'] > 0 ) {
				$price_query['value']   = $params['max_price'];
				$price_query['compare'] = '<=';
			}

			$args['meta_query'][] = $price_query;
		}

		// Fuel type (process as array)
		if ( ! empty( $params['fuel_type'] ) ) {
			$fuel_values          = is_array( $params['fuel_type'] ) ? $params['fuel_type'] : array( $params['fuel_type'] );
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_fuel_type',
				'value'   => $fuel_values,
				'compare' => 'IN',
			);
		}

		// Transmission type (process as array)
		if ( ! empty( $params['transmission'] ) ) {
			$transmission_values  = is_array( $params['transmission'] ) ? $params['transmission'] : array( $params['transmission'] );
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_transmission',
				'value'   => $transmission_values,
				'compare' => 'IN',
			);
		}

		// Seat count (process as array)
		if ( ! empty( $params['seats'] ) ) {
			$seats_values         = is_array( $params['seats'] ) ? $params['seats'] : array( $params['seats'] );
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_seats',
				'value'   => $seats_values,
				'compare' => 'IN',
			);
		}

		// Brand (process as array)
		if ( ! empty( $params['brand'] ) ) {
			$brand_values         = is_array( $params['brand'] ) ? $params['brand'] : array( $params['brand'] );
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_brand',
				'value'   => $brand_values,
				'compare' => 'IN',
			);
		}

		// Year range
		if ( $params['year_min'] > 0 || $params['year_max'] > 0 ) {
			$year_query = array(
				'key'  => '_mhm_rentiva_year',
				'type' => 'NUMERIC',
			);

			if ( $params['year_min'] > 0 && $params['year_max'] > 0 ) {
				$year_query['value']   = array( $params['year_min'], $params['year_max'] );
				$year_query['compare'] = 'BETWEEN';
			} elseif ( $params['year_min'] > 0 ) {
				$year_query['value']   = $params['year_min'];
				$year_query['compare'] = '>=';
			} elseif ( $params['year_max'] > 0 ) {
				$year_query['value']   = $params['year_max'];
				$year_query['compare'] = '<=';
			}

			$args['meta_query'][] = $year_query;
		}

		// Mileage
		if ( $params['mileage_max'] > 0 ) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_mileage',
				'value'   => $params['mileage_max'],
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}

		// Sorting
		switch ( $params['sort'] ) {
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
		if ( count( $args['meta_query'] ) > 1 ) {
			$args['meta_query']['relation'] = 'AND';
		}

		// Execute query
		$query = new \WP_Query( $args );

		// Format results
		$vehicles = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$vehicles[] = self::format_vehicle_data( get_the_ID() );
			}
			wp_reset_postdata();
		}

		$current_page = $params['page'] ?? 1;

		return array(
			'vehicles'     => $vehicles,
			'total'        => $query->found_posts,
			'current_page' => $current_page,
			'per_page'     => (int) $atts['results_per_page'],
			'max_pages'    => $query->max_num_pages,
			'pagination'   => array(
				'current'   => $current_page,
				'total'     => $query->max_num_pages,
				'has_prev'  => $current_page > 1,
				'has_next'  => $current_page < $query->max_num_pages,
				'prev_page' => max( 1, $current_page - 1 ),
				'next_page' => min( $query->max_num_pages, $current_page + 1 ),
			),
		);
	}

	/**
	 * Formats vehicle data
	 */
	private static function format_vehicle_data( int $vehicle_id ): array {
		$vehicle = get_post( $vehicle_id );
		if ( ! $vehicle ) {
			return array();
		}

		$featured_image_id  = get_post_thumbnail_id( $vehicle_id );
		$featured_image_url = $featured_image_id ? wp_get_attachment_image_url( $featured_image_id, 'medium' ) : '';

		// Check if vehicle is in user's favorites
		$is_favorite = false;
		if ( is_user_logged_in() ) {
			$user_id   = get_current_user_id();
			$favorites = get_user_meta( $user_id, 'mhm_rentiva_favorites', true );
			if ( is_array( $favorites ) ) {
				$is_favorite = in_array( $vehicle_id, array_map( 'intval', $favorites ), true );
			}
		}

		// Get fuel type and transmission labels
		$fuel_type_key    = get_post_meta( $vehicle_id, '_mhm_rentiva_fuel_type', true );
		$transmission_key = get_post_meta( $vehicle_id, '_mhm_rentiva_transmission', true );

		$fuel_types    = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_fuel_types();
		$transmissions = \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::get_transmission_types();

		$fuel_type_label = ! empty( $fuel_type_key ) && isset( $fuel_types[ $fuel_type_key ] )
			? $fuel_types[ $fuel_type_key ]
			: $fuel_type_key;

		$transmission_label = ! empty( $transmission_key ) && isset( $transmissions[ $transmission_key ] )
			? $transmissions[ $transmission_key ]
			: $transmission_key;

		return array(
			'id'              => $vehicle_id,
			'title'           => $vehicle->post_title,
			'excerpt'         => $vehicle->post_excerpt,
			'url'             => get_permalink( $vehicle_id ) ?: '',
			'featured_image'  => array(
				'url' => $featured_image_url,
				'alt' => $vehicle->post_title,
			),
			'price_per_day'   => (float) get_post_meta( $vehicle_id, MetaKeys::VEHICLE_PRICE_PER_DAY, true ),
			'currency'        => \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency', 'USD' ),
			'currency_symbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
			'brand'           => get_post_meta( $vehicle_id, MetaKeys::VEHICLE_BRAND, true ),
			'model'           => get_post_meta( $vehicle_id, MetaKeys::VEHICLE_MODEL, true ),
			'year'            => get_post_meta( $vehicle_id, MetaKeys::VEHICLE_YEAR, true ),
			'fuel_type'       => $fuel_type_label,
			'transmission'    => $transmission_label,
			'seats'           => get_post_meta( $vehicle_id, MetaKeys::VEHICLE_SEATS, true ),
			'mileage'         => get_post_meta( $vehicle_id, MetaKeys::VEHICLE_MILEAGE, true ),
			'is_favorite'     => $is_favorite,
			'rating'          => array(
				'average' => (float) get_post_meta( $vehicle_id, MetaKeys::VEHICLE_RATING_AVERAGE, true ),
				'count'   => (int) get_post_meta( $vehicle_id, MetaKeys::VEHICLE_RATING_COUNT, true ),
			),
		);
	}

	/**
	 * Gets filter options
	 */
	private static function get_filter_options( array $current_params ): array {
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
		foreach ( $fuel_type_keys as $key ) {
			if ( isset( $fuel_types_map[ $key ] ) ) {
				$fuel_types[ $key ] = $fuel_types_map[ $key ];
			} else {
				$fuel_types[ $key ] = $key; // Fallback to key if label not found
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
		foreach ( $transmission_keys as $key ) {
			if ( isset( $transmissions_map[ $key ] ) ) {
				$transmissions[ $key ] = $transmissions_map[ $key ];
			} else {
				$transmissions[ $key ] = $key; // Fallback to key if label not found
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

		return array(
			'fuel_types'    => $fuel_types ?: array(),
			'transmissions' => $transmissions ?: array(),
			'seats'         => $seats ?: array(),
			'brands'        => $brands ?: array(),
			'year_range'    => array(
				'min' => (int) ( $year_range->min_year ?? 1990 ),
				'max' => (int) ( $year_range->max_year ?? gmdate( 'Y' ) ),
			),
			'price_range'   => array(
				'min' => (float) ( $price_range->min_price ?? 0 ),
				'max' => (float) ( $price_range->max_price ?? 10000 ),
			),
		);
	}

	/**
	 * Gets sorting options
	 */
	private static function get_sorting_options(): array {
		return array(
			'relevance'  => __( 'Most Relevant', 'mhm-rentiva' ),
			'price_asc'  => __( 'Price: Low to High', 'mhm-rentiva' ),
			'price_desc' => __( 'Price: High to Low', 'mhm-rentiva' ),
			'name_asc'   => __( 'Name: A to Z', 'mhm-rentiva' ),
			'name_desc'  => __( 'Name: Z to A', 'mhm-rentiva' ),
			'year_desc'  => __( 'Newest First', 'mhm-rentiva' ),
		);
	}

	/**
	 * AJAX: Update filter results
	 */
	public static function ajax_filter_results(): void {
		check_ajax_referer( 'mhm_rentiva_search_results', 'nonce' );

		try {
			// Get filters from POST parameters
			$search_params = array(
				'keyword'      => self::sanitize_text_field_safe( isset( $_POST['keyword'] ) ? wp_unslash( $_POST['keyword'] ) : '' ),
				'pickup_date'  => self::sanitize_text_field_safe( isset( $_POST['pickup_date'] ) ? wp_unslash( $_POST['pickup_date'] ) : '' ),
				'return_date'  => self::sanitize_text_field_safe( isset( $_POST['return_date'] ) ? wp_unslash( $_POST['return_date'] ) : '' ),
				'min_price'    => (int) ( isset( $_POST['min_price'] ) ? wp_unslash( $_POST['min_price'] ) : 0 ),
				'max_price'    => (int) ( isset( $_POST['max_price'] ) ? wp_unslash( $_POST['max_price'] ) : 0 ),
				'fuel_type'    => isset( $_POST['fuel_type'] ) ? ( is_array( $_POST['fuel_type'] ) ? array_map( 'wp_unslash', $_POST['fuel_type'] ) : wp_unslash( $_POST['fuel_type'] ) ) : array(),
				'transmission' => isset( $_POST['transmission'] ) ? ( is_array( $_POST['transmission'] ) ? array_map( 'wp_unslash', $_POST['transmission'] ) : wp_unslash( $_POST['transmission'] ) ) : array(),
				'seats'        => isset( $_POST['seats'] ) ? ( is_array( $_POST['seats'] ) ? array_map( 'wp_unslash', $_POST['seats'] ) : wp_unslash( $_POST['seats'] ) ) : array(),
				'brand'        => isset( $_POST['brand'] ) ? ( is_array( $_POST['brand'] ) ? array_map( 'wp_unslash', $_POST['brand'] ) : wp_unslash( $_POST['brand'] ) ) : array(),
				'year_min'     => (int) ( isset( $_POST['year_min'] ) ? wp_unslash( $_POST['year_min'] ) : 0 ),
				'year_max'     => (int) ( isset( $_POST['year_max'] ) ? wp_unslash( $_POST['year_max'] ) : 0 ),
				'mileage_max'  => (int) ( isset( $_POST['mileage_max'] ) ? wp_unslash( $_POST['mileage_max'] ) : 0 ),
				'sort'         => self::sanitize_text_field_safe( isset( $_POST['sort'] ) ? wp_unslash( $_POST['sort'] ) : 'relevance' ),
				'page'         => (int) ( isset( $_POST['page'] ) ? wp_unslash( $_POST['page'] ) : 1 ),
			);

			$atts = array(
				'layout'           => self::sanitize_text_field_safe( isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : 'grid' ),
				'results_per_page' => (int) ( isset( $_POST['per_page'] ) ? wp_unslash( $_POST['per_page'] ) : 12 ),
			);

			$results = self::perform_search( $search_params, $atts );

			wp_send_json_success(
				array(
					'html'       => self::render_vehicles_list( $results['vehicles'], $atts['layout'] ),
					'pagination' => $results['pagination'],
					'total'      => $results['total'],
				)
			);
		} catch ( Exception $e ) {
			$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
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
	private static function check_vehicle_availability( int $vehicle_id ): array {
		$status = (string) get_post_meta( $vehicle_id, '_mhm_vehicle_status', true );

		// Fallback for older data or if status is not set
		if ( empty( $status ) ) {
			$old_availability = (string) get_post_meta( $vehicle_id, '_mhm_vehicle_availability', true );
			// Handle legacy values
			if ( $old_availability === '0' || $old_availability === 'passive' || $old_availability === 'inactive' ) {
				$status = 'inactive';
			} elseif ( $old_availability === '1' || $old_availability === 'active' ) {
				$status = 'active';
			} elseif ( $old_availability === 'maintenance' ) {
				$status = 'maintenance';
			} else {
				$status = 'active'; // Default
			}
		}

		$is_available = ( $status === 'active' );

		return array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? __( 'Available', 'mhm-rentiva' ) : __( 'Out of Order', 'mhm-rentiva' ),
		);
	}

	/**
	 * Renders vehicles list
	 *
	 * @param array $vehicles Vehicles list
	 * @param string $layout Layout type
	 * @return string
	 */
	private static function render_vehicles_list( array $vehicles, string $layout ): string {
		if ( empty( $vehicles ) ) {
			return '<div class="rv-no-results">' . __( 'No vehicles found matching your criteria.', 'mhm-rentiva' ) . '</div>';
		}

		$html = '';
		foreach ( $vehicles as $vehicle ) {
			$html .= self::render_vehicle_card( $vehicle, $layout );
		}

		return $html;
	}

	/**
	 * Renders single vehicle card
	 *
	 * @param array $vehicle Vehicle data (from format_vehicle_data)
	 * @param string $layout Layout type
	 * @return string
	 */
	public static function render_vehicle_card( array $vehicle, string $layout ): string {
		if ( empty( $vehicle ) ) {
			return '';
		}

		$vehicle_id = (int) $vehicle['id'];

		// Use global VehicleFeatureHelper for settings-aware feature collection
		// This ensures Search Results show the same features as Grid, List, and Featured modules
		$raw_features = VehicleFeatureHelper::collect_items( $vehicle_id );
		$features     = array();
		foreach ( $raw_features as $item ) {
			$features[] = array(
				'icon'  => $item['icon'] ?? 'default',
				'text'  => $item['text'],
				'value' => $item['text'],
				'svg'   => VehiclesList::get_feature_icon_svg( $item['icon'] ?? 'default' ),
			);
		}

		// Prepare standardized data
		$confidence_data = \MHMRentiva\Admin\Vehicle\Helpers\RatingConfidenceHelper::from_count(
			(int) ( $vehicle['rating']['count'] ?? 0 )
		);

		$standardized_vehicle = array(
			'id'           => $vehicle_id,
			'title'        => $vehicle['title'],
			'permalink'    => $vehicle['url'],
			'image'        => array(
				'url' => $vehicle['featured_image']['url'] ?? '',
				'alt' => $vehicle['featured_image']['alt'] ?? $vehicle['title'],
			),
			'rating'       => array(
				'average'            => (float) $vehicle['rating']['average'],
				'stars'              => \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::get_star_html( (float) $vehicle['rating']['average'] ),
				'count'              => $vehicle['rating']['count'],
				'confidence_key'     => $confidence_data['key'],
				'confidence_label'   => $confidence_data['label'],
				'confidence_tooltip' => $confidence_data['tooltip'],
			),
			'category'     => array(
				'name' => $vehicle['brand'] . ' ' . $vehicle['model'],
				'url'  => '#',
			),
			'availability' => self::check_vehicle_availability( $vehicle_id ),
			'features'     => $features,
			'price'        => array(
				'formatted' => $vehicle['currency_symbol'] . number_format( (float) $vehicle['price_per_day'], 0, ',', '.' ),
			),
			'is_featured'  => false,
			'is_favorite'  => $vehicle['is_favorite'] ?? false,
		);

		// Render partial
		return Templates::render(
			'partials/vehicle-card',
			array(
				'vehicle' => $standardized_vehicle,
				'layout'  => $layout,
				'atts'    => array(
					'show_favorite_button' => $atts['show_favorite_button'] ?? true,
					'show_compare_button'  => $atts['show_compare_button'] ?? true,
					'show_booking_btn'     => true,
					'show_price'           => true,
					'show_title'           => true,
					'show_features'        => true,
					'show_rating'          => true,
					'show_badges'          => true,
					'booking_url'          => VehiclesList::get_booking_url(),
				),
			),
			true
		);
	}
}
