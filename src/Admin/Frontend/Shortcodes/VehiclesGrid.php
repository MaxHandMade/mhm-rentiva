<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vehicle grid shortcode uses bounded filter/sort and aggregate lookup queries.



use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Vehicle\Helpers\RatingSortHelper;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use Exception;



/**
 * VehiclesGrid Shortcode
 *
 * Special shortcode for grid layout - only supports grid layout
 */
class VehiclesGrid extends AbstractShortcode {




	/**
	 * Returns shortcode tag
	 */
	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_vehicles_grid';
	}

	/**
	 * Returns template file path
	 */
	protected static function get_template_path(): string
	{
		return 'shortcodes/vehicles-grid';
	}

	/**
	 * Returns default attributes for template
	 */
	protected static function get_default_attributes(): array
	{
		return array(
			'limit'                => '12',
			'columns'              => '2', // 2, 3, 4
			'orderby'              => 'title', // title, date, price, featured
			'order'                => 'ASC', // ASC, DESC
			'category'             => '', // Vehicle category
			'featured'             => '0', // 0: all, 1: featured only
			'show_image'           => '1',
			'show_title'           => '1',
			'show_price'           => '1',
			'show_features'        => '1',
			'show_rating'          => '1',
			'show_booking_button'  => '1',
			'show_favorite_button' => '1',
			'show_category'        => '1',
			'show_brand'           => '0',
			'show_badges'          => '1',
			'show_description'     => '0',
			'show_availability'    => '0',
			'show_compare_button'  => '1',
			'enable_lazy_load'     => '1',
			'image_size'           => 'large',
			'view_all_url'         => '',
			'view_all_text'        => '',
			'class'                => '',
			'custom_css_class'     => '',
			'min_rating'           => '',
			'min_reviews'          => '',
			'layout'               => 'grid', // grid, masonry
		);
	}

	/**
	 * Prepares template data
	 */
	protected static function prepare_template_data(array $atts): array
	{
		$atts = wp_parse_args($atts, self::get_default_attributes());

		// Map Gutenberg block sortBy/sortOrder → shortcode orderby/order.
		if ( ! empty( $atts['sort_by'] ) ) {
			$sort_map = array(
				'newest'       => array(
					'orderby' => 'date',
					'order'   => 'DESC',
				),
				'price'        => array(
					'orderby' => 'price',
					'order'   => strtoupper( $atts['sort_order'] ?? 'asc' ),
				),
				'title'        => array(
					'orderby' => 'title',
					'order'   => strtoupper( $atts['sort_order'] ?? 'asc' ),
				),
				'popularity'   => array(
					'orderby' => 'comment_count',
					'order'   => 'DESC',
				),
				'rating'       => array(
					'orderby' => 'rating',
					'order'   => strtoupper( $atts['sort_order'] ?? 'desc' ),
				),
				'rating_count' => array(
					'orderby' => 'rating_count',
					'order'   => strtoupper( $atts['sort_order'] ?? 'desc' ),
				),
				'confidence'   => array(
					'orderby' => 'confidence',
					'order'   => strtoupper( $atts['sort_order'] ?? 'desc' ),
				),
			);
			$mapping  = $sort_map[ $atts['sort_by'] ] ?? null;
			if ( $mapping ) {
				$atts['orderby'] = $mapping['orderby'];
				$atts['order']   = $mapping['order'];
			}
		}

		$vehicles = self::get_vehicles($atts);

		// Inject custom texts from settings if not already set via shortcode attribute
		$text_settings                 = self::get_text();
		$atts['booking_btn_text']      = $atts['booking_btn_text'] ?? $text_settings['book_now'];
		$atts['view_details_btn_text'] = $atts['view_details_btn_text'] ?? $text_settings['view_details'];

		// Attribute Mapping (Block camelCase -> Shortcode snake_case)
		$map = array(
			'showImage'          => 'show_image',
			'showTitle'          => 'show_title',
			'showRating'         => 'show_rating',
			'showPrice'          => 'show_price',
			'showFeatures'       => 'show_features',
			'showBookButton'     => 'show_booking_button',
			'showFavoriteButton' => 'show_favorite_button',
			'showCompareButton'  => 'show_compare_button',
		);
		// FORCE FOR EVIDENCE 6
		//$atts['show_image'] = true;
		//$atts['show_title'] = false;

		foreach ($map as $camel => $snake) {
			if (isset($atts[ $camel ])) {
				$atts[ $snake ] = $atts[ $camel ];
			}
		}

		$layout_class = ( $atts['layout'] === 'masonry' ) ? 'rv-vehicles-masonry' : 'rv-vehicles-grid';

		return array(
			'atts'           => $atts,
			'vehicles'       => $vehicles,
			'total_vehicles' => count($vehicles),
			'has_vehicles'   => ! empty($vehicles),
			'layout_class'   => $layout_class,
			'columns_class'  => $layout_class . '--columns-' . $atts['columns'],
			'wrapper_class'  => self::get_wrapper_class($atts),
			'booking_url'    => self::get_booking_url(),
		);
	}

	/**
	 * Gets texts with fallback to i18n defaults
	 */
	private static function get_text(): array
	{
		return array(
			'book_now'               => SettingsCore::get('mhm_rentiva_text_book_now', '') ?: __('Book Now', 'mhm-rentiva'),
			'view_details'           => SettingsCore::get('mhm_rentiva_text_view_details', '') ?: __('View Details', 'mhm-rentiva'),
			'added_to_favorites'     => SettingsCore::get('mhm_rentiva_text_added_to_favorites', '') ?: __('Added to favorites', 'mhm-rentiva'),
			'removed_from_favorites' => SettingsCore::get('mhm_rentiva_text_removed_from_favorites', '') ?: __('Removed from favorites', 'mhm-rentiva'),
			'login_required'         => SettingsCore::get('mhm_rentiva_text_login_required', '') ?: __('You must be logged in to add to favorites', 'mhm-rentiva'),
		);
	}

	/**
	 * Gets vehicles
	 */
	private static function get_vehicles(array $atts): array
	{
		$atts = wp_parse_args($atts, self::get_default_attributes());

		$args = array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => intval($atts['limit'] ?? 12),
			'orderby'        => (string) ( $atts['orderby'] ?? 'title' ),
			'order'          => (string) ( $atts['order'] ?? 'ASC' ),
			'meta_query'     => array(
				\MHMRentiva\Admin\Core\Utilities\MetaQueryHelper::get_active_vehicle_meta_query(),
			),
		);

		// Category filter
		if (! empty($atts['category'])) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'vehicle_category',
					'field'    => 'slug',
					'terms'    => $atts['category'],
				),
			);
		}

		// Featured filter
		if (( $atts['featured'] ?? '0' ) === '1') {
			$args['meta_query'] = array(
				array(
					'key'     => '_mhm_rentiva_featured',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

		// All vehicle service types (rental, transfer, both) are shown.
		// The vehicle card displays a service type badge so users can distinguish transfer-only vehicles.

		// Meta-based sorting via meta_key + meta_value_num (standard WP pattern).
		// WP_Query merges meta_key into meta_query internally — no JOIN conflict with the
		// active vehicle meta_query above.
		$orderby_key = strtolower( (string) ( $atts['orderby'] ?? 'title' ) );
		$sort_order  = strtoupper( (string) ( $atts['order'] ?? 'ASC' ) );
		if ( $orderby_key === 'price' ) {
			$args['meta_key'] = \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_PRICE_PER_DAY;
			$args['orderby']  = 'meta_value_num';
			$args['order']    = $sort_order;
		} elseif ( $orderby_key === 'featured' ) {
			$args['meta_key'] = '_mhm_rentiva_featured';
			$args['orderby']  = 'meta_value';
			$args['order']    = $sort_order;
		}

		// Rating-based sorting & filtering (opt-in via shortcode attributes)
		RatingSortHelper::apply_sort_args(
			$args,
			(string) ( $atts['orderby'] ?? 'title' ),
			(string) ( $atts['order'] ?? 'ASC' )
		);
		RatingSortHelper::apply_filter_args($args, $atts);

		$posts    = get_posts($args);
		$vehicles = array();

		foreach ($posts as $post) {
			$vehicles[] = self::get_vehicle_data_for_shortcode($post->ID, $atts);
		}

		return $vehicles;
	}

	/**
	 * Prepares single vehicle data for shortcode
	 */
	private static function get_vehicle_data_for_shortcode(int $vehicle_id, array $atts): array
	{
		$post       = get_post($vehicle_id);
		$image_size = isset($atts['image_size']) && is_string($atts['image_size']) && $atts['image_size'] !== ''
			? $atts['image_size']
			: 'large';

		// Resolve location name for display on vehicle card.
		static $location_map = null;
		if ($location_map === null) {
			$location_map = array();
			foreach (\MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_locations('rental') as $loc) {
				$location_map[ (int) $loc->id ] = (string) $loc->name;
			}
		}
		$vehicle_location_id   = (int) get_post_meta($vehicle_id, '_mhm_rentiva_location_id', true);
		$vehicle_location_name = $location_map[ $vehicle_location_id ] ?? '';
		// Fallback to city meta for vendor-submitted vehicles that have no location_id.
		if ('' === $vehicle_location_name) {
			$vehicle_location_name = (string) get_post_meta($vehicle_id, '_mhm_rentiva_vehicle_city', true);
		}

		$data = array(
			'id'            => $vehicle_id,
			'title'         => get_the_title($vehicle_id) ?: '',
			'permalink'     => get_permalink($vehicle_id) ?: '',
			'image'         => self::get_vehicle_image($vehicle_id, $image_size),
			'price'         => self::get_vehicle_price($vehicle_id),
			'features'      => self::get_vehicle_features($vehicle_id),
			'category'      => self::get_vehicle_category($vehicle_id),
			'brand'         => get_post_meta($vehicle_id, '_mhm_rentiva_brand', true) ?: '',
			'rating'        => self::get_vehicle_rating($vehicle_id),
			'meta'          => self::get_vehicle_meta($vehicle_id),
			'availability'  => self::check_vehicle_availability($vehicle_id),
			'is_featured'   => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::is_featured($vehicle_id),
			'is_favorite'   => self::is_favorite($vehicle_id),
			'booking_url'   => self::get_booking_url(),
			'location_name' => $vehicle_location_name,
			'location_id'   => $vehicle_location_id,
		);

		// Add SVGs to features for consistency
		foreach ($data['features'] as &$feature) {
			$feature['svg'] = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg($feature['icon'] ?? '');
		}

		return $data;
	}

	/**
	 * Loads asset files
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// CSS
		wp_enqueue_style(
			'mhm-rentiva-vehicles-grid',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vehicles-grid.css',
			array( 'mhm-vehicle-card-css' ), // Depend on core card logic
			MHM_RENTIVA_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'mhm-rentiva-vehicles-grid',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vehicles-grid.js',
			array( 'jquery' ),
			MHM_RENTIVA_VERSION . '-' . filemtime(MHM_RENTIVA_PLUGIN_PATH . 'assets/js/frontend/vehicles-grid.js'),
			true
		);

		// Localize script
		wp_localize_script(
			'mhm-rentiva-vehicles-grid',
			'mhmRentivaVehiclesGrid',
			array(
				'ajaxUrl'        => admin_url('admin-ajax.php'),
				'nonce'          => wp_create_nonce('mhm_rentiva_toggle_favorite'),
				'bookingUrl'     => self::get_booking_url(),
				'loginUrl'       => self::get_login_url(),
				'isUserLoggedIn' => is_user_logged_in(),
				'i18n'           => array(
					'loading'                => __('Loading...', 'mhm-rentiva'),
					'no_vehicles'            => __('No vehicles found', 'mhm-rentiva'),
					'error'                  => __('An error occurred', 'mhm-rentiva'),
					'book_now'               => __('Book Now', 'mhm-rentiva'),
					'view_details'           => __('View Details', 'mhm-rentiva'),
					'added_to_favorites'     => __('Added to favorites', 'mhm-rentiva'),
					'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
					'login_required'         => __('You must be logged in to add to favorites', 'mhm-rentiva'),
				),
			)
		);
	}

	/**
	 * Checks vehicle availability
	 */
	private static function check_vehicle_availability(int $vehicle_id): array
	{
		$status       = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($vehicle_id);
		$is_available = ( $status === 'active' );

		return array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva'),
		);
	}

	/**
	 * Gets vehicle image
	 */
	private static function get_vehicle_image(int $vehicle_id, string $size = 'medium'): array
	{
		$image_id = get_post_thumbnail_id($vehicle_id);

		if (! $image_id) {
			$placeholder_url = self::get_placeholder_image_url();
			return array(
				'url'    => $placeholder_url,
				'alt'    => get_the_title($vehicle_id) ?: __('Vehicle Image', 'mhm-rentiva'),
				'width'  => 300,
				'height' => 200,
			);
		}

		$image = wp_get_attachment_image_src($image_id, $size);

		return array(
			'url'    => $image[0] ?? self::get_placeholder_image_url(),
			'alt'    => get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: get_the_title($vehicle_id),
			'width'  => $image[1] ?? 300,
			'height' => $image[2] ?? 200,
		);
	}

	/**
	 * Get placeholder image URL with fallback
	 * Checks for placeholder files and falls back to data URI
	 */
	private static function get_placeholder_image_url(): string
	{
		// Try different placeholder file extensions
		$possible_files = array(
			'placeholder-vehicle.jpg',
			'placeholder-vehicle.png',
			'placeholder-vehicle.svg',
			'no-image.jpg',
			'no-image.png',
		);

		foreach ($possible_files as $filename) {
			$file_path = MHM_RENTIVA_PLUGIN_DIR . 'assets/images/' . $filename;
			if (file_exists($file_path)) {
				return MHM_RENTIVA_PLUGIN_URL . 'assets/images/' . $filename;
			}
		}

		// Fallback: Use data URI (SVG with text)
		return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5WZWhpY2xlIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
	}

	/**
	 * Gets vehicle price
	 */
	private static function get_vehicle_price(int $vehicle_id): array
	{
		$price = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);

		return array(
			'raw'       => $price,
			'formatted' => $price > 0 ? self::format_price_with_position($price) : __('Price Not Specified', 'mhm-rentiva'),
		);
	}

	/**
	 * Format price with currency position
	 */
	private static function format_price_with_position(float $price): string
	{
		return CurrencyHelper::format_price($price, 0);
	}

	/**
	 * Gets vehicle features
	 */
	private static function get_vehicle_features(int $vehicle_id): array
	{
		return VehicleFeatureHelper::collect_items($vehicle_id);
	}

	/**
	 * Gets vehicle category
	 */
	private static function get_vehicle_category(int $vehicle_id): array
	{
		$terms = get_the_terms($vehicle_id, 'vehicle_category');

		if (empty($terms) || is_wp_error($terms)) {
			return array(
				'name' => '',
				'slug' => '',
				'url'  => '',
			);
		}

		$term = $terms[0];

		return array(
			'name' => $term->name,
			'slug' => $term->slug,
			'url'  => get_term_link($term),
		);
	}

	/**
	 * Get vehicle rating
	 */
	private static function get_vehicle_rating(int $vehicle_id): array
	{
		return \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::get_rating($vehicle_id);
	}

	/**
	 * Gets star rating
	 */
	private static function get_star_rating(float $rating): string
	{
		$stars = '';
		for ($i = 1; $i <= 5; $i++) {
			$stars .= ( $i <= round($rating) ) ? '★' : '☆';
		}
		return $stars;
	}

	/**
	 * Gets vehicle meta data
	 */
	private static function get_vehicle_meta(int $vehicle_id): array
	{
		return array(
			'featured'  => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::is_featured($vehicle_id),
			'available' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($vehicle_id) === 'active',
		);
	}

	/**
	 * Gets wrapper CSS class
	 */
	private static function get_wrapper_class(array $atts): string
	{
		$classes = array( 'rv-vehicles-grid-wrapper' );

		if (! empty($atts['class'])) {
			$classes[] = $atts['class'];
		}

		if (! empty($atts['custom_css_class'])) {
			$classes[] = $atts['custom_css_class'];
		}

		return implode(' ', $classes);
	}

	/**
	 * Returns JS dependencies
	 */
	protected static function get_js_dependencies(): array
	{
		return array( 'jquery', 'mhm-vehicle-interactions' );
	}

	/**
	 * Returns CSS filename
	 */
	protected static function get_css_filename(): string
	{
		return 'vehicles-grid.css';
	}

	/**
	 * Returns JavaScript filename
	 */
	protected static function get_js_filename(): string
	{
		return 'vehicles-grid.js';
	}

	/**
	 * Loads asset files
	 */


	/**
	 * Gets booking URL
	 */
	private static function get_booking_url(): string
	{
		// First check from settings
		$booking_url = SettingsCore::get('mhm_rentiva_booking_url', '');
		if (! empty($booking_url)) {
			return $booking_url;
		}

		// ShortcodeUrlManager'dan kontrol et
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
		if (! empty($login_url)) {
			return $login_url;
		}

		// ShortcodeUrlManager'dan kontrol et
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
	 * Registers AJAX handlers
	 */
	/**
	 * Registers AJAX handlers
	 */
	protected static function register_ajax_handlers(): void
	{
		// AJAX handlers removed as they were legacy.
	}

	/**
	 * Registers hooks
	 */
	protected static function register_hooks(): void
	{
		parent::register_hooks();

		// Clear cache when vehicle data changes, not on every page load.
		add_action('save_post_vehicle', array( self::class, 'clear_page_cache' ));
		add_action('deleted_post', array( self::class, 'clear_page_cache' ));
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
			$wpdb->query(
				$wpdb->prepare(
					"
                DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
            ",
					'_transient_shortcode_rentiva_vehicles_grid_%'
				)
			);
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

		// Turn off cache for administrators using capability-based gating.
		if (is_admin() || current_user_can('manage_options')) {
			return false;
		}

		return true;
	}



	/**
	 * Checks user favorites
	 */
	public static function is_favorite(int $vehicle_id, ?int $user_id = null): bool
	{
		if (! $user_id) {
			$user_id = get_current_user_id();
		}

		if (! $user_id) {
			return false;
		}

		$favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true);

		if (! is_array($favorites)) {
			$favorites = array_filter(array_map('intval', (array) $favorites));
		}

		return in_array($vehicle_id, $favorites, true);
	}
}
