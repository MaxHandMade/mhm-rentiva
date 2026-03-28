<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Featured Vehicles Shortcode
 *
 * [rentiva_featured_vehicles limit="6" layout="slider"]
 */


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Featured vehicles shortcode intentionally uses bounded selection/meta queries.





use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use WP_Query;

final class FeaturedVehicles extends AbstractShortcode
{

	public const SHORTCODE = 'rentiva_featured_vehicles';

	/**
	 * Tracks which layout variants have already had their assets enqueued.
	 * Allows slider/carousel assets to load even if a grid variant was rendered first.
	 *
	 * @var array<string, bool>
	 */
	private static array $enqueued_layouts = array();

	/**
	 * Layout-aware asset-once guard.
	 *
	 * On the first ever render of this shortcode the parent handles base assets
	 * (notifications, etc.). For each additional distinct layout variant, only the
	 * layout-specific assets are enqueued — avoiding double-enqueue of shared assets.
	 *
	 * @param array $atts Normalised shortcode attributes.
	 */
	protected static function enqueue_assets_once(array $atts = array()): void
	{
		$layout = $atts['layout'] ?? 'grid';
		// Treat 'carousel' as an alias for 'slider'.
		if ($layout === 'carousel') {
			$layout = 'slider';
		}

		$tag        = static::get_shortcode_tag();
		$layout_key = $tag . ':' . $layout;

		if (! isset(self::$enqueued_layouts[$tag])) {
			// First render: delegate to parent for base assets (notifications, etc.).
			parent::enqueue_assets_once($atts);
			self::$enqueued_layouts[$tag] = true;
		} elseif (! isset(self::$enqueued_layouts[$layout_key])) {
			// New layout variant: enqueue only its layout-specific assets.
			static::enqueue_assets($atts);
		}

		self::$enqueued_layouts[$layout_key] = true;
	}

	/**
	 * Reset layout-specific enqueue tracking.
	 *
	 * FOR TESTING ONLY — call alongside AbstractShortcode::reset_enqueued_assets_for_tests().
	 *
	 * @internal
	 */
	public static function reset_layout_enqueued_for_tests(): void
	{
		self::$enqueued_layouts = array();
	}

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
			'layout'               => 'slider', // slider, grid
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
			'filter_brands'        => '',
			'filter_categories'    => '',
		);
	}

	protected static function prepare_template_data(array $atts): array
	{

		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) ($atts['limit'] ?? 6),
			'orderby'        => sanitize_key((string) ($atts['orderby'] ?? 'date')),
			'order'          => sanitize_key((string) ($atts['order'] ?? 'DESC')),
			'fields'         => 'ids', // Only need IDs
		);

		// Always filter only active vehicles (excludes maintenance/inactive).
		$args['meta_query'] = array(
			\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_active_vehicle_meta_query(),
		);

		// Filter by IDs
		if (! empty($atts['ids'])) {
			$args['post__in'] = array_map('intval', explode(',', (string) $atts['ids']));
			$args['orderby']  = 'post__in';
		} else {
			// MUST filter by featured meta if no IDs provided.
			$args['meta_query'][] = \MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_featured_meta_query('1');
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

		// Exclude transfer-only vehicles from rental-oriented listings.
		if (! isset($args['meta_query'])) {
			$args['meta_query'] = array();
		}
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_rentiva_vehicle_service_type',
				'value'   => 'transfer',
				'compare' => '!=',
			),
			array(
				'key'     => '_rentiva_vehicle_service_type',
				'compare' => 'NOT EXISTS',
			),
		);

		$cache_key = 'featured_' . md5(wp_json_encode($args));
		$vehicle_ids = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('vehicle_list', $cache_key);

		if (false === $vehicle_ids) {
			$query = new WP_Query($args);
			$vehicle_ids = $query->posts;

			if (! empty($vehicle_ids)) {
				\MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache('vehicle_list', $cache_key, $vehicle_ids, 3600);
			}
		}

		// Performance: Prime caches for all retrieved vehicle IDs (Batch execution to solve N+1)
		if (! empty($vehicle_ids)) {
			$int_vehicle_ids = array_map('intval', $vehicle_ids);
			_prime_post_caches($int_vehicle_ids, true, true);
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

		// If using slider/carousel layout, enqueue Swiper CSS and JS from vendor.
		// 'carousel' is treated as an alias for 'slider'.
		$layout = $atts['layout'] ?? 'slider';

		if ($layout === 'slider' || $layout === 'carousel') {
			wp_enqueue_style('mhm-swiper-css');
			wp_enqueue_script('mhm-swiper');
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

		if ($layout === 'slider' || $layout === 'carousel') {
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
