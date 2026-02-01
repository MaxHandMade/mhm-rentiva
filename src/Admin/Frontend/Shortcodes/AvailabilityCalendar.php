<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use Exception;

if (! defined('ABSPATH')) {
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
		add_action('wp_ajax_mhm_rentiva_availability_unified', array(self::class, 'ajax_unified_availability'));
		add_action('wp_ajax_nopriv_mhm_rentiva_availability_unified', array(self::class, 'ajax_unified_availability'));

		add_action('wp_ajax_mhm_rentiva_get_vehicle_info', array(self::class, 'ajax_get_vehicle_info'));
		add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_info', array(self::class, 'ajax_get_vehicle_info'));

		// Cache invalidation
		add_action('save_post_vehicle_booking', array(self::class, 'clear_availability_cache'), 10, 3);
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
		return array(
			'vehicle_id'      => '',
			'show_pricing'    => apply_filters('mhm_rentiva/availability_calendar/show_pricing', '1'),
			'theme'           => apply_filters('mhm_rentiva/availability_calendar/theme', 'default'),
			'start_date'      => '',
			'months_ahead'    => apply_filters('mhm_rentiva/availability_calendar/months_ahead', '3'),
			'show_weekends'   => apply_filters('mhm_rentiva/availability_calendar/show_weekends', '1'),
			'show_past_dates' => apply_filters('mhm_rentiva/availability_calendar/show_past_dates', '0'),
			'class'           => '',
		);
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
		$css_files = array(
			'assets/css/frontend/availability-calendar.css',
		);

		return $css_files;
	}

	/**
	 * Loads CSS files - Override
	 */
	protected static function enqueue_styles(): void
	{

		$css_files = self::get_css_files();
		foreach ($css_files as $css_file) {
			if (self::asset_exists($css_file)) {
				$handle = self::get_asset_handle();

				// Check for minified version
				$minified_file = str_replace('.css', '.min.css', $css_file);
				$css_url       = MHM_RENTIVA_PLUGIN_URL . (self::asset_exists($minified_file) ? $minified_file : $css_file);

				wp_enqueue_style(
					$handle,
					$css_url,
					self::get_css_dependencies(),
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
		$js_files = self::get_js_files();
		foreach ($js_files as $js_file) {
			if (self::asset_exists($js_file)) {
				$handle = self::get_asset_handle();

				// Check for minified version
				$minified_file = str_replace('.js', '.min.js', $js_file);
				$js_url        = MHM_RENTIVA_PLUGIN_URL . (self::asset_exists($minified_file) ? $minified_file : $js_file);

				wp_enqueue_script(
					$handle,
					$js_url,
					self::get_js_dependencies(),
					MHM_RENTIVA_VERSION . '.' . time(), // Cache busting force while debugging
					true
				);

				// Load JavaScript variables using the custom object name expected by JS
				wp_localize_script($handle, 'mhmRentivaAvailability', self::get_localized_data());
				break;
			}
		}
	}

	/**
	 * Returns JS files
	 */
	protected static function get_js_files(): array
	{
		return array(
			'assets/js/frontend/availability-calendar.js',
		);
	}

	/**
	 * Override asset loading method
	 */
	protected static function enqueue_assets(): void
	{
		// Enqueue Global Notifications System CSS (Forced for reliability)
		wp_enqueue_style(
			'mhm-rentiva-notifications',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/notifications.css',
			array(),
			MHM_RENTIVA_VERSION
		);

		// Load CSS
		self::enqueue_styles();

		// Load JS
		self::enqueue_scripts();

		// Enqueue Booking Form Assets (Required for modal)
		if (class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm')) {
			\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::enqueue_assets();
		}
	}

	/**
	 * Asset loading check - Override
	 */
	protected static function enqueue_assets_once(): void
	{
		$tag = self::get_shortcode_tag();

		// Static cache check
		static $enqueued_assets = array();

		// Load assets
		self::enqueue_assets();
		$enqueued_assets[$tag] = true;
	}

	/**
	 * Returns CSS dependencies
	 */
	protected static function get_css_dependencies(): array
	{
		return array();
	}

	/**
	 * Returns JS dependencies
	 */
	protected static function get_js_dependencies(): array
	{
		return array('jquery');
	}

	protected static function get_script_object_name(): string
	{
		return 'mhmRentivaAvailability';
	}

	protected static function get_localized_strings(): array
	{
		return array(
			'error'             => esc_html__('An error occurred', 'mhm-rentiva'),
			'available'         => esc_html__('Available', 'mhm-rentiva'),
			'unavailable'       => esc_html__('Vehicle Unavailable', 'mhm-rentiva'),
			'outOfOrderMessage' => esc_html__('This vehicle is currently out of order and cannot be booked. Please choose another vehicle.', 'mhm-rentiva'),
			'chooseAnother'     => esc_html__('Choose Another Vehicle', 'mhm-rentiva'),
			'booked'            => esc_html__('Booked', 'mhm-rentiva'),
			'maintenance'       => esc_html__('Maintenance', 'mhm-rentiva'),
			'unknown_error'     => esc_html__('Unknown error', 'mhm-rentiva'),
			// Flattened strings
			'select_vehicle'    => esc_html__('Select Vehicle', 'mhm-rentiva'),
			'close'             => esc_html__('Close', 'mhm-rentiva'),
			'per_day'           => esc_html__('/day', 'mhm-rentiva'),
			'monday'            => esc_html__('Mon', 'mhm-rentiva'),
			'tuesday'           => esc_html__('Tue', 'mhm-rentiva'),
			'wednesday'         => esc_html__('Wed', 'mhm-rentiva'),
			'thursday'          => esc_html__('Thu', 'mhm-rentiva'),
			'friday'            => esc_html__('Fri', 'mhm-rentiva'),
			'saturday'          => esc_html__('Sat', 'mhm-rentiva'),
			'sunday'            => esc_html__('Sun', 'mhm-rentiva'),
			'select_date_first' => esc_html__('Please select a date first.', 'mhm-rentiva'),
			'login_required'    => esc_html__('Please login to add favorites.', 'mhm-rentiva'),
		);
	}

	protected static function get_localized_data(): array
	{
		$data = parent::get_localized_data();

		// Add common Rentiva data
		$data['isUserLoggedIn'] = is_user_logged_in();
		$data['currencySymbol'] = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();
		$data['pluginUrl']      = MHM_RENTIVA_PLUGIN_URL;
		$data['dateFormat']     = get_option('date_format', 'd.m.Y');
		$data['timeFormat']     = get_option('time_format', 'H:i');
		$data['locale']         = get_locale();
		$data['bookingPageUrl'] = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');

		// Add messages for fallback
		$data['messages'] = array(
			'error'   => __('An error occurred.', 'mhm-rentiva'),
			'success' => __('Operation successful.', 'mhm-rentiva'),
		);

		/**
		 * Merge additional strings
		 */
		if (! isset($data['strings']) || ! is_array($data['strings'])) {
			$data['strings'] = array();
		}

		$data['strings'] = array_merge(
			$data['strings'],
			array(
				'login_required'         => __('You must be logged in to add to favorites', 'mhm-rentiva'),
				'added_to_favorites'     => __('Added to favorites', 'mhm-rentiva'),
				'removed_from_favorites' => __('Removed from favorites', 'mhm-rentiva'),
				'error'                  => __('An error occurred', 'mhm-rentiva'),
				'success'                => __('Operation successful', 'mhm-rentiva'),
			)
		);

		/**
		 * Nonce mapping for Availability Calendar
		 * 1. nonce: Used for data loading (mhm_rentiva_availability_nonce)
		 * 2. favoriteNonce: Used for favorite toggling (mhm_rentiva_vehicles_list)
		 */
		$data['nonce']         = wp_create_nonce('mhm_rentiva_availability_nonce');
		$data['favoriteNonce'] = wp_create_nonce('mhm_rentiva_toggle_favorite'); // Use toggle_favorite specifically
		$data['accountNonce']  = $data['favoriteNonce'];

		return $data;
	}

	public static function render(array $atts = array(), ?string $content = null): string
	{
		$defaults = array(
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
		);
		$atts     = shortcode_atts($defaults, $atts, self::SHORTCODE);

		// Prepare template data
		$template_data = self::prepare_template_data($atts);

		// Asset loading
		self::enqueue_assets_once();

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
		$vehicle_id = 0;

		if (! empty($atts['vehicle_id'])) {
			$vehicle = get_post($atts['vehicle_id']);
			if ($vehicle && $vehicle->post_type === 'vehicle') {
				$vehicle_id = intval($atts['vehicle_id']);
			}
		} else {
			// If vehicle ID not provided, get first available vehicle
			$vehicles = get_posts(
				array(
					'post_type'   => 'vehicle',
					'post_status' => array('publish', 'draft', 'private'),
					'numberposts' => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);

			if (! empty($vehicles)) {
				$vehicle_id = $vehicles[0]->ID;
			}
		}

		// Vehicle list (for dropdown)
		$vehicles_list = self::get_vehicles_list();

		// If vehicle not selected but vehicles exist, select first one
		if ($vehicle_id === 0 && ! empty($vehicles_list)) {
			$vehicle_id = $vehicles_list[0]['id'];
		}

		// Get structured vehicle data (High Fidelity)
		$selected_vehicle = $vehicle_id > 0 ? self::get_selected_vehicle_data($vehicle_id) : null;

		// Start month
		$start_month    = ! empty($atts['start_month']) ? $atts['start_month'] : gmdate('Y-m');
		$months_to_show = intval($atts['months_to_show']);

		// Get calendar data (if vehicle exists)
		$availability_data = array();
		if ($vehicle_id > 0) {
			$availability_data = self::get_availability_data($vehicle_id, $start_month, $months_to_show);
		}

		// Get pricing data (if to be shown and vehicle exists)
		$pricing_data = array();
		if ($atts['show_pricing'] === '1' && $vehicle_id > 0) {
			$pricing_data = self::get_pricing_data($vehicle_id, $start_month, $months_to_show);
		}

		return array(
			'atts'              => $atts,
			'selected_vehicle'  => $selected_vehicle,
			'vehicle_id'        => $vehicle_id,
			'vehicles_list'     => $vehicles_list,
			'start_month'       => $start_month,
			'months_to_show'    => $months_to_show,
			'availability_data' => $availability_data,
			'pricing_data'      => $pricing_data,
			'current_user'      => wp_get_current_user(),
		);
	}

	/**
	 * Prepare high-fidelity vehicle data for the card
	 * Reuses logic design from BookingForm for UI consolidation
	 */
	private static function get_selected_vehicle_data(int $vehicle_id): ?array
	{
		$vehicle = get_post($vehicle_id);
		if (! $vehicle || $vehicle->post_type !== 'vehicle') {
			return null;
		}

		$price_per_day   = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);
		$currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

		// Vehicle image
		$image_id  = get_post_thumbnail_id($vehicle_id);
		$image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
		if (! $image_url && class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList')) {
			$image_url = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_image($vehicle_id);
		}

		// Rating information
		$rating = array(
			'average' => 0.0,
			'count'   => 0,
		);
		if (class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList')) {
			$rating_data = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_rating($vehicle_id);
			$rating['average'] = (float) ($rating_data['average'] ?? 0);
			$rating['count']   = (int) ($rating_data['count'] ?? 0);
		}

		// Favorite check
		$is_favorited = false;
		if (class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList')) {
			$is_favorited = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::is_favorite($vehicle_id);
		}

		// Meta information
		$year    = get_post_meta($vehicle_id, '_mhm_rentiva_year', true);
		$mileage = get_post_meta($vehicle_id, '_mhm_rentiva_mileage', true);
		$seats   = get_post_meta($vehicle_id, '_mhm_rentiva_seats', true);

		// Features
		$features = array();
		if (class_exists('\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper')) {
			$features = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::collect_items($vehicle_id);
			if (class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList')) {
				foreach ($features as &$feature) {
					$feature['svg'] = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg($feature['icon'] ?? '');
				}
			}
		}

		// Availability Status
		$status_data = array(
			'is_available' => true,
			'status'       => 'active',
			'text'         => esc_html__('Available', 'mhm-rentiva'),
		);

		$status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);
		if (empty($status)) {
			$status = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true) ?: 'active';
		}

		// Normalize
		if ($status === '1' || $status === 'active' || $status === 'evet') {
			$status = 'active';
		} else {
			$status = 'maintenance';
		}

		$is_available = ($status === 'active');
		$status_data = array(
			'is_available' => $is_available,
			'status'       => $status,
			'text'         => $is_available ? esc_html__('Available', 'mhm-rentiva') : esc_html__('Out of Order', 'mhm-rentiva'),
		);

		return array(
			'id'              => $vehicle_id,
			'title'           => $vehicle->post_title,
			'excerpt'         => wp_trim_words($vehicle->post_excerpt, 20),
			'price_per_day'   => $price_per_day,
			'formatted_price' => number_format((float) $price_per_day, 0, ',', '.'),
			'currency_symbol' => $currency_symbol,
			'rating'          => $rating,
			'favorite'        => $is_favorited,
			'image_url'       => $image_url,
			'features'        => $features,
			'permalink'       => get_permalink($vehicle_id),
			'year'            => $year,
			'mileage'         => $mileage,
			'seats'           => $seats,
			'availability'    => $status_data,
		);
	}

	private static function get_vehicles_list(): array
	{
		// Cache key
		$cache_key = 'availability_calendar_vehicles_list';

		// Check from cache
		$cached_data = \MHMRentiva\Admin\Core\PerformanceHelper::cache_get($cache_key);
		if ($cached_data !== null) {
			return $cached_data;
		}

		// Performance monitoring
		$performance_data = \MHMRentiva\Admin\Core\PerformanceHelper::time_execution(
			function () {
				$vehicles = get_posts(
					array(
						'post_type'   => 'vehicle',
						'post_status' => array('publish', 'draft', 'private'),
						'numberposts' => -1,
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);

				// Batch load vehicle prices to prevent N+1 queries
				$vehicle_ids = array_map(
					function ($vehicle) {
						return $vehicle->ID;
					},
					$vehicles
				);

				$vehicle_data_batch = \MHMRentiva\Admin\Core\PerformanceHelper::batch_load_vehicle_data($vehicle_ids);

				$vehicles_list = array();
				foreach ($vehicles as $vehicle) {
					$vehicle_id = $vehicle->ID;
					$batch_data = $vehicle_data_batch[$vehicle_id] ?? array();

					$price = 0;

					// Get price using helper
					$price = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);

					$vehicles_list[] = array(
						'id'      => $vehicle_id,
						'title'   => $vehicle->post_title,
						'slug'    => $vehicle->post_name,
						'excerpt' => wp_trim_words($vehicle->post_excerpt, 15),
						'price'   => $price,
					);
				}

				return $vehicles_list;
			},
			'vehicles_list_loading'
		);

		$vehicles_list = $performance_data['result'];

		// Save to cache (30 minutes)
		\MHMRentiva\Admin\Core\PerformanceHelper::cache_set(
			$cache_key,
			$vehicles_list,
			1800,
			array('vehicles', 'availability_calendar')
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
		$performance_data = \MHMRentiva\Admin\Core\PerformanceHelper::time_execution(
			function () use ($vehicle_id, $start_month, $months_to_show) {
				global $wpdb;

				$availability_data = array();
				$current_month     = $start_month;

				// Calculate date range for single query
				$start_date = gmdate('Y-m-01', strtotime($start_month));
				$end_date   = gmdate('Y-m-t', strtotime($start_month . ' +' . ($months_to_show - 1) . ' months'));

				// Single optimized query for all months
				$bookings = $wpdb->get_results(
					$wpdb->prepare(
						"
                SELECT 
                    p.ID,
                    p.post_title,
                    pm_start.meta_value as start_date,
                    pm_end.meta_value as end_date,
                    pm_status.meta_value as status,
                    pm_payment.meta_value as payment_status
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id AND pm_vehicle.meta_key = '_mhm_vehicle_id'
                INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key IN ('_mhm_start_date', '_mhm_pickup_date')
                INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key IN ('_mhm_end_date', '_mhm_return_date', '_mhm_dropoff_date')
                LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
                LEFT JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_mhm_payment_status'
                WHERE p.post_type = 'vehicle_booking'
                AND p.post_status IN ('publish', 'pending', 'confirmed')
                AND pm_vehicle.meta_value = %d
                AND pm_start.meta_value <= %s
                AND pm_end.meta_value >= %s
                AND (pm_status.meta_value IS NULL OR pm_status.meta_value NOT IN ('cancelled', 'trash', 'refunded', 'iptal'))
                GROUP BY p.ID
                ORDER BY pm_start.meta_value ASC
            ",
						$vehicle_id,
						$end_date . ' 23:59:59',
						$start_date
					)
				);

				// Get global vehicle status
				$global_status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);
				if (empty($global_status)) {
					$global_status = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true); // Legacy
				}

				// Normalize status (simple normalization, can be extracted to helper if needed)
				if ($global_status === '1' || $global_status === 'evet') {
					$global_status = 'active';
				}
				if ($global_status === '0' || $global_status === 'hayir') {
					$global_status = 'maintenance';
				}

				// Process bookings for all months
				for ($i = 0; $i < $months_to_show; $i++) {
					$month_start = gmdate('Y-m-01', strtotime($current_month));
					$month_end   = gmdate('Y-m-t', strtotime($current_month));

					// Calculate daily states
					$days         = array();
					$current_date = $month_start;

					while ($current_date <= $month_end) {
						$day_status    = 'available';
						$day_bookings  = array();
						$day_occupancy = 0;

						foreach ($bookings as $booking) {
							// Normalize dates to exclude time portion if exists
							$b_start = substr((string) $booking->start_date, 0, 10);
							$b_end   = substr((string) $booking->end_date, 0, 10);

							if ($current_date >= $b_start && $current_date <= $b_end) {
								$day_status     = $booking->status ?: 'booked';
								$day_bookings[] = array(
									'id'             => $booking->ID,
									'title'          => $booking->post_title,
									'status'         => $booking->status ?: 'booked',
									'payment_status' => $booking->payment_status ?: 'unpaid',
								);
								$day_occupancy++;
							}
						}

						// State determination (Restored & Improved for Pending/Partial logic)
						if ($day_occupancy > 0) {
							$is_maintenance = false;
							$has_paid       = false;
							$has_pending    = false;

							foreach ($day_bookings as $db_item) {
								if ($db_item['status'] === 'maintenance') {
									$is_maintenance = true;
								}
								// If it's paid or confirmed, it's a solid booking (Red)
								if ($db_item['payment_status'] === 'paid' || $db_item['status'] === 'confirmed' || $db_item['status'] === 'publish') {
									$has_paid = true;
								}
								// If it's pending (like Bank/EFT or 30-min reserve), it's partial (Yellow)
								if ($db_item['payment_status'] === 'pending' || $db_item['payment_status'] === 'partial' || $db_item['status'] === 'pending') {
									$has_pending = true;
								}
							}

							if ($is_maintenance) {
								$day_status = 'maintenance';
							} elseif ($has_paid) {
								$day_status = 'booked';
							} elseif ($has_pending) {
								$day_status = 'partial';
							} else {
								$day_status = 'booked'; // Fallback
							}
						}

						// Global Status Override
						// If vehicle is in global maintenance mode, all days are maintenance
						if ($global_status === 'maintenance') {
							$day_status = 'maintenance';
						}
						// If vehicle is inactive, available days become unavailable
						if ($global_status === 'inactive' && $day_status === 'available') {
							$day_status = 'unavailable';
						}

						$days[$current_date] = array(
							'status'     => $day_status,
							'bookings'   => $day_bookings,
							'occupancy'  => $day_occupancy,
							'day_number' => gmdate('j', strtotime($current_date)),
							'is_weekend' => in_array(gmdate('N', strtotime($current_date)), array(6, 7)),
							'is_today'   => $current_date === gmdate('Y-m-d'),
							'is_past'    => $current_date < gmdate('Y-m-d'),
						);

						$current_date = gmdate('Y-m-d', strtotime($current_date . ' +1 day'));
					}

					$availability_data[$current_month] = array(
						'month_name' => self::get_month_name($current_month),
						'year'       => gmdate('Y', strtotime($current_month)),
						'days'       => $days,
						'stats'      => array(
							'total_days'     => count($days),
							'available_days' => count(
								array_filter(
									$days,
									function ($day) {
										return $day['status'] === 'available';
									}
								)
							),
							'booked_days'    => count(
								array_filter(
									$days,
									function ($day) {
										return $day['status'] === 'booked';
									}
								)
							),
							'partial_days'   => count(
								array_filter(
									$days,
									function ($day) {
										return $day['status'] === 'partial';
									}
								)
							),
						),
					);

					$current_month = gmdate('Y-m', strtotime($current_month . ' +1 month'));
				}

				return $availability_data;
			},
			"availability_data_{$vehicle_id}"
		);

		$availability_data = $performance_data['result'];

		// Save to cache (60 seconds for better responsiveness)
		\MHMRentiva\Admin\Core\PerformanceHelper::cache_set(
			$cache_key,
			$availability_data,
			60,
			array('vehicles', 'availability', "vehicle_{$vehicle_id}")
		);

		// Debug log
		if ($performance_data['execution_time'] > 1000) { // If slower than 1 second
		}

		return $availability_data;
	}

	private static function get_pricing_data(int $vehicle_id, string $start_month, int $months_to_show): array
	{
		return self::calculate_calendar_pricing($vehicle_id, $start_month, $months_to_show);
	}

	private static function calculate_calendar_pricing(int $vehicle_id, string $start_month, int $months_to_show): array
	{
		$pricing_data  = array();
		$current_month = $start_month;

		for ($i = 0; $i < $months_to_show; $i++) {
			$month_start = gmdate('Y-m-01', strtotime($current_month));
			$month_end   = gmdate('Y-m-t', strtotime($current_month));

			$base_price      = self::get_vehicle_base_price($vehicle_id);
			$weekend_price   = self::get_vehicle_weekend_price($vehicle_id);
			$seasonal_prices = self::get_vehicle_seasonal_prices($vehicle_id);
			$discounts       = self::get_vehicle_discounts($vehicle_id);

			$days         = array();
			$current_date = $month_start;

			while ($current_date <= $month_end) {
				$is_weekend      = in_array(gmdate('N', strtotime($current_date)), array(6, 7));
				$day_price       = $base_price;
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

				$days[$current_date] = array(
					'base_price'      => $base_price,
					'day_price'       => $day_price,
					'is_weekend'      => $is_weekend,
					'has_discount'    => $discount_amount > 0,
					'discount_amount' => $discount_amount,
					'original_price'  => $day_price + $discount_amount,
				);

				$current_date = gmdate('Y-m-d', strtotime($current_date . ' +1 day'));
			}

			$pricing_data[$current_month] = array(
				'month_name'    => self::get_month_name($current_month),
				'year'          => gmdate('Y', strtotime($current_month)),
				'days'          => $days,
				'base_price'    => $base_price,
				'weekend_price' => $weekend_price,
			);

			$current_month = gmdate('Y-m', strtotime($current_month . ' +1 month'));
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
		$base_price = self::get_vehicle_base_price($vehicle_id);
		// Get multiplier from settings, default to 1.2
		$multiplier = (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_vehicle_weekend_multiplier', 1.2);

		return $base_price * $multiplier;
	}

	private static function get_vehicle_seasonal_prices(int $vehicle_id): array
	{
		// Seasonal pricing not yet in meta field
		return array();
	}

	private static function get_vehicle_discounts(int $vehicle_id): array
	{
		// Discounts not yet in meta field
		return array();
	}

	/**
	 * Unified AJAX handler for availability and pricing
	 */
	public static function ajax_unified_availability(): void
	{
		// Nonce check
		$nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
		if (! wp_verify_nonce($nonce, 'mhm_rentiva_availability_nonce')) {
			wp_send_json_error(array('message' => esc_html__('Security check failed.', 'mhm-rentiva')));
			return;
		}

		try {
			$vehicle_id     = intval($_POST['vehicle_id'] ?? 0);
			$start_month    = self::sanitize_text_field_safe($_POST['start_month'] ?? gmdate('Y-m'));
			$months_to_show = intval($_POST['months_to_show'] ?? 1);

			if (! $vehicle_id) {
				wp_send_json_error(array('message' => esc_html__('Vehicle ID is required.', 'mhm-rentiva')));
			}

			// ⭐ OPTIMIZATION: Check for license limits before substantial work
			$booking_limit_reached = false;
			if (class_exists('\MHMRentiva\Admin\Licensing\Restrictions')) {
				$status = \MHMRentiva\Admin\Licensing\Restrictions::check_limits();
				$booking_limit_reached = $status['bookings']['exceeded'] ?? false;
			}

			// Get Both Availability and Pricing
			$availability_data = self::get_availability_data($vehicle_id, $start_month, $months_to_show);
			$pricing_data      = self::get_pricing_data($vehicle_id, $start_month, $months_to_show);

			wp_send_json_success(
				array(
					'availability_data' => $availability_data,
					'pricing_data'      => $pricing_data,
					'limit_reached'     => $booking_limit_reached,
					'message'           => esc_html__('Calendar data updated.', 'mhm-rentiva'),
				)
			);
		} catch (Exception $e) {
			wp_send_json_error(array('message' => esc_html__('An error occurred while retrieving data.', 'mhm-rentiva')));
		}
	}

	public static function ajax_get_vehicle_info(): void
	{
		try {
			// Nonce check
			if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_availability_nonce')) {
				wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
				return;
			}

			$vehicle_id = intval($_POST['vehicle_id'] ?? 0);

			if ($vehicle_id <= 0) {
				wp_send_json_error(__('Invalid vehicle ID', 'mhm-rentiva'));
				return;
			}

			$vehicle = get_post($vehicle_id);
			if (! $vehicle || $vehicle->post_type !== 'vehicle') {
				wp_send_json_error(__('Vehicle not found', 'mhm-rentiva'));
				return;
			}

			// Vehicle image
			$image_url = '';
			if (has_post_thumbnail($vehicle_id)) {
				$image_url = get_the_post_thumbnail_url($vehicle_id, 'medium');
			}

			// Vehicle features (Unified with SVGs)
			$features     = array();
			$fuel_type    = get_post_meta($vehicle_id, '_mhm_rentiva_fuel_type', true);
			$transmission = get_post_meta($vehicle_id, '_mhm_rentiva_transmission', true);
			$seats        = get_post_meta($vehicle_id, '_mhm_rentiva_seats', true);

			if ($fuel_type) {
				$features[] = array(
					'icon' => 'fuel',
					'text' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_fuel_type_label($fuel_type),
					'svg'  => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg('fuel'),
				);
			}
			if ($transmission) {
				$features[] = array(
					'icon' => 'gear',
					'text' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_transmission_label($transmission),
					'svg'  => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg('gear'),
				);
			}
			if ($seats) {
				$features[] = array(
					'icon' => 'people',
					'text' => sprintf(__('%d people', 'mhm-rentiva'), $seats),
					'svg'  => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg('people'),
				);
			}
			// Add default features if needed or more meta
			$yearValue = get_post_meta($vehicle_id, '_mhm_rentiva_year', true);
			if ($yearValue) {
				$features[] = array(
					'icon' => 'calendar',
					'text' => $yearValue,
					'svg'  => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_feature_icon_svg('calendar'),
				);
			}

			// Price
			$price = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_price_per_day($vehicle_id);

			// Rating information
			$rating = array(
				'average' => 0.0,
				'count'   => 0,
			);
			if (class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList')) {
				$rating_data       = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_rating($vehicle_id);
				$rating['average'] = (float) ($rating_data['average'] ?? 0);
				$rating['count']   = (int) ($rating_data['count'] ?? 0);
			}

			$data = array(
				'id'              => $vehicle_id,
				'title'           => $vehicle->post_title,
				'excerpt'         => $vehicle->post_excerpt ?: wp_trim_words($vehicle->post_content, 15),
				'image'           => $image_url ?: '',
				'features'        => $features,
				'price'           => number_format($price, 0, ',', '.'),
				'currency_symbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
				'rating'          => $rating,
				'is_favorite'     => false,
			);

			if (is_user_logged_in()) {
				$user_id   = get_current_user_id();
				$favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true);
				if (is_array($favorites) && in_array($vehicle_id, $favorites)) {
					$data['is_favorite'] = true;
				}
			}

			// Availability Check
			$status = get_post_meta($vehicle_id, '_mhm_vehicle_status', true);
			// Backward compatibility
			if (empty($status)) {
				$status = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
			}

			// Normalize status
			if ($status === '1' || $status === 'evet' || $status === 'yes') {
				$status = 'active';
			}
			if ($status === '0' || $status === 'hayir' || $status === 'no') {
				$status = 'maintenance';
			}
			if (empty($status)) {
				$status = 'active'; // Default
			}

			$data['status']       = $status;
			$data['is_available'] = ($status === 'active');

			$status_labels       = array(
				'active'      => esc_html__('Available', 'mhm-rentiva'),
				'maintenance' => esc_html__('Out of Order', 'mhm-rentiva'),
			);
			$data['status_text'] = $status_labels[$status] ?? esc_html__('Unavailable', 'mhm-rentiva');

			wp_send_json_success($data);
		} catch (Exception $e) {
			wp_send_json_error(array('message' => esc_html__('An error occurred while retrieving vehicle information.', 'mhm-rentiva')));
		}
	}


	/**
	 * Get localized month name
	 * 
	 * @param string $month Month number (01-12)
	 * @return string
	 */
	private static function get_month_name(string $month): string
	{
		$months = array(
			'01' => esc_html__('January', 'mhm-rentiva'),
			'02' => esc_html__('February', 'mhm-rentiva'),
			'03' => esc_html__('March', 'mhm-rentiva'),
			'04' => esc_html__('April', 'mhm-rentiva'),
			'05' => esc_html__('May', 'mhm-rentiva'),
			'06' => esc_html__('June', 'mhm-rentiva'),
			'07' => esc_html__('July', 'mhm-rentiva'),
			'08' => esc_html__('August', 'mhm-rentiva'),
			'09' => esc_html__('September', 'mhm-rentiva'),
			'10' => esc_html__('October', 'mhm-rentiva'),
			'11' => esc_html__('November', 'mhm-rentiva'),
			'12' => esc_html__('December', 'mhm-rentiva'),
		);

		$month_num = gmdate('m', strtotime($month));
		return $months[$month_num] ?? esc_html__('Unknown', 'mhm-rentiva');
	}

	/**
	 * Clear availability cache for a vehicle
	 *
	 * @param int $post_id Post ID
	 * @param \WP_Post $post Post Object
	 * @param bool $update Whether this is an existing post being updated
	 */
	public static function clear_availability_cache($post_id, $post, $update): void
	{
		if ($post->post_type !== 'vehicle_booking') {
			return;
		}

		$vehicle_id = get_post_meta($post_id, '_mhm_vehicle_id', true);
		if ($vehicle_id) {
			\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(array("vehicle_{$vehicle_id}"));
		}
	}
}
