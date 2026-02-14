<?php

declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vehicle list shortcode uses bounded filter/sort and aggregate lookup queries.

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Admin\Vehicle\Helpers\RatingSortHelper;
use MHMRentiva\Admin\Services\FavoritesService;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use Exception;

if (! defined('ABSPATH')) {
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
	 *
	 * @param mixed $value Input value
	 * @return string
	 */
	public static function sanitize_text_field_safe($value): string
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
		$post = get_post($post_id);
		if (! $post) {
			return '';
		}

		// 1. Prefer manual excerpt
		$text = $post->post_excerpt;

		// 2. Fallback to content
		if (empty($text)) {
			$text = $post->post_content;
		}

		// Clean up
		$text = strip_shortcodes($text);
		$text = wp_strip_all_tags($text);
		$text = str_replace(array("\r", "\n"), ' ', $text);

		// Limit to ~160 chars (approx 25 words or strict char limit)
		// User requested 120-160 chars.
		if (mb_strlen($text) > 160) {
			$text = mb_substr($text, 0, 157) . '...';
		}

		return $text;
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
		return array(
			'limit'                  => '12',
			'columns'                => '1', // 1, 2, 3, 4 - For list layout
			'orderby'                => 'title', // title, date, price, featured
			'order'                  => 'ASC', // ASC, DESC
			'category'               => '', // Vehicle category
			'featured'               => '0', // 0: all, 1: featured only
			'show_image'             => '1',
			'show_title'             => '1',
			'show_price'             => '1',
			'show_features'          => '1',
			'show_rating'            => '1',
			'show_booking_btn'       => '1',
			'show_favorite_btn'      => '1',
			'show_favorite_button'   => '1',
			'show_category'          => '1',
			'show_brand'             => '0',
			'show_badges'            => '1',
			'show_description'       => '0',
			'show_availability'      => '0',
			'show_compare_btn'       => '1',
			'show_compare_button'    => '1',
			'enable_lazy_load'       => '1',
			'enable_ajax_filtering'  => '0',
			'enable_infinite_scroll' => '0',
			'image_size'             => 'medium',
			'ids'                    => '', // Comma separated vehicle IDs
			'max_features'           => '5',
			'price_format'           => 'daily',
			'class'                  => '',
			'custom_css_class'       => '',
			'min_rating'             => '',
			'min_reviews'            => '',
		);
	}

	/**
	 * Prepares template data
	 */
	protected static function prepare_template_data(array $atts): array
	{
		$vehicles = self::get_vehicles($atts);

		// Attribute Mapping (Block camelCase -> Shortcode snake_case)
		// This ensures block toggles (showTitle) work with PHP logic (show_title)
		$map = array(
			'showTitle'          => 'show_title',
			'showDescription'    => 'show_description',
			'showPrice'          => 'show_price',
			'showFeatures'       => 'show_features',
			'showRating'         => 'show_rating',
			'showBookButton'     => 'show_booking_btn',
			'showImage'          => 'show_image',
			'showFavoriteButton' => 'show_favorite_button',
			'showCompareButton'  => 'show_compare_button',
		);

		foreach ($map as $camel => $snake) {
			if (isset($atts[$camel])) {
				$atts[$snake] = $atts[$camel];
			}
		}

		// Inject custom texts from settings
		$text_settings                 = self::get_text();
		$atts['booking_btn_text']      = $atts['booking_btn_text'] ?? $text_settings['book_now'];
		$atts['view_details_btn_text'] = $atts['view_details_btn_text'] ?? $text_settings['view_details'];

		return array(
			'atts'           => $atts,
			'vehicles'       => $vehicles,
			'total_vehicles' => count($vehicles),
			'has_vehicles'   => ! empty($vehicles),
			'columns'        => intval($atts['columns']),
			// Standard wrapper class
			'wrapper_class'  => self::get_wrapper_class($atts),
			'booking_url'    => self::get_booking_url(),
		);
	}

	/**
	 * Gets vehicles
	 */
	private static function get_vehicles(array $atts): array
	{
		$args = array(
			'post_type'      => 'vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => intval($atts['limit']),
			'orderby'        => $atts['orderby'],
			'order'          => $atts['order'],
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
		if ($atts['featured'] === '1') {
			$args['meta_query'] = array(
				array(
					'key'     => MetaKeys::VEHICLE_FEATURED,
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

		// Specific IDs
		if (! empty($atts['ids'])) {
			$args['post__in']       = array_map('intval', explode(',', $atts['ids']));
			$args['posts_per_page'] = -1; // Ignore limit if IDs provided? Or keep limit? Usually verify IDs.
		}

		// Rating-based sorting & filtering (opt-in via shortcode attributes)
		RatingSortHelper::apply_sort_args($args, $atts['orderby'], $atts['order']);
		RatingSortHelper::apply_filter_args($args, $atts);

		$posts    = get_posts($args);
		$vehicles = array();

		foreach ($posts as $post) {
			$vehicles[] = self::get_vehicle_data_for_shortcode($post->ID, $atts);
		}

		return $vehicles;
	}

	/**
	 * Shortcode register
	 */
	public static function register(): void
	{
		parent::register();

		// AJAX handlers
		add_action('wp_ajax_mhm_rentiva_toggle_favorite', array(self::class, 'ajax_toggle_favorite'));
		add_action('wp_ajax_nopriv_mhm_rentiva_toggle_favorite', array(self::class, 'ajax_toggle_favorite'));
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
	 * Returns CSS dependencies
	 */
	protected static function get_css_dependencies(): array
	{
		return array('mhm-rentiva-core-variables', 'mhm-vehicle-card-css');
	}

	/**
	 * Returns JS dependencies
	 */
	public static function get_js_dependencies(): array
	{
		return array('jquery', 'mhm-vehicle-interactions');
	}

	// ... (skipping to next method)

	/**
	 * Gets single vehicle data (for shortcode)
	 */
	public static function get_vehicle_data_for_shortcode(int $vehicle_id, array $atts): array
	{
		$vehicle = get_post($vehicle_id);
		if (! $vehicle || $vehicle->post_type !== 'vehicle') {
			return array();
		}

		$features = self::get_limited_features($vehicle_id, intval($atts['max_features']));
		foreach ($features as &$feature) {
			$feature['svg'] = self::get_feature_icon_svg($feature['icon'] ?? '');
		}

		// Prepare Image Data
		$image_url  = self::get_vehicle_image($vehicle_id, $atts['image_size']);
		$image_data = array(
			'url' => $image_url,
			'alt' => get_the_title($vehicle_id),
		);

		// Prepare Category Data - Standardized (Taxonomy Only)
		$category_name = self::get_vehicle_category($vehicle_id);

		$category_data = array(
			'name' => $category_name,
			'url'  => '#',
		);

		$brand = get_post_meta($vehicle_id, '_mhm_rentiva_brand', true) ?: '';

		return array(
			'id'           => $vehicle_id,
			'title'        => get_the_title($vehicle_id) ?: '',
			'excerpt'      => self::get_safe_excerpt($vehicle_id),
			'permalink'    => get_permalink($vehicle_id) ?: '',
			'image'        => $image_data, // Standardized key
			'image_url'    => $image_url, // Keep for backward compatibility if needed locally
			'price'        => self::get_vehicle_price($vehicle_id),
			'features'     => $features,
			'category'     => $category_data, // Standardized key
			'brand'        => $brand,
			'booking_url'  => self::get_booking_url(),
			'rating'       => self::get_vehicle_rating($vehicle_id),
			'availability' => self::check_vehicle_availability($vehicle_id),
			'badge'        => self::get_vehicle_badge($vehicle_id),
			'is_featured'  => get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true) === '1',
			'is_favorite'  => self::is_favorite($vehicle_id),
			'price_format' => $atts['price_format'],
		);
	}

	/**
	 * Gets vehicle image
	 */
	public static function get_vehicle_image(int $vehicle_id, ?string $size = null): string
	{
		$image_id = get_post_thumbnail_id($vehicle_id);
		if (! $image_id) {
			return self::get_placeholder_image_url();
		}

		$size      = $size ?: 'medium';
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

		$currency        = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
		$currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

		// Use default value if price is 0
		if (empty($daily_price) || floatval($daily_price) == 0) {
			$daily_price = 1000; // Default price
		}

		return array(
			'raw'       => floatval($daily_price),
			'daily'     => floatval($daily_price),
			'currency'  => $currency,
			'symbol'    => $currency_symbol,
			'formatted' => self::format_price_with_position(floatval($daily_price)),
		);
	}

	/**
	 * Format price with currency position
	 */
	private static function format_price_with_position(float $price): string
	{
		$symbol           = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
		$position         = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency_position', 'right_space');
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
		return get_post_meta($vehicle_id, MetaKeys::VEHICLE_CATEGORY, true) ?: '';
	}

	/**
	 * Gets all data for vehicle (for favorites page)
	 */
	public static function get_vehicle_data(int $vehicle_id): ?array
	{
		$vehicle_post = get_post($vehicle_id);
		if (! $vehicle_post || $vehicle_post->post_type !== 'vehicle') {
			return null;
		}

		return array(
			'id'           => $vehicle_id,
			'title'        => $vehicle_post->post_title,
			'image'        => self::get_vehicle_image($vehicle_id),
			'price'        => self::get_vehicle_price($vehicle_id),
			'features'     => self::get_vehicle_features($vehicle_id),
			'rating'       => self::get_vehicle_rating($vehicle_id),
			'availability' => self::check_vehicle_availability($vehicle_id),
			'badge'        => self::get_vehicle_badge($vehicle_id),
			'category'     => self::get_vehicle_category($vehicle_id),
			'booking_url'  => self::get_booking_url(),
		);
	}

	/**
	 * Get vehicle rating
	 */
	/**
	 * Get vehicle rating
	 */
	public static function get_vehicle_rating(int $vehicle_id): array
	{
		return \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::get_rating($vehicle_id);
	}

	/**
	 * Returns SVG for feature icons
	 */
	public static function get_feature_icon_svg(string $icon): string
	{
		switch ($icon) {
			case 'people':
				return \MHMRentiva\Helpers\Icons::get('users');
			case 'heart-filled':
				return \MHMRentiva\Helpers\Icons::get('heart', array('fill' => 'currentColor'));
			default:
				return \MHMRentiva\Helpers\Icons::get($icon);
		}
	}



	/**
	 * Checks vehicle availability
	 */
	/**
	 * Checks vehicle availability
	 */
	private static function check_vehicle_availability(int $vehicle_id): array
	{
		$status = get_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, true);

		// Fallback for older data or if status is not set
		if (empty($status)) {
			$old_availability = get_post_meta($vehicle_id, MetaKeys::VEHICLE_AVAILABILITY, true);
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

		return array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva'),
		);
	}

	/**
	 * Gets vehicle badge
	 */
	private static function get_vehicle_badge(int $vehicle_id): ?array
	{
		$is_featured = get_post_meta($vehicle_id, MetaKeys::VEHICLE_FEATURED, true) === '1';
		if ($is_featured) {
			return array(
				'text'  => __('Featured', 'mhm-rentiva'),
				'class' => 'featured',
			);
		}

		return null;
	}

	/**
	 * Creates wrapper class
	 */
	private static function get_wrapper_class(array $atts): string
	{
		$classes = array('rv-vehicles-list');

		if (! empty($atts['class'])) {
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
		if (! empty($booking_url)) {
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
		if (! empty($login_url)) {
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
		return array(
			'book_now'               => SettingsCore::get('mhm_rentiva_text_book_now', '') ?: __('Book Now', 'mhm-rentiva'),
			'view_details'           => SettingsCore::get('mhm_rentiva_text_view_details', '') ?: __('View Details', 'mhm-rentiva'),
			'added_to_favorites'     => SettingsCore::get('mhm_rentiva_text_added_to_favorites', '') ?: __('Added to favorites', 'mhm-rentiva'),
			'removed_from_favorites' => SettingsCore::get('mhm_rentiva_text_removed_from_favorites', '') ?: __('Removed from favorites', 'mhm-rentiva'),
			'login_required'         => SettingsCore::get('mhm_rentiva_text_login_required', '') ?: __('You must be logged in to add to favorites', 'mhm-rentiva'),
		);
	}

	protected static function get_localized_data(): array
	{
		$data = parent::get_localized_data();
		$data['icons'] = array(
			'heart' => \MHMRentiva\Helpers\Icons::get('heart'),
		);
		return $data;
	}

	/**
	 * AJAX favorite add/remove
	 *
	 * @return void
	 */
	public static function ajax_toggle_favorite(): void
	{
		// Proxy to Service
		if (class_exists(FavoritesService::class)) {
			FavoritesService::ajax_toggle_favorite();
		} else {
			wp_send_json_error(array('message' => 'Service not available'));
		}
	}



	/**
	 * AJAX: Get vehicle ratings
	 */


	/**
	 * Registers AJAX handlers
	 */
	protected static function register_ajax_handlers(): void
	{
		add_action('wp_ajax_mhm_rentiva_toggle_favorite', array(self::class, 'ajax_toggle_favorite'));
		add_action('wp_ajax_nopriv_mhm_rentiva_toggle_favorite', array(self::class, 'ajax_toggle_favorite'));
	}

	/**
	 * Checks user favorites
	 */
	public static function is_favorite(int $vehicle_id, ?int $user_id = null): bool
	{
		if (! $user_id) {
			$user_id = get_current_user_id();
		}

		if (class_exists(FavoritesService::class)) {
			return FavoritesService::is_favorite($user_id, $vehicle_id);
		}

		// Fallback (should not happen if Service is loaded)
		if (! $user_id) {
			return false;
		}

		$favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true) ?: array();
		return in_array($vehicle_id, $favorites);
	}

	/**
	 * Registers hooks
	 */
	protected static function register_hooks(): void
	{
		parent::register_hooks();
		// Unnecessary page-load cache clearing removed for performance
	}

	/**
	 * Clears shortcode cache (called via hooks or manually)
	 */
	public static function clear_page_cache(): void
	{
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_shortcode_rentiva_vehicles_list_%'
			)
		);
	}

	/**
	 * Cache status check
	 */
	protected static function is_caching_enabled(): bool
	{
		// Disable if caching is explicitly turned off via constant
		if (defined('MHM_RENTIVA_DISABLE_CACHE') && \MHM_RENTIVA_DISABLE_CACHE) {
			return false;
		}

		// Turn off cache in development environment (WP_DEBUG)
		if (defined('WP_DEBUG') && WP_DEBUG) {
			return false;
		}

		// Check if caching is enabled in settings
		if (! SettingsCore::get('mhm_rentiva_enable_shortcode_cache', '1')) {
			return false;
		}

		return parent::is_caching_enabled();
	}
}
