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


// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Featured vehicles shortcode intentionally uses bounded selection/meta queries.





use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use WP_Query;

final class FeaturedVehicles extends AbstractShortcode {

	/** Renders inside the front-end standard's shell. */
	protected static bool $rvs_shell = true;

	public const SHORTCODE = 'rentiva_featured_vehicles';

	/** How long an empty list is remembered, in seconds. */
	private const EMPTY_LIST_TTL = 300;

	/**
	 * The meta keys that decide whether a vehicle is on a cached list.
	 */
	private const LIST_META_KEYS = array(
		MetaKeys::VEHICLE_FEATURED,
		MetaKeys::VEHICLE_LIFECYCLE_STATUS,
		MetaKeys::VEHICLE_STATUS,
		...\MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::SERVICE_META_KEYS,
	);

	/**
	 * The list's cache follows every change that decides membership, whichever path
	 * makes it (bulk action, REST, import, lifecycle job), not only the edit screen.
	 */
	public static function register(): void
	{
		parent::register();

		add_action('transition_post_status', array( self::class, 'forget_list_on_status' ), 10, 3);
		add_action('deleted_post', array( self::class, 'forget_list_on_delete' ), 10, 2);
		foreach (array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook) {
			add_action($hook, array( self::class, 'forget_list_on_meta' ), 10, 3);
		}
	}

	/**
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       The post.
	 */
	public static function forget_list_on_status($new_status, $old_status, $post): void
	{
		if ($new_status !== $old_status && $post instanceof \WP_Post && PT_Vehicle::POST_TYPE === $post->post_type) {
			\MHMRentiva\Admin\Core\Utilities\CacheManager::invalidate_type('vehicle_list');
		}
	}

	/**
	 * @param int           $post_id The deleted post.
	 * @param \WP_Post|null $post    The post.
	 */
	public static function forget_list_on_delete($post_id, $post = null): void
	{
		unset($post_id);
		if ($post instanceof \WP_Post && PT_Vehicle::POST_TYPE === $post->post_type) {
			\MHMRentiva\Admin\Core\Utilities\CacheManager::invalidate_type('vehicle_list');
		}
	}

	/**
	 * @param int|array<int> $meta_id  Meta row ID (an array when deleting).
	 * @param int            $post_id  The post.
	 * @param string         $meta_key The key.
	 */
	public static function forget_list_on_meta($meta_id, $post_id, $meta_key): void
	{
		unset($meta_id);
		if (in_array($meta_key, self::LIST_META_KEYS, true) && PT_Vehicle::POST_TYPE === get_post_type( (int) $post_id)) {
			\MHMRentiva\Admin\Core\Utilities\CacheManager::invalidate_type('vehicle_list');
		}
	}

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

		$tag        = self::get_shortcode_tag();
		$layout_key = $tag . ':' . $layout;

		if (! isset(self::$enqueued_layouts[ $tag ])) {
			// First render: delegate to parent for base assets (notifications, etc.).
			parent::enqueue_assets_once($atts);
			self::$enqueued_layouts[ $tag ] = true;
		} elseif (! isset(self::$enqueued_layouts[ $layout_key ])) {
			// New layout variant: enqueue only its layout-specific assets.
			self::enqueue_assets($atts);
		}

		self::$enqueued_layouts[ $layout_key ] = true;
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
			'ids'                  => '',      // Comma separated IDs
			'exclude'              => '',      // Comma separated IDs left out of the list
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
			'show_booking_button'  => '1',
			'show_features'        => '1',
			'max_features'         => '5',
			'show_brand'           => '0',
			'show_compare_button'  => '1',
			'show_favorite_button' => '1',
			'image_size'           => 'large',
			'filter_categories'    => '',
			'view_all_url'         => '',
			'view_all_text'        => '',
		);
	}

	protected static function prepare_template_data(array $atts): array
	{

		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) ( $atts['limit'] ?? 6 ),
			'orderby'        => sanitize_key( (string) ( $atts['orderby'] ?? 'date' )),
			'order'          => sanitize_key( (string) ( $atts['order'] ?? 'DESC' )),
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

		// Leave out the given vehicles (the similar-vehicles list drops its own page's vehicle).
		$excluded = array_values(array_filter(array_map('absint', explode(',', (string) ( $atts['exclude'] ?? '' )))));
		if (! empty($excluded)) {
			$args['post__not_in'] = $excluded;
			// WP_Query ignores post__not_in once post__in is set, so drop them from post__in too.
			if (isset($args['post__in'])) {
				$args['post__in'] = array_values(array_diff($args['post__in'], $excluded));
				if (array() === $args['post__in']) {
					$args['post__in'] = array( 0 ); // Everything excluded: match nothing, not everything.
				}
			}
		}

		// Filter by Category
		if (! empty($atts['category'])) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'mhmrentiva_vehicle_category',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( (string) $atts['category']),
				),
			);
		}

		$args = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::restrict_query_to_service($args, 'rental');
		$args = apply_filters('mhmrentiva_vehicle_query_args', $args, 'featured_vehicles', array(), $atts);

		$cache_key   = 'featured_' . md5(wp_json_encode($args));
		$vehicle_ids = \MHMRentiva\Admin\Core\Utilities\CacheManager::get_cache('vehicle_list', $cache_key);

		if (false === $vehicle_ids) {
			// No total is read, and the batch priming below fills the caches.
			$query       = new WP_Query(
				array_merge(
					\MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::prefilter_service_clause($args),
					array(
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				)
			);
			$vehicle_ids = $query->posts;

			// An empty answer is an answer: without this every render on a site with no
			// featured vehicle asks again. Shorter, as a safety net for writes that
			// bypass the hooks in register().
			\MHMRentiva\Admin\Core\Utilities\CacheManager::set_cache('vehicle_list', $cache_key, $vehicle_ids, array() === $vehicle_ids ? self::EMPTY_LIST_TTL : 3600);
		}

		// Performance: Prime caches for all retrieved vehicle IDs (Batch execution to solve N+1)
		if (! empty($vehicle_ids)) {
			$int_vehicle_ids = array_map('intval', $vehicle_ids);
			_prime_post_caches($int_vehicle_ids, true, true);
			// The cards' pickup points (vendor fallback) in one batch, not one per card.
			\MHMRentiva\Admin\Core\LocationContext::prime_vehicles($int_vehicle_ids);
		}

		// Standardize vehicle data using canonical VehiclesList helper
		$standardized_vehicles = array();
		if (! empty($vehicle_ids)) {
			foreach ($vehicle_ids as $v_id) {
				$standardized_vehicles[] = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data_for_shortcode( (int) $v_id, $atts);
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
			wp_enqueue_style('mhm-rentiva-swiper-css');
			wp_enqueue_script('mhm-rentiva-swiper');
		}

		// Module specific styles
		$files[] = 'assets/css/frontend/featured-vehicles.css';

		return $files;
	}

	/**
	 * The slider layout stays in featured-vehicles.css; the card itself is the
	 * front-end standard's (rvs-card.css), not the old vehicle-card.css.
	 */
	protected static function get_css_dependencies(): array
	{
		return array( 'mhm-rentiva-rvs-card' );
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
		return array( 'jquery', 'mhm-rentiva-vehicle-interactions', 'mhm-rentiva-swiper' );
	}

	protected static function get_js_config(): array
	{
		return array();
	}
}
