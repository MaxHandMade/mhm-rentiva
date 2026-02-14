<?php

/**
 * Featured Vehicles Shortcode
 *
 * [rentiva_featured_vehicles limit="6" layout="slider"]
 */

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Featured vehicles shortcode intentionally uses bounded selection/meta queries.

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use WP_Query;

final class FeaturedVehicles extends AbstractShortcode
{

	public const SHORTCODE = 'rentiva_featured_vehicles';

	protected static function get_shortcode_tag(): string
	{
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/featured-vehicles';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'title'                => __('Featured Vehicles', 'mhm-rentiva'),
			'ids'                  => '',      // Comma separated IDs
			'category'             => '',      // Category slug
			'limit'                => '6',
			'layout'               => 'grid', // slider, grid
			'columns'              => '3',
			'autoplay'             => '1',
			'interval'             => '5000',
			'orderby'              => 'date',
			'order'                => 'DESC',
			'show_price'           => '1',
			'show_rating'          => '1',
			'show_category'        => '1',
			'show_book_button'     => '1',
			'show_features'        => '1',
			'max_features'         => '5',
			'show_brand'           => '0',
			'show_availability'    => '0',
			'show_compare_button'  => '1',
			'show_badges'          => '1',
			'show_favorite_button' => '1',
			'image_size'           => 'medium_large',
			'price_format'         => 'daily',
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		// Normalize attributes for helper
		$atts['show_favorite_button'] = ($atts['show_favorite_button'] ?? $atts['show_favorite_btn'] ?? '1') === '1';
		$atts['show_compare_button']  = ($atts['show_compare_button'] ?? $atts['show_compare_btn'] ?? '1') === '1';

		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) ($atts['limit'] ?? 6),
			'orderby'        => sanitize_key((string) ($atts['orderby'] ?? 'date')),
			'order'          => sanitize_key((string) ($atts['order'] ?? 'DESC')),
			'fields'         => 'ids', // Only need IDs
		);

		// Filter by IDs
		if (! empty($atts['ids'])) {
			$args['post__in'] = array_map('intval', explode(',', (string) $atts['ids']));
			$args['orderby']  = 'post__in';
		} else {
			// MUST filter by featured meta if no IDs provided.
			// Keep legacy fallback key for backward compatibility.
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => MetaKeys::VEHICLE_FEATURED,
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => '_mhm_rentiva_is_featured',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

		// Filter by Category
		if (! empty($atts['category'])) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'vehicle_category',
					'field'    => 'slug',
					'terms'    => sanitize_text_field((string) $atts['category']),
				),
			);
		}

		$cache_key = 'featured_' . md5(wp_json_encode($args));
		$vehicle_ids = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('vehicle_list', $cache_key);

		if (false === $vehicle_ids) {
			$query = new WP_Query($args);
			$vehicle_ids = $query->posts;

			if (! empty($vehicle_ids)) {
				\MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache('vehicle_list', $cache_key, $vehicle_ids, 3600);
			}
		}

		// Standardize vehicle data using canonical VehiclesList helper
		$standardized_vehicles = array();
		if (! empty($vehicle_ids)) {
			foreach ($vehicle_ids as $v_id) {
				$standardized_vehicles[] = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data_for_shortcode((int) $v_id, $atts);
			}
		}

		return array(
			'atts'      => $atts,
			'vehicles'  => $standardized_vehicles,
			'has_posts' => ! empty($standardized_vehicles),
		);
	}

	protected static function get_css_files(array $atts = array()): array
	{
		$files = array();

		// If using slider layout, enqueue Swiper CSS from vendor
		$layout = $atts['layout'] ?? 'slider';

		if ($layout === 'slider') {
			wp_enqueue_style('mhm-swiper-css');
		}

		// Module specific styles
		$files[] = 'assets/css/frontend/featured-vehicles.css';

		return $files;
	}

	protected static function get_css_dependencies(): array
	{
		return array('mhm-vehicle-card-css');
	}

	protected static function get_js_files(array $atts = array()): array
	{
		$files  = array();
		$layout = $atts['layout'] ?? 'slider';

		if ($layout === 'slider') {
			$files[] = 'assets/js/frontend/featured-vehicles.js';
		}
		return $files;
	}

	protected static function get_js_dependencies(): array
	{
		return array('jquery', 'mhm-vehicle-interactions', 'mhm-swiper');
	}

	protected static function get_js_config(): array
	{
		return array();
	}
}
