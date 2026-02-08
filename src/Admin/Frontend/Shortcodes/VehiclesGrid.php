<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use Exception;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * VehiclesGrid Shortcode
 *
 * Special shortcode for grid layout - only supports grid layout
 */
class VehiclesGrid extends AbstractShortcode
{

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
			'limit'                  => '12',
			'columns'                => '2', // 2, 3, 4
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
			'show_category'          => '1',
			'show_badges'            => '1',
			'show_description'       => '0',
			'show_availability'      => '0',
			'show_compare_btn'       => '0',
			'enable_lazy_load'       => '1',
			'enable_ajax_filtering'  => '0',
			'enable_infinite_scroll' => '0',
			'image_size'             => 'medium',
			'class'                  => '',
			'custom_css_class'       => '',
		);
	}

	/**
	 * Prepares template data
	 */
	protected static function prepare_template_data(array $atts): array
	{
		$vehicles = self::get_vehicles($atts);

		// Inject custom texts from settings if not already set via shortcode attribute
		$text_settings                 = self::get_text();
		$atts['booking_btn_text']      = $atts['booking_btn_text'] ?? $text_settings['book_now'];
		$atts['view_details_btn_text'] = $atts['view_details_btn_text'] ?? $text_settings['view_details'];

		return array(
			'atts'           => $atts,
			'vehicles'       => $vehicles,
			'total_vehicles' => count($vehicles),
			'has_vehicles'   => ! empty($vehicles),
			'layout_class'   => 'rv-vehicles-grid', // Only grid
			'columns_class'  => 'rv-vehicles-grid--columns-' . $atts['columns'],
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
					'key'     => '_mhm_rentiva_featured',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

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
		$post = get_post($vehicle_id);

		$data = array(
			'id'           => $vehicle_id,
			'title'        => get_the_title($vehicle_id) ?: '',
			'permalink'    => get_permalink($vehicle_id) ?: '',
			'image'        => self::get_vehicle_image($vehicle_id, $atts['image_size']),
			'price'        => self::get_vehicle_price($vehicle_id),
			'features'     => self::get_vehicle_features($vehicle_id),
			'category'     => self::get_vehicle_category($vehicle_id),
			'rating'       => self::get_vehicle_rating($vehicle_id),
			'meta'         => self::get_vehicle_meta($vehicle_id),
			'availability' => self::check_vehicle_availability($vehicle_id),
			'is_featured'  => get_post_meta($vehicle_id, '_mhm_rentiva_featured', true) === '1',
		);

		// Add SVGs to features for consistency
		foreach ($data['features'] as &$feature) {
			$feature['svg'] = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg($feature['icon'] ?? '');
		}

		return $data;
	}

	/**
	 * Checks vehicle availability
	 */
	private static function check_vehicle_availability(int $vehicle_id): array
	{
		$status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);

		// Fallback for older data or if status is not set
		if (empty($status)) {
			$old_availability = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
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
		$price = get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
		if (empty($price)) {
			$price = get_post_meta($vehicle_id, '_mhm_rentiva_daily_price', true);
		}

		$price = floatval($price);

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
		// Calculate current rating from WordPress comments system
		$comments = get_comments(
			array(
				'post_id'    => $vehicle_id,
				'status'     => 'approve',
				'meta_query' => array(
					array(
						'key'     => 'mhm_rating',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$total_rating = 0;
		$count        = 0;

		foreach ($comments as $comment) {
			$rating = intval(get_comment_meta($comment->comment_ID, 'mhm_rating', true));
			if ($rating > 0) {
				$total_rating += $rating;
				++$count;
			}
		}

		$average = $count > 0 ? round($total_rating / $count, 1) : 0;

		return array(
			'average' => $average,
			'count'   => $count,
			'stars'   => self::get_star_rating($average),
		);
	}

	/**
	 * Gets star rating
	 */
	private static function get_star_rating(float $rating): string
	{
		$stars = '';
		for ($i = 1; $i <= 5; $i++) {
			$stars .= ($i <= round($rating)) ? '★' : '☆';
		}
		return $stars;
	}

	/**
	 * Gets vehicle meta data
	 */
	private static function get_vehicle_meta(int $vehicle_id): array
	{
		return array(
			'featured'  => get_post_meta($vehicle_id, '_mhm_rentiva_featured', true) === '1',
			'available' => get_post_meta($vehicle_id, '_mhm_vehicle_availability', true) === 'active',
		);
	}

	/**
	 * Gets wrapper CSS class
	 */
	private static function get_wrapper_class(array $atts): string
	{
		$classes = array('rv-vehicles-grid-wrapper');

		if (! empty($atts['class'])) {
			$classes[] = $atts['class'];
		}

		if (! empty($atts['custom_css_class'])) {
			$classes[] = $atts['custom_css_class'];
		}

		return implode(' ', $classes);
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
	protected static function enqueue_assets(array $atts = []): void
	{
		// CSS
		wp_enqueue_style(
			'mhm-rentiva-vehicles-grid',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vehicles-grid.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'mhm-rentiva-vehicles-grid',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vehicles-grid.js',
			array('jquery'),
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
	protected static function register_ajax_handlers(): void
	{
		// Rating AJAX handlers
		add_action('wp_ajax_mhm_rentiva_get_user_rating', array(self::class, 'ajax_get_user_rating'));
		add_action('wp_ajax_nopriv_mhm_rentiva_get_user_rating', array(self::class, 'ajax_get_user_rating'));
		add_action('wp_ajax_mhm_rentiva_get_vehicle_ratings', array(self::class, 'ajax_get_vehicle_ratings'));
		add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_ratings', array(self::class, 'ajax_get_vehicle_ratings'));
	}

	/**
	 * Registers hooks
	 */
	protected static function register_hooks(): void
	{
		parent::register_hooks();

		// Clear cache on page changes
		add_action('wp_head', array(self::class, 'clear_page_cache'), 1);
		add_action('template_redirect', array(self::class, 'clear_page_cache'), 1);
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

		// Turn off cache for admin users
		if (is_admin() || current_user_can('administrator')) {
			return false;
		}

		// Temporarily turn off cache for testing
		return false;
	}

	/**
	 * AJAX: Submit rating
	 * 
	 * @return void
	 */
	public static function ajax_submit_rating(): void
	{
		// Security check
		if (! check_ajax_referer('mhm_rentiva_toggle_favorite', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Security check failed.', 'mhm-rentiva')));
			return;
		}

		$vehicle_id = intval(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);
		$rating     = intval(isset($_POST['rating']) ? wp_unslash($_POST['rating']) : 0);

		if (! $vehicle_id || ! get_post($vehicle_id)) {
			wp_send_json_error(array('message' => __('Invalid vehicle ID', 'mhm-rentiva')));
		}

		if ($rating < 1 || $rating > 5) {
			wp_send_json_error(array('message' => __('Invalid rating value', 'mhm-rentiva')));
		}

		if (! is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You need to login', 'mhm-rentiva')));
		}

		$user_id = get_current_user_id();

		// Check if user has rated before
		$existing_rating = get_user_meta($user_id, '_mhm_rentiva_vehicle_rating_' . $vehicle_id, true);

		// Save rating
		update_user_meta($user_id, '_mhm_rentiva_vehicle_rating_' . $vehicle_id, $rating);

		// Update vehicle ratings
		self::update_vehicle_rating($vehicle_id);

		wp_send_json_success(
			array(
				'message'    => __('Rating saved', 'mhm-rentiva'),
				'rating'     => $rating,
				'vehicle_id' => $vehicle_id,
				'is_update'  => ! empty($existing_rating),
			)
		);
	}

	/**
	 * AJAX: Get user rating
	 * 
	 * @return void
	 */
	public static function ajax_get_user_rating(): void
	{
		// Security check
		if (! check_ajax_referer('mhm_rentiva_toggle_favorite', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Security check failed.', 'mhm-rentiva')));
			return;
		}

		$vehicle_id = intval(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);

		if (! $vehicle_id || ! get_post($vehicle_id)) {
			wp_send_json_error(array('message' => __('Invalid vehicle ID', 'mhm-rentiva')));
		}

		if (! is_user_logged_in()) {
			wp_send_json_error(array('message' => __('You need to login', 'mhm-rentiva')));
		}

		$user_id = get_current_user_id();
		$rating  = get_user_meta($user_id, '_mhm_rentiva_vehicle_rating_' . $vehicle_id, true);

		wp_send_json_success(
			array(
				'rating'     => intval($rating),
				'vehicle_id' => $vehicle_id,
			)
		);
	}

	/**
	 * AJAX: Get vehicle ratings
	 * 
	 * @return void
	 */
	public static function ajax_get_vehicle_ratings(): void
	{
		// Security check
		if (! check_ajax_referer('mhm_rentiva_toggle_favorite', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Security check failed.', 'mhm-rentiva')));
			return;
		}

		$vehicle_id = intval(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);

		if (! $vehicle_id || ! get_post($vehicle_id)) {
			wp_send_json_error(array('message' => __('Invalid vehicle ID', 'mhm-rentiva')));
		}

		$ratings = self::get_vehicle_rating($vehicle_id);

		wp_send_json_success(
			array(
				'ratings' => $ratings,
			)
		);
	}

	/**
	 * Updates vehicle ratings
	 */
	private static function update_vehicle_rating(int $vehicle_id): void
	{
		global $wpdb;

		$ratings = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = %s 
            AND meta_value IS NOT NULL 
            AND meta_value != ''
        ",
				'_mhm_rentiva_vehicle_rating_' . $vehicle_id
			)
		);

		if (empty($ratings)) {
			update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', 0);
			update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', 0);
			return;
		}

		$total = 0;
		$count = 0;

		foreach ($ratings as $rating) {
			$value = intval($rating->meta_value);
			if ($value >= 1 && $value <= 5) {
				$total += $value;
				++$count;
			}
		}

		$average = $count > 0 ? round($total / $count, 1) : 0;

		update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', $average);
		update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $count);
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
